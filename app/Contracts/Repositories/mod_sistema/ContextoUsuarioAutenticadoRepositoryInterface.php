<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\mod_sistema;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;

/**
 * Contrato de repositorio para la consulta y validación atómica del contexto institucional del usuario.
 */
interface ContextoUsuarioAutenticadoRepositoryInterface
{
    /**
     * Valida de forma atómica y estricta la asignación institucional activa
     * para la combinación dada de idLogin, idCompania e idCargo.
     */
    public function validarYObtenerContexto(int $idLogin, int $idCompania, int $idCargo): ?ContextoUsuarioAutenticadoDto;

    /**
     * Retorna todas las compañías activas asignadas a un login en sistema.r_cmpnias_lgin.
     *
     * @return array<int, array{id: int, nombre: string}>
     */
    public function obtenerCompaniasUsuario(int $idLogin): array;

    /**
     * Retorna todos los cargos activos asignados a un login en sistema.r_crgos_lgin.
     *
     * @return array<int, array{id: int, nombre: string, id_area: int, nombre_area: string, id_compania: int}>
     */
    public function obtenerCargosUsuario(int $idLogin, ?int $idCompania = null): array;
}
