<?php

namespace App\Http\Controllers\mod_a\sgl;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Core\DocumentGeneratorService;
use App\Services\mod_a\sgl\VerRequerimientoLogisticaService;
use App\Services\mod_gene\mod_tram_inter\IngresarTramiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Pago a proveedores — Logística
 * (migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/pago_proveedores/ver_requerimiento_logistica.php).
 */
class VerRequerimientoLogisticaController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly VerRequerimientoLogisticaService $service,
        private readonly IngresarTramiteService $ingresarTramiteService
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

        $vista = 'mod_a.sgl.pago_proveedores.ver_requerimiento_logistica';
        if (is_string($pathVista) && $pathVista !== '' && $this->existeVistaBlade($pathVista)) {
            $vista = $pathVista;
        }

        $tituloOpcion = trim((string) ($opcionMenu->dc_rle ?? 'Pago a proveedores - Logística'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Pago a proveedores - Logística';
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

    public function itemDetalle(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->itemDetalle((int) $request->input('item_id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 404);
    }

    public function semilla(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->semilla((int) $request->input('tipo_id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function publicar(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->publicar((int) $request->input('id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function actualizarCampo(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->actualizarCampo(
            (int) $request->input('item_id', 0),
            (string) $request->input('campo', ''),
            (string) $request->input('valor', '')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function actualizarMontoMemo(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->actualizarMontoMemo(
            (int) $request->input('memo_id', 0),
            (float) $request->input('monto', 0)
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function guardarHabilitacion(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->guardarHabilitacion(
            (int) $request->input('item_id', 0),
            $request->only(['monto', 'ccp', 'esp_gasto']),
            $request->file('ccp_file')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function guardarOs(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->guardarOs(
            (int) $request->input('item_id', 0),
            $request->only(['usuario_id', 'nombre', 'os', 'fecha_os', 'ruc', 'cci', 'monto', 'siaf', 'rubro']),
            $request->file('os_file')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function anularMigra(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->anularMigra((int) $request->input('item_id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function buscarProveedor(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->buscarProveedor((string) $request->input('nombre', ''));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function guardarProveedor(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->guardarProveedor($request->all());

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function subirZip(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->subirZip(
            (int) $request->input('id', 0),
            (int) $request->input('tipo', 0),
            $request->file('zip')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function enviarFinanzas(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->enviarFinanzas(
            (int) $request->input('id', 0),
            (int) $request->input('tipo_id', 0),
            (string) $request->input('semilla', ''),
            $request->file('adjunto')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function enviarInforme(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->enviarInforme(
            (int) $request->input('id', 0),
            (int) $request->input('tipo_id', 0),
            (string) $request->input('semilla', ''),
            $request->file('adjunto')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function enviarPresupuesto(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->enviarPresupuesto((int) $request->input('id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function devolver(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->devolver(
            (int) $request->input('id', 0),
            (string) $request->input('motivo', ''),
            (string) $request->input('etapa', '')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function verBorrador(Request $request): BinaryFileResponse|JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $tipoId = (int) $request->input('tipo_id', 0);
        $semilla = trim((string) $request->input('semilla', ''));
        $asunto = trim((string) $request->input('asunto', 'TRAMITE DE PAGO'));
        $destArea = (int) $request->input('dest_area', 0);
        $areaId = $this->service->areaUsuario();
        if ($tipoId <= 0 || $areaId <= 0) {
            return response()->json(['success' => false, 'message' => 'Seleccione el documento.'], 422);
        }

        try {
            $datosParaWord = $this->ingresarTramiteService->prepararDatosBorrador([
                'sel_cdgo_tpo_trmte' => $tipoId,
                'txt_nu_smlla_intrno' => $semilla !== '' ? $semilla : '1-' . date('Y'),
                'sel_areas' => [$destArea > 0 ? $destArea : $areaId],
                'sel_crgos' => null,
                'txt_fe_rcbdo' => date('Y-m-d'),
                'txt_de_obser' => $asunto,
            ]);

            $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
            $tpo = DB::table($schema . 'a_tpo_trmtes')->where('cdgo_tpo_trmte', $datosParaWord['cdgo_tpo_trmte'])->first();
            $plantillaNom = trim((string) $request->input('plantilla', ''));
            if ($plantillaNom === '') {
                $plantillaNom = (string) ($tpo->no_plntlla ?? '');
            }
            if ($plantillaNom === '') {
                return response()->json(['success' => false, 'message' => 'Este tipo de documento no tiene plantilla configurada.'], 400);
            }

            $templatePath = $this->resolverRutaPlantilla($plantillaNom, $areaId);
            if ($templatePath === null) {
                return response()->json(['success' => false, 'message' => 'La plantilla solicitada no existe: ' . $plantillaNom], 404);
            }

            $fileName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', (string) ($datosParaWord['nu_tram_todo'] ?? 'borrador')) . '.docx';
            $filePath = app(DocumentGeneratorService::class)->generarBorradorInterno($datosParaWord, $templatePath, $fileName);

            return response()->download($filePath, $fileName, [
                'Access-Control-Expose-Headers' => 'X-Suggested-Filename, Content-Disposition',
                'X-Suggested-Filename' => rawurlencode($fileName),
            ])->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar borrador: ' . $e->getMessage()], 500);
        }
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

    private function resolverRutaPlantilla(string $noPlntlla, int $areaId): ?string
    {
        $basename = basename(str_replace('\\', '/', $noPlntlla));
        if ($basename === '' || str_contains($basename, '..')) {
            return null;
        }

        $relBase = 'plantillas/mod_gene/mod_tram_inter';
        $candidatosRel = [];
        if ($areaId > 0) {
            $candidatosRel[] = "{$relBase}/{$areaId}_{$basename}";
        }
        $candidatosRel[] = "{$relBase}/{$basename}";
        $candidatosRel[] = 'plantillas/mod_a/sgl/pago_proveedores/' . $basename;

        foreach ($candidatosRel as $rel) {
            if (is_file(storage_path('app/' . $rel))) {
                return $rel;
            }
        }

        $legacyRootRaw = (string) config('filesystems.legacy_sigtram_root', base_path('../sigtram_antiguo'));
        $legacyRoot = rtrim($legacyRootRaw, '/\\');
        if ($legacyRoot !== '' && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $legacyRoot) && ! str_starts_with($legacyRoot, '/')) {
            $legacyRoot = rtrim(base_path($legacyRoot), '/\\');
        }

        $legacyFiles = [];
        foreach ([
            $legacyRoot . '/Presentacion/mod_gene/mod_tram_inter/plantillas/',
            $legacyRoot . '/Presentacion/mod_a/sgl/pago_proveedores/',
        ] as $legacyDir) {
            if ($areaId > 0) {
                $legacyFiles[] = $legacyDir . $areaId . '_' . $basename;
            }
            $legacyFiles[] = $legacyDir . $basename;
        }

        foreach ($legacyFiles as $src) {
            if (! is_file($src)) {
                continue;
            }
            $destRel = $relBase . '/' . basename($src);
            $destAbs = storage_path('app/' . $destRel);
            if (! is_dir(dirname($destAbs))) {
                @mkdir(dirname($destAbs), 0775, true);
            }
            if (! is_file($destAbs)) {
                @copy($src, $destAbs);
            }
            if (is_file($destAbs)) {
                return $destRel;
            }
        }

        return null;
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
        foreach ([
            '/mod_a/sgl/pago_proveedores/ver_requerimiento_logistica',
            '/mod_a/sgl/pago_proveedores/ver_requerimiento_logistica.php',
        ] as $ruta) {
            if ($this->securityCore->tienePermiso((int) $user->cdgo_lgin, $ruta, $cargoId)) {
                return true;
            }
        }

        return false;
    }

    private function apiRoutes(): array
    {
        return [
            'listar' => route('sgl.pago_proveedores.logistica.listar', [], false),
            'detalle' => route('sgl.pago_proveedores.logistica.detalle', [], false),
            'itemDetalle' => route('sgl.pago_proveedores.logistica.item_detalle', [], false),
            'semilla' => route('sgl.pago_proveedores.logistica.semilla', [], false),
            'publicar' => route('sgl.pago_proveedores.logistica.publicar', [], false),
            'actualizarCampo' => route('sgl.pago_proveedores.logistica.actualizar_campo', [], false),
            'actualizarMontoMemo' => route('sgl.pago_proveedores.logistica.actualizar_monto', [], false),
            'guardarHabilitacion' => route('sgl.pago_proveedores.logistica.guardar_habilitacion', [], false),
            'guardarOs' => route('sgl.pago_proveedores.logistica.guardar_os', [], false),
            'anularMigra' => route('sgl.pago_proveedores.logistica.anular_migra', [], false),
            'buscarProveedor' => route('sgl.pago_proveedores.logistica.buscar_proveedor', [], false),
            'guardarProveedor' => route('sgl.pago_proveedores.logistica.guardar_proveedor', [], false),
            'subirZip' => route('sgl.pago_proveedores.logistica.subir_zip', [], false),
            'enviarFinanzas' => route('sgl.pago_proveedores.logistica.enviar_finanzas', [], false),
            'enviarInforme' => route('sgl.pago_proveedores.logistica.enviar_informe', [], false),
            'enviarPresupuesto' => route('sgl.pago_proveedores.logistica.enviar_presupuesto', [], false),
            'devolver' => route('sgl.pago_proveedores.logistica.devolver', [], false),
            'verBorrador' => route('sgl.pago_proveedores.logistica.ver_borrador', [], false),
            'archivo' => route('sgl.pago_proveedores.logistica.archivo', [], false),
            'seguimiento' => route('sgl.buscar_requerimiento_pago.seguimiento_tramite', [], false),
        ];
    }
}
