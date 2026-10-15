<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class TramiteExternoRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_retorna_null_si_movimiento_no_existe_o_es_de_otra_compania(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn(null);

        $repo = new TramiteExternoRepository($db);
        $res = $repo->obtenerMovimientoPorMovimientoId(999, 1);

        $this->assertNull($res, 'Debe retornar null cuando el movimiento no existe o pertenece a otra compañía.');
    }

    public function test_consultar_bandeja_construye_contrato_paginacion(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->andReturnSelf();
        $builder->shouldReceive('whereIn')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn(45);
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('offset')->andReturnSelf();
        $builder->shouldReceive('limit')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmte_dcmnto'   => 10,
                'cdgo_dto_trmte'      => 5,
                'nu_expe_todo'        => '2026-0005',
                'cdgo_tpo_trmte'      => 22,
                'in_docu_trmte'       => 'E',
                'dc_tpo_trmte'        => 'E',
                'de_tpo_trmte'        => 'EXTERNO',
                'cdgo_estdo_trmte'    => 1,
                'dc_estdo_trmte'      => 'PENDIENTE',
                'fe_rcbdo_trmte'      => '2026-10-10 10:00:00',
                'de_obse_trmte'       => 'Registro inicial',
                'no_crto'             => 'Ciudadano',
                'nu_tiem_sile'        => 30,
                'nu_tiem_sile_adcnal' => 0,
                'nu_foli_trmte'       => 12,
            ],
        ]));

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);

        $res = $repo->consultarBandeja($contexto, 'entrada', [], 1, 15);

        $this->assertSame(45, $res['total']);
        $this->assertSame(3, $res['last_page']);
        $this->assertSame(1, $res['pagination']['current_page']);
        $this->assertSame(15, $res['pagination']['per_page']);
        $this->assertCount(1, $res['data']);
        $this->assertSame('2026-0005', $res['data'][0]['nu_expe_todo']);
    }

    public function test_consultar_bandeja_rechaza_bandeja_desconocida_con_excepcion(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bandeja desconocida');

        $repo->consultarBandeja($contexto, 'bandeja_maliciosa', []);
    }

    /**
     * Prueba estática de contrato del esquema (CE-00A / 08-contrato-esquema-existente.md):
     * Verifica que el repositorio y servicios de Correo Externo no contengan identificadores
     * inexistentes como m_tramite, d_tramite, nu_dto_trmte, nu_flio o r.nom_archivo.
     */
    public function test_repositorio_no_contiene_identificadores_invalidos(): void
    {
        $archivosAuditados = [
            app_path('Repositories/mod_gm/mod_tram_exte/TramiteExternoRepository.php'),
            app_path('Services/mod_gm/mod_tram_exte/TramiteExternoService.php'),
            app_path('Http/Controllers/mod_gm/mod_tram_exte/CorreoExternoController.php'),
        ];

        $prohibidos = [
            'm_tramite',
            'd_tramite',
            'nu_dto_trmte',
            'nu_flio',
            'r.nom_archivo',
            'p_sgmto_dscrgas.cdgo_trmte_dcmnto',
            'p_dto_trmtes.nom_archivo',
            'r_trmtes_dcmntos.nom_archivo',
        ];

        foreach ($archivosAuditados as $ruta) {
            $this->assertFileExists($ruta);
            $contenido = (string) file_get_contents($ruta);

            foreach ($prohibidos as $identificador) {
                $this->assertStringNotContainsString(
                    $identificador,
                    $contenido,
                    "El archivo [{$ruta}] contiene el identificador inválido [{$identificador}], violando CE-00A."
                );
            }
        }
    }

    public function test_obtener_detalle_expediente_consulta_esquema_real(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/p_dto_trmtes/'))->andReturn($builder);
        $builder->shouldReceive('leftJoin')->times(4)->andReturnSelf();
        $builder->shouldReceive('where')->twice()->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn((object) [
            'cdgo_dto_trmte'    => 100,
            'nu_expe_todo'      => 'EXP-2026-00100',
            'nu_foli_trmte'     => 15,
            'de_obse_todo'      => 'Asunto del trámite',
            'de_obse_trmte'     => 'Observación inicial',
            'no_crto'           => 'Empresa SAC',
            'nu_docu'           => '20123456789',
            'de_dire'           => 'Av. Principal 123',
            'nu_tele'           => '999888777',
            'de_mail'           => 'empresa@correo.com',
            'de_usrios_mail'    => 'contacto@correo.com',
            'de_tpo_trmte'      => 'Expediente Externo',
            'de_docmnto_trmte'  => 'Licencia de Funcionamiento',
            'nu_tiem_sile'      => 30,
        ]);

        // Mock para obtenerMovimientoActual
        $builderMov = Mockery::mock(Builder::class);
        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos/'))->andReturn($builderMov);
        $builderMov->shouldReceive('join')->andReturnSelf();
        $builderMov->shouldReceive('where')->andReturnSelf();
        $builderMov->shouldReceive('select')->andReturnSelf();
        $builderMov->shouldReceive('orderBy')->andReturnSelf();
        $builderMov->shouldReceive('first')->andReturn(null);

        $repo = new TramiteExternoRepository($db);
        $res = $repo->obtenerDetalleExpediente(100, 1);

        $this->assertNotNull($res);
        $this->assertSame('EXP-2026-00100', $res['data']['expediente']);
        $this->assertSame('Empresa SAC', $res['data']['solicitante']);
        $this->assertSame('20123456789', $res['data']['documento_id']);
        $this->assertSame('contacto@correo.com', $res['data']['correo']);
        $this->assertSame(15, $res['data']['folios']);
        $this->assertSame('30 días', $res['data']['silencio_admin']);
    }

    public function test_obtener_datos_index_construye_catalogos_y_contadores(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderAreas = Mockery::mock(Builder::class);
        $builderCargos = Mockery::mock(Builder::class);
        $builderTipos = Mockery::mock(Builder::class);
        $builderEntrada = Mockery::mock(Builder::class);
        $builderRecibidos = Mockery::mock(Builder::class);
        $builderEstados = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/a_areas/'))->andReturn($builderAreas);
        $builderAreas->shouldReceive('where')->twice()->andReturnSelf();
        $builderAreas->shouldReceive('orderBy')->andReturnSelf();
        $builderAreas->shouldReceive('get')->andReturn(collect([
            (object) ['cdgo_area' => 1, 'de_area' => 'Gerencia Municipal', 'cdgo_area_prim' => null],
            (object) ['cdgo_area' => 2, 'de_area' => 'Subgerencia OTI', 'cdgo_area_prim' => 1],
        ]));

        $db->shouldReceive('table')->with(Mockery::pattern('/p_crgos as c/'))->andReturn($builderCargos);
        $builderCargos->shouldReceive('join')->times(3)->andReturnSelf();
        $builderCargos->shouldReceive('select')->andReturnSelf();
        $builderCargos->shouldReceive('where')->times(4)->andReturnSelf();
        $builderCargos->shouldReceive('orderBy')->andReturnSelf();
        $builderCargos->shouldReceive('get')->andReturn(collect([]));

        $db->shouldReceive('table')->with(Mockery::pattern('/r_tpo_trmte_areas as r/'))->andReturn($builderTipos);
        $builderTipos->shouldReceive('join')->andReturnSelf();
        $builderTipos->shouldReceive('select')->andReturnSelf();
        $builderTipos->shouldReceive('where')->twice()->andReturnSelf();
        $builderTipos->shouldReceive('orderBy')->andReturnSelf();
        $builderTipos->shouldReceive('get')->andReturn(collect([]));

        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos as r/'))->twice()->andReturn($builderEntrada, $builderRecibidos);
        $builderEntrada->shouldReceive('join')->andReturnSelf();
        $builderEntrada->shouldReceive('where')->times(4)->andReturnSelf();
        $builderEntrada->shouldReceive('whereNotIn')->andReturnSelf();
        $builderEntrada->shouldReceive('count')->andReturn(7);

        $builderRecibidos->shouldReceive('join')->andReturnSelf();
        $builderRecibidos->shouldReceive('where')->times(4)->andReturnSelf();
        $builderRecibidos->shouldReceive('whereNotIn')->andReturnSelf();
        $builderRecibidos->shouldReceive('count')->andReturn(3);

        $db->shouldReceive('table')->with(Mockery::pattern('/a_estdos_trmtes/'))->never();

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);
        $datos = $repo->obtenerDatosIndex($contexto);

        $this->assertSame(7, $datos['countEntrada']);
        $this->assertSame(3, $datos['countRecibidos']);
        $this->assertCount(2, $datos['areasList']);
        $this->assertArrayNotHasKey('estadosList', $datos);
    }

    /**
     * FASE 5: Verifica que finalizados, archivo y todos exijan r.in_esta = '1' y los cargos/áreas correspondientes.
     */
    public function test_aplicar_filtro_bandeja_exige_in_esta_vigente_en_finalizados_archivo_y_todos(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);

        // 1. finalizados_notificados
        $bFinalizados = Mockery::mock(Builder::class);
        $bFinalizados->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $bFinalizados->shouldReceive('where')->with('r.cdgo_crgo_en', 105)->once()->andReturnSelf();
        $bFinalizados->shouldReceive('where')->with('r.cdgo_crgo_sa', 105)->once()->andReturnSelf();
        $bFinalizados->shouldReceive('whereIn')->with('r.cdgo_estdo_trmte', [3, 26])->once()->andReturnSelf();
        $repo->aplicarFiltroBandeja($bFinalizados, 'finalizados_notificados', $contexto);

        // 2. archivo
        $bArchivo = Mockery::mock(Builder::class);
        $bArchivo->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $bArchivo->shouldReceive('where')->with('r.cdgo_crgo_en', 105)->once()->andReturnSelf();
        $bArchivo->shouldReceive('where')->with('r.cdgo_crgo_sa', 105)->once()->andReturnSelf();
        $bArchivo->shouldReceive('where')->with('r.cdgo_estdo_trmte', 27)->once()->andReturnSelf();
        $repo->aplicarFiltroBandeja($bArchivo, 'archivo', $contexto);

        // 3. todos
        $bTodos = Mockery::mock(Builder::class);
        $bTodos->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $bTodos->shouldReceive('where')->with('c.cdgo_area', 20)->once()->andReturnSelf();
        $repo->aplicarFiltroBandeja($bTodos, 'todos', $contexto);

        // 4. sin_finalizar (con paridad legacy r.cdgo_crgo_en != cargo_actual)
        $bSinFin = Mockery::mock(Builder::class);
        $bSinFin->shouldReceive('where')->with('r.cdgo_crgo_sa', 105)->once()->andReturnSelf();
        $bSinFin->shouldReceive('where')->with('r.cdgo_crgo_en', '!=', 105)->once()->andReturnSelf();
        $bSinFin->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $bSinFin->shouldReceive('whereNotIn')->with('r.cdgo_estdo_trmte', [3, 26])->once()->andReturnSelf();
        $repo->aplicarFiltroBandeja($bSinFin, 'sin_finalizar', $contexto);

        $this->assertTrue(true, 'Todas las expectativas de filtros vigentes y cargos fueron verificadas.');
    }

    /**
     * FASE 6: El histórico externo recupera todos los movimientos (in_esta = 0 y 1) sin filtrar por vigencia.
     */
    public function test_historico_externo_recupera_movimientos_activos_e_inactivos(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderHist = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos as r/'))->andReturn($builderHist);
        $builderHist->shouldReceive('leftJoin')->times(8)->andReturnSelf();
        $builderHist->shouldReceive('where')->with('r.cdgo_dto_trmte', 9901)->once()->andReturnSelf();
        $builderHist->shouldReceive('select')->andReturnSelf();
        $builderHist->shouldReceive('orderByDesc')->with('r.cdgo_trmte_dcmnto')->andReturnSelf();

        $movInactivo = (object) [
            'cdgo_trmte_dcmnto' => 101,
            'cdgo_dto_trmte' => 9901,
            'fe_rcbdo_trmte' => '2026-09-01 09:00:00',
            'cdgo_estdo_trmte' => 1,
            'dc_estdo_trmte' => 'INGRESADO',
            'de_obse' => 'Pase inicial',
            'nom_archivo' => null,
            'cdgo_crgo_sa' => 10,
            'cdgo_crgo_en' => 20,
            'cargo_salida' => 'SECRETARIA',
            'cargo_entrada' => 'SUBGERENCIA',
            'area_salida' => 'SECRETARIA GENERAL',
            'area_entrada' => 'TRAMITE DOCUMENTARIO',
            'cdgo_lgin' => 5,
            'login_actor' => 'operador1',
            'usuario_actor' => 'JUAN PEREZ',
            'perfil_id_actor' => 2,
            'perfil_actor' => 'OPERADOR',
            'in_esta' => '0',
        ];

        $movActivo = (object) [
            'cdgo_trmte_dcmnto' => 102,
            'cdgo_dto_trmte' => 9901,
            'fe_rcbdo_trmte' => '2026-09-02 11:00:00',
            'cdgo_estdo_trmte' => 2,
            'dc_estdo_trmte' => 'RECEPCIONADO',
            'de_obse' => 'Recepción',
            'nom_archivo' => null,
            'cdgo_crgo_sa' => 20,
            'cdgo_crgo_en' => 30,
            'cargo_salida' => 'SUBGERENCIA',
            'cargo_entrada' => 'GERENCIA',
            'area_salida' => 'TRAMITE DOCUMENTARIO',
            'area_entrada' => 'GERENCIA MUNICIPAL',
            'cdgo_lgin' => 6,
            'login_actor' => 'operador2',
            'usuario_actor' => 'MARIA LOPEZ',
            'perfil_id_actor' => 2,
            'perfil_actor' => 'OPERADOR',
            'in_esta' => '1',
        ];

        $builderHist->shouldReceive('get')->andReturn(collect([$movInactivo, $movActivo]));

        // Consultas complementarias masivas
        $builderDest = Mockery::mock(Builder::class);
        $db->shouldReceive('table')->with(Mockery::pattern('/r_crgos_lgin as rl/'))->andReturn($builderDest);
        $builderDest->shouldReceive('join')->twice()->andReturnSelf();
        $builderDest->shouldReceive('whereIn')->andReturnSelf();
        $builderDest->shouldReceive('where')->andReturnSelf();
        $builderDest->shouldReceive('select')->andReturnSelf();
        $builderDest->shouldReceive('get')->andReturn(collect([]));

        $repo = new TramiteExternoRepository($db);
        $res = $repo->obtenerHistoricoMovimientos(9901);

        $this->assertCount(2, $res, 'El histórico externo debe incluir tanto in_esta = 0 como in_esta = 1.');
        $this->assertSame('0', $res[0]['in_esta']);
        $this->assertSame('1', $res[1]['in_esta']);
    }

    /**
     * FASE 7: Dos movimientos activos para el mismo expediente representan derivaciones simultáneas válidas.
     */
    public function test_dos_movimientos_activos_no_pierden_ramas_simultaneas(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderHist = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos as r/'))->andReturn($builderHist);
        $builderHist->shouldReceive('leftJoin')->times(8)->andReturnSelf();
        $builderHist->shouldReceive('where')->with('r.cdgo_dto_trmte', 8888)->once()->andReturnSelf();
        $builderHist->shouldReceive('select')->andReturnSelf();
        $builderHist->shouldReceive('orderByDesc')->with('r.cdgo_trmte_dcmnto')->andReturnSelf();

        // Dos ramas activas simultáneas derivadas a diferentes áreas
        $rama1 = (object) [
            'cdgo_trmte_dcmnto' => 501,
            'cdgo_dto_trmte' => 8888,
            'fe_rcbdo_trmte' => '2026-10-01 10:00:00',
            'cdgo_estdo_trmte' => 2,
            'dc_estdo_trmte' => 'RECEPCIONADO',
            'de_obse' => 'Pase a OTI',
            'nom_archivo' => null,
            'cdgo_crgo_sa' => 10,
            'cdgo_crgo_en' => 25,
            'cargo_salida' => 'SUBGERENCIA',
            'cargo_entrada' => 'JEFE OTI',
            'area_salida' => 'TRAMITE',
            'area_entrada' => 'OTI',
            'cdgo_lgin' => 1,
            'login_actor' => 'admin',
            'usuario_actor' => 'ADMINISTRADOR',
            'perfil_id_actor' => 1,
            'perfil_actor' => 'ADMIN',
            'in_esta' => '1',
        ];

        $rama2 = (object) [
            'cdgo_trmte_dcmnto' => 502,
            'cdgo_dto_trmte' => 8888,
            'fe_rcbdo_trmte' => '2026-10-01 10:00:00',
            'cdgo_estdo_trmte' => 2,
            'dc_estdo_trmte' => 'RECEPCIONADO',
            'de_obse' => 'Pase simultáneo a Asesoría Jurídica',
            'nom_archivo' => null,
            'cdgo_crgo_sa' => 10,
            'cdgo_crgo_en' => 35,
            'cargo_salida' => 'SUBGERENCIA',
            'cargo_entrada' => 'ASESOR LEGAL',
            'area_salida' => 'TRAMITE',
            'area_entrada' => 'ASESORIA JURIDICA',
            'cdgo_lgin' => 1,
            'login_actor' => 'admin',
            'usuario_actor' => 'ADMINISTRADOR',
            'perfil_id_actor' => 1,
            'perfil_actor' => 'ADMIN',
            'in_esta' => '1',
        ];

        $builderHist->shouldReceive('get')->andReturn(collect([$rama1, $rama2]));

        $builderDest = Mockery::mock(Builder::class);
        $db->shouldReceive('table')->with(Mockery::pattern('/r_crgos_lgin as rl/'))->andReturn($builderDest);
        $builderDest->shouldReceive('join')->twice()->andReturnSelf();
        $builderDest->shouldReceive('whereIn')->andReturnSelf();
        $builderDest->shouldReceive('where')->andReturnSelf();
        $builderDest->shouldReceive('select')->andReturnSelf();
        $builderDest->shouldReceive('get')->andReturn(collect([]));

        $repo = new TramiteExternoRepository($db);
        $res = $repo->obtenerHistoricoMovimientos(8888);

        $this->assertCount(2, $res, 'Ambas ramas activas deben conservarse sin descartar ninguna.');
        $this->assertSame(501, $res[0]['cdgo_trmte_dcmnto']);
        $this->assertSame(502, $res[1]['cdgo_trmte_dcmnto']);
    }

    public function test_consultar_bandeja_mapea_tipos_catalogo_dinamicamente(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->andReturnSelf();
        $builder->shouldReceive('whereIn')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn(3);
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('offset')->andReturnSelf();
        $builder->shouldReceive('limit')->andReturnSelf();

        $builder->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmte_dcmnto'   => 101,
                'cdgo_dto_trmte'      => 1,
                'nu_expe_todo'        => '2026-0101',
                'cdgo_tpo_trmte'      => 888,
                'in_docu_trmte'       => 'S',
                'dc_tpo_trmte'        => 'S',
                'de_tpo_trmte'        => 'DOCUMENTO SIMPLE',
                'cdgo_estdo_trmte'    => 1,
                'dc_estdo_trmte'      => 'PENDIENTE',
                'fe_rcbdo_trmte'      => '2026-10-10 10:00:00',
                'de_obse_trmte'       => 'Caso 1',
                'no_crto'             => 'Ciudadano 1',
                'nu_tiem_sile'        => 30,
                'nu_tiem_sile_adcnal' => 0,
                'nu_foli_trmte'       => 1,
            ],
            (object) [
                'cdgo_trmte_dcmnto'   => 102,
                'cdgo_dto_trmte'      => 2,
                'nu_expe_todo'        => '2026-0102',
                'cdgo_tpo_trmte'      => 999,
                'in_docu_trmte'       => 'E',
                'dc_tpo_trmte'        => 'E',
                'de_tpo_trmte'        => 'EXPEDIENTE EXTERNO',
                'cdgo_estdo_trmte'    => 1,
                'dc_estdo_trmte'      => 'PENDIENTE',
                'fe_rcbdo_trmte'      => '2026-10-10 10:00:00',
                'de_obse_trmte'       => 'Caso 2',
                'no_crto'             => 'Ciudadano 2',
                'nu_tiem_sile'        => 0,
                'nu_tiem_sile_adcnal' => 0,
                'nu_foli_trmte'       => 2,
            ],
            (object) [
                'cdgo_trmte_dcmnto'   => 103,
                'cdgo_dto_trmte'      => 3,
                'nu_expe_todo'        => '2026-0103',
                'cdgo_tpo_trmte'      => 777,
                'in_docu_trmte'       => 'O',
                'dc_tpo_trmte'        => 'O',
                'de_tpo_trmte'        => 'OBSERVADO',
                'cdgo_estdo_trmte'    => 1,
                'dc_estdo_trmte'      => 'PENDIENTE',
                'fe_rcbdo_trmte'      => '2026-10-10 10:00:00',
                'de_obse_trmte'       => 'Caso 3',
                'no_crto'             => 'Ciudadano 3',
                'nu_tiem_sile'        => 0,
                'nu_tiem_sile_adcnal' => 0,
                'nu_foli_trmte'       => 3,
            ],
        ]));

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);

        $res = $repo->consultarBandeja($contexto, 'entrada', [], 1, 15);
        $data = $res['data'];

        $this->assertCount(3, $data);

        // Caso 1: S mapea dinámicamente
        $this->assertSame('S', $data[0]['orig_doc']);
        $this->assertSame(888, $data[0]['cdgo_tpo_trmte']);
        $this->assertSame('DOCUMENTO SIMPLE', $data[0]['de_tpo_trmte']);

        // Caso 2: E mapea dinámicamente
        $this->assertSame('E', $data[1]['orig_doc']);
        $this->assertSame(999, $data[1]['cdgo_tpo_trmte']);
        $this->assertSame('EXPEDIENTE EXTERNO', $data[1]['de_tpo_trmte']);

        // Caso 3: O mapea dinámicamente
        $this->assertSame('O', $data[2]['orig_doc']);
        $this->assertSame(777, $data[2]['cdgo_tpo_trmte']);
        $this->assertSame('OBSERVADO', $data[2]['de_tpo_trmte']);
    }

    public function test_consultar_bandeja_falla_si_cdgo_tpo_trmte_es_nulo(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('inválido');

        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn(1);
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('offset')->andReturnSelf();
        $builder->shouldReceive('limit')->andReturnSelf();

        $builder->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmte_dcmnto'   => 101,
                'cdgo_dto_trmte'      => 1,
                'nu_expe_todo'        => '2026-0101',
                'cdgo_tpo_trmte'      => null, // nulo: debe fallar cerrado
                'in_docu_trmte'       => 'E',
                'dc_tpo_trmte'        => 'E',
                'de_tpo_trmte'        => 'EXTERNO',
                'cdgo_estdo_trmte'    => 1,
                'dc_estdo_trmte'      => 'PENDIENTE',
                'fe_rcbdo_trmte'      => '2026-10-10 10:00:00',
                'de_obse_trmte'       => 'Caso nulo',
                'no_crto'             => 'Ciudadano',
                'nu_tiem_sile'        => 0,
                'nu_tiem_sile_adcnal' => 0,
                'nu_foli_trmte'       => 1,
            ],
        ]));

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);
        $repo->consultarBandeja($contexto, 'entrada', [], 1, 15);
    }

    public function test_consultar_bandeja_falla_si_identificador_no_es_positivo(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('inválido');

        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn(1);
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('offset')->andReturnSelf();
        $builder->shouldReceive('limit')->andReturnSelf();

        $builder->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmte_dcmnto'   => 10,
                'cdgo_dto_trmte'      => 0,
                'nu_expe_todo'        => '0',
                'cdgo_tpo_trmte'      => 22,
                'in_docu_trmte'       => 'E',
                'dc_tpo_trmte'        => 'E',
                'de_tpo_trmte'        => 'EXTERNO',
                'cdgo_estdo_trmte'    => 1,
                'dc_estdo_trmte'      => 'PENDIENTE',
                'fe_rcbdo_trmte'      => '2026-10-10 10:00:00',
                'de_obse_trmte'       => 'Inválido',
                'no_crto'             => 'Ciudadano',
                'nu_tiem_sile'        => 0,
                'nu_tiem_sile_adcnal' => 0,
                'nu_foli_trmte'       => 1,
            ],
        ]));

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);
        $repo->consultarBandeja($contexto, 'entrada', [], 1, 15);
    }

    public function test_consultar_bandeja_falla_si_falta_propiedad_garantizada_por_select(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("carece de la propiedad 'cdgo_tpo_trmte'");

        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNotIn')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn(1);
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('offset')->andReturnSelf();
        $builder->shouldReceive('limit')->andReturnSelf();

        $builder->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmte_dcmnto'   => 10,
                'cdgo_dto_trmte'      => 5,
                'nu_expe_todo'        => '2026-0005',
                // falta 'cdgo_tpo_trmte'
                'in_docu_trmte'       => 'E',
                'dc_tpo_trmte'        => 'E',
                'de_tpo_trmte'        => 'EXTERNO',
                'cdgo_estdo_trmte'    => 1,
                'dc_estdo_trmte'      => 'PENDIENTE',
            ],
        ]));

        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);
        $repo = new TramiteExternoRepository($db);
        $repo->consultarBandeja($contexto, 'entrada', [], 1, 15);
    }
}
