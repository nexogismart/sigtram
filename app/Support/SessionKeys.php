<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Constantes semánticas de todas las claves de sesión del sistema SIGTRAM3.
 *
 * USO CORRECTO:   session(SessionKeys::COMPANIA_ID), session(SessionKeys::CARGO_ID)
 * USO PROHIBIDO:  session('usComp') o session('sigtram_cargo_id') dispersos
 *
 * Regla: CODE-06 y CI-03
 */
final class SessionKeys
{
    // ── Identidad canónica ───────────────────────────────────────────────────
    public const LOGIN_ID        = 'usId';         // ID canónico de login (sistema.p_lgin.cdgo_lgin)
    public const USUARIO_ID      = 'usCdgoUsrios'; // ID canónico de persona/usuario (sistema.p_usrios.cdgo_usrios)
    public const LOGIN_NOMBRE    = 'usLoginNombre';// Nombre alfanumérico de login (sistema.p_lgin.de_lgin)
    public const USER_NAME       = 'usNombre';     // Nombre completo del funcionario
    public const USER_DNI        = 'usDni';        // DNI del funcionario
    public const PERFIL_ID       = 'usPerfilId';   // ID de perfil de acceso (sistema.p_lgin.cdgo_prfle)

    // ── Contexto institucional activo ────────────────────────────────────────
    public const COMPANIA_ID     = 'usComp';               // ID entero positivo de compañía activa (sistema.a_cmpnias.cdgo_cmpnia)
    public const COMPANIA_NOMBRE = 'usNomComp';            // Denominación/razón social activa
    public const CARGO_ID        = 'sigtram_cargo_id';     // ID entero positivo de cargo activo (sistema.p_crgos.cdgo_crgo)
    public const CARGO_NOMBRE    = 'sigtram_cargo_nombre'; // Denominación del cargo activo
    public const AREA_ID         = 'sigtram_area_id';      // ID entero positivo de área activa (sistema.a_areas.cdgo_area)
    public const AREA_NOMBRE     = 'sigtram_area_nombre';  // Denominación del área activa

    // ── Aliases de compatibilidad transitoria ────────────────────────────────
    public const USER_COMPANY    = self::COMPANIA_ID;
    public const USER_CARGO      = self::CARGO_ID;
    public const USER_AREA       = self::AREA_ID;
    public const USER_ID         = 'usId';         // Clave legacy donde se almacenaba cdgo_lgin
    public const USER_LOGIN      = self::LOGIN_ID;

    // ── Caché de permisos ────────────────────────────────────────────────────
    public const PERM_CACHE_PREFIX = 'user_perms_'; // + $cdgoLgin

    // Previene instanciación — esta clase solo tiene constantes
    private function __construct() {}

    /**
     * Genera la clave de caché de permisos para un usuario específico.
     * Uso: Cache::remember(SessionKeys::permKey('jperez'), 3600, fn() => ...)
     */
    public static function permKey(string $cdgoLgin): string
    {
        return self::PERM_CACHE_PREFIX . $cdgoLgin;
    }
}
