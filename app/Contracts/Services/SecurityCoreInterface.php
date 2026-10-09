<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Support\Collection;

/**
 * Interface SecurityCoreInterface
 * 
 * Contrato para el servicio central de seguridad (SecurityCore).
 * Define las operaciones de obtención de cargos, verificación de permisos,
 * generación del menú y manejo de la caché de permisos.
 */
interface SecurityCoreInterface
{
    /**
     * Obtiene los cargos asignados a un usuario desde la base de datos.
     *
     * @param int $loginId ID de login del usuario.
     * @return Collection Colección de cargos activos.
     */
    public function obtenerCargosUsuario(int $loginId): Collection;

    /**
     * Verifica si un usuario con un cargo determinado tiene permiso para acceder a una ruta o módulo.
     * Implementa la lógica híbrida de permisos base y excepciones con caché.
     *
     * @param int $loginId ID de login del usuario.
     * @param string $moduloORuta Nombre del módulo o la ruta/URL a verificar.
     * @param int|null $cargoId ID del cargo activo seleccionado.
     * @param string $accion Acción a verificar ('ver', 'crea', 'edit', 'elim').
     * @return bool True si tiene permiso, false en caso contrario.
     */
    public function tienePermiso(int $loginId, string $moduloORuta, ?int $cargoId = null, string $accion = 'ver'): bool;

    /**
     * Obtiene el menú de opciones estructurado en formato jerárquico para el usuario.
     *
     * @param int $loginId ID de login del usuario.
     * @param int $cargoId ID del cargo activo.
     * @return array Estructura de menú compatible con el diseño visual del panel.
     */
    public function obtenerMenuUsuario(int $loginId, int $cargoId): array;

    /**
     * Limpia la caché de permisos persistida del usuario.
     *
     * @param string $cdgoLgin Código de login del usuario.
     * @return void
     */
    public function limpiarCachePermisos(string $cdgoLgin): void;

    /**
     * Obtiene la opción de menú (a_rles) cuya de_dreccon coincide con la ruta.
     *
     * @param string $ruta Ruta solicitada (con o sin .php / .blade.php).
     * @return object|null Fila de a_rles o null si no existe.
     */
    public function obtenerOpcionPorRuta(string $ruta): ?object;
}
