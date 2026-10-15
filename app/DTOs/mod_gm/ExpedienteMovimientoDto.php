<?php

declare(strict_types=1);

namespace App\DTOs\mod_gm;

/**
 * DTO inmutable que representa un expediente y su movimiento actual.
 * Utilizado como frontera entre el repositorio y las políticas/servicios.
 */
final class ExpedienteMovimientoDto
{
    /**
     * @param int $expedienteId ID principal del expediente (cdgo_dto_trmte)
     * @param string $numeroExpediente Número formal del trámite
     * @param int $movimientoId ID del movimiento documental (cdgo_trmte_dcmnto)
     * @param int $companiaId ID de la compañía dueña del trámite
     * @param int $cargoSalidaId ID del cargo emisor (cdgo_crgo_sa)
     * @param int $cargoEntradaId ID del cargo receptor (cdgo_crgo_en)
     * @param int $areaSalidaId ID del área de salida
     * @param int $areaEntradaId ID del área de entrada
     * @param int $estadoId Código numérico de estado actual
     * @param string $inEsta Indicador de vigencia ('1' o '0')
     * @param string|null $fechaMovimiento Fecha del movimiento
     * @param string|null $observacion Observación del movimiento
     */
    public function __construct(
        public readonly int $expedienteId,
        public readonly string $numeroExpediente,
        public readonly int $movimientoId,
        public readonly int $companiaId,
        public readonly int $cargoSalidaId,
        public readonly int $cargoEntradaId,
        public readonly int $areaSalidaId,
        public readonly int $areaEntradaId,
        public readonly int $estadoId,
        public readonly string $inEsta = '1',
        public readonly ?string $fechaMovimiento = null,
        public readonly ?string $observacion = null
    ) {}

    /**
     * Genera un token de snapshot opaco para concurrencia optimista y prevención de edición sobre estados obsoletos.
     */
    public function snapshotToken(): string
    {
        return hash('sha256', "{$this->expedienteId}:{$this->movimientoId}:{$this->estadoId}:{$this->inEsta}");
    }
}
