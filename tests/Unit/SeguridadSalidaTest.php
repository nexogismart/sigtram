<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\mod_gm\mod_tram_exte\Concerns\ApiResponseTrait;
use Exception;
use Tests\TestCase;

class SeguridadSalidaTest extends TestCase
{
    use ApiResponseTrait;

    public function test_respuesta_segura_oculta_stack_trace_y_retorna_correlation_id(): void
    {
        $excepcionInterna = new Exception('Error interno crítico de base de datos con contraseña y path /var/www/secret');
        $respuesta = $this->respuestaSeguraError($excepcionInterna, 'No se pudo completar la operación.');

        $this->assertSame(500, $respuesta->getStatusCode());

        $contenido = json_decode((string) $respuesta->getContent(), true);

        $this->assertSame('error', $contenido['status']);
        $this->assertSame('No se pudo completar la operación.', $contenido['message']);
        $this->assertArrayHasKey('correlation_id', $contenido);
        $this->assertNotEmpty($contenido['correlation_id']);

        // Verificar que no se fugue información sensible del mensaje real de la excepción
        $this->assertStringNotContainsString('Error interno crítico', (string) $respuesta->getContent());
        $this->assertStringNotContainsString('/var/www/secret', (string) $respuesta->getContent());
    }

    public function test_respuesta_exito_cumple_contrato_json_estandar(): void
    {
        $respuesta = $this->respuestaExito('Expediente recepcionado correctamente.', ['id' => 123]);

        $this->assertSame(200, $respuesta->getStatusCode());

        $contenido = json_decode((string) $respuesta->getContent(), true);

        $this->assertSame('success', $contenido['status']);
        $this->assertSame('Expediente recepcionado correctamente.', $contenido['message']);
        $this->assertSame(['id' => 123], $contenido['data']);
    }
}
