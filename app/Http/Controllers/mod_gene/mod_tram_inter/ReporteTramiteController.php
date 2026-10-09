<?php

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Equivalente a lista_reporte_area.php:
 * reporte general de trámites internos por gerencia/área.
 */
class ReporteTramiteController extends Controller
{
    private string $schema;

    public function __construct()
    {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    public function index(Request $request)
    {
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

        $reportes = $this->buildReport($fechaDesdeSql, $fechaHastaSql);

        $totales = [
            'sum_entregado' => 0,
            'sum_recibido' => 0,
            'sum_salida' => 0,
            'sum_finalizado' => 0,
            'sum_total_doc' => 0,
            'sum_total_ges' => 0,
            'porcentaje' => 0,
        ];

        foreach ($reportes as $r) {
            $totales['sum_entregado'] += $r->por_recibir;
            $totales['sum_recibido'] += $r->recibidos;
            $totales['sum_salida'] += $r->derivados;
            $totales['sum_finalizado'] += $r->finalizados;
            $totales['sum_total_doc'] += $r->total_doc;
            $totales['sum_total_ges'] += ($r->derivados + $r->finalizados);
        }

        $totales['porcentaje'] = $totales['sum_total_doc'] > 0
            ? number_format(($totales['sum_total_ges'] / $totales['sum_total_doc']) * 100, 2, '.', '')
            : number_format(0, 2, '.', '');

        return view('mod_gene.mod_tram_inter.reporte-tramite', [
            'reportes' => $reportes,
            'totales' => $totales,
            'desdeStr' => $desdeStr,
            'hastaStr' => $hastaStr,
        ]);
    }

    /**
     * Equivalente a ajax_reporte_area.php (tipos 1–4).
     */
    public function ajaxDetalle(Request $request)
    {
        $areaId = (int) $request->input('area_id');
        $tipo = (int) $request->input('tipo_bandeja');
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
            ->join("{$s}a_tpo_trmtes as te", 'p.cdgo_tpo_trmte', '=', 'te.cdgo_tpo_trmte')
            ->join("{$s}p_crgos as c_en", 'r.cdgo_crgo_en', '=', 'c_en.cdgo_crgo')
            ->join("{$s}a_areas as a_en", 'c_en.cdgo_area', '=', 'a_en.cdgo_area')
            ->leftJoin("{$s}p_crgos as c_sa", 'r.cdgo_crgo_sa', '=', 'c_sa.cdgo_crgo')
            ->leftJoin("{$s}a_areas as a_sa", 'c_sa.cdgo_area', '=', 'a_sa.cdgo_area')
            ->join("{$s}a_areas as a_inco", 'p.cdgo_area', '=', 'a_inco.cdgo_area')
            ->where('r.in_esta', '1')
            ->whereBetween('p.fe_ingrso', [$fechaDesdeSql, $fechaHastaSql]);

        if ($tipo === 1) {
            $baseQuery->where('a_en.cdgo_area', $areaId)
                ->where(function ($q) use ($areaId) {
                    $q->whereNull('a_sa.cdgo_area')
                        ->orWhere('a_sa.cdgo_area', '!=', $areaId);
                })
                ->whereNotIn('r.cdgo_estdo_trmte', ['5', '12', '29', '27']);
        } elseif ($tipo === 2) {
            $baseQuery->where('a_en.cdgo_area', $areaId)
                ->where('a_sa.cdgo_area', $areaId)
                ->whereNotIn('r.cdgo_estdo_trmte', ['1', '3', '24']);
        } elseif ($tipo === 3) {
            $baseQuery->where('a_sa.cdgo_area', $areaId)
                ->where('a_en.cdgo_area', '!=', $areaId);
        } elseif ($tipo === 4) {
            $baseQuery->where('a_en.cdgo_area', $areaId)
                ->where('a_sa.cdgo_area', $areaId)
                ->whereIn('r.cdgo_estdo_trmte', ['3', '24']);
        }

        $tramites = $baseQuery
            ->orderBy('te.dc_tpo_trmte')
            ->orderBy('p.fe_ingrso')
            ->get();

        foreach ($tramites as $tramite) {
            $tramite->fechaini = Carbon::parse($tramite->fe_ingrso)->timezone('America/Lima')->format('d/m/Y H:i:s');
            $tramite->fe_ultima = $tramite->fe_rcbdo
                ? Carbon::parse($tramite->fe_rcbdo)->timezone('America/Lima')->format('d/m/Y H:i:s')
                : '-';
            $tramite->nu_tram_format = str_pad((string) $tramite->nu_tram_todo, 10, '0', STR_PAD_LEFT);
            $tramite->de_obser_corto = mb_substr((string) ($tramite->de_obser ?? ''), 0, 55);
        }

        $html = view('mod_gene.mod_tram_inter.partials.reporte-tramite-detalle', [
            'tramites' => $tramites,
            'uid' => 'tbl_area_' . $areaId . '_' . $tipo,
        ])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * gene_count_tramite_area_* optimizado (mismas reglas del legado).
     */
    private function buildReport(string $fechaDesde, string $fechaHasta): array
    {
        $s = $this->schema;

        $areas = DB::table($s . 'a_areas')
            ->where('in_esta', '1')
            ->where('cdgo_area', '!=', 0)
            ->orderBy('de_area')
            ->get(['cdgo_area', 'de_area'])
            ->keyBy('cdgo_area');

        $q1Sql = "
            SELECT
                c_en.cdgo_area,
                SUM(CASE WHEN (a_sa.cdgo_area IS NULL OR a_sa.cdgo_area != c_en.cdgo_area)
                          AND r.cdgo_estdo_trmte NOT IN ('5','12','29','27') THEN 1 ELSE 0 END) AS por_recibir,
                SUM(CASE WHEN a_sa.cdgo_area = c_en.cdgo_area
                          AND r.cdgo_estdo_trmte NOT IN ('1','3','24') THEN 1 ELSE 0 END) AS recibidos,
                SUM(CASE WHEN a_sa.cdgo_area = c_en.cdgo_area
                          AND r.cdgo_estdo_trmte IN ('3','24') THEN 1 ELSE 0 END) AS finalizados
            FROM {$s}r_trmtes_ntrnos r
            INNER JOIN {$s}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            LEFT JOIN {$s}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            LEFT JOIN {$s}a_areas a_sa ON c_sa.cdgo_area = a_sa.cdgo_area
            INNER JOIN {$s}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
            WHERE r.in_esta = '1'
              AND p.fe_ingrso >= ? AND p.fe_ingrso <= ?
            GROUP BY c_en.cdgo_area
        ";

        $q1Results = collect(DB::select($q1Sql, [$fechaDesde, $fechaHasta]))->keyBy('cdgo_area');

        $q2Sql = "
            SELECT
                c_sa.cdgo_area,
                COUNT(*) AS derivados
            FROM {$s}r_trmtes_ntrnos r
            INNER JOIN {$s}p_crgos c_en ON r.cdgo_crgo_en = c_en.cdgo_crgo
            INNER JOIN {$s}a_areas a_en ON c_en.cdgo_area = a_en.cdgo_area
            INNER JOIN {$s}p_crgos c_sa ON r.cdgo_crgo_sa = c_sa.cdgo_crgo
            INNER JOIN {$s}p_dtos_ntrnos p ON r.cdgo_dtos_ntrnos = p.cdgo_dtos_ntrnos
            WHERE r.in_esta = '1'
              AND a_en.cdgo_area != c_sa.cdgo_area
              AND p.fe_ingrso >= ? AND p.fe_ingrso <= ?
            GROUP BY c_sa.cdgo_area
        ";

        $q2Results = collect(DB::select($q2Sql, [$fechaDesde, $fechaHasta]))->keyBy('cdgo_area');

        $results = [];
        foreach ($areas as $cdgoArea => $area) {
            $porRecibir = isset($q1Results[$cdgoArea]) ? (int) $q1Results[$cdgoArea]->por_recibir : 0;
            $recibidos = isset($q1Results[$cdgoArea]) ? (int) $q1Results[$cdgoArea]->recibidos : 0;
            $finalizados = isset($q1Results[$cdgoArea]) ? (int) $q1Results[$cdgoArea]->finalizados : 0;
            $derivados = isset($q2Results[$cdgoArea]) ? (int) $q2Results[$cdgoArea]->derivados : 0;
            $totalDoc = $porRecibir + $recibidos + $derivados + $finalizados;

            // Antiguo: inserta todas las áreas; % = 0.00 si total = 0
            $porcentaje = $totalDoc > 0
                ? number_format((($derivados + $finalizados) / $totalDoc) * 100, 2, '.', '')
                : number_format(0, 2, '.', '');

            $results[] = (object) [
                'cdgo_area' => (int) $cdgoArea,
                'de_area' => $area->de_area,
                'por_recibir' => $porRecibir,
                'recibidos' => $recibidos,
                'derivados' => $derivados,
                'finalizados' => $finalizados,
                'total_doc' => $totalDoc,
                'porcentaje' => $porcentaje,
                'porcentaje_num' => (float) $porcentaje,
            ];
        }

        // ORDER BY nu_prcntje DESC, nu_total DESC
        usort($results, function ($a, $b) {
            if ($b->porcentaje_num !== $a->porcentaje_num) {
                return $b->porcentaje_num <=> $a->porcentaje_num;
            }

            return $b->total_doc <=> $a->total_doc;
        });

        return $results;
    }
}
