<?php

namespace App\Http\Controllers\mod_ti\reporte_publicidad_2026;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_ti\ReportePublicidad2026Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reportes de publicidad estatal 2026
 * (migrado desde sigtram_antiguo/Presentacion/mod_ti/reporte_publicidad_2026/).
 */
class ReportePublicidad2026Controller extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly ReportePublicidad2026Service $service
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

        return view('mod_ti.reporte_publicidad_2026.index', [
            'routes' => $this->apiRoutes(),
        ]);
    }

    public function formulario(): View
    {
        return view('mod_ti.reporte_publicidad_2026.formulario');
    }

    public function listar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        return response()->json(
            $this->service->listar((int) $request->query('pagina', 1))
        );
    }

    public function obtener(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        return response()->json(
            $this->service->obtener((int) $request->query('reporte_id', 0))
        );
    }

    public function guardar(Request $request): JsonResponse
    {
        $user = $this->usuarioAutorizado($request);
        if ($user === null) {
            return response()->json(['status' => 'error', 'message' => 'Acceso denegado.'], 403);
        }

        $detallesRaw = $request->input('detalles', '[]');
        $detalles = is_string($detallesRaw) ? (json_decode($detallesRaw, true) ?: []) : (is_array($detallesRaw) ? $detallesRaw : []);

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
            'ubicacion' => (string) $request->input('ubicacion', ''),
            'fech_ini' => (string) $request->input('fech_ini', ''),
            'fech_fin' => (string) $request->input('fech_fin', ''),
            'enlace_link' => (string) $request->input('enlace_link', ''),
            'fundamento' => (string) $request->input('fundamento', ''),
            'detalles' => $detalles,
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

    public function pdf(Request $request): Response
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
        $detalles = $data['detalles'];
        $imagenes = $data['imagenes'];

        $fechaIni = ! empty($reporte['fech_ini']) ? strtotime((string) $reporte['fech_ini']) : false;
        $fechaFin = ! empty($reporte['fech_fin']) ? strtotime((string) $reporte['fech_fin']) : false;

        $periodo = [
            'dia_ini' => $fechaIni ? date('d', $fechaIni) : '',
            'mes_ini' => $fechaIni ? date('m', $fechaIni) : '',
            'anio_ini' => $fechaIni ? date('Y', $fechaIni) : '',
            'dia_fin' => $fechaFin ? date('d', $fechaFin) : '',
            'mes_fin' => $fechaFin ? date('m', $fechaFin) : '',
            'anio_fin' => $fechaFin ? date('Y', $fechaFin) : '',
            'hasta_actualidad' => ($fechaFin && $fechaIni && $fechaFin < $fechaIni),
        ];

        $cantidadTotal = 0.0;
        foreach ($detalles as $d) {
            $cantidadTotal += (float) $d['cantidad'];
        }

        $nombreArchivo = 'DOCUMENTO DEL ' . date('d-m-Y') . ' (' . $contador . ').pdf';

        $pdf = Pdf::loadView('mod_ti.reporte_publicidad_2026.pdf', [
            'reporte' => $reporte,
            'detalles' => $detalles,
            'imagenes' => $imagenes,
            'periodo' => $periodo,
            'cantidadTotal' => $cantidadTotal,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($nombreArchivo);
    }

    public function imagen(Request $request): BinaryFileResponse
    {
        if (! $this->checkAccess($request)) {
            abort(403, 'Acceso denegado.');
        }

        $path = $this->service->rutaAdjuntoSegura((string) $request->query('f', ''));
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
        return $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/reporte_publicidad_2026', $cargoId)
            || $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/reporte_publicidad_2026/index', $cargoId);
    }

    private function apiRoutes(): array
    {
        return [
            'index' => route('reporte_publicidad_2026.index'),
            'formulario' => route('reporte_publicidad_2026.formulario'),
            'listar' => route('reporte_publicidad_2026.listar'),
            'obtener' => route('reporte_publicidad_2026.obtener'),
            'guardar' => route('reporte_publicidad_2026.guardar'),
            'eliminar' => route('reporte_publicidad_2026.eliminar'),
            'pdf' => route('reporte_publicidad_2026.pdf'),
            'imagen' => route('reporte_publicidad_2026.imagen'),
        ];
    }
}
