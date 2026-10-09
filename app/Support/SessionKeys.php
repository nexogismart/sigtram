<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Constantes de todas las claves de sesión del sistema SIGTRAM3.
 *
 * USO CORRECTO:   session(SessionKeys::USER_CARGO)
 * USO PROHIBIDO:  session('usIdsCargo')   ← strings literales dispersos
 *
 * Regla: CODE-06 en GEMINI.md
 */
final class SessionKeys
{
    // ── Identidad del usuario ────────────────────────────────────────────────
    public const USER_ID       = 'usId';        // ID interno del usuario (p_lgin.cdgo_lgin)
    public const USER_LOGIN    = 'usCdgoLgin';  // Código de login (string)
    public const USER_NAME     = 'usNombre';    // Nombre completo
    public const USER_DNI      = 'usDni';       // DNI del funcionario

    // ── Contexto activo (cargo + área + empresa) ─────────────────────────────
    public const USER_CARGO    = 'usIdsCargo';  // ID(s) de cargo activo
    public const USER_AREA     = 'usIdsArea';   // ID(s) de área activa
    public const USER_COMPANY  = 'usComp';      // Datos de la empresa/municipalidad activa

    // ── Caché de permisos ────────────────────────────────────────────────────
    // Uso: Cache::remember(SessionKeys::permKey($cdgoLgin), ...)
    // No se guarda en sesión, se guarda en Cache. Definida aquí como prefijo de clave.
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
