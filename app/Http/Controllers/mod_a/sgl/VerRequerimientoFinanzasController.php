<?php

namespace App\Http\Controllers\mod_a\sgl;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_a\sgl\VerRequerimientoFinanzasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pago a proveedores — Finanzas
 * (migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/pago_proveedores/ver_requerimiento_finanzas.php).
 */
class VerRequerimientoFinanzasController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly VerRequerimientoFinanzasService $service
    ) {}

    public function index(Request $request, ?string $pathVista = null, ?object $opcionMenu = null): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }

        $vista = 'mod_a.sgl.pago_proveedores.ver_requerimiento_finanzas';
        if (is_string($pathVista) && $pathVista !== '' && $this->existeVistaBlade($pathVista)) {
            $vista = $pathVista;
        }

        $tituloOpcion = trim((string) ($opcionMenu->dc_rle ?? 'Pago a proveedores - Finanzas'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Pago a proveedores - Finanzas';
        }

        return view($vista, [
            'tituloOpcion' => $tituloOpcion,
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

    public function itemDetalle(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->itemDetalle((int) $request->input('item_id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 404);
    }

    public function finalizar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->finalizar((int) $request->input('id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function devolver(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->devolver(
            (int) $request->input('id', 0),
            (string) $request->input('motivo', '')
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
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

        return $cargoId > 0 && $this->tienePermiso($user, $cargoId);
    }

    private function tienePermiso(User $user, int $cargoId): bool
    {
        $rutas = [
            '/mod_a/sgl/pago_proveedores/ver_requerimiento_finanzas',
            '/mod_a/sgl/pago_proveedores/ver_requerimiento_finanzas.php',
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
            'listar' => route('sgl.pago_proveedores.finanzas.listar', [], false),
            'detalle' => route('sgl.pago_proveedores.finanzas.detalle', [], false),
            'itemDetalle' => route('sgl.pago_proveedores.finanzas.item_detalle', [], false),
            'finalizar' => route('sgl.pago_proveedores.finanzas.finalizar', [], false),
            'devolver' => route('sgl.pago_proveedores.finanzas.devolver', [], false),
            'archivo' => route('sgl.pago_proveedores.finanzas.archivo', [], false),
            'seguimiento' => route('sgl.buscar_requerimiento_pago.seguimiento_tramite', [], false),
        ];
    }
}
