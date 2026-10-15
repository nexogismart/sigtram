<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gm\ExpedienteMovimientoDto;
use App\DTOs\Support\ParticipanteUDto;
use App\DTOs\Support\ResumenUDto;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use Tests\TestCase;

class ParticipantesUCompartidoTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * CEF-10: ParticipanteUDto y ResumenUDto cumplen con el contrato de Spec 10
     * sin exponer datos sensibles PII.
     */
    public function test_dtos_cumplen_contrato_sin_pii(): void
    {
        $part = new ParticipanteUDto(
            login: 'jsalazar',
            nombre: 'José Salazar',
            cargo: 'Soporte OTI',
            area: 'OTI',
            estadoAsignacion: 'Activo'
        );

        $arr = $part->toArray();
        $this->assertSame('jsalazar', $arr['login']);
        $this->assertSame('José Salazar', $arr['nombre']);
        $this->assertSame('Soporte OTI', $arr['cargo']);
        $this->assertSame('OTI', $arr['area']);
        $this->assertSame('Activo', $arr['estado_asignacion']);

        // Verificación estricta de NO PII
        $this->assertArrayNotHasKey('dni', $arr);
        $this->assertArrayNotHasKey('nu_docu', $arr);
        $this->assertArrayNotHasKey('correo', $arr);
        $this->assertArrayNotHasKey('de_mail', $arr);
        $this->assertArrayNotHasKey('telefono', $arr);
        $this->assertArrayNotHasKey('nu_tele', $arr);
        $this->assertArrayNotHasKey('direccion', $arr);

        $resumen = new ResumenUDto(
            movimientoId: 100,
            actor: ['login' => 'jsalazar', 'nombre' => 'José Salazar'],
            destino: ['cargo_id' => 514, 'cargo' => 'Jefatura'],
            usuariosResumen: ['total' => 12, 'preview' => [], 'fuente' => 'asignacion_actual']
        );

        $resArr = $resumen->toArray();
        $this->assertSame(100, $resArr['movimiento_id']);
        $this->assertSame('asignacion_actual', $resArr['usuarios_resumen']['fuente']);
        $this->assertSame(12, $resArr['usuarios_resumen']['total']);
    }

    /**
     * CEF-10: TramiteExternoRepository::obtenerParticipantesU pagina y busca sin N+1.
     */
    public function test_externo_obtener_participantes_u_paginado_y_busqueda(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderMov = Mockery::mock(Builder::class);
        $builderCargo = Mockery::mock(Builder::class);
        $builderUsers = Mockery::mock(Builder::class);

        // 1. Mock de obtenerMovimientoPorMovimientoId
        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos/'))->andReturn($builderMov);
        $builderMov->shouldReceive('join')->andReturnSelf();
        $builderMov->shouldReceive('where')->times(2)->andReturnSelf();
        $builderMov->shouldReceive('select')->andReturnSelf();
        $builderMov->shouldReceive('first')->andReturn((object) [
            'cdgo_dto_trmte'    => 10,
            'nu_expe_todo'      => 'EXP-10',
            'cdgo_trmte_dcmnto' => 500,
            'cdgo_cmpnia'       => 1,
            'cdgo_crgo_sa'      => 105,
            'cdgo_crgo_en'      => 200,
            'cdgo_estdo_trmte'  => 1,
            'in_esta'           => '1',
            'fe_rcbdo_trmte'    => '2026-10-10 10:00:00',
            'de_obse'           => null,
        ]);

        $db->shouldReceive('table')->with(Mockery::pattern('/p_crgos$/'))->andReturn($builderCargo);
        $builderCargo->shouldReceive('where')->andReturnSelf();
        $builderCargo->shouldReceive('value')->andReturn(15);

        // 2. Mock de la consulta de participantes en r_crgos_lgin
        $db->shouldReceive('table')->with(Mockery::pattern('/r_crgos_lgin/'))->andReturn($builderUsers);
        $builderUsers->shouldReceive('join')->times(3)->andReturnSelf();
        $builderUsers->shouldReceive('leftJoin')->andReturnSelf();
        $builderUsers->shouldReceive('where')->times(3)->andReturnSelf();
        $builderUsers->shouldReceive('where')->with(Mockery::type('Closure'))->andReturnSelf();
        $builderUsers->shouldReceive('distinct')->andReturnSelf();
        $builderUsers->shouldReceive('count')->andReturn(12);
        $builderUsers->shouldReceive('select')->andReturnSelf();
        $builderUsers->shouldReceive('orderBy')->andReturnSelf();
        $builderUsers->shouldReceive('offset')->with(0)->andReturnSelf();
        $builderUsers->shouldReceive('limit')->with(25)->andReturnSelf();
        $builderUsers->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_lgin' => 'agarcia',
                'no_crto'   => 'Ana García',
                'de_crgo'   => 'Especialista',
                'de_area'   => 'Sistemas',
            ],
        ]));

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);

        $res = $repo->obtenerParticipantesU(500, $contexto, 1, 25, 'Ana');

        $this->assertSame('success', $res['status']);
        $this->assertCount(1, $res['data']);
        $this->assertSame('agarcia', $res['data'][0]['login']);
        $this->assertSame('Ana García', $res['data'][0]['nombre']);
        $this->assertSame(12, $res['pagination']['total']);
        $this->assertSame(1, $res['pagination']['current_page']);
        $this->assertSame('asignacion_actual', $res['meta']['fuente']);
    }

    /**
     * CEF-10: Retorna 404 si el movimiento no pertenece a la compañía.
     */
    public function test_externo_obtener_participantes_retorna_404_si_movimiento_no_existe(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderMov = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos/'))->andReturn($builderMov);
        $builderMov->shouldReceive('join')->andReturnSelf();
        $builderMov->shouldReceive('where')->times(2)->andReturnSelf();
        $builderMov->shouldReceive('select')->andReturnSelf();
        $builderMov->shouldReceive('first')->andReturn(null);

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);

        $res = $repo->obtenerParticipantesU(9999, $contexto, 1, 25);

        $this->assertSame('error', $res['status']);
        $this->assertSame(404, $res['code']);
    }

    /**
     * CEF-10: TramiteInternoRepository::obtenerParticipantesU pagina y busca usuarios del cargo destino.
     */
    public function test_interno_obtener_participantes_u_paginado_y_busqueda(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderMov = Mockery::mock(Builder::class);
        $builderCargo = Mockery::mock(Builder::class);
        $builderUsers = Mockery::mock(Builder::class);

        // 1. Mock de obtenerMovimientoPorId
        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_ntrnos/'))->andReturn($builderMov);
        $builderMov->shouldReceive('join')->andReturnSelf();
        $builderMov->shouldReceive('where')->times(2)->andReturnSelf();
        $builderMov->shouldReceive('select')->andReturnSelf();
        $builderMov->shouldReceive('first')->andReturn((object) [
            'cdgo_trmtes_ntrnos' => 300,
            'cdgo_dtos_ntrnos'   => 150,
            'nu_tram_todo'       => 'INT-150',
            'cdgo_cmpnia'        => 1,
            'cdgo_crgo_sa'       => 20,
            'cdgo_crgo_en'       => 35,
            'area_sa'            => 5,
            'cdgo_estdo_trmte'   => 1,
            'in_esta'            => '1',
            'fe_rcbdo'           => '2026-10-10 11:00:00',
            'de_obser'           => null,
            'in_copia'           => '0',
        ]);

        $db->shouldReceive('table')->with(Mockery::pattern('/p_crgos$/'))->andReturn($builderCargo);
        $builderCargo->shouldReceive('where')->andReturnSelf();
        $builderCargo->shouldReceive('value')->andReturn(8);

        // 2. Mock de la consulta de participantes en r_crgos_lgin
        $db->shouldReceive('table')->with(Mockery::pattern('/r_crgos_lgin/'))->andReturn($builderUsers);
        $builderUsers->shouldReceive('join')->times(3)->andReturnSelf();
        $builderUsers->shouldReceive('leftJoin')->andReturnSelf();
        $builderUsers->shouldReceive('where')->times(3)->andReturnSelf();
        $builderUsers->shouldReceive('where')->with(Mockery::type('Closure'))->andReturnSelf();
        $builderUsers->shouldReceive('distinct')->andReturnSelf();
        $builderUsers->shouldReceive('count')->andReturn(8);
        $builderUsers->shouldReceive('select')->andReturnSelf();
        $builderUsers->shouldReceive('orderBy')->andReturnSelf();
        $builderUsers->shouldReceive('offset')->with(0)->andReturnSelf();
        $builderUsers->shouldReceive('limit')->with(25)->andReturnSelf();
        $builderUsers->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_lgin' => 'cflores',
                'no_crto'   => 'Carlos Flores',
                'de_crgo'   => 'Asistente',
                'de_area'   => 'Logística',
            ],
        ]));

        $contexto = $this->crearContexto('operador1', 10, 1, 20, 5, [20]);

        $repo = new \App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository($db);
        $res = $repo->obtenerParticipantesU(300, $contexto, 1, 25, 'Carlos');

        $this->assertSame('success', $res['status']);
        $this->assertCount(1, $res['data']);
        $this->assertSame('cflores', $res['data'][0]['login']);
        $this->assertSame('Carlos Flores', $res['data'][0]['nombre']);
        $this->assertSame(8, $res['pagination']['total']);
        $this->assertSame('asignacion_actual', $res['meta']['fuente']);
    }

    /**
     * CEF-10: En Trámite Interno, cuando cargo origen es igual a cargo destino,
     * no se reemplaza por el actor sino que se consultan todos los usuarios activos del cargo destino.
     */
    public function test_interno_obtener_participantes_cuando_origen_igual_destino_consulta_todos_usuarios_activos(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderMov = Mockery::mock(Builder::class);
        $builderCargo = Mockery::mock(Builder::class);
        $builderUsers = Mockery::mock(Builder::class);

        // Movimiento interno donde cargo_sa == cargo_en (ej. pase interno de área o recepción)
        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_ntrnos/'))->andReturn($builderMov);
        $builderMov->shouldReceive('join')->andReturnSelf();
        $builderMov->shouldReceive('where')->times(2)->andReturnSelf();
        $builderMov->shouldReceive('select')->andReturnSelf();
        $builderMov->shouldReceive('first')->andReturn((object) [
            'cdgo_trmtes_ntrnos' => 305,
            'cdgo_dtos_ntrnos'   => 150,
            'nu_tram_todo'       => 'INT-150',
            'cdgo_cmpnia'        => 1,
            'cdgo_crgo_sa'       => 20,
            'cdgo_crgo_en'       => 20, // ORIGEN == DESTINO
            'area_sa'            => 5,
            'cdgo_estdo_trmte'   => 2,
            'in_esta'            => '1',
            'fe_rcbdo'           => '2026-10-10 11:30:00',
            'de_obser'           => null,
            'in_copia'           => '0',
        ]);

        $db->shouldReceive('table')->with(Mockery::pattern('/p_crgos$/'))->andReturn($builderCargo);
        $builderCargo->shouldReceive('where')->andReturnSelf();
        $builderCargo->shouldReceive('value')->andReturn(5);

        // Verifica que se consulten los usuarios asignados al cargo 20 (no solo el actor)
        $db->shouldReceive('table')->with(Mockery::pattern('/r_crgos_lgin/'))->andReturn($builderUsers);
        $builderUsers->shouldReceive('join')->times(3)->andReturnSelf();
        $builderUsers->shouldReceive('leftJoin')->andReturnSelf();
        $builderUsers->shouldReceive('where')->with('rl.cdgo_crgo', 20)->andReturnSelf();
        $builderUsers->shouldReceive('where')->with('rl.in_esta', '1')->andReturnSelf();
        $builderUsers->shouldReceive('where')->with('l.in_esta', '1')->andReturnSelf();
        $builderUsers->shouldReceive('distinct')->andReturnSelf();
        $builderUsers->shouldReceive('count')->andReturn(3);
        $builderUsers->shouldReceive('select')->andReturnSelf();
        $builderUsers->shouldReceive('orderBy')->andReturnSelf();
        $builderUsers->shouldReceive('offset')->with(0)->andReturnSelf();
        $builderUsers->shouldReceive('limit')->with(25)->andReturnSelf();
        $builderUsers->shouldReceive('get')->andReturn(collect([
            (object) ['cdgo_lgin' => 'u1', 'no_crto' => 'Usuario 1', 'de_crgo' => 'Cargo A', 'de_area' => 'Area X'],
            (object) ['cdgo_lgin' => 'u2', 'no_crto' => 'Usuario 2', 'de_crgo' => 'Cargo A', 'de_area' => 'Area X'],
            (object) ['cdgo_lgin' => 'u3', 'no_crto' => 'Usuario 3', 'de_crgo' => 'Cargo A', 'de_area' => 'Area X'],
        ]));

        $contexto = $this->crearContexto('u1', 10, 1, 20, 5, [20]);

        $repo = new \App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository($db);
        $res = $repo->obtenerParticipantesU(305, $contexto, 1, 25);

        $this->assertSame('success', $res['status']);
        $this->assertCount(3, $res['data']);
        $this->assertSame(3, $res['pagination']['total']);
    }

    /**
     * CEF-10 / FASE 3: Si no existen destinatarios activos para el cargo, retorna estado vacío legítimo
     * (data = [], total = 0, status = 'success') sin lanzar excepción ni mensaje de error.
     */
    public function test_participantes_retorna_estado_vacio_legitimo_sin_destinatarios(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderMov = Mockery::mock(Builder::class);
        $builderCargo = Mockery::mock(Builder::class);
        $builderUsers = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_ntrnos/'))->andReturn($builderMov);
        $builderMov->shouldReceive('join')->andReturnSelf();
        $builderMov->shouldReceive('where')->times(2)->andReturnSelf();
        $builderMov->shouldReceive('select')->andReturnSelf();
        $builderMov->shouldReceive('first')->andReturn((object) [
            'cdgo_trmtes_ntrnos' => 310,
            'cdgo_dtos_ntrnos'   => 160,
            'nu_tram_todo'       => 'INT-160',
            'cdgo_cmpnia'        => 1,
            'cdgo_crgo_sa'       => 20,
            'cdgo_crgo_en'       => 99, // Cargo sin usuarios
            'area_sa'            => 5,
            'cdgo_estdo_trmte'   => 1,
            'in_esta'            => '1',
            'fe_rcbdo'           => null,
            'de_obser'           => null,
            'in_copia'           => '0',
        ]);

        $db->shouldReceive('table')->with(Mockery::pattern('/p_crgos$/'))->andReturn($builderCargo);
        $builderCargo->shouldReceive('where')->andReturnSelf();
        $builderCargo->shouldReceive('value')->andReturn(8);

        $db->shouldReceive('table')->with(Mockery::pattern('/r_crgos_lgin/'))->andReturn($builderUsers);
        $builderUsers->shouldReceive('join')->times(3)->andReturnSelf();
        $builderUsers->shouldReceive('leftJoin')->andReturnSelf();
        $builderUsers->shouldReceive('where')->times(3)->andReturnSelf();
        $builderUsers->shouldReceive('distinct')->andReturnSelf();
        $builderUsers->shouldReceive('count')->andReturn(0);
        $builderUsers->shouldReceive('select')->andReturnSelf();
        $builderUsers->shouldReceive('orderBy')->andReturnSelf();
        $builderUsers->shouldReceive('offset')->with(0)->andReturnSelf();
        $builderUsers->shouldReceive('limit')->with(25)->andReturnSelf();
        $builderUsers->shouldReceive('get')->andReturn(collect([]));

        $contexto = $this->crearContexto('operador1', 10, 1, 20, 5, [20]);

        $repo = new \App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository($db);
        $res = $repo->obtenerParticipantesU(310, $contexto, 1, 25);

        $this->assertSame('success', $res['status']);
        $this->assertIsArray($res['data']);
        $this->assertEmpty($res['data']);
        $this->assertSame(0, $res['pagination']['total']);
        $this->assertSame(0, $res['meta']['total']);
    }

    /**
     * FASE 3: El endpoint rechaza movimiento_id igual a 0 o negativo.
     */
    public function test_participantes_rechaza_movimiento_id_cero_o_invalido(): void
    {
        $user = new \App\Models\User([
            'cdgo_usrios' => 10,
            'cdgo_lgin' => 'operador',
            'cdgo_cmpnia' => 1,
            'cdgo_prfle' => 1,
            'in_esta' => '1',
        ]);
        $user->cdgo_usrios = 10;
        $user->cdgo_lgin = 'operador';
        $user->cdgo_cmpnia = 1;
        $user->cdgo_prfle = 1;

        // 1. Interno
        $respInterno = $this->actingAs($user)
            ->withSession([
                \App\Support\SessionKeys::USER_COMPANY => 1,
                \App\Support\SessionKeys::USER_CARGO => 20,
                \App\Support\SessionKeys::USER_AREA => 5,
            ])
            ->getJson(route('mod_tram_inter.correo_interno.participantes', ['movimiento_id' => 0]));

        $respInterno->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Identificador de movimiento inválido.',
            ]);

        // 2. Externo
        $respExterno = $this->actingAs($user)
            ->withSession([
                \App\Support\SessionKeys::USER_COMPANY => 1,
                \App\Support\SessionKeys::USER_CARGO => 20,
                \App\Support\SessionKeys::USER_AREA => 5,
            ])
            ->getJson(route('mod_gm.segi_expedi.participantes', ['movimiento_id' => 0]));

        $respExterno->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'ID de movimiento inválido.',
            ]);
    }

    /**
     * FASE 4: Auditoría de descargas con cero descargas retorna lista vacía sin error.
     */
    public function test_auditoria_descargas_cero_descargas_retorna_contrato_exitoso_vacio(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderMov = Mockery::mock(Builder::class);
        $builderAudit = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_ntrnos as r/'))->andReturn($builderMov);
        $builderMov->shouldReceive('join')->andReturnSelf();
        $builderMov->shouldReceive('where')->times(2)->andReturnSelf();
        $builderMov->shouldReceive('select')->andReturnSelf();
        $builderMov->shouldReceive('first')->andReturn((object) [
            'de_urls_agrdo' => 'documento_test.pdf',
            'de_urls_adjnto' => null,
        ]);

        $db->shouldReceive('table')->with(Mockery::pattern('/p_sgmto_dscrgas as sd/'))->andReturn($builderAudit);
        $builderAudit->shouldReceive('leftJoin')->times(2)->andReturnSelf();
        $builderAudit->shouldReceive('where')->with('sd.de_urls_dscrgas', 'documento_test.pdf')->andReturnSelf();
        $builderAudit->shouldReceive('count')->andReturn(0);
        $builderAudit->shouldReceive('select')->andReturnSelf();
        $builderAudit->shouldReceive('orderBy')->times(2)->andReturnSelf();
        $builderAudit->shouldReceive('offset')->with(0)->andReturnSelf();
        $builderAudit->shouldReceive('limit')->with(10)->andReturnSelf();
        $builderAudit->shouldReceive('get')->andReturn(collect([]));

        $repo = new \App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository($db);
        $res = $repo->obtenerAuditoriaDescargasPaginada(100, 1, 1, 10);

        $this->assertSame('success', $res['status']);
        $this->assertIsArray($res['data']);
        $this->assertEmpty($res['data']);
        $this->assertSame(0, $res['pagination']['total']);
        $this->assertSame('documento_test.pdf', $res['archivo']);
    }

    /**
     * FASE 4 / FASE 7: Ausencia del antiguo popover dentro de la tabla histórica.
     */
    public function test_ausencia_del_antiguo_popover_dentro_del_historico(): void
    {
        $vistaExterna = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/historico.blade.php'));
        $this->assertIsString($vistaExterna);
        $this->assertStringNotContainsString('popover-auditoria', $vistaExterna);
        $this->assertStringNotContainsString('data-popover', $vistaExterna);
        $this->assertStringNotContainsString('overflow-y-auto', $vistaExterna);
        $this->assertStringNotContainsString('max-h-[', $vistaExterna);
    }

    /**
     * FASE 1: CorreoExternoController::participantes autoriza movimientos históricos inactivos (in_esta = '0')
     * evaluando las ramas activas del documento sin romper el contrato ni exigir que el movimiento sea activo.
     */
    public function test_externo_participantes_movimiento_historico_inactivo_autorizado_via_documento(): void
    {
        $user = new \App\Models\User([
            'cdgo_usrios' => 10,
            'cdgo_lgin' => 'jperez',
            'cdgo_cmpnia' => 1,
            'cdgo_prfle' => 2,
            'in_esta' => '1',
        ]);
        $user->cdgo_usrios = 10;
        $user->cdgo_lgin = 'jperez';
        $user->cdgo_cmpnia = 1;
        $user->cdgo_prfle = 2;
        \Illuminate\Support\Facades\Auth::setUser($user);

        $repo = Mockery::mock(\App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface::class);
        $policy = Mockery::mock(\App\Policies\mod_gm\TramiteExternoPolicy::class);
        $service = Mockery::mock(\App\Services\mod_gm\mod_tram_exte\TramiteExternoService::class);
        $gestor = Mockery::mock(\App\Contracts\Services\GestorAdjuntosInterface::class);
        $consolidacion = Mockery::mock(\App\Services\mod_gm\mod_tram_exte\ConsolidacionExpedienteService::class);

        $controller = new \App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController(
            $service,
            $repo,
            $gestor,
            $consolidacion,
            $policy
        );

        $movHistorico = new \App\DTOs\mod_gm\ExpedienteMovimientoDto(
            expedienteId: 10,
            numeroExpediente: '2026-00010',
            movimientoId: 50,
            companiaId: 1,
            cargoSalidaId: 80,
            cargoEntradaId: 90,
            areaSalidaId: 15,
            areaEntradaId: 18,
            estadoId: 1,
            inEsta: '0' // Inactivo
        );

        $movActivo = new \App\DTOs\mod_gm\ExpedienteMovimientoDto(
            expedienteId: 10,
            numeroExpediente: '2026-00010',
            movimientoId: 55,
            companiaId: 1,
            cargoSalidaId: 90,
            cargoEntradaId: 105,
            areaSalidaId: 18,
            areaEntradaId: 20,
            estadoId: 1,
            inEsta: '1'
        );

        $repo->shouldReceive('obtenerMovimientoPorMovimientoId')->with(50, 1)->andReturn($movHistorico);
        $repo->shouldReceive('obtenerMovimientosActivosPorExpediente')->with(10, 1)->andReturn([$movActivo]);
        $policy->shouldReceive('verHistorico')->with(
            Mockery::type(ContextoUsuarioAutenticadoDto::class),
            $movHistorico,
            [$movActivo]
        )->andReturn(true);

        $repo->shouldReceive('obtenerParticipantesU')->with(
            50,
            Mockery::type(ContextoUsuarioAutenticadoDto::class),
            1,
            25,
            null
        )->andReturn([
            'status' => 'success',
            'data' => [
                ['login' => 'ana', 'nombre' => 'Ana Torres', 'cargo' => 'Asistente', 'area' => 'Mesa de Partes'],
            ],
            'origen_ejecutor' => [
                'login' => 'carlos',
                'nombre' => 'Carlos Ejecutor',
                'cargo' => 'Operador',
                'area' => 'Recepción',
            ],
            'pagination' => ['current_page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1],
            'meta' => ['fuente' => 'asignacion_actual'],
        ]);

        $request = \Illuminate\Http\Request::create('/mod_gm/mod_tram_exte/segi_expedi/participantes', 'GET', [
            'movimiento_id' => 50,
        ]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        $request->session()->put([
            \App\Support\SessionKeys::USER_COMPANY => 1,
            \App\Support\SessionKeys::USER_CARGO => 105,
            \App\Support\SessionKeys::USER_AREA => 20,
            \App\Support\SessionKeys::USER_LOGIN => 'jperez',
            \App\Support\SessionKeys::USER_ID => 10,
        ]);

        $response = $controller->participantes($request);
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $data['status']);
        $this->assertCount(1, $data['data']);
        $this->assertSame('Ana Torres', $data['data'][0]['nombre']);
        $this->assertSame('Carlos Ejecutor', $data['origen_ejecutor']['nombre']);
    }

    /**
     * FASE 1: CorreoExternoController::participantes retorna 404 si el movimiento no pertenece a la compañía
     * o no está autorizado.
     */
    public function test_externo_participantes_retorna_404_para_movimiento_no_autorizado_o_ajeno(): void
    {
        $user = new \App\Models\User(['cdgo_usrios' => 10, 'cdgo_lgin' => 'jperez', 'cdgo_cmpnia' => 1]);
        $user->cdgo_usrios = 10;
        $user->cdgo_lgin = 'jperez';
        $user->cdgo_cmpnia = 1;
        \Illuminate\Support\Facades\Auth::setUser($user);

        $repo = Mockery::mock(\App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface::class);
        $policy = Mockery::mock(\App\Policies\mod_gm\TramiteExternoPolicy::class);
        $service = Mockery::mock(\App\Services\mod_gm\mod_tram_exte\TramiteExternoService::class);
        $gestor = Mockery::mock(\App\Contracts\Services\GestorAdjuntosInterface::class);
        $consolidacion = Mockery::mock(\App\Services\mod_gm\mod_tram_exte\ConsolidacionExpedienteService::class);

        $controller = new \App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController(
            $service,
            $repo,
            $gestor,
            $consolidacion,
            $policy
        );

        // Caso 1: Movimiento inexistente en la compañía
        $repo->shouldReceive('obtenerMovimientoPorMovimientoId')->with(999, 1)->andReturn(null);

        $req1 = \Illuminate\Http\Request::create('/mod_gm/mod_tram_exte/segi_expedi/participantes', 'GET', ['movimiento_id' => 999]);
        $req1->setUserResolver(fn () => $user);
        $req1->setLaravelSession(app('session.store'));
        $req1->session()->put([\App\Support\SessionKeys::USER_COMPANY => 1, \App\Support\SessionKeys::USER_CARGO => 10, \App\Support\SessionKeys::USER_AREA => 5]);

        $res1 = $controller->participantes($req1);
        $this->assertSame(404, $res1->getStatusCode());

        // Caso 2: Movimiento existe pero policy rechaza (sin autorización)
        $movSinAuth = new \App\DTOs\mod_gm\ExpedienteMovimientoDto(
            expedienteId: 10,
            numeroExpediente: '2026-00010',
            movimientoId: 50,
            companiaId: 1,
            cargoSalidaId: 80,
            cargoEntradaId: 90,
            areaSalidaId: 15,
            areaEntradaId: 18,
            estadoId: 1,
            inEsta: '0'
        );
        $repo->shouldReceive('obtenerMovimientoPorMovimientoId')->with(50, 1)->andReturn($movSinAuth);
        $repo->shouldReceive('obtenerMovimientosActivosPorExpediente')->with(10, 1)->andReturn([]);
        $policy->shouldReceive('verHistorico')->andReturn(false);
        $repo->shouldReceive('obtenerMovimientosHistoricosPorExpediente')->with(10, 1)->andReturn([$movSinAuth]);
        $policy->shouldReceive('verHistorialExpediente')->andReturn(false);

        $req2 = \Illuminate\Http\Request::create('/mod_gm/mod_tram_exte/segi_expedi/participantes', 'GET', ['movimiento_id' => 50]);
        $req2->setUserResolver(fn () => $user);
        $req2->setLaravelSession(app('session.store'));
        $req2->session()->put([\App\Support\SessionKeys::USER_COMPANY => 1, \App\Support\SessionKeys::USER_CARGO => 10, \App\Support\SessionKeys::USER_AREA => 5]);

        $res2 = $controller->participantes($req2);
        $this->assertSame(404, $res2->getStatusCode());
    }

    /**
     * FASE 3: Verifica que los sidebars de Trámite Interno y Trámite Externo no contengan la clase h-full
     * y utilicen self-start para terminar naturalmente después de Nuevo Trámite.
     */
    public function test_sidebars_no_tienen_h_full_y_tienen_self_start(): void
    {
        $sidebarExterno = file_get_contents(resource_path('views/mod_gm/mod_tram_exte/partials_segi/sidebar.blade.php'));
        $this->assertIsString($sidebarExterno);
        $this->assertDoesNotMatchRegularExpression('/(?<![a-zA-Z0-9_-])h-full(?![a-zA-Z0-9_-])/', $sidebarExterno);
        $this->assertStringContainsString('self-start', $sidebarExterno);

        $sidebarInterno = file_get_contents(resource_path('views/mod_gene/mod_tram_inter/partials/sidebar.blade.php'));
        $this->assertIsString($sidebarInterno);
        $this->assertDoesNotMatchRegularExpression('/(?<![a-zA-Z0-9_-])h-full(?![a-zA-Z0-9_-])/', $sidebarInterno);
        $this->assertStringContainsString('self-start', $sidebarInterno);
    }
}

