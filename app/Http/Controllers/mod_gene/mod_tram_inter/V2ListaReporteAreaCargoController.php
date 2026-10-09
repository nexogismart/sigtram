<?php

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Equivalente a v2_lista_reporte_area_cargo.php:
 * dashboard moderno + analytics por cargo del área.
 */
class V2ListaReporteAreaCargoController extends Controller
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
        $evoData = $this->buildEvolution($usIdsArea, $fechaDesdeSql, $fechaHastaSql);

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
            $pendientes = $con_entrada + $con_recibido;

            // Lógica v2: total=0 → 100%; sin pendientes → EXCELENTE
            $porcentaje = $total > 0 ? ($gestionados / $total) * 100 : 100;

            if ($pendientes == 0) {
                $porcentaje = 100;
                $status_text = 'EXCELENTE';
                $status_color = 'var(--success)';
            } elseif ($porcentaje >= 80) {
                $status_text = 'AL DÍA';
                $status_color = 'var(--success)';
            } elseif ($porcentaje >= 50) {
                $status_text = 'REVISAR';
                $status_color = 'var(--warning)';
            } else {
                $status_text = 'CRÍTICO';
                $status_color = 'var(--danger)';
            }

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
                'porcentaje' => round($porcentaje, 1),
                'status_text' => $status_text,
                'status_color' => $status_color,
            ];
        }

        usort($rows_data, function ($a, $b) {
            $aTiene = $a['total'] > 0;
            $bTiene = $b['total'] > 0;
            if ($aTiene !== $bTiene) {
                return $bTiene <=> $aTiene;
            }
            $pctA = $a['total'] > 0 ? ($a['gestionados'] / $a['total']) * 100 : 0;
            $pctB = $b['total'] > 0 ? ($b['gestionados'] / $b['total']) * 100 : 0;

            return $pctB <=> $pctA;
        });

        $sum_gestionado_global = $sum_salida + $sum_finalizado;
        $pct_trabajo = $sum_total_doc > 0 ? round(($sum_gestionado_global / $sum_total_doc) * 100, 1) : 0;

        if ($pct_trabajo >= 80) {
            $pct_color = '#10b981';
            $pct_bg = 'rgba(16,185,129,0.1)';
        } elseif ($pct_trabajo >= 50) {
            $pct_color = '#f59e0b';
            $pct_bg = 'rgba(245,158,11,0.1)';
        } else {
            $pct_color = '#ef4444';
            $pct_bg = 'rgba(239,68,68,0.1)';
        }

        $totales = [
            'sum_entregado' => $sum_entregado,
            'sum_recibido' => $sum_recibido,
            'sum_salida' => $sum_salida,
            'sum_finalizado' => $sum_finalizado,
            'sum_total_doc' => $sum_total_doc,
            'sum_total_ges' => $sum_total_ges,
            'pct_trabajo' => $pct_trabajo,
            'pct_color' => $pct_color,
            'pct_bg' => $pct_bg,
        ];

        return view('mod_gene.mod_tram_inter.v2_lista_reporte_area_cargo', compact(
            'rows_data',
            'totales',
            'evoData',
            'desdeStr',
            'hastaStr'
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

        if ($tipo_bandeja === 1) {
            $baseQuery->where('r.cdgo_crgo_en', $cargo_id)
                ->where(function ($q) use ($cargo_id) {
                    $q->whereNull('r.cdgo_crgo_sa')->orWhere('r.cdgo_crgo_sa', '!=', $cargo_id);
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

        $tramites = $baseQuery->orderBy('p.fe_ingrso', 'desc')->get();

        foreach ($tramites as $tramite) {
            $areasSql = DB::table("{$s}r_trmtes_ntrnos as r2")
                ->join("{$s}p_crgos as c", 'r2.cdgo_crgo_en', '=', 'c.cdgo_crgo')
                ->join("{$s}a_areas as a", 'c.cdgo_area', '=', 'a.cdgo_area')
                ->where('r2.cdgo_dtos_ntrnos', $tramite->cdgo_dtos_ntrnos)
                ->orderBy('r2.cdgo_trmtes_ntrnos')
                ->pluck('a.dc_area');

            $ruta_areas = [];
            $last_area = '';
            foreach ($areasSql as $current_area) {
                if (! empty($current_area) && $current_area !== $last_area) {
                    $ruta_areas[] = $current_area;
                    $last_area = $current_area;
                }
            }
            $tramite->ruta_areas = $ruta_areas;

            $dias_area = 0;
            if ($tramite->fe_rcbdo) {
                $dias_area = Carbon::parse($tramite->fe_rcbdo)->diffInDays(Carbon::now('America/Lima'));
            }
            $tramite->dias_area = $dias_area;
            $tramite->fechaini = Carbon::parse($tramite->fe_ingrso)->format('d/m/Y H:i:s');
            $tramite->fe_ultima = $tramite->fe_rcbdo
                ? Carbon::parse($tramite->fe_rcbdo)->format('d/m/Y H:i:s')
                : '-';
            $tramite->nu_tram_format = str_pad((string) $tramite->nu_tram_todo, 10, '0', STR_PAD_LEFT);
            $tramite->area_actual = ! empty($ruta_areas) ? end($ruta_areas) : ($tramite->dc_area_inco ?? '-');
        }

        $viewHtml = view('mod_gene.mod_tram_inter.partials.v2_lista_reporte_area_cargo_detalle', [
            'tramites' => $tramites,
            'uid' => 'tbl_v2_' . $cargo_id . '_' . $tipo_bandeja,
        ])->render();

        return response()->json(['html' => $viewHtml]);
    }

    private function buildEvolution(int $usIdsArea, string $fechaDesdeSql, string $fechaHastaSql): array
    {
        $s = $this->schema;
        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $sqlEvo = "
            SELECT
                EXTRACT(YEAR FROM p.fe_ingrso)::int  AS anio,
                EXTRACT(MONTH FROM p.fe_ingrso)::int AS mes,
                SUM(CASE WHEN r.cdgo_estdo_trmte IN ('3','24') THEN 1 ELSE 0 END) AS finalizados,
                SUM(CASE WHEN r.cdgo_estdo_trmte IN ('1','2') THEN 1 ELSE 0 END) AS pendientes
            FROM {$s}r_trmtes_ntrnos r
            INNER JOIN {$s}p_crgos c ON r.cdgo_crgo_en = c.cdgo_crgo
            INNER JOIN {$s}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
            WHERE c.cdgo_area = ?
              AND r.in_esta = '1'
              AND p.fe_ingrso >= ?
              AND p.fe_ingrso <= ?
            GROUP BY anio, mes
            ORDER BY anio ASC, mes ASC
            LIMIT 19
        ";

        $resEvo = DB::select($sqlEvo, [$usIdsArea, $fechaDesdeSql, $fechaHastaSql]);

        $labels = [];
        $finalizados = [];
        $pendientes = [];
        $total_fin = 0;

        foreach ($resEvo as $row) {
            $idx = (int) $row->mes - 1;
            $labels[] = $meses[$idx] . ' ' . substr((string) $row->anio, 2);
            $finalizados[] = (int) $row->finalizados;
            $pendientes[] = (int) $row->pendientes;
            $total_fin += (int) $row->finalizados;
        }

        $avg_fin = count($finalizados) > 0 ? round($total_fin / count($finalizados), 1) : 0;
        $last_val = end($finalizados);
        $prev_val = prev($finalizados);
        $trend_pct = 0;
        if ($prev_val !== false && $prev_val > 0) {
            $trend_pct = round((($last_val - $prev_val) / $prev_val) * 100);
        }

        $sqlTime = "
            SELECT AVG(EXTRACT(DAY FROM (r.fe_rcbdo - p.fe_ingrso))) as avg_time
            FROM {$s}r_trmtes_ntrnos r
            INNER JOIN {$s}p_crgos c ON r.cdgo_crgo_en = c.cdgo_crgo
            INNER JOIN {$s}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
            WHERE c.cdgo_area = ?
              AND r.cdgo_estdo_trmte IN ('3','24')
              AND r.in_esta = '1'
              AND r.fe_rcbdo IS NOT NULL
              AND p.fe_ingrso >= ? AND p.fe_ingrso <= ?
        ";
        $resTime = DB::selectOne($sqlTime, [$usIdsArea, $fechaDesdeSql, $fechaHastaSql]);
        $avg_time = $resTime && $resTime->avg_time !== null ? round((float) $resTime->avg_time, 1) : 1.5;

        return [
            'labels' => $labels,
            'finalizados' => $finalizados,
            'pendientes' => $pendientes,
            'total_fin' => $total_fin,
            'avg_fin' => $avg_fin,
            'trend_pct' => $trend_pct,
            'avg_time' => $avg_time,
        ];
    }

    private function areaUsuarioActual(): int
    {
        $cargoId = (int) session('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return 0;
        }

        return (int) (DB::table($this->schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->value('cdgo_area') ?? 0);
    }

    private function countEntrada(int $cdgo, string $desde, string $hasta): int
    {
        $s = $this->schema;

        return (int) DB::table("{$s}r_trmtes_ntrnos as r")
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->join("{$s}p_dtos_ntrnos as p", 'r.cdgo_dtos_ntrnos', '=', 'p.cdgo_dtos_ntrnos')
            ->where('c_en.cdgo_crgo', $cdgo)
            ->where(function ($q) use ($cdgo) {
                $q->whereNull('c_sa.cdgo_crgo')->orWhere('c_sa.cdgo_crgo', '!=', $cdgo);
            })
            ->where('r.in_esta', '1')
            ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27'])
            ->whereBetween('p.fe_ingrso', [$desde, $hasta])
            ->count();
    }

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
