<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Enums\mod_gm\BandejaTramiteExterno;
use App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use Tests\TestCase;

class ConteosYBandejasUnificadasTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * FASE 2: Verifica que TramiteExternoRepository::obtenerConteosBandejas
     * retorne conteos para las bandejas visibles de Trámite Externo.
     */
    public function test_externo_obtener_conteos_retorna_bandejas_visibles_de_menu(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->times(7)->andReturn($builder);
        $builder->shouldReceive('join')->times(7)->andReturnSelf();
        $builder->shouldReceive('leftJoin')->times(7)->andReturnSelf();
        $builder->shouldReceive('where')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('whereIn')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('count')->times(7)->andReturn(5);

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);

        $conteos = $repo->obtenerConteosBandejas($contexto);

        $this->assertIsArray($conteos);
        $this->assertArrayHasKey(BandejaTramiteExterno::TODOS->value, $conteos);
        $this->assertArrayHasKey(BandejaTramiteExterno::ENTRADA->value, $conteos);
        $this->assertArrayHasKey(BandejaTramiteExterno::RECIBIDOS->value, $conteos);
        $this->assertArrayHasKey(BandejaTramiteExterno::SALIDA->value, $conteos);
        $this->assertArrayHasKey(BandejaTramiteExterno::FINALIZADOS->value, $conteos);
        $this->assertArrayHasKey('finalizados_notificados', $conteos);
        $this->assertArrayHasKey(BandejaTramiteExterno::ARCHIVO->value, $conteos);
        $this->assertArrayHasKey(BandejaTramiteExterno::SIN_FINALIZAR->value, $conteos);

        $this->assertSame(5, $conteos[BandejaTramiteExterno::TODOS->value]);
        $this->assertSame(5, $conteos['finalizados_notificados']);
    }

    /**
     * CEF-09A: Verifica que TramiteInternoRepository::obtenerConteosBandejas
     * retorne las bandejas autorizadas para el contexto interno.
     */
    public function test_interno_obtener_conteos_retorna_bandejas_autorizadas(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->times(7)->andReturn($builder);
        $builder->shouldReceive('join')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('leftJoin')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('where')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('whereIn')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('orderBy')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('select')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('count')->times(7)->andReturn(12);

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
        $conteos = $repo->obtenerConteosBandejas($contexto);

        $this->assertIsArray($conteos);
        $this->assertCount(7, $conteos);
        $this->assertArrayHasKey('entrada', $conteos);
        $this->assertArrayHasKey('enviados', $conteos);
        $this->assertArrayHasKey('recibidos', $conteos);
        $this->assertArrayHasKey('salida', $conteos);
        $this->assertArrayHasKey('finalizados', $conteos);
        $this->assertArrayHasKey('sin_finalizar', $conteos);
        $this->assertArrayHasKey('todos', $conteos);
        $this->assertArrayNotHasKey('contabilidad', $conteos, 'No debe incluir contabilidad para área distinta a 21 sin permiso 18');
    }

    /**
     * CEF-09A: Verifica que si el contexto es Contabilidad (área 21 o permiso 18),
     * se incluya la bandeja 'contabilidad'.
     */
    public function test_interno_obtener_conteos_incluye_contabilidad_para_area_21(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        // 8 bandejas (7 estándar + contabilidad)
        $db->shouldReceive('table')->times(8)->andReturn($builder);
        $builder->shouldReceive('join')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('leftJoin')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('where')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('whereIn')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('orderBy')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('select')->atLeast()->once()->andReturnSelf();
        $builder->shouldReceive('count')->times(8)->andReturn(3);

        $contexto = $this->crearContexto(
            login: 'contador',
            usuarioId: 15,
            companiaId: 1,
            cargoId: 21,
            areaId: 21,
            cargosAsignados: [21],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $conteos = $repo->obtenerConteosBandejas($contexto);

        $this->assertCount(8, $conteos);
        $this->assertArrayHasKey('contabilidad', $conteos);
    }
}
