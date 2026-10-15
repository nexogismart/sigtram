<?php

declare(strict_types=1);

namespace Tests\Unit\mod_gm;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;
use App\Http\Requests\IngresoExpedienteRequest;
use App\Models\User;
use App\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepository;
use App\Services\IngresoExpedienteService;
use App\Support\SessionKeys;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class TramiteExternoTiposPermitidosTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function autenticarUsuario(int $loginId = 55): User
    {
        $user = new User();
        $user->cdgo_lgin = $loginId;
        $user->de_lgin = 'operador_mesa';
        Auth::setUser($user);

        return $user;
    }

    /**
     * Demuestra que al seleccionar EXTERNO el backend resuelve dinámicamente el catálogo con dc_tpo_trmte = 'E'.
     */
    public function test_externo_resuelve_catalogo_letra_e_dinamicamente(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockBuilder = Mockery::mock(Builder::class);

        // Simulamos catálogo dinámico donde E tiene ID 999 (demostrando que NO depende del 22)
        $mockDb->shouldReceive('table')->with('sistema.a_tpo_trmtes')->once()->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('where')->with('dc_tpo_trmte', 'E')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('get')->with(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte', 'in_esta'])->once()->andReturn(collect([
            (object) [
                'cdgo_tpo_trmte' => 999, // ID dinámico no hardcodeado
                'dc_tpo_trmte' => 'E',
                'de_tpo_trmte' => 'EXPEDIENTE EXTERNO GENERAL',
                'in_esta' => '1',
            ]
        ]));

        $mockConn = Mockery::mock(\Illuminate\Database\ConnectionInterface::class);
        $mockDb->shouldReceive('connection')->andReturn($mockConn);
        $mockConn->shouldReceive('getDriverName')->andReturn('sqlite');

        $currYear = (int) date('Y');
        $mockBuilderAnio = Mockery::mock(Builder::class);
        $mockDb->shouldReceive('table')->with('sistema.a_anios')->once()->andReturn($mockBuilderAnio);
        $mockBuilderAnio->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $mockBuilderAnio->shouldReceive('where')->with('de_anio', $currYear)->once()->andReturnSelf();
        $mockBuilderAnio->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $mockBuilderAnio->shouldReceive('first')->once()->andReturn((object) ['cdgo_anio' => $currYear, 'de_anio' => $currYear]);

        $mockBuilderExp = Mockery::mock(Builder::class);
        $mockDb->shouldReceive('table')->with('sistema.p_dto_trmtes')->once()->andReturn($mockBuilderExp);
        $mockBuilderExp->shouldReceive('where')->with('cdgo_anio', $currYear)->once()->andReturnSelf();
        $mockBuilderExp->shouldReceive('where')->with('cdgo_tpo_trmte', 999)->once()->andReturnSelf();
        $mockBuilderExp->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $mockBuilderExp->shouldReceive('max')->with('nu_expe_trmte')->once()->andReturn(50);

        $repo = new IngresoExpedienteRepository($mockDb);
        $res = $repo->generarNumeroExpediente(['tipo_tramite' => 'externo'], 2);

        $this->assertSame("E-51-{$currYear}", $res['nu_expe_todo']);
        $this->assertSame(999, $res['cdgo_tpo_trmte']);
        $this->assertSame('E', $res['in_docu_trmte']);
        $this->assertSame(51, $res['nu_expe_trmte']);
    }

    /**
     * Demuestra que al seleccionar DOCUMENTO SIMPLE el backend resuelve catálogo con dc_tpo_trmte = 'S'.
     */
    public function test_documento_simple_resuelve_catalogo_letra_s_dinamicamente(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockBuilder = Mockery::mock(Builder::class);

        // Simulamos catálogo dinámico donde S tiene ID 888 (demostrando que NO depende del 17)
        $mockDb->shouldReceive('table')->with('sistema.a_tpo_trmtes')->once()->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('where')->with('dc_tpo_trmte', 'S')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('get')->with(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte', 'in_esta'])->once()->andReturn(collect([
            (object) [
                'cdgo_tpo_trmte' => 888, // ID dinámico no hardcodeado
                'dc_tpo_trmte' => 'S',
                'de_tpo_trmte' => 'DOCUMENTO SIMPLE MESA',
                'in_esta' => '1',
            ]
        ]));

        $mockConn = Mockery::mock(\Illuminate\Database\ConnectionInterface::class);
        $mockDb->shouldReceive('connection')->andReturn($mockConn);
        $mockConn->shouldReceive('getDriverName')->andReturn('sqlite');

        $currYear = (int) date('Y');
        $mockBuilderAnio = Mockery::mock(Builder::class);
        $mockDb->shouldReceive('table')->with('sistema.a_anios')->once()->andReturn($mockBuilderAnio);
        $mockBuilderAnio->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $mockBuilderAnio->shouldReceive('where')->with('de_anio', $currYear)->once()->andReturnSelf();
        $mockBuilderAnio->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $mockBuilderAnio->shouldReceive('first')->once()->andReturn((object) ['cdgo_anio' => $currYear, 'de_anio' => $currYear]);

        $mockBuilderExp = Mockery::mock(Builder::class);
        $mockDb->shouldReceive('table')->with('sistema.p_dto_trmtes')->once()->andReturn($mockBuilderExp);
        $mockBuilderExp->shouldReceive('where')->with('cdgo_anio', $currYear)->once()->andReturnSelf();
        $mockBuilderExp->shouldReceive('where')->with('cdgo_tpo_trmte', 888)->once()->andReturnSelf();
        $mockBuilderExp->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $mockBuilderExp->shouldReceive('max')->with('nu_expe_trmte')->once()->andReturn(20);

        $repo = new IngresoExpedienteRepository($mockDb);
        $res = $repo->generarNumeroExpediente(['tipo_tramite' => 'simple'], 2);

        $this->assertSame("S-21-{$currYear}", $res['nu_expe_todo']);
        $this->assertSame(888, $res['cdgo_tpo_trmte']);
        $this->assertSame('S', $res['in_docu_trmte']);
        $this->assertSame(21, $res['nu_expe_trmte']);
    }

    /**
     * Demuestra que marcar OBSERVADO resuelve catálogo 'O' aunque previamente se eligiera E o S.
     */
    public function test_observado_resuelve_o_anulando_seleccion_previa(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockBuilder = Mockery::mock(Builder::class);

        // Simulamos catálogo dinámico donde O tiene ID 777 (demostrando que NO depende del 40)
        $mockDb->shouldReceive('table')->with('sistema.a_tpo_trmtes')->once()->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('where')->with('dc_tpo_trmte', 'O')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('get')->with(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte', 'in_esta'])->once()->andReturn(collect([
            (object) [
                'cdgo_tpo_trmte' => 777, // ID dinámico no hardcodeado
                'dc_tpo_trmte' => 'O',
                'de_tpo_trmte' => 'EXPEDIENTE OBSERVADO',
                'in_esta' => '1',
            ]
        ]));

        $mockConn = Mockery::mock(\Illuminate\Database\ConnectionInterface::class);
        $mockDb->shouldReceive('connection')->andReturn($mockConn);
        $mockConn->shouldReceive('getDriverName')->andReturn('sqlite');

        $currYear = (int) date('Y');
        $mockBuilderAnio = Mockery::mock(Builder::class);
        $mockDb->shouldReceive('table')->with('sistema.a_anios')->once()->andReturn($mockBuilderAnio);
        $mockBuilderAnio->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $mockBuilderAnio->shouldReceive('where')->with('de_anio', $currYear)->once()->andReturnSelf();
        $mockBuilderAnio->shouldReceive('lockForUpdate')->once()->andReturnSelf();
        $mockBuilderAnio->shouldReceive('first')->once()->andReturn((object) ['cdgo_anio' => $currYear, 'de_anio' => $currYear]);

        $mockBuilderExp = Mockery::mock(Builder::class);
        $mockDb->shouldReceive('table')->with('sistema.p_dto_trmtes')->once()->andReturn($mockBuilderExp);
        $mockBuilderExp->shouldReceive('where')->with('cdgo_anio', $currYear)->once()->andReturnSelf();
        $mockBuilderExp->shouldReceive('where')->with('cdgo_tpo_trmte', 777)->once()->andReturnSelf();
        $mockBuilderExp->shouldReceive('where')->with('cdgo_cmpnia', 2)->once()->andReturnSelf();
        $mockBuilderExp->shouldReceive('max')->with('nu_expe_trmte')->once()->andReturn(5);

        $repo = new IngresoExpedienteRepository($mockDb);
        // Aunque se pase 'tipo_tramite' => 'simple', observado = true DEBE forzar 'O'
        $res = $repo->generarNumeroExpediente(['tipo_tramite' => 'simple', 'observado' => true], 2);

        $this->assertSame("O-6-{$currYear}", $res['nu_expe_todo']);
        $this->assertSame(777, $res['cdgo_tpo_trmte']);
        $this->assertSame('O', $res['in_docu_trmte']);
        $this->assertSame(6, $res['nu_expe_trmte']);
    }

    /**
     * Demuestra que el sistema rechaza con HTTP 422 si se intenta manipular el request enviando 'A', 'R', 'OTROS', '55', etc.
     */
    public function test_rechaza_tipos_no_permitidos_con_error_422(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockBuilder = Mockery::mock(Builder::class);

        // Simulamos que el request envía cdgo_tpo_trmte = 70 que corresponde a 'A'
        $mockDb->shouldReceive('table')->with('sistema.a_tpo_trmtes')->once()->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('where')->with('cdgo_tpo_trmte', 70)->once()->andReturnSelf();
        $mockBuilder->shouldReceive('first')->with(['dc_tpo_trmte', 'de_tpo_trmte'])->once()->andReturn((object) [
            'dc_tpo_trmte' => 'A',
            'de_tpo_trmte' => 'ACTA O TIPO NO PERMITIDO'
        ]);

        $repo = new IngresoExpedienteRepository($mockDb);

        $this->expectException(ValidationException::class);

        $repo->generarNumeroExpediente(['tipo_tramite' => 'externo', 'cdgo_tpo_trmte' => 70], 2);
    }

    /**
     * Demuestra que si el tipo de catálogo está inactivo, aborta con mensaje controlado y sin escrituras.
     */
    public function test_catalogo_inactivo_produce_error_controlado(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockBuilder = Mockery::mock(Builder::class);

        // Catálogo 'E' existe pero está inactivo (in_esta = '0')
        $mockDb->shouldReceive('table')->with('sistema.a_tpo_trmtes')->once()->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('where')->with('dc_tpo_trmte', 'E')->once()->andReturnSelf();
        $mockBuilder->shouldReceive('get')->with(['cdgo_tpo_trmte', 'dc_tpo_trmte', 'de_tpo_trmte', 'in_esta'])->once()->andReturn(collect([
            (object) [
                'cdgo_tpo_trmte' => 22,
                'dc_tpo_trmte' => 'E',
                'de_tpo_trmte' => 'EXTERNO INACTIVO',
                'in_esta' => '0', // Inactivo
            ]
        ]));

        $repo = new IngresoExpedienteRepository($mockDb);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('se encuentra inactivo y no permite nuevas creaciones');

        $repo->generarNumeroExpediente(['tipo_tramite' => 'externo'], 2);
    }

    /**
     * Demuestra que el formulario guarda la compañía activa de la sesión y no permite suplantación.
     */
    public function test_store_guarda_compania_activa_de_sesion(): void
    {
        $user = $this->autenticarUsuario(10);
        session([
            SessionKeys::USER_COMPANY => 2, // Sesión legítima es 2
        ]);

        $mockRepo = Mockery::mock(IngresoExpedienteRepositoryInterface::class);
        $mockService = Mockery::mock(IngresoExpedienteService::class);

        // El service DEBE ser llamado con companiaId = 2 (de la sesión), no 999
        $mockService->shouldReceive('registrar')
            ->once()
            ->with(
                Mockery::any(),
                $user,
                2, // Compañía 2
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn(['id' => 9999, 'numero' => 'E-100-2026']);

        $request = Mockery::mock(IngresoExpedienteRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'tipo_tramite' => 'externo',
            'tipo_persona' => 'natural',
            'tipo_doc' => '1',
            'num_doc' => '12345678',
            'nombres' => 'Juan',
            'ape_paterno' => 'Perez',
            'ape_materno' => 'Lopez',
            'direccion' => 'Av Principal 123',
            'telefono' => '999888777',
            'correo' => 'juan@example.com',
            'folios' => 1,
            'destino_modo' => 'areas',
            'gerencia' => 10,
            'subgerencia' => 10,
        ]);
        $request->shouldReceive('file')->with('fle_adjunto')->andReturn(null);
        $request->shouldReceive('input')->with('cdgo_tpo_observado', 0)->andReturn(0);
        $request->shouldReceive('input')->with('cdgo_tpo_trmte', 0)->andReturn(0);
        $request->shouldReceive('input')->with('cdgo_area_observado', 0)->andReturn(0);
        $request->shouldReceive('ip')->andReturn('127.0.0.1');

        $controller = new IngresoExpedienteController($mockService, $mockRepo);
        $response = $controller->store($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame('E-100-2026', $data['numero_expediente']);
    }
}
