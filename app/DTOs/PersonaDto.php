<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO inmutable que representa a una Persona Natural con soporte para discrepancias.
 */
readonly class PersonaDto
{
    public string $nombreCompleto;

    public function __construct(
        public string $dni,
        public string $nombres,
        public string $apellidoPaterno,
        public string $apellidoMaterno,
        public ?string $direccion = null,
        public ?string $foto = null,
        public string $fuente = 'LOCAL',
        public ?string $fuenteDetalle = null,
        public ?int $cdgoUsrios = null,
        public bool $tieneDiscrepancia = false,
        public ?array $datosLocales = null,
        public ?array $bienes = null // SUNARP properties/vehicles
    ) {
        $this->nombreCompleto = trim("{$this->nombres} {$this->apellidoPaterno} {$this->apellidoMaterno}");
    }

    /**
     * Retorna una nueva instancia con los bienes de SUNARP complementados.
     */
    public function withBienes(array $bienes): self
    {
        return new self(
            dni: $this->dni,
            nombres: $this->nombres,
            apellidoPaterno: $this->apellidoPaterno,
            apellidoMaterno: $this->apellidoMaterno,
            direccion: $this->direccion,
            foto: $this->foto,
            fuente: $this->fuente,
            fuenteDetalle: $this->fuenteDetalle,
            cdgoUsrios: $this->cdgoUsrios,
            tieneDiscrepancia: $this->tieneDiscrepancia,
            datosLocales: $this->datosLocales,
            bienes: $bienes
        );
    }

    /**
     * Convierte el DTO a un array asociativo.
     */
    public function toArray(): array
    {
        return [
            'dni' => $this->dni,
            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellidoPaterno,
            'apellido_materno' => $this->apellidoMaterno,
            'nombre_completo' => $this->nombreCompleto,
            'direccion' => $this->direccion,
            'foto' => $this->foto,
            'fuente' => $this->fuente,
            'fuente_detalle' => $this->fuenteDetalle,
            'cdgo_usrios' => $this->cdgoUsrios,
            'tiene_discrepancia' => $this->tieneDiscrepancia,
            'datos_locales' => $this->datosLocales,
            'bienes' => $this->bienes,
        ];
    }
}
