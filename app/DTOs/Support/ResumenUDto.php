<?php

declare(strict_types=1);

namespace App\DTOs\Support;

/**
 * DTO para el resumen U que acompaña cada fila del histórico (Spec 10).
 */
final class ResumenUDto
{
    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $destino
     * @param array<string, mixed> $usuariosResumen
     * @param array<string, mixed>|null $recepcionadoPor
     */
    public function __construct(
        public readonly int $movimientoId,
        public readonly array $actor,
        public readonly array $destino,
        public readonly array $usuariosResumen,
        public readonly ?array $recepcionadoPor = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'movimiento_id'    => $this->movimientoId,
            'actor'            => $this->actor,
            'destino'          => $this->destino,
            'usuarios_resumen' => $this->usuariosResumen,
            'recepcionado_por' => $this->recepcionadoPor,
        ];
    }
}
