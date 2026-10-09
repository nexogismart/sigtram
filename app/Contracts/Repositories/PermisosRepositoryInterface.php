<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

/**
 * Interface PermisosRepositoryInterface
 *
 * Define el contrato para el acceso a datos relacionados con los permisos
 * del sistema, unificando permisos base y excepciones.
 */
interface PermisosRepositoryInterface
{
    /**
     * Obtiene los permisos combinados (base y excepciones individuales) de un usuario.
     *
     * @param string $cdgoLgin Código de login del usuario.
     * @param int $cdgoCargo ID de cargo seleccionado.
     * @param int $cdgoPerfl ID del perfil del usuario.
     * @return array Matriz de permisos indexada por código de rol/módulo.
     */
    public function obtenerPermisosUsuario(string $cdgoLgin, int $cdgoCargo, int $cdgoPerfl): array;

    /**
     * Invalida la caché de permisos persistidos del usuario.
     *
     * @param string $cdgoLgin Código de login del usuario.
     * @return void
     */
    public function invalidarCache(string $cdgoLgin): void;
}
