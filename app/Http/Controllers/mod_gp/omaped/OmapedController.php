<?php

namespace App\Http\Controllers\mod_gp\omaped;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\mod_gp\omaped\OmapedBridge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OmapedController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
        if ($cargoId <= 0) {
            return redirect()->route('seleccionar.cargo');
        }
        $this->assertPermiso($user, $cargoId);

        $omaped = OmapedBridge::make();
        $puedeEliminar = $omaped ? (bool) $omaped->puedeEliminar() : false;

        return view('mod_gp.omaped.index', [
            'routes' => $this->apiRoutes(),
            'puedeEliminar' => $puedeEliminar,
            'usNomArea' => (string) $request->session()->get('sigtram_area_nombre', ''),
            'usNomCargo' => (string) $request->session()->get('sigtram_cargo_nombre', ''),
            'usNombre' => method_exists($user, 'nombreMostrar') ? $user->nombreMostrar() : '',
        ]);
    }

    public function listar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }
        $exportar = $request->query('exportar') === '1';
        $resultado = $omaped->listar(
            trim((string) $request->query('tipoFiltro', '')),
            trim((string) $request->query('q', '')),
            trim((string) $request->query('zona', '')),
            trim((string) $request->query('fechaInicio', '')),
            trim((string) $request->query('fechaFin', '')),
            (int) $request->query('pagina', 1),
            (int) $request->query('porPagina', 5),
            $exportar
        );

        return response()->json($resultado, !empty($resultado['success']) ? 200 : 500);
    }

    public function detalle(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }
        $id = (int) $request->query('id_paciente', 0);
        $resultado = $omaped->detalle($id);
        if (!empty($resultado['success']) && !empty($resultado['data']['urlQrPng'])) {
            $token = pathinfo((string) $resultado['data']['urlQrPng'], PATHINFO_FILENAME);
            $resultado['data']['urlQrPng'] = url('/mod_gp/omaped/qr-imagen/' . rawurlencode($token) . '.jpg');
        }

        return response()->json($resultado, !empty($resultado['success']) ? 200 : 500);
    }

    public function catalogos(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }

        return response()->json($omaped->catalogos());
    }

    public function buscarDocumento(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }
        $dni = trim((string) $request->query('dni', ''));

        return response()->json($omaped->buscarPersonaPorDocumento($dni));
    }

    public function guardar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }
        $input = $this->parseDataPayload($request);
        if (!$input) {
            return response()->json(['success' => false, 'error' => 'Datos no validos.'], 400);
        }
        $this->mergeUploadedFiles($request, $input);
        $resultado = $omaped->guardar($input);

        return response()->json($resultado, !empty($resultado['success']) ? 200 : 500);
    }

    public function actualizar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }
        $input = $this->parseDataPayload($request);
        if (!$input || empty($input['idPaciente'])) {
            return response()->json(['success' => false, 'error' => 'Datos no validos.'], 400);
        }
        $this->mergeUploadedFiles($request, $input);
        $resultado = $omaped->actualizar((int) $input['idPaciente'], $input);

        return response()->json($resultado, !empty($resultado['success']) ? 200 : 500);
    }

    public function eliminar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }
        $id = (int) $request->input('idPaciente', 0);
        $resultado = $omaped->eliminar($id);

        return response()->json($resultado, !empty($resultado['success']) ? 200 : 500);
    }

    public function historicoGuardar(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }
        $input = $this->parseDataPayload($request);
        if (!$input || empty($input['idPaciente'])) {
            return response()->json(['success' => false, 'error' => 'Datos no validos.'], 400);
        }
        if ($request->hasFile('evidenciaAtencion')) {
            $file = $request->file('evidenciaAtencion');
            $_FILES['evidenciaAtencion'] = [
                'name' => $file->getClientOriginalName(),
                'type' => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error' => UPLOAD_ERR_OK,
                'size' => $file->getSize(),
            ];
        }
        $idAtencion = (int) ($input['idAtencion'] ?? 0);
        $resultado = $omaped->guardarOActualizarHistorico((int) $input['idPaciente'], $input, $idAtencion);

        return response()->json($resultado, !empty($resultado['success']) ? 200 : 500);
    }

    public function togglePariente(Request $request): JsonResponse
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }
        $resultado = $omaped->toggleParienteEstado(
            (int) $request->input('idPaciente', 0),
            (int) $request->input('cdgoUsrios', 0),
            $request->input('estado')
        );

        return response()->json($resultado, !empty($resultado['success']) ? 200 : 500);
    }

    public function qrPng(Request $request): BinaryFileResponse|JsonResponse|Response
    {
        if (!$this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        $omaped = OmapedBridge::make();
        if (!$omaped) {
            return response()->json(['success' => false, 'error' => 'No se pudo conectar a la base de datos.'], 500);
        }
        $resultado = $omaped->obtenerQrPngPorPaciente((int) $request->query('id_paciente', 0));
        if (empty($resultado['success']) || empty($resultado['data']['rutaFisica'])) {
            return response()->json($resultado, 404);
        }
        $ruta = $resultado['data']['rutaFisica'];
        if (!is_file($ruta)) {
            return response()->json(['success' => false, 'error' => 'Archivo QR no encontrado.'], 404);
        }
        $nombre = $resultado['data']['nombreDescarga'] ?? ('qr_omaped.jpg');

        return response()->download($ruta, $nombre, ['Content-Type' => 'image/jpeg']);
    }

    public function qrImagen(string $token): BinaryFileResponse|Response
    {
        $token = basename($token, '.jpg');
        $token = preg_replace('/[^a-zA-Z0-9_\-]/', '', $token) ?? '';
        $ruta = OmapedBridge::tempQrBase() . '/' . $token . '.jpg';
        if (!is_file($ruta)) {
            abort(404);
        }

        return response()->file($ruta, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=60']);
    }

    public function archivoPrivado(Request $request): BinaryFileResponse|JsonResponse|Response
    {
        $tokenA = trim((string) $request->query('a', ''));
        $autenticado = Auth::check();
        if (!$autenticado && $tokenA === '') {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }
        if ($autenticado && !$this->checkAccess($request) && $tokenA === '') {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $tipo = trim((string) $request->query('tipo', ''));
        $archivo = basename(trim((string) $request->query('archivo', '')));
        if ($archivo === '' || str_contains($archivo, '..')) {
            return response()->json(['success' => false, 'error' => 'Archivo inválido.'], 400);
        }

        $map = [
            'foto' => 'foto',
            'carnet' => 'carnt_conds',
            'certificado' => 'cert_disc',
            'evidencia' => 'evidencia',
        ];
        if (!isset($map[$tipo])) {
            return response()->json(['success' => false, 'error' => 'Tipo inválido.'], 400);
        }

        // Acceso público con token solo para foto
        if (!$autenticado && $tokenA !== '') {
            if ($tipo !== 'foto') {
                return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
            }
            OmapedBridge::boot();
            $omaped = OmapedBridge::make();
            if (!$omaped) {
                return response()->json(['success' => false, 'error' => 'Error de conexión.'], 500);
            }
            $acceso = $omaped->validarTokenAcceso($tokenA, $request->ip());
            if (!$acceso) {
                return response()->json(['success' => false, 'error' => 'Token inválido o expirado.'], 403);
            }
            $pub = $omaped->obtenerDatosPublicosPaciente((int) $acceso['id_pacnt']);
            $foto = $pub['foto'] ?? ($pub['data']['foto'] ?? null);
            // obtenerDatosPublicosPaciente may return ok() wrap - check both
            if (is_array($pub) && isset($pub['success'], $pub['data'])) {
                $foto = $pub['data']['foto'] ?? null;
            }
            if (!$foto || basename((string) $foto) !== $archivo) {
                return response()->json(['success' => false, 'error' => 'Archivo no autorizado.'], 403);
            }
        }

        $ruta = OmapedBridge::adjuntoBase() . $map[$tipo] . '/' . $archivo;
        if (!is_file($ruta)) {
            return response()->json(['success' => false, 'error' => 'Archivo no encontrado.'], 404);
        }
        $mime = mime_content_type($ruta) ?: 'application/octet-stream';

        return response()->file($ruta, ['Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="' . $archivo . '"']);
    }

    // ── Público QR ────────────────────────────────────────────────────────

    public function entrada(Request $request): View|RedirectResponse
    {
        OmapedBridge::boot();
        $token = trim((string) $request->query('t', ''));
        if ($request->isMethod('post')) {
            $token = trim((string) $request->input('t', $token));
            $dni = preg_replace('/\D/', '', (string) $request->input('dni', '')) ?? '';
            $omaped = OmapedBridge::make();
            if (!$omaped || $token === '' || strlen($dni) !== 8) {
                return back()->withErrors(['dni' => 'Datos inválidos.'])->withInput();
            }
            $pac = $omaped->obtenerPacientePorTokenQRPublico($token);
            if (!$pac || (string) ($pac['dni'] ?? '') !== $dni) {
                $omaped->registrarIntentoFallidoDni($token, $request->ip());

                return back()->withErrors(['dni' => 'El DNI no coincide con el QR.'])->withInput();
            }
            $acceso = $omaped->crearTokenAcceso((int) $pac['id_qr'], $request->ip(), (string) $request->userAgent(), 5);
            if (!$acceso) {
                return back()->withErrors(['dni' => 'No se pudo crear acceso temporal.'])->withInput();
            }

            return redirect()->route('omaped.ver_datos', ['a' => $acceso]);
        }

        return view('mod_gp.omaped.entrada', [
            'token' => $token,
            'routes' => [
                'entrada' => route('omaped.entrada'),
                'fondo' => asset('mod_gp/omaped/images/fondo_pantalla.jpg'),
                'logo' => asset('mod_gp/omaped/images/logo_web.png'),
            ],
        ]);
    }

    public function verDatos(Request $request): View|RedirectResponse
    {
        OmapedBridge::boot();
        $tokenA = trim((string) $request->query('a', ''));
        $omaped = OmapedBridge::make();
        if (!$omaped || $tokenA === '') {
            abort(403, 'Acceso no válido.');
        }
        $acceso = $omaped->validarTokenAcceso($tokenA, $request->ip());
        if (!$acceso) {
            abort(403, 'El enlace expiró o no es válido.');
        }
        $datos = $omaped->obtenerDatosPublicosPaciente((int) $acceso['id_pacnt']);
        $payload = is_array($datos) && isset($datos['data']) ? $datos['data'] : $datos;
        $foto = $payload['foto'] ?? '';
        $fotoUrl = $foto !== ''
            ? url('/mod_gp/omaped/archivo-privado?tipo=foto&archivo=' . rawurlencode(basename((string) $foto)) . '&a=' . rawurlencode($tokenA))
            : '';

        return view('mod_gp.omaped.ver_datos', [
            'datos' => $payload,
            'fotoUrl' => $fotoUrl,
            'routes' => [
                'fondo' => asset('mod_gp/omaped/images/fondo_pantalla.jpg'),
                'logo' => asset('mod_gp/omaped/images/logo_web.png'),
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function parseDataPayload(Request $request): ?array
    {
        if ($request->filled('data')) {
            $decoded = json_decode((string) $request->input('data'), true);

            return is_array($decoded) ? $decoded : null;
        }
        $all = $request->all();
        unset($all['_token']);

        return $all !== [] ? $all : null;
    }

    private function mergeUploadedFiles(Request $request, array &$input): void
    {
        foreach (['fotoPaciente', 'archivoCarnetConadis', 'archivoCertificadoDiscapacidad'] as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $_FILES[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getMimeType(),
                    'tmp_name' => $file->getRealPath(),
                    'error' => UPLOAD_ERR_OK,
                    'size' => $file->getSize(),
                ];
            }
        }
    }

    private function checkAccess(Request $request): bool
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return false;
        }
        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

        return $cargoId > 0 && $this->tienePermiso($user, $cargoId);
    }

    private function assertPermiso(User $user, int $cargoId): void
    {
        if (!$this->tienePermiso($user, $cargoId)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }
    }

    private function tienePermiso(User $user, int $cargoId): bool
    {
        return $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_gp/omaped', $cargoId)
            || $this->securityCore->tienePermiso((int) $user->cdgo_lgin, '/mod_gp/omaped/index', $cargoId);
    }

    private function apiRoutes(): array
    {
        return [
            'listar' => route('omaped.listar'),
            'detalle' => route('omaped.detalle'),
            'catalogos' => route('omaped.catalogos'),
            'buscar_documento' => route('omaped.buscar_documento'),
            'guardar' => route('omaped.guardar'),
            'actualizar' => route('omaped.actualizar'),
            'eliminar' => route('omaped.eliminar'),
            'historico_guardar' => route('omaped.historico_guardar'),
            'toggle_pariente' => route('omaped.toggle_pariente'),
            'qr_png' => route('omaped.qr_png'),
            'archivo_privado' => route('omaped.archivo_privado'),
            'inicio' => route('inicio'),
            'index' => route('omaped.index'),
            'banner' => asset('mod_gp/omaped/images/banner.jpeg'),
            'logo' => asset('mod_gp/omaped/images/logo_web.png'),
        ];
    }
}
