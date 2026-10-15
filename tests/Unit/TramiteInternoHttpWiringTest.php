<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Models\User;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\Services\mod_gene\mod_tram_inter\CorreoInternoTransaccionalService;
use App\Services\mod_gene\mod_tram_inter\ValidadorPagoProveedoresTipo64Service;
use App\Support\SessionKeys;
use Mockery;
use Tests\TestCase;

class TramiteInternoHttpWiringTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new User([
            'cdgo_usrios' => 100,
            'cdgo_lgin' => 'OPERADOR_TEST',
            'cdgo_cmpnia' => 1,
            'cdgo_prfle' => 2,
            'in_esta' => '1',
        ]);
        $this->user->cdgo_usrios = 100;
        $this->user->cdgo_lgin = 'OPERADOR_TEST';
        $this->user->cdgo_cmpnia = 1;
        $this->user->cdgo_prfle = 2;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson(route('mod_tram_inter.correo_interno.recepcionar'), [
            'ids' => [101],
        ]);

        $response->assertStatus(401);
    }

    public function test_recepcionar_valida_form_request_422(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 1,
                SessionKeys::USER_CARGO => 10,
                SessionKeys::USER_AREA => 5,
                'sigtram_cargo_id' => 10,
                'sigtram_area_id' => 5,
            ])
            ->postJson(route('mod_tram_inter.correo_interno.recepcionar'), [
                'ids' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_recepcionar_tipo64_incompleto_retorna_422(): void
    {
        $validadorMock = Mockery::mock(ValidadorPagoProveedoresTipo64Service::class);
        $validadorMock->shouldReceive('validarLoteParaRecepcion')
            ->once()
            ->with([200])
            ->andReturn([
                'valido' => false,
                'mensaje' => 'El trámite N° EXP-64 (Pago a Proveedores) no puede ser recepcionado porque cuenta con anexos obligatorios incompletos.',
                'incompletos' => [200],
            ]);

        $this->app->instance(ValidadorPagoProveedoresTipo64Service::class, $validadorMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 1,
                SessionKeys::USER_CARGO => 10,
                SessionKeys::USER_AREA => 5,
                'sigtram_cargo_id' => 10,
                'sigtram_area_id' => 5,
            ])
            ->postJson(route('mod_tram_inter.correo_interno.recepcionar'), [
                'ids' => [200],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'El trámite N° EXP-64 (Pago a Proveedores) no puede ser recepcionado porque cuenta con anexos obligatorios incompletos.',
            ]);
    }

    public function test_recepcionar_tipo64_servicio_caido_retorna_503_fail_closed(): void
    {
        $validadorMock = Mockery::mock(ValidadorPagoProveedoresTipo64Service::class);
        $validadorMock->shouldReceive('validarLoteParaRecepcion')
            ->once()
            ->with([200])
            ->andReturn([
                'valido' => false,
                'mensaje' => 'El servicio de verificación de Logística no está disponible temporalmente.',
                'servicio_no_disponible' => true,
            ]);

        $this->app->instance(ValidadorPagoProveedoresTipo64Service::class, $validadorMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 1,
                SessionKeys::USER_CARGO => 10,
                SessionKeys::USER_AREA => 5,
                'sigtram_cargo_id' => 10,
                'sigtram_area_id' => 5,
            ])
            ->postJson(route('mod_tram_inter.correo_interno.recepcionar'), [
                'ids' => [200],
            ]);

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_asignar_concurrencia_retorna_409(): void
    {
        $serviceMock = Mockery::mock(CorreoInternoTransaccionalService::class);
        $serviceMock->shouldReceive('ejecutarAsignar')
            ->once()
            ->andThrow(new \RuntimeException('Conflicto de concurrencia: el trámite 101 ya no se encuentra disponible.'));

        $this->app->instance(CorreoInternoTransaccionalService::class, $serviceMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 1,
                SessionKeys::USER_CARGO => 10,
                SessionKeys::USER_AREA => 5,
                'sigtram_cargo_id' => 10,
                'sigtram_area_id' => 5,
            ])
            ->postJson(route('mod_tram_inter.correo_interno.asignar'), [
                'ids' => [101],
                'id_cargo_destino' => 12,
                'observaciones' => 'Para su atención',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_asignar_error_tecnico_retorna_500_seguro_con_correlation_id(): void
    {
        $serviceMock = Mockery::mock(CorreoInternoTransaccionalService::class);
        $serviceMock->shouldReceive('ejecutarAsignar')
            ->once()
            ->andThrow(new \Exception('SQLSTATE[08006] PostgreSQL server closed connection unexpectedly.'));

        $this->app->instance(CorreoInternoTransaccionalService::class, $serviceMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 1,
                SessionKeys::USER_CARGO => 10,
                SessionKeys::USER_AREA => 5,
                'sigtram_cargo_id' => 10,
                'sigtram_area_id' => 5,
            ])
            ->postJson(route('mod_tram_inter.correo_interno.asignar'), [
                'ids' => [101],
                'id_cargo_destino' => 12,
                'observaciones' => 'Para su atención',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Error al asignar el trámite.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'correlation_id',
            ]);

        // Asegurar que NO se fuga el error de base de datos ni SQLSTATE
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        $this->assertStringNotContainsString('PostgreSQL', (string) $response->getContent());
    }

    public function test_descargar_lote_valida_form_request_422(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 1,
                SessionKeys::USER_CARGO => 10,
                SessionKeys::USER_AREA => 5,
                'sigtram_cargo_id' => 10,
                'sigtram_area_id' => 5,
            ])
            ->getJson(route('mod_tram_inter.correo_interno.descargar_lote', ['ids' => '']));

        $response->assertStatus(422);
    }
}
