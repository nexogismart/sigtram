<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Token firmado, verificable y no falsificable sin QR_KEY para la consulta pública de trámites externos.
 * Utiliza HMAC SHA-256 permanente para garantizar la autenticidad e integridad del identificador.
 */
class PublicTrackingToken
{
    /**
     * Resuelve y valida la clave criptográfica para firma de QR desde config('services.qr.key').
     *
     * @throws RuntimeException Si la clave está ausente, malformada o tiene menos de 32 bytes reales.
     */
    public static function getSigningKey(): string
    {
        $rawKey = config('services.qr.key');

        if (!is_string($rawKey) || trim($rawKey) === '') {
            Log::error('Falta la configuración de QR_KEY en config/services.php o la clave está vacía.');
            throw new RuntimeException('Configuración de clave de seguimiento QR no disponible.');
        }

        $trimmed = trim($rawKey);

        if (str_starts_with($trimmed, 'base64:')) {
            $base64Part = substr($trimmed, 7);
            $decoded = base64_decode($base64Part, true);

            if ($decoded === false) {
                Log::error('La clave QR_KEY no es un Base64 válido.');
                throw new RuntimeException('Configuración de clave de seguimiento QR inválida.');
            }

            if (strlen($decoded) < 32) {
                Log::error('La clave QR_KEY decodificada tiene una longitud insuficiente (menor a 32 bytes).');
                throw new RuntimeException('Longitud de clave de seguimiento QR insuficiente.');
            }

            return $decoded;
        }

        if (strlen($trimmed) < 32) {
            Log::error('La clave QR_KEY tiene una longitud insuficiente (menor a 32 bytes).');
            throw new RuntimeException('Longitud de clave de seguimiento QR insuficiente.');
        }

        return $trimmed;
    }

    /**
     * Genera un token público URL-safe a partir del ID de expediente y la clave QR_KEY.
     *
     * @throws \InvalidArgumentException Si el ID de expediente no es positivo.
     * @throws RuntimeException Si la configuración de QR_KEY no es válida.
     */
    public static function generate(int $expedienteId): string
    {
        if ($expedienteId <= 0) {
            throw new \InvalidArgumentException('El identificador de expediente debe ser un entero positivo.');
        }

        $key = self::getSigningKey();
        $hash = hash_hmac('sha256', "seguimiento_publico:{$expedienteId}", $key);
        $payload = "{$expedienteId}:" . substr($hash, 0, 32);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * Decodifica y verifica el token público. Retorna el ID del expediente si es válido, o null si está alterado.
     *
     * @throws RuntimeException Si la configuración de QR_KEY no es válida.
     */
    public static function decode(string $token): ?int
    {
        $normalized = strtr($token, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }

        $parts = explode(':', $decoded, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return null;
        }

        $id = (int) $parts[0];
        if ($id <= 0) {
            return null;
        }

        $providedHash = $parts[1];
        $key = self::getSigningKey();
        $expectedHash = substr(hash_hmac('sha256', "seguimiento_publico:{$id}", $key), 0, 32);

        if (!hash_equals($expectedHash, $providedHash)) {
            return null;
        }

        return $id;
    }
}
