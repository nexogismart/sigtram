<?php

namespace Tests\Unit;

use Tests\TestCase;
use Mockery;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;

class TramiteInternoPaginacionBandejasTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_paginacion_servidor_retorna_contrato_meta_completo(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->andReturnSelf();
        $builder->shouldReceive('whereBetween')->andReturnSelf();
        $builder->shouldReceive('whereRaw')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();

        // 248 registros en total, página 2 con per_page 25
        $builder->shouldReceive('count')->andReturn(248);
        $builder->shouldReceive('offset')->with(25)->andReturnSelf();
        $builder->shouldReceive('limit')->with(25)->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect(range(1, 25)));

        $contexto = $this->crearContexto(
            login: 'operador1',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $resultado = $repo->listarBandeja('entrada', $contexto, [], 2, 25);

        $this->assertArrayHasKey('meta', $resultado);
        $meta = $resultado['meta'];

        $this->assertEquals(2, $meta['current_page']);
        $this->assertEquals(25, $meta['per_page']);
        $this->assertEquals(248, $meta['total']);
        $this->assertEquals(10, $meta['last_page']);
        $this->assertEquals(26, $meta['from']);
        $this->assertEquals(50, $meta['to']);
        $this->assertTrue($meta['has_more_pages']);
        $this->assertCount(25, $resultado['data']);
    }

    public function test_ultima_pagina_calcula_limites_y_has_more_pages_false(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();

        // 53 registros en total, página 3 con per_page 25 (quedan 3 registros: del 51 al 53)
        $builder->shouldReceive('count')->andReturn(53);
        $builder->shouldReceive('offset')->with(50)->andReturnSelf();
        $builder->shouldReceive('limit')->with(25)->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([1, 2, 3]));

        $contexto = $this->crearContexto('usr', 1, 1, 10, 5, [10], []);

        $repo = new TramiteInternoRepository($db);
        $resultado = $repo->listarBandeja('recibidos', $contexto, [], 3, 25);

        $meta = $resultado['meta'];
        $this->assertEquals(3, $meta['current_page']);
        $this->assertEquals(3, $meta['last_page']);
        $this->assertEquals(51, $meta['from']);
        $this->assertEquals(53, $meta['to']);
        $this->assertFalse($meta['has_more_pages']);
    }
}