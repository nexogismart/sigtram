<?php

declare(strict_types=1);

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use App\Services\mod_gene\mod_tram_inter\ReporteCargoInternoService;
use App\Support\SessionKeys;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Equivalente a lista_reporte_area_cargo.php:
 * reporte de trámites internos por cargo del área del usuario.
 */
class ReporteCargoInternoController extends Controller
{
    public function __construct(
        private readonly ReporteCargoInternoService $reporteService
    ) {
    }

    public function index(Request $request): View
    {
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        $usIdsArea = $cargoId > 0 ? $this->reporteService->obtenerAreaUsuario($cargoId) : 0;
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

        $reporte = $this->reporteService->generarReporte($usIdsArea, $fechaDesdeSql, $fechaHastaSql);

        return view('mod_gene.mod_tram_inter.reporte-cargo-interno', [
            'rows_data' => $reporte['rows_data'],
            'totales' => $reporte['totales'],
            'desdeStr' => $desdeStr,
            'hastaStr' => $hastaStr,
            'usIdsArea' => $usIdsArea,
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

        $tramites = $this->reporteService->obtenerTramitesDetalle($cargo_id, $tipo_bandeja, $fechaDesdeSql, $fechaHastaSql);

        $viewHtml = view('mod_gene.mod_tram_inter.partials.reporte-cargo-interno-detalle', [
            'tramites' => $tramites,
            'uid' => 'tbl_int_' . $cargo_id . '_' . $tipo_bandeja,
        ])->render();

        return response()->json(['html' => $viewHtml]);
    }
}
