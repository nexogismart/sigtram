<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mockery;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;

class TramiteInternoRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_retorna_null_si_movimiento_no_existe_o_es_de_otra_compania(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        
        // Simular que pertenece a compania 2 mientras se solicita compania 1
        $filaFalsa = (object) [
            'cdgo_trmtes_ntrnos' => 100,
            'cdgo_dtos_ntrnos' => 50,
            'nu_tram_todo' => 'MEMO-001',
            'cdgo_cmpnia' => 2, // Otra compania
            'cdgo_crgo_sa' => 5,
            'cdgo_crgo_en' => 6,
            'area_sa' => 10,
            'cdgo_estdo_trmte' => 1,
            'in_esta' => '1',
            'fe_rcbdo' => null,
            'de_obser' => null,
            'in_copia' => 0,
        ];
        $builder->shouldReceive('first')->andReturn($filaFalsa);

        $repo = new TramiteInternoRepository($db);
        $res = $repo->obtenerMovimientoPorId(100, 1); // companiaId = 1

        $this->assertNull($res, 'Debe retornar null cuando el movimiento pertenece a otra compañía (fail-closed IDOR).');
    }

    public function test_obtener_movimientos_por_ids_falla_si_tamano_no_coincide(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('whereIn')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();

        // Se piden 3 IDs pero la BD solo devuelve 2 (uno fue filtrado por ser de otra compania o in_esta=0)
        $builder->shouldReceive('get')->andReturn(collect([
            (object) ['cdgo_trmtes_ntrnos' => 1, 'cdgo_cmpnia' => 1],
            (object) ['cdgo_trmtes_ntrnos' => 2, 'cdgo_cmpnia' => 1],
        ]));

        $repo = new TramiteInternoRepository($db);
        $res = $repo->obtenerMovimientosPorIds([1, 2, 3], 1);

        $this->assertEmpty($res, 'Debe rechazar todo el lote si al menos un ID no pertenece a la compañía.');
    }

    public function test_listar_bandeja_construye_metadata_paginacion_correcta(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereIn')->andReturnSelf();
        $builder->shouldReceive('whereBetween')->andReturnSelf();
        $builder->shouldReceive('whereRaw')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn(45);
        $builder->shouldReceive('offset')->with(20)->andReturnSelf();
        $builder->shouldReceive('limit')->with(20)->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([
            ['id' => 21],
            ['id' => 22],
        ]));

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $res = $repo->listarBandeja('entrada', $contexto, [], 2, 20);

        $this->assertArrayHasKey('meta', $res);
        $this->assertEquals(2, $res['meta']['current_page']);
        $this->assertEquals(20, $res['meta']['per_page']);
        $this->assertEquals(45, $res['meta']['total']);
        $this->assertEquals(3, $res['meta']['last_page']);
        $this->assertEquals(21, $res['meta']['from']);
        $this->assertEquals(40, $res['meta']['to']);
        $this->assertTrue($res['meta']['has_more_pages']);
    }
}