<?php

declare(strict_types=1);

namespace Tests\Unit\mod_gene;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\IngresarTramiteRepositoryInterface;
use App\Http\Controllers\mod_gene\mod_tram_inter\IngresarTramiteController;
use App\Http\Requests\mod_gene\mod_tram_inter\StoreTramiteInternoRequest;
use App\Models\User;
use App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository;
use App\Services\mod_gene\mod_tram_inter\IngresarTramiteService;
use App\Support\SessionKeys;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TramiteInternoCompaniaTest extends TestCase
{
    /** Institutional titles omit the sender; personal titles use initials like Word drafts. */
    public static function tiposDeNumeracion(): array
    {
        return ['institucional' => [0, ''], 'personal' => [1, '-jp']];
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise real commit/rollback boundaries without opening PostgreSQL.
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE transaction_probe (id INTEGER PRIMARY KEY)');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function autenticarUsuario(int $loginId = 10): User
    {
        $user = new User();
        $user->cdgo_lgin = $loginId;
        $user->de_lgin = 'testuser';
        Auth::setUser($user);

        return $user;
    }

    /**
     * Demuestra que compañía activa 2 guarda obligatoriamente p_dtos_ntrnos.cdgo_cmpnia = 2.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tiposDeNumeracion')]
    public function test_tramite_interno_guarda_compania_activa_2(int $personal, string $sufijo): void
    {
        $this->autenticarUsuario(10);
        session([SessionKeys::CARGO_ID => 101]);

        $mockRepo = Mockery::mock(IngresarTramiteRepositoryInterface::class);

        // Emisor válido en compañía 2
        $mockRepo->shouldReceive('validarCargoYAreaEmisor')
            ->with(101, 2)
            ->once()
            ->andReturn((object) ['cdgo_crgo' => 101, 'cdgo_area' => 5, 'cdgo_cmpnia' => 2]);

        // Destinos válidos en compañía 2
        $mockRepo->shouldReceive('validarAreasDestinoCompania')
            ->with([20], 2)
            ->once()
            ->andReturn(true);

        $mockRepo->shouldReceive('obtenerUsuarioLogin')->with(10)->andReturn((object) ['no_crto' => 'J. Perez']);
        $mockRepo->shouldReceive('obtenerAnioPorValor')->andReturn((object) ['cdgo_anio' => 2026, 'de_anio' => '2026']);
        $mockRepo->shouldReceive('obtenerInfoTipoTramite')->with(1)->andReturn((object) ['de_tpo_trmte' => 'INFORME', 'in_scncal' => $personal, 'in_cc' => 1]);
        $mockRepo->shouldReceive('obtenerJerarquiaArea')->with(5)->andReturn((object) ['dc_area_prim' => 'GM', 'dc_area' => 'SGT']);
        $mockRepo->shouldReceive('obtenerNuevaSemilla')->andReturn(1);
        $mockRepo->shouldReceive('guardarSemilla')->once();

        // Verificación clave: registrarNuevoTramite DEBE recibir cdgo_cmpnia = 2
        $mockRepo->shouldReceive('registrarNuevoTramite')
            ->once()
            ->with(Mockery::on(function (array $cabecera) use ($sufijo) {
                return isset($cabecera['cdgo_cmpnia'])
                    && $cabecera['cdgo_cmpnia'] === 2
                    && $cabecera['nu_tram_todo'] === '1-2026'
                    && $cabecera['nu_tram'] === 1
                    && $cabecera['nu_tram_cmplto'] === 'INFORME Nº 0001-2026-MDPP-GM-SGT'.$sufijo;
            }))
            ->andReturn(5001);

        $mockRepo->shouldReceive('actualizarRutaAdjunto')->once();
        $mockRepo->shouldReceive('obtenerCargosDestinoPorAreas')->with([20])->andReturn(collect([
            20 => collect([(object) ['cdgo_crgo' => 201, 'nu_orde' => '1']])
        ]));
        $mockRepo->shouldReceive('registrarHistoricoDestinos')->once();

        $service = new IngresarTramiteService($mockRepo);
        $resultado = $service->registrarTramite([
            'sel_cdgo_tpo_trmte'  => 1,
            'txt_nu_smlla_intrno' => '1-2026',
            'txt_fe_rcbdo'        => '2026-10-14 10:00:00',
            'sel_areas'           => [20],
            'txt_de_obser'        => 'Prueba trámite interno empresa 2',
            'rdn_in_prrdad_trmte' => 1,
        ], [], 0, 2);

        $this->assertTrue($resultado['success']);
        $this->assertSame(5001, $resultado['id']);
    }

    /**
     * Demuestra que compañía activa 3 guarda obligatoriamente p_dtos_ntrnos.cdgo_cmpnia = 3.
     */
    public function test_tramite_interno_guarda_compania_activa_3(): void
    {
        $this->autenticarUsuario(11);
        session([SessionKeys::CARGO_ID => 102]);

        $mockRepo = Mockery::mock(IngresarTramiteRepositoryInterface::class);

        // Emisor válido en compañía 3
        $mockRepo->shouldReceive('validarCargoYAreaEmisor')
            ->with(102, 3)
            ->once()
            ->andReturn((object) ['cdgo_crgo' => 102, 'cdgo_area' => 8, 'cdgo_cmpnia' => 3]);

        // Destinos válidos en compañía 3
        $mockRepo->shouldReceive('validarAreasDestinoCompania')
            ->with([30], 3)
            ->once()
            ->andReturn(true);

        $mockRepo->shouldReceive('obtenerUsuarioLogin')->with(11)->andReturn((object) ['no_crto' => 'M. Gomez']);
        $mockRepo->shouldReceive('obtenerAnioPorValor')->andReturn((object) ['cdgo_anio' => 2026, 'de_anio' => '2026']);
        $mockRepo->shouldReceive('obtenerInfoTipoTramite')->with(2)->andReturn((object) ['de_tpo_trmte' => 'MEMORANDO', 'in_scncal' => 0, 'in_cc' => 1]);
        $mockRepo->shouldReceive('obtenerJerarquiaArea')->with(8)->andReturn((object) ['dc_area_prim' => 'GAF', 'dc_area' => 'SGL']);
        $mockRepo->shouldReceive('obtenerNuevaSemilla')->andReturn(1);
        $mockRepo->shouldReceive('guardarSemilla')->once();

        // Verificación clave: registrarNuevoTramite DEBE recibir cdgo_cmpnia = 3
        $mockRepo->shouldReceive('registrarNuevoTramite')
            ->once()
            ->with(Mockery::on(function (array $cabecera) {
                return isset($cabecera['cdgo_cmpnia']) && $cabecera['cdgo_cmpnia'] === 3;
            }))
            ->andReturn(5002);

        $mockRepo->shouldReceive('actualizarRutaAdjunto')->once();
        $mockRepo->shouldReceive('obtenerCargosDestinoPorAreas')->with([30])->andReturn(collect([
            30 => collect([(object) ['cdgo_crgo' => 301, 'nu_orde' => '1']])
        ]));
        $mockRepo->shouldReceive('registrarHistoricoDestinos')->once();

        $service = new IngresarTramiteService($mockRepo);
        $resultado = $service->registrarTramite([
            'sel_cdgo_tpo_trmte'  => 2,
            'txt_nu_smlla_intrno' => '1-2026',
            'txt_fe_rcbdo'        => '2026-10-14 10:00:00',
            'sel_areas'           => [30],
            'txt_de_obser'        => 'Prueba trámite interno empresa 3',
            'rdn_in_prrdad_trmte' => 1,
        ], [], 0, 3);

        $this->assertTrue($resultado['success']);
        $this->assertSame(5002, $resultado['id']);
    }

    /**
     * Demuestra que un valor cdgo_cmpnia manipulado en el request es ignorado y no suplanta la compañía activa de sesión.
     */
    public function test_valor_cdgo_cmpnia_manipulado_en_request_es_ignorado(): void
    {
        $this->autenticarUsuario(10);
        session([
            SessionKeys::USER_COMPANY => 2, // Sesión legítima es compañía 2
            SessionKeys::CARGO_ID => 101,
        ]);

        $mockRepo = Mockery::mock(IngresarTramiteRepositoryInterface::class);
        $mockService = Mockery::mock(IngresarTramiteService::class);

        // El controlador DEBE llamar a service pasando la compañía 2 de la sesión, IGNORANDO el 999 del request
        $mockService->shouldReceive('registrarTramite')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                0,
                2 // Debe ser 2, no 999
            )
            ->andReturn(['success' => true, 'titulo' => 'DOC-01', 'id' => 100]);

        $request = StoreTramiteInternoRequest::create('/mod_gene/mod_tram_inter/ingresar_tramite', 'POST', [
            'sel_cdgo_tpo_trmte'  => 1,
            'txt_nu_smlla_intrno' => '1-2026',
            'txt_fe_rcbdo'        => '2026-10-14 10:00:00',
            'sel_areas'           => [20],
            'txt_de_obser'        => 'Asunto válido',
            'rdn_in_prrdad_trmte' => 1,
            'cdgo_cmpnia'         => 999, // Ataque de inyección de compañía ajena
        ]);
        $request->setLaravelSession(session()->driver());
        $request->setValidator(validator($request->all(), $request->rules()));

        $controller = new IngresarTramiteController($mockService, $mockRepo);
        $response = $controller->store($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Demuestra que sesión sin compañía activa rechaza el registro antes de consultar o insertar.
     */
    public function test_sesion_sin_compania_activa_rechaza_registro(): void
    {
        $this->autenticarUsuario(10);
        session()->forget([SessionKeys::USER_COMPANY, 'usComp']);

        $mockRepo = Mockery::mock(IngresarTramiteRepositoryInterface::class);
        $service = new IngresarTramiteService($mockRepo);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Sesión sin compañía activa válida');

        $service->registrarTramite([
            'sel_cdgo_tpo_trmte' => 1,
        ], [], 0, 0); // 0 indica sin compañía
    }

    /**
     * Demuestra que área de destino de otra compañía es rechazada con ValidationException.
     */
    public function test_area_destino_de_otra_compania_es_rechazada(): void
    {
        $this->autenticarUsuario(10);
        session([SessionKeys::CARGO_ID => 101]);

        $mockRepo = Mockery::mock(IngresarTramiteRepositoryInterface::class);

        // Emisor válido en compañía 2
        $mockRepo->shouldReceive('validarCargoYAreaEmisor')
            ->with(101, 2)
            ->once()
            ->andReturn((object) ['cdgo_crgo' => 101, 'cdgo_area' => 5, 'cdgo_cmpnia' => 2]);

        // Destino área 999 NO pertenece a compañía 2
        $mockRepo->shouldReceive('validarAreasDestinoCompania')
            ->with([999], 2)
            ->once()
            ->andReturn(false);

        // No debe llegar a insertar nada
        $mockRepo->shouldNotReceive('registrarNuevoTramite');

        $service = new IngresarTramiteService($mockRepo);

        $this->expectException(ValidationException::class);

        $service->registrarTramite([
            'sel_cdgo_tpo_trmte'  => 1,
            'txt_nu_smlla_intrno' => '1-2026',
            'txt_fe_rcbdo'        => '2026-10-14 10:00:00',
            'sel_areas'           => [999], // Área de otra compañía
            'txt_de_obser'        => 'Intento cruzado de compañía',
            'rdn_in_prrdad_trmte' => 1,
        ], [], 0, 2);
    }

    /**
     * Demuestra que emisor inactivo o perteneciente a otra compañía es rechazado con AuthorizationException.
     */
    public function test_emisor_inactivo_o_de_otra_compania_es_rechazado(): void
    {
        $this->autenticarUsuario(10);
        session([SessionKeys::CARGO_ID => 999]);

        $mockRepo = Mockery::mock(IngresarTramiteRepositoryInterface::class);

        // Cargo 999 no existe o está inactivo en compañía 2
        $mockRepo->shouldReceive('validarCargoYAreaEmisor')
            ->with(999, 2)
            ->once()
            ->andReturn(null);

        $mockRepo->shouldNotReceive('registrarNuevoTramite');

        $service = new IngresarTramiteService($mockRepo);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('El cargo o área del usuario emisor no pertenece a la compañía activa o se encuentra inactivo.');

        $service->registrarTramite([
            'sel_cdgo_tpo_trmte' => 1,
            'sel_areas' => [20],
        ], [], 0, 2);
    }

    /**
     * Demuestra que si ocurre un fallo durante la creación del histórico, se hace rollback completo y no quedan registros parciales.
     */
    public function test_rollback_completo_ante_fallo_en_historico_destinos(): void
    {
        $this->autenticarUsuario(10);
        session([SessionKeys::CARGO_ID => 101]);

        $mockRepo = Mockery::mock(IngresarTramiteRepositoryInterface::class);

        $mockRepo->shouldReceive('validarCargoYAreaEmisor')->andReturn((object) ['cdgo_crgo' => 101, 'cdgo_area' => 5, 'cdgo_cmpnia' => 2]);
        $mockRepo->shouldReceive('validarAreasDestinoCompania')->andReturn(true);
        $mockRepo->shouldReceive('obtenerUsuarioLogin')->andReturn((object) ['no_crto' => 'J. Perez']);
        $mockRepo->shouldReceive('obtenerAnioPorValor')->andReturn((object) ['cdgo_anio' => 2026, 'de_anio' => '2026']);
        $mockRepo->shouldReceive('obtenerInfoTipoTramite')->andReturn((object) ['de_tpo_trmte' => 'INFORME', 'in_scncal' => 0, 'in_cc' => 1]);
        $mockRepo->shouldReceive('obtenerJerarquiaArea')->andReturn(null);
        $mockRepo->shouldReceive('obtenerNuevaSemilla')->andReturn(1);
        $mockRepo->shouldReceive('guardarSemilla');
        $mockRepo->shouldReceive('registrarNuevoTramite')->andReturnUsing(function () {
            DB::table('transaction_probe')->insert(['id'=>777]);
            return 777;
        });
        $mockRepo->shouldReceive('actualizarRutaAdjunto');
        $mockRepo->shouldReceive('obtenerCargosDestinoPorAreas')->andReturn(collect([
            20 => collect() // Sin cargos asignados
        ]));

        // Simula error en registro de histórico
        $mockRepo->shouldReceive('registrarHistoricoDestinos')
            ->once()
            ->andThrow(new \RuntimeException('Fallo catastrófico al insertar destinos'));

        $service = new IngresarTramiteService($mockRepo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fallo catastrófico al insertar destinos');

        try {
            $service->registrarTramite([
                'sel_cdgo_tpo_trmte'  => 1,
                'txt_nu_smlla_intrno' => '1-2026',
                'txt_fe_rcbdo'        => '2026-10-14 10:00:00',
                'sel_areas'           => [20],
                'txt_de_obser'        => 'Prueba rollback',
                'rdn_in_prrdad_trmte' => 1,
            ], [], 0, 2);
        } finally {
            $this->assertSame(0, DB::table('transaction_probe')->count());
            $this->assertSame(0, DB::connection()->transactionLevel());
        }
    }

    /**
     * Demuestra el aislamiento por compañía en el repositorio de bandejas/histórico:
     * El trámite de compañía 2 solo es visible para compañía 2, y retorna null para compañía 1 o 3 (protección IDOR).
     */
    public function test_aislamiento_multitenant_en_repositorio_tramite_interno(): void
    {
        $mockDb = Mockery::mock(DatabaseManager::class);
        $mockBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);

        $mockDb->shouldReceive('table')->with('sistema.r_trmtes_ntrnos as r')->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('join')->andReturnSelf();
        $mockBuilder->shouldReceive('where')->with('r.cdgo_trmtes_ntrnos', Mockery::any())->andReturnSelf();
        $mockBuilder->shouldReceive('where')->with('r.in_esta', '1')->andReturnSelf();
        $mockBuilder->shouldReceive('where')->with('p.in_esta', '1')->andReturnSelf();
        $mockBuilder->shouldReceive('select')->andReturnSelf();
        $mockBuilder->shouldReceive('first')->andReturn((object) [
            'cdgo_trmtes_ntrnos' => 100,
            'cdgo_dtos_ntrnos'   => 5001,
            'nu_tram_todo'       => 'INFORME Nº 0001-2026-MDPP-GM-SGT',
            'cdgo_cmpnia'        => 2, // Pertenece a compañía 2
            'cdgo_crgo_sa'       => 101,
            'cdgo_crgo_en'       => 201,
            'area_sa'            => 5,
            'cdgo_estdo_trmte'   => 1,
            'in_esta'            => '1',
            'in_copia'           => '1',
        ]);

        $mockDb->shouldReceive('table')->with('sistema.p_crgos')->andReturn($mockBuilder);
        $mockBuilder->shouldReceive('where')->with('cdgo_crgo', 201)->andReturnSelf();
        $mockBuilder->shouldReceive('value')->with('cdgo_area')->andReturn(20);

        $repo = new TramiteInternoRepository($mockDb);

        // 1. Acceso con compañía legítima 2: Debe retornar el DTO del movimiento
        $dto = $repo->obtenerMovimientoPorId(100, 2);
        $this->assertNotNull($dto);
        $this->assertSame(2, $dto->companiaId);
        $this->assertSame('INFORME Nº 0001-2026-MDPP-GM-SGT', $dto->numero);

        // 2. Acceso con compañía 1 o 3: Debe retornar null bloqueando IDOR inter-empresa
        $dtoOtro = $repo->obtenerMovimientoPorId(100, 3);
        $this->assertNull($dtoOtro, 'Un movimiento de compañía 2 debe ser invisible para compañía 3');

        // 3. Fila con cdgo_cmpnia nulo: Debe retornar null sin aplicar jamás fallback a compañía 1
        $mockBuilder->shouldReceive('first')->andReturn((object) [
            'cdgo_trmtes_ntrnos' => 101,
            'cdgo_dtos_ntrnos'   => 5002,
            'nu_tram_todo'       => 'INFORME Nº 0002-2026-MDPP-GM-SGT',
            'cdgo_cmpnia'        => null, // Nulo
            'cdgo_crgo_sa'       => 101,
            'cdgo_crgo_en'       => 201,
            'area_sa'            => 5,
            'cdgo_estdo_trmte'   => 1,
            'in_esta'            => '1',
            'in_copia'           => '1',
        ]);
        $dtoNulo = $repo->obtenerMovimientoPorId(101, 1);
        $this->assertNull($dtoNulo, 'Una fila con cdgo_cmpnia nulo no debe aplicar fallback a compañía 1');
    }
}
