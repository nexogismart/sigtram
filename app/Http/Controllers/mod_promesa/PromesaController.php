<?php

namespace App\Http\Controllers\mod_promesa;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_promesa\PromesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Controlador del módulo "Audiencias Públicas" (mod_promesa).
 *
 * Expone la vista SPA de administración de lugares/reuniones y sus
 * compromisos (promesas), además de los endpoints JSON usados por el
 * front-end en public/mod_promesa/js/app.js.
 */
class PromesaController extends Controller
{
    public function __construct(
        private readonly PromesaService $promesaService,
        private readonly SecurityCoreInterface $securityCore
    ) {}

    // ── Vista principal ──────────────────────────────────────────────────

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

        $areaId = $this->areaIdDesdeCargo($user, $cargoId);
        $accesoPleno = $this->promesaService->esAccesoPleno($areaId);

        return view('mod_promesa.index', [
            'routes' => $this->apiRoutes(),
            'accesoPleno' => $accesoPleno,
            'usNomArea' => (string) $request->session()->get('sigtram_area_nombre', ''),
            'usNomCargo' => (string) $request->session()->get('sigtram_cargo_nombre', ''),
            'usNombre' => $user->nombreMostrar(),
            'areaId' => $areaId,
        ]);
    }

    // ── Vistas popup (ventanas emergentes) ─────────────────────────────────

    public function formReunion(Request $request): View|RedirectResponse
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

        $areaId = $this->areaIdDesdeCargo($user, $cargoId);
        $id = $request->query('id');

        return view('mod_promesa.reunion', [
            'routes' => $this->apiRoutes(),
            'id' => $id !== null && $id !== '' ? (int) $id : null,
            'accesoPleno' => $this->promesaService->esAccesoPleno($areaId),
        ]);
    }

    public function formPromesa(Request $request): View|RedirectResponse
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

        $id = $request->query('id');
        $idReunion = $request->query('id_reunion');

        return view('mod_promesa.promesa', [
            'routes' => $this->apiRoutes(),
            'id' => $id !== null && $id !== '' ? (int) $id : null,
            'idReunion' => $idReunion !== null && $idReunion !== '' ? (int) $idReunion : null,
        ]);
    }

    public function formSeguimiento(Request $request): View|RedirectResponse
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

        return view('mod_promesa.seguimiento', [
            'routes' => $this->apiRoutes(),
            'idPromesa' => (int) $request->query('id_promesa', 0),
        ]);
    }

    public function formEliminarReunion(Request $request): View|RedirectResponse
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

        return view('mod_promesa.eliminar_reunion', [
            'routes' => $this->apiRoutes(),
            'id' => (int) $request->query('id', 0),
        ]);
    }

    public function formEliminarPromesa(Request $request): View|RedirectResponse
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

        return view('mod_promesa.eliminar_promesa', [
            'routes' => $this->apiRoutes(),
            'id' => (int) $request->query('id', 0),
        ]);
    }

    // ── Lugares / Reuniones ───────────────────────────────────────────────

    public function listarLugares(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->promesaService->listarLugares(
            trim((string) $request->query('palabra', '')),
            trim((string) $request->query('desde', '')),
            trim((string) $request->query('hasta', '')),
            (int) $request->query('pagina', 1),
            (int) $request->query('porPagina', 7)
        );

        return $this->jsonResult($resultado);
    }

    public function detalleLugar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $idReunion = (int) $request->query('id_reunion', 0);
        $resultado = $this->promesaService->detallePorcentajeAreas($idReunion);

        return $this->jsonResult($resultado);
    }

    public function obtenerLugar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) $request->query('id', 0);
        $resultado = $this->promesaService->obtenerLugar($id);

        return $this->jsonResult($resultado);
    }

    public function guardarLugar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $datos = [
            'zona_nombre' => (string) $request->input('zona_nombre', ''),
            'zona_long_lat' => (string) $request->input('zona_long_lat', ''),
            'fecha_rnn' => (string) $request->input('fecha_rnn', ''),
            'hora_rnn' => (string) $request->input('hora_rnn', ''),
        ];

        $resultado = $this->promesaService->guardarLugar($datos, $this->userId($request));

        return $this->jsonResult($resultado);
    }

    public function actualizarLugar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) $request->input('id', 0);
        $datos = [
            'zona_nombre' => (string) $request->input('zona_nombre', ''),
            'zona_long_lat' => (string) $request->input('zona_long_lat', ''),
            'fecha_rnn' => (string) $request->input('fecha_rnn', ''),
            'hora_rnn' => (string) $request->input('hora_rnn', ''),
        ];

        $pdf = null;
        if ($request->hasFile('file_url_rnn')) {
            $pdf = $request->file('file_url_rnn');
        }

        $resultado = $this->promesaService->actualizarLugar($id, $datos, $this->userId($request), $pdf);

        return $this->jsonResult($resultado);
    }

    public function eliminarLugar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) $request->input('id', 0);
        $resultado = $this->promesaService->eliminarLugar($id, $this->userId($request));

        return $this->jsonResult($resultado);
    }

    // ── Promesas / Compromisos ───────────────────────────────────────────

    public function listarPromesas(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $areaId = $this->areaIdDesdeSesion($request);
        $accesoPleno = $this->promesaService->esAccesoPleno($areaId);

        $resultado = $this->promesaService->listarPromesas(
            (int) $request->query('id_reunion', 0),
            $areaId,
            $accesoPleno,
            trim((string) $request->query('criterio', '')),
            trim((string) $request->query('palabra', '')),
            (int) $request->query('pagina', 1),
            (int) $request->query('porPagina', 7)
        );

        return $this->jsonResult($resultado);
    }

    public function obtenerPromesa(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) $request->query('id', 0);
        $resultado = $this->promesaService->obtenerPromesa($id);

        return $this->jsonResult($resultado);
    }

    public function guardarPromesa(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $datos = $this->promesaPayload($request);
        $resultado = $this->promesaService->guardarPromesa($datos, $this->userId($request));

        return $this->jsonResult($resultado);
    }

    public function actualizarPromesa(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) $request->input('id', 0);
        $datos = $this->promesaPayload($request);
        $resultado = $this->promesaService->actualizarPromesa($id, $datos, $this->userId($request));

        return $this->jsonResult($resultado);
    }

    public function eliminarPromesa(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $id = (int) $request->input('id', 0);
        $resultado = $this->promesaService->eliminarPromesa($id);

        return $this->jsonResult($resultado);
    }

    // ── Seguimiento / Actividades ────────────────────────────────────────

    public function listarSeguimiento(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $idPromesa = (int) $request->query('id_promesa', 0);
        $resultado = $this->promesaService->listarSeguimiento($idPromesa);

        return $this->jsonResult($resultado);
    }

    public function guardarActividad(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->promesaService->guardarActividad(
            (int) $request->input('id_promesa', 0),
            (string) $request->input('actividad', ''),
            (string) $request->input('fe_inicio', ''),
            (string) $request->input('fe_final', ''),
            $this->userId($request)
        );

        return $this->jsonResult($resultado);
    }

    public function extenderActividad(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->promesaService->extenderActividad(
            (int) $request->input('id_actividad', 0),
            (int) $request->input('id_promesa', 0),
            (string) $request->input('actividad', ''),
            (string) $request->input('fe_inicio', ''),
            (string) $request->input('fe_final', ''),
            $this->userId($request)
        );

        return $this->jsonResult($resultado);
    }

    public function finalizarActividad(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $idActividad = (int) $request->input('id_actividad', 0);
        $resultado = $this->promesaService->finalizarActividad($idActividad);

        return $this->jsonResult($resultado);
    }

    public function eliminarActividad(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $idActividad = (int) $request->input('id_actividad', 0);
        $resultado = $this->promesaService->eliminarActividad($idActividad);

        return $this->jsonResult($resultado);
    }

    public function finalizarSeguimiento(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $idPromesa = (int) $request->input('id_promesa', 0);
        $resultado = $this->promesaService->finalizarSeguimientoPromesa($idPromesa, $this->userId($request));

        return $this->jsonResult($resultado);
    }

    // ── Catálogos ─────────────────────────────────────────────────────────

    public function catalogos(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->promesaService->catalogosAreas();

        return $this->jsonResult($resultado);
    }

    public function personalPorArea(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $areaId = (int) $request->query('area', 0);
        $resultado = $this->promesaService->personalPorArea($areaId);

        return $this->jsonResult($resultado);
    }

    // ── Impresión ─────────────────────────────────────────────────────────

    public function imprimir(Request $request): View|JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $ids = $request->query('ids', []);
        if (! is_array($ids)) {
            $ids = [$ids];
        }

        $resultado = $this->promesaService->listarPromesasImpresion($ids);
        $items = ! empty($resultado['success']) ? ($resultado['data'] ?? []) : [];

        $lugar = null;
        if ($items !== []) {
            $idReunion = (int) ($items[0]['id_reunion'] ?? 0);
            if ($idReunion > 0) {
                $lugarResultado = $this->promesaService->obtenerLugar($idReunion);
                if (! empty($lugarResultado['success'])) {
                    $lugar = $lugarResultado['data'];
                }
            }
        }

        return view('mod_promesa.imprimir', [
            'items' => $items,
            'lugar' => $lugar,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Construye el arreglo de datos de una promesa a partir del request,
     * usado tanto por guardarPromesa() como por actualizarPromesa().
     */
    private function promesaPayload(Request $request): array
    {
        return [
            'id_reunion' => (int) $request->input('id_reunion', 0),
            'dni_dirigente' => (string) $request->input('dni', ''),
            'dirigente' => (string) $request->input('nombres', ''),
            'telefono' => (string) $request->input('telefono', ''),
            'pedido' => (string) $request->input('pedido', ''),
            'area_id' => (int) $request->input('unidad', 0),
            'responsable_id' => $request->input('responsable', ''),
            'responsable_nombre' => (string) $request->input('responsable_nombre', ''),
            'compromiso' => (string) $request->input('des_compromiso', ''),
            'avance' => (string) $request->input('avance', '0'),
            'comentario' => (string) $request->input('comentario', ''),
            'fecha_cumplimiento' => (string) $request->input('fecha_cumplimiento', ''),
        ];
    }

    private function jsonResult(array $resultado): JsonResponse
    {
        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
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

    private function assertPermiso(User $user, int $cargoId): void
    {
        if (! $this->tienePermiso($user, $cargoId)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function tienePermiso(User $user, int $cargoId): bool
    {
        return $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_promesa', $cargoId)
            || $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_promesa/index', $cargoId);
    }

    /**
     * Obtiene el cdgo_area del cargo activo del usuario en sesión.
     */
    private function areaIdDesdeCargo(User $user, int $cargoId): int
    {
        $cargos = $this->securityCore->obtenerCargosUsuario((int) $user->cdgo_lgin);
        $cargo = $cargos->firstWhere('cdgo_crgo', $cargoId);

        return (int) ($cargo->cdgo_area ?? 0);
    }

    /**
     * Alias de areaIdDesdeCargo() a partir del Request actual (sesión).
     */
    private function areaIdDesdeSesion(Request $request): int
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return 0;
        }
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

        return $this->areaIdDesdeCargo($user, $cargoId);
    }

    /**
     * Id de usuario (cdgo_lgin) usado para las columnas de auditoría
     * (cdgo_usuario_ins / cdgo_usuario_upd / cdgo_usuario_del / cdgo_persona).
     */
    private function userId(Request $request): int
    {
        $user = Auth::user();

        return $user instanceof User ? (int) $user->cdgo_lgin : 0;
    }

    private function apiRoutes(): array
    {
        return [
            'listar_lugares' => route('promesa.listar_lugares'),
            'detalle_lugar' => route('promesa.detalle_lugar'),
            'obtener_lugar' => route('promesa.obtener_lugar'),
            'guardar_lugar' => route('promesa.guardar_lugar'),
            'actualizar_lugar' => route('promesa.actualizar_lugar'),
            'eliminar_lugar' => route('promesa.eliminar_lugar'),
            'listar_promesas' => route('promesa.listar_promesas'),
            'obtener_promesa' => route('promesa.obtener_promesa'),
            'guardar_promesa' => route('promesa.guardar_promesa'),
            'actualizar_promesa' => route('promesa.actualizar_promesa'),
            'eliminar_promesa' => route('promesa.eliminar_promesa'),
            'listar_seguimiento' => route('promesa.listar_seguimiento'),
            'guardar_actividad' => route('promesa.guardar_actividad'),
            'extender_actividad' => route('promesa.extender_actividad'),
            'finalizar_actividad' => route('promesa.finalizar_actividad'),
            'eliminar_actividad' => route('promesa.eliminar_actividad'),
            'finalizar_seguimiento' => route('promesa.finalizar_seguimiento'),
            'catalogos' => route('promesa.catalogos'),
            'personal' => route('promesa.personal'),
            'imprimir' => route('promesa.imprimir'),
            'inicio' => route('inicio'),
            'index' => route('promesa.index'),
            'form_reunion' => route('promesa.form_reunion'),
            'form_promesa' => route('promesa.form_promesa'),
            'form_seguimiento' => route('promesa.form_seguimiento'),
            'form_eliminar_reunion' => route('promesa.form_eliminar_reunion'),
            'form_eliminar_promesa' => route('promesa.form_eliminar_promesa'),
            'icono_nuevo' => asset('mod_promesa/images/nuevo.png'),
            'icono_editar' => asset('mod_promesa/images/editar.png'),
            'icono_cancel' => asset('mod_promesa/images/cancel.png'),
            'icono_reporte' => asset('mod_promesa/images/reporte.png'),
            'banner' => file_exists(public_path('mod_promesa/images/banner.jpeg'))
                ? asset('mod_promesa/images/banner.jpeg')
                : asset('mod_gp/omaped/images/banner.jpeg'),
            'logo' => file_exists(public_path('mod_promesa/images/logo_web.png'))
                ? asset('mod_promesa/images/logo_web.png')
                : asset('mod_gp/omaped/images/logo_web.png'),
        ];
    }
}
