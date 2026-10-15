<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\Http\Requests\mod_gm\mod_tram_exte\RecepcionarExpedienteRequest;
use App\Models\User;
use App\Policies\mod_gm\TramiteExternoPolicy;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;
use App\Services\mod_gm\mod_tram_exte\DocumentoInternoDesdeExpedienteExternoService;
use App\Services\mod_gm\mod_tram_exte\IdempotenciaService;
use App\Services\mod_gm\mod_tram_exte\MaquinaEstadosTramiteExterno;
use App\Services\mod_gm\mod_tram_exte\TramiteExternoService;
use App\Support\SessionKeys;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

/**
 * FASE 2 & FASE 8: Pruebas conductuales de extremo a extremo del contrato de selección externa.
 * Valida la cadena completa: Blade/Alpine -> Form Request -> Controlador -> Servicio -> Repositorio -> Policy.
 */
class ContratoSeleccionExternaConductualTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private User $user;
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

        $this->user = new User([
            'cdgo_usrios' => 864,
            'cdgo_lgin' => 864,
            'de_lgin' => 'hsantos',
            'cdgo_cmpnia' => 2,
            'cdgo_prfle' => 2,
            'in_esta' => '1',
        ]);
        $this->user->cdgo_usrios = 864;
        $this->user->cdgo_lgin = 864;
        $this->user->de_lgin = 'hsantos';
        $this->user->cdgo_cmpnia = 2;
        $this->user->cdgo_prfle = 2;

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

        $this->maquina = new MaquinaEstadosTramiteExterno();
        $this->policy = new TramiteExternoPolicy();
        $this->idempotencia = new IdempotenciaService(Cache::store('array'));

        $this->service = new TramiteExternoService(
            $this->repository,
            $this->maquina,
            $this->policy,
            $this->idempotencia,
            $this->db,
            $this->documentoInternoService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * CASO 1 & 2: Selección individual y "Marcar todos" usan exactamente el mismo identificador (movimiento_id)
     * y nunca cambian entre movimiento y documento en la vista Blade / Alpine.
     */
    public function test_caso_1_y_2_seleccion_individual_y_marcar_todos_usan_canonicamente_movimiento_id(): void
    {
        $bladePath = resource_path('views/mod_gm/mod_tram_exte/partials_segi/filtros-tabla.blade.php');
        $this->assertFileExists($bladePath);
        $bladeContent = (string) file_get_contents($bladePath);

        // 1. Clave de fila :key debe usar exclusivamente movimiento_id (sin fallback a idx)
        $this->assertStringContainsString(':key="item.movimiento_id || item.cdgo_trmte_dcmnto"', $bladeContent);
        $this->assertStringNotContainsString(':key="item.cdgo_trmte_dcmnto || idx"', $bladeContent);
        $this->assertStringNotContainsString(':key="idx"', $bladeContent);

        // 2. Checkbox individual debe ligar el movimiento_id
        $this->assertStringContainsString(':value="item.movimiento_id || item.cdgo_trmte_dcmnto"', $bladeContent);
        $this->assertStringNotContainsString(':value="item.cdgo_dto_trmte"', $bladeContent);

        // 3. toggleSelectAll en bandejas.js debe guardar movimiento_id (no cdgo_dto_trmte)
        $bandejasJsPath = resource_path('js/tramite-externo/bandejas.js');
        $this->assertFileExists($bandejasJsPath);
        $bandejasJs = (string) file_get_contents($bandejasJsPath);

        $this->assertStringContainsString('this.selectedIds = this.items.map((i) => i.movimiento_id || i.cdgo_trmte_dcmnto)', $bandejasJs);
        $this->assertStringNotContainsString('this.selectedIds = this.items.map((i) => i.cdgo_dto_trmte)', $bandejasJs);
    }

    /**
     * CASO 3: Documento con dos ramas activas independientes.
     * Seleccionar una rama opera exclusivamente sobre su movimiento_id sin alterar la otra rama del mismo documento.
     */
    public function test_caso_3_documento_con_dos_ramas_activas_opera_solo_sobre_la_rama_seleccionada(): void
    {
        $documentoId = 50;
        $movimientoRamaA = 101;
        $movimientoRamaB = 102;

        $contexto = $this->crearContexto('hsantos', 864, 2, 20, 8, [2], [], false, 864);

        $movimientoDtoA = new ExpedienteMovimientoDto(
            expedienteId: $documentoId,
            numeroExpediente: 'EXP-2026-00050',
            movimientoId: $movimientoRamaA,
            companiaId: 2,
            cargoSalidaId: 10,
            cargoEntradaId: 20,
            areaSalidaId: 4,
            areaEntradaId: 8,
            estadoId: 1, // PENDIENTE
            inEsta: '1'
        );

        // El repositorio debe consultar exclusivamente por r.cdgo_trmte_dcmnto IN (101)
        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->once()
            ->with([$movimientoRamaA], 2)
            ->andReturn([$movimientoDtoA]);

        // Simular update exitoso de cierre de la rama A en el repositorio
        $this->repository->shouldReceive('desactivarMovimientoExternoEsperado')
            ->once()
            ->with($movimientoRamaA)
            ->andReturn(1);

        $this->repository->shouldReceive('crearMovimientoExterno')
            ->once()
            ->andReturn(301);

        $resultado = $this->service->recepcionar($contexto, [$movimientoRamaA], '2026-10-13 10:00:00', null, 'idem-rama-a');

        $this->assertSame(1, $resultado['procesados']);
    }

    /**
     * CASO 4: Form Request valida movimiento_ids y rechaza arrays vacíos o campos no conformes.
     */
    public function test_caso_4_form_request_valida_movimiento_ids_correctamente(): void
    {
        // 1. Envío explícito con movimiento_ids
        $reglas = (new RecepcionarExpedienteRequest())->rules();
        $validadorValido = Validator::make([
            'movimiento_ids' => [101, 102],
            'fecha' => '2026-10-13 10:00:00',
        ], $reglas);
        $this->assertTrue($validadorValido->passes());

        // 2. Envío sin IDs falla validación
        $validadorInvalido = Validator::make([
            'fecha' => '2026-10-13 10:00:00',
        ], $reglas);
        $this->assertFalse($validadorInvalido->passes());
    }

    /**
     * CASO 5: Colisión numérica: un movimiento_id (ej. 500) coincide numéricamente con el cdgo_dto_trmte de otro expediente.
     * El repositorio filtra estrictamente por r.cdgo_trmte_dcmnto y nunca por r.cdgo_dto_trmte.
     */
    public function test_caso_5_colision_numerica_busca_estrictamente_por_cdgo_trmte_dcmnto(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos as r/'))->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        
        // Debe filtrar por r.cdgo_trmte_dcmnto, NUNCA por r.cdgo_dto_trmte
        $builder->shouldReceive('whereIn')
            ->once()
            ->with('r.cdgo_trmte_dcmnto', [500])
            ->andReturnSelf();

        $builder->shouldReceive('where')->with('p.cdgo_cmpnia', 2)->andReturnSelf();
        $builder->shouldReceive('where')->with('r.in_esta', '1')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('orderBy')->with('r.cdgo_trmte_dcmnto', 'asc')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmte_dcmnto' => 500,
                'cdgo_dto_trmte' => 999, // Documento real 999, NO 500
                'nu_dcmnto' => '00000999-2026',
                'cdgo_estdo_trmte' => 1,
                'dc_estdo_trmte' => 'PENDIENTE',
                'cdgo_crgo_sa' => 10,
                'cdgo_crgo_en' => 20,
                'cdgo_cmpnia' => 2,
                'in_esta' => '1',
                'fe_rcbdo_trmte' => '2026-10-10 10:00:00',
                'de_obse' => 'OK',
            ],
        ]));

        $builderCargos = Mockery::mock(Builder::class);
        $db->shouldReceive('table')->with(Mockery::pattern('/p_crgos/'))->andReturn($builderCargos);
        $builderCargos->shouldReceive('whereIn')->with('cdgo_crgo', Mockery::any())->andReturnSelf();
        $builderCargos->shouldReceive('pluck')->with('cdgo_area', 'cdgo_crgo')->andReturn(collect([10 => 4, 20 => 8]));

        $repo = new TramiteExternoRepository($db);
        $movimientos = $repo->obtenerMovimientosPorMovimientoIds([500], 2);

        $this->assertCount(1, $movimientos);
        $this->assertSame(500, $movimientos[0]->movimientoId);
        $this->assertSame(999, $movimientos[0]->expedienteId); // Confirma que no confundió el documento
    }

    /**
     * CASO 6: Movimiento de otra compañía es rechazado por aislamiento multi-tenant.
     */
    public function test_caso_6_movimiento_de_otra_compania_es_rechazado(): void
    {
        $contexto = $this->crearContexto('hsantos', 864, 2, 20, 8, [2], [], false, 864);

        // Movimiento 800 pertenece a compañía 99, por lo que el repositorio filtrando por compañía 2 no lo encuentra
        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->once()
            ->with([800], 2)
            ->andReturn([]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->recepcionar($contexto, [800], '2026-10-13 10:00:00', null, 'idem-comp-ajena');
    }

    /**
     * CASO 7 & 8: Policy granular autoriza únicamente movimientos dirigidos al cargo del funcionario.
     */
    public function test_caso_7_y_8_policy_autoriza_movimiento_por_cargo_destino(): void
    {
        $movimientoAutorizado = new ExpedienteMovimientoDto(
            expedienteId: 50,
            numeroExpediente: 'EXP-001',
            movimientoId: 101,
            companiaId: 2,
            cargoSalidaId: 10,
            cargoEntradaId: 20, // Coincide con cargo activo
            areaSalidaId: 4,
            areaEntradaId: 8,
            estadoId: 1, // PENDIENTE
            inEsta: '1'
        );

        $movimientoNoAutorizado = new ExpedienteMovimientoDto(
            expedienteId: 50,
            numeroExpediente: 'EXP-002',
            movimientoId: 102,
            companiaId: 2,
            cargoSalidaId: 10,
            cargoEntradaId: 99, // Cargo ajeno
            areaSalidaId: 4,
            areaEntradaId: 8,
            estadoId: 1, // PENDIENTE
            inEsta: '1'
        );

        $contexto = $this->crearContexto('hsantos', 864, 2, 20, 8, [2], [], false, 864);

        $this->assertTrue($this->policy->recepcionar($contexto, $movimientoAutorizado));
        $this->assertFalse($this->policy->recepcionar($contexto, $movimientoNoAutorizado));
    }

    /**
     * CASO 9: Conflicto de concurrencia o carrera en update revierte la transacción.
     */
    public function test_caso_9_conflicto_de_concurrencia_lanza_excepcion_y_revierte(): void
    {
        $contexto = $this->crearContexto('hsantos', 864, 2, 20, 8, [2], [], false, 864);

        $movimiento = new ExpedienteMovimientoDto(
            expedienteId: 70,
            numeroExpediente: 'EXP-070',
            movimientoId: 201,
            companiaId: 2,
            cargoSalidaId: 10,
            cargoEntradaId: 20,
            areaSalidaId: 4,
            areaEntradaId: 8,
            estadoId: 1,
            inEsta: '1'
        );

        $this->repository->shouldReceive('obtenerMovimientosVigentesParaBloqueo')
            ->once()
            ->with([201], 2)
            ->andReturn([$movimiento]);

        // Simular que afectados es 0 porque otro proceso ya cerró o modificó el registro
        $this->repository->shouldReceive('desactivarMovimientoExternoEsperado')
            ->once()
            ->with(201)
            ->andReturn(0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conflicto de concurrencia: el movimiento 201 fue modificado concurrentemente.');

        $this->service->recepcionar($contexto, [201], '2026-10-13 10:00:00', null, 'idem-concurrencia');
    }

    /**
     * CASO 10: Histórico se solicita con documento_id y retorna todos los movimientos históricos.
     */
    public function test_caso_10_historico_se_solicita_con_documento_id_y_retorna_todos_los_movimientos(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $movimientoActivo = new ExpedienteMovimientoDto(
            expedienteId: 50,
            numeroExpediente: 'EXP-050',
            movimientoId: 101,
            companiaId: 2,
            cargoSalidaId: 10,
            cargoEntradaId: 20,
            areaSalidaId: 4,
            areaEntradaId: 8,
            estadoId: 1,
            inEsta: '1'
        );

        $repoMock->shouldReceive('obtenerMovimientosHistoricosPorExpediente')
            ->once()
            ->with(50, 2)
            ->andReturn([$movimientoActivo]);

        $repoMock->shouldReceive('obtenerHistoricoMovimientos')
            ->once()
            ->with(50)
            ->andReturn([
                [
                    'movimiento_id' => 101,
                    'documento_id' => 50,
                    'fecha' => '2026-10-10 10:00:00',
                    'estado' => 'PENDIENTE',
                ],
                [
                    'movimiento_id' => 102,
                    'documento_id' => 50,
                    'fecha' => '2026-10-11 11:00:00',
                    'estado' => 'RECIBIDO',
                ],
            ]);

        $this->app->instance(TramiteExternoRepositoryInterface::class, $repoMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 2,
                SessionKeys::USER_CARGO => 20,
                SessionKeys::USER_AREA => 8,
            ])
            ->postJson(route('mod_gm.segi_expedi.historial'), [
                'id' => 50,
                'documento_id' => 50,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
        
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    /**
     * CASO 11: Auditoría de descargas y participantes se solicitan estrictamente con movimiento_id.
     */
    public function test_caso_11_auditoria_descargas_y_participantes_se_solicitan_con_movimiento_id(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        
        // 1. Auditoría valida movimiento_id
        $repoMock->shouldReceive('obtenerMovimientoPorMovimientoId')
            ->once()
            ->with(301, 2)
            ->andReturn(new ExpedienteMovimientoDto(
                expedienteId: 60,
                numeroExpediente: 'EXP-060',
                movimientoId: 301,
                companiaId: 2,
                cargoSalidaId: 10,
                cargoEntradaId: 20,
                areaSalidaId: 4,
                areaEntradaId: 8,
                estadoId: 1,
                inEsta: '1'
            ));

        $repoMock->shouldReceive('obtenerAuditoriaDescargasPaginada')
            ->once()
            ->with(301, 2, 1, 10)
            ->andReturn([
                'status' => 'success',
                'data' => [],
                'pagination' => ['total' => 0, 'current_page' => 1, 'per_page' => 10],
            ]);

        $this->app->instance(TramiteExternoRepositoryInterface::class, $repoMock);

        $response = $this->actingAs($this->user)
            ->withSession([
                SessionKeys::USER_COMPANY => 2,
                SessionKeys::USER_CARGO => 20,
                SessionKeys::USER_AREA => 8,
            ])
            ->getJson(route('mod_gm.segi_expedi.auditoria_descargas', ['movimiento_id' => 301]));

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }
}
