<?php

namespace App\Http\Controllers\mod_a\sgl;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_a\sgl\PagoProveedoresIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Support\SessionKeys;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pago a proveedores — principal
 * (migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/pago_proveedores/index.php).
 */
class PagoProveedoresIndexController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly PagoProveedoresIndexService $service
    ) {}

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }

        $vista = 'mod_a.sgl.pago_proveedores.index';
        if (is_string($pathVista) && $pathVista !== '' && $this->existeVistaBlade($pathVista)) {
            $vista = $pathVista;
        }

        $tituloOpcion = trim((string) ($opcionMenu->dc_rle ?? 'Pago a proveedores'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Pago a proveedores';
        }

        return view($vista, [
            'tituloOpcion' => $tituloOpcion,
            'catalogos' => $this->service->catalogos(),
            'openPgoId' => (int) $request->query('intCdgoPgoPrvdres', $request->query('id', 0)),
            'routes' => $this->apiRoutes(),
        ]);
    }

    public function listar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->listar($request->all());

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 500);
    }

    public function detalle(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->detalle((int) $request->input('id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 404);
    }

    public function guardar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->guardar($request->all());

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function anular(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->anular((int) $request->input('id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function finalizar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->finalizar((int) $request->input('id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function bajaItem(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->bajaItem((int) $request->input('item_id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function semilla(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->semilla((int) $request->input('tipo_id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function generarTramite(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->generarTramite(
            (int) $request->input('id', 0),
            (int) $request->input('tipo_id', 0),
            (string) $request->input('semilla', ''),
            $request->file('adjunto')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function generarMemo(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->generarMemo(
            (int) $request->input('id', 0),
            (int) $request->input('tipo_id', 0),
            (string) $request->input('semilla', ''),
            $request->file('adjunto')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function itemDetalle(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->itemDetalle((int) $request->input('item_id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 404);
    }

    public function guardarTdrSiga(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->guardarTdrSiga(
            (int) $request->input('item_id', 0),
            $request->file('tdr'),
            $request->file('siga')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function guardarAnexos(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->guardarAnexos(
            (int) $request->input('item_id', 0),
            $request->only(['ruc', 'cci', 'monto']),
            $request->file('anexos'),
            $request->file('servir')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function guardarConformidad(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $pagos = [];
        foreach ((array) $request->input('pagos', []) as $i => $pago) {
            $pagos[] = [
                'id' => (int) ($pago['id'] ?? 0),
                'recibo' => (string) ($pago['recibo'] ?? ''),
                'suspension' => ! empty($pago['suspension']),
                'archivo' => $request->file('pagos.' . $i . '.archivo'),
            ];
        }

        $resultado = $this->service->guardarConformidad(
            (int) $request->input('item_id', 0),
            $pagos,
            $request->file('adicional')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function archivo(Request $request): BinaryFileResponse|Response|JsonResponse
    {
        if (! $this->checkAccess($request)) {
            abort(403, 'Acceso denegado.');
        }

        $archivo = basename(str_replace('\\', '/', (string) $request->query('nom_archivo', $request->query('archivo', ''))));
        if ($archivo === '') {
            abort(404, 'Archivo no indicado.');
        }

        $ruta = $this->service->resolverRutaArchivo($archivo);
        if ($ruta === null) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->file($ruta, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $archivo . '"',
        ]);
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

    private function tienePermiso(User $user, int $cargoId): bool
    {
        $rutas = [
            '/mod_a/sgl/pago_proveedores/index',
            '/mod_a/sgl/pago_proveedores',
        ];

        foreach ($rutas as $ruta) {
            if ($this->securityCore->tienePermiso((int) $user->cdgo_lgin, $ruta, $cargoId)) {
                return true;
            }
        }

        return false;
    }

    private function apiRoutes(): array
    {
        return [
            'listar' => route('sgl.pago_proveedores.listar', [], false),
            'detalle' => route('sgl.pago_proveedores.detalle', [], false),
            'guardar' => route('sgl.pago_proveedores.guardar', [], false),
            'anular' => route('sgl.pago_proveedores.anular', [], false),
            'finalizar' => route('sgl.pago_proveedores.finalizar', [], false),
            'bajaItem' => route('sgl.pago_proveedores.baja_item', [], false),
            'semilla' => route('sgl.pago_proveedores.semilla', [], false),
            'generarTramite' => route('sgl.pago_proveedores.generar_tramite', [], false),
            'generarMemo' => route('sgl.pago_proveedores.generar_memo', [], false),
            'itemDetalle' => route('sgl.pago_proveedores.item_detalle', [], false),
            'guardarTdr' => route('sgl.pago_proveedores.guardar_tdr', [], false),
            'guardarAnexos' => route('sgl.pago_proveedores.guardar_anexos', [], false),
            'guardarConformidad' => route('sgl.pago_proveedores.guardar_conformidad', [], false),
            'archivo' => route('sgl.pago_proveedores.archivo', [], false),
            'seguimiento' => route('sgl.buscar_requerimiento_pago.seguimiento_tramite', [], false),
        ];
    }
}
