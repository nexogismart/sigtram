<?php

declare(strict_types=1);

namespace App\DTOs\mod_gm;

/**
 * DTO inmutable con la información del documento interno asociado a un movimiento externo.
 * Cumple paridad con el flujo Legacy de "Enviar con trámite interno asociado".
 */
final class DocumentoInternoAsociadoDto
{
    public function __construct(
        public readonly int $documentoInternoId,
        public readonly int $movimientoInternoId,
        public readonly string $numeroFormateado,
        public readonly int $tipoDocumentalId,
        public readonly string $tipoDocumentalNombre,
        public readonly ?string $rutaAdjunto = null
    ) {}
}
