<?php

namespace App\Http\Controllers\mod_a\sgl;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Core\DocumentGeneratorService;
use App\Services\mod_a\sgl\VerRequerimientoPresupuestoService;
use App\Services\mod_gene\IngresarTramiteService;
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
 * Pago a proveedores — Presupuesto
 * (migrado desde sigtram_antiguo/Presentacion/mod_a/sgl/pago_proveedores/ver_requerimiento_presupuesto.php).
 */
class VerRequerimientoPresupuestoController extends Controller
{
    public function __construct(
        private readonly SecurityCoreInterface $securityCore,
        private readonly VerRequerimientoPresupuestoService $service,
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

        $vista = 'mod_a.sgl.pago_proveedores.ver_requerimiento_presupuesto';
        if (is_string($pathVista) && $pathVista !== '' && $this->existeVistaBlade($pathVista)) {
            $vista = $pathVista;
        }

        $tituloOpcion = trim((string) ($opcionMenu?->dc_rle ?? 'Pago a proveedores - Presupuesto'));
        if ($tituloOpcion === '') {
            $tituloOpcion = 'Pago a proveedores - Presupuesto';
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

    public function actualizarSiaf(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->actualizarSiaf(
            (int) $request->input('item_id', 0),
            (string) $request->input('valor', '')
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
            $request->only(['ccp', 'ccp_nta', 'meta', 'rubro', 'esp_gasto']),
            $request->file('siaf_file')
        );

        return response()->json($resultado, ! empty($resultado['success']) ? 200 : 422);
    }

    public function enviarAbastecimiento(Request $request): JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $resultado = $this->service->enviarAbastecimiento(
            (int) $request->input('id', 0),
            (int) $request->input('tipo_id', 0),
            (string) $request->input('semilla', ''),
            $request->file('adjunto')
        );

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

    public function verBorrador(Request $request): BinaryFileResponse|JsonResponse
    {
        if (! $this->checkAccess($request)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado.'], 403);
        }

        $tipoId = (int) $request->input('tipo_id', 0);
        $semilla = trim((string) $request->input('semilla', ''));
        $asunto = trim((string) $request->input('asunto', 'TRAMITE DE PAGO'));
        $destArea = (int) $request->input('dest_area', 101);
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
                $plantillaNom = 'memorandum_aprueba_habilitacion.docx';
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
            '/mod_a/sgl/pago_proveedores/ver_requerimiento_presupuesto',
            '/mod_a/sgl/pago_proveedores/ver_requerimiento_presupuesto.php',
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
            'listar' => route('sgl.pago_proveedores.presupuesto.listar', [], false),
            'detalle' => route('sgl.pago_proveedores.presupuesto.detalle', [], false),
            'itemDetalle' => route('sgl.pago_proveedores.presupuesto.item_detalle', [], false),
            'semilla' => route('sgl.pago_proveedores.presupuesto.semilla', [], false),
            'publicar' => route('sgl.pago_proveedores.presupuesto.publicar', [], false),
            'actualizarSiaf' => route('sgl.pago_proveedores.presupuesto.actualizar_siaf', [], false),
            'guardarHabilitacion' => route('sgl.pago_proveedores.presupuesto.guardar_habilitacion', [], false),
            'enviarAbastecimiento' => route('sgl.pago_proveedores.presupuesto.enviar_abastecimiento', [], false),
            'devolver' => route('sgl.pago_proveedores.presupuesto.devolver', [], false),
            'verBorrador' => route('sgl.pago_proveedores.presupuesto.ver_borrador', [], false),
            'archivo' => route('sgl.pago_proveedores.presupuesto.archivo', [], false),
            'seguimiento' => route('sgl.buscar_requerimiento_pago.seguimiento_tramite', [], false),
        ];
    }
}
