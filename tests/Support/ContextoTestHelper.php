<?php

declare(strict_types=1);

namespace Tests\Support;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;

trait ContextoTestHelper
{
    /**
     * Helper canónico para instanciar ContextoUsuarioAutenticadoDto en pruebas unitarias y de integración.
     * Soporta firma legacy con loginId en 7mo parámetro o permisos en 7mo y loginId en 9no.
     */
    protected function crearContexto(
        string $login = 'jperez',
        int $usuarioId = 10,
        int $companiaId = 1,
        int $cargoId = 105,
        int $areaId = 20,
        array $rolesOCargos = [],
        int|array $permisosOrLoginId = [],
        bool $esContabilidad = false,
        int $loginId = 0,
        array $roles = [],
        array $cargosAsignados = [],
        array $permisos = [],
        int $idLogin = 0,
        string $nombreLogin = '',
        int $idUsuario = 0,
        int $idCompania = 0,
        int $idCargo = 0,
        int $idArea = 0
    ): ContextoUsuarioAutenticadoDto {
        $actualLogin = $nombreLogin !== '' ? $nombreLogin : $login;
        $actualUsuarioId = $idUsuario > 0 ? $idUsuario : $usuarioId;
        $actualCompaniaId = $idCompania > 0 ? $idCompania : $companiaId;
        $actualCargoId = $idCargo > 0 ? $idCargo : $cargoId;
        $actualAreaId = $idArea > 0 ? $idArea : $areaId;

        $actualPermisos = !empty($permisos)
            ? $permisos
            : (is_array($permisosOrLoginId) ? $permisosOrLoginId : []);

        $actualLoginId = $idLogin > 0
            ? $idLogin
            : ($loginId > 0
                ? $loginId
                : (is_int($permisosOrLoginId)
                    ? $permisosOrLoginId
                    : (is_numeric($actualLogin) && (int) $actualLogin > 0 ? (int) $actualLogin : $actualUsuarioId)));

        $actualRoles = !empty($roles) ? $roles : $rolesOCargos;
        $actualCargos = !empty($cargosAsignados)
            ? $cargosAsignados
            : (!empty($rolesOCargos) ? $rolesOCargos : ($actualCargoId > 0 ? [$actualCargoId] : []));

        return new ContextoUsuarioAutenticadoDto(
            idLogin: $actualLoginId,
            idUsuario: $actualUsuarioId,
            nombreLogin: $actualLogin,
            idPerfil: $actualRoles[0] ?? 1,
            idCompania: $actualCompaniaId,
            idCargo: $actualCargoId,
            idArea: $actualAreaId,
            cargosAsignados: $actualCargos,
            permisos: $actualPermisos,
            roles: $actualRoles,
            esContabilidad: $esContabilidad,
            nombreCompania: 'MUNICIPALIDAD',
            nombreCargo: 'Cargo Test',
            nombreArea: 'Área Test'
        );
    }
}
