<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use App\Services\mod_gene\mod_tram_inter\ReporteAreaCargoInternoService;
use App\Support\SessionKeys;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Equivalente a v2_lista_reporte_area_cargo.php:
 * dashboard moderno + analytics por cargo del área.
 */
class V2ListaReporteAreaCargoController extends Controller
{
    public function __construct(
        private readonly ReporteAreaCargoInternoService $reporteService
    ) {
    }

    public function index(Request $request): View
    {
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        $usIdsArea = $cargoId > 0 ? $this->reporteService->obtenerAreaUsuario($cargoId) : 0;
        $hoy = Carbon::now('America/Lima');

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

        $res = $this->reporteService->generarDashboardV2($usIdsArea, $fechaDesdeSql, $fechaHastaSql);

        return view('mod_gene.mod_tram_inter.v2_lista_reporte_area_cargo', [
            'rows_data' => $res['rows_data'],
            'totales' => $res['totales'],
            'evoData' => $res['evoData'],
            'desdeStr' => $desdeStr,
            'hastaStr' => $hastaStr,
        ]);
    }

    public function ajaxDetalle(Request $request): JsonResponse
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

        $tramites = $this->reporteService->obtenerTramitesDetalleV2($cargo_id, $tipo_bandeja, $fechaDesdeSql, $fechaHastaSql);

        $viewHtml = view('mod_gene.mod_tram_inter.partials.v2_lista_reporte_area_cargo_detalle', [
            'tramites' => $tramites,
            'uid' => 'tbl_v2_' . $cargo_id . '_' . $tipo_bandeja,
        ])->render();

        return response()->json(['html' => $viewHtml]);
    }
}
