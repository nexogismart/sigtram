<?php

namespace App\Http\Controllers\mod_a\sgl;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_a\sgl\V2ListaReporteRequerimientosGeneralService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Support\SessionKeys;
use Illuminate\View\View;

/**
 * Reporte general de requerimientos de servicios / pago a proveedores
 * (migrado desde sigtram_antiguo/.../pago_proveedores/v2_lista_reporte_requerimientos_general.php).
 */
class V2ListaReporteRequerimientosGeneralController extends Controller
{
    private const VISTA = 'mod_a.sgl.pago_proveedores.v2_lista_reporte_requerimientos_general';

    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly V2ListaReporteRequerimientosGeneralService $service
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }

        $this->assertPermiso($user, $cargoId);

        [$feInicio, $feFin] = $this->rangoSolicitado($request);

        $stats = $this->service->statsPorArea($feInicio, $feFin);
        $listado = $this->service->listadoGeneral($feInicio, $feFin);

        return view(self::VISTA, [
            'feInicio' => $feInicio,
            'feFin' => $feFin,
            'filas' => ! empty($stats['success']) ? $stats['data']['filas'] : [],
            'totales' => ! empty($stats['success']) ? $stats['data']['totales'] : $this->totalesVacios(),
            'listado' => ! empty($listado['success']) ? $listado['data']['rows'] : [],
            'listadoRutas' => ! empty($listado['success']) ? $listado['data']['ruta_por_dto'] : [],
            'error' => empty($stats['success']) ? ($stats['error'] ?? 'Error al generar el reporte.') : null,
            'rutas' => [
                'index' => route('sgl.v2_lista_reporte_requerimientos_general'),
                'detalle' => route('sgl.v2_lista_reporte_requerimientos_general.detalle'),
                'export_detalle' => route('sgl.v2_lista_reporte_requerimientos_general.export_detalle'),
                'export_areas' => route('sgl.v2_lista_reporte_requerimientos_general.export_areas'),
                'seguimiento' => route('sgl.buscar_requerimiento_pago.seguimiento_tramite'),
                'requerimiento' => route('sgl.buscar_requerimiento_pago'),
                'inicio' => route('inicio'),
            ],
        ]);
    }

    /**
     * Detalle AJAX: devuelve el HTML parcial de la tabla (equivalente a action=get_detail_html).
     */
    public function detalle(Request $request): Response
    {
        if (! $this->checkAccess($request)) {
            return response('<div class="req-detail-msg req-detail-msg--error">Acceso denegado.</div>', 403)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        [$feInicio, $feFin] = $this->rangoSolicitado($request);

        $resultado = $this->service->detalle(
            (int) $request->query('area', -1),
            (int) $request->query('tipo', 0),
            (int) $request->query('offset', 0),
            $request->query('ml') !== null ? (string) $request->query('ml') : null,
            $feInicio,
            $feFin
        );

        if (empty($resultado['success'])) {
            $mensaje = e($resultado['error'] ?? 'No se pudo cargar el detalle.');

            return response('<div class="req-detail-msg req-detail-msg--error">' . $mensaje . '</div>', 200)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $html = view('mod_a.sgl.pago_proveedores.partials.v2_lista_reporte_requerimientos_general_detalle', [
            'rows' => $resultado['data']['rows'],
            'rutas' => $resultado['data']['ruta_por_dto'],
            'total' => $resultado['data']['total'],
            'offset' => $resultado['data']['offset'],
            'limit' => $resultado['data']['limit'],
            'areaId' => $resultado['data']['area_id'],
            'tipo' => $resultado['data']['tipo'],
            'ml' => $resultado['data']['ml'],
            'mlLabel' => $resultado['data']['ml_label'],
            'pane' => (string) $request->query('pane', '-1'),
            'urlSeguimiento' => route('sgl.buscar_requerimiento_pago.seguimiento_tramite'),
            'urlRequerimiento' => route('sgl.buscar_requerimiento_pago'),
        ])->render();

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function exportDetalle(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
        $this->assertPermiso($user, $cargoId);

        [$feInicio, $feFin] = $this->rangoSolicitado($request);

        $resultado = $this->service->exportDetalleCsv(
            (int) $request->query('area', -1),
            (int) $request->query('tipo', 0),
            $feInicio,
            $feFin
        );

        return $this->descargaCsv($resultado);
    }

    public function exportAreas(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
        $this->assertPermiso($user, $cargoId);

        [$feInicio, $feFin] = $this->rangoSolicitado($request);

        return $this->descargaCsv($this->service->exportAreasCsv($feInicio, $feFin));
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @param  array{success: bool, data?: array{filename: string, contenido: string}, error?: string}  $resultado
     */
    private function descargaCsv(array $resultado): Response
    {
        if (empty($resultado['success'])) {
            return response($resultado['error'] ?? 'No se pudo generar la exportación.', 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response($resultado['data']['contenido'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $resultado['data']['filename'] . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Acepta fe_inicio/fe_fin (Y-m-d) por GET o POST; por defecto, del 1 del mes actual a hoy (Lima).
     *
     * @return array{0: string, 1: string}
     */
    private function rangoSolicitado(Request $request): array
    {
        $hoy = Carbon::now('America/Lima');

        $feInicio = trim((string) $request->input('fe_inicio', $request->input('fi', '')));
        $feFin = trim((string) $request->input('fe_fin', $request->input('ff', '')));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $feInicio) !== 1) {
            $feInicio = $hoy->copy()->startOfMonth()->format('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $feFin) !== 1) {
            $feFin = $hoy->format('Y-m-d');
        }

        return [$feInicio, $feFin];
    }

    private function totalesVacios(): array
    {
        return [
            'total' => 0,
            'por_iniciar' => 0,
            'en_tramite' => 0,
            'en_proceso' => 0,
            'finalizados' => 0,
            'gestionados' => 0,
            'pct_trabajo' => 0,
        ];
    }

    private function checkAccess(Request $request): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);

        return $cargoId > 0 && $this->tienePermiso($user, $cargoId);
    }

    private function assertPermiso(User $user, int $cargoId): void
    {
        if ($cargoId <= 0 || ! $this->tienePermiso($user, $cargoId)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function tienePermiso(User $user, int $cargoId): bool
    {
        $rutas = [
            '/mod_a/sgl/pago_proveedores/v2_lista_reporte_requerimientos_general',
            '/mod_a/sgl/pago_proveedores/v2_lista_reporte_requerimientos_general.blade.php',
            '/mod_a/sgl/v2_lista_reporte_requerimientos_general',
        ];

        foreach ($rutas as $ruta) {
            if ($this->securityCore->tienePermiso((int) $user->cdgo_lgin, $ruta, $cargoId)) {
                return true;
            }
        }

        return false;
    }
}
