<?php
declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException;
use App\Exceptions\mod_gene\DestinoTramiteInvalidoException;
use App\Exceptions\mod_gene\OperacionTramiteNoAutorizadaException;
use App\Exceptions\mod_gene\TramiteInternoNoEncontradoException;
use App\Exceptions\mod_gene\TransicionTramiteInvalidaException;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\Services\mod_gene\mod_tram_inter\MaquinaEstadosTramiteInterno;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;
use ZipArchive;
use InvalidArgumentException;

class CorreoInternoTransaccionalService
{
    private readonly TramiteInternoRepositoryInterface $repository;
    private readonly TramiteInternoPolicy $policy;
    private readonly MaquinaEstadosTramiteInterno $maquinaEstados;
    private readonly DatabaseManager $db;
    private readonly GestorAdjuntosInterface $gestorAdjuntos;

    public function __construct(
        ?TramiteInternoRepositoryInterface $repository = null,
        ?TramiteInternoPolicy $policy = null,
        ?MaquinaEstadosTramiteInterno $maquinaEstados = null,
        ?DatabaseManager $db = null,
        ?GestorAdjuntosInterface $gestorAdjuntos = null
    ) {
        $this->repository = $repository ?? app(TramiteInternoRepositoryInterface::class);
        $this->policy = $policy ?? app(TramiteInternoPolicy::class);
        $this->maquinaEstados = $maquinaEstados ?? app(MaquinaEstadosTramiteInterno::class);
        $this->db = $db ?? app(DatabaseManager::class);
        $this->gestorAdjuntos = $gestorAdjuntos ?? app(GestorAdjuntosInterface::class);
    }

    /**
     * Obtiene listas de apoyo para los formularios modales:
     * - Cargos y colaboradores de la misma área activa (para Asignar)
     * - Áreas destino (para Derivar)
     * - Tipos de documento autorizados para el área (para Crear Trámite Interno)
     */
    public function obtenerDatosTransaccionales(int $cargoId, int $areaId): array
    {
        // 1. Cargos del área (excluyendo el cargo actual y usuario sistema id 8)
        $cargosArea = $this->repository->obtenerCargosDeArea($areaId, $cargoId);

        // 2. Todas las áreas municipales destino
        $areasDestino = $this->repository->obtenerAreasDestino();

        // 3. Tipos de documento autorizados para el área actual
        $tiposDocumento = $this->repository->obtenerTiposDocumentoInterno($areaId);

        return [
            'cargosArea' => $cargosArea,
            'areasDestino' => $areasDestino,
            'tiposDocumento' => $tiposDocumento,
            'defaultFecha' => now()->format('Y-m-d\TH:i'),
        ];
    }

    /**
     * Calcula el siguiente número correlativo de trámite interno para el tipo de documento seleccionado
     */
    public function obtenerCorrelativoInterno(int $cdgoTpoTrmte, int $cargoId, int $areaId): array
    {
        $currentYear = (int) date('Y');
        $anioInfo = $this->repository->obtenerAnioPorNumero($currentYear);
        $cdgoAnio = $anioInfo ? (int) $anioInfo->cdgo_anio : null;
        $anioStr = (string) $currentYear;

        if (!$cdgoAnio) {
            $latestAnio = $this->repository->obtenerUltimoAnio();
            if ($latestAnio) {
                $cdgoAnio = (int) $latestAnio->cdgo_anio;
                $anioStr = (string) $latestAnio->de_anio;
            }
        }

        $tpoTrmteInfo = $this->repository->obtenerTipoDocumentoInterno($cdgoTpoTrmte);
        if (!$tpoTrmteInfo) {
            throw new \InvalidArgumentException('Tipo de documento no válido.');
        }

        $inScncal = (int) ($tpoTrmteInfo->in_scncal ?? 0);

        $nuSemilla = $this->repository->obtenerSemillaInternaParaBloqueo(
            (int) ($cdgoAnio ?? 1),
            $cdgoTpoTrmte,
            $cargoId,
            $areaId,
            $inScncal === 1,
            false
        );

        return [
            'nu_smlla' => $nuSemilla,
            'de_anio' => $anioStr,
            'cdgo_anio' => $cdgoAnio,
            'nu_smlla_intrno' => $nuSemilla . '-' . $anioStr,
            'in_scncal' => $inScncal,
            'in_frma_dgtal' => (int) ($tpoTrmteInfo->in_frma_dgtal ?? 0),
        ];
    }

    /**
     * Procesa y almacena hasta 4 archivos adjuntos mediante GestorAdjuntosInterface (RTI-C02).
     * Garantiza nombres no predecibles, almacenamiento privado, validación MIME estricta
     * y seguimiento de archivos creados para limpieza compensatoria si la transacción de BD falla.
     *
     * @param array<string, mixed> $archivos
     * @param int $nextId
     * @param array<string> &$archivosGuardados
     * @return string Ruta relativa del archivo guardado en almacenamiento privado.
     */
    public function procesarArchivos(array $archivos, int $nextId, array &$archivosGuardados = []): string
    {
        $validFiles = [];
        for ($i = 1; $i <= 4; $i++) {
            $file = $archivos["fle_adjunto{$i}"] ?? null;
            if ($file instanceof UploadedFile && $file->isValid()) {
                $validFiles[$i] = $file;
            }
        }

        if (empty($validFiles)) {
            return '';
        }

        // Caso 1: Exactamente 1 archivo adjunto
        if (count($validFiles) === 1) {
            $file = reset($validFiles);
            $dto = $this->gestorAdjuntos->guardar($file, 'internos');
            $archivosGuardados[] = $dto->rutaRelativa;
            return $dto->rutaRelativa;
        }

        // Caso 2: Múltiples archivos -> Empaquetar en ZIP en directorio temporal seguro y guardar vía GestorAdjuntos
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $tempZipPath = tempnam($tempDir, 'adj_zip_');
        if ($tempZipPath === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal para consolidar adjuntos múltiples.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempZipPath);
            throw new \RuntimeException('Error al inicializar el contenedor ZIP de adjuntos.');
        }

        $zip->addEmptyDir('anexos');
        $principalIdx = 1;
        if (!isset($validFiles[1])) {
            $keys = array_keys($validFiles);
            $principalIdx = $keys[0];
        }

        foreach ($validFiles as $pos => $file) {
            $nombreSanitizado = preg_replace('/[^a-zA-Z0-9_\-\. \[\]]/', '_', $file->getClientOriginalName());
            if ($pos === $principalIdx) {
                $zip->addFile($file->getRealPath(), $nombreSanitizado);
            } else {
                $zip->addFile($file->getRealPath(), 'anexos/' . $nombreSanitizado);
            }
        }
        $zip->close();

        try {
            $uploadedZip = new UploadedFile(
                $tempZipPath,
                "anexos_{$nextId}.zip",
                'application/zip',
                null,
                true // test mode to allow local path
            );

            $dto = $this->gestorAdjuntos->guardar($uploadedZip, 'internos');
            $archivosGuardados[] = $dto->rutaRelativa;

            return $dto->rutaRelativa;
        } finally {
            if (file_exists($tempZipPath)) {
                @unlink($tempZipPath);
            }
        }
    }

    /**
     * Opcional: Genera registro en p_dtos_ntrnos y correlativo si se marcó "Crear Trámite Interno"
     */
    private function crearDocumentoInterno(
        int $tramiteReferenciaId,
        int $tipoDocId,
        string $numSemillaTxt,
        bool $envioFisico,
        int $cargoId,
        int $areaId,
        int $loginId,
        string $fechaEnvio,
        string $archivoAdjunto,
        int $companiaId = 0
    ): ?array {
        if ($tipoDocId <= 0) {
            return null;
        }

        // AG-TI-15: Resolver año oficial
        $currentYear = (int) date('Y');
        $anioInfo = $this->repository->obtenerAnioPorNumero($currentYear);
        if (!$anioInfo) {
            $latestAnio = $this->repository->obtenerUltimoAnio();
            $cdgoAnio = $latestAnio ? (int) $latestAnio->cdgo_anio : 1;
            $deAnio = $latestAnio ? (string) $latestAnio->de_anio : (string) $currentYear;
        } else {
            $cdgoAnio = (int) $anioInfo->cdgo_anio;
            $deAnio = (string) $anioInfo->de_anio;
        }

        // Validar tipo de documento
        $tpoDoc = $this->repository->obtenerTipoDocumentoInterno($tipoDocId);
        if (!$tpoDoc) {
            throw new \InvalidArgumentException('Tipo de documento no válido para correlativo.');
        }
        $inScncal = (int) ($tpoDoc->in_scncal ?? 0);

        // Bloqueo pesimista para evitar colisión concurrente (lockForUpdate)
        $nuSemilla = $this->repository->obtenerSemillaInternaParaBloqueo($cdgoAnio, $tipoDocId, $cargoId, $areaId, $inScncal === 1, true);

        // 1. Guardar correlativo en a_smlla_intrno con el valor reservado atómicamente
        $this->repository->crearSemillaInterna([
            'cdgo_tpo_trmte' => $tipoDocId,
            'cdgo_anio' => $cdgoAnio,
            'cdgo_area' => $areaId,
            'cdgo_crgo' => ($inScncal === 1) ? $cargoId : null,
            'nu_smlla_intrno' => $nuSemilla,
            'co_usua_modi' => $loginId,
            'ts_usua_modi' => now(),
            'in_esta' => 1,
        ]);

        // 2. Obtener datos del trámite original de referencia
        $refTramite = $this->repository->obtenerReferenciaDocumentoInterno($tramiteReferenciaId);

        $obsRef = '';
        if ($refTramite) {
            $obsRef = "CON REFERENCIA AL " . ($refTramite->de_tpo_trmte ?? 'TRÁMITE') . " N° " . ($refTramite->nu_tram_todo ?? '') . " DEL AREA " . ($refTramite->de_area ?? '');
        }

        $numAuto = $nuSemilla . '-' . $deAnio;
        $numTramTodo = str_pad((string)$nuSemilla, 6, '0', STR_PAD_LEFT) . '-' . $deAnio;

        $cmpId = $companiaId > 0 ? $companiaId : (int) ($refTramite->cdgo_cmpnia ?? (function_exists('session') ? session(\App\Support\SessionKeys::USER_COMPANY, 0) : 0));

        // 3. Insertar en p_dtos_ntrnos
        $nuevoDocId = $this->repository->crearDocumentoInterno([
            'cdgo_tpo_trmte' => $tipoDocId,
            'cdgo_anio' => $cdgoAnio,
            'cdgo_area' => $areaId,
            'cdgo_lgin' => $loginId,
            'nu_tram_todo' => $numTramTodo,
            'nu_tram' => $nuSemilla,
            'de_obser' => $obsRef,
            'de_urls_adjnto' => $archivoAdjunto,
            'fe_ingrso' => $fechaEnvio,
            'in_esta' => '1',
            'cdgo_cmpnia' => $cmpId > 0 ? $cmpId : null,
            'cdgo_dtos_ntrnos_pri' => $tramiteReferenciaId,
            'in_envo_fsco' => $envioFisico ? 1 : 0,
            'nu_tram_cmplto' => ($tpoDoc->de_tpo_trmte ?? 'DOC') . ' N° ' . $numAuto,
            'in_prrdad_trmte' => 1,
        ]);

        // 4. Crear movimiento inicial de este nuevo documento interno
        $nuevoMovId = $this->repository->crearMovimientoInterno([
            'cdgo_dtos_ntrnos' => $nuevoDocId,
            'cdgo_estdo_trmte' => 1,
            'cdgo_lgin' => $loginId,
            'cdgo_crgo_sa' => $cargoId,
            'cdgo_crgo_en' => $cargoId,
            'de_urls_agrdo' => $archivoAdjunto,
            'fe_rcbdo' => $fechaEnvio,
            'de_obser_ntrnos' => $obsRef,
            'in_copia' => '1',
            'in_esta' => '1',
        ]);

        return [
            'cdgo_dtos_ntrnos' => $nuevoDocId,
            'cdgo_trmtes_ntrnos' => $nuevoMovId,
            'de_tpo_trmte' => $tpoDoc->de_tpo_trmte ?? 'DOCUMENTO',
            'num_auto' => $numAuto,
        ];
    }

    /**
     * FLUJO 0: RECEPCIONAR TRÁMITES (segi_recibir.php legacy / RA-03, RA-11)
     * Deshabilita movimiento en Entrada (in_esta = '0') bajo lock pesimista
     * e inserta nuevo movimiento en estado 2 (RECEPCIONADO) o 23 (RECEP. COPIA).
     */
    public function ejecutarRecepcionar(
        array $ids,
        string $fecha,
        string $observaciones,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        if (empty($ids)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un trámite para recepcionar.');
        }

        $idsNormalizados = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($idsNormalizados)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un trámite válido para recepcionar.');
        }

        $auditLoginId = $contexto->getCdgoLginAudit();

        return $this->db->transaction(function () use ($idsNormalizados, $fecha, $observaciones, $contexto, $ip, $auditLoginId) {
            $movimientos = $this->repository->obtenerMovimientosVigentesParaOperacion($idsNormalizados, $contexto);

            if (count($movimientos) !== count($idsNormalizados)) {
                throw new TramiteInternoNoEncontradoException('Uno o más trámites del lote no fueron encontrados, pertenecen a otra compañía o no están vigentes.');
            }

            $procesados = 0;
            foreach ($movimientos as $mov) {
                if (!$this->policy->recepcionar($contexto, $mov)) {
                    throw new OperacionTramiteNoAutorizadaException("Operación de recepción no autorizada para el trámite {$mov->id}.");
                }

                if (!$this->maquinaEstados->esTransicionValida($mov->estadoId, 'recepcionar')) {
                    throw new TransicionTramiteInvalidaException("Transición inválida desde el estado actual para el trámite {$mov->id}.");
                }

                $affected = $this->repository->desactivarMovimientoInternoEsperado($mov->movimientoId);

                if ($affected !== 1) {
                    throw new ConflictoConcurrenteTramiteException("Conflicto de concurrencia al recepcionar el trámite {$mov->id}.");
                }

                $this->repository->crearMovimientoInterno([
                    'cdgo_dtos_ntrnos' => $mov->id,
                    'cdgo_estdo_trmte' => 2, // Por paridad legacy activa (segi_enviar.php): siempre estado 2
                    'cdgo_lgin' => $auditLoginId,
                    'cdgo_crgo_sa' => $contexto->idCargo,
                    'cdgo_crgo_en' => $contexto->idCargo,
                    'de_urls_agrdo' => '',
                    'fe_rcbdo' => $fecha,
                    'de_obser_ntrnos' => $observaciones,
                    'in_copia' => $mov->inCopia,
                    'in_esta' => '1',
                    'cdgo_dtos_ntrnos_rfrncia' => null,
                    'nu_tram_todo_rfncia' => null,
                ]);

                try {
                    $this->repository->registrarSeguimientoInterno(
                        $auditLoginId,
                        "Se recepciono tramite interno dto: {$mov->id} mov: {$mov->movimientoId}",
                        $ip
                    );
                } catch (\Throwable) {
                }

                $procesados++;
            }

            return [
                'status' => 'success',
                'message' => 'Trámite(s) recepcionado(s) correctamente.',
                'procesados' => $procesados,
            ];
        });
    }

    /**
     * FLUJO 2: ASIGNAR TRÁMITE INTERNAMENTE (segi_asignar.php legacy)
     * Deshabilita movimiento previo (in_esta = '0')
     * Inserta nuevo movimiento con cdgo_estdo_trmte = 7 (ASIGNADO) hacia el cargo destino de la misma área.
     */
    public function ejecutarAsignar(
        array $ids,
        int $cargoDestino,
        string $observaciones,
        string $fecha,
        array $docInternoParams,
        array $archivos,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        if (empty($ids)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente para asignar.');
        }
        $idsNormalizados = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($idsNormalizados)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente válido para asignar.');
        }
        if ($cargoDestino <= 0) {
            throw new DestinoTramiteInvalidoException('Debe seleccionar el cargo o colaborador destino.');
        }

        $auditLoginId = $contexto->getCdgoLginAudit();
        $archivosGuardados = [];
        try {
            return $this->db->transaction(function () use (
                $idsNormalizados, $cargoDestino, $observaciones, $fecha,
                $docInternoParams, $archivos, $contexto, $ip, $auditLoginId, &$archivosGuardados
            ) {
                // RTI-04 / RTI-05: Validación y bloqueo de cargo destino dentro de la transacción para prevenir TOCTOU
                $cargoDestRow = $this->repository->obtenerCargoDestino($cargoDestino, true);

                if (!$cargoDestRow) {
                    throw new DestinoTramiteInvalidoException("El cargo destino {$cargoDestino} no existe o no está activo.");
                }

                $cargoDestinoAreaId = (int) $cargoDestRow->cdgo_area;
                if ($cargoDestinoAreaId !== $contexto->idArea) {
                    throw new DestinoTramiteInvalidoException('El cargo destino no pertenece a la misma área del funcionario.');
                }

                $movimientos = $this->repository->obtenerMovimientosVigentesParaOperacion($idsNormalizados, $contexto);

                if (count($movimientos) !== count($idsNormalizados)) {
                    throw new TramiteInternoNoEncontradoException('Uno o más expedientes del lote no fueron encontrados, pertenecen a otra compañía o no están vigentes.');
                }

                foreach ($movimientos as $mov) {
                    if (!$this->policy->asignar($contexto, $mov, $cargoDestinoAreaId)) {
                        throw new OperacionTramiteNoAutorizadaException("Operación de asignación no autorizada para el expediente {$mov->id}.");
                    }
                    if (!$this->maquinaEstados->esTransicionValida($mov->estadoId, 'asignar')) {
                        throw new TransicionTramiteInvalidaException("Transición inválida desde el estado actual para el expediente {$mov->id}.");
                    }
                }

                $nextSeqId = $this->obtenerSiguienteSecuencia();

                $nombreAdjunto = $this->procesarArchivos($archivos, $nextSeqId, $archivosGuardados);
                $observacionesUpper = mb_strtoupper(trim($observaciones ?: 'SIN NOVEDADES PARA ASIGNAR'), 'UTF-8');

                $procesados = 0;
                foreach ($movimientos as $mov) {
                    $textoDocCreado = '';
                    $refMovId = null;
                    if (!empty($docInternoParams['crear_tramite_interno'])) {
                        $creado = $this->crearDocumentoInterno(
                            $mov->id,
                            (int) ($docInternoParams['cdgo_tpo_trmte'] ?? 0),
                            (string) ($docInternoParams['txt_nu_smlla_intrno'] ?? ''),
                            !empty($docInternoParams['in_envo_fsco']),
                            $contexto->idCargo,
                            $contexto->idArea,
                            $auditLoginId,
                            $fecha,
                            $nombreAdjunto,
                            $contexto->idCompania
                        );
                        if ($creado) {
                            $refMovId = $creado['cdgo_trmtes_ntrnos'];
                            $textoDocCreado = "; SE ASIGNA CON " . $creado['de_tpo_trmte'] . " N° " . $creado['num_auto'];
                        }
                    }

                    $affected = $this->repository->desactivarMovimientoInternoEsperado($mov->movimientoId);

                    if ($affected !== 1) {
                        throw new ConflictoConcurrenteTramiteException("Conflicto de concurrencia al deshabilitar movimiento previo del expediente {$mov->id}.");
                    }

                    $this->repository->crearMovimientoInterno([
                        'cdgo_dtos_ntrnos' => $mov->id,
                        'cdgo_estdo_trmte' => 7, // ASIGNADO
                        'cdgo_lgin' => $auditLoginId,
                        'cdgo_crgo_sa' => $contexto->idCargo,
                        'cdgo_crgo_en' => $cargoDestino,
                        'de_urls_agrdo' => $nombreAdjunto,
                        'fe_rcbdo' => $fecha,
                        'de_obser_ntrnos' => $observacionesUpper . $textoDocCreado,
                        'in_copia' => '1',
                        'in_esta' => '1',
                        'cdgo_dtos_ntrnos_rfrncia' => $refMovId,
                    ]);

                    try {
                        $this->repository->registrarSeguimientoInterno(
                            $auditLoginId,
                            "Se asigna el tramite interno N {$mov->id} al cargo {$cargoDestino}",
                            $ip
                        );
                    } catch (\Throwable) {
                    }

                    $procesados++;
                }

                return [
                    'status' => 'success',
                    'message' => "Se asignaron {$procesados} expediente(s) correctamente.",
                    'procesados' => $procesados,
                ];
            });
        } catch (\Throwable $e) {
            foreach ($archivosGuardados as $archivoRel) {
                try {
                    $this->gestorAdjuntos->eliminar($archivoRel);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
    }

    /**
     * FLUJO 1: DERIVAR TRÁMITE A OTRAS ÁREAS (segi_reenviar.php legacy)
     * Deshabilita movimiento previo (in_esta = '0')
     * Por cada área seleccionada, obtiene su cargo principal e inserta movimiento con estado 1 (INGRESADO).
     */
    public function ejecutarDerivar(
        array $ids,
        array $areasDestino,
        string $observaciones,
        string $fecha,
        array $docInternoParams,
        array $archivos,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        if (empty($ids)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente para derivar.');
        }
        $idsNormalizados = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($idsNormalizados)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente válido para derivar.');
        }

        $areasNormalizadas = array_values(array_unique(array_filter(array_map('intval', $areasDestino))));
        if (empty($areasNormalizadas)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un área de destino válida.');
        }

        $auditLoginId = $contexto->getCdgoLginAudit();
        $archivosGuardados = [];
        try {
            return $this->db->transaction(function () use (
                $idsNormalizados, $areasNormalizadas, $observaciones, $fecha,
                $docInternoParams, $archivos, $contexto, $ip, $auditLoginId, &$archivosGuardados
            ) {
                // RTI-04 / RTI-05: Resolver y validar todas las áreas de destino y sus cargos dentro de la transacción con lockForUpdate
                $cargosPorArea = [];
                foreach ($areasNormalizadas as $areaDest) {
                    $areaRow = $this->repository->obtenerArea($areaDest, true);

                    if (!$areaRow) {
                        throw new DestinoTramiteInvalidoException("El área de destino {$areaDest} no existe o no está activa.");
                    }

                    $cargoDestRow = $this->repository->obtenerCargoPrincipalDeArea($areaDest, true);

                    if (!$cargoDestRow || (int) $cargoDestRow->cdgo_crgo <= 0) {
                        throw new DestinoTramiteInvalidoException("El área {$areaDest} no cuenta con ningún cargo activo para recibir la derivación.");
                    }

                    $cargoDestId = (int) $cargoDestRow->cdgo_crgo;
                    if ($cargoDestId === $contexto->idCargo) {
                        throw new DestinoTramiteInvalidoException('No se permite derivar un expediente al mismo cargo remitente.');
                    }

                    $cargosPorArea[$areaDest] = $cargoDestId;
                }

                $movimientos = $this->repository->obtenerMovimientosVigentesParaOperacion($idsNormalizados, $contexto);

                if (count($movimientos) !== count($idsNormalizados)) {
                    throw new TramiteInternoNoEncontradoException('Uno o más expedientes del lote no fueron encontrados, pertenecen a otra compañía o no están vigentes.');
                }

                foreach ($movimientos as $mov) {
                    if (!$this->policy->derivar($contexto, $mov)) {
                        throw new OperacionTramiteNoAutorizadaException("Operación de derivación no autorizada para el expediente {$mov->id}.");
                    }
                    if (!$this->maquinaEstados->esTransicionValida($mov->estadoId, 'derivar')) {
                        throw new TransicionTramiteInvalidaException("Transición inválida desde el estado actual para el expediente {$mov->id}.");
                    }
                }

                $nextSeqId = $this->obtenerSiguienteSecuencia();

                $nombreAdjunto = $this->procesarArchivos($archivos, $nextSeqId, $archivosGuardados);
                $observacionesUpper = mb_strtoupper(trim($observaciones ?: 'SIN NOVEDADES PARA ENVIAR'), 'UTF-8');

                $procesados = 0;
                foreach ($movimientos as $mov) {
                    $textoDocCreado = '';
                    $refMovId = null;
                    if (!empty($docInternoParams['crear_tramite_interno'])) {
                        $creado = $this->crearDocumentoInterno(
                            $mov->id,
                            (int) ($docInternoParams['cdgo_tpo_trmte'] ?? 0),
                            (string) ($docInternoParams['txt_nu_smlla_intrno'] ?? ''),
                            !empty($docInternoParams['in_envo_fsco']),
                            $contexto->idCargo,
                            $contexto->idArea,
                            $auditLoginId,
                            $fecha,
                            $nombreAdjunto,
                            $contexto->idCompania
                        );
                        if ($creado) {
                            $refMovId = $creado['cdgo_trmtes_ntrnos'];
                            $textoDocCreado = "; SE DERIVA CON " . $creado['de_tpo_trmte'] . " N° " . $creado['num_auto'];
                        }
                    }

                    $affected = $this->repository->desactivarMovimientoInternoEsperado($mov->movimientoId);

                    if ($affected !== 1) {
                        throw new ConflictoConcurrenteTramiteException("Conflicto de concurrencia al deshabilitar movimiento previo del expediente {$mov->id}.");
                    }

                    foreach ($areasNormalizadas as $areaDest) {
                        $cargoDestId = $cargosPorArea[$areaDest];

                        $this->repository->crearMovimientoInterno([
                            'cdgo_dtos_ntrnos' => $mov->id,
                            'cdgo_estdo_trmte' => 1, // INGRESADO (Bandeja Entrada del receptor)
                            'cdgo_lgin' => $auditLoginId,
                            'cdgo_crgo_sa' => $contexto->idCargo,
                            'cdgo_crgo_en' => $cargoDestId,
                            'de_urls_agrdo' => $nombreAdjunto,
                            'fe_rcbdo' => $fecha,
                            'de_obser_ntrnos' => $observacionesUpper . $textoDocCreado,
                            'in_copia' => '1',
                            'in_esta' => '1',
                            'cdgo_dtos_ntrnos_rfrncia' => $refMovId,
                        ]);
                    }

                    try {
                        $this->repository->registrarSeguimientoInterno(
                            $auditLoginId,
                            "Se reenvia el tramite interno N {$mov->id}",
                            $ip
                        );
                    } catch (\Throwable) {
                    }

                    $procesados++;
                }

                return [
                    'status' => 'success',
                    'message' => "Se derivaron {$procesados} expediente(s) a " . count($areasNormalizadas) . " área(s).",
                    'procesados' => $procesados,
                ];
            });
        } catch (\Throwable $e) {
            foreach ($archivosGuardados as $archivoRel) {
                try {
                    $this->gestorAdjuntos->eliminar($archivoRel);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
    }

    /**
     * FLUJO 3: FINALIZAR TRÁMITE (segi_finaliza.php legacy)
     * Deshabilita movimiento previo (in_esta = '0')
     * Inserta nuevo movimiento con cdgo_estdo_trmte = 3 (FINALIZADO) o 24 (FINAL. COPIA).
     */
    public function ejecutarFinalizar(
        array $ids,
        string $observaciones,
        string $fecha,
        array $archivos,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        if (empty($ids)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente para finalizar.');
        }
        $idsNormalizados = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($idsNormalizados)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente válido para finalizar.');
        }

        $auditLoginId = $contexto->getCdgoLginAudit();
        $archivosGuardados = [];
        try {
            return $this->db->transaction(function () use ($idsNormalizados, $observaciones, $fecha, $archivos, $contexto, $ip, $auditLoginId, &$archivosGuardados) {
                $movimientos = $this->repository->obtenerMovimientosVigentesParaOperacion($idsNormalizados, $contexto);

                if (count($movimientos) !== count($idsNormalizados)) {
                    throw new TramiteInternoNoEncontradoException('Uno o más expedientes del lote no fueron encontrados, pertenecen a otra compañía o no están vigentes.');
                }

                foreach ($movimientos as $mov) {
                    if (!$this->policy->finalizar($contexto, $mov)) {
                        throw new OperacionTramiteNoAutorizadaException("Operación de finalización no autorizada para el expediente {$mov->id}.");
                    }
                    if (!$this->maquinaEstados->esTransicionValida($mov->estadoId, 'finalizar')) {
                        throw new TransicionTramiteInvalidaException("Transición inválida desde el estado actual para el expediente {$mov->id}.");
                    }
                }

                $nextSeqId = $this->obtenerSiguienteSecuencia();

                $nombreAdjunto = $this->procesarArchivos($archivos, $nextSeqId, $archivosGuardados);
                $observacionesUpper = mb_strtoupper(trim($observaciones ?: 'SIN NOVEDADES PARA FINALIZAR'), 'UTF-8');

                $procesados = 0;
                foreach ($movimientos as $mov) {
                    $affected = $this->repository->desactivarMovimientoInternoEsperado($mov->movimientoId);

                    if ($affected !== 1) {
                        throw new ConflictoConcurrenteTramiteException("Conflicto de concurrencia al deshabilitar movimiento previo del expediente {$mov->id}.");
                    }

                    $estadoFinal = 3; // Por paridad legacy activa (segi_finaliza.php): siempre estado 3

                    $this->repository->crearMovimientoInterno([
                        'cdgo_dtos_ntrnos' => $mov->id,
                        'cdgo_estdo_trmte' => $estadoFinal,
                        'cdgo_lgin' => $auditLoginId,
                        'cdgo_crgo_sa' => $contexto->idCargo,
                        'cdgo_crgo_en' => $contexto->idCargo,
                        'de_urls_agrdo' => $nombreAdjunto,
                        'fe_rcbdo' => $fecha,
                        'de_obser_ntrnos' => $observacionesUpper,
                        'in_copia' => $mov->inCopia,
                        'in_esta' => '1',
                    ]);

                    try {
                        $this->repository->registrarSeguimientoInterno(
                            $auditLoginId,
                            "Se finalizo el expediente {$mov->id}",
                            $ip
                        );
                    } catch (\Throwable) {
                    }

                    $procesados++;
                }

                return [
                    'status' => 'success',
                    'message' => "Se finalizaron {$procesados} expediente(s) con éxito.",
                    'procesados' => $procesados,
                ];
            });
        } catch (\Throwable $e) {
            foreach ($archivosGuardados as $archivoRel) {
                try {
                    $this->gestorAdjuntos->eliminar($archivoRel);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
    }

    /**
     * FLUJO 4: DEVOLVER TRÁMITE (segi_regresar.php legacy)
     * Deshabilita movimiento en Salida / Recibidos e inserta estado 4 (DEVUELTO).
     */
    public function ejecutarDevolver(
        array $ids,
        string $observaciones,
        string $fecha,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        if (empty($ids)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente para devolver.');
        }
        $idsNormalizados = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($idsNormalizados)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente válido para devolver.');
        }

        $causalLimpia = trim($observaciones);
        if ($causalLimpia === '' || strcasecmp($causalLimpia, 'Sin novedades para devolver') === 0) {
            throw new InvalidArgumentException('Debe ingresar un motivo o causal real de devolución.');
        }

        $auditLoginId = $contexto->getCdgoLginAudit();

        return $this->db->transaction(function () use ($idsNormalizados, $causalLimpia, $fecha, $contexto, $ip, $auditLoginId) {
            $movimientos = $this->repository->obtenerMovimientosVigentesParaOperacion($idsNormalizados, $contexto);

            if (count($movimientos) !== count($idsNormalizados)) {
                throw new TramiteInternoNoEncontradoException('Uno o más expedientes del lote no fueron encontrados, pertenecen a otra compañía o no están vigentes.');
            }

            foreach ($movimientos as $mov) {
                if (!$this->policy->devolver($contexto, $mov, $causalLimpia)) {
                    throw new OperacionTramiteNoAutorizadaException("Operación de devolución no autorizada para el expediente {$mov->id}.");
                }
                if (!$this->maquinaEstados->esTransicionValida($mov->estadoId, 'devolver')) {
                    throw new TransicionTramiteInvalidaException("Transición inválida desde el estado actual para el expediente {$mov->id}.");
                }
            }

            $observacionesUpper = mb_strtoupper($causalLimpia, 'UTF-8');

            $procesados = 0;
            foreach ($movimientos as $mov) {
                $affected = $this->repository->desactivarMovimientoInternoEsperado($mov->movimientoId);

                if ($affected !== 1) {
                    throw new ConflictoConcurrenteTramiteException("Conflicto de concurrencia al deshabilitar movimiento previo del trámite {$mov->id}.");
                }

                $this->repository->crearMovimientoInterno([
                    'cdgo_dtos_ntrnos' => $mov->id,
                    'cdgo_estdo_trmte' => 4, // DEVUELTO
                    'cdgo_lgin' => $auditLoginId,
                    'cdgo_crgo_sa' => $contexto->idCargo,
                    'cdgo_crgo_en' => $contexto->idCargo,
                    'de_urls_agrdo' => '',
                    'fe_rcbdo' => $fecha,
                    'de_obser_ntrnos' => $observacionesUpper,
                    'in_copia' => '1',
                    'in_esta' => '1',
                ]);

                try {
                    $this->repository->registrarSeguimientoInterno(
                        $auditLoginId,
                        "Se devolvio el tramite {$mov->id}",
                        $ip
                    );
                } catch (\Throwable) {
                }

                $procesados++;
            }

            return [
                'status' => 'success',
                'message' => "Se devolvieron {$procesados} expediente(s) con éxito.",
                'procesados' => $procesados,
            ];
        });
    }

    /**
     * FLUJO 5: REITERAR TRÁMITE (segi_reiterar.php legacy)
     * Inserta nuevo movimiento con cdgo_estdo_trmte = 53 (REITERATIVO) hacia el área destino morosa.
     */
    public function ejecutarReiterar(
        array $ids,
        string $observaciones,
        string $fecha,
        array $archivos,
        ContextoUsuarioAutenticadoDto $contexto,
        string $ip
    ): array {
        if (empty($ids)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente para reiterar.');
        }
        $idsNormalizados = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($idsNormalizados)) {
            throw new InvalidArgumentException('Debe seleccionar al menos un expediente válido para reiterar.');
        }

        $auditLoginId = $contexto->getCdgoLginAudit();
        $archivosGuardados = [];
        try {
            return $this->db->transaction(function () use ($idsNormalizados, $observaciones, $fecha, $archivos, $contexto, $ip, $auditLoginId, &$archivosGuardados) {
                $movimientos = $this->repository->obtenerMovimientosVigentesParaOperacion($idsNormalizados, $contexto);

                if (count($movimientos) !== count($idsNormalizados)) {
                    throw new TramiteInternoNoEncontradoException('Uno o más expedientes del lote no fueron encontrados, pertenecen a otra compañía o no están vigentes.');
                }

                foreach ($movimientos as $mov) {
                    if (!$this->policy->reiterar($contexto, $mov)) {
                        throw new OperacionTramiteNoAutorizadaException("Operación de reiteración no autorizada para el expediente {$mov->id}.");
                    }
                    if (!$this->maquinaEstados->esTransicionValida($mov->estadoId, 'reiterar')) {
                        throw new TransicionTramiteInvalidaException("Transición inválida desde el estado actual para el expediente {$mov->id}.");
                    }
                }

                $nextSeqId = $this->obtenerSiguienteSecuencia();

                $nombreAdjunto = $this->procesarArchivos($archivos, $nextSeqId, $archivosGuardados);
                $observacionesUpper = mb_strtoupper(trim($observaciones ?: 'SIN NOVEDADES PARA REITERAR'), 'UTF-8');

                $procesados = 0;
                foreach ($movimientos as $mov) {
                    // Desactivar el movimiento anterior mediante update condicional
                    $affected = $this->repository->desactivarMovimientoInternoEsperado($mov->movimientoId);

                    if ($affected !== 1) {
                        throw new ConflictoConcurrenteTramiteException("Conflicto de concurrencia al deshabilitar movimiento previo del trámite {$mov->id}.");
                    }

                    $cargoDestino = $mov->cargoEntradaId;

                    // Insertar movimiento de reiteración (estado 53 = REITERATIVO)
                    $this->repository->crearMovimientoInterno([
                        'cdgo_dtos_ntrnos' => $mov->id,
                        'cdgo_estdo_trmte' => 53, // REITERATIVO
                        'cdgo_lgin' => $auditLoginId,
                        'cdgo_crgo_sa' => $contexto->idCargo,
                        'cdgo_crgo_en' => $cargoDestino,
                        'de_urls_agrdo' => $nombreAdjunto,
                        'fe_rcbdo' => $fecha,
                        'de_obser_ntrnos' => $observacionesUpper,
                        'in_copia' => '1',
                        'in_esta' => '1',
                    ]);

                    try {
                        $this->repository->registrarSeguimientoInterno(
                            $auditLoginId,
                            "Se reitera el tramite interno N {$mov->id}",
                            $ip
                        );
                    } catch (\Throwable) {
                    }

                    $procesados++;
                }

                return [
                    'status' => 'success',
                    'message' => "Se reiteraron {$procesados} expediente(s) con éxito.",
                    'procesados' => $procesados,
                ];
            });
        } catch (\Throwable $e) {
            foreach ($archivosGuardados as $archivoRel) {
                try {
                    $this->gestorAdjuntos->eliminar($archivoRel);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
    }

    /**
     * MÓDULO COMPLEMENTARIO 1: HOJA DE RUTA (segi_hoja_ruta.php legacy)
     * Obtiene el encabezado del trámite y todos los movimientos para la vista de impresión.
     */
    public function obtenerDatosHojaRuta(int $tramiteId): array
    {
        $tramite = $this->repository->obtenerDetalleTramiteParaHojaRuta($tramiteId);

        if (!$tramite) {
            throw new \InvalidArgumentException('Trámite no encontrado.');
        }

        $movimientos = $this->repository->obtenerMovimientosHojaRuta($tramiteId);

        return [
            'tramite' => $tramite,
            'movimientos' => $movimientos,
            'fechaImpresion' => now()->format('d/m/Y H:i:s'),
        ];
    }

    private function obtenerSiguienteSecuencia(): int
    {
        return $this->repository->obtenerSiguienteSecuenciaInterna();
    }
}