<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PublicTrackingToken;
use RuntimeException;
use Tests\TestCase;

class PublicTrackingTokenTest extends TestCase
{
    private string $originalQrKey;
    private string $originalAppKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalQrKey = (string) config('services.qr.key');
        $this->originalAppKey = (string) config('app.key');
    }

    protected function tearDown(): void
    {
        config(['services.qr.key' => $this->originalQrKey]);
        config(['app.key' => $this->originalAppKey]);
        parent::tearDown();
    }

    /**
     * Test: QR_KEY válida genera token seguro y decodifica correctamente el ID de expediente.
     */
    public function test_qr_key_valida_genera_y_valida_token(): void
    {
        $validKey = 'base64:' . base64_encode(random_bytes(32));
        config(['services.qr.key' => $validKey]);

        $expedienteId = 829986;
        $token = PublicTrackingToken::generate($expedienteId);

        $this->assertNotEmpty($token);
        $this->assertSame($expedienteId, PublicTrackingToken::decode($token));
    }

    /**
     * Test: QR_KEY ausente o vacía falla de forma controlada con RuntimeException y no usa fallback.
     */
    public function test_qr_key_ausente_falla_de_forma_controlada_sin_fallback(): void
    {
        config(['services.qr.key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuración de clave de seguimiento QR no disponible.');
        PublicTrackingToken::generate(12345);
    }

    /**
     * Test: QR_KEY en blanco falla de forma controlada.
     */
    public function test_qr_key_vacia_falla_de_forma_controlada(): void
    {
        config(['services.qr.key' => '   ']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuración de clave de seguimiento QR no disponible.');
        PublicTrackingToken::getSigningKey();
    }

    /**
     * Test: QR_KEY con prefijo base64: pero contenido inválido falla de forma controlada.
     */
    public function test_qr_key_con_base64_invalido_falla_de_forma_controlada(): void
    {
        config(['services.qr.key' => 'base64:@@@not_a_valid_base64@@@']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuración de clave de seguimiento QR inválida.');
        PublicTrackingToken::getSigningKey();
    }

    /**
     * Test: QR_KEY con longitud menor a 32 bytes reales falla de forma controlada.
     */
    public function test_qr_key_demasiado_corta_falla_de_forma_controlada(): void
    {
        // 16 bytes codificados en base64
        $shortKey = 'base64:' . base64_encode(random_bytes(16));
        config(['services.qr.key' => $shortKey]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Longitud de clave de seguimiento QR insuficiente.');
        PublicTrackingToken::getSigningKey();
    }

    /**
     * Test: Cambiar APP_KEY manteniendo QR_KEY no invalida los tokens existentes (independencia total).
     */
    public function test_cambiar_app_key_manteniendo_qr_key_continua_validando(): void
    {
        $qrKey = 'base64:' . base64_encode(random_bytes(32));
        config(['services.qr.key' => $qrKey]);

        // Clave APP_KEY inicial
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        $expedienteId = 554433;
        $token = PublicTrackingToken::generate($expedienteId);
        $this->assertSame($expedienteId, PublicTrackingToken::decode($token));

        // Rotación de APP_KEY (simulando cambio de clave de sesión/cifrado de la aplicación)
        config(['app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        // El token QR debe seguir siendo 100% válido porque QR_KEY no cambió
        $this->assertSame($expedienteId, PublicTrackingToken::decode($token));
    }

    /**
     * Test: Cambiar QR_KEY invalida inmediatamente los tokens generados con la clave anterior.
     */
    public function test_cambiar_qr_key_invalida_token_anterior(): void
    {
        $qrKey1 = 'base64:' . base64_encode(random_bytes(32));
        $qrKey2 = 'base64:' . base64_encode(random_bytes(32));

        config(['services.qr.key' => $qrKey1]);
        $expedienteId = 778899;
        $tokenKey1 = PublicTrackingToken::generate($expedienteId);
        $this->assertSame($expedienteId, PublicTrackingToken::decode($tokenKey1));

        // Rotar QR_KEY
        config(['services.qr.key' => $qrKey2]);

        // Con la nueva QR_KEY, el token generado con la anterior debe ser rechazado
        $this->assertNull(PublicTrackingToken::decode($tokenKey1));
    }

    /**
     * Test: Token adulterado (un solo caracter modificado) es rechazado retornando null.
     */
    public function test_token_adulterado_retorna_null(): void
    {
        $qrKey = 'base64:' . base64_encode(random_bytes(32));
        config(['services.qr.key' => $qrKey]);

        $token = PublicTrackingToken::generate(123456);

        // Alterar el último caracter del token
        $lastChar = substr($token, -1);
        $replacement = ($lastChar === 'A') ? 'B' : 'A';
        $tamperedToken = substr($token, 0, -1) . $replacement;

        $this->assertNull(PublicTrackingToken::decode($tamperedToken));
    }

    /**
     * Test: ID menor o igual a cero no es permitido en generate() y decode().
     */
    public function test_id_invalido_menor_o_igual_a_cero(): void
    {
        $qrKey = 'base64:' . base64_encode(random_bytes(32));
        config(['services.qr.key' => $qrKey]);

        $this->expectException(\InvalidArgumentException::class);
        PublicTrackingToken::generate(0);
    }
}
