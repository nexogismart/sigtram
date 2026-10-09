<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReporteExpedienteController extends Controller
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Reporte General de Trámites Externos'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Reporte General de Trámites Externos';
        }

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
            'desde' => Carbon::now('America/Lima')->startOfMonth()->format('Y-m-d'),
            'hasta' => Carbon::now('America/Lima')->format('Y-m-d'),
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $this->authorizeReportAccess([3, 6]);

        $data = $request->validate([
            'modo' => 'nullable|string|in:generico,fechas,avanzado',
            'palabra' => 'nullable|string|max:120',
            'campo' => 'nullable|string|max:40',
            'fecha' => 'nullable|date_format:Y-m-d',
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:desde',
            'simple' => 'nullable|boolean',
            'palabra1' => 'nullable|string|max:120',
            'campo1' => 'nullable|string|max:40',
            'palabra2' => 'nullable|string|max:120',
            'campo2' => 'nullable|string|max:40',
            'excluir' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|in:10,15,25,50,100',
        ]);

        $query = $this->baseSearchQuery();
        $this->applyCompanyFilter($query);
        $this->applyDocumentSimpleFilter($query, (bool) ($data['simple'] ?? false));
        $this->applySearchMode($query, $data);

        $perPage = (int) ($data['per_page'] ?? 25);
        $page = (int) ($data['page'] ?? 1);
        $paginator = $query
            ->orderByDesc('p.cdgo_dto_trmte')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => collect($paginator->items())->map(fn ($row) => $this->formatExpedienteRow($row))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function resumen(Request $request): JsonResponse
    {
        $this->authorizeReportAccess([3, 15, 106]);

        $data = $request->validate([
            'tipo' => 'required|string|in:area,cargo,sinfinalizar',
            'desde' => 'required|date_format:Y-m-d',
            'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde',
        ]);

        $rows = $data['tipo'] === 'cargo'
            ? $this->buildCargoReport($data['desde'], $data['hasta'])
            : $this->buildAreaReport($data['desde'], $data['hasta']);

        if ($data['tipo'] === 'sinfinalizar') {
            $rows = array_values(array_filter($rows, fn ($row) => (int) $row->sin_finalizar > 0));
        }

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'totales' => $this->calculateSummaryTotals($rows),
        ]);
    }

    public function resumenDetalle(Request $request): JsonResponse
    {
        $this->authorizeReportAccess([3, 15, 106]);

        $data = $request->validate([
            'tipo' => 'required|string|in:area,cargo',
            'categoria' => 'required|string|in:digitalizar,por_recibir,recibidos,derivados,finalizados,criticos,sin_finalizar',
            'id' => 'required|integer|min:1',
            'desde' => 'required|date_format:Y-m-d',
            'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde',
        ]);

        if ($data['tipo'] === 'area') {
            $rows = $this->listarBandejaArea(
                (int) $data['id'],
                $data['categoria'],
                $data['desde'],
                $data['hasta']
            );

            return response()->json([
                'status' => 'success',
                'data' => $rows,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => count($rows),
                    'total' => count($rows),
                ],
            ]);
        }

        $query = $this->baseSearchQuery()
            ->whereBetween('p.fe_ingr_trmte', [$data['desde'] . ' 00:01:00', $data['hasta'] . ' 23:59:00']);

        $this->applyCompanyFilter($query);
        $this->applyCargoCategory($query, (int) $data['id'], $data['categoria']);

        $items = $query->orderByDesc('p.cdgo_dto_trmte')->limit(2000)->get();

        return response()->json([
            'status' => 'success',
            'data' => $items->map(fn ($row) => $this->formatExpedienteRow($row))->values(),
        ]);
    }

    public function detalle(Request $request): JsonResponse
    {
        $this->authorizeReportAccess([3, 6, 15, 106]);

        $data = $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $row = $this->baseSearchQuery()
            ->where('p.cdgo_dto_trmte', (int) $data['id'])
            ->first();

        if (! $row) {
            return response()->json(['status' => 'error', 'message' => 'Expediente no encontrado'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatExpedienteRow($row),
        ]);
    }

    public function imprimirReporte(Request $request)
    {
        $this->authorizeReportAccess([3, 6, 15, 106]);

        $view = $request->input('view', 'buscador');
        $data = [];

        if ($view === 'buscador') {
            $query = $this->baseSearchQuery();
            $this->applyCompanyFilter($query);
            $this->applyDocumentSimpleFilter($query, (bool) ($request->input('simple', false)));
            $this->applySearchMode($query, $request->all());

            $selectedIds = $request->input('selected_ids');
            if (is_array($selectedIds) && count($selectedIds) > 0) {
                $query->whereIn('p.cdgo_dto_trmte', $selectedIds);
            }

            $rows = $query->orderByDesc('p.cdgo_dto_trmte')
                ->limit(1000)
                ->get()
                ->map(fn ($row) => $this->formatExpedienteRow($row))
                ->values();
            
            $data = ['tipo' => 'buscador', 'filas' => $rows, 'filtros' => $request->all()];
        } else {
            $tipo = $request->input('view', 'area');
            $desde = $request->input('desde');
            $hasta = $request->input('hasta');

            if (!$desde || !$hasta) {
                $desde = Carbon::now('America/Lima')->startOfMonth()->format('Y-m-d');
                $hasta = Carbon::now('America/Lima')->format('Y-m-d');
            }

            $rows = $tipo === 'cargo'
                ? $this->buildCargoReport($desde, $hasta)
                : $this->buildAreaReport($desde, $hasta);

            if ($tipo === 'sinfinalizar') {
                $rows = array_values(array_filter($rows, fn ($row) => (int) $row->sin_finalizar > 0));
            }

            $data = [
                'tipo' => 'resumen',
                'subtipo' => $tipo,
                'filas' => $rows,
                'totales' => $this->calculateSummaryTotals($rows),
                'filtros' => ['desde' => $desde, 'hasta' => $hasta]
            ];
        }

        $pdf = Pdf::loadView('mod_gm.mod_tram_exte.pdf.reporte-general', $data)
                  ->setPaper('a4', 'landscape');

        return $pdf->stream('reporte_expedientes.pdf');
    }

    public function imprimirExpediente($id)
    {
        $this->authorizeReportAccess([3, 6, 15, 106]);

        $row = $this->baseSearchQuery()
            ->where('p.cdgo_dto_trmte', (int) $id)
            ->first();

        if (! $row) {
            abort(404, 'Expediente no encontrado');
        }

        $expediente = $this->formatExpedienteRow($row);

        $pdf = Pdf::loadView('mod_gm.mod_tram_exte.pdf.reporte-expediente-pdf', ['expediente' => $expediente])
                  ->setPaper('a4', 'portrait');

        return $pdf->stream('expediente_' . $expediente['expediente_raw'] . '.pdf');
    }

    private function buildAreaReport(string $desde, string $hasta): array
    {
        $s = $this->schema;
        $from = $desde . ' 00:01';
        $to = $hasta . ' 23:59';

        $areas = DB::table($s . 'a_areas')
            ->where('in_esta', '1')
            ->where('cdgo_area', '<>', 0)
            ->orderBy('de_area')
            ->get(['cdgo_area', 'de_area']);

        $porDestino = collect(DB::select("
            SELECT
                a_en.cdgo_area AS cdgo_area,
                SUM(CASE WHEN (a_sa.cdgo_area IS NULL OR a_sa.cdgo_area <> a_en.cdgo_area) AND r.cdgo_estdo_trmte IN ('5') THEN 1 ELSE 0 END) AS digitalizar,
                SUM(CASE WHEN (a_sa.cdgo_area IS NULL OR a_sa.cdgo_area <> a_en.cdgo_area) AND r.cdgo_estdo_trmte NOT IN ('5','12','29','27') THEN 1 ELSE 0 END) AS por_recibir,
                SUM(CASE WHEN a_sa.cdgo_area = a_en.cdgo_area AND r.cdgo_estdo_trmte NOT IN ('3','26','27') THEN 1 ELSE 0 END) AS recibidos,
                SUM(CASE WHEN a_sa.cdgo_area = a_en.cdgo_area AND r.cdgo_estdo_trmte IN ('3') THEN 1 ELSE 0 END) AS finalizados
            FROM {$s}r_trmtes_dcmntos r
            INNER JOIN {$s}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            INNER JOIN {$s}a_areas a_en ON c_en.cdgo_area = a_en.cdgo_area
            LEFT JOIN {$s}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            LEFT JOIN {$s}a_areas a_sa ON c_sa.cdgo_area = a_sa.cdgo_area
            INNER JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte
            WHERE r.in_esta = '1'
              AND p.fe_ingr_trmte >= ?
              AND p.fe_ingr_trmte <= ?
            GROUP BY a_en.cdgo_area
        ", [$from, $to]))->keyBy('cdgo_area');

        $porOrigen = collect(DB::select("
            SELECT
                a_sa.cdgo_area AS cdgo_area,
                COUNT(*) AS derivados
            FROM {$s}r_trmtes_dcmntos r
            INNER JOIN {$s}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            INNER JOIN {$s}a_areas a_en ON c_en.cdgo_area = a_en.cdgo_area
            LEFT JOIN {$s}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            LEFT JOIN {$s}a_areas a_sa ON c_sa.cdgo_area = a_sa.cdgo_area
            INNER JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte
            WHERE r.in_esta = '1'
              AND a_sa.cdgo_area IS NOT NULL
              AND a_en.cdgo_area <> a_sa.cdgo_area
              AND p.fe_ingr_trmte >= ?
              AND p.fe_ingr_trmte <= ?
            GROUP BY a_sa.cdgo_area
        ", [$from, $to]))->keyBy('cdgo_area');

        $criticosPorArea = $this->contarCriticosPorArea($desde, $hasta);

        $rows = [];
        foreach ($areas as $area) {
            $id = (int) $area->cdgo_area;
            $dest = $porDestino->get($id);
            $orig = $porOrigen->get($id);
            $digitalizar = (int) ($dest->digitalizar ?? 0);
            $porRecibir = (int) ($dest->por_recibir ?? 0);
            $recibidos = (int) ($dest->recibidos ?? 0);
            $finalizados = (int) ($dest->finalizados ?? 0);
            $derivados = (int) ($orig->derivados ?? 0);
            $total = $digitalizar + $porRecibir + $recibidos + $derivados + $finalizados;
            $porcentaje = $total > 0 ? round((($derivados + $finalizados) / $total) * 100, 2) : 0.0;
            $criticos = (int) ($criticosPorArea[$id] ?? 0);

            $row = (object) [
                'cdgo_area' => $id,
                'cdgo_crgo' => null,
                'de_area' => $area->de_area,
                'id' => $id,
                'nombre' => $area->de_area,
                'usuarios' => '',
                'digitalizar' => $digitalizar,
                'por_recibir' => $porRecibir,
                'recibidos' => $recibidos,
                'derivados' => $derivados,
                'finalizados' => $finalizados,
                'criticos' => $criticos,
                'sin_finalizar' => 0,
                'total_doc' => $total,
                'porcentaje' => $porcentaje,
            ];
            $rows[] = $row;
        }

        usort($rows, function ($a, $b) {
            if ($b->porcentaje !== $a->porcentaje) {
                return $b->porcentaje <=> $a->porcentaje;
            }

            return $b->total_doc <=> $a->total_doc;
        });

        return $rows;
    }

    /**
     * Equivalente a ajax_reporte_area.php + gene_expediente_area_*
     */
    private function listarBandejaArea(int $areaId, string $categoria, string $desde, string $hasta): array
    {
        $s = $this->schema;
        $from = $desde . ' 00:01';
        $to = $hasta . ' 23:59';

        $query = DB::table($s . 'r_trmtes_dcmntos as r')
            ->join($s . 'p_crgos as c_en', 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->join($s . 'a_areas as a_en', 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
            ->leftJoin($s . 'p_crgos as c_sa', 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_sa', 'c_sa.cdgo_area', '=', 'a_sa.cdgo_area')
            ->join($s . 'p_dto_trmtes as p', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'p_usrios as u', 'p.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'u.no_crto',
                'p.fe_ingr_trmte',
                'p.de_obse_trmte',
                'r.fe_rcbdo_trmte',
                'r.cdgo_trmte_dcmnto',
            ])
            ->where('r.in_esta', '1')
            ->where('p.fe_ingr_trmte', '>=', $from)
            ->where('p.fe_ingr_trmte', '<=', $to);

        if ($categoria === 'criticos') {
            $idsCriticos = $this->idsCriticos($desde, $hasta);
            if ($idsCriticos === []) {
                return [];
            }
            $query
                ->where('a_en.cdgo_area', $areaId)
                ->whereNotIn('r.cdgo_estdo_trmte', ['3'])
                ->whereIn('p.cdgo_dto_trmte', $idsCriticos);
        } else {
            match ($categoria) {
                'digitalizar' => $query
                    ->where(function ($q) use ($areaId) {
                        $q->whereNull('a_sa.cdgo_area')->orWhere('a_sa.cdgo_area', '<>', $areaId);
                    })
                    ->where('a_en.cdgo_area', $areaId)
                    ->whereIn('r.cdgo_estdo_trmte', ['5']),
                'por_recibir' => $query
                    ->where(function ($q) use ($areaId) {
                        $q->whereNull('a_sa.cdgo_area')->orWhere('a_sa.cdgo_area', '<>', $areaId);
                    })
                    ->where('a_en.cdgo_area', $areaId)
                    ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']),
                'recibidos' => $query
                    ->where('a_sa.cdgo_area', $areaId)
                    ->where('a_en.cdgo_area', $areaId)
                    ->whereNotIn('r.cdgo_estdo_trmte', ['3', '26', '27']),
                'derivados' => $query
                    ->where('a_sa.cdgo_area', $areaId)
                    ->where('a_en.cdgo_area', '<>', $areaId),
                'finalizados' => $query
                    ->where('a_sa.cdgo_area', $areaId)
                    ->where('a_en.cdgo_area', $areaId)
                    ->whereIn('r.cdgo_estdo_trmte', ['3']),
                default => $query->whereRaw('1 = 0'),
            };
        }

        $rows = $query->orderByDesc('p.cdgo_dto_trmte')->get();
        $adjuntos = $this->primerosAdjuntos($rows->pluck('cdgo_dto_trmte')->all());

        return $rows->map(function ($row) use ($adjuntos) {
            $nombre = (string) ($row->no_crto ?? '');
            $obs = (string) ($row->de_obse_trmte ?? '');

            return [
                'id' => (int) $row->cdgo_dto_trmte,
                'expediente' => $row->nu_expe_todo,
                'solicitante' => $nombre,
                'solicitante_corto' => mb_strlen($nombre, 'UTF-8') > 35
                    ? mb_substr($nombre, 0, 35, 'UTF-8')
                    : $nombre,
                'fecha' => $this->formatDateTime($row->fe_ingr_trmte),
                'fecha_ultima' => $this->formatDateTime($row->fe_rcbdo_trmte),
                'descripcion' => $obs,
                'adjunto' => $adjuntos[(int) $row->cdgo_dto_trmte] ?? '',
            ];
        })->values()->all();
    }

    /** @return array<int, int> areaId => count (tipos 17 y 22) */
    private function contarCriticosPorArea(string $desde, string $hasta): array
    {
        $counts = [];
        foreach ($this->candidatosCriticos($desde, $hasta) as $row) {
            if (! in_array((string) $row->cdgo_tpo_trmte, ['17', '22'], true)) {
                continue;
            }
            $areaId = (int) $row->cdgo_area;
            $counts[$areaId] = ($counts[$areaId] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return list<int> */
    private function idsCriticos(string $desde, string $hasta): array
    {
        return collect($this->candidatosCriticos($desde, $hasta))
            ->pluck('cdgo_dto_trmte')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Replica t_trmtes_crtcos: silencio administrativo vencido o por vencer.
     *
     * @return list<object>
     */
    private function candidatosCriticos(string $desde, string $hasta): array
    {
        static $cache = [];
        $key = $desde . '|' . $hasta;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $s = $this->schema;
        $from = $desde . ' 00:01';
        $to = $hasta . ' 23:59';
        $company = $this->currentCompanyId();

        $sql = "
            SELECT p.cdgo_dto_trmte, p.fe_ingr_trmte, p.nu_tiem_sile_adcnal, p.cdgo_tpo_trmte,
                   a_en.cdgo_area, COALESCE(doc.nu_tiem_sile, 0) AS nu_tiem_sile
            FROM {$s}p_dto_trmtes p
            INNER JOIN {$s}r_trmtes_dcmntos r ON r.cdgo_dto_trmte = p.cdgo_dto_trmte
            INNER JOIN {$s}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            INNER JOIN {$s}a_areas a_en ON c_en.cdgo_area = a_en.cdgo_area
            LEFT JOIN {$s}a_docmntos_trmtes doc ON p.cdgo_docmnto_trmte = doc.cdgo_docmnto_trmte
            WHERE r.in_esta = '1'
              AND r.cdgo_estdo_trmte NOT IN ('3','29','27')
              AND p.cdgo_docmnto_trmte IS NOT NULL
              AND p.fe_ingr_trmte >= ?
              AND p.fe_ingr_trmte <= ?
        ";
        $bindings = [$from, $to];
        if ($company > 0) {
            $sql .= ' AND p.cdgo_cmpnia = ?';
            $bindings[] = $company;
        }

        $festivos = $this->festivos();
        $out = [];
        foreach (DB::select($sql, $bindings) as $row) {
            $silen = (int) $row->nu_tiem_sile + (int) ($row->nu_tiem_sile_adcnal ?? 0);
            if ($silen <= 0) {
                continue;
            }
            $transcurridos = $this->diasHabilesDesde($row->fe_ingr_trmte, $festivos);
            if ($transcurridos >= ($silen - 1)) {
                $out[] = $row;
            }
        }

        $cache[$key] = $out;

        return $out;
    }

    /** @param array<string, bool> $festivos */
    private function diasHabilesDesde($fechaIngreso, array $festivos): int
    {
        if (! $fechaIngreso) {
            return 0;
        }
        $inicio = Carbon::parse(substr((string) $fechaIngreso, 0, 10))->startOfDay();
        $hoy = Carbon::today();
        $diasCalendario = (int) round(($hoy->getTimestamp() - $inicio->getTimestamp()) / 86400);
        if ($diasCalendario <= 0) {
            return 0;
        }

        $total = 0;
        $cursor = $inicio->copy();
        for ($i = 1; $i <= $diasCalendario; $i++) {
            $cursor->addDay();
            $n = (int) $cursor->format('N');
            if ($n === 6 || $n === 7) {
                continue;
            }
            if (isset($festivos[$cursor->toDateString()])) {
                continue;
            }
            $total++;
        }

        return $total;
    }

    /** @return array<string, bool> */
    private function festivos(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            $rows = DB::table($this->schema . 'a_dias_fstvos')
                ->where('in_esta', '1')
                ->get(['fe_dias_fstvos']);
            foreach ($rows as $row) {
                if ($row->fe_dias_fstvos) {
                    $cache[Carbon::parse($row->fe_dias_fstvos)->toDateString()] = true;
                }
            }
        } catch (\Throwable $e) {
            $cache = [];
        }

        return $cache;
    }

    /** @param list<int|string> $ids @return array<int, string> */
    private function primerosAdjuntos(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $s = $this->schema;
        $rows = DB::select("
            SELECT DISTINCT ON (cdgo_dto_trmte) cdgo_dto_trmte, de_urls_imag
            FROM {$s}r_trmtes_dcmntos
            WHERE cdgo_dto_trmte IN (" . implode(',', $ids) . ")
              AND de_urls_imag IS NOT NULL
              AND de_urls_imag <> ''
            ORDER BY cdgo_dto_trmte, cdgo_trmte_dcmnto ASC
        ");

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->cdgo_dto_trmte] = (string) $row->de_urls_imag;
        }

        return $out;
    }

    private function formatDateTime($value): string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y H:i') : '';
    }

    private function buildCargoReport(string $desde, string $hasta): array
    {
        $s = $this->schema;
        $from = $desde . ' 00:00:00';
        $to = $hasta . ' 23:59:59';
        $areaId = $this->currentAreaId();

        $sql = "
            SELECT
                c.cdgo_crgo,
                c.de_crgo AS de_area,
                STRING_AGG(DISTINCT u.no_crto, ' - ') AS usuarios,
                0 AS digitalizar,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND (c_sa.cdgo_crgo IS NULL OR c_sa.cdgo_crgo != c.cdgo_crgo) AND r.cdgo_estdo_trmte NOT IN ('5','12','29','27') THEN 1 ELSE 0 END), 0) AS por_recibir,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND c_sa.cdgo_crgo = c.cdgo_crgo AND c_en.cdgo_crgo = c.cdgo_crgo AND r.cdgo_estdo_trmte NOT IN ('3','26','27') THEN 1 ELSE 0 END), 0) AS recibidos,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND c_sa.cdgo_crgo = c.cdgo_crgo AND c_en.cdgo_crgo != c.cdgo_crgo THEN 1 ELSE 0 END), 0) AS derivados,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND c_sa.cdgo_crgo = c.cdgo_crgo AND c_en.cdgo_crgo = c.cdgo_crgo AND r.cdgo_estdo_trmte IN ('3') THEN 1 ELSE 0 END), 0) AS finalizados,
                0 AS criticos,
                COALESCE(SUM(CASE WHEN p.cdgo_dto_trmte IS NOT NULL AND c_en.cdgo_crgo = c.cdgo_crgo AND r.cdgo_estdo_trmte != '3' THEN 1 ELSE 0 END), 0) AS sin_finalizar
            FROM {$s}p_crgos c
            LEFT JOIN {$s}r_crgos_lgin rl ON c.cdgo_crgo = rl.cdgo_crgo AND rl.in_esta = '1'
            LEFT JOIN {$s}p_lgin l ON rl.cdgo_lgin = l.cdgo_lgin
            LEFT JOIN {$s}p_usrios u ON l.cdgo_usrios = u.cdgo_usrios
            LEFT JOIN {$s}r_trmtes_dcmntos r ON r.cdgo_crgo_en = c.cdgo_crgo AND r.in_esta = '1'
            LEFT JOIN {$s}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            LEFT JOIN {$s}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            LEFT JOIN {$s}p_dto_trmtes p ON r.cdgo_dto_trmte = p.cdgo_dto_trmte AND p.fe_ingr_trmte BETWEEN ? AND ?
            WHERE c.in_esta = '1'
              AND (? = 0 OR c.cdgo_area = ?)
            GROUP BY c.cdgo_crgo, c.de_crgo
        ";

        return $this->normalizeSummaryRows(DB::select($sql, [$from, $to, $areaId, $areaId]));
    }

    private function normalizeSummaryRows(array $rows): array
    {
        foreach ($rows as $row) {
            $row->id = (int) ($row->cdgo_area ?? $row->cdgo_crgo);
            $row->nombre = (string) $row->de_area;
            $row->usuarios = (string) ($row->usuarios ?? '');
            $row->digitalizar = (int) $row->digitalizar;
            $row->por_recibir = (int) $row->por_recibir;
            $row->recibidos = (int) $row->recibidos;
            $row->derivados = (int) $row->derivados;
            $row->finalizados = (int) $row->finalizados;
            $row->criticos = (int) $row->criticos;
            $row->sin_finalizar = (int) $row->sin_finalizar;
            $row->total_doc = $row->digitalizar + $row->por_recibir + $row->recibidos + $row->derivados + $row->finalizados;
            $row->porcentaje = $row->total_doc > 0
                ? round((($row->derivados + $row->finalizados) / $row->total_doc) * 100, 2)
                : 0.0;
        }

        $rows = array_values(array_filter($rows, fn ($row) => $row->total_doc > 0 || $row->sin_finalizar > 0));

        usort($rows, function ($a, $b) {
            if ($b->porcentaje !== $a->porcentaje) {
                return $b->porcentaje <=> $a->porcentaje;
            }
            return $b->total_doc <=> $a->total_doc;
        });

        return $rows;
    }

    private function calculateSummaryTotals(array $rows): array
    {
        $totals = [
            'digitalizar' => 0,
            'por_recibir' => 0,
            'recibidos' => 0,
            'derivados' => 0,
            'finalizados' => 0,
            'total_doc' => 0,
            'criticos' => 0,
            'sin_finalizar' => 0,
        ];

        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (int) ($row->{$key} ?? 0);
            }
        }

        $totals['porcentaje'] = $totals['total_doc'] > 0
            ? round((($totals['derivados'] + $totals['finalizados']) / $totals['total_doc']) * 100, 2)
            : 0;

        return $totals;
    }

    private function baseSearchQuery(): Builder
    {
        $s = $this->schema;

        return DB::table($s . 'p_dto_trmtes as p')
            ->join($s . 'p_lgin as l', 'p.co_usua_ingr', '=', 'l.cdgo_lgin')
            ->join($s . 'p_usrios as solicitante', 'p.cdgo_usrios', '=', 'solicitante.cdgo_usrios')
            ->join($s . 'r_trmtes_dcmntos as r', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'a_estdos_trmtes as e', 'r.cdgo_estdo_trmte', '=', 'e.cdgo_estdo_trmte')
            ->join($s . 'p_crgos as c_en', 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->join($s . 'a_areas as a_en', 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
            ->leftJoin($s . 'p_crgos as c_sa', 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->leftJoin($s . 'a_areas as a_sa', 'c_sa.cdgo_area', '=', 'a_sa.cdgo_area')
            ->leftJoin($s . 'a_docmntos_trmtes as doc', 'p.cdgo_docmnto_trmte', '=', 'doc.cdgo_docmnto_trmte')
            ->leftJoin($s . 'a_tpo_trmtes as tipo', 'p.cdgo_tpo_trmte', '=', 'tipo.cdgo_tpo_trmte')
            ->leftJoin($s . 'p_usrios as usuario_lgin', 'l.cdgo_usrios', '=', 'usuario_lgin.cdgo_usrios')
            ->leftJoin($s . 'h_usrios_ntral as hn', 'usuario_lgin.cdgo_usrios', '=', 'hn.cdgo_usrios')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'p.nu_expe_trmte',
                'p.nu_docu_trmte',
                'p.cdgo_tpo_trmte',
                'p.in_sile_admi',
                'p.fe_ingr_trmte',
                'p.nu_foli_trmte',
                'p.de_obse_trmte',
                'p.nu_tiem_sile_adcnal',
                'solicitante.no_crto',
                'solicitante.nu_docu',
                'tipo.de_tpo_trmte',
                'doc.de_docmnto_trmte',
                'doc.nu_tiem_sile',
                'a_en.cdgo_area as cdgo_area_actual',
                'a_en.dc_area',
                'a_en.de_area',
                'a_sa.cdgo_area as cdgo_area_origen',
                'c_en.cdgo_crgo as cdgo_crgo_actual',
                'c_sa.cdgo_crgo as cdgo_crgo_origen',
                'r.cdgo_trmte_dcmnto',
                'r.cdgo_estdo_trmte',
                'r.fe_rcbdo_trmte',
                'r.de_obse as movimiento_observacion',
                'r.de_urls_imag',
                'e.dc_estdo_trmte',
                'e.de_estdo_trmte',
                DB::raw('COALESCE(hn.no_usrio, usuario_lgin.no_crto, l.de_lgin) as usuario_plataforma'),
            ])
            ->where('r.in_esta', '1');
    }

    private function applySearchMode(Builder $query, array $data): void
    {
        $mode = $data['modo'] ?? 'generico';

        if ($mode === 'fechas') {
            $this->applyDateRange($query, $data['desde'] ?? null, $data['hasta'] ?? null);
            return;
        }

        if ($mode === 'avanzado') {
            $this->applyFieldSearch($query, $data['campo1'] ?? 'area', $data['palabra1'] ?? '');
            $this->applyDateRange($query, $data['desde'] ?? null, $data['hasta'] ?? null);
            $this->applyFieldSearch($query, $data['campo2'] ?? 'estado', $data['palabra2'] ?? '', (bool) ($data['excluir'] ?? false));
            return;
        }

        $this->applyFieldSearch($query, $data['campo'] ?? 'expediente', $data['palabra'] ?? '');

        if (! empty($data['fecha'])) {
            $query->whereBetween('p.fe_ingr_trmte', [$data['fecha'] . ' 00:00:00', $data['fecha'] . ' 23:59:59']);
        } elseif (empty($data['palabra'])) {
            $query->where('p.fe_ingr_trmte', '>=', Carbon::now('America/Lima')->subDays(60)->startOfDay()->format('Y-m-d H:i:s'));
        }
    }

    private function applyDateRange(Builder $query, ?string $desde, ?string $hasta): void
    {
        if ($desde && $hasta) {
            $query->whereBetween('p.fe_ingr_trmte', [$desde . ' 00:00:00', $hasta . ' 23:59:59']);
        }
    }

    private function applyFieldSearch(Builder $query, string $field, ?string $value, bool $exclude = false): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        $column = $this->searchColumn($field);
        $operator = $exclude ? 'NOT ILIKE' : 'ILIKE';

        if ($field === 'expediente') {
            $query->where($column, '=', $value);
            return;
        }

        if ($field === 'expediente_varios') {
            $numbers = collect(explode(',', $value))
                ->map(fn ($part) => trim($part))
                ->filter(fn ($part) => $part !== '')
                ->values();

            if ($numbers->isNotEmpty()) {
                $query->whereIn($column, $numbers->all());
            }
            return;
        }

        $query->where($column, $operator, '%' . mb_strtoupper($value) . '%');
    }

    private function searchColumn(string $field): string
    {
        return match ($field) {
            'identificacion' => 'solicitante.nu_docu',
            'nombre' => 'solicitante.no_crto',
            'area' => 'a_en.de_area',
            'tipo_documento' => 'tipo.de_tpo_trmte',
            'nro_documento' => 'p.nu_docu_trmte',
            'estado' => 'e.dc_estdo_trmte',
            'usuario' => 'usuario_lgin.no_crto',
            'observaciones' => 'p.de_obse_trmte',
            'expediente_varios' => 'p.nu_expe_trmte',
            default => 'p.nu_expe_todo',
        };
    }

    private function applyDocumentSimpleFilter(Builder $query, bool $includeSimple): void
    {
        if (! $includeSimple) {
            $query->where('p.cdgo_tpo_trmte', '<>', '17');
        }
    }

    private function applyCompanyFilter(Builder $query): void
    {
        $company = $this->currentCompanyId();
        if ($company > 0) {
            $query->where('p.cdgo_cmpnia', $company);
        }
    }

    private function applyAreaCategory(Builder $query, int $areaId, string $category): void
    {
        match ($category) {
            'digitalizar' => $query
                ->where('a_en.cdgo_area', $areaId)
                ->where(fn ($q) => $q->where('a_sa.cdgo_area', '<>', $areaId)->orWhereNull('a_sa.cdgo_area'))
                ->whereIn('r.cdgo_estdo_trmte', ['5']),
            'por_recibir' => $query
                ->where('a_en.cdgo_area', $areaId)
                ->where(fn ($q) => $q->where('a_sa.cdgo_area', '<>', $areaId)->orWhereNull('a_sa.cdgo_area'))
                ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']),
            'recibidos' => $query
                ->where('a_en.cdgo_area', $areaId)
                ->where('a_sa.cdgo_area', $areaId)
                ->whereNotIn('r.cdgo_estdo_trmte', ['3', '26', '27']),
            'derivados' => $query
                ->where('a_sa.cdgo_area', $areaId)
                ->where('a_en.cdgo_area', '<>', $areaId),
            'finalizados' => $query
                ->where('a_en.cdgo_area', $areaId)
                ->where('a_sa.cdgo_area', $areaId)
                ->whereIn('r.cdgo_estdo_trmte', ['3']),
            'criticos' => $this->applyCriticalFilter($query->where('a_en.cdgo_area', $areaId)),
            'sin_finalizar' => $query
                ->where('a_en.cdgo_area', $areaId)
                ->where('r.cdgo_estdo_trmte', '<>', '3'),
            default => null,
        };
    }

    private function applyCargoCategory(Builder $query, int $cargoId, string $category): void
    {
        match ($category) {
            'por_recibir' => $query
                ->where('c_en.cdgo_crgo', $cargoId)
                ->where(fn ($q) => $q->where('c_sa.cdgo_crgo', '<>', $cargoId)->orWhereNull('c_sa.cdgo_crgo'))
                ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']),
            'recibidos' => $query
                ->where('c_en.cdgo_crgo', $cargoId)
                ->where('c_sa.cdgo_crgo', $cargoId)
                ->whereNotIn('r.cdgo_estdo_trmte', ['3', '26', '27']),
            'derivados' => $query
                ->where('c_sa.cdgo_crgo', $cargoId)
                ->where('c_en.cdgo_crgo', '<>', $cargoId),
            'finalizados' => $query
                ->where('c_en.cdgo_crgo', $cargoId)
                ->where('c_sa.cdgo_crgo', $cargoId)
                ->whereIn('r.cdgo_estdo_trmte', ['3']),
            'sin_finalizar' => $query
                ->where('c_en.cdgo_crgo', $cargoId)
                ->where('r.cdgo_estdo_trmte', '<>', '3'),
            default => null,
        };
    }

    private function applyCriticalFilter(Builder $query): void
    {
        $query
            ->where('r.cdgo_estdo_trmte', '<>', '3')
            ->whereIn('p.cdgo_tpo_trmte', ['17', '22'])
            ->whereRaw("COALESCE(doc.nu_tiem_sile, 0) > 0")
            ->whereRaw("DATE_PART('day', NOW() - p.fe_ingr_trmte) >= (doc.nu_tiem_sile + COALESCE(p.nu_tiem_sile_adcnal, 0) - 1)");
    }

    private function formatExpedienteRow(object $row): array
    {
        return [
            'id' => (int) $row->cdgo_dto_trmte,
            'movimiento_id' => (int) $row->cdgo_trmte_dcmnto,
            'expediente' => $this->formatNumeroExpediente($row->nu_expe_todo),
            'expediente_raw' => $row->nu_expe_todo,
            'expediente_varios' => $row->nu_expe_trmte,
            'documento' => trim(($row->de_tpo_trmte ?: $row->de_docmnto_trmte ?: 'Documento') . ' ' . ($row->nu_docu_trmte ?: '')),
            'identificacion' => $row->nu_docu,
            'folios' => (int) ($row->nu_foli_trmte ?? 0),
            'fecha' => $this->formatDate($row->fe_ingr_trmte),
            'fecha_ultima' => $this->formatDate($row->fe_rcbdo_trmte),
            'silencio' => (string) ($row->in_sile_admi ?? '') === '1',
            'area' => $row->dc_area ?: $row->de_area,
            'area_larga' => $row->de_area,
            'estado' => $row->dc_estdo_trmte ?: $row->de_estdo_trmte,
            'estado_largo' => $row->de_estdo_trmte,
            'estado_id' => (int) $row->cdgo_estdo_trmte,
            'solicitante' => $row->no_crto,
            'descripcion' => $row->de_obse_trmte ?: $row->movimiento_observacion,
            'usuario' => $row->usuario_plataforma,
            'adjunto' => $row->de_urls_imag,
            'dias_transcurridos' => $row->fe_ingr_trmte ? Carbon::parse($row->fe_ingr_trmte)->diffInDays(Carbon::now('America/Lima')) : null,
            'plazo_silencio' => (int) (($row->nu_tiem_sile ?? 0) + ($row->nu_tiem_sile_adcnal ?? 0)),
        ];
    }

    private function formatNumeroExpediente(?string $numero): string
    {
        if (! $numero) {
            return '';
        }

        $parts = explode('-', $numero);
        if (count($parts) >= 3) {
            return $parts[0] . '-' . str_pad($parts[1] . '-' . substr($parts[2], 2), 8, '0', STR_PAD_LEFT);
        }

        return $numero;
    }

    private function formatDate($value): ?string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y') : null;
    }

    private function currentCompanyId(): int
    {
        $user = Auth::user();
        return (int) (session('usComp') ?: ($user->cdgo_cmpnia ?? 0));
    }

    private function currentAreaId(): int
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return 0;
        }

        return (int) DB::table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->value('cdgo_area');
    }

    private function authorizeReportAccess(array $roleIds): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $hasPermission = DB::table($this->schema . 'r_prmsos')
            ->where('cdgo_prfle', (int) $user->cdgo_prfle)
            ->whereIn('cdgo_rle', $roleIds)
            ->exists();

        if (! $hasPermission) {
            abort(403, 'No tiene permiso para acceder a este modulo.');
        }
    }

    private function searchFields(): array
    {
        return [
            ['value' => 'expediente', 'label' => 'Nro de Expediente'],
            ['value' => 'expediente_varios', 'label' => 'Nro de Expediente Varios'],
            ['value' => 'identificacion', 'label' => 'Nro de Identificacion'],
            ['value' => 'nombre', 'label' => 'Nombre o Razon Social'],
            ['value' => 'area', 'label' => 'Area'],
            ['value' => 'tipo_documento', 'label' => 'Tipo de Documento'],
            ['value' => 'nro_documento', 'label' => 'Nro del Documento'],
            ['value' => 'estado', 'label' => 'Estado'],
            ['value' => 'usuario', 'label' => 'Usuario de Plataforma'],
            ['value' => 'observaciones', 'label' => 'Observaciones'],
        ];
    }
}
