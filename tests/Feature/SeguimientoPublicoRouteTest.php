<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Support\PublicTrackingToken;
use Mockery;
use Tests\TestCase;

class SeguimientoPublicoRouteTest extends TestCase
{
    /**
     * Test ruta publica con token valido retorna HTTP 200 y renderiza vista publica.
     */
    public function test_ruta_publica_con_token_valido_retorna_200(): void
    {
        $token = PublicTrackingToken::generate(12345);

        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockRepo->shouldReceive('obtenerSeguimientoPublico')
            ->once()
            ->with(12345)
            ->andReturn([
                'expediente_id' => 12345,
                'numero_expediente' => 'E-12345-2026',
                'fecha_ingreso' => '14/10/2026 10:00',
                'asunto' => 'Constancia de Posesión',
                'solicitante' => 'C*** D***',
                'tipo_tramite' => 'TRAMITE EXTERNO',
                'estado_actual' => 'REGISTRADO',
                'area_actual' => 'MESA DE PARTES',
                'compania_nombre' => 'MUNICIPALIDAD DISTRITAL',
                'movimientos' => [],
            ]);

        $this->app->instance(IngresoExpedienteRepositoryInterface::class, $mockRepo);

        $response = $this->get('/tramite/seguimiento/' . $token);

        $response->assertStatus(200);
        $response->assertViewIs('publico.seguimiento');
        $response->assertSee('E-12345-2026');
        $response->assertSee('MESA DE PARTES');
    }

    /**
     * Test ruta publica aplica throttling con limite de 30 solicitudes por minuto y retorna 429.
     */
    public function test_ruta_publica_aplica_throttling_con_limite_de_30_solicitudes_por_minuto(): void
    {
        $token = PublicTrackingToken::generate(12345);

        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockRepo->shouldReceive('obtenerSeguimientoPublico')
            ->andReturn([
                'expediente_id' => 12345,
                'numero_expediente' => 'E-12345-2026',
                'fecha_ingreso' => '14/10/2026 10:00',
                'asunto' => 'Constancia',
                'solicitante' => 'C*** D***',
                'tipo_tramite' => 'TRAMITE EXTERNO',
                'estado_actual' => 'REGISTRADO',
                'area_actual' => 'MESA DE PARTES',
                'compania_nombre' => 'MUNICIPALIDAD DISTRITAL',
                'movimientos' => [],
            ]);
        $this->app->instance(IngresoExpedienteRepositoryInterface::class, $mockRepo);

        // Ejecutar hasta el límite de 30
        for ($i = 1; $i <= 30; $i++) {
            $resp = $this->get('/tramite/seguimiento/' . $token);
            $resp->assertStatus(200);
        }

        // La solicitud 31 debe ser bloqueada con HTTP 429 Too Many Requests
        $resp31 = $this->get('/tramite/seguimiento/' . $token);
        $resp31->assertStatus(429);
    }

    /**
     * Test ruta publica con token adulterado o invalido retorna HTTP 404 uniforme.
     */
    public function test_ruta_publica_con_token_invalido_retorna_404(): void
    {
        $tamperedToken = 'invalid.tampered.token';

        $response = $this->get('/tramite/seguimiento/' . $tamperedToken);

        $response->assertStatus(404);
    }
}


