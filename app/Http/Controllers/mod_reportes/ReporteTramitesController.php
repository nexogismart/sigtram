<?php

namespace App\Http\Controllers\mod_reportes;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReporteTramitesController extends Controller
{
    private string $schema;

    /** Campos de búsqueda permitidos (whitelist; evita inyección como el base64 del legado). */
    private const CRITERIOS = [
        'detalle' => ['col' => 'p_dto_trmtes.de_obse_trmte', 'mode' => 'like'],
        'expediente' => ['col' => 'p_dto_trmtes.nu_expe_todo', 'mode' => 'eq'],
        'documento' => ['col' => 'p_usrios.nu_docu', 'mode' => 'eq'],
        'nombre' => ['col' => 'p_usrios.no_crto', 'mode' => 'like'],
        'usuario' => ['col' => 'p_lgin.de_lgin', 'mode' => 'eq_lower'],
    ];

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request): View
    {
        $this->assertRol51();

        $areas = DB::table($this->schema . 'a_areas')
            ->select('cdgo_area', 'de_area', 'dc_area')
            ->where('in_esta', '1')
            ->orderBy('de_area')
            ->get();

        $tramites = DB::table($this->schema . 'a_docmntos_trmtes')
            ->select('cdgo_docmnto_trmte', 'de_docmnto_trmte')
            ->orderBy('de_docmnto_trmte')
            ->get();

        return view('mod_reportes.reporte_tramites', [
            'areas' => $areas,
            'tramites' => $tramites,
            'urlBuscar' => url('/mod_reportes/reporte_tramites/buscar'),
            'urlBuscaExpedi' => route('tramites.busca_expedi'),
        ]);
    }

    public function tipos(Request $request): JsonResponse
    {
        $this->assertRol51();

        $data = DB::table($this->schema . 'a_docmntos_trmtes')
            ->select('cdgo_docmnto_trmte', 'de_docmnto_trmte')
            ->orderBy('de_docmnto_trmte')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $this->assertRol51();

        $data = $request->validate([
            'areas' => 'nullable|array',
            'areas.*' => 'integer',
            'tramite' => 'nullable|integer',
            'silencio' => 'nullable|boolean',
            'estado' => 'nullable|integer',
            'not_estado' => 'nullable|boolean',
            'tipo_expe' => 'nullable|integer|in:1,2,3,4,5',
            'criterio' => 'nullable|string|in:detalle,expediente,documento,nombre,usuario',
            'descripcion' => 'nullable|string|max:200',
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:desde',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = $this->baseQuery();
        $this->applyFilters($query, $data);

        $perPage = (int) ($data['per_page'] ?? 15);
        $page = (int) ($data['page'] ?? 1);

        $paginator = $query
            ->orderByDesc('p_dto_trmtes.cdgo_dto_trmte')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items())->map(function ($row, $idx) use ($paginator) {
            $tpo = ((int) $row->cdgo_tpo_trmte === 17) ? 'S' : 'E';
            $anexo = ((string) $row->de_estdo_trmte === '3') ? '0' : '1';
            $n = (($paginator->currentPage() - 1) * $paginator->perPage()) + $idx + 1;

            return [
                'n' => $n,
                'cdgo_dto_trmte' => (int) $row->cdgo_dto_trmte,
                'nu_expe_todo' => $row->nu_expe_todo,
                'tpo' => $tpo,
                'nu_foli_trmte' => $row->nu_foli_trmte,
                'dc_area1' => $row->dc_area1,
                'de_area1' => $row->de_area1,
                'fe_ingr' => $this->fmtFecha($row->fe_ingr_trmte),
                'fe_ingr_full' => $this->fmtFechaHora($row->fe_ingr_trmte),
                'fe_act' => $this->fmtFecha($row->fe_rcbdo_trmte),
                'fe_act_full' => $this->fmtFechaHora($row->fe_rcbdo_trmte),
                'no_crto' => $row->no_crto,
                'de_docmnto_trmte' => $row->de_docmnto_trmte,
                'dc_area' => $row->dc_area,
                'de_area' => $row->de_area,
                'de_estdo_trmte' => $row->de_estdo_trmte,
                'de_obse_trmte' => $row->de_obse_trmte,
                'usuario_tip' => 'Usuario: ' . strtoupper((string) $row->de_lgin) . ' --> ' . strtoupper((string) $row->no_crto_lgin),
                'anexo' => $anexo,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function baseQuery(): Builder
    {
        $s = $this->schema;

        return DB::table($s . 'r_trmtes_dcmntos as r_trmtes_dcmntos')
            ->join($s . 'p_crgos', 'r_trmtes_dcmntos.cdgo_crgo_en', '=', 'p_crgos.cdgo_crgo')
            ->join($s . 'a_areas', 'p_crgos.cdgo_area', '=', 'a_areas.cdgo_area')
            ->join($s . 'a_estdos_trmtes', 'r_trmtes_dcmntos.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($s . 'p_dto_trmtes', 'p_dto_trmtes.cdgo_dto_trmte', '=', 'r_trmtes_dcmntos.cdgo_dto_trmte')
            ->join($s . 'p_usrios', 'p_usrios.cdgo_usrios', '=', 'p_dto_trmtes.cdgo_usrios')
            ->leftJoin($s . 'a_docmntos_trmtes', 'a_docmntos_trmtes.cdgo_docmnto_trmte', '=', 'p_dto_trmtes.cdgo_docmnto_trmte')
            ->leftJoin($s . 'a_areas as a_areas_1', 'p_dto_trmtes.cdgo_area', '=', 'a_areas_1.cdgo_area')
            ->join($s . 'a_tpo_trmtes', 'p_dto_trmtes.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($s . 'p_lgin', 'p_dto_trmtes.co_usua_ingr', '=', 'p_lgin.cdgo_lgin')
            ->join($s . 'p_usrios as p_usrios_lgin', 'p_lgin.cdgo_usrios', '=', 'p_usrios_lgin.cdgo_usrios')
            ->whereIn('r_trmtes_dcmntos.cdgo_estdo_trmte', [1, 2, 3, 5, 55, 25, 29])
            ->select([
                'p_dto_trmtes.cdgo_dto_trmte',
                'p_dto_trmtes.nu_expe_todo',
                'p_dto_trmtes.cdgo_tpo_trmte',
                'p_dto_trmtes.nu_foli_trmte',
                'p_dto_trmtes.co_usua_ingr',
                'a_areas_1.dc_area as dc_area1',
                'a_areas_1.de_area as de_area1',
                'p_dto_trmtes.fe_ingr_trmte',
                'r_trmtes_dcmntos.fe_rcbdo_trmte',
                'a_docmntos_trmtes.de_docmnto_trmte',
                'a_areas.dc_area',
                'a_areas.de_area',
                'a_estdos_trmtes.de_estdo_trmte',
                DB::raw('UPPER(p_dto_trmtes.de_obse_trmte) as de_obse_trmte'),
                DB::raw('UPPER(p_usrios.no_crto) as no_crto'),
                'a_tpo_trmtes.de_tpo_trmte',
                'p_usrios_lgin.no_crto as no_crto_lgin',
                'p_lgin.de_lgin',
            ]);
    }

    private function applyFilters(Builder $query, array $data): void
    {
        $areas = collect($data['areas'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if ($areas->isNotEmpty()) {
            $query->whereIn('a_areas_1.cdgo_area', $areas->all());
        } else {
            $query->where('a_areas_1.in_esta', '<>', '0');
        }

        $tipo = (int) ($data['tipo_expe'] ?? 5);
        $mapTipo = [1 => 22, 2 => 17, 3 => 40, 4 => 42];
        if (isset($mapTipo[$tipo])) {
            $query->where('p_dto_trmtes.cdgo_tpo_trmte', $mapTipo[$tipo]);
        }

        $tramite = (int) ($data['tramite'] ?? 0);
        if ($tramite > 0) {
            $query->where('a_docmntos_trmtes.cdgo_docmnto_trmte', $tramite);
        }

        if (!empty($data['silencio'])) {
            $query->where('a_docmntos_trmtes.in_sile_admi', '1');
        }

        $estado = (int) ($data['estado'] ?? 0);
        if ($estado > 0) {
            if (!empty($data['not_estado'])) {
                $query->where('a_estdos_trmtes.cdgo_estdo_trmte', '<>', $estado);
            } else {
                $query->where('a_estdos_trmtes.cdgo_estdo_trmte', $estado);
            }
        }

        if (!empty($data['desde']) && !empty($data['hasta'])) {
            $desde = Carbon::createFromFormat('Y-m-d', $data['desde'], 'America/Lima')->startOfDay();
            $hasta = Carbon::createFromFormat('Y-m-d', $data['hasta'], 'America/Lima')->endOfDay();
            $query->whereBetween('p_dto_trmtes.fe_ingr_trmte', [
                $desde->format('Y-m-d H:i:s'),
                $hasta->format('Y-m-d H:i:s'),
            ]);
        }

        $criterioKey = (string) ($data['criterio'] ?? '');
        $texto = trim((string) ($data['descripcion'] ?? ''));
        if ($texto !== '' && isset(self::CRITERIOS[$criterioKey])) {
            $meta = self::CRITERIOS[$criterioKey];
            $upper = mb_strtoupper($texto, 'UTF-8');
            if ($meta['mode'] === 'like') {
                $query->whereRaw('UPPER(' . $meta['col'] . ') LIKE ?', ['%' . $upper . '%']);
            } elseif ($meta['mode'] === 'eq_lower') {
                $query->whereRaw('LOWER(' . $meta['col'] . ') = ?', [mb_strtolower($texto, 'UTF-8')]);
            } else {
                $query->whereRaw('UPPER(' . $meta['col'] . ') = ?', [$upper]);
            }
        }
    }

    private function assertRol51(): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $ok = DB::table($this->schema . 'r_prmsos')
            ->where('cdgo_prfle', (int) $user->cdgo_prfle)
            ->where('cdgo_rle', 51)
            ->exists();

        if (!$ok) {
            abort(403, 'No tiene permiso para el reporte de trámites (rol 51).');
        }
    }

    private function fmtFecha($value): string
    {
        if (!$value) {
            return '';
        }
        try {
            return Carbon::parse($value)->timezone('America/Lima')->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function fmtFechaHora($value): string
    {
        if (!$value) {
            return '';
        }
        try {
            return Carbon::parse($value)->timezone('America/Lima')->format('d/m/Y h:i:s A');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
