<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListaReporteController extends Controller
{
    private string $schema;

    private const CAMPOS = [
        'p_dto_trmtes.nu_expe_todo',
        'p_usrios.nu_docu',
        'p_usrios.no_crto',
        'a_areas.de_area',
        'a_tpo_trmtes.de_tpo_trmte',
        'p_dto_trmtes.nu_docu_trmte',
        'a_estdos_trmtes.dc_estdo_trmte',
        'p_usrios_1.no_crto',
        'p_dto_trmtes.de_obse_todo',
    ];

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Reportes de Expedientes Ingresados'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Reportes de Expedientes Ingresados';
        }

        return view($pathVista, [
            'tituloOpcion' => $tituloOpcion,
            'pathVista' => $pathVista,
        ]);
    }

    public function buscar(Request $request)
    {
        $perPage = 37;
        $page = max(1, (int) $request->input('page', 1));

        $query = $this->queryBase($request);
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $rows = $query
            ->orderByDesc('p.cdgo_dto_trmte')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $rows->map(fn ($row) => $this->mapRow($row))->values(),
            'current_page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
        ]);
    }

    public function imprimir(Request $request)
    {
        $rows = $this->queryBase($request)
            ->orderByDesc('p.cdgo_dto_trmte')
            ->limit(2000)
            ->get()
            ->map(fn ($row) => $this->mapRow($row))
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'filtros' => $request->only([
                'modo', 'palabra', 'campo', 'fecha', 'desde', 'hasta',
                'palabra1', 'campo1', 'palabra2', 'campo2', 'excluir', 'simple',
            ]),
            'usuario' => Auth::user()->de_lgin ?? '',
            'fecha' => now()->format('d/m/Y'),
        ]);
    }

    private function queryBase(Request $request)
    {
        $s = $this->schema;
        $company = (int) (session('usComp') ?: (Auth::user()->cdgo_cmpnia ?? 0));

        $query = DB::table($s . 'p_dto_trmtes as p')
            ->join($s . 'p_lgin as l', 'p.co_usua_ingr', '=', 'l.cdgo_lgin')
            ->join($s . 'p_usrios as p_usrios', 'p.cdgo_usrios', '=', 'p_usrios.cdgo_usrios')
            ->join($s . 'r_trmtes_dcmntos as r', 'r.cdgo_dto_trmte', '=', 'p.cdgo_dto_trmte')
            ->join($s . 'a_estdos_trmtes as a_estdos_trmtes', 'r.cdgo_estdo_trmte', '=', 'a_estdos_trmtes.cdgo_estdo_trmte')
            ->join($s . 'p_crgos as p_crgos', 'r.cdgo_crgo_en', '=', 'p_crgos.cdgo_crgo')
            ->join($s . 'a_areas as a_areas', 'p_crgos.cdgo_area', '=', 'a_areas.cdgo_area')
            ->leftJoin($s . 'a_docmntos_trmtes as a_docmntos_trmtes', 'p.cdgo_docmnto_trmte', '=', 'a_docmntos_trmtes.cdgo_docmnto_trmte')
            ->leftJoin($s . 'a_tpo_trmtes as a_tpo_trmtes', 'p.cdgo_tpo_trmte', '=', 'a_tpo_trmtes.cdgo_tpo_trmte')
            ->join($s . 'p_usrios as p_usrios_1', 'l.cdgo_usrios', '=', 'p_usrios_1.cdgo_usrios')
            ->leftJoin($s . 'h_usrios_ntral as h_usrios_ntral', 'p_usrios_1.cdgo_usrios', '=', 'h_usrios_ntral.cdgo_usrios')
            ->select([
                'p.cdgo_dto_trmte',
                'p.nu_expe_todo',
                'a_tpo_trmtes.de_tpo_trmte',
                'a_areas.dc_area',
                'p.de_obse_trmte',
                'p.in_sile_admi',
                'p.fe_ingr_trmte',
                'r.fe_rcbdo_trmte',
                'p_usrios.no_crto',
                'a_docmntos_trmtes.de_docmnto_trmte',
                'p.nu_foli_trmte',
                DB::raw('COALESCE(h_usrios_ntral.no_usrio, p_usrios_1.no_crto, l.de_lgin) as usuario'),
                'r.cdgo_estdo_trmte',
                'a_estdos_trmtes.de_estdo_trmte',
                'a_estdos_trmtes.dc_estdo_trmte',
            ])
            ->where('r.in_esta', '1');

        if ($company > 0) {
            $query->where('p.cdgo_cmpnia', $company);
        }

        $simple = $request->boolean('simple');
        if (! $simple) {
            $query->where('p.cdgo_tpo_trmte', '<>', '17');
        }

        $modo = (string) $request->input('modo', 'generico');
        if ($modo === 'fechas') {
            $this->aplicarRango($query, $request->input('desde'), $request->input('hasta'));
        } elseif ($modo === 'avanzado') {
            $this->aplicarCampo($query, $request->input('campo1', 'a_areas.de_area'), $request->input('palabra1'), false);
            $this->aplicarRango($query, $request->input('desde'), $request->input('hasta'));
            $palabra2 = trim((string) $request->input('palabra2', ''));
            if ($palabra2 !== '') {
                $this->aplicarCampo(
                    $query,
                    $request->input('campo2', 'a_estdos_trmtes.dc_estdo_trmte'),
                    $palabra2,
                    $request->boolean('excluir')
                );
            }
        } else {
            $palabra = trim((string) $request->input('palabra', ''));
            $fecha = $request->input('fecha');
            $this->aplicarCampo($query, $request->input('campo', 'p_dto_trmtes.nu_expe_todo'), $palabra, false, true);
            if ($fecha) {
                $this->aplicarRango($query, $fecha, $fecha);
            } elseif ($palabra === '') {
                $query->where('p.fe_ingr_trmte', '>=', Carbon::now('America/Lima')->subDays(60)->format('Y-m-d'));
            }
        }

        return $query;
    }

    private function aplicarRango($query, $desde, $hasta): void
    {
        $d = $this->normalizarFecha($desde);
        $h = $this->normalizarFecha($hasta);
        if ($d && $h) {
            $query->whereRaw('p.fe_ingr_trmte >= ? AND p.fe_ingr_trmte <= (CAST(? AS date) + 1)', [$d, $h]);
        } elseif ($d) {
            $query->where('p.fe_ingr_trmte', '>=', $d);
        } elseif ($h) {
            $query->whereRaw('p.fe_ingr_trmte <= (CAST(? AS date) + 1)', [$h]);
        }
    }

    private function aplicarCampo($query, $campo, $valor, bool $excluir, bool $exactoExpediente = false): void
    {
        $campo = (string) $campo;
        if (! in_array($campo, self::CAMPOS, true)) {
            $campo = 'p_dto_trmtes.nu_expe_todo';
        }
        $col = $this->aliasCampo($campo);
        $valor = trim((string) $valor);
        if ($valor === '') {
            return;
        }

        if ($exactoExpediente && $campo === 'p_dto_trmtes.nu_expe_todo') {
            $query->where($col, '=', $valor);

            return;
        }

        if ($excluir) {
            $query->whereRaw("UPPER({$col}) NOT LIKE UPPER(?)", ['%' . $valor . '%']);
        } else {
            $query->whereRaw("UPPER({$col}) LIKE UPPER(?)", ['%' . $valor . '%']);
        }
    }

    private function aliasCampo(string $campo): string
    {
        return match ($campo) {
            'p_dto_trmtes.nu_expe_todo' => 'p.nu_expe_todo',
            'p_usrios.nu_docu' => 'p_usrios.nu_docu',
            'p_usrios.no_crto' => 'p_usrios.no_crto',
            'a_areas.de_area' => 'a_areas.de_area',
            'a_tpo_trmtes.de_tpo_trmte' => 'a_tpo_trmtes.de_tpo_trmte',
            'p_dto_trmtes.nu_docu_trmte' => 'p.nu_docu_trmte',
            'a_estdos_trmtes.dc_estdo_trmte' => 'a_estdos_trmtes.dc_estdo_trmte',
            'p_usrios_1.no_crto' => 'p_usrios_1.no_crto',
            'p_dto_trmtes.de_obse_todo' => 'p.de_obse_todo',
            default => 'p.nu_expe_todo',
        };
    }

    private function mapRow(object $row): array
    {
        $obs = trim((string) ($row->de_obse_trmte ?? ''));
        $desc = $obs !== '' ? $obs : (string) ($row->de_docmnto_trmte ?? '');

        return [
            'id' => (int) $row->cdgo_dto_trmte,
            'expediente' => $this->formatoExpediente($row->nu_expe_todo),
            'expediente_raw' => $row->nu_expe_todo,
            'folios' => $row->nu_foli_trmte,
            'fecha' => $this->fechaCorta($row->fe_ingr_trmte),
            'fecha_completa' => $row->fe_ingr_trmte ? Carbon::parse($row->fe_ingr_trmte)->format('d/m/Y') : '',
            'silencio' => ((string) $row->in_sile_admi === '1') ? 'Si' : 'No',
            'area' => $row->dc_area,
            'estado' => $row->de_estdo_trmte,
            'estado_corto' => mb_substr((string) ($row->dc_estdo_trmte ?: $row->de_estdo_trmte), 0, 5, 'UTF-8'),
            'estado_id' => (int) $row->cdgo_estdo_trmte,
            'solicitante' => $row->no_crto,
            'descripcion' => $desc,
            'usuario' => $row->usuario,
            'anex' => (int) $row->cdgo_estdo_trmte === 3 ? 0 : 1,
        ];
    }

    private function formatoExpediente(?string $numero): string
    {
        if (! $numero) {
            return '';
        }
        $parts = explode('-', $numero);
        if (count($parts) >= 3) {
            return $parts[0] . '-' . str_pad($parts[1] . '-' . substr($parts[2], 2, 10), 8, '0', STR_PAD_LEFT);
        }

        return $numero;
    }

    private function fechaCorta($valor): string
    {
        if (! $valor) {
            return '';
        }

        return Carbon::parse($valor)->format('d/m');
    }

    private function normalizarFecha($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
                return Carbon::createFromFormat('d/m/Y', $valor)->format('Y-m-d');
            }

            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
