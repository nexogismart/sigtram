<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Mockery;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository;

class TramiteInternoAutorizacionIdorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_policy_view_rechaza_por_compania_distinta(): void
    {
        $policy = new TramiteInternoPolicy();
        $ctx = $this->crearContexto('admin', 1, 1, 10, 5, [10], []);
        $mov = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 200,
            numero: 'DOC-01',
            companiaId: 2, // Otra compania
            cargoSalidaId: 10,
            cargoEntradaId: 11,
            areaSalidaId: 5,
            areaEntradaId: 6,
            estadoId: 1,
            inEsta: '1'
        );

        $this->assertFalse($policy->ver($ctx, $mov));
        $this->assertFalse($policy->consultarHistorico($ctx, $mov));
        $this->assertFalse($policy->descargar($ctx, $mov));
    }

    public function test_policy_view_rechaza_cargo_ajeno_sin_super_permiso(): void
    {
        $policy = new TramiteInternoPolicy();
        $ctx = $this->crearContexto('usr', 1, 1, 99, 99, [99], []);
        $mov = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 200,
            numero: 'DOC-01',
            companiaId: 1,
            cargoSalidaId: 10,
            cargoEntradaId: 11,
            areaSalidaId: 5,
            areaEntradaId: 6,
            estadoId: 1,
            inEsta: '1'
        );

        $this->assertFalse($policy->ver($ctx, $mov));
    }

    public function test_policy_view_autoriza_receptor_emisor_o_super_permiso(): void
    {
        $policy = new TramiteInternoPolicy();
        
        // Emisor
        $ctxEmisor = $this->crearContexto('usr1', 1, 1, 10, 5, [10], []);
        $mov = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 200,
            numero: 'DOC-01',
            companiaId: 1,
            cargoSalidaId: 10,
            cargoEntradaId: 11,
            areaSalidaId: 5,
            areaEntradaId: 6,
            estadoId: 1,
            inEsta: '1'
        );
        $this->assertTrue($policy->ver($ctxEmisor, $mov));

        // Receptor
        $ctxReceptor = $this->crearContexto('usr2', 2, 1, 11, 6, [11], []);
        $this->assertTrue($policy->ver($ctxReceptor, $mov));

        // Super-permiso 18
        $ctxSuper = $this->crearContexto('auditor', 3, 1, 50, 20, [50], [18]);
        $this->assertTrue($policy->ver($ctxSuper, $mov));
    }

    public function test_lote_aborta_si_al_menos_un_item_es_ajeno(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('whereIn')->with('r.cdgo_trmtes_ntrnos', [101, 102, 103])->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();

        // Se solicitaron 3 IDs pero la BD solo devuelve 2 (porque 1 era de otra compania o no existe)
        $fila1 = (object) [
            'cdgo_trmtes_ntrnos' => 101, 'cdgo_dtos_ntrnos' => 50, 'nu_tram_todo' => 'DOC-1',
            'cdgo_cmpnia' => 1, 'cdgo_crgo_sa' => 5, 'cdgo_crgo_en' => 6, 'area_sa' => 10,
            'cdgo_estdo_trmte' => 1, 'in_esta' => '1', 'fe_rcbdo' => null, 'de_obser' => null, 'in_copia' => 0
        ];
        $fila2 = (object) [
            'cdgo_trmtes_ntrnos' => 102, 'cdgo_dtos_ntrnos' => 51, 'nu_tram_todo' => 'DOC-2',
            'cdgo_cmpnia' => 1, 'cdgo_crgo_sa' => 5, 'cdgo_crgo_en' => 6, 'area_sa' => 10,
            'cdgo_estdo_trmte' => 1, 'in_esta' => '1', 'fe_rcbdo' => null, 'de_obser' => null, 'in_copia' => 0
        ];

        $builder->shouldReceive('get')->andReturn(collect([$fila1, $fila2]));

        $repo = new TramiteInternoRepository($db);
        $resultado = $repo->obtenerMovimientosPorIds([101, 102, 103], 1);

        $this->assertEmpty($resultado, 'Un lote con discrepancia o elemento ajeno debe abortar completamente (fail-closed).');
    }

    public function test_historial_retorna_vacio_si_documento_es_de_otra_compania(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        // first() retorna null porque cdgo_cmpnia difiere
        $builder->shouldReceive('first')->andReturn(null);

        $repo = new TramiteInternoRepository($db);
        $ctx = $this->crearContexto('usr', 1, 1, 10, 5, [10], []);
        $res = $repo->obtenerHistorial(999, $ctx);

        $this->assertSame(0, $res['meta']['total']);
        $this->assertEmpty($res['data']);
    }
}
