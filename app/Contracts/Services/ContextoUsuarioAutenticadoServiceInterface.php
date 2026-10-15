<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Exceptions\ContextoInvalidoException;

/**
 * Contrato de servicio centralizado para la resolución, validación y gestión
 * del contexto institucional activo (identidad, compañía, cargo y área) del usuario.
 */
interface ContextoUsuarioAutenticadoServiceInterface
{
    /**
     * Obtiene el contexto institucional activo desde la sesión actual si es válido.
     */
    public function obtenerContextoActivo(?int $idLogin = null, ?int $idCompania = null, ?int $idCargo = null): ?ContextoUsuarioAutenticadoDto;

    /**
     * Resuelve de forma obligatoria el contexto institucional activo.
     *
     * @throws ContextoInvalidoException Si la autenticación o combinación institucional es inválida.
     */
    public function resolverContextoObligatorio(?int $idLogin = null, ?int $idCompania = null, ?int $idCargo = null): ContextoUsuarioAutenticadoDto;

    /**
     * Valida de forma estricta contra base de datos y establece el contexto operativo en la sesión.
     *
     * @throws ContextoInvalidoException Si la combinación de compañía y cargo no está asignada y activa.
     */
    public function establecerContexto(int $idCompania, int $idCargo): ContextoUsuarioAutenticadoDto;

    /**
     * Limpia completamente las claves canónicas del contexto operativo de la sesión.
     */
    public function limpiarContexto(): void;
}
