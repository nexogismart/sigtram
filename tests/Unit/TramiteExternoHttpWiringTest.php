<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Models\User;
use App\Services\mod_gm\mod_tram_exte\TramiteExternoService;
use App\Support\SessionKeys;
use Mockery;
use Tests\TestCase;

class TramiteExternoHttpWiringTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Idempotency-Key', 'test-http-wiring-001');

        $this->user = new User([
            'cdgo_usrios' => 200,
            'cdgo_lgin' => 2001,
            'de_lgin' => 'EXT_OPERADOR',
            'cdgo_cmpnia' => 2,
            'cdgo_prfle' => 3,
            'in_esta' => '1',
        ]);
        $this->user->cdgo_usrios = 200;
        $this->user->cdgo_lgin = 2001;
        $this->user->de_lgin = 'EXT_OPERADOR';
        $this->user->cdgo_cmpnia = 2;
        $this->user->cdgo_prfle = 3;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_externo_unauthenticated_returns_401(): void
    {
        $response = $this->postJson(route('mod_gm.segi_expedi.recepcionar'), [
            'ids' => [501],
            'fecha' => '2026-10-11 12:00:00',
        ]);

        $response->assertStatus(401);
    }

    public function test_externo_recepcionar_valida_form_request_422(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_CARGO => 20,
                SessionKeys::USER_AREA => 8,
                'sigtram_cargo_id' => 20,
                'sigtram_area_id' => 8,
            ])
            ->postJson(route('mod_gm.segi_expedi.recepcionar'), [
                'ids' => [],
                'fecha' => '2026-10-11 12:00:00',
            ]);

        $response->assertStatus(422);
    }

    public function test_externo_recepcionar_concurrencia_retorna_409(): void
    {
        $serviceMock = Mockery::mock(TramiteExternoService::class);
        $serviceMock->shouldReceive('recepcionar')
            ->once()
            ->andThrow(new \RuntimeException('Conflicto de concurrencia: el expediente 501 ya fue procesado por otra operación.'));

        $this->app->instance(TramiteExternoService::class, $serviceMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_CARGO => 20,
                SessionKeys::USER_AREA => 8,
                SessionKeys::USER_COMPANY => 2,
                'sigtram_cargo_id' => 20,
                'sigtram_area_id' => 8,
            ])
            ->postJson(route('mod_gm.segi_expedi.recepcionar'), [
                'ids' => [501],
                'fecha' => '2026-10-11 12:00:00',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'status' => 'error',
                'message' => 'Conflicto de concurrencia: el expediente ya fue procesado o modificado por otra operación.',
            ]);
    }

    public function test_externo_derivar_error_tecnico_retorna_500_seguro_con_correlation_id(): void
    {
        $serviceMock = Mockery::mock(TramiteExternoService::class);
        $serviceMock->shouldReceive('derivar')
            ->once()
            ->andThrow(new \Exception('SQLSTATE[HY000] General error: 7 server closed the connection unexpectedly.'));

        $this->app->instance(TramiteExternoService::class, $serviceMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_CARGO => 20,
                SessionKeys::USER_AREA => 8,
                SessionKeys::USER_COMPANY => 2,
                'sigtram_cargo_id' => 20,
                'sigtram_area_id' => 8,
            ])
            ->postJson(route('mod_gm.segi_expedi.derivar'), [
                'ids' => [501],
                'cargo' => 25,
                'gerencia' => 10,
                'fecha' => '2026-10-11 12:00:00',
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Error al derivar el expediente.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'correlation_id',
            ]);

        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        $this->assertStringNotContainsString('server closed', (string) $response->getContent());
    }

    public function test_externo_devolver_exito_retorna_contrato_plano(): void
    {
        $serviceMock = Mockery::mock(TramiteExternoService::class);
        $serviceMock->shouldReceive('devolver')
            ->once()
            ->andReturn([
                'status' => 'success',
                'mensaje' => 'Expediente(s) devuelto(s) con éxito.',
                'data' => [
                    'procesados' => 1,
                ],
            ]);

        $this->app->instance(TramiteExternoService::class, $serviceMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_CARGO => 20,
                SessionKeys::USER_AREA => 8,
                SessionKeys::USER_COMPANY => 2,
                'sigtram_cargo_id' => 20,
                'sigtram_area_id' => 8,
            ])
            ->postJson(route('mod_gm.segi_expedi.devolver'), [
                'ids' => [501],
                'motivo' => 'Devolución por falta de requisitos formales',
                'fecha' => '2026-10-11 12:00:00',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Expediente(s) devuelto(s) con éxito.',
            ]);
    }

    public function test_externo_archivar_valida_form_request_422(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_CARGO => 20,
                SessionKeys::USER_AREA => 8,
                'sigtram_cargo_id' => 20,
                'sigtram_area_id' => 8,
            ])
            ->postJson(route('mod_gm.segi_expedi.archivar'), [
                'ids' => [],
                'ubicacion' => '',
            ]);

        $response->assertStatus(422);
    }

    public function test_correo_externo_no_renderiza_inicio_dentro_del_iframe(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $repoMock->shouldReceive('obtenerDatosIndex')
            ->once()
            ->andReturn([
                'estados' => [],
                'criterios' => [],
                'gerencias' => [],
                'motivos' => [],
                'cargoActualId' => 20,
                'areaActualId' => 8,
                'companiaId' => 2,
                'videosDisponibles' => collect(),
            ]);

        $securityMock = Mockery::mock(\App\Contracts\Services\SecurityCoreInterface::class);
        $securityMock->shouldReceive('tienePermiso')
            ->andReturn(true);
        $securityMock->shouldReceive('obtenerOpcionPorRuta')
            ->andReturn((object) [
                'dc_rle' => 'Corr. Externo',
                'de_dreccon' => '/mod_gm/mod_tram_exte/segi_expedi.blade.php',
            ]);

        $this->app->instance(TramiteExternoRepositoryInterface::class, $repoMock);
        $this->app->instance(\App\Contracts\Services\SecurityCoreInterface::class, $securityMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_CARGO => 20,
                SessionKeys::USER_AREA => 8,
                SessionKeys::USER_COMPANY => 2,
                'sigtram_cargo_id' => 20,
                'sigtram_area_id' => 8,
            ])
            ->get('/mod_gm/mod_tram_exte/segi_expedi.blade.php');

        $response->assertStatus(200);
        $response->assertViewIs('mod_gm.mod_tram_exte.segi_expedi');
        $this->assertNotEquals('inicio', $response->original->name() ?? '');
    }

    public function test_correo_externo_no_autenticado_no_renderiza_inicio(): void
    {
        $response = $this->get('/mod_gm/mod_tram_exte/segi_expedi.blade.php');

        $response->assertRedirect(route('login'));
        $this->assertFalse($response->isOk());
    }

    public function test_correo_externo_autenticado_sin_cargo_no_renderiza_inicio(): void
    {
        $securityMock = Mockery::mock(\App\Contracts\Services\SecurityCoreInterface::class);
        $securityMock->shouldReceive('tienePermiso')
            ->andReturn(false);

        $this->app->instance(\App\Contracts\Services\SecurityCoreInterface::class, $securityMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                'sigtram_cargo_id' => 0,
            ])
            ->get('/mod_gm/mod_tram_exte/segi_expedi.blade.php');

        $response->assertStatus(403);
    }

    public function test_auth_guardar_cargo_persiste_compania_y_area_en_sesion(): void
    {
        $securityMock = Mockery::mock(\App\Contracts\Services\SecurityCoreInterface::class);
        $securityMock->shouldReceive('obtenerCompaniasUsuario')
            ->andReturn(collect([
                (object) [
                    'cdgo_cmpnia' => 2,
                    'de_cmpnia' => 'MUNICIPALIDAD DE PUENTE PIEDRA',
                    'in_esta' => '1',
                ],
            ]));
        $securityMock->shouldReceive('obtenerCargosUsuario')
            ->andReturn(collect([
                (object) [
                    'cdgo_crgo' => 25,
                    'de_crgo' => 'Especialista Legal',
                    'cdgo_area' => 12,
                    'de_area' => 'Gerencia de Asesoría Jurídica',
                    'cdgo_cmpnia' => 2,
                ],
            ]));

        $this->app->instance(\App\Contracts\Services\SecurityCoreInterface::class, $securityMock);

        $contextoRepoMock = Mockery::mock(\App\Contracts\Repositories\mod_sistema\ContextoUsuarioAutenticadoRepositoryInterface::class);
        $contextoRepoMock->shouldReceive('validarYObtenerContexto')
            ->andReturn(new \App\DTOs\Core\ContextoUsuarioAutenticadoDto(
                idLogin: (int) $this->user->cdgo_lgin,
                idUsuario: 10,
                nombreLogin: 'testuser',
                idPerfil: 1,
                idCompania: 2,
                idCargo: 25,
                idArea: 12,
                nombreCompania: 'MDPP',
                nombreCargo: 'Especialista Legal',
                nombreArea: 'Gerencia de Asesoría Jurídica'
            ));
        $this->app->instance(\App\Contracts\Repositories\mod_sistema\ContextoUsuarioAutenticadoRepositoryInterface::class, $contextoRepoMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 2,
            ])
            ->post(route('seleccionar.cargo.guardar'), [
                'cargo_id' => 25,
            ]);

        $response->assertRedirect(route('inicio'));
        $response->assertSessionMissing('sigtram_compania_id');
        $response->assertSessionMissing('usIdsCargo');
        $response->assertSessionMissing('usIdsArea');
        $response->assertSessionHas(SessionKeys::CARGO_ID, 25);
        $response->assertSessionHas(SessionKeys::AREA_ID, 12);
        $response->assertSessionHas(SessionKeys::COMPANIA_ID, 2);
    }
}
