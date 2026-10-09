<?php

namespace App\Http\Controllers\mod_ti\reporte_publicidad;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_ti\ReportePublicidadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reportes de publicidad estatal
 * (migrado desde sigtram_antiguo/Presentacion/mod_ti/reporte_publicidad/).
 */
class ReportePublicidadController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly ReportePublicidadService $service
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

        return view('mod_ti.reporte_publicidad.index', [
            'routes' => $this->apiRoutes(),
        ]);
    }

    public function listar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        return response()->json($this->service->listar());
    }

    public function cuadros(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        return response()->json(
            $this->service->obtenerCuadros((int) $request->query('reporte_id', 0))
        );
    }

    public function editar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        return response()->json(
            $this->service->obtenerParaEditar((int) $request->query('reporte_id', 0))
        );
    }

    public function guardar(Request $request): JsonResponse
    {
        $user = $this->usuarioAutorizado($request);
        if ($user === null) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        $imagenes = [];
        if ($request->hasFile('imagenes')) {
            $files = $request->file('imagenes');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file) {
                        $imagenes[] = $file;
                    }
                }
            } elseif ($files) {
                $imagenes[] = $files;
            }
        }

        $resultado = $this->service->guardar([
            'modalMode' => (string) $request->input('modalMode', ''),
            'cdgo_reporte' => (int) $request->input('cdgo_reporte', 0),
            'mdio_empleado' => (string) $request->input('mdio_empleado', ''),
            'ubicacion' => (string) $request->input('ubicacion', ''),
            'fech_ini' => (string) $request->input('fech_ini', ''),
            'fech_fin' => (string) $request->input('fech_fin', ''),
            'enlace' => (string) $request->input('enlace', ''),
            'fundamento' => (string) $request->input('fundamento', ''),
            'cuadros' => $request->input('cuadros', []),
        ], (int) $user->cdgo_usrios, $imagenes);

        return response()->json($resultado);
    }

    public function eliminar(Request $request): JsonResponse
    {
        $user = $this->usuarioAutorizado($request);
        if ($user === null) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        return response()->json(
            $this->service->eliminar((int) $request->input('reporte_id', 0), (int) $user->cdgo_usrios)
        );
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        if (! $this->checkAccess($request)) {
            abort(403, 'Acceso denegado.');
        }

        $reporteId = (int) $request->query('reporte_id', 0);
        $contador = (int) $request->query('contador', $reporteId);

        $resultado = $this->service->obtenerParaPdf($reporteId);
        if (($resultado['status'] ?? '') !== 'success') {
            abort(404, $resultado['message'] ?? 'Reporte no encontrado');
        }

        $data = $resultado['data'];
        $reporte = $data['reporte'];
        $cuadros = $data['cuadros'];
        $imagenes = $data['imagenes'];

        $fechaIni = strtotime((string) $reporte['fech_ini']);
        $fechaFin = strtotime((string) $reporte['fech_fin']);
        $textoFechaFin = ($fechaFin < $fechaIni)
            ? 'Hasta la actualidad.'
            : date('d/m/Y', $fechaFin);

        $canTotal = 0.0;
        $invTotal = 0.0;
        foreach ($cuadros as $c) {
            $canTotal += (float) $c['cantidad'];
            $invTotal += (float) $c['inversion'];
        }

        $pdf = Pdf::loadView('mod_ti.reporte_publicidad.pdf', [
            'reporte' => $reporte,
            'cuadros' => $cuadros,
            'imagenes' => $imagenes,
            'textoFechaFin' => $textoFechaFin,
            'fechaIniFmt' => date('d/m/Y', $fechaIni),
            'canTotal' => $canTotal,
            'invTotal' => $invTotal,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Reporte_' . $contador . '.pdf');
    }

    public function imagen(Request $request): BinaryFileResponse
    {
        if (! $this->checkAccess($request)) {
            abort(403, 'Acceso denegado.');
        }

        $archivo = (string) $request->query('f', '');
        $path = $this->service->rutaAdjuntoSegura($archivo);
        if ($path === null) {
            abort(404, 'Imagen no encontrada.');
        }

        return response()->file($path);
    }

    private function usuarioAutorizado(Request $request): ?User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return null;
        }
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0 || ! $this->tienePermiso($user, $cargoId)) {
            return null;
        }

        return $user;
    }

    private function checkAccess(Request $request): bool
    {
        return $this->usuarioAutorizado($request) !== null;
    }

    private function assertPermiso(User $user, int $cargoId): void
    {
        if (! $this->tienePermiso($user, $cargoId)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function tienePermiso(User $user, int $cargoId): bool
    {
        return $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/reporte_publicidad', $cargoId)
            || $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/reporte_publicidad/index', $cargoId);
    }

    private function apiRoutes(): array
    {
        return [
            'index' => route('reporte_publicidad.index'),
            'listar' => route('reporte_publicidad.listar'),
            'cuadros' => route('reporte_publicidad.cuadros'),
            'editar' => route('reporte_publicidad.editar'),
            'guardar' => route('reporte_publicidad.guardar'),
            'eliminar' => route('reporte_publicidad.eliminar'),
            'pdf' => route('reporte_publicidad.pdf'),
            'imagen' => route('reporte_publicidad.imagen'),
        ];
    }
}
