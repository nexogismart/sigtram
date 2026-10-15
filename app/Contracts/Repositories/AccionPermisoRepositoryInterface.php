<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

/**
 * Interface AccionPermisoRepositoryInterface
 *
 * Contrato para verificar la autorización granular de acciones del sistema
 * consultando las tablas sistema.a_acciones y sistema.r_acciones_usrios.
 */
interface AccionPermisoRepositoryInterface
{
    /**
     * Valida si un usuario autenticado tiene permitido ejecutar una acción específica
     * dentro del ámbito de la compañía activa.
     *
     * @param int $cdgoLgin Identificador canónico de login (sistema.p_lgin.cdgo_lgin)
     * @param int $cdgoCmpnia Identificador de compañía activa
     * @param string $codigoAccion Código canónico de la acción (sistema.a_acciones.de_codigo)
     * @return bool True únicamente si la acción y asignación están activas y permitidas
     */
    public function tieneAccionPermitida(int $cdgoLgin, int $cdgoCmpnia, string $codigoAccion): bool;
}
