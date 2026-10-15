<?php

declare(strict_types=1);

namespace App\DTOs\Core;

use RuntimeException;

/**
 * DTO canónico inmutable que representa la identidad y el contexto operativo activo
 * del usuario autenticado en SIGTRAM3.
 *
 * PROHIBIDO: Usar Auth, Request, Session, DB o Repository dentro de este DTO.
 * PROHIBIDO: Propiedades duplicadas o aliases en inglés (loginId, companiaId, etc.).
 */
final readonly class ContextoUsuarioAutenticadoDto
{
    /**
     * Identificador de login en sistema.p_lgin.cdgo_lgin.
     */
    public int $idLogin;

    /**
     * Identificador de persona/funcionario en sistema.p_usrios.cdgo_usrios.
     */
    public int $idUsuario;

    /**
     * Nombre alfanumérico de cuenta de login (sistema.p_lgin.de_lgin).
     */
    public string $nombreLogin;

    /**
     * Identificador de perfil de acceso (sistema.p_lgin.cdgo_prfle).
     */
    public int $idPerfil;

    /**
     * Identificador de compañía activa (sistema.a_cmpnias.cdgo_cmpnia).
     */
    public int $idCompania;

    /**
     * Identificador de cargo activo (sistema.p_crgos.cdgo_crgo).
     */
    public int $idCargo;

    /**
     * Identificador de área orgánica del cargo (sistema.a_areas.cdgo_area).
     */
    public int $idArea;

    /**
     * Lista de cargos asignados en sistema.r_crgos_lgin.
     * @var array<int>
     */
    public array $cargosAsignados;

    /**
     * Lista de permisos/opciones concedidas.
     * @var array<int>
     */
    public array $permisos;

    /**
     * Lista de roles concedidos.
     * @var array<int>
     */
    public array $roles;

    /**
     * Flag indicativo de área contable.
     */
    public bool $esContabilidad;

    /**
     * Denominación de la compañía activa.
     */
    public string $nombreCompania;

    /**
     * Denominación del cargo activo.
     */
    public string $nombreCargo;

    /**
     * Denominación del área activa.
     */
    public string $nombreArea;

    /**
     * @param int $idLogin Identificador de login (sistema.p_lgin.cdgo_lgin)
     * @param int $idUsuario Identificador de funcionario (sistema.p_usrios.cdgo_usrios)
     * @param string $nombreLogin Nombre alfanumérico de login (sistema.p_lgin.de_lgin)
     * @param int $idPerfil Código de perfil (sistema.p_lgin.cdgo_prfle)
     * @param int $idCompania Código de compañía activa (sistema.a_cmpnias.cdgo_cmpnia)
     * @param int $idCargo Código de cargo activo (sistema.p_crgos.cdgo_crgo)
     * @param int $idArea Código de área orgánica (sistema.a_areas.cdgo_area)
     * @param array<int> $cargosAsignados Lista de cargos autorizados en sistema.r_crgos_lgin
     * @param array<int> $permisos Lista de roles/opciones permitidas
     * @param array<int> $roles Lista de roles asignados
     * @param bool $esContabilidad Flag de área contable
     * @param string $nombreCompania Nombre de la compañía activa
     * @param string $nombreCargo Nombre del cargo activo
     * @param string $nombreArea Nombre del área activa
     */
    public function __construct(
        int $idLogin,
        int $idUsuario,
        string $nombreLogin,
        int $idPerfil,
        int $idCompania,
        int $idCargo,
        int $idArea,
        array $cargosAsignados = [],
        array $permisos = [],
        array $roles = [],
        bool $esContabilidad = false,
        string $nombreCompania = '',
        string $nombreCargo = '',
        string $nombreArea = ''
    ) {
        $this->idLogin = $idLogin;
        $this->idUsuario = $idUsuario;
        $this->nombreLogin = $nombreLogin;
        $this->idPerfil = $idPerfil;
        $this->idCompania = $idCompania;
        $this->idCargo = $idCargo;
        $this->idArea = $idArea;
        $this->cargosAsignados = $cargosAsignados;
        $this->permisos = $permisos;
        $this->roles = $roles;
        $this->esContabilidad = $esContabilidad;
        $this->nombreCompania = $nombreCompania;
        $this->nombreCargo = $nombreCargo;
        $this->nombreArea = $nombreArea;
    }

    /**
     * Retorna el identificador canónico de login (sistema.p_lgin.cdgo_lgin).
     */
    public function getCdgoLgin(): int
    {
        return $this->idLogin;
    }

    /**
     * Retorna el identificador entero real de p_lgin para auditoría legal.
     * Falla cerrado si idLogin no es un entero positivo.
     */
    public function getCdgoLginAudit(): int
    {
        if ($this->idLogin <= 0) {
            throw new RuntimeException('Identificador de login no válido para el registro de auditoría legal.');
        }

        return $this->idLogin;
    }

    /**
     * Valida si el cargo activo pertenece a los cargos autorizados para el login.
     */
    public function cargoAsignadoValido(?int $idCargo = null): bool
    {
        $cargo = $idCargo ?? $this->idCargo;
        if (empty($this->cargosAsignados)) {
            return $cargo > 0;
        }

        return in_array($cargo, $this->cargosAsignados, true);
    }

    /**
     * Valida si el usuario tiene un rol específico asignado.
     */
    public function tieneRol(int $idRol): bool
    {
        return in_array($idRol, $this->roles, true);
    }

    /**
     * Valida si el usuario tiene un permiso específico concedido.
     */
    public function tienePermiso(int $idPermiso): bool
    {
        return in_array($idPermiso, $this->permisos, true);
    }

    /**
     * Normaliza de forma segura el valor de compañía activa desde sesión o contexto.
     * Retorna un entero mayor a cero si es válido, o 0 si no existe o es inválido.
     * PROHIBIDO: Retornar fallbacks fijos (1 o 2).
     */
    public static function normalizarCompania(mixed $raw): int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : 0;
        }

        if (is_string($raw) && ctype_digit($raw)) {
            $value = (int) $raw;

            return $value > 0 ? $value : 0;
        }

        if (is_array($raw)) {
            $val = $raw['cdgo_cmpnia'] ?? 0;
            return (is_int($val) || (is_string($val) && ctype_digit((string) $val))) && (int) $val > 0 ? (int) $val : 0;
        }

        if (is_object($raw)) {
            $val = $raw->cdgo_cmpnia ?? 0;
            return (is_int($val) || (is_string($val) && ctype_digit((string) $val))) && (int) $val > 0 ? (int) $val : 0;
        }

        return 0;
    }
}
