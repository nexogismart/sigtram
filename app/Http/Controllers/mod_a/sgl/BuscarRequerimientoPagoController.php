<?php

namespace App\Http\Controllers\mod_a\sgl;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_a\sgl\BuscarRequerimientoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controlador de SOLO CONSULTA del módulo "Buscar Requerimiento de Pago"
 * (migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago.php).
 *
 * No expone ningún endpoint de creación/edición/eliminación: únicamente
 * lista los requerimientos de pago a proveedores y muestra su detalle.
 */
class BuscarRequerimientoPagoController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly BuscarRequerimientoPagoService $service
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }

        $this->assertPermiso($user, $cargoId);

        $areasResultado = $this->service->catalogoAreas();

        return view('mod_a.sgl.pago_proveedores.buscar_requerimiento_pago', [
            'routes' => $this->apiRoutes(),
            'areas' => ! empty($areasResultado['success']) ? $areasResultado['data'] : [],
            'openPgoId' => (int) $request->query('intCdgoPgoPrvdres', $request->query('id', 0)),
        ]);
    }

    public function listar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->listar([
            'area_id' => (int) $request->query('area_id', 0),
            'campo' => (string) $request->query('campo', ''),
            'criterio' => trim((string) $request->query('criterio', '')),
            'fecha' => trim((string) $request->query('fecha', '')),
            'pagina' => (int) $request->query('pagina', 1),
            'porPagina' => (int) $request->query('porPagina', 25),
        ]);

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 500);
    }

    public function detalle(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) $request->query('id', 0);

        $resultado = $this->service->detalle($id);
        if (empty($resultado['success'])) {
            return response()->json($resultado, 404);
        }

        $seguimiento = $this->service->seguimientoPagos($id);
        $items = $this->service->listarItems($id);

        $data = $resultado['data'];
        $data['seguimiento'] = ! empty($seguimiento['success']) ? $seguimiento['data'] : [];
        $data['items'] = ! empty($items['success']) ? $items['data'] : [];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Vista embebida (iframe) del seguimiento del trámite interno,
     * equivalente a segi_tramite.php del sistema antiguo.
     */
    public function seguimientoTramite(Request $request): View|Response
    {
        if (! $this->checkAccess($request)) {
            abort(403, 'Acceso denegado.');
        }

        $nuExpe = (int) $request->query('nu_expe', 0);
        $resultado = $this->service->historialTramite($nuExpe);

        return view('mod_a.sgl.pago_proveedores.seguimiento_tramite_iframe', [
            'ok' => ! empty($resultado['success']),
            'error' => $resultado['error'] ?? null,
            'titulo' => $resultado['data']['titulo'] ?? '',
            'items' => $resultado['data']['items'] ?? [],
            'cdgoDtosNtrnos' => $resultado['data']['cdgo_dtos_ntrnos'] ?? $nuExpe,
        ]);
    }

    /**
     * Popup de detalle de un movimiento (equivalente a busca_vauche_adjun.php).
     */
    public function voucherMovimiento(Request $request): View|Response
    {
        if (! $this->checkAccess($request)) {
            abort(403, 'Acceso denegado.');
        }

        $id = (int) $request->query('id_tram', 0);
        $resultado = $this->service->detalleMovimientoInterno($id);

        return view('mod_a.sgl.pago_proveedores.voucher_movimiento_interno', [
            'ok' => ! empty($resultado['success']),
            'error' => $resultado['error'] ?? null,
            'data' => $resultado['data'] ?? [
                'numero' => '',
                'fecha' => '',
                'hora' => '',
                'adjunto' => '',
                'detalles' => '',
            ],
        ]);
    }

    /**
     * Sirve (solo lectura) un adjunto del requerimiento (TDR/ANEXOS) por nombre de archivo,
     * buscando primero en storage y luego en la ruta legacy conocida.
     */
    public function archivo(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $archivo = basename(trim((string) $request->query('archivo', '')));
        if ($archivo === '' || str_contains($archivo, '..')) {
            return response()->json(['success' => false, 'error' => 'Archivo inválido.'], 400);
        }

        $ruta = $this->resolverRutaArchivo($archivo);
        if ($ruta === null) {
            return response()->json(['success' => false, 'error' => 'Archivo no encontrado.'], 404);
        }

        $mime = @mime_content_type($ruta) ?: 'application/octet-stream';

        return response()->file($ruta, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $archivo . '"',
        ]);
    }

    /**
     * Resuelve la ruta física del adjunto. Solo lectura: nunca escribe ni modifica archivos.
     * 1) storage/app/mod_a/sgl/pago_proveedores/adjunto/ (convención nueva)
     * 2) Presentacion/mod_a/sgl/pago_proveedores/adjunto/ del sistema legacy (si está disponible).
     */
    private function resolverRutaArchivo(string $archivo): ?string
    {
        $legacyRoot = rtrim((string) config('filesystems.legacy_sigtram_root', base_path('../sigtram_antiguo')), '/');

        $candidatos = [
            storage_path('app/mod_a/sgl/pago_proveedores/adjunto/' . $archivo),
            $legacyRoot . '/Presentacion/mod_a/sgl/pago_proveedores/adjunto/' . $archivo,
        ];

        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    // ── Helpers de acceso ─────────────────────────────────────────────────

    private function checkAccess(Request $request): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

        return $cargoId > 0 && $this->tienePermiso($user, $cargoId);
    }

    private function assertPermiso(User $user, int $cargoId): void
    {
        if (! $this->tienePermiso($user, $cargoId)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function tienePermiso(User $user, int $cargoId): bool
    {
        return $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago', $cargoId)
            || $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_a/sgl/buscar_requerimiento_pago', $cargoId);
    }

    private function apiRoutes(): array
    {
        return [
            'listar' => route('sgl.buscar_requerimiento_pago.listar'),
            'detalle' => route('sgl.buscar_requerimiento_pago.detalle'),
            'archivo' => route('sgl.buscar_requerimiento_pago.archivo'),
            'seguimiento_tramite' => route('sgl.buscar_requerimiento_pago.seguimiento_tramite'),
            'index' => route('sgl.buscar_requerimiento_pago'),
        ];
    }
}
