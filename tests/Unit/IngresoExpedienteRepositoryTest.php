<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;
use App\Services\IngresoExpedienteService;
use Illuminate\Http\Request;
use Mockery;
use ReflectionClass;

use App\Support\SessionKeys;

class IngresoExpedienteRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session([
            SessionKeys::USER_COMPANY => 2,
            'usComp' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ingreso_expediente_repository_esta_registrado_en_el_contenedor(): void
    {
        $repo = $this->app->make(IngresoExpedienteRepositoryInterface::class);
        $this->assertInstanceOf(IngresoExpedienteRepositoryInterface::class, $repo);
    }

    public function test_ingreso_expediente_controller_no_contiene_accesos_directos_a_bd(): void
    {
        $reflection = new ReflectionClass(IngresoExpedienteController::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'IngresoExpedienteController no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'IngresoExpedienteController no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'IngresoExpedienteController no debe importar la fachada DB');
    }

    public function test_ingreso_expediente_controller_inyecta_repositorio_en_constructor(): void
    {
        $ref = new ReflectionClass(IngresoExpedienteController::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(IngresoExpedienteRepositoryInterface::class, $params);
    }

    public function test_buscar_usuario_natural_delega_en_repositorio(): void
    {
        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockService = Mockery::mock(IngresoExpedienteService::class);

        $mockRepo->shouldReceive('buscarUsuarioNatural')
            ->once()
            ->with('12345678', Mockery::any())
            ->andReturn([
                'id' => 10,
                'nombres' => 'Juan',
                'ape_paterno' => 'Perez',
                'ape_materno' => 'Gomez',
                'telefono' => '999888777',
                'correo' => 'juan@test.com',
                'direccion' => 'Av Principal 123',
                'tipo_doc' => '1',
            ]);

        $controller = new IngresoExpedienteController($mockService, $mockRepo);

        $request = Request::create('/tramites/buscar-usuario', 'GET', [
            'tipo' => 'natural',
            'documento' => '12345678',
        ]);

        $response = $controller->buscarUsuario($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Juan', $data['data']['nombres']);
    }

    public function test_buscar_usuario_juridico_delega_en_repositorio(): void
    {
        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockService = Mockery::mock(IngresoExpedienteService::class);

        $mockRepo->shouldReceive('buscarUsuarioJuridico')
            ->once()
            ->with('20123456789', Mockery::any())
            ->andReturn([
                'id' => 20,
                'razon_social' => 'EMPRESA SAC',
                'ruc' => '20123456789',
                'tel_jur' => '015554433',
                'cor_jur' => 'info@empresa.com',
                'dir_jur' => 'Av Industrial 456',
            ]);

        $controller = new IngresoExpedienteController($mockService, $mockRepo);

        $request = Request::create('/tramites/buscar-usuario', 'GET', [
            'tipo' => 'juridica',
            'documento' => '20123456789',
        ]);

        $response = $controller->buscarUsuario($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals('EMPRESA SAC', $data['data']['razon_social']);
    }

    public function test_listar_subgerencias_delega_en_repositorio(): void
    {
        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockService = Mockery::mock(IngresoExpedienteService::class);

        $mockRepo->shouldReceive('listarSubgerencias')
            ->once()
            ->with(5, Mockery::any())
            ->andReturn([
                ['id' => 501, 'nombre' => 'Subgerencia A'],
            ]);

        $controller = new IngresoExpedienteController($mockService, $mockRepo);

        $response = $controller->listarSubgerencias(5);
        $data = $response->getData(true);

        $this->assertCount(1, $data);
        $this->assertEquals('Subgerencia A', $data[0]['nombre']);
    }

    public function test_listar_tramites_delega_en_repositorio(): void
    {
        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockService = Mockery::mock(IngresoExpedienteService::class);

        $mockRepo->shouldReceive('listarTramitesPorServicio')
            ->once()
            ->with(12, Mockery::any())
            ->andReturn([
                ['id' => 1201, 'nombre' => 'Trámite TUPA 1', 'in_sile_admi' => '0', 'nu_tiem_sile' => 30],
            ]);

        $controller = new IngresoExpedienteController($mockService, $mockRepo);

        $response = $controller->listarTramites(12);
        $data = $response->getData(true);

        $this->assertCount(1, $data);
        $this->assertEquals('Trámite TUPA 1', $data[0]['nombre']);
    }

    public function test_generar_numero_expediente_usa_bloqueo_comun_y_retorna_estructura_completa(): void
    {
        $mockDb = Mockery::mock(\Illuminate\Database\DatabaseManager::class);
        $mockConn = Mockery::mock(\Illuminate\Database\ConnectionInterface::class);

        $mockDb->shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($mockConn);

        $mockConn->shouldReceive('getDriverName')
            ->atLeast()->once()
            ->andReturn('sqlite');

        $builderTipo = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $mockDb->shouldReceive('table')
            ->with('sistema.a_tpo_trmtes')
            ->once()
            ->andReturn($builderTipo);
        $builderTipo->shouldReceive('where')->with('dc_tpo_trmte', 'E')->once()->andReturnSelf();
        $builderTipo->shouldReceive('get')->with(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte', 'in_esta'])->once()->andReturn(collect([
            (object) [
                'cdgo_tpo_trmte' => 22,
                'dc_tpo_trmte' => 'E',
                'de_tpo_trmte' => 'EXTERNO',
                'in_esta' => '1',
            ]
        ]));

        $currYear = (int) date('Y');
        $builderAnio = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $mockDb->shouldReceive('table')
            ->with('sistema.a_anios')
            ->once()
            ->andReturn($builderAnio);
        $builderAnio->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $builderAnio->shouldReceive('where')->with('de_anio', $currYear)->once()->andReturnSelf();
        $builderAnio->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $builderAnio->shouldReceive('first')->once()->andReturn((object) [
            'cdgo_anio' => $currYear,
            'de_anio' => $currYear,
        ]);

        $builderExp = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $mockDb->shouldReceive('table')
            ->with('sistema.p_dto_trmtes')
            ->once()
            ->andReturn($builderExp);
        $builderExp->shouldReceive('where')->with('cdgo_anio', $currYear)->once()->andReturnSelf();
        $builderExp->shouldReceive('where')->with('cdgo_tpo_trmte', 22)->once()->andReturnSelf();
        $builderExp->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $builderExp->shouldReceive('max')->with('nu_expe_trmte')->once()->andReturn(100);

        $repo = new \App\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepository($mockDb);
        $res = $repo->generarNumeroExpediente(['tipo_tramite' => 'externo'], 2);

        $this->assertEquals("E-101-{$currYear}", $res['nu_expe_todo']);
        $this->assertEquals(101, $res['nu_expe_trmte']);
        $this->assertEquals($currYear, $res['cdgo_anio']);
        $this->assertEquals(22, $res['cdgo_tpo_trmte']);
        $this->assertEquals('E', $res['in_docu_trmte']);
    }

    public function test_generar_numero_expediente_falla_fail_closed_cuando_statement_advisory_lock_falla(): void
    {
        $mockDb = Mockery::mock(\Illuminate\Database\DatabaseManager::class);
        $mockConn = Mockery::mock(\Illuminate\Database\ConnectionInterface::class);

        $mockDb->shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($mockConn);

        $mockConn->shouldReceive('getDriverName')
            ->atLeast()->once()
            ->andReturn('pgsql');

        $builderTipo = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $mockDb->shouldReceive('table')
            ->with('sistema.a_tpo_trmtes')
            ->once()
            ->andReturn($builderTipo);
        $builderTipo->shouldReceive('where')->with('dc_tpo_trmte', 'E')->once()->andReturnSelf();
        $builderTipo->shouldReceive('get')->with(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte', 'in_esta'])->once()->andReturn(collect([
            (object) [
                'cdgo_tpo_trmte' => 22,
                'dc_tpo_trmte' => 'E',
                'de_tpo_trmte' => 'EXTERNO',
                'in_esta' => '1',
            ]
        ]));

        $currYear = (int) date('Y');
        // Advisory lock falla de forma simulada
        $mockDb->shouldReceive('statement')
            ->once()
            ->with('SELECT pg_advisory_xact_lock(?, ?)', [2, $currYear])
            ->andThrow(new \RuntimeException('PostgreSQL lock connection lost'));

        // REGLA CRÍTICA FAIL-CLOSED: No debe consultar a_anios ni p_dto_trmtes
        $mockDb->shouldNotReceive('table')->with('sistema.a_anios');
        $mockDb->shouldNotReceive('table')->with('sistema.p_dto_trmtes');

        $repo = new \App\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepository($mockDb);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PostgreSQL lock connection lost');

        $repo->generarNumeroExpediente(['tipo_tramite' => 'externo'], 2);
    }
}
