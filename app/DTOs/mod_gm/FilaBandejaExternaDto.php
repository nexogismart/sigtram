<?php

declare(strict_types=1);

namespace App\DTOs\mod_gm;

/**
 * DTO Canónico para representar una fila en las bandejas de Trámite Externo.
 * Formaliza el contrato entre repositorio, API JSON y Alpine.js (CE-02 / CE-P0-004).
 */
final class FilaBandejaExternaDto
{
    public function __construct(
        public readonly int $cdgoDtoTrmte,
        public readonly string $origDoc,
        public readonly string $nuExpeTodo,
        public readonly int $cdgoEstdoTrmte,
        public readonly string $dcEstdoTrmte,
        public readonly string $feRcbdoTrmte,
        public readonly string $deObseTrmte,
        public readonly string $noCrto,
        public readonly int $nuTiemSile = 0,
        public readonly int $nuTiemSileAdcnal = 0,
        public readonly int $diasAcumulados = 0,
        public readonly bool $hasVideo = false,
        public readonly ?int $cdgoTpoTrmte = null,
        public readonly ?string $dcTpoTrmte = null,
        public readonly ?string $deTpoTrmte = null,
        public readonly ?string $inDocuTrmte = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cdgo_dto_trmte'      => $this->cdgoDtoTrmte,
            'orig_doc'            => $this->origDoc,
            'nu_expe_todo'        => $this->nuExpeTodo,
            'cdgo_estdo_trmte'    => $this->cdgoEstdoTrmte,
            'dc_estdo_trmte'      => $this->dcEstdoTrmte,
            'fe_rcbdo_trmte'      => $this->feRcbdoTrmte,
            'de_obse_trmte'       => $this->deObseTrmte,
            'no_crto'             => $this->noCrto,
            'nu_tiem_sile'        => $this->nuTiemSile,
            'nu_tiem_sile_adcnal' => $this->nuTiemSileAdcnal,
            'dias_acumulados'     => $this->diasAcumulados,
            'has_video'           => $this->hasVideo,
            'cdgo_tpo_trmte'      => $this->cdgoTpoTrmte,
            'dc_tpo_trmte'        => $this->dcTpoTrmte,
            'de_tpo_trmte'        => $this->deTpoTrmte,
            'in_docu_trmte'       => $this->inDocuTrmte,
        ];
    }
}
