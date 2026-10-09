<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Migración gradual de contraseñas SHA1 → bcrypt en sistema.p_lgin.de_pass.
 *
 * ESTADO: NO USADO AÚN. El login y el alta/cambio de clave siguen solo con SHA1.
 * Cuando se active: AuthController / PasswordController / UsuarioController.
 *
 * - Lee SHA1 (legado) y bcrypt ($2y$/$2a$/$2b$).
 * - Tras un login correcto con SHA1, reescribe el hash a bcrypt.
 * - Cuando todos los usuarios estén en bcrypt, se puede retirar isSha1().
 *
 * Requisito BD: de_pass debe admitir al menos 60 caracteres (bcrypt).
 */
final class SigtramPassword
{
    public static function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    public static function isBcrypt(?string $stored): bool
    {
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return (bool) preg_match('/^\$2[ayb]\$\d{2}\$.{53}$/', $stored);
    }

    public static function isSha1(?string $stored): bool
    {
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return (bool) preg_match('/^[0-9a-f]{40}$/i', $stored);
    }

    /**
     * @return array{ok: bool, needs_rehash: bool}
     */
    public static function check(string $plain, ?string $stored): array
    {
        if (!is_string($stored) || $stored === '' || $plain === '') {
            return ['ok' => false, 'needs_rehash' => false];
        }

        if (self::isBcrypt($stored)) {
            $ok = Hash::check($plain, $stored);

            return [
                'ok' => $ok,
                'needs_rehash' => $ok && Hash::needsRehash($stored),
            ];
        }

        if (self::isSha1($stored)) {
            $ok = hash_equals(strtolower($stored), sha1($plain));

            return [
                'ok' => $ok,
                'needs_rehash' => $ok,
            ];
        }

        // Hash desconocido: intentar bcrypt por si el formato es ligeramente distinto.
        if (str_starts_with($stored, '$2')) {
            $ok = Hash::check($plain, $stored);

            return [
                'ok' => $ok,
                'needs_rehash' => $ok && Hash::needsRehash($stored),
            ];
        }

        return ['ok' => false, 'needs_rehash' => false];
    }
}
