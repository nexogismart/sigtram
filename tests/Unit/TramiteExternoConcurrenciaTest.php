<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Enums\mod_gm\EstadoTramiteExterno;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Services\mod_gm\mod_tram_exte\DocumentoInternoDesdeExpedienteExternoService;
use App\Services\mod_gm\mod_tram_exte\IdempotenciaService;
use App\Services\mod_gm\mod_tram_exte\MaquinaEstadosTramiteExterno;
use App\Services\mod_gm\mod_tram_exte\TramiteExternoService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

class TramiteExternoConcurrenciaTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $repository;
    private $db;
    private $builder;
    private $documentoInternoService;
    private TramiteExternoPolicy $policy;
    private MaquinaEstadosTramiteExterno $maquina;
    private IdempotenciaService $idempotencia;
    private TramiteExternoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $this->db = Mockery::mock(DatabaseManager::class);
        $this->builder = Mockery::mock(Builder::class);
        $this->documentoInternoService = Mockery::mock(DocumentoInternoDesdeExpedienteExternoService::class);

        $this->db->shouldReceive('transaction')->andReturnUsing(function ($callback) {
            return $callback();
        });
        $this->db->shouldReceive('table')->andReturn($this->builder);

        $this->builder->shouldReceive('where')->andReturnSelf();
        $this->builder->shouldReceive('orderBy')->andReturnSelf();
        $this->builder->shouldReceive('insert')->andReturn(true);

        $this->repository->shouldReceive('desactivarMovimientoExternoEsperado')->byDefault()->andReturn(1);
        $this->repository->shouldReceive('crearMovimientoExterno')->byDefault()->andReturn(1);
        $this->repository->shouldReceive('obtenerCargoPrincipalDeArea')->byDefault()->andReturn((object) ['cdgo_crgo' => 500]);
        $this->repository->shouldReceive('validarCargoEnArea')->byDefault()->andReturn(true);

        $this->maquina = new MaquinaEstadosTramiteExterno();
        $this->policy = new TramiteExternoPolicy();
        $this->idempotencia = new IdempotenciaService(\Illuminate\Support\Facades\Cache::store('array'));

        $this->service = new TramiteExternoService(
            $this->repository,
            $this->maquina,
            $this->policy,
            $this->idempotencia,
            $this->db,
            $this->documentoInternoService
        );
    }

    /**
     * CEF-P0-02: Si otra transacción cerró o mutó el movimiento (afectados === 0), debe abortar con conflicto.
     */
    public function test_recepcionar_falla_si_hay_conflicto_concurrente_en_el_movimiento(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], [], false, 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 80, 105, 15, 20, EstadoTramiteExterno::PENDIENTE->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->andReturn([$movimiento]);

        // Simula que la fila ya no tenía in_esta = '1' al momento del UPDATE atómico
        $this->repository->shouldReceive('desactivarMovimientoExternoEsperado')->with(50)->once()->andReturn(0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conflicto de concurrencia');

        $this->service->recepcionar(
            $contexto,
            [1],
            '2026-10-10 10:00:00',
            'Intento concurrente',
            'idem-rec-conflict'
        );
    }

    /**
     * CEF-P0-03 / CEF-04: Enviar multi-área debe rechazar si un área no tiene cargo activo, nunca convertir areaId en cargoId.
     */
    public function test_enviar_multiarea_rechaza_area_sin_cargo_activo_y_no_usa_area_como_cargo(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], [], false, 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 80, 105, 15, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->andReturn([$movimiento]);

        // Área 999 sin cargo activo
        $this->repository->shouldReceive('obtenerCargoPrincipalDeArea')->with(999)->once()->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no posee un cargo activo válido');

        $this->service->enviar(
            $contexto,
            [1],
            [999],
            '2026-10-10 10:00:00',
            null,
            null,
            null,
            null,
            null,
            'idem-env-no-cargo'
        );
    }

    /**
     * CEF-04: Asignar interno debe validar que el cargo pertenezca al área del contexto y esté activo.
     */
    public function test_asignar_rechaza_cargo_que_no_pertenece_al_area_activa(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], [], false, 1001); // área 20
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 80, 105, 15, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->andReturn([$movimiento]);

        // Cargo 500 no existe en área 20 con in_esta = '1'
        $this->repository->shouldReceive('validarCargoEnArea')->with(500, 20)->once()->andReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no pertenece al área activa o no está vigente');

        $this->service->asignar(
            $contexto,
            [1],
            500, // cargo ajeno
            '2026-10-10 10:00:00',
            null,
            'idem-asig-area-invalida'
        );
    }

    /**
     * CEF-04: resolverCargoParaArea lanza InvalidArgumentException si no existe cargo activo.
     */
    public function test_resolver_cargo_para_area_lanza_excepcion_si_no_existe_cargo_activo(): void
    {
        $this->repository->shouldReceive('obtenerCargoPrincipalDeArea')->with(999)->once()->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no posee un cargo activo válido');

        $this->service->resolverCargoParaArea(999);
    }

    /**
     * CEF-04: derivar resuelve cargo desde el área cuando cargoDestinoId no es provisto.
     */
    public function test_derivar_resuelve_cargo_desde_area_cuando_cargo_es_cero(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20, [], [], false, 1001);
        $movimiento = new ExpedienteMovimientoDto(1, '2026-0001', 50, 1, 80, 105, 15, 20, EstadoTramiteExterno::RECIBIDO->value);

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->with([1], 1)
            ->andReturn([$movimiento]);

        $this->repository->shouldReceive('obtenerCargoPrincipalDeArea')->with(55)->once()->andReturn((object) ['cdgo_crgo' => 300]);
        $this->repository->shouldReceive('desactivarMovimientoExternoEsperado')->with(50)->once()->andReturn(1);

        $resultado = $this->service->derivar(
            $contexto,
            [1],
            0, // sin cargo explícito
            '2026-10-10 10:00:00',
            'Derivación por área',
            'idem-der-area-1',
            55 // área 55
        );

        $this->assertSame(1, $resultado['procesados']);
    }
}
