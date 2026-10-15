<?php

namespace App\Http\Controllers\mod_a\sgl;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Core\DocumentGeneratorService;
use App\Services\mod_a\sgl\PagoProveedoresIndexService;
use App\Services\mod_a\sgl\VerRequerimientoLocadorService;
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
 * Pago a proveedores — Locador
 * (migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/pago_proveedores/ver_requerimiento_locador.php).
 */
class VerRequerimientoLocadorController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly VerRequerimientoLocadorService $service,
        private readonly PagoProveedoresIndexService $pagoService,
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

        $vista = 'mod_a.sgl.pago_proveedores.ver_requerimiento_locador';
        if (is_string($pathVista) && $pathVista !== '' && $this->existeVistaBlade($pathVista)) {
            $vista = $pathVista;
        }

        $tituloOpcion = trim((string) ($opcionMenu->dc_rle ?? 'Pago a proveedores - Locador'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Pago a proveedores - Locador';
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

        $resultado = $this->pagoService->itemDetalle((int) $request->input('item_id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 404);
    }

    public function guardarTdrSiga(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->pagoService->guardarTdrSiga(
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

        $resultado = $this->pagoService->guardarAnexos(
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

        $resultado = $this->pagoService->guardarConformidad(
            (int) $request->input('item_id', 0),
            $pagos,
            $request->file('adicional')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function bajaItem(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->pagoService->bajaItem((int) $request->input('item_id', 0));

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function guardarPlantilla(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->guardarPlantilla(
            (int) $request->input('id', 0),
            $request->file('adjunto')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function verPlantilla(Request $request): BinaryFileResponse|JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $tipoId = (int) $request->input('tipo_id', 0);
        $semilla = trim((string) $request->input('semilla', '1-' . date('Y')));
        $asunto = trim((string) $request->input('asunto', ''));
        $areaId = $this->service->areaUsuario();

        if ($tipoId !== 63) {
            return response()->json(['success' => false, 'message' => 'Tipo de documento no permitido.'], 422);
        }
        if ($areaId <= 0) {
            return response()->json(['success' => false, 'message' => 'No se pudo determinar el área del cargo.'], 422);
        }

        try {
            $datosParaWord = $this->ingresarTramiteService->prepararDatosBorrador([
                'sel_cdgo_tpo_trmte' => $tipoId,
                'txt_nu_smlla_intrno' => $semilla !== '' ? $semilla : '1-' . date('Y'),
                'sel_areas' => [$areaId],
                'sel_crgos' => null,
                'txt_fe_rcbdo' => date('Y-m-d'),
                'txt_de_obser' => $asunto,
            ]);

            $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
            $tpoTrmteInfo = DB::table($schema . 'a_tpo_trmtes')
                ->where('cdgo_tpo_trmte', $datosParaWord['cdgo_tpo_trmte'])
                ->first();

            if (! $tpoTrmteInfo || empty($tpoTrmteInfo->no_plntlla)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este tipo de documento no tiene plantilla configurada (no_plntlla).',
                ], 400);
            }

            $templatePath = $this->resolverRutaPlantilla((string) $tpoTrmteInfo->no_plntlla, $areaId);
            if ($templatePath === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'La plantilla solicitada no existe: ' . $tpoTrmteInfo->no_plntlla,
                ], 404);
            }

            $fileName = (string) ($datosParaWord['nu_tram_todo'] ?? 'plantilla') . '.docx';
            $fileName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $fileName);

            $generator = app(DocumentGeneratorService::class);
            $filePath = $generator->generarBorradorInterno($datosParaWord, $templatePath, $fileName);

            return response()->download($filePath, $fileName, [
                'Access-Control-Expose-Headers' => 'X-Suggested-Filename, Content-Disposition',
                'X-Suggested-Filename' => rawurlencode($fileName),
            ])->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar plantilla: ' . $e->getMessage(),
            ], 500);
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

        $ruta = $this->pagoService->resolverRutaArchivo($archivo);
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
        $legacyDir = $legacyRoot . '/Presentacion/mod_gene/mod_tram_inter/plantillas';

        $legacyFiles = [];
        if ($areaId > 0) {
            $legacyFiles[] = $legacyDir . '/' . $areaId . '_' . $basename;
        }
        $legacyFiles[] = $legacyDir . '/' . $basename;

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
        $rutas = [
            '/mod_a/sgl/pago_proveedores/ver_requerimiento_locador',
            '/mod_a/sgl/pago_proveedores/ver_requerimiento_locador.php',
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
            'listar' => route('sgl.pago_proveedores.locador.listar', [], false),
            'detalle' => route('sgl.pago_proveedores.locador.detalle', [], false),
            'itemDetalle' => route('sgl.pago_proveedores.locador.item_detalle', [], false),
            'guardarTdr' => route('sgl.pago_proveedores.locador.guardar_tdr', [], false),
            'guardarAnexos' => route('sgl.pago_proveedores.locador.guardar_anexos', [], false),
            'guardarConformidad' => route('sgl.pago_proveedores.locador.guardar_conformidad', [], false),
            'bajaItem' => route('sgl.pago_proveedores.locador.baja_item', [], false),
            'guardarPlantilla' => route('sgl.pago_proveedores.locador.guardar_plantilla', [], false),
            'verPlantilla' => route('sgl.pago_proveedores.locador.ver_plantilla', [], false),
            'archivo' => route('sgl.pago_proveedores.locador.archivo', [], false),
            'seguimiento' => route('sgl.buscar_requerimiento_pago.seguimiento_tramite', [], false),
        ];
    }
}
