<?php
declare(strict_types=1);

namespace App\Http\Controllers\mod_gene\mod_tram_inter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\mod_gene\IngresarTramiteService;
use App\Http\Requests\mod_gene\mod_tram_inter\StoreTramiteInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\GenerarBorradorTramiteRequest;

class IngresarTramiteController extends Controller
{
    private IngresarTramiteService $service;

    public function __construct(IngresarTramiteService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);

        // Obtener cdgo_area del cargo actual del usuario
        $cargoInfo = DB::table($schema . 'p_crgos')
            ->where('cdgo_crgo', $cargoId)
            ->first();

        $areaId = (int) ($cargoInfo->cdgo_area ?? 0);
        $inFrmaDgtalCargo = (int) ($cargoInfo->in_frma_dgtal ?? 0) === 1;

        // Tipos de documento permitidos
        $tiposDocumento = DB::table($schema . 'a_tpo_trmtes as t')
            ->join($schema . 'r_tpo_trmte_areas as r', 't.cdgo_tpo_trmte', '=', 'r.cdgo_tpo_trmte')
            ->select('t.cdgo_tpo_trmte', 't.de_tpo_trmte')
            ->where('t.in_esta', '1')
            ->where('r.in_esta', '1')
            ->where('r.cdgo_area', $areaId)
            ->distinct()
            ->orderBy('t.de_tpo_trmte', 'asc')
            ->get();

        // Áreas destino
        $destinoAreas = DB::table($schema . 'a_areas')
            ->select('cdgo_area', 'de_area', 'dc_area')
            ->where('in_esta', '1')
            ->orderBy('cdgo_tpo_area', 'asc')
            ->orderBy('de_area', 'asc')
            ->get();

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
            $cdgo_tpo_trmte = (int) $request->input('cdgo_tpo_trmte');
            $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

            $cargoId = (int) session('sigtram_cargo_id', 0);
            $cargoInfo = DB::table($schema . 'p_crgos')->where('cdgo_crgo', $cargoId)->first();
            $areaId = (int) ($cargoInfo->cdgo_area ?? 0);

            $currentYear = date('Y');
            $anioInfo = DB::table($schema . 'a_anios')->where('de_anio', $currentYear)->first();
            $cdgo_anio = $anioInfo ? (int) $anioInfo->cdgo_anio : null;

            if (!$cdgo_anio) {
                $latestAnio = DB::table($schema . 'a_anios')->orderBy('de_anio', 'desc')->first();
                if ($latestAnio) {
                    $cdgo_anio = (int) $latestAnio->cdgo_anio;
                    $currentYear = $latestAnio->de_anio;
                }
            }

            if (!$cdgo_anio) {
                return response()->json(['success' => false, 'message' => 'Año de ejercicio no configurado.'], 400);
            }

            $tpoTrmteInfo = DB::table($schema . 'a_tpo_trmtes')->where('cdgo_tpo_trmte', $cdgo_tpo_trmte)->first();
            if (!$tpoTrmteInfo) {
                return response()->json(['success' => false, 'message' => 'Tipo de documento no válido.'], 400);
            }

            $in_scncal = (int) $tpoTrmteInfo->in_scncal;

            // Consultar DB de forma rápida sin transacción solo para visualización
            $query = DB::table($schema . 'a_smlla_intrno')
                ->where('cdgo_tpo_trmte', $cdgo_tpo_trmte)
                ->where('cdgo_anio', $cdgo_anio);

            if ($in_scncal === 1) {
                $query->where('cdgo_crgo', $cargoId);
            } else {
                $query->where('cdgo_area', $areaId);
            }

            $maxSemilla = $query->max('nu_smlla_intrno');
            $nu_smlla = $maxSemilla ? ((int) $maxSemilla) + 1 : 1;

            return response()->json([
                'success' => true,
                'nu_smlla_intrno' => $nu_smlla . '-' . $currentYear,
                'nu_smlla' => $nu_smlla,
                'cdgo_anio' => $cdgo_anio,
                'in_scncal' => $in_scncal,
                'in_frma_dgtal_trmte' => (int) $tpoTrmteInfo->in_frma_dgtal
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function getCargosArea(Request $request)
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
        $cdgo_area = (int) $request->input('cdgo_area');

        $cargos = DB::table($schema . 'p_crgos')
            ->select('cdgo_crgo', 'de_crgo')
            ->where('cdgo_area', $cdgo_area)
            ->whereNull('in_nvel')
            ->orderBy('nu_orde', 'asc')
            ->orderBy('de_crgo', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'cargos' => $cargos
        ]);
    }

    public function store(StoreTramiteInternoRequest $request)
    {
        try {
            // El ID referente (en caso sea una respuesta) vendría en 'hdn_cdgo_dtos_ntrnos'
            $idReferencia = (int) $request->input('hdn_cdgo_dtos_ntrnos', 0);

            $archivos = [
                'fle_adjunto1' => $request->file('fle_adjunto1'),
                'fle_adjunto2' => $request->file('fle_adjunto2'),
                'fle_adjunto3' => $request->file('fle_adjunto3'),
                'fle_adjunto4' => $request->file('fle_adjunto4'),
            ];

            $resultado = $this->service->registrarTramite($request->validated(), array_filter($archivos), $idReferencia);

            return response()->json([
                'success' => true,
                'message' => 'Trámite interno (' . $resultado['titulo'] . ') registrado y enviado con éxito.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el trámite interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generarBorrador(GenerarBorradorTramiteRequest $request)
    {
        try {
            $datosParaWord = $this->service->prepararDatosBorrador($request->validated());

            $generator = app(\App\Services\Core\DocumentGeneratorService::class);
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

            $cargoId = (int) $request->session()->get('sigtram_cargo_id', 0);
            $cargoInfo = DB::table($schema . 'p_crgos')->where('cdgo_crgo', $cargoId)->first();
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

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar borrador: ' . $e->getMessage()], 500);
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

        // Legacy: copiar/usar desde el antiguo si existe
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
            // Cache local en storage para OpenTBS
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
