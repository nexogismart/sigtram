<?php

declare(strict_types=1);

namespace App\DTOs\Support;

/**
 * DTO para participante en la columna/modal U (Spec 10).
 * No incluye datos sensibles (DNI, correo personal, teléfono, dirección).
 */
final class ParticipanteUDto
{
    public function __construct(
        public readonly string $login,
        public readonly string $nombre,
        public readonly string $cargo,
        public readonly string $area,
        public readonly string $estadoAsignacion = 'Activo',
        public readonly ?string $perfil = null,
        public readonly ?int $loginId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'login'             => $this->login,
            'login_id'          => $this->loginId,
            'nombre'            => $this->nombre,
            'cargo'             => $this->cargo,
            'area'              => $this->area,
            'estado_asignacion' => $this->estadoAsignacion,
            'perfil'            => $this->perfil,
        ];
    }
}
