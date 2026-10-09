<?php

namespace App\Http\Controllers\mod_ti\firma_email;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_ti\FirmaEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generador de firmas de correo institucional
 * (migrado desde sigtram_antiguo/Presentacion/mod_ti/firma_email/).
 */
class FirmaEmailController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly FirmaEmailService $service
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

        return view('mod_ti.firma_email.index', [
            'routes' => $this->apiRoutes(),
            'assetBase' => asset('mod_ti/firma_email'),
        ]);
    }

    public function buscarPorDni(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->buscarPorDni(trim((string) $request->input('dni', '')));

        if (empty($resultado['success'])) {
            return response()->json(['success' => false, 'error' => $resultado['error'] ?? 'Error']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Funcionario encontrado',
            'data' => $resultado['data'],
        ]);
    }

    public function generarIndividual(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $dni = preg_replace('/\D+/', '', (string) $request->input('dni', '')) ?: 'generada';
        $filename = $dni . '.png';

        $resultado = $this->service->generarIndividual([
            'titulo' => (string) $request->input('titulo', ''),
            'nombre' => (string) $request->input('nombre', ''),
            'cargo' => (string) $request->input('cargo', ''),
            'celular' => (string) $request->input('celular', ''),
            'anexo' => (string) $request->input('anexo', ''),
            'correo' => (string) $request->input('correo', ''),
        ], $filename);

        if (empty($resultado['success'])) {
            return response()->json(['success' => false, 'error' => $resultado['error'] ?? 'Error'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Imagen generada correctamente',
            'data' => $resultado['data'],
        ]);
    }

    public function generarMasivo(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        if (! $request->hasFile('csv')) {
            return response()->json(['success' => false, 'error' => 'No se pudo cargar el archivo CSV'], 422);
        }

        $file = $request->file('csv');
        if (! $file || ! $file->isValid()) {
            return response()->json(['success' => false, 'error' => 'No se pudo cargar el archivo CSV'], 422);
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            return response()->json(['success' => false, 'error' => 'Solo se permiten archivos CSV'], 422);
        }

        $resultado = $this->service->generarMasivo($file->getRealPath() ?: $file->getPathname());
        if (empty($resultado['success'])) {
            return response()->json(['success' => false, 'error' => $resultado['error'] ?? 'Error'], 422);
        }

        $zipFile = (string) ($resultado['data']['zip_file'] ?? '');

        return response()->json([
            'success' => true,
            'message' => 'Proceso completado. Se generaron ' . count($resultado['data']['results'] ?? []) . ' firmas.',
            'data' => [
                'zip_url' => route('firma_email.descargar_temp', ['f' => $zipFile]),
                'results' => $resultado['data']['results'] ?? [],
            ],
        ]);
    }

    public function descargarPlantilla(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            echo "sep=,\r\n";
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['DNI', 'Grado', 'Nombre Completo', 'Cargo', 'Celular', 'Anexo', 'Correo']);
            fputcsv($out, ['12345678', 'Ing.', 'JOSE LUIS SALAZAR MEZA', 'ESPECIALISTA TI', '999888777', '1234', 'jsalazar@munipuentepiedra.gob.pe']);
            fclose($out);
        }, 'plantilla_firmas.csv', [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    public function descargarTemp(Request $request): BinaryFileResponse|Response
    {
        if (! $this->checkAccess($request)) {
            abort(403, 'Acceso denegado.');
        }

        $archivo = (string) $request->query('f', '');
        $path = $this->service->rutaTempSegura($archivo);
        if ($path === null) {
            abort(404, 'Archivo no encontrado o ya fue descargado.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $headers = [
            'Cache-Control' => 'private, max-age=3600',
        ];

        if ($ext === 'png') {
            $headers['Content-Type'] = 'image/png';
        } else {
            $headers['Content-Type'] = 'application/zip';
            $headers['Content-Disposition'] = 'attachment; filename="' . basename($path) . '"';
        }

        return response()->download($path, basename($path), $headers)->deleteFileAfterSend(true);
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
        return $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/firma_email', $cargoId)
            || $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_ti/firma_email/index', $cargoId);
    }

    private function apiRoutes(): array
    {
        return [
            'index' => route('firma_email.index'),
            'buscar_dni' => route('firma_email.buscar_dni'),
            'generar_individual' => route('firma_email.generar_individual'),
            'generar_masivo' => route('firma_email.generar_masivo'),
            'plantilla' => route('firma_email.plantilla'),
            'descargar_temp' => route('firma_email.descargar_temp'),
        ];
    }
}
