<?php

declare(strict_types=1);

namespace App\DTOs\mod_gene;

readonly class TramiteInternoMovimientoDto
{
    public function __construct(
        public int $id,
        public ?int $movimientoId,
        public string $numero,
        public int $companiaId,
        public int $cargoSalidaId,
        public int $cargoEntradaId,
        public int $areaSalidaId,
        public int $areaEntradaId,
        public int $estadoId,
        public string $inEsta = '1',
        public string $inCopia = '1',
    ) {
    }
}