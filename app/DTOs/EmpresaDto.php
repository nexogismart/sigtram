<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO inmutable que representa a una Persona Jurídica (Empresa) con soporte para discrepancias.
 */
readonly class EmpresaDto
{
    public function __construct(
        public string $ruc,
        public string $razonSocial,
        public ?string $direccion = null,
        public ?string $estado = null,
        public ?string $condicion = null,
        public string $fuente = 'LOCAL',
        public ?string $fuenteDetalle = null,
        public ?int $cdgoUsrios = null,
        public bool $tieneDiscrepancia = false,
        public ?array $datosLocales = null,
        public ?array $representantesLegales = null // SUNARP representatives list
    ) {}

    /**
     * Retorna una nueva instancia con los representantes de SUNARP complementados.
     */
    public function withRepresentantesLegales(array $representantesLegales): self
    {
        return new self(
            ruc: $this->ruc,
            razonSocial: $this->razonSocial,
            direccion: $this->direccion,
            estado: $this->estado,
            condicion: $this->condicion,
            fuente: $this->fuente,
            fuenteDetalle: $this->fuenteDetalle,
            cdgoUsrios: $this->cdgoUsrios,
            tieneDiscrepancia: $this->tieneDiscrepancia,
            datosLocales: $this->datosLocales,
            representantesLegales: $representantesLegales
        );
    }

    /**
     * Convierte el DTO a un array asociativo.
     */
    public function toArray(): array
    {
        return [
            'ruc' => $this->ruc,
            'razon_social' => $this->razonSocial,
            'direccion' => $this->direccion,
            'estado' => $this->estado,
            'condicion' => $this->condicion,
            'fuente' => $this->fuente,
            'fuente_detalle' => $this->fuenteDetalle,
            'cdgo_usrios' => $this->cdgoUsrios,
            'tiene_discrepancia' => $this->tieneDiscrepancia,
            'datos_locales' => $this->datosLocales,
            'representantes_legales' => $this->representantesLegales,
        ];
    }
}
