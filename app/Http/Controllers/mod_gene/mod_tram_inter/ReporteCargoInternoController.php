<?php

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Equivalente a lista_reporte_area_cargo.php:
 * reporte de trámites internos por cargo del área del usuario.
 */
class ReporteCargoInternoController extends Controller
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request)
    {
        $usIdsArea = $this->areaUsuarioActual();
        $hoy = Carbon::now('America/Lima');

        // Igual que el antiguo: default 01/mes → hoy (d/m/Y) vía POST
        $desdeStr = $request->input('txt_fe_inicio', $hoy->copy()->startOfMonth()->format('d/m/Y'));
        $hastaStr = $request->input('txt_fe_fin', $hoy->format('d/m/Y'));

        try {
            $fechaDesdeSql = Carbon::createFromFormat('d/m/Y', $desdeStr)->format('Y-m-d') . ' 00:00:00';
            $fechaHastaSql = Carbon::createFromFormat('d/m/Y', $hastaStr)->format('Y-m-d') . ' 23:59:59';
        } catch (\Exception $e) {
            $desdeStr = $hoy->copy()->startOfMonth()->format('d/m/Y');
            $hastaStr = $hoy->format('d/m/Y');
            $fechaDesdeSql = $hoy->copy()->startOfMonth()->format('Y-m-d') . ' 00:00:00';
            $fechaHastaSql = $hoy->format('Y-m-d') . ' 23:59:59';
        }

        $s = $this->schema;

        // Cargos activos del área (como consu_tabla p_crgos WHERE cdgo_area = usIdsArea)
        $cargos = DB::table($s . 'p_crgos')
            ->where('in_esta', '1')
            ->where('cdgo_area', $usIdsArea)
            ->orderBy('de_crgo')
            ->get(['cdgo_crgo', 'de_crgo']);

        $rows_data = [];
        $sum_entregado = 0;
        $sum_recibido = 0;
        $sum_salida = 0;
        $sum_finalizado = 0;
        $sum_total_doc = 0;
        $sum_total_ges = 0;

        foreach ($cargos as $cargo) {
            $cdgo = (int) $cargo->cdgo_crgo;

            $con_entrada = $this->countEntrada($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_recibido = $this->countRecibidos($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_salida = $this->countSalida($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_finalizado = $this->countFinalizado($cdgo, $fechaDesdeSql, $fechaHastaSql);

            $total = $con_entrada + $con_recibido + $con_salida + $con_finalizado;
            $gestionados = $con_salida + $con_finalizado;

            // Antiguo: si total = 0 → 0.00 (no fuerza 100%)
            $porcentaje = $total > 0
                ? round(($gestionados / $total) * 100, 2)
                : 0.0;

            $sum_entregado += $con_entrada;
            $sum_recibido += $con_recibido;
            $sum_salida += $con_salida;
            $sum_finalizado += $con_finalizado;
            $sum_total_doc += $total;
            $sum_total_ges += $gestionados;

            $usuarios = $this->usuariosDelCargo($cdgo);
            $iniciales = array_map(fn ($n) => $this->geneIniciales($n), $usuarios);

            $rows_data[] = [
                'id' => $cdgo,
                'cargo' => (string) $cargo->de_crgo,
                'usuarios' => $usuarios,
                'iniciales' => $iniciales,
                'entrada' => $con_entrada,
                'recibido' => $con_recibido,
                'salida' => $con_salida,
                'finalizado' => $con_finalizado,
                'total' => $total,
                'gestionados' => $gestionados,
                'porcentaje' => $porcentaje,
            ];
        }

        $totales = [
            'sum_entregado' => $sum_entregado,
            'sum_recibido' => $sum_recibido,
            'sum_salida' => $sum_salida,
            'sum_finalizado' => $sum_finalizado,
            'sum_total_doc' => $sum_total_doc,
            'sum_total_ges' => $sum_total_ges,
        ];

        return view('mod_gene.mod_tram_inter.reporte-cargo-interno', compact(
            'rows_data',
            'totales',
            'desdeStr',
            'hastaStr',
            'usIdsArea'
        ));
    }

    public function ajaxDetalle(Request $request)
    {
        $cargo_id = (int) $request->input('cargo_id');
        $tipo_bandeja = (int) $request->input('tipo_bandeja');
        $desde = (string) $request->input('fecha_inicio');
        $hasta = (string) $request->input('fecha_fin');

        try {
            $fechaDesdeSql = Carbon::createFromFormat('d/m/Y', $desde)->format('Y-m-d') . ' 00:00:00';
            $fechaHastaSql = Carbon::createFromFormat('d/m/Y', $hasta)->format('Y-m-d') . ' 23:59:59';
        } catch (\Exception $e) {
            $fechaDesdeSql = Carbon::now('America/Lima')->startOfMonth()->format('Y-m-d') . ' 00:00:00';
            $fechaHastaSql = Carbon::now('America/Lima')->format('Y-m-d') . ' 23:59:59';
        }

        $s = $this->schema;

        $baseQuery = DB::table("{$s}r_trmtes_ntrnos as r")
            ->select([
                'r.cdgo_dtos_ntrnos',
                'p.nu_tram_todo',
                'p.fe_ingrso',
                'r.fe_rcbdo',
                'p.de_obser',
                'p.de_urls_adjnto',
                'te.de_tpo_trmte',
                'te.dc_tpo_trmte',
                'a_inco.de_area as de_area_inco',
                'a_inco.dc_area as dc_area_inco',
            ])
            ->join("{$s}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->join("{$s}p_tpo_trmte as te", 'p.cdgo_tpo_trmte', '=', 'te.cdgo_tpo_trmte')
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}a_areas as a_inco", 'p.cdgo_area', '=', 'a_inco.cdgo_area')
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingrso', [$fechaDesdeSql, $fechaHastaSql]);

        // Tipos 1–4 = ajax_reporte_cargo.php (entrada/recibidos/salida/finalizado)
        if ($tipo_bandeja === 1) {
            $baseQuery->where('r.cdgo_crgo_en', $cargo_id)
                ->where(function ($q) use ($cargo_id) {
                    $q->whereNull('r.cdgo_crgo_sa')
                        ->orWhere('r.cdgo_crgo_sa', '!=', $cargo_id);
                })
                ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']);
        } elseif ($tipo_bandeja === 2) {
            $baseQuery->where('r.cdgo_crgo_en', $cargo_id)
                ->where('r.cdgo_crgo_sa', $cargo_id)
                ->whereNotIn('r.cdgo_estdo_trmte', ['1', '3', '24']);
        } elseif ($tipo_bandeja === 3) {
            $baseQuery->where('r.cdgo_crgo_sa', $cargo_id)
                ->where('r.cdgo_crgo_en', '!=', $cargo_id);
        } elseif ($tipo_bandeja === 4) {
            $baseQuery->where('r.cdgo_crgo_en', $cargo_id)
                ->where('r.cdgo_crgo_sa', $cargo_id)
                ->whereIn('r.cdgo_estdo_trmte', ['3', '24']);
        }

        $tramites = $baseQuery
            ->orderBy('te.dc_tpo_trmte')
            ->orderBy('p.fe_ingrso')
            ->get();

        foreach ($tramites as $tramite) {
            $tramite->fechaini = Carbon::parse($tramite->fe_ingrso)->format('d/m/Y H:i:s');
            $tramite->fe_ultima = $tramite->fe_rcbdo
                ? Carbon::parse($tramite->fe_rcbdo)->format('d/m/Y H:i:s')
                : '-';
            $tramite->nu_tram_format = str_pad((string) $tramite->nu_tram_todo, 10, '0', STR_PAD_LEFT);
            $tramite->de_obser_corto = mb_substr((string) ($tramite->de_obser ?? ''), 0, 55);
        }

        $viewHtml = view('mod_gene.mod_tram_inter.partials.reporte-cargo-interno-detalle', [
            'tramites' => $tramites,
            'uid' => 'tbl_int_' . $cargo_id . '_' . $tipo_bandeja,
        ])->render();

        return response()->json(['html' => $viewHtml]);
    }

    private function areaUsuarioActual(): int
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return 0;
        }

        $area = DB::table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->value('cdgo_area');

        return (int) ($area ?? 0);
    }

    /** gene_count_tramite_cargo_entrada */
    private function countEntrada(int $cdgo, string $desde, string $hasta): int
    {
        $s = $this->schema;

        return (int) DB::table("{$s}r_trmtes_ntrnos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where(function ($q) use ($cdgo) {
                $q->whereNull('c_sa.cdgo_crgo')
                    ->orWhere('c_sa.cdgo_crgo', '!=', $cdgo);
            })
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27'])
            ->whereBetween('p.fe_ingrso', [$desde, $hasta])
            ->count();
    }

    /** gene_count_tramite_cargo_recibidos */
    private function countRecibidos(int $cdgo, string $desde, string $hasta): int
    {
        $s = $this->schema;

        return (int) DB::table("{$s}r_trmtes_ntrnos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where('c_sa.cdgo_crgo', $cdgo)
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', ['1', '3', '24'])
            ->whereBetween('p.fe_ingrso', [$desde, $hasta])
            ->count();
    }

    /** gene_count_tramite_cargo_salida */
    private function countSalida(int $cdgo, string $desde, string $hasta): int
    {
        $s = $this->schema;

        return (int) DB::table("{$s}r_trmtes_ntrnos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('c_sa.cdgo_crgo', $cdgo)
            ->where('c_en.cdgo_crgo', '!=', $cdgo)
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingrso', [$desde, $hasta])
            ->count();
    }

    /** gene_count_tramite_cargo_finalizado */
    private function countFinalizado(int $cdgo, string $desde, string $hasta): int
    {
        $s = $this->schema;

        return (int) DB::table("{$s}r_trmtes_ntrnos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where('c_sa.cdgo_crgo', $cdgo)
            ->where('r.in_esta', '1')
            ->whereIn('r.cdgo_estdo_trmte', ['3', '24'])
            ->whereBetween('p.fe_ingrso', [$desde, $hasta])
            ->count();
    }

    /** gene_cargo_nombre filtrado por cargo */
    private function usuariosDelCargo(int $cdgo): array
    {
        $s = $this->schema;

        return DB::table("{$s}r_crgos_lgin as rcl")
            ->join("{$s}p_lgin as l", 'rcl.cdgo_lgin', '=', 'l.cdgo_lgin')
            ->join("{$s}p_usrios as u", 'l.cdgo_usrios', '=', 'u.cdgo_usrios')
            ->where('rcl.cdgo_crgo', $cdgo)
            ->where('l.in_esta', '1')
            ->orderBy('u.no_crto')
            ->pluck('u.no_crto')
            ->map(fn ($n) => (string) $n)
            ->all();
    }

    /** gene_iniciales del antiguo */
    private function geneIniciales(string $nombre): string
    {
        $parts = preg_split('/\s+/', trim($nombre)) ?: [];
        $ini = '';
        foreach ($parts as $p) {
            if ($p !== '') {
                $ini .= mb_substr($p, 0, 1);
            }
        }

        return mb_strtoupper($ini);
    }
}
