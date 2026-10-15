<?php

declare(strict_types=1);

namespace App\Services\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\Contracts\Services\NotificationCoreInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Enums\mod_gm\EstadoTramiteExterno;
use App\Policies\mod_gm\TramiteExternoPolicy;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Servicio de Dominio para Trámite Externo.
 * Orquesta todas las transiciones, validaciones de seguridad, autorización por recurso
 * e idempotencia del módulo, manteniendo controladores delgados (ARCH-01/02).
 */
class TramiteExternoService
{
    public function __construct(
        private readonly TramiteExternoRepositoryInterface $repository,
        private readonly MaquinaEstadosTramiteExterno $maquinaEstados,
        private readonly TramiteExternoPolicy $policy,
        private readonly IdempotenciaService $idempotencia,
        private readonly DatabaseManager $db,
        private readonly DocumentoInternoDesdeExpedienteExternoService $documentoInternoService,
        private readonly ?GestorAdjuntosInterface $gestorAdjuntos = null,
        private readonly ?NotificationCoreInterface $notificationCore = null
    ) {}

    /**
     * Recepciona uno o varios expedientes destinados al cargo activo.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<int> $movimientoIds
     * @param string $fecha
     * @param string|null $observaciones
     * @param string $claveIdempotente
     * @return array{procesados: int, mensaje: string}
     */
    public function recepcionar(
        ContextoUsuarioAutenticadoDto $contexto,
        array $movimientoIds,
        string $fecha,
        ?string $observaciones,
        string $claveIdempotente
    ): array {
        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }

        $claveScoped = "{$loginId}:{$contexto->idCompania}:recepcionar:{$claveIdempotente}";
        $payloadHash = md5(json_encode([$movimientoIds, $fecha, $observaciones]));

        return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use ($contexto, $movimientoIds, $fecha, $observaciones, $loginId) {
            $procesados = 0;
            $this->db->transaction(function () use ($contexto, $movimientoIds, $fecha, $observaciones, $loginId, &$procesados) {
                $movimientos = $this->obtenerMovimientosParaOperacion($movimientoIds, $contexto->idCompania);
                if (empty($movimientos)) {
                    throw new InvalidArgumentException('No se encontraron expedientes válidos para recepcionar.');
                }

                foreach ($movimientos as $mov) {
                    if (!$this->policy->recepcionar($contexto, $mov)) {
                        throw new AccessDeniedHttpException("Acceso denegado: el expediente {$mov->numeroExpediente} no puede ser recepcionado por su cargo actual.");
                    }
                    $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'recepcionar');

                    // Cerrar movimiento actual con validación estricta de concurrencia (CEF-P0-02)
                    $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                    if ($afectados !== 1) {
                        throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                    }

                    // Crear movimiento de recepción
                    $this->repository->crearMovimientoExterno([
                        'cdgo_dto_trmte'    => $mov->expedienteId,
                        'cdgo_crgo_sa'      => $contexto->idCargo,
                        'cdgo_crgo_en'      => $contexto->idCargo,
                        'fe_rcbdo_trmte'    => $fecha,
                        'cdgo_estdo_trmte'  => EstadoTramiteExterno::RECIBIDO->value,
                        'in_esta'           => '1',
                        'de_obse'           => $observaciones ?? 'Expediente recepcionado.',
                        'cdgo_lgin'         => $loginId,
                    ]);
                    $procesados++;
                }
            });

            return [
                'procesados' => $procesados,
                'mensaje'    => "Se recepcionaron {$procesados} expediente(s) correctamente.",
            ];
        });
    }

    /**
     * Deriva expedientes recibidos hacia un área/cargo destino.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<int> $expedienteIds
     * @param int $cargoDestinoId
     * @param string $fecha
     * @param string|null $observaciones
     * @param string $claveIdempotente
     * @param int $areaDestinoId
     * @param UploadedFile|null $adjunto
     * @param int|null $docTipo
     * @param string|null $tramiteNro
     * @param int|null $folios
     * @return array{procesados: int, mensaje: string}
     */
    public function derivar(
        ContextoUsuarioAutenticadoDto $contexto,
        array $movimientoIds,
        int $cargoDestinoId,
        string $fecha,
        ?string $observaciones,
        string $claveIdempotente,
        int $areaDestinoId = 0,
        ?UploadedFile $adjunto = null,
        ?int $docTipo = null,
        ?string $tramiteNro = null,
        ?int $folios = null
    ): array {
        if ($cargoDestinoId <= 0 && $areaDestinoId > 0) {
            $cargoDestinoId = $this->resolverCargoParaArea($areaDestinoId);
        }

        if ($cargoDestinoId <= 0) {
            throw new InvalidArgumentException('Debe especificar un cargo de destino válido.');
        }

        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }

        $claveScoped = "{$loginId}:{$contexto->idCompania}:derivar:{$claveIdempotente}";
        $payloadHash = md5(json_encode([$movimientoIds, $cargoDestinoId, $areaDestinoId, $fecha, $observaciones, $docTipo, $tramiteNro, $folios]));

        return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use (
            $contexto,
            $movimientoIds,
            $cargoDestinoId,
            $fecha,
            $observaciones,
            $adjunto,
            $docTipo,
            $tramiteNro,
            $folios,
            $loginId
        ) {
            $nombreAdjunto = null;
            if ($adjunto !== null && $this->gestorAdjuntos !== null) {
                $dtoAdjunto = $this->gestorAdjuntos->guardar($adjunto, 'tramites/externos');
                $nombreAdjunto = $dtoAdjunto->rutaRelativa;
            }

            $obsTexto = $observaciones ?? 'Expediente derivado.';
            if ($docTipo !== null && !empty($tramiteNro)) {
                $obsTexto .= " [Doc: {$tramiteNro} - Folios: " . ($folios ?? 0) . "]";
            }

            try {
                $procesados = 0;
                $this->db->transaction(function () use (
                    $movimientoIds,
                    $contexto,
                    $cargoDestinoId,
                    $fecha,
                    $obsTexto,
                    $nombreAdjunto,
                    $docTipo,
                    $observaciones,
                    $folios,
                    $adjunto,
                    $loginId,
                    &$procesados
                ) {
                    $movimientos = $this->obtenerMovimientosParaOperacion($movimientoIds, $contexto->idCompania);
                    if (empty($movimientos)) {
                        throw new InvalidArgumentException('No se encontraron expedientes válidos para derivar.');
                    }

                    foreach ($movimientos as $mov) {
                        if (!$this->policy->derivar($contexto, $mov)) {
                            throw new AccessDeniedHttpException("Acceso denegado: el expediente {$mov->numeroExpediente} no puede ser derivado.");
                        }
                        $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'derivar');

                        $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                        if ($afectados !== 1) {
                            throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                        }

                        $docInternoId = null;
                        if ($docTipo !== null && $docTipo > 0) {
                            $dtoDoc = $this->documentoInternoService->generarDocumentoInterno(
                                contexto: $contexto,
                                expedienteId: $mov->expedienteId,
                                numeroExpediente: $mov->numeroExpediente,
                                docTipo: $docTipo,
                                fecha: $fecha,
                                observaciones: $observaciones,
                                folios: $folios,
                                adjuntoPdf: $adjunto
                            );
                            $docInternoId = $dtoDoc->documentoInternoId;
                        }

                        $this->repository->crearMovimientoExterno([
                            'cdgo_dto_trmte'    => $mov->expedienteId,
                            'cdgo_crgo_sa'      => $contexto->idCargo,
                            'cdgo_crgo_en'      => $cargoDestinoId,
                            'fe_rcbdo_trmte'    => $fecha,
                            'cdgo_estdo_trmte'  => EstadoTramiteExterno::PENDIENTE->value,
                            'in_esta'           => '1',
                            'de_obse'           => $obsTexto,
                            'cdgo_lgin'         => $loginId,
                            'cdgo_dtos_ntrnos'  => $docInternoId,
                            'de_urls_imag'      => $nombreAdjunto,
                        ]);
                        $procesados++;
                    }
                });

                return [
                    'procesados' => $procesados,
                    'mensaje'    => "Se derivaron {$procesados} expediente(s) con éxito.",
                ];
            } catch (\Throwable $e) {
                if ($nombreAdjunto !== null) {
                    try {
                        $this->gestorAdjuntos->eliminar($nombreAdjunto);
                    } catch (\Throwable) {
                    }
                }
                throw $e;
            }
        });
    }

    /**
     * Asigna expedientes a un funcionario interno de la misma área.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<int> $expedienteIds
     * @param int $cargoInternoId
     * @param string $fecha
     * @param string|null $observaciones
     * @param string $claveIdempotente
     * @return array{procesados: int, mensaje: string}
     */
    /**
     * Resuelve el cargo receptor para un área institucional asegurando vigencia y correspondencia.
     * Resuelve CEF-P0-03 y CEF-04: nunca convierte ID de área en ID de cargo.
     */
    public function resolverCargoParaArea(int $areaId): int
    {
        $cargo = $this->repository->obtenerCargoPrincipalDeArea($areaId);

        if (!$cargo || !isset($cargo->cdgo_crgo) || (int) $cargo->cdgo_crgo <= 0) {
            throw new InvalidArgumentException("El área {$areaId} no posee un cargo activo válido.");
        }

        return (int) $cargo->cdgo_crgo;
    }

    /**
     * Asigna expedientes a un funcionario interno de la misma área.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<int> $expedienteIds
     * @param int $cargoInternoId
     * @param string $fecha
     * @param string|null $observaciones
     * @param string $claveIdempotente
     * @param int|null $docTipo
     * @param string|null $tramiteNro
     * @param int|null $folios
     * @param UploadedFile|null $adjunto
     * @return array{procesados: int, mensaje: string}
     */
    public function asignar(
        ContextoUsuarioAutenticadoDto $contexto,
        array $movimientoIds,
        int $cargoInternoId,
        string $fecha,
        ?string $observaciones,
        string $claveIdempotente,
        ?int $docTipo = null,
        ?string $tramiteNro = null,
        ?int $folios = null,
        ?UploadedFile $adjunto = null
    ): array {
        $cargoValido = $this->repository->validarCargoEnArea($cargoInternoId, $contexto->idArea);

        if (!$cargoValido) {
            throw new InvalidArgumentException("El cargo destino {$cargoInternoId} no pertenece al área activa o no está vigente.");
        }

        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }

        $nombreAdjunto = null;
        if ($adjunto !== null && $this->gestorAdjuntos !== null) {
            $dtoAdjunto = $this->gestorAdjuntos->guardar($adjunto, 'tramites/externos');
            $nombreAdjunto = $dtoAdjunto->rutaRelativa;
        }

        try {
            $obsTexto = $observaciones ?? 'Expediente asignado a cargo interno.';
            if ($docTipo !== null && !empty($tramiteNro)) {
                $obsTexto .= " [Doc: {$tramiteNro} - Folios: " . ($folios ?? 0) . "]";
            }

            $claveScoped = "{$loginId}:{$contexto->idCompania}:asignar:{$claveIdempotente}";
            $payloadHash = md5(json_encode([$movimientoIds, $cargoInternoId, $fecha, $observaciones, $docTipo, $tramiteNro, $folios]));

            return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use (
                $contexto,
                $movimientoIds,
                $cargoInternoId,
                $fecha,
                $obsTexto,
                $nombreAdjunto,
                $docTipo,
                $observaciones,
                $folios,
                $adjunto,
                $loginId
            ) {
                $procesados = 0;
                $this->db->transaction(function () use (
                    $contexto,
                    $movimientoIds,
                    $cargoInternoId,
                    $fecha,
                    $obsTexto,
                    $nombreAdjunto,
                    $docTipo,
                    $observaciones,
                    $folios,
                    $adjunto,
                    $loginId,
                    &$procesados
                ) {
                    $movimientos = $this->obtenerMovimientosParaOperacion($movimientoIds, $contexto->idCompania);
                    if (empty($movimientos)) {
                        throw new InvalidArgumentException('No se encontraron expedientes válidos para asignar.');
                    }

                    foreach ($movimientos as $mov) {
                        if (!$this->policy->asignar($contexto, $mov)) {
                            throw new AccessDeniedHttpException("Acceso denegado: el expediente {$mov->numeroExpediente} no puede ser asignado.");
                        }
                        $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'asignar');

                        $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                        if ($afectados !== 1) {
                            throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                        }

                        $docInternoId = null;
                        if ($docTipo !== null && $docTipo > 0) {
                            $dtoDoc = $this->documentoInternoService->generarDocumentoInterno(
                                contexto: $contexto,
                                expedienteId: $mov->expedienteId,
                                numeroExpediente: $mov->numeroExpediente,
                                docTipo: $docTipo,
                                fecha: $fecha,
                                observaciones: $observaciones,
                                folios: $folios,
                                adjuntoPdf: $adjunto
                            );
                            $docInternoId = $dtoDoc->documentoInternoId;
                        }

                        $this->repository->crearMovimientoExterno([
                            'cdgo_dto_trmte'    => $mov->expedienteId,
                            'cdgo_crgo_sa'      => $contexto->idCargo,
                            'cdgo_crgo_en'      => $cargoInternoId,
                            'fe_rcbdo_trmte'    => $fecha,
                            'cdgo_estdo_trmte'  => EstadoTramiteExterno::ASIGNADO->value,
                            'in_esta'           => '1',
                            'de_obse'           => $obsTexto,
                            'cdgo_lgin'         => $loginId,
                            'cdgo_dtos_ntrnos'  => $docInternoId,
                            'de_urls_imag'      => $nombreAdjunto,
                        ]);
                        $procesados++;
                    }
                });

                return [
                    'procesados' => $procesados,
                    'mensaje'    => "Se asignaron {$procesados} expediente(s) internamente.",
                ];
            });
        } catch (\Throwable $e) {
            if ($nombreAdjunto !== null) {
                try {
                    $this->gestorAdjuntos->eliminar($nombreAdjunto);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
    }

    /**
     * Envía expedientes a múltiples áreas (Envío Multi-Área).
     * Resuelve CE-P1-003, CE-P1-004 y CE-07.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<int> $expedienteIds
     * @param array<int> $areasDestinoIds
     * @param string $fecha
     * @param string|null $observaciones
     * @param int|null $docTipo
     * @param string|null $tramiteNro
     * @param int|null $folios
     * @param UploadedFile|null $adjunto
     * @param string $claveIdempotente
     * @return array{procesados: int, total_destinos: int, mensaje: string}
     */
    public function enviar(
        ContextoUsuarioAutenticadoDto $contexto,
        array $movimientoIds,
        array $areasDestinoIds,
        string $fecha,
        ?string $observaciones,
        ?int $docTipo,
        ?string $tramiteNro,
        ?int $folios,
        ?UploadedFile $adjunto,
        string $claveIdempotente
    ): array {
        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }

        $claveScoped = "{$loginId}:{$contexto->idCompania}:enviar:{$claveIdempotente}";
        $payloadHash = md5(json_encode([$movimientoIds, $areasDestinoIds, $fecha, $observaciones, $docTipo, $tramiteNro, $folios]));

        return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use (
            $contexto,
            $movimientoIds,
            $areasDestinoIds,
            $fecha,
            $observaciones,
            $docTipo,
            $tramiteNro,
            $folios,
            $adjunto,
            $loginId
        ) {
            $idsUnicos = array_values(array_unique(array_filter($movimientoIds, fn($id) => $id > 0)));
            $areasUnicas = array_values(array_unique(array_filter($areasDestinoIds, fn($id) => $id > 0)));

            if (empty($idsUnicos)) {
                throw new InvalidArgumentException('Debe seleccionar al menos un expediente.');
            }
            if (empty($areasUnicas)) {
                throw new InvalidArgumentException('Debe seleccionar al menos un área de destino.');
            }

            $cargosPorArea = [];
            foreach ($areasUnicas as $areaId) {
                $cargosPorArea[$areaId] = $this->resolverCargoParaArea($areaId);
            }

            $nombreAdjunto = null;
            if ($adjunto !== null && $this->gestorAdjuntos !== null) {
                $dtoAdjunto = $this->gestorAdjuntos->guardar($adjunto, 'tramites/externos');
                $nombreAdjunto = $dtoAdjunto->rutaRelativa;
            }

            try {
                $obsTexto = $observaciones ?? 'Envío multi-área.';
                if ($docTipo !== null && !empty($tramiteNro)) {
                    $obsTexto .= " [Doc: {$tramiteNro} - Folios: " . ($folios ?? 0) . "]";
                }

                $procesados = 0;
                $totalDestinos = 0;

                $this->db->transaction(function () use (
                    $idsUnicos,
                    $areasUnicas,
                    $cargosPorArea,
                    $contexto,
                    $fecha,
                    $obsTexto,
                    $nombreAdjunto,
                    $docTipo,
                    $observaciones,
                    $folios,
                    $adjunto,
                    $loginId,
                    &$procesados,
                    &$totalDestinos
                ) {
                    $movimientos = $this->obtenerMovimientosParaOperacion($idsUnicos, $contexto->idCompania);
                    if (empty($movimientos)) {
                        throw new InvalidArgumentException('No se encontraron expedientes válidos para enviar.');
                    }

                    foreach ($movimientos as $mov) {
                        if (!$this->policy->enviar($contexto, $mov)) {
                            throw new AccessDeniedHttpException("Acceso denegado: el expediente {$mov->numeroExpediente} no puede ser enviado.");
                        }
                        $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'enviar');

                        $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                        if ($afectados !== 1) {
                            throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                        }

                        $docInternoId = null;
                        if ($docTipo !== null && $docTipo > 0) {
                            $dtoDoc = $this->documentoInternoService->generarDocumentoInterno(
                                contexto: $contexto,
                                expedienteId: $mov->expedienteId,
                                numeroExpediente: $mov->numeroExpediente,
                                docTipo: $docTipo,
                                fecha: $fecha,
                                observaciones: $observaciones,
                                folios: $folios,
                                adjuntoPdf: $adjunto
                            );
                            $docInternoId = $dtoDoc->documentoInternoId;
                        }

                        foreach ($areasUnicas as $areaId) {
                            $cargoDestinoId = $cargosPorArea[$areaId];
                            $this->repository->crearMovimientoExterno([
                                'cdgo_dto_trmte'    => $mov->expedienteId,
                                'cdgo_crgo_sa'      => $contexto->idCargo,
                                'cdgo_crgo_en'      => $cargoDestinoId,
                                'fe_rcbdo_trmte'    => $fecha,
                                'cdgo_estdo_trmte'  => EstadoTramiteExterno::ENVIADO_MULTIAREA->value,
                                'in_esta'           => '1',
                                'de_obse'           => $obsTexto,
                                'cdgo_lgin'         => $loginId,
                                'cdgo_dtos_ntrnos'  => $docInternoId,
                                'de_urls_imag'      => $nombreAdjunto,
                            ]);
                            $totalDestinos++;
                        }
                        $procesados++;
                    }
                });

                return [
                    'procesados'     => $procesados,
                    'total_destinos' => $totalDestinos,
                    'mensaje'        => "Se enviaron {$procesados} expediente(s) a {$totalDestinos} destino(s) con éxito.",
                ];
            } catch (\Throwable $e) {
                if ($nombreAdjunto !== null) {
                    try {
                        $this->gestorAdjuntos->eliminar($nombreAdjunto);
                    } catch (\Throwable) {
                    }
                }
                throw $e;
            }
        });
    }

    /**
     * Finaliza expedientes en custodia.
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<int> $expedienteIds
     * @param string $fecha
     * @param string $observaciones
     * @param string|null $correoNotificar
     * @param string $claveIdempotente
     * @param int $enviarCorreo
     * @param int|null $docTipo
     * @param string|null $tramiteNro
     * @param int|null $folios
     * @param UploadedFile|null $adjunto
     * @return array{procesados: int, mensaje: string}
     */
    public function finalizar(
        ContextoUsuarioAutenticadoDto $contexto,
        array $movimientoIds,
        string $fecha,
        string $observaciones,
        ?string $correoNotificar,
        string $claveIdempotente,
        int $enviarCorreo = 0,
        ?int $docTipo = null,
        ?string $tramiteNro = null,
        ?int $folios = null,
        ?UploadedFile $adjunto = null
    ): array {
        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }

        $idsUnicos = array_values(array_unique(array_filter(array_map('intval', $movimientoIds), fn ($id) => $id > 0)));
        if (empty($idsUnicos)) {
            throw new InvalidArgumentException('No se proporcionaron identificadores válidos para finalizar.');
        }

        $nombreAdjunto = null;
        if ($adjunto !== null && $this->gestorAdjuntos !== null) {
            $dtoAdjunto = $this->gestorAdjuntos->guardar($adjunto, 'tramites/externos');
            $nombreAdjunto = $dtoAdjunto->rutaRelativa;
        }

        try {
            $obsTexto = $observaciones;
            if ($docTipo !== null && !empty($tramiteNro)) {
                $obsTexto .= " [Doc vinculado: {$tramiteNro} - Folios: " . ($folios ?? 0) . "]";
            }

            $claveScoped = "{$loginId}:{$contexto->idCompania}:finalizar:{$claveIdempotente}";
            $payloadHash = md5(json_encode([$idsUnicos, $fecha, $observaciones, $docTipo, $tramiteNro, $folios]));

            return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use (
                $contexto,
                $idsUnicos,
                $fecha,
                $obsTexto,
                $correoNotificar,
                $enviarCorreo,
                $nombreAdjunto,
                $docTipo,
                $observaciones,
                $folios,
                $adjunto,
                $loginId
            ) {
                $procesados = 0;
                $expedientesProcesadosIds = [];
                $this->db->transaction(function () use (
                    $idsUnicos,
                    $contexto,
                    $fecha,
                    $obsTexto,
                    $nombreAdjunto,
                    $docTipo,
                    $observaciones,
                    $folios,
                    $adjunto,
                    $loginId,
                    &$procesados,
                    &$expedientesProcesadosIds
                ) {
                    $movimientos = $this->obtenerMovimientosParaOperacion($idsUnicos, $contexto->idCompania);
                    if (empty($movimientos)) {
                        throw new InvalidArgumentException('No se encontraron expedientes válidos para finalizar.');
                    }

                    foreach ($movimientos as $mov) {
                        if (!$this->policy->finalizar($contexto, $mov)) {
                            throw new AccessDeniedHttpException("Acceso denegado: el expediente {$mov->numeroExpediente} no puede ser finalizado.");
                        }
                        $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'finalizar');

                        $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                        if ($afectados !== 1) {
                            throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                        }

                        $docInternoId = null;
                        if ($docTipo !== null && $docTipo > 0) {
                            $dtoDoc = $this->documentoInternoService->generarDocumentoInterno(
                                contexto: $contexto,
                                expedienteId: $mov->expedienteId,
                                numeroExpediente: $mov->numeroExpediente,
                                docTipo: $docTipo,
                                fecha: $fecha,
                                observaciones: $observaciones,
                                folios: $folios,
                                adjuntoPdf: $adjunto
                            );
                            $docInternoId = $dtoDoc->documentoInternoId;
                        }

                        // Insertar estado 3 (FINALIZADO)
                        $this->repository->crearMovimientoExterno([
                            'cdgo_dto_trmte'    => $mov->expedienteId,
                            'cdgo_crgo_sa'      => $contexto->idCargo,
                            'cdgo_crgo_en'      => $contexto->idCargo,
                            'fe_rcbdo_trmte'    => $fecha,
                            'cdgo_estdo_trmte'  => EstadoTramiteExterno::FINALIZADO->value,
                            'in_esta'           => '1',
                            'de_obse'           => $obsTexto,
                            'cdgo_lgin'         => $loginId,
                            'cdgo_dtos_ntrnos'  => $docInternoId,
                            'de_urls_imag'      => $nombreAdjunto,
                        ]);
                        $procesados++;
                        $expedientesProcesadosIds[] = $mov->expedienteId;
                    }
                });

                // Notificación desacoplada after-commit (CE-P1-005, CEF-P0-06 / CEF-06)
                if ($enviarCorreo > 0 || !empty($correoNotificar)) {
                    foreach ($expedientesProcesadosIds as $expId) {
                        $destEmail = $correoNotificar;
                        if (empty($destEmail) || !filter_var($destEmail, FILTER_VALIDATE_EMAIL)) {
                            $contacto = $this->repository->obtenerContactoNotificacionExpediente($expId, $contexto->idCompania);

                            $destEmail = null;
                            if (is_array($contacto)) {
                                $correoH = !empty($contacto['correo_h']) ? trim((string) $contacto['correo_h']) : '';
                                $correoU = !empty($contacto['correo_u']) ? trim((string) $contacto['correo_u']) : '';
                                $destEmail = ($correoH !== '') ? $correoH : (($correoU !== '') ? $correoU : null);
                            }
                        }

                        if (!empty($destEmail) && filter_var($destEmail, FILTER_VALIDATE_EMAIL)) {
                            try {
                                if ($this->notificationCore !== null) {
                                    $this->notificationCore->enviarCorreo(
                                        $destEmail,
                                        'Expediente Finalizado - Trámite Externo',
                                        'emails.expediente_finalizado',
                                        ['expedientes' => [$expId], 'observaciones' => $obsTexto],
                                        true
                                    );
                                } else {
                                    Log::info("Notificación de finalización programada para {$destEmail} en expediente: {$expId}");
                                }
                            } catch (\Throwable $e) {
                                Log::warning("Fallo al despachar notificación para {$destEmail}: " . $e->getMessage());
                            }
                        }
                    }
                }

                return [
                    'procesados' => $procesados,
                    'mensaje'    => "Se finalizaron {$procesados} expediente(s) con éxito.",
                ];
            }, $payloadHash);
        } catch (\Throwable $e) {
            if ($nombreAdjunto !== null) {
                try {
                    $this->gestorAdjuntos->eliminar($nombreAdjunto);
                } catch (\Throwable) {
                }
            }
            throw $e;
        }
    }

    /**
     * Devuelve/regresa un expediente enviado que sigue pendiente en destino (TE-015).
     *
     * @param ContextoUsuarioAutenticadoDto $contexto
     * @param array<int> $movimientoIds
     * @param string $motivo
     * @param string $fecha
     * @param string $claveIdempotente
     * @return array{procesados: int, mensaje: string}
     */
    public function devolver(
        ContextoUsuarioAutenticadoDto $contexto,
        array $movimientoIds,
        string $motivo,
        string $fecha,
        string $claveIdempotente
    ): array {
        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }

        $idsUnicos = array_values(array_unique(array_filter(array_map('intval', $movimientoIds), fn ($id) => $id > 0)));
        if (empty($idsUnicos)) {
            throw new InvalidArgumentException('No se proporcionaron identificadores válidos para devolver.');
        }

        $claveScoped = "{$loginId}:{$contexto->idCompania}:devolver:{$claveIdempotente}";
        $payloadHash = md5(json_encode([$idsUnicos, $motivo, $fecha]));

        return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use ($contexto, $idsUnicos, $motivo, $fecha, $loginId) {
            $procesados = 0;
            $this->db->transaction(function () use ($idsUnicos, $contexto, $motivo, $fecha, $loginId, &$procesados) {
                $movimientos = $this->obtenerMovimientosParaOperacion($idsUnicos, $contexto->idCompania);
                if (empty($movimientos)) {
                    throw new InvalidArgumentException('No se encontraron expedientes válidos para devolver.');
                }

                foreach ($movimientos as $mov) {
                    if (!$this->policy->devolver($contexto, $mov)) {
                        throw new AccessDeniedHttpException("Acceso denegado: el expediente {$mov->numeroExpediente} no puede ser devuelto.");
                    }
                    $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'devolver');

                    $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                    if ($afectados !== 1) {
                        throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                    }

                    // Crear movimiento con estado 4 (DEVUELTO) regresando la custodia al emisor
                    $this->repository->crearMovimientoExterno([
                        'cdgo_dto_trmte'    => $mov->expedienteId,
                        'cdgo_crgo_sa'      => $mov->cargoEntradaId,
                        'cdgo_crgo_en'      => $contexto->idCargo,
                        'fe_rcbdo_trmte'    => $fecha,
                        'cdgo_estdo_trmte'  => EstadoTramiteExterno::DEVUELTO->value,
                        'in_esta'           => '1',
                        'de_obse'           => "Devuelto por emisor: {$motivo}",
                        'cdgo_lgin'         => $loginId,
                    ]);
                    $procesados++;
                }
            });

            return [
                'procesados' => $procesados,
                'mensaje'    => "Se devolvieron {$procesados} expediente(s) a su bandeja.",
            ];
        }, $payloadHash);
    }

    /**
     * Archiva expedientes finalizados (TE-016, TE-026).
     */
    public function archivar(
        ContextoUsuarioAutenticadoDto $contexto,
        array $movimientoIds,
        string $ubicacion,
        ?string $observaciones,
        string $claveIdempotente
    ): array {
        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }

        $idsUnicos = array_values(array_unique(array_filter(array_map('intval', $movimientoIds), fn ($id) => $id > 0)));
        if (empty($idsUnicos)) {
            throw new InvalidArgumentException('No se encontraron expedientes para archivar.');
        }

        $claveScoped = "{$loginId}:{$contexto->idCompania}:archivar:{$claveIdempotente}";
        $payloadHash = md5(json_encode([$idsUnicos, $ubicacion, $observaciones]));

        return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use ($contexto, $idsUnicos, $ubicacion, $observaciones, $loginId) {
            $procesados = 0;
            $this->db->transaction(function () use ($idsUnicos, $contexto, $ubicacion, $observaciones, $loginId, &$procesados) {
                $movimientos = $this->obtenerMovimientosParaOperacion($idsUnicos, $contexto->idCompania);
                if (empty($movimientos)) {
                    throw new InvalidArgumentException('No se encontraron expedientes para archivar.');
                }

                foreach ($movimientos as $mov) {
                    if (!$this->policy->archivar($contexto, $mov)) {
                        throw new AccessDeniedHttpException("Acceso denegado: el expediente {$mov->numeroExpediente} no puede ser archivado.");
                    }
                    $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'archivar');

                    $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                    if ($afectados !== 1) {
                        throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                    }

                    $this->repository->crearMovimientoExterno([
                        'cdgo_dto_trmte'    => $mov->expedienteId,
                        'cdgo_crgo_sa'      => $contexto->idCargo,
                        'cdgo_crgo_en'      => $contexto->idCargo,
                        'fe_rcbdo_trmte'    => date('Y-m-d H:i:s'),
                        'cdgo_estdo_trmte'  => EstadoTramiteExterno::ARCHIVADO->value,
                        'in_esta'           => '1',
                        'de_obse'           => "Archivado en [{$ubicacion}]. " . ($observaciones ?? ''),
                        'cdgo_lgin'         => $loginId,
                    ]);
                    $procesados++;
                }
            });

            return [
                'procesados' => $procesados,
                'mensaje'    => "Se archivaron {$procesados} expediente(s) correctamente.",
            ];
        }, $payloadHash);
    }

    /**
     * Presta un expediente archivado (TE-025).
     */
    public function prestar(
        ContextoUsuarioAutenticadoDto $contexto,
        int $movimientoId,
        string $prestatario,
        string $finalidad,
        string $fechaSalida,
        ?string $fechaRetorno,
        string $claveIdempotente
    ): array {
        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }
        if ($movimientoId <= 0) {
            throw new InvalidArgumentException('Identificador de movimiento inválido.');
        }

        $claveScoped = "{$loginId}:{$contexto->idCompania}:prestar:{$claveIdempotente}";
        $payloadHash = md5(json_encode([$movimientoId, $prestatario, $finalidad, $fechaSalida, $fechaRetorno]));

        return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use ($contexto, $movimientoId, $prestatario, $finalidad, $fechaSalida, $fechaRetorno, $loginId) {
            $this->db->transaction(function () use ($movimientoId, $contexto, $prestatario, $finalidad, $fechaSalida, $fechaRetorno, $loginId) {
                $movimientos = $this->obtenerMovimientosParaOperacion([$movimientoId], $contexto->idCompania);
                if (empty($movimientos)) {
                    throw new InvalidArgumentException('Expediente no encontrado.');
                }
                $mov = $movimientos[0];

                if (!$this->policy->prestar($contexto, $mov)) {
                    throw new AccessDeniedHttpException('Acceso denegado: el expediente no se encuentra en estado ARCHIVADO.');
                }

                $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'prestar');

                $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                if ($afectados !== 1) {
                    throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                }

                $obs = "Prestado a {$prestatario}. Finalidad: {$finalidad}. Retorno previsto: " . ($fechaRetorno ?? 'No fijado');
                $this->repository->crearMovimientoExterno([
                    'cdgo_dto_trmte'    => $mov->expedienteId,
                    'cdgo_crgo_sa'      => $contexto->idCargo,
                    'cdgo_crgo_en'      => $contexto->idCargo,
                    'fe_rcbdo_trmte'    => $fechaSalida,
                    'cdgo_estdo_trmte'  => EstadoTramiteExterno::PRESTADO->value,
                    'in_esta'           => '1',
                    'de_obse'           => $obs,
                    'cdgo_lgin'         => $loginId,
                ]);
            });

            return ['mensaje' => 'Expediente prestado correctamente.'];
        }, $payloadHash);
    }

    /**
     * Retorna un expediente prestado al archivo.
     */
    public function retornarPrestamo(
        ContextoUsuarioAutenticadoDto $contexto,
        int $movimientoId,
        string $observaciones,
        string $claveIdempotente
    ): array {
        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }
        if ($movimientoId <= 0) {
            throw new InvalidArgumentException('Identificador de movimiento inválido.');
        }

        $claveScoped = "{$loginId}:{$contexto->idCompania}:retornar_prestamo:{$claveIdempotente}";
        $payloadHash = md5(json_encode([$movimientoId, $observaciones]));

        return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use ($contexto, $movimientoId, $observaciones, $loginId) {
            $this->db->transaction(function () use ($movimientoId, $contexto, $observaciones, $loginId) {
                $movimientos = $this->obtenerMovimientosParaOperacion([$movimientoId], $contexto->idCompania);
                if (empty($movimientos) || $movimientos[0]->estadoId !== EstadoTramiteExterno::PRESTADO->value) {
                    throw new InvalidArgumentException('El expediente no se encuentra prestado.');
                }
                $mov = $movimientos[0];

                if (!$this->policy->retornarPrestamo($contexto, $mov)) {
                    throw new AccessDeniedHttpException('Acceso denegado: el expediente no se encuentra en custodia o estado permitido para retornar préstamo.');
                }

                $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'retornar_prestamo');

                $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                if ($afectados !== 1) {
                    throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                }

                $this->repository->crearMovimientoExterno([
                    'cdgo_dto_trmte'    => $mov->expedienteId,
                    'cdgo_crgo_sa'      => $contexto->idCargo,
                    'cdgo_crgo_en'      => $contexto->idCargo,
                    'fe_rcbdo_trmte'    => date('Y-m-d H:i:s'),
                    'cdgo_estdo_trmte'  => EstadoTramiteExterno::ARCHIVADO->value,
                    'in_esta'           => '1',
                    'de_obse'           => "Retorno de préstamo: {$observaciones}",
                    'cdgo_lgin'         => $loginId,
                ]);
            });

            return ['mensaje' => 'Expediente retornado al archivo con éxito.'];
        }, $payloadHash);
    }

    /**
     * Obtiene los movimientos vigentes para una operación transaccional,
     * invocando exclusivamente el repositorio bloqueante canónico.
     *
     * @param array<int> $movimientoIds
     * @param int $companiaId
     * @return array<ExpedienteMovimientoDto>
     */
    private function obtenerMovimientosParaOperacion(array $movimientoIds, int $companiaId): array
    {
        $uniqueIds = array_values(array_unique(array_filter(array_map('intval', $movimientoIds), fn ($id) => $id > 0)));
        if (empty($uniqueIds)) {
            return [];
        }

        $movs = $this->repository->obtenerMovimientosVigentesParaBloqueo($uniqueIds, $companiaId);
        if (empty($movs)) {
            return [];
        }

        if (count($movs) !== count($uniqueIds)) {
            throw new RuntimeException('Conflicto de concurrencia: uno o más expedientes ya no se encuentran vigentes o disponibles para la operación solicitada.');
        }

        return $movs;
    }

    /**
     * Redirige un expediente registrando el cambio como nuevo movimiento histórico (TE-044).
     */
    public function redirigir(
        ContextoUsuarioAutenticadoDto $contexto,
        int $movimientoId,
        int $nuevoCargoDestinoId,
        string $motivo,
        string $claveIdempotente
    ): array {
        $loginId = $contexto->getCdgoLgin();
        if ($loginId <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de login no resuelto.');
        }
        if ($contexto->idCompania <= 0) {
            throw new InvalidArgumentException('Contexto de funcionario inválido: identificador de compañía no resuelto.');
        }
        if ($movimientoId <= 0) {
            throw new InvalidArgumentException('Identificador de movimiento inválido.');
        }

        $claveScoped = "{$loginId}:{$contexto->idCompania}:redirigir:{$claveIdempotente}";
        $payloadHash = md5(json_encode([$movimientoId, $nuevoCargoDestinoId, $motivo]));

        return $this->idempotencia->ejecutarIdempotente($claveScoped, 60, function () use ($contexto, $movimientoId, $nuevoCargoDestinoId, $motivo, $loginId) {
            $this->db->transaction(function () use ($movimientoId, $contexto, $nuevoCargoDestinoId, $motivo, $loginId) {
                $movimientos = $this->obtenerMovimientosParaOperacion([$movimientoId], $contexto->idCompania);
                if (empty($movimientos)) {
                    throw new InvalidArgumentException('Expediente no encontrado.');
                }
                $mov = $movimientos[0];

                if (!$this->policy->redirigir($contexto, $mov)) {
                    throw new AccessDeniedHttpException('Acceso denegado: se requieren privilegios administrativos para redirigir.');
                }

                $this->maquinaEstados->resolverEstadoDestino($mov->estadoId, 'redirigir');

                $afectados = $this->repository->desactivarMovimientoExternoEsperado($mov->movimientoId);

                if ($afectados !== 1) {
                    throw new RuntimeException("Conflicto de concurrencia: el movimiento {$mov->movimientoId} fue modificado concurrentemente.");
                }

                $this->repository->crearMovimientoExterno([
                    'cdgo_dto_trmte'    => $mov->expedienteId,
                    'cdgo_crgo_sa'      => $contexto->idCargo,
                    'cdgo_crgo_en'      => $nuevoCargoDestinoId,
                    'fe_rcbdo_trmte'    => date('Y-m-d H:i:s'),
                    'cdgo_estdo_trmte'  => EstadoTramiteExterno::REDIRIGIDO->value,
                    'in_esta'           => '1',
                    'de_obse'           => "Redirigido: {$motivo}",
                    'cdgo_lgin'         => $loginId,
                ]);
            });

            return ['mensaje' => 'Expediente redirigido exitosamente.'];
        }, $payloadHash);
    }
}
