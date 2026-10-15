<?php
declare(strict_types=1);

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\mod_gene\mod_tram_inter\IngresarTramiteService;
use App\Contracts\Repositories\mod_gene\mod_tram_inter\IngresarTramiteRepositoryInterface;
use App\Http\Requests\mod_gene\mod_tram_inter\StoreTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\GenerarBorradorTramiteRequest;
use App\Http\Controllers\Concerns\ApiResponseTrait;
use App\Support\SessionKeys;

class IngresarTramiteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly IngresarTramiteService $service,
        private readonly IngresarTramiteRepositoryInterface $repository
    ) {}

    /**
     * Resuelve de forma estricta la compañía activa desde la sesión.
     */
    private function resolverCompaniaActiva(Request $request): int
    {
        $comp = (int) $request->session()->get(SessionKeys::COMPANIA_ID, 0);

        if ($comp <= 0) {
            abort(403, 'Sesión sin compañía activa válida o usuario no autorizado.');
        }

        return $comp;
    }

    public function index(Request $request)
    {
        $companiaId = $this->resolverCompaniaActiva($request);
        $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);

        // Obtener cdgo_area del cargo actual del usuario filtrado por compañía
        $cargoInfo = $this->repository->obtenerCargoInfo($cargoId, $companiaId);

        $areaId = (int) ($cargoInfo->cdgo_area ?? 0);
        $inFrmaDgtalCargo = (int) ($cargoInfo->in_frma_dgtal ?? 0) === 1;

        // Tipos de documento permitidos para el área
        $tiposDocumento = $this->repository->obtenerTiposDocumentoPorArea($areaId);

        // Áreas destino activas de la compañía activa
        $destinoAreas = $this->repository->obtenerAreasDestino($companiaId);

        $defaultDate = Carbon::now('America/Lima')->format('Y-m-d\TH:i');
        $minDate = Carbon::now('America/Lima')->subDays(4)->format('Y-m-d') . 'T00:00';
        $maxDate = Carbon::now('America/Lima')->format('Y-m-d\TH:i');

        return view('mod_gene.mod_tram_inter.ingresar-tramite', compact(
            'tiposDocumento',
            'destinoAreas',
            'defaultDate',
            'minDate',
            'maxDate',
            'areaId',
            'cargoId',
            'inFrmaDgtalCargo'
        ));
    }

    public function getSemilla(Request $request)
    {
        try {
            $companiaId = $this->resolverCompaniaActiva($request);
            $cdgo_tpo_trmte = (int) $request->input('cdgo_tpo_trmte');

            $cargoId = (int) session(SessionKeys::CARGO_ID, 0);
            $cargoInfo = $this->repository->obtenerCargoInfo($cargoId, $companiaId);
            $areaId = (int) ($cargoInfo->cdgo_area ?? 0);

            $currentYear = (int) date('Y');
            $anioInfo = $this->repository->obtenerAnioPorValor($currentYear);
            $cdgo_anio = $anioInfo ? (int) $anioInfo->cdgo_anio : null;

            if (!$cdgo_anio) {
                $latestAnio = $this->repository->obtenerAnioReciente();
                if ($latestAnio) {
                    $cdgo_anio = (int) $latestAnio->cdgo_anio;
                    $currentYear = (int) $latestAnio->de_anio;
                }
            }

            if (!$cdgo_anio) {
                return response()->json(['success' => false, 'message' => 'Año de ejercicio no configurado.'], 400);
            }

            $tpoTrmteInfo = $this->repository->obtenerInfoTipoTramite($cdgo_tpo_trmte);
            if (!$tpoTrmteInfo) {
                return response()->json(['success' => false, 'message' => 'Tipo de documento no válido.'], 400);
            }

            $in_scncal = (int) $tpoTrmteInfo->in_scncal;
            $nu_smlla = $this->repository->obtenerSemillaCorrelativo(
                $cdgo_tpo_trmte,
                $cdgo_anio,
                $areaId,
                $cargoId,
                $in_scncal
            );

            return response()->json([
                'success' => true,
                'nu_smlla_intrno' => $nu_smlla . '-' . $currentYear,
                'nu_smlla' => $nu_smlla,
                'cdgo_anio' => $cdgo_anio,
                'in_scncal' => $in_scncal,
                'in_frma_dgtal_trmte' => (int) $tpoTrmteInfo->in_frma_dgtal
            ]);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al obtener correlativo de trámite.');
        }
    }

    public function getCargosArea(Request $request)
    {
        try {
            $companiaId = $this->resolverCompaniaActiva($request);
            $cdgo_area = (int) $request->input('cdgo_area');

            $cargos = $this->repository->obtenerCargosPorArea($cdgo_area, $companiaId);

            return response()->json([
                'success' => true,
                'cargos' => $cargos
            ]);
        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al obtener los cargos del área.');
        }
    }

    public function store(StoreTramiteInternoRequest $request)
    {
        try {
            $companiaId = $this->resolverCompaniaActiva($request);

            // El ID referente (en caso sea una respuesta) vendría en 'hdn_cdgo_dtos_ntrnos'
            $idReferencia = (int) $request->input('hdn_cdgo_dtos_ntrnos', 0);

            $archivos = [
                'fle_adjunto1' => $request->file('fle_adjunto1'),
                'fle_adjunto2' => $request->file('fle_adjunto2'),
                'fle_adjunto3' => $request->file('fle_adjunto3'),
                'fle_adjunto4' => $request->file('fle_adjunto4'),
            ];

            $resultado = $this->service->registrarTramite(
                $request->validated(),
                array_filter($archivos),
                $idReferencia,
                $companiaId
            );

            return response()->json([
                'success' => true,
                'message' => 'Trámite interno (' . $resultado['titulo'] . ') registrado y enviado con éxito.'
            ]);

        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al guardar el trámite interno.');
        }
    }

    public function generarBorrador(GenerarBorradorTramiteRequest $request)
    {
        try {
            $companiaId = $this->resolverCompaniaActiva($request);
            $datosParaWord = $this->service->prepararDatosBorrador($request->validated(), $companiaId);

            $generator = app(\App\Services\Core\DocumentGeneratorService::class);

            $tpoTrmteInfo = $this->repository->obtenerInfoTipoTramite((int) $datosParaWord['cdgo_tpo_trmte']);

            if (! $tpoTrmteInfo || empty($tpoTrmteInfo->no_plntlla)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este tipo de documento no tiene plantilla configurada (no_plntlla).',
                ], 400);
            }

            $cargoId = (int) $request->session()->get(SessionKeys::CARGO_ID, 0);
            $cargoInfo = $this->repository->obtenerCargoInfo($cargoId, $companiaId);
            $areaId = (int) ($cargoInfo->cdgo_area ?? 0);

            $templatePath = $this->resolverRutaPlantilla((string) $tpoTrmteInfo->no_plntlla, $areaId);
            if ($templatePath === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'La plantilla solicitada no existe: ' . $tpoTrmteInfo->no_plntlla
                        . '. Colóquela en storage/app/plantillas/mod_gene/mod_tram_inter/'
                        . ' (o en Presentacion/.../plantillas del sistema antiguo).',
                ], 404);
            }

            $fileName = $datosParaWord['nu_tram_todo'] . '.docx';
            $fileName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $fileName);

            $filePath = $generator->generarBorradorInterno($datosParaWord, $templatePath, $fileName);

            return response()->download($filePath, $fileName, [
                'Access-Control-Expose-Headers' => 'X-Suggested-Filename, Content-Disposition',
                'X-Suggested-Filename' => rawurlencode($fileName),
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            return $this->respuestaSeguraError($e, 'Error al generar el borrador del documento.');
        }
    }

    /**
     * Resuelve plantilla Word como gene_docu.php:
     * 1) storage/app/plantillas/mod_gene/mod_tram_inter/{area}_{archivo}
     * 2) storage/app/plantillas/mod_gene/mod_tram_inter/{archivo}
     * 3) legacy Presentacion/.../plantillas/{area}_{archivo}
     * 4) legacy Presentacion/.../plantillas/{archivo}
     *
     * @return string|null Ruta relativa a storage/app/ (para DocumentGeneratorService)
     */
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
}
