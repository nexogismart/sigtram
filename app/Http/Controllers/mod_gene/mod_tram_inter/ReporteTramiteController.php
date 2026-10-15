<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use App\Services\mod_gene\mod_tram_inter\ReporteTramiteService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Equivalente a lista_reporte_area.php:
 * reporte general de trámites internos por gerencia/área.
 */
class ReporteTramiteController extends Controller
{
    public function __construct(
        private readonly ReporteTramiteService $reporteService
    ) {
    }

    public function index(Request $request): View
    {
        $hoy = Carbon::now('America/Lima');

        // Igual que el antiguo: default 01/mes → hoy (d/m/Y) vía POST
        $desdeStr = (string) $request->input('txt_fe_inicio', $hoy->copy()->startOfMonth()->format('d/m/Y'));
        $hastaStr = (string) $request->input('txt_fe_fin', $hoy->format('d/m/Y'));

        try {
            $fechaDesdeSql = Carbon::createFromFormat('d/m/Y', $desdeStr)->format('Y-m-d') . ' 00:00:00';
            $fechaHastaSql = Carbon::createFromFormat('d/m/Y', $hastaStr)->format('Y-m-d') . ' 23:59:59';
        } catch (\Exception $e) {
            $desdeStr = $hoy->copy()->startOfMonth()->format('d/m/Y');
            $hastaStr = $hoy->format('d/m/Y');
            $fechaDesdeSql = $hoy->copy()->startOfMonth()->format('Y-m-d') . ' 00:00:00';
            $fechaHastaSql = $hoy->format('Y-m-d') . ' 23:59:59';
        }

        $resultado = $this->reporteService->generarReporte($fechaDesdeSql, $fechaHastaSql);

        return view('mod_gene.mod_tram_inter.reporte-tramite', [
            'reportes' => $resultado['reportes'],
            'totales' => $resultado['totales'],
            'desdeStr' => $desdeStr,
            'hastaStr' => $hastaStr,
        ]);
    }

    /**
     * Equivalente a ajax_reporte_area.php (tipos 1–4).
     */
    public function ajaxDetalle(Request $request): JsonResponse
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

        $tramites = $this->reporteService->obtenerTramitesDetalle($areaId, $tipo, $fechaDesdeSql, $fechaHastaSql);

        $html = view('mod_gene.mod_tram_inter.partials.reporte-tramite-detalle', [
            'tramites' => $tramites,
            'uid' => 'tbl_area_' . $areaId . '_' . $tipo,
        ])->render();

        return response()->json(['html' => $html]);
    }
}
