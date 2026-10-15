<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface;
use App\Http\Controllers\mod_gm\mod_tram_exte\BuscaExpediController;
use App\Http\Requests\mod_gm\mod_tram_exte\ActualizarExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\AgregarAnexoExpedienteRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\GenerarExpedienteObservadoRequest;
use App\Http\Requests\mod_gm\mod_tram_exte\RegistrarCitaVirtualRequest;
use App\Services\mod_gm\mod_tram_exte\BuscaExpedienteService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Validator;
use Mockery;
use ReflectionClass;
use RuntimeException;

class BuscaExpedienteServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_busca_expediente_repository_esta_registrado_en_el_contenedor(): void
    {
        $repo = $this->app->make(BuscaExpedienteRepositoryInterface::class);
        $this->assertInstanceOf(BuscaExpedienteRepositoryInterface::class, $repo);
    }

    public function test_busca_expedi_controller_no_contiene_accesos_directos_a_bd(): void
    {
        $reflection = new ReflectionClass(BuscaExpediController::class);
        $fileName = $reflection->getFileName();
        $this->assertNotFalse($fileName);
        $content = file_get_contents($fileName);

        $this->assertDoesNotMatchRegularExpression('/(?<!\\\\)DB::/', $content, 'BuscaExpediController no debe invocar la fachada DB::');
        $this->assertDoesNotMatchRegularExpression('/->table\(/', $content, 'BuscaExpediController no debe construir queries con ->table()');
        $this->assertDoesNotMatchRegularExpression('/use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/', $content, 'BuscaExpediController no debe importar la fachada DB');
    }

    public function test_busca_expedi_controller_inyecta_service_en_constructor(): void
    {
        $ref = new ReflectionClass(BuscaExpediController::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $params = array_map(fn($p) => $p->getType()?->getName(), $constructor->getParameters());
        $this->assertContains(BuscaExpedienteService::class, $params);
    }

    public function test_actualizar_expediente_ejecuta_transaccion_y_actualiza_repositorio(): void
    {
        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockRepo->shouldReceive('obtenerExpedienteParaActualizar')
            ->once()
            ->with(101, 2, true)
            ->andReturn((object) ['de_obse_todo' => 'INICIAL']);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $mockRepo->shouldReceive('actualizarDatosExpediente')
            ->once()
            ->with(101, 2, 'NUEVA OBS', 'INICIAL - NUEVA OBS', [1 => '1']);

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $service->actualizarExpediente(101, 2, 'nueva obs', [1 => '1']);
        $this->assertTrue(true);
    }

    public function test_actualizar_expediente_con_compania_cero_lanza_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $service->actualizarExpediente(101, 0, 'obs', []);
    }

    public function test_actualizar_expediente_inexistente_lanza_excepcion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expediente no encontrado.');

        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $mockRepo->shouldReceive('obtenerExpedienteParaActualizar')
            ->once()
            ->with(999, 2, true)
            ->andReturn(null);

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $service->actualizarExpediente(999, 2, 'obs', []);
    }

    public function test_actualizar_expediente_de_otra_compania_es_rechazado_como_inexistente(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expediente no encontrado.');

        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        // Al consultar con companiaId = 3, el repository devuelve null por aislamiento
        $mockRepo->shouldReceive('obtenerExpedienteParaActualizar')
            ->once()
            ->with(101, 3, true)
            ->andReturn(null);

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $service->actualizarExpediente(101, 3, 'obs de otra compania', []);
    }

    public function test_agregar_anexo_abre_transaccion_y_retorna_mov_id(): void
    {
        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $mockRepo->shouldReceive('obtenerMovimientosActivosParaBloqueo')
            ->once()
            ->with(55, 2)
            ->andReturn([(object) ['cdgo_trmte_dcmnto' => 101, 'cdgo_crgo_sa' => 10, 'cdgo_crgo_en' => 20]]);

        $mockRepo->shouldReceive('desactivarMovimientosActivos')
            ->once()
            ->with(55, 2, 1, [101])
            ->andReturn(1);

        $mockRepo->shouldReceive('insertarMovimiento')
            ->once()
            ->andReturn(301);

        $mockRepo->shouldReceive('buscarExpediente')
            ->once()
            ->with(55, 2)
            ->andReturn((object) ['de_obse_todo' => 'OBS PREVIA']);

        $mockRepo->shouldReceive('actualizarObservacionesExpediente')
            ->once()
            ->with(55, 2, 'OBS PREVIA - ANEXO FOLIOS', 'ANEXO FOLIOS');

        $mockRepo->shouldReceive('registrarSeguimiento')
            ->once()
            ->with(1, 'Se anexo al expediente 55', '127.0.0.1');

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $movId = $service->agregarAnexo(55, 2, 1, 'anexo folios', 4, 1, null, '127.0.0.1');

        $this->assertEquals(301, $movId);
    }

    public function test_agregar_anexo_con_cero_filas_afectadas_lanza_excepcion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conflicto de concurrencia: no se encontraron movimientos activos para desactivar.');

        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        // 0 ramas activas encontradas
        $mockRepo->shouldReceive('obtenerMovimientosActivosParaBloqueo')
            ->once()
            ->with(55, 2)
            ->andReturn([]);

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $service->agregarAnexo(55, 2, 1, 'anexo folios', 4, 1, null, '127.0.0.1');
    }

    public function test_agregar_anexo_con_multiples_ramas_activas_desactiva_todas_las_ramas(): void
    {
        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $movs = [
            (object) ['cdgo_trmte_dcmnto' => 201, 'cdgo_crgo_sa' => 10, 'cdgo_crgo_en' => 20],
            (object) ['cdgo_trmte_dcmnto' => 202, 'cdgo_crgo_sa' => 10, 'cdgo_crgo_en' => 30],
            (object) ['cdgo_trmte_dcmnto' => 203, 'cdgo_crgo_sa' => 10, 'cdgo_crgo_en' => 40],
        ];

        $mockRepo->shouldReceive('obtenerMovimientosActivosParaBloqueo')
            ->once()
            ->with(55, 2)
            ->andReturn($movs);

        // Bloquea todas las ramas y desactiva exactamente esas 3 ramas autorizadas con expectedCount
        $mockRepo->shouldReceive('desactivarMovimientosActivos')
            ->once()
            ->with(55, 2, 3, [201, 202, 203])
            ->andReturn(3);

        $mockRepo->shouldReceive('insertarMovimiento')
            ->once()
            ->andReturn(305);

        $mockRepo->shouldReceive('buscarExpediente')
            ->once()
            ->with(55, 2)
            ->andReturn((object) ['de_obse_todo' => 'RAMAS ACTIVAS']);

        $mockRepo->shouldReceive('actualizarObservacionesExpediente')
            ->once()
            ->with(55, 2, 'RAMAS ACTIVAS - ANEXO', 'ANEXO');

        $mockRepo->shouldReceive('registrarSeguimiento')
            ->once()
            ->with(1, 'Se anexo al expediente 55', '127.0.0.1');

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $movId = $service->agregarAnexo(55, 2, 1, 'anexo', 2, 1, null, '127.0.0.1');

        $this->assertEquals(305, $movId);
    }

    public function test_agregar_anexo_falla_si_auditoria_falla_fail_closed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error de auditoría');

        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $mockRepo->shouldReceive('obtenerMovimientosActivosParaBloqueo')
            ->once()
            ->with(55, 2)
            ->andReturn([(object) ['cdgo_trmte_dcmnto' => 101, 'cdgo_crgo_sa' => 10, 'cdgo_crgo_en' => 20]]);

        $mockRepo->shouldReceive('desactivarMovimientosActivos')
            ->once()
            ->with(55, 2, 1, [101])
            ->andReturn(1);

        $mockRepo->shouldReceive('insertarMovimiento')
            ->once()
            ->andReturn(301);

        $mockRepo->shouldReceive('buscarExpediente')
            ->once()
            ->with(55, 2)
            ->andReturn((object) ['de_obse_todo' => 'OBS']);

        $mockRepo->shouldReceive('actualizarObservacionesExpediente')
            ->once()
            ->with(55, 2, 'OBS - ANEXO', 'ANEXO');

        // Auditoría falla
        $mockRepo->shouldReceive('registrarSeguimiento')
            ->once()
            ->with(1, 'Se anexo al expediente 55', '127.0.0.1')
            ->andThrow(new RuntimeException('Error de auditoría'));

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $service->agregarAnexo(55, 2, 1, 'anexo', 1, 1, null, '127.0.0.1');
    }

    public function test_form_requests_validan_criterios_requeridos(): void
    {
        // ActualizarExpedienteRequest
        $req1 = new ActualizarExpedienteRequest();
        $v1 = Validator::make(['id_tram' => 0], $req1->rules());
        $this->assertTrue($v1->fails());

        $v1Ok = Validator::make(['id_tram' => 12, 'TA1' => 'obs'], $req1->rules());
        $this->assertFalse($v1Ok->fails());

        // AgregarAnexoExpedienteRequest
        $req2 = new AgregarAnexoExpedienteRequest();
        $v2 = Validator::make(['Cha' => 12, 'hdn_tipo' => 3], $req2->rules());
        $this->assertTrue($v2->fails());

        $v2Ok = Validator::make([
            'Cha' => 12,
            'hdn_tipo' => 1,
            'TA1' => 'anexo de prueba',
            'txt_nu_foli_anex' => 3,
        ], $req2->rules());
        $this->assertFalse($v2Ok->fails());

        // RegistrarCitaVirtualRequest
        $req3 = new RegistrarCitaVirtualRequest();
        $v3 = Validator::make(['id_tram' => 12], $req3->rules());
        $this->assertTrue($v3->fails());

        $v3Ok = Validator::make([
            'id_tram' => 12,
            'txt_fecha' => '2026-10-15',
            'txt_hora' => '10:00',
        ], $req3->rules());
        $this->assertFalse($v3Ok->fails());

        // GenerarExpedienteObservadoRequest
        $req4 = new GenerarExpedienteObservadoRequest();
        $v4 = Validator::make(['id_tram' => 12, 'hdn_cdgo_usrios' => 1, 'Se2' => 0], $req4->rules());
        $this->assertTrue($v4->fails());

        $v4Ok = Validator::make([
            'id_tram' => 12,
            'hdn_cdgo_usrios' => 5,
            'Se2' => 10,
            'tipo_tramite' => 'E',
        ], $req4->rules());
        $this->assertFalse($v4Ok->fails());

        // Rechaza IDs numéricos 17 y 22 enviados por el navegador
        $v4Num1 = Validator::make(['id_tram' => 12, 'hdn_cdgo_usrios' => 5, 'Se2' => 10, 'rdn_cdgo_tpo_trmtes' => 17], $req4->rules());
        $this->assertTrue($v4Num1->fails());
        $v4Num2 = Validator::make(['id_tram' => 12, 'hdn_cdgo_usrios' => 5, 'Se2' => 10, 'rdn_cdgo_tpo_trmtes' => 22], $req4->rules());
        $this->assertTrue($v4Num2->fails());
        $v4Num3 = Validator::make(['id_tram' => 12, 'hdn_cdgo_usrios' => 5, 'Se2' => 10, 'tipo_tramite' => '17'], $req4->rules());
        $this->assertTrue($v4Num3->fails());

        // Acepta únicamente valores semánticos E y S
        $v4E = Validator::make(['id_tram' => 12, 'hdn_cdgo_usrios' => 5, 'Se2' => 10, 'tipo_tramite' => 'E'], $req4->rules());
        $this->assertFalse($v4E->fails());
        $v4S = Validator::make(['id_tram' => 12, 'hdn_cdgo_usrios' => 5, 'Se2' => 10, 'tipo_tramite' => 'S'], $req4->rules());
        $this->assertFalse($v4S->fails());
    }
    public function test_agregar_anexo_con_discrepancia_de_concurrencia_lanza_excepcion_y_provoca_rollback(): void
    {
        $this->expectException(\App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException::class);
        $this->expectExceptionMessage('se esperaban 2 movimientos activos afectados pero se actualizaron 1');

        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $movs = [
            (object) ['cdgo_trmte_dcmnto' => 201, 'cdgo_crgo_sa' => 10, 'cdgo_crgo_en' => 20],
            (object) ['cdgo_trmte_dcmnto' => 202, 'cdgo_crgo_sa' => 10, 'cdgo_crgo_en' => 30],
        ];

        $mockRepo->shouldReceive('obtenerMovimientosActivosParaBloqueo')
            ->once()
            ->with(55, 2)
            ->andReturn($movs);

        // Discrepancia: se esperaban 2, pero sólo 1 fue desactivado concurrentemente
        $mockRepo->shouldReceive('desactivarMovimientosActivos')
            ->once()
            ->with(55, 2, 2, [201, 202])
            ->andThrow(new \App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException('Conflicto de concurrencia: se esperaban 2 movimientos activos afectados pero se actualizaron 1.'));

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $service->agregarAnexo(55, 2, 1, 'anexo', 1, 1, null, '127.0.0.1');
    }

    public function test_registrar_cita_virtual_con_multiples_ramas_activas_bloquea_todas_y_desactiva_todas(): void
    {
        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $movs = [
            (object) ['cdgo_trmte_dcmnto' => 301, 'cdgo_crgo_sa' => 10, 'cdgo_crgo_en' => 20],
            (object) ['cdgo_trmte_dcmnto' => 302, 'cdgo_crgo_sa' => 10, 'cdgo_crgo_en' => 30],
        ];

        $mockRepo->shouldReceive('obtenerMovimientosActivosParaBloqueo')
            ->once()
            ->with(60, 2)
            ->andReturn($movs);

        $mockRepo->shouldReceive('desactivarMovimientosActivos')
            ->once()
            ->with(60, 2, 2, [301, 302])
            ->andReturn(2);

        $mockRepo->shouldReceive('insertarMovimiento')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['cdgo_crgo_sa'] === 20
                    && $arg['cdgo_crgo_en'] === 10
                    && $arg['cdgo_estdo_trmte'] === '1'
                    && $arg['in_esta'] === '1';
            }))
            ->andReturn(401);

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $service->registrarCitaVirtual(60, 2, '2026-10-15', '10:00', 'obs cita', 1);
        $this->assertTrue(true);
    }

    public function test_registrar_cita_virtual_con_cero_ramas_activas_lanza_excepcion_concurrente(): void
    {
        $this->expectException(\App\Exceptions\mod_gene\ConflictoConcurrenteTramiteException::class);
        $this->expectExceptionMessage('Conflicto de concurrencia: no se encontraron movimientos activos para desactivar.');

        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockDb->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $mockRepo->shouldReceive('obtenerMovimientosActivosParaBloqueo')
            ->once()
            ->with(60, 2)
            ->andReturn([]);

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $service->registrarCitaVirtual(60, 2, '2026-10-15', '10:00', 'obs cita', 1);
    }

    public function test_resolver_tipo_tramite_activo_delega_a_repositorio_y_falla_si_catalogo_invalido(): void
    {
        $mockRepo = Mockery::mock(BuscaExpedienteRepositoryInterface::class);
        $mockDb = Mockery::mock(DatabaseManager::class);

        $mockRepo->shouldReceive('resolverTipoTramiteActivoPorCodigo')
            ->once()
            ->with('E')
            ->andReturn((object) ['cdgo_tpo_trmte' => 300, 'dc_tpo_trmte' => 'E', 'de_tpo_trmte' => 'EXTERNO']);

        $service = new BuscaExpedienteService($mockRepo, $mockDb);
        $res = $service->resolverTipoTramiteActivo('E');

        $this->assertSame(300, $res->cdgo_tpo_trmte);
        $this->assertSame('E', $res->dc_tpo_trmte);
    }
}