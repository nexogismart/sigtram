<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Contracts\Services\ContextoUsuarioAutenticadoServiceInterface;
use App\Models\User;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Services\mod_gm\mod_tram_exte\TramiteExternoService;
use App\Support\SessionKeys;
use Mockery;
use Tests\TestCase;

/** HTTP contracts use synthetic identities and mocked business writes, never a real DB. */
class SeguimientoExpedienteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $contexto = $this->crearContexto(companiaId: 2, cargoId: 101, areaId: 20);
        $this->mock(ContextoUsuarioAutenticadoServiceInterface::class)
            ->shouldReceive('obtenerContextoActivo')->andReturn($contexto);
        $user = new User();
        $user->cdgo_lgin = 10;
        $user->cdgo_usrios = 11;
        $user->de_lgin = 'test-seguimiento';
        $user->cdgo_prfle = 4;
        $this->actingAs($user)->withSession([
            SessionKeys::COMPANIA_ID => 2,
            SessionKeys::CARGO_ID => 101,
            SessionKeys::AREA_ID => 20,
        ]);
    }

    public function test_acceso_vista_seguimiento_expedientes(): void
    {
        $this->withoutVite();
        $this->mock(TramiteExternoRepositoryInterface::class)->shouldReceive('obtenerDatosIndex')
            ->once()->with(Mockery::on(fn ($ctx) => $ctx->idCompania === 2 && $ctx->idCargo === 101))
            ->andReturn(['areasList' => [], 'cargosList' => [], 'tiposDocumento' => []]);
        $this->mock(TramiteExternoPolicy::class)->shouldReceive('exportarExcel')->andReturn(false);
        $this->get(route('tramites.bandeja'))->assertOk()
            ->assertViewIs('mod_gm.mod_tram_exte.segi_expedi')
            ->assertSee('tramite-externo-app');
    }

    public function test_consultar_tabla_bandeja(): void
    {
        $this->mock(TramiteExternoRepositoryInterface::class)->shouldReceive('consultarBandeja')
            ->once()->with(Mockery::on(fn ($ctx) => $ctx->idCompania === 2 && $ctx->idCargo === 101),
                'entrada', Mockery::subset(['criterio' => 'expe_todo', 'descripcion' => 'EXP-TEST']), 2, 25)
            ->andReturn(['data' => [['id' => 55]], 'last_page' => 2, 'total' => 26]);
        $this->postJson(route('mod_gm.segi_expedi.tabla'), [
            'bandeja' => 'entrada', 'criterio' => 'expe_todo', 'descripcion' => 'EXP-TEST', 'page' => 2,
        ])->assertOk()->assertJsonPath('data.0.id', 55)
            ->assertJsonPath('pagination.current_page', 2)->assertJsonPath('pagination.total', 26);
    }

    public function test_recepcionar_expediente(): void
    {
        $this->mock(TramiteExternoService::class)->shouldReceive('recepcionar')->once()
            ->with(Mockery::on(fn ($ctx) => $ctx->idCompania === 2 && $ctx->idCargo === 101),
                [55], '2026-10-15 10:00:00', 'Prueba aislada', 'test-idempotency-001')
            ->andReturn(['procesados' => 1, 'mensaje' => 'Recepción de prueba correcta.']);
        $this->postJson(route('mod_gm.segi_expedi.recepcionar'), [
            'movimiento_ids' => [55], 'fecha' => '2026-10-15 10:00:00', 'observaciones' => 'Prueba aislada',
        ], ['Idempotency-Key' => 'test-idempotency-001'])->assertOk()
            ->assertJsonPath('status', 'success')->assertJsonPath('message', 'Recepción de prueba correcta.');
    }

    public function test_recepcion_sin_idempotencia_no_ejecuta_escritura(): void
    {
        $this->mock(TramiteExternoService::class)->shouldNotReceive('recepcionar');
        $this->postJson(route('mod_gm.segi_expedi.recepcionar'), [
            'movimiento_ids' => [55], 'fecha' => '2026-10-15 10:00:00',
        ])->assertUnprocessable();
    }

    public function test_bandeja_invalida_no_ejecuta_consulta(): void
    {
        $this->mock(TramiteExternoRepositoryInterface::class)->shouldNotReceive('consultarBandeja');
        $this->postJson(route('mod_gm.segi_expedi.tabla'), ['bandeja' => 'inexistente'])
            ->assertUnprocessable()->assertJsonValidationErrors('bandeja');
    }
}
