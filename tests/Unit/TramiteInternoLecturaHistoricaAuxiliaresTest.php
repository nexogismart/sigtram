<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use App\Http\Controllers\mod_gene\mod_tram_inter\CorreoInternoController;
use App\Http\Requests\mod_gene\mod_tram_inter\AuditoriaDescargasInternoRequest;
use App\Http\Requests\mod_gene\mod_tram_inter\ListarHistoricoInternoRequest;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\Services\mod_gene\mod_tram_inter\CorreoInternoTransaccionalService;
use App\Support\SessionKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class TramiteInternoLecturaHistoricaAuxiliaresTest extends TestCase
{
    private TramiteInternoPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TramiteInternoPolicy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function crearContextoLocal(int $companiaId = 1, int $cargoId = 20, int $areaId = 5, array $permisos = []): ContextoUsuarioAutenticadoDto
    {
        return new ContextoUsuarioAutenticadoDto(
            idLogin: 10,
            idUsuario: 10,
            nombreLogin: 'operador',
            idPerfil: 1,
            idCompania: $companiaId,
            idCargo: $cargoId,
            idArea: $areaId,
            cargosAsignados: [$cargoId],
            permisos: $permisos,
            roles: [1]
        );
    }

    private function crearControllerMock(
        ?TramiteInternoRepositoryInterface $repo = null,
        ?CorreoInternoTransaccionalService $service = null,
        ?TramiteInternoPolicy $policy = null
    ): CorreoInternoController {
        $repoMock = $repo ?? Mockery::mock(TramiteInternoRepositoryInterface::class);
        $serviceMock = $service ?? Mockery::mock(CorreoInternoTransaccionalService::class);
        $policyInst = $policy ?? $this->policy;

        return new CorreoInternoController($serviceMock, $repoMock, $policyInst);
    }

    private function autenticarUsuario(int $companiaId = 1, int $cargoId = 20, int $areaId = 5): Request
    {
        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = $companiaId;
        $this->actingAs($user);

        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, $companiaId);
        $session->put(SessionKeys::USER_CARGO, $cargoId);
        $session->put(SessionKeys::USER_AREA, $areaId);
        $session->put('sigtram_cargo_id', $cargoId);
        $session->put('sigtram_area_id', $areaId);

        $req = Request::create('/', 'GET');
        $req->setLaravelSession($session);
        return $req;
    }

    /**
     * CASO 1: Sesión válida y documento de la misma compañía: puede abrir histórico
     * aunque su cargo y área nunca hayan participado en el documento ni en ningún movimiento.
     */
    public function test_01_historico_autorizado_para_usuario_sin_participacion_en_el_documento(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $docAutorizado = (object) [
            'cdgo_dtos_ntrnos' => 9001,
            'cdgo_cmpnia' => 1,
            'nu_tram_todo' => '00009001-2026',
            'de_tpo_trmte' => 'MEMORANDO',
            'dc_area' => 'GERENCIA',
            'in_esta' => '0', // Documento histórico inactivo
        ];

        $repo->shouldReceive('obtenerDocumentoHistoricoAutorizado')
            ->once()
            ->with(9001, 1)
            ->andReturn($docAutorizado);

        $repo->shouldReceive('obtenerHistorial')
            ->once()
            ->andReturn([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                    'has_more_pages' => false,
                ],
            ]);

        $builderCount = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $builderCount->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $builderCount->shouldReceive('count')->andReturn(0);

        $builderMovs = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $builderMovs->shouldReceive('join')->withAnyArgs()->andReturnSelf();
        $builderMovs->shouldReceive('leftJoin')->withAnyArgs()->andReturnSelf();
        $builderMovs->shouldReceive('where')->withAnyArgs()->andReturnSelf();
        $builderMovs->shouldReceive('orderBy')->withAnyArgs()->andReturnSelf();
        $builderMovs->shouldReceive('offset')->withAnyArgs()->andReturnSelf();
        $builderMovs->shouldReceive('limit')->withAnyArgs()->andReturnSelf();
        $builderMovs->shouldReceive('select')->withAnyArgs()->andReturnSelf();
        $builderMovs->shouldReceive('get')->andReturn(collect([]));

        DB::shouldReceive('table')->andReturnUsing(function (string $table) use ($builderCount, $builderMovs) {
            return str_contains($table, ' as ') ? $builderMovs : $builderCount;
        });

        $req = ListarHistoricoInternoRequest::create('/mod_gene/mod_tram_inter/correo_interno/historial', 'POST', [
            'documento_id' => 9001,
        ]);
        $req->setContainer(app());
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 99); // Cargo ajeno
        $session->put('sigtram_area_id', 88);  // Área ajena
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador_ajeno';
        $user->cdgo_usrios = 55;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->historial($req);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('success', $payload['status']);
    }

    /**
     * CASO 2: Movimiento activo de la misma compañía: U responde 200.
     */
    public function test_02_participantes_movimiento_activo_misma_compania_retorna_200(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $movDto = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 501,
            numero: 'INT-501',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 30,
            areaSalidaId: 5,
            areaEntradaId: 8,
            estadoId: 1,
            inEsta: '1' // Activo
        );

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(501, 1)
            ->andReturn($movDto);

        $repo->shouldReceive('obtenerParticipantesU')
            ->once()
            ->with(501, Mockery::type(ContextoUsuarioAutenticadoDto::class), 1, 25, null)
            ->andReturn([
                'status' => 'success',
                'data' => [['login' => 'user1', 'nombre' => 'Usuario 1']],
                'pagination' => ['total' => 1, 'current_page' => 1],
            ]);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/participantes', 'GET', ['movimiento_id' => 501]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->participantes($req);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('success', $payload['status']);
        $this->assertCount(1, $payload['data']);
    }

    /**
     * CASO 3: Movimiento inactivo de la misma compañía: U responde 200.
     */
    public function test_03_participantes_movimiento_inactivo_misma_compania_retorna_200(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $movDto = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 502,
            numero: 'INT-502',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 30,
            areaSalidaId: 5,
            areaEntradaId: 8,
            estadoId: 2,
            inEsta: '0' // Inactivo
        );

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(502, 1)
            ->andReturn($movDto);

        $repo->shouldReceive('obtenerParticipantesU')
            ->once()
            ->with(502, Mockery::type(ContextoUsuarioAutenticadoDto::class), 1, 25, null)
            ->andReturn([
                'status' => 'success',
                'data' => [['login' => 'user2', 'nombre' => 'Usuario 2']],
                'pagination' => ['total' => 1, 'current_page' => 1],
            ]);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/participantes', 'GET', ['movimiento_id' => 502]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->participantes($req);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('success', $payload['status']);
    }

    /**
     * CASO 4: Documento inactivo de la misma compañía: U responde 200.
     */
    public function test_04_participantes_cuando_documento_inactivo_misma_compania_retorna_200(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $movDto = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 503,
            numero: 'INT-503',
            companiaId: 1,
            cargoSalidaId: 30,
            cargoEntradaId: 20,
            areaSalidaId: 8,
            areaEntradaId: 5,
            estadoId: 2,
            inEsta: '0'
        );

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(503, 1)
            ->andReturn($movDto);

        $repo->shouldReceive('obtenerParticipantesU')
            ->once()
            ->with(503, Mockery::type(ContextoUsuarioAutenticadoDto::class), 1, 25, null)
            ->andReturn([
                'status' => 'success',
                'data' => [['login' => 'user3', 'nombre' => 'Usuario 3']],
                'pagination' => ['total' => 1, 'current_page' => 1],
            ]);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/participantes', 'GET', ['movimiento_id' => 503]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->participantes($req);
        $this->assertSame(200, $res->getStatusCode());
    }

    /**
     * CASO 5: Movimiento entre dos áreas completamente ajenas al usuario:
     * U responde 200 si pertenece a la misma compañía.
     */
    public function test_05_participantes_movimiento_entre_areas_ajenas_misma_compania_retorna_200(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $movDto = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 506,
            numero: 'INT-506',
            companiaId: 1,
            cargoSalidaId: 90, // Ajeno
            cargoEntradaId: 91, // Ajeno
            areaSalidaId: 30,   // Ajena
            areaEntradaId: 31,   // Ajena
            estadoId: 1,
            inEsta: '0'
        );

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(506, 1)
            ->andReturn($movDto);

        $repo->shouldReceive('obtenerParticipantesU')
            ->once()
            ->with(506, Mockery::type(ContextoUsuarioAutenticadoDto::class), 1, 25, null)
            ->andReturn([
                'status' => 'success',
                'data' => [['login' => 'externo_user', 'nombre' => 'Funcionario Ajeno']],
                'pagination' => ['total' => 1, 'current_page' => 1],
            ]);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/participantes', 'GET', ['movimiento_id' => 506]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20); // El usuario tiene cargo 20 (no 90 ni 91)
        $session->put('sigtram_area_id', 5);   // El usuario tiene área 5 (no 30 ni 31)
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->participantes($req);
        $this->assertSame(200, $res->getStatusCode(), 'Debe autorizar a usuarios de la misma entidad sin exigir participación.');
        $payload = $res->getData(true);
        $this->assertSame('success', $payload['status']);
    }

    /**
     * CASO 6: El mismo caso anterior para A (auditoría descargas): responde 200.
     */
    public function test_06_auditoria_descargas_movimiento_entre_areas_ajenas_misma_compania_retorna_200(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $movDto = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 507,
            numero: 'INT-507',
            companiaId: 1,
            cargoSalidaId: 90,
            cargoEntradaId: 91,
            areaSalidaId: 30,
            areaEntradaId: 31,
            estadoId: 1,
            inEsta: '1'
        );

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(507, 1)
            ->andReturn($movDto);

        $repo->shouldReceive('obtenerAuditoriaDescargasPaginada')
            ->once()
            ->with(507, 1, 1, 10)
            ->andReturn([
                'status' => 'success',
                'data' => [['nombre_usuario' => 'Juan', 'ip' => '192.168.1.1']],
                'pagination' => ['total' => 1, 'current_page' => 1, 'last_page' => 1],
                'archivo' => 'doc.pdf',
            ]);

        $req = AuditoriaDescargasInternoRequest::create('/mod_gene/mod_tram_inter/correo_interno/auditoria_descargas', 'GET', [
            'movimiento_id' => 507,
        ]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20); // No coincide con 90 ni 91
        $session->put('sigtram_area_id', 5);   // No coincide con 30 ni 31
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->auditoriaDescargas($req);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('success', $payload['status']);
        $this->assertCount(1, $payload['data']);
    }

    /**
     * CASO 7: El mismo caso anterior para D (voucher): responde 200 con datos del comprobante.
     */
    public function test_07_voucher_movimiento_entre_areas_ajenas_misma_compania_retorna_200(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/voucher/507', 'GET');
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $ctx = $this->crearContextoLocal(1, 20, 5);
        $movAjeno = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 507,
            numero: '000100-2026',
            companiaId: 1,
            cargoSalidaId: 90,
            cargoEntradaId: 91,
            areaSalidaId: 30,
            areaEntradaId: 31,
            estadoId: 1
        );

        $this->assertTrue($this->policy->consultarHistorico($ctx, $movAjeno), 'Policy debe autorizar lectura histórica D a usuarios de la misma compañía.');
    }

    /**
     * CASO 8: Participantes sin usuarios actualmente activos: 200 con lista vacía.
     */
    public function test_08_participantes_sin_usuarios_actuales_destino_retorna_200_vacio(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $movDto = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 504,
            numero: 'INT-504',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 99,
            areaSalidaId: 5,
            areaEntradaId: 8,
            estadoId: 1,
            inEsta: '0'
        );

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(504, 1)
            ->andReturn($movDto);

        $repo->shouldReceive('obtenerParticipantesU')
            ->once()
            ->with(504, Mockery::type(ContextoUsuarioAutenticadoDto::class), 1, 25, null)
            ->andReturn([
                'status' => 'success',
                'data' => [],
                'pagination' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/participantes', 'GET', ['movimiento_id' => 504]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->participantes($req);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('success', $payload['status']);
        $this->assertSame([], $payload['data']);
        $this->assertSame(0, $payload['pagination']['total']);
    }

    /**
     * CASO 9: Auditoría sin registros: 200 con lista vacía.
     */
    public function test_09_auditoria_historica_sin_registros_retorna_200_vacio(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $movDto = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 509,
            numero: 'INT-509',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 30,
            areaSalidaId: 5,
            areaEntradaId: 8,
            estadoId: 2,
            inEsta: '0'
        );

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(509, 1)
            ->andReturn($movDto);

        $repo->shouldReceive('obtenerAuditoriaDescargasPaginada')
            ->once()
            ->with(509, 1, 1, 10)
            ->andReturn([
                'status' => 'success',
                'data' => [],
                'pagination' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
                'archivo' => 'doc_sin_descargas.pdf',
            ]);

        $req = AuditoriaDescargasInternoRequest::create('/mod_gene/mod_tram_inter/correo_interno/auditoria_descargas', 'GET', [
            'movimiento_id' => 509,
        ]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->auditoriaDescargas($req);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('success', $payload['status']);
        $this->assertSame([], $payload['data']);
        $this->assertSame(0, $payload['pagination']['total']);
    }

    /**
     * CASO 10: Movimiento sin archivo: 200 con lista vacía.
     */
    public function test_10_auditoria_historica_sin_archivo_retorna_200_vacio(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $movDto = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 510,
            numero: 'INT-510',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 30,
            areaSalidaId: 5,
            areaEntradaId: 8,
            estadoId: 2,
            inEsta: '0'
        );

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(510, 1)
            ->andReturn($movDto);

        $repo->shouldReceive('obtenerAuditoriaDescargasPaginada')
            ->once()
            ->with(510, 1, 1, 10)
            ->andReturn([
                'status' => 'success',
                'data' => [],
                'pagination' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
                'archivo' => null,
            ]);

        $req = AuditoriaDescargasInternoRequest::create('/mod_gene/mod_tram_inter/correo_interno/auditoria_descargas', 'GET', [
            'movimiento_id' => 510,
        ]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->auditoriaDescargas($req);
        $this->assertSame(200, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('success', $payload['status']);
        $this->assertNull($payload['archivo']);
    }

    /**
     * CASO 11: Documento de otra compañía: 404.
     */
    public function test_11_documento_otra_compania_retorna_404(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $repo->shouldReceive('obtenerDocumentoHistoricoAutorizado')
            ->once()
            ->with(8888, 1)
            ->andReturn(null); // No existe en companiaId = 1

        $req = ListarHistoricoInternoRequest::create('/mod_gene/mod_tram_inter/correo_interno/historial', 'POST', [
            'documento_id' => 8888,
        ]);
        $req->setContainer(app());
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->historial($req);
        $this->assertSame(404, $res->getStatusCode(), 'Documento de otra compañía debe retornar 404.');
    }

    /**
     * CASO 12: Movimiento de otra compañía: 404.
     */
    public function test_12_movimiento_otra_compania_retorna_404(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(505, 1)
            ->andReturn(null);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/participantes', 'GET', ['movimiento_id' => 505]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->participantes($req);
        $this->assertSame(404, $res->getStatusCode(), 'Movimiento de otra compañía debe retornar 404.');
    }

    /**
     * CASO 13: Movimiento inexistente: 404.
     */
    public function test_13_movimiento_inexistente_retorna_404(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $repo->shouldReceive('obtenerMovimientoHistoricoPorId')
            ->once()
            ->with(999999, 1)
            ->andReturn(null);

        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/participantes', 'GET', ['movimiento_id' => 999999]);
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->participantes($req);
        $this->assertSame(404, $res->getStatusCode(), 'Movimiento inexistente debe retornar 404.');
    }

    /**
     * CASO 14: Movimiento que no pertenece al documento indicado: rechazado con 422.
     */
    public function test_14_movimiento_que_no_pertenece_al_documento_rechazado(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $controller = $this->crearControllerMock($repo);

        $repo->shouldReceive('obtenerMovimientoHistoricoAutorizado')
            ->once()
            ->with(999, 1)
            ->andReturn(new TramiteInternoMovimientoDto(
                id: 8888,
                movimientoId: 999,
                numero: 'DOC-8888',
                companiaId: 1,
                cargoSalidaId: 20,
                cargoEntradaId: 21,
                areaSalidaId: 5,
                areaEntradaId: 6,
                estadoId: 1,
                inEsta: '0',
                inCopia: '0'
            ));

        $req = ListarHistoricoInternoRequest::create('/mod_gene/mod_tram_inter/correo_interno/historial', 'POST', [
            'documento_id' => 5001,
            'movimiento_id' => 999,
        ]);
        $req->setContainer(app());
        $session = app('session.store');
        $session->put(SessionKeys::USER_COMPANY, 1);
        $session->put('sigtram_cargo_id', 20);
        $session->put('sigtram_area_id', 5);
        $req->setLaravelSession($session);

        $user = new \App\Models\User();
        $user->cdgo_lgin = 'operador';
        $user->cdgo_usrios = 10;
        $user->cdgo_cmpnia = 1;
        $this->actingAs($user);

        $res = $controller->historial($req);
        $this->assertSame(422, $res->getStatusCode(), 'Movimiento que no pertenece al documento debe ser rechazado con 422.');
    }

    /**
     * CASO 15: Usuario sin sesión o contexto válido: 401.
     */
    public function test_15_usuario_sin_sesion_o_contexto_retorna_401(): void
    {
        $controller = $this->crearControllerMock();

        // Sin usuario autenticado ni sesión
        $req = Request::create('/mod_gene/mod_tram_inter/correo_interno/participantes', 'GET', ['movimiento_id' => 10]);
        $req->setLaravelSession(app('session.store'));

        $res = $controller->participantes($req);
        $this->assertSame(401, $res->getStatusCode(), 'Petición sin sesión debe retornar 401.');
    }

    /**
     * CASO 16: Frontend envía el movimiento_id exacto de la fila.
     */
    public function test_16_frontend_envia_movimiento_id_exacto_de_la_fila(): void
    {
        $partJs = file_get_contents(resource_path('js/tramite-interno/participantes.js'));
        $this->assertStringNotContainsString('|| it.id', $partJs, 'participantes.js no debe recurrir al fallback ambiguo it.id');
        $this->assertStringContainsString('it.cdgo_trmtes_ntrnos || it.movimiento_id', $partJs);

        $histJs = file_get_contents(resource_path('js/tramite-interno/historico.js'));
        $this->assertStringContainsString("movimientoId: String(it.cdgo_trmtes_ntrnos || it.movimiento_id || '')", $histJs);
    }

    /**
     * CASO 17: Frontend limpia contadores y datos anteriores al abrir y al fallar.
     */
    public function test_17_frontend_limpia_contadores_y_datos_al_abrir_y_fallar(): void
    {
        $partJs = file_get_contents(resource_path('js/tramite-interno/participantes.js'));
        // Verifica reseteo de variables al abrir
        $this->assertStringContainsString('state.uModalContext.total = 0', $partJs);
        $this->assertStringContainsString('state.uModalContext.page = 1', $partJs);
        $this->assertStringContainsString('state.uModalContext.lastPage = 1', $partJs);
        // Verifica reseteo en renderizarErrorU
        $this->assertStringContainsString("pagInfo.textContent = 'Página 1 de 1'", $partJs);
        $this->assertStringContainsString('btnPrev.disabled = true', $partJs);
        $this->assertStringContainsString('btnNext.disabled = true', $partJs);

        $histJs = file_get_contents(resource_path('js/tramite-interno/historico.js'));
        $this->assertStringContainsString('auditContext.total = 0', $histJs);
        $this->assertStringContainsString("pagInfo.textContent = 'Página 1 de 1 (0 registros)'", $histJs);
        $this->assertStringContainsString('btnPrev.disabled = true', $histJs);
        $this->assertStringContainsString('btnNext.disabled = true', $histJs);
    }

    /**
     * CASO 18: Las Policies de mutación continúan rechazando movimientos inactivos o cargos incorrectos.
     */
    public function test_18_policies_de_mutacion_rechazan_movimientos_inactivos_y_cargos_incorrectos(): void
    {
        $ctx = $this->crearContextoLocal(1, 20, 5);

        $movInactivo = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 513,
            numero: 'INT-513',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 20,
            areaSalidaId: 5,
            areaEntradaId: 5,
            estadoId: 1,
            inEsta: '0' // INACTIVO
        );

        $this->assertFalse($this->policy->recepcionar($ctx, $movInactivo));
        $this->assertFalse($this->policy->asignar($ctx, $movInactivo, 5));
        $this->assertFalse($this->policy->derivar($ctx, $movInactivo));
        $this->assertFalse($this->policy->finalizar($ctx, $movInactivo));
        $this->assertFalse($this->policy->devolver($ctx, $movInactivo, 'Motivo'));
        $this->assertFalse($this->policy->reiterar($ctx, $movInactivo));
        $this->assertFalse($this->policy->reemplazarFirmado($ctx, $movInactivo));

        // Cargo incorrecto
        $movCargoOtro = new TramiteInternoMovimientoDto(
            id: 100,
            movimientoId: 514,
            numero: 'INT-514',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 99, // Destino es cargo 99, usuario tiene cargo 20
            areaSalidaId: 5,
            areaEntradaId: 5,
            estadoId: 1,
            inEsta: '1'
        );
        $this->assertFalse($this->policy->recepcionar($ctx, $movCargoOtro));
    }

    /**
     * CASO 19: Ninguna operación de escritura utiliza la autorización histórica permisiva.
     */
    public function test_19_ninguna_operacion_de_escritura_utiliza_autorizacion_historica_permisiva(): void
    {
        $policyPath = app_path('Policies/mod_gene/TramiteInternoPolicy.php');
        $code = file_get_contents($policyPath);

        // Los métodos de mutación deben evaluar in_esta === '1'
        $metodosMutacion = ['recepcionar', 'asignar', 'derivar', 'finalizar', 'devolver', 'reiterar', 'reemplazarFirmado'];
        foreach ($metodosMutacion as $m) {
            preg_match("/public function {$m}\([^)]*\): bool\s*\{(.*?)\n    public function /s", $code . "\n    public function ", $match);
            $this->assertNotEmpty($match[1] ?? '', "Método {$m} debe existir");
            $this->assertMatchesRegularExpression(
                '/\$mov(imiento)?->inEsta !== [\'"]1[\'"]/',
                $match[1],
                "Método {$m} debe verificar estrictamente inEsta !== '1'"
            );
            $this->assertStringNotContainsString(
                "consultarHistorico",
                $match[1],
                "Método {$m} no debe invocar ni depender de consultarHistorico"
            );
        }
    }

    /**
     * CASO 20: No hay regresiones en descarga segura ni auditoría.
     */
    public function test_20_no_hay_regresiones_en_descarga_segura_ni_auditoria(): void
    {
        $servicePath = app_path('Services/mod_gene/mod_tram_inter/DescargaSeguraTramiteInternoService.php');
        $this->assertFileExists($servicePath);
        $code = file_get_contents($servicePath);

        // Verifica que conserva protección contra path traversal
        $this->assertStringContainsString('Path Traversal detectado', $code);
        // Verifica que audita en p_sgmto_dscrgas
        $this->assertStringContainsString('p_sgmto_dscrgas', $code);
        // Verifica confinamiento de rutas
        $this->assertStringContainsString('realpath', $code);
    }

    /**
     * Pruebas auxiliares visuales de layout: Sidebars Interno y Externo usan 220px.
     */
    public function test_sidebars_interno_y_externo_usan_220px(): void
    {
        $internoRoot = file_get_contents(resource_path('views/mod_gene/mod_tram_inter/correo-interno.blade.php'));
        $this->assertStringContainsString('xl:grid-cols-[220px_1fr]', $internoRoot, 'Trámite Interno debe usar 220px');

        $externoRoot = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/segi_expedi.blade.php'));
        $this->assertStringContainsString('xl:grid-cols-[220px_1fr]', $externoRoot, 'Trámite Externo debe conservar 220px');
    }

    /**
     * Pruebas auxiliares visuales de layout: Ambos sidebars conservan self-start y no contienen h-full.
     */
    public function test_ambos_sidebars_conservan_self_start_sin_h_full(): void
    {
        $internoSide = file_get_contents(resource_path('views/mod_gene/mod_tram_inter/partials/sidebar.blade.php'));
        $externoSide = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/sidebar.blade.php'));

        $this->assertStringContainsString('self-start', $internoSide);
        $this->assertStringContainsString('self-start', $externoSide);

        $this->assertDoesNotMatchRegularExpression('/(?<![a-zA-Z0-9_-])h-full(?![a-zA-Z0-9_-])/', $internoSide);
        $this->assertDoesNotMatchRegularExpression('/(?<![a-zA-Z0-9_-])h-full(?![a-zA-Z0-9_-])/', $externoSide);
    }

    /**
     * Pruebas auxiliares visuales de layout: Modales externos tienen backdrop blur, estructura equivalente a interno y sin sombreado doble.
     */
    public function test_modales_externos_homologados_sin_sombreado_doble(): void
    {
        $externoModales = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/modales.blade.php'));

        $this->assertStringContainsString('bg-gray-955/70 backdrop-blur-sm', $externoModales);
        $this->assertMatchesRegularExpression('/id="modal-usuarios"[\s\S]*?<div class="fixed inset-0 bg-gray-955\/70 backdrop-blur-sm"[\s\S]*?<div class="relative bg-white rounded-2xl shadow-2xl/', $externoModales);
        $this->assertMatchesRegularExpression('/id="modal-trazabilidad-descargas"[\s\S]*?<div class="fixed inset-0 bg-gray-955\/70 backdrop-blur-sm"[\s\S]*?<div class="relative bg-white rounded-2xl shadow-2xl/', $externoModales);
        $this->assertDoesNotMatchRegularExpression('/id="modal-usuarios"[^>]*class="[^"]*modal-overlay/', $externoModales);
        $this->assertDoesNotMatchRegularExpression('/id="modal-trazabilidad-descargas"[^>]*class="[^"]*modal-overlay/', $externoModales);
    }
}
