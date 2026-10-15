<?php

namespace App\Http\Controllers\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use App\Support\SessionKeys;

class ReporteExpedienteController extends Controller
{
    private TramiteExternoRepositoryInterface $tramiteRepo;

    public function __construct(TramiteExternoRepositoryInterface $tramiteRepo)
    {
        $this->tramiteRepo = $tramiteRepo;
    }

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null)
    {
        $pathVista = $this->vistaDesdeMenu($pathVista, $opcionMenu);
        $tituloOpcion = trim((string) ($opcionMenu->dc_rle ?? 'Reporte General de Trámites Externos'));
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

        $perPage = (int) ($data['per_page'] ?? 25);
        $page = (int) ($data['page'] ?? 1);
        $company = $this->currentCompanyId();

        $res = $this->tramiteRepo->buscarReporteGeneral($data, $page, $perPage, $company);

        return response()->json([
            'status' => 'success',
            'data' => collect($res['items'])->map(fn ($row) => $this->formatExpedienteRow((object) $row))->values(),
            'pagination' => [
                'current_page' => $res['current_page'],
                'last_page' => $res['last_page'],
                'per_page' => $res['per_page'],
                'total' => $res['total'],
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

        $company = $this->currentCompanyId();
        $rows = $data['tipo'] === 'cargo'
            ? $this->tramiteRepo->obtenerReporteGeneralResumenCargo($data['desde'], $data['hasta'], $this->currentAreaId(), $company)
            : $this->tramiteRepo->obtenerReporteGeneralResumenArea($data['desde'], $data['hasta'], $company);

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

        $company = $this->currentCompanyId();
        $rows = $this->tramiteRepo->obtenerReporteGeneralDetalleBandeja(
            $data['tipo'],
            (int) $data['id'],
            $data['categoria'],
            $data['desde'],
            $data['hasta'],
            $company
        );

        if ($data['tipo'] === 'area') {
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

        return response()->json([
            'status' => 'success',
            'data' => collect($rows)->map(fn ($row) => $this->formatExpedienteRow((object) $row))->values(),
        ]);
    }

    public function detalle(Request $request): JsonResponse
    {
        $this->authorizeReportAccess([3, 6, 15, 106]);

        $data = $request->validate([
            'id' => 'required|integer|min:1',
        ]);

        $company = $this->currentCompanyId();
        $row = $this->tramiteRepo->obtenerDetalleReporteGeneral((int) $data['id'], $company);

        if (! $row) {
            return response()->json(['status' => 'error', 'message' => 'Expediente no encontrado'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatExpedienteRow((object) $row),
        ]);
    }

    public function imprimirReporte(Request $request)
    {
        $this->authorizeReportAccess([3, 6, 15, 106]);

        $view = $request->input('view', 'buscador');
        $company = $this->currentCompanyId();
        $data = [];

        if ($view === 'buscador') {
            $selectedIds = $request->input('selected_ids');
            $rows = collect($this->tramiteRepo->obtenerReporteGeneralParaImprimir($request->all(), $company, is_array($selectedIds) ? $selectedIds : null, 1000))
                ->map(fn ($row) => $this->formatExpedienteRow((object) $row))
                ->values();

            $data = ['tipo' => 'buscador', 'filas' => $rows, 'filtros' => $request->all()];
        } else {
            $tipo = $request->input('view', 'area');
            $desde = $request->input('desde');
            $hasta = $request->input('hasta');

            if (! $desde || ! $hasta) {
                $desde = Carbon::now('America/Lima')->startOfMonth()->format('Y-m-d');
                $hasta = Carbon::now('America/Lima')->format('Y-m-d');
            }

            $rows = $tipo === 'cargo'
                ? $this->tramiteRepo->obtenerReporteGeneralResumenCargo($desde, $hasta, $this->currentAreaId(), $company)
                : $this->tramiteRepo->obtenerReporteGeneralResumenArea($desde, $hasta, $company);

            if ($tipo === 'sinfinalizar') {
                $rows = array_values(array_filter($rows, fn ($row) => (int) $row->sin_finalizar > 0));
            }

            $data = [
                'tipo' => 'resumen',
                'subtipo' => $tipo,
                'filas' => $rows,
                'totales' => $this->calculateSummaryTotals($rows),
                'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            ];
        }

        $pdf = Pdf::loadView('mod_gm.mod_tram_exte.pdf.reporte-general', $data)
                  ->setPaper('a4', 'landscape');

        return $pdf->stream('reporte_expedientes.pdf');
    }

    public function imprimirExpediente($id)
    {
        $this->authorizeReportAccess([3, 6, 15, 106]);

        $company = $this->currentCompanyId();
        $row = $this->tramiteRepo->obtenerDetalleReporteGeneral((int) $id, $company);

        if (! $row) {
            abort(404, 'Expediente no encontrado');
        }

        $expediente = $this->formatExpedienteRow((object) $row);

        $pdf = Pdf::loadView('mod_gm.mod_tram_exte.pdf.reporte-expediente-pdf', ['expediente' => $expediente])
                  ->setPaper('a4', 'portrait');

        return $pdf->stream('expediente_' . $expediente['expediente_raw'] . '.pdf');
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
        return (int) session(SessionKeys::USER_COMPANY, 0);
    }

    private function currentAreaId(): int
    {
        $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
        if ($cargoId <= 0) {
            return 0;
        }

        return $this->tramiteRepo->obtenerAreaPorCargo($cargoId);
    }

    private function authorizeReportAccess(array $roleIds): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $hasPermission = $this->tramiteRepo->verificarPermisoReporte((int) $user->cdgo_prfle, $roleIds);

        if (! $hasPermission) {
            abort(403, 'No tiene permiso para acceder a este modulo.');
        }
    }
}
