<?php

namespace App\Http\Controllers\mod_a\sgl;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_a\sgl\FichaSeleccionSeaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\SessionKeys;
use Illuminate\View\View;

/**
 * Fichas de selección SE@CE
 * (migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/index.php).
 */
class FichaSeleccionSeaceController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly FichaSeleccionSeaceService $service
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

        $vista = 'mod_a.sgl.index';
        if (is_string($pathVista) && $pathVista !== '' && $this->existeVistaBlade($pathVista)) {
            $vista = $pathVista;
        }

        $tituloOpcion = trim((string) ($opcionMenu->dc_rle ?? 'Fichas de selección SE@CE'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Fichas de selección SE@CE';
        }

        $catalogos = $this->service->catalogos();

        return view($vista, [
            'tituloOpcion' => $tituloOpcion,
            'catalogos' => $catalogos,
            'routes' => [
                'buscar' => route('sgl.ficha_seleccion.buscar', [], false),
                'detalle' => route('sgl.ficha_seleccion.detalle', [], false),
                'guardar' => route('sgl.ficha_seleccion.guardar', [], false),
                'cronograma' => route('sgl.ficha_seleccion.cronograma', [], false),
                'buscaExpediDeta' => route('tramites.busca_expedi_deta', [], false),
                'historico' => route('mod_gm.v2_lista_reporte_area_cargo.historico', ['id' => '__HID__'], false),
            ],
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        return response()->json($this->service->listar([
            'campo' => (string) $request->input('campo', ''),
            'palabra' => (string) $request->input('palabra', ''),
            'desde' => (string) $request->input('desde', ''),
            'hasta' => (string) $request->input('hasta', ''),
            'pagina' => (int) $request->input('pagina', 1),
            'porPagina' => (int) $request->input('porPagina', 10),
        ]));
    }

    public function detalle(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        return response()->json($this->service->detalle((int) $request->input('id', 0)));
    }

    public function guardar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->guardar($request->all());

        return response()->json($resultado, ($resultado['status'] ?? '') === 'success' ? 200 : 422);
    }

    public function cronogramaFecha(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->actualizarFechaCronograma(
            (int) $request->input('id', 0),
            (string) $request->input('campo', ''),
            (string) $request->input('fecha', '')
        );

        return response()->json($resultado, ($resultado['status'] ?? '') === 'success' ? 200 : 422);
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
            '/mod_a/sgl/index',
            '/mod_a/sgl',
        ];

        foreach ($rutas as $ruta) {
            if ($this->securityCore->tienePermiso((int) $user->cdgo_lgin, $ruta, $cargoId)) {
                return true;
            }
        }

        return false;
    }
}
