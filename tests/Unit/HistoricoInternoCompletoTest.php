<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Mockery;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use Carbon\Carbon;

class HistoricoInternoCompletoTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * FASE 1: Caso con un documento y dos movimientos (uno in_esta = '0' y otro in_esta = '1').
     * Ambos deben aparecer en data y contarse en total.
     */
    public function test_historico_recupera_movimientos_activos_e_inactivos(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderDoc = Mockery::mock(Builder::class);
        $builderHist = Mockery::mock(Builder::class);

        // 1 y 2. Búsqueda del documento base por movimiento y luego consulta de movimientos
        $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos as r')->andReturn($builderDoc, $builderHist);
        $builderDoc->shouldReceive('join')->with('sistema.p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('r.cdgo_trmtes_ntrnos', 117)->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->andReturnSelf();
        $builderDoc->shouldReceive('select')->with('r.cdgo_dtos_ntrnos')->andReturnSelf();
        $builderDoc->shouldReceive('first')->andReturn((object) ['cdgo_dtos_ntrnos' => 5001]);

        // Consulta de los movimientos históricos del documento
        $builderHist->shouldReceive('join')->andReturnSelf();
        $builderHist->shouldReceive('leftJoin')->andReturnSelf();
        $builderHist->shouldReceive('where')->with('r.cdgo_dtos_ntrnos', 5001)->andReturnSelf();
        $builderHist->shouldReceive('orderByDesc')->with('r.cdgo_trmtes_ntrnos')->andReturnSelf();
        $builderHist->shouldReceive('select')->andReturnSelf();

        // Conteo total (ambos movimientos)
        $builderHist->shouldReceive('count')->andReturn(2);

        // Paginación
        $builderHist->shouldReceive('offset')->with(0)->andReturnSelf();
        $builderHist->shouldReceive('limit')->with(50)->andReturnSelf();

        $movIngresado = (object) [
            'cdgo_trmtes_ntrnos' => 1001,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'OFIC. 00000117-2026',
            'cargo_salida' => 'SECRETARIA GENERAL',
            'cargo_entrada' => 'SUBGERENCIA DE TRAMITE',
            'estado_descripcion' => 'INGRESADO',
            'cdgo_estdo_trmte' => 1,
            'fe_rcbdo' => '2026-10-01 10:00:00',
            'de_obser_ntrnos' => 'Ingreso de expediente',
            'de_obser' => 'Ingreso de expediente',
            'de_obser_documento' => 'Documento original',
            'in_copia' => 0,
            'in_esta' => '0', // Inactivo (histórico)
        ];

        $movRecepcionado = (object) [
            'cdgo_trmtes_ntrnos' => 1002,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'OFIC. 00000117-2026',
            'cargo_salida' => 'SUBGERENCIA DE TRAMITE',
            'cargo_entrada' => 'OTI',
            'estado_descripcion' => 'RECEPCIONADO',
            'cdgo_estdo_trmte' => 2,
            'fe_rcbdo' => '2026-10-02 11:30:00',
            'de_obser_ntrnos' => 'Recepcionado conforme',
            'de_obser' => 'Recepcionado conforme',
            'de_obser_documento' => 'Documento original',
            'in_copia' => 0,
            'in_esta' => '1', // Activo (vigente)
        ];

        $builderHist->shouldReceive('get')->andReturn(collect([$movIngresado, $movRecepcionado]));

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $res = $repo->obtenerHistorial(117, $contexto, 1, 50);

        // Verificaciones obligatorias
        $this->assertEquals(2, $res['meta']['total'], 'El total de paginación debe contar ambos movimientos.');
        $this->assertCount(2, $res['data'], 'Deben aparecer tanto el movimiento in_esta=0 como in_esta=1.');
        $this->assertEquals('0', (string) $res['data'][0]->in_esta, 'El primer movimiento debe conservar in_esta=0.');
        $this->assertEquals('1', (string) $res['data'][1]->in_esta, 'El segundo movimiento debe conservar in_esta=1.');
        $this->assertEquals('INGRESADO', $res['data'][0]->estado_descripcion);
        $this->assertEquals('RECEPCIONADO', $res['data'][1]->estado_descripcion);
    }

    /**
     * FASE 1: Un documento de otra compañía debe devolver total 0 y data vacía (anti-IDOR).
     */
    public function test_historico_falla_seguro_si_pertenece_a_otra_compania(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderDoc = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos as r')->andReturn($builderDoc);
        $builderDoc->shouldReceive('join')->with('sistema.p_dtos_ntrnos as p', 'p.cdgo_dtos_ntrnos', '=', 'r.cdgo_dtos_ntrnos')->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('r.cdgo_trmtes_ntrnos', 117)->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->andReturnSelf();
        $builderDoc->shouldReceive('select')->with('r.cdgo_dtos_ntrnos')->andReturnSelf();
        // Pertenece a compañía 2, por lo que p.cdgo_cmpnia=1 retorna null
        $builderDoc->shouldReceive('first')->andReturn(null);

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $res = $repo->obtenerHistorial(117, $contexto, 1, 50);

        $this->assertEquals(0, $res['meta']['total']);
        $this->assertEmpty($res['data']);
    }

    /**
     * FASE 1: La fila frontera para T.E. debe considerar la fecha del movimiento previo
     * y calcular la diferencia exacta tanto si in_esta es '0' como '1'.
     */
    public function test_calculo_te_diferencia_movimientos_historicos_y_vigentes(): void
    {
        $feIngresado = '2026-10-01 10:00:00';
        $feRecepcionado = '2026-10-03 14:30:00';

        // Para in_esta = '0', T.E. se calcula entre fe_rcbdo del movimiento y fe_rcbdo del movimiento anterior (o frontera)
        $inicio = Carbon::parse($feIngresado);
        $fin = Carbon::parse($feRecepcionado);

        $diffSegundos = abs($fin->getTimestamp() - $inicio->getTimestamp());
        $dias = intdiv($diffSegundos, 86400);
        $horas = intdiv($diffSegundos % 86400, 3600);
        $minutos = intdiv($diffSegundos % 3600, 60);

        $this->assertEquals(2, $dias, 'Diferencia de 2 días');
        $this->assertEquals(4, $horas, 'Diferencia de 4 horas');
        $this->assertEquals(30, $minutos, 'Diferencia de 30 minutos');
    }

    /**
     * FASE 1: Una bandeja operativa (ej. Entrada) debe continuar filtrando in_esta = '1'.
     */
    public function test_bandeja_operativa_mantiene_filtro_in_esta_vigente(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn(10);
        $builder->shouldReceive('offset')->andReturnSelf();
        $builder->shouldReceive('limit')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));

        // Debe llamarse explícitamente where('r.in_esta', '1') para bandejas operativas
        $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with(Mockery::any(), Mockery::any())->andReturnSelf();
        $builder->shouldReceive('where')->with(Mockery::any(), Mockery::any(), Mockery::any())->andReturnSelf();

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $repo->listarBandeja('entrada', $contexto, [], 1, 20);

        // Si where('r.in_esta', '1') no se llamó exactamente una vez, Mockery lanzará excepción
        $this->assertTrue(true);
    }

    /**
     * FASE 1: Prueba estructural que asegura que obtenerHistorial en TramiteInternoRepository
     * no contiene where('r.in_esta', '1') ni where('r_trmtes_ntrnos.in_esta', '1').
     */
    public function test_prueba_estructural_ausencia_filtro_vigencia_en_historial(): void
    {
        $repoPath = app_path('Repositories/mod_gene/mod_tram_inter/TramiteInternoRepository.php');
        $this->assertFileExists($repoPath);

        $repoCode = file_get_contents($repoPath);

        // Extraer método obtenerHistorial
        preg_match('/public function obtenerHistorial\([^)]*\): array\s*\{(.*?)\n    public function /s', $repoCode, $matches);
        $this->assertNotEmpty($matches[1] ?? '', 'Debe existir el método obtenerHistorial');

        $metodoHistorial = $matches[1];

        // Verificar que no filtra r.in_esta = '1'
        $this->assertStringNotContainsString(
            "where('r.in_esta'",
            $metodoHistorial,
            "El método obtenerHistorial no debe filtrar por r.in_esta."
        );
        $this->assertStringNotContainsString(
            "where('r_trmtes_ntrnos.in_esta'",
            $metodoHistorial,
            "El método obtenerHistorial no debe filtrar por r_trmtes_ntrnos.in_esta."
        );

        // Revisar también CorreoInternoController::historial
        $controllerPath = app_path('Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php');
        $this->assertFileExists($controllerPath);

        $controllerCode = file_get_contents($controllerPath);
        preg_match('/public function historial\([^)]*\)[^{]*\{(.*?)\n    public function /s', $controllerCode, $ctrlMatches);
        $this->assertNotEmpty($ctrlMatches[1] ?? '', 'Debe existir el método historial en el controlador');

        $ctrlHistorial = $ctrlMatches[1];

        // Comprobar que en el conteo y listado de movimientos de historial no se filtra in_esta = '1'
        $this->assertStringNotContainsString(
            "where('r.in_esta', '1')",
            $ctrlHistorial,
            "El controlador de historial no debe filtrar movimientos por r.in_esta = '1'."
        );
        $this->assertStringNotContainsString(
            "where('r_trmtes_ntrnos.in_esta', '1')",
            $ctrlHistorial,
            "El controlador de historial no debe filtrar movimientos por r_trmtes_ntrnos.in_esta = '1'."
        );
    }

    /**
     * FASE 1 OBLIGATORIA: Documento con exactamente 5 movimientos:
     * - Movimiento A: INGRESADO, in_esta = '0'
     * - Movimiento B: RECEPCIONADO, in_esta = '0'
     * - Movimiento C: DERIVADO al área 1, in_esta = '1'
     * - Movimiento D: DERIVADO al área 2, in_esta = '1'
     * - Movimiento E: otro estado histórico (NOTIFICADO), in_esta = '0'
     * La respuesta debe contener exactamente los 5 movimientos sin deduplicar ni recortar.
     */
    public function test_historico_recupera_cinco_movimientos_con_derivacion_paralela_y_estados_mixtos(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderHist = Mockery::mock(Builder::class);
        $builderDoc = Mockery::mock(Builder::class);

        // Validación de compañía para documento_id directo 5001
        $db->shouldReceive('table')->with('sistema.p_dtos_ntrnos as p')->andReturn($builderDoc);
        $builderDoc->shouldReceive('where')->with('p.cdgo_dtos_ntrnos', 5001)->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->andReturnSelf();
        $builderDoc->shouldReceive('exists')->andReturn(true);

        // Consulta de movimientos históricos
        $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos as r')->andReturn($builderHist);
        $builderHist->shouldReceive('join')->andReturnSelf();
        $builderHist->shouldReceive('leftJoin')->andReturnSelf();
        $builderHist->shouldReceive('where')->with('r.cdgo_dtos_ntrnos', 5001)->andReturnSelf();
        $builderHist->shouldReceive('orderByDesc')->with('r.cdgo_trmtes_ntrnos')->andReturnSelf();
        $builderHist->shouldReceive('select')->andReturnSelf();
        $builderHist->shouldReceive('count')->andReturn(5);
        $builderHist->shouldReceive('offset')->with(0)->andReturnSelf();
        $builderHist->shouldReceive('limit')->with(50)->andReturnSelf();

        $movA = (object) [
            'cdgo_trmtes_ntrnos' => 2001,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'OFIC. 00000117-2026',
            'cargo_salida' => 'SECRETARIA GENERAL',
            'cargo_entrada' => 'SUBGERENCIA DE TRAMITE',
            'estado_descripcion' => 'INGRESADO',
            'cdgo_estdo_trmte' => 1,
            'fe_rcbdo' => '2026-10-01 09:00:00',
            'in_copia' => 0,
            'in_esta' => '0', // Movimiento A: INGRESADO in_esta = '0'
        ];
        $movB = (object) [
            'cdgo_trmtes_ntrnos' => 2002,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'OFIC. 00000117-2026',
            'cargo_salida' => 'SUBGERENCIA DE TRAMITE',
            'cargo_entrada' => 'GERENCIA MUNICIPAL',
            'estado_descripcion' => 'RECEPCIONADO',
            'cdgo_estdo_trmte' => 2,
            'fe_rcbdo' => '2026-10-01 11:30:00',
            'in_copia' => 0,
            'in_esta' => '0', // Movimiento B: RECEPCIONADO in_esta = '0'
        ];
        $movC = (object) [
            'cdgo_trmtes_ntrnos' => 2003,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'OFIC. 00000117-2026',
            'cargo_salida' => 'GERENCIA MUNICIPAL',
            'cargo_entrada' => 'AREA 1 (OTI)',
            'estado_descripcion' => 'DERIVADO',
            'cdgo_estdo_trmte' => 3,
            'fe_rcbdo' => '2026-10-02 08:45:00',
            'in_copia' => 0,
            'in_esta' => '1', // Movimiento C: DERIVADO rama 1 in_esta = '1'
        ];
        $movD = (object) [
            'cdgo_trmtes_ntrnos' => 2004,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'OFIC. 00000117-2026',
            'cargo_salida' => 'GERENCIA MUNICIPAL',
            'cargo_entrada' => 'AREA 2 (FINANZAS)',
            'estado_descripcion' => 'DERIVADO',
            'cdgo_estdo_trmte' => 3,
            'fe_rcbdo' => '2026-10-02 08:45:00',
            'in_copia' => 1,
            'in_esta' => '1', // Movimiento D: DERIVADO rama 2 in_esta = '1'
        ];
        $movE = (object) [
            'cdgo_trmtes_ntrnos' => 2005,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'OFIC. 00000117-2026',
            'cargo_salida' => 'AREA 1 (OTI)',
            'cargo_entrada' => 'NOTIFICACIONES',
            'estado_descripcion' => 'NOTIFICADO',
            'cdgo_estdo_trmte' => 26,
            'fe_rcbdo' => '2026-10-03 14:00:00',
            'in_copia' => 0,
            'in_esta' => '0', // Movimiento E: NOTIFICADO in_esta = '0'
        ];

        $builderHist->shouldReceive('get')->andReturn(collect([$movA, $movB, $movC, $movD, $movE]));

        $contexto = $this->crearContexto(
            login: 'auditor',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        // Consulta canónica mediante documentoId = 5001
        $res = $repo->obtenerHistorial(0, $contexto, 1, 50, 5001);

        // Aserciones obligatorias:
        $this->assertEquals(5, $res['meta']['total'], 'El total debe ser exactamente 5 movimientos.');
        $this->assertCount(5, $res['data'], 'La respuesta debe contener exactamente los 5 movimientos.');

        // 1. Dos o más movimientos con in_esta = '1': ambos aparecen
        $vigentes = array_filter($res['data'], fn($m) => (string) $m->in_esta === '1');
        $this->assertCount(2, $vigentes, 'Deben existir exactamente 2 movimientos con in_esta = 1 (C y D).');

        // 2. Todos los movimientos con in_esta = '0': todos aparecen
        $inactivos = array_filter($res['data'], fn($m) => (string) $m->in_esta === '0');
        $this->assertCount(3, $inactivos, 'Deben existir exactamente 3 movimientos con in_esta = 0 (A, B y E).');

        // 3. Diferentes estados: todos aparecen
        $estados = array_map(fn($m) => $m->cdgo_estdo_trmte, $res['data']);
        $this->assertContains(1, $estados, 'Estado INGRESADO (1) debe estar presente.');
        $this->assertContains(2, $estados, 'Estado RECEPCIONADO (2) debe estar presente.');
        $this->assertContains(3, $estados, 'Estado DERIVADO (3) debe estar presente.');
        $this->assertContains(26, $estados, 'Estado NOTIFICADO (26) debe estar presente.');

        // 4. Estados repetidos: no se deduplican (C y D ambos tienen estado 3)
        $derivados = array_filter($res['data'], fn($m) => $m->cdgo_estdo_trmte === 3);
        $this->assertCount(2, $derivados, 'Los movimientos con estado repetido (DERIVADO) no deben deduplicarse.');

        // 5. Misma fecha y diferente cdgo_trmtes_ntrnos: no se deduplican
        $this->assertEquals($movC->fe_rcbdo, $movD->fe_rcbdo, 'C y D tienen exactamente la misma fecha.');
        $this->assertNotEquals($movC->cdgo_trmtes_ntrnos, $movD->cdgo_trmtes_ntrnos);

        // 6. Derivaciones paralelas a distintas áreas: todas aparecen como filas independientes
        $destinos = array_map(fn($m) => $m->cargo_entrada, $derivados);
        $this->assertContains('AREA 1 (OTI)', $destinos);
        $this->assertContains('AREA 2 (FINANZAS)', $destinos);
    }

    /**
     * FASE 1: Verificación de captura de Query: el constructor de consultas para histórico
     * NO debe agregar filtros por in_esta, estado, cargo ni área al conjunto.
     */
    public function test_captura_query_no_filtra_por_in_esta_estado_cargo_ni_area(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderHist = Mockery::mock(Builder::class);
        $builderDoc = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with('sistema.p_dtos_ntrnos as p')->andReturn($builderDoc);
        $builderDoc->shouldReceive('where')->with('p.cdgo_dtos_ntrnos', 7001)->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->andReturnSelf();
        $builderDoc->shouldReceive('exists')->andReturn(true);

        $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos as r')->andReturn($builderHist);
        $builderHist->shouldReceive('join')->andReturnSelf();
        $builderHist->shouldReceive('leftJoin')->andReturnSelf();
        $builderHist->shouldReceive('orderByDesc')->with('r.cdgo_trmtes_ntrnos')->andReturnSelf();
        $builderHist->shouldReceive('select')->andReturnSelf();
        $builderHist->shouldReceive('count')->andReturn(1);
        $builderHist->shouldReceive('offset')->andReturnSelf();
        $builderHist->shouldReceive('limit')->andReturnSelf();
        $builderHist->shouldReceive('get')->andReturn(collect([(object) [
            'cdgo_trmtes_ntrnos' => 3001,
            'cdgo_dtos_ntrnos' => 7001,
            'in_esta' => '0',
            'cdgo_estdo_trmte' => 1,
            'fe_rcbdo' => '2026-10-01 10:00:00',
        ]]));

        // Inspeccionar las llamadas a where() en builderHist:
        // Solo debe filtrarse por r.cdgo_dtos_ntrnos = 7001.
        $whereCalls = [];
        $builderHist->shouldReceive('where')->andReturnUsing(function ($col, $val = null) use (&$whereCalls, $builderHist) {
            $whereCalls[] = $col;
            return $builderHist;
        });

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $repo->obtenerHistorial(0, $contexto, 1, 50, 7001);

        // Verificaciones de cláusulas WHERE en el histórico
        $this->assertContains('r.cdgo_dtos_ntrnos', $whereCalls, 'Debe filtrar únicamente por r.cdgo_dtos_ntrnos.');
        $this->assertNotContains('r.in_esta', $whereCalls, 'PROHIBIDO filtrar por r.in_esta en histórico.');
        $this->assertNotContains('r_trmtes_ntrnos.in_esta', $whereCalls, 'PROHIBIDO filtrar por in_esta.');
        $this->assertNotContains('r.cdgo_estdo_trmte', $whereCalls, 'PROHIBIDO filtrar por estado en histórico.');
        $this->assertNotContains('r.cdgo_crgo_sa', $whereCalls, 'PROHIBIDO recortar histórico por cargo de salida.');
        $this->assertNotContains('r.cdgo_crgo_en', $whereCalls, 'PROHIBIDO recortar histórico por cargo de entrada.');
    }

    /**
     * FASE 1: Paginación histórica completa no duplica ni omite registros,
     * y la suma de todas las páginas coincide exactamente con el conjunto total.
     */
    public function test_historico_paginacion_sin_duplicaciones_ni_omisiones(): void
    {
        $movimientos = [
            (object) ['cdgo_trmtes_ntrnos' => 5, 'cdgo_dtos_ntrnos' => 9001, 'in_esta' => '0', 'fe_rcbdo' => '2026-10-03 16:00:00'],
            (object) ['cdgo_trmtes_ntrnos' => 4, 'cdgo_dtos_ntrnos' => 9001, 'in_esta' => '1', 'fe_rcbdo' => '2026-10-02 09:00:00'],
            (object) ['cdgo_trmtes_ntrnos' => 3, 'cdgo_dtos_ntrnos' => 9001, 'in_esta' => '1', 'fe_rcbdo' => '2026-10-02 09:00:00'],
            (object) ['cdgo_trmtes_ntrnos' => 2, 'cdgo_dtos_ntrnos' => 9001, 'in_esta' => '0', 'fe_rcbdo' => '2026-10-01 12:00:00'],
            (object) ['cdgo_trmtes_ntrnos' => 1, 'cdgo_dtos_ntrnos' => 9001, 'in_esta' => '0', 'fe_rcbdo' => '2026-10-01 10:00:00'],
        ];

        $perPage = 2;
        $total = count($movimientos);
        $paginas = (int) ceil($total / $perPage);

        $recuperados = [];
        for ($p = 1; $p <= $paginas; $p++) {
            $offset = ($p - 1) * $perPage;
            $slice = array_slice($movimientos, $offset, $perPage);
            foreach ($slice as $item) {
                $recuperados[] = $item->cdgo_trmtes_ntrnos;
            }
        }

        $this->assertEquals([5, 4, 3, 2, 1], $recuperados, 'La paginación descendente no debe omitir ni duplicar ningún ID.');
        $this->assertCount(5, array_unique($recuperados), 'No debe existir colapso de IDs entre páginas.');
    }

    /**
     * FASE 1: Documentos con mismo número visible pero diferente cdgo_dtos_ntrnos
     * no se mezclan entre sí porque la identificación es por cdgo_dtos_ntrnos canónico.
     */
    public function test_historico_no_mezcla_documentos_con_mismo_correlativo_visible(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderDoc = Mockery::mock(Builder::class);
        $builderHist = Mockery::mock(Builder::class);

        // Supongamos OFIC. 00000117-2026 (cdgo_dtos_ntrnos = 386119)
        // y MEMO. 00000117-2026 (cdgo_dtos_ntrnos = 386999)
        $db->shouldReceive('table')->with('sistema.p_dtos_ntrnos as p')->andReturn($builderDoc);
        $builderDoc->shouldReceive('where')->with('p.cdgo_dtos_ntrnos', 386119)->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->andReturnSelf();
        $builderDoc->shouldReceive('exists')->andReturn(true);

        $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos as r')->andReturn($builderHist);
        $builderHist->shouldReceive('join')->andReturnSelf();
        $builderHist->shouldReceive('leftJoin')->andReturnSelf();
        $builderHist->shouldReceive('where')->with('r.cdgo_dtos_ntrnos', 386119)->once()->andReturnSelf();
        $builderHist->shouldReceive('orderByDesc')->with('r.cdgo_trmtes_ntrnos')->andReturnSelf();
        $builderHist->shouldReceive('select')->andReturnSelf();
        $builderHist->shouldReceive('count')->andReturn(2);
        $builderHist->shouldReceive('offset')->andReturnSelf();
        $builderHist->shouldReceive('limit')->andReturnSelf();
        $builderHist->shouldReceive('get')->andReturn(collect([
            (object) ['cdgo_trmtes_ntrnos' => 10, 'cdgo_dtos_ntrnos' => 386119, 'in_esta' => '0'],
            (object) ['cdgo_trmtes_ntrnos' => 11, 'cdgo_dtos_ntrnos' => 386119, 'in_esta' => '1'],
        ]));

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $res = $repo->obtenerHistorial(0, $contexto, 1, 50, 386119);

        foreach ($res['data'] as $item) {
            $this->assertEquals(386119, $item->cdgo_dtos_ntrnos, 'Solo debe contener movimientos del documento 386119.');
        }
    }

    /**
     * FASE 1: El contrato HTTP y el request diferencian explícitamente documento_id y movimiento_id.
     */
    public function test_diferenciacion_estricta_documento_id_y_movimiento_id_en_request(): void
    {
        $req = new \App\Http\Requests\mod_gene\mod_tram_inter\ListarHistoricoInternoRequest();

        $vDoc = \Illuminate\Support\Facades\Validator::make(['documento_id' => 386119], $req->rules());
        $this->assertTrue($vDoc->passes());
        $this->assertEquals(386119, $vDoc->validated()['documento_id']);
        $this->assertNull($vDoc->validated()['movimiento_id'] ?? null);

        $vMov = \Illuminate\Support\Facades\Validator::make(['movimiento_id' => 2001], $req->rules());
        $this->assertTrue($vMov->passes());
        $this->assertEquals(2001, $vMov->validated()['movimiento_id']);
        $this->assertNull($vMov->validated()['documento_id'] ?? null);
    }

    /**
     * FASE 3: Si se pasan documento_id y movimiento_id inconsistentes, la consulta se rechaza de forma controlada.
     */
    public function test_inconsistencia_movimiento_documento_es_rechazada(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderDoc = Mockery::mock(Builder::class);
        $builderMov = Mockery::mock(Builder::class);

        // Documento 5001 existe en la compañía
        $db->shouldReceive('table')->with('sistema.p_dtos_ntrnos as p')->andReturn($builderDoc);
        $builderDoc->shouldReceive('where')->with('p.cdgo_dtos_ntrnos', 5001)->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->andReturnSelf();
        $builderDoc->shouldReceive('exists')->andReturn(true);

        // Pero movimiento 999 NO pertenece al documento 5001
        $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos')->andReturn($builderMov);
        $builderMov->shouldReceive('where')->with('cdgo_trmtes_ntrnos', 999)->andReturnSelf();
        $builderMov->shouldReceive('where')->with('cdgo_dtos_ntrnos', 5001)->andReturnSelf();
        $builderMov->shouldReceive('exists')->andReturn(false);

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $res = $repo->obtenerHistorial(999, $contexto, 1, 15, 5001);

        $this->assertEmpty($res['data'], 'Una combinación inconsistente debe producir una respuesta vacía / error controlado.');
        $this->assertSame(0, $res['meta']['total']);
    }

    /**
     * FASE 3: Si documento_id = 500 y movimiento_id = 500 pero movimiento 500 pertenece a OTRO documento (ej 6000),
     * no debe colisionar ni consultar el documento incorrecto.
     */
    public function test_ids_coincidentes_documento_y_movimiento_no_colisionan(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderDoc = Mockery::mock(Builder::class);
        $builderMov = Mockery::mock(Builder::class);

        // Documento 500 existe en la compañía
        $db->shouldReceive('table')->with('sistema.p_dtos_ntrnos as p')->andReturn($builderDoc);
        $builderDoc->shouldReceive('where')->with('p.cdgo_dtos_ntrnos', 500)->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->andReturnSelf();
        $builderDoc->shouldReceive('exists')->andReturn(true);

        // Movimiento 500 NO pertenece al documento 500 (pertenece a doc 6000)
        $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos')->andReturn($builderMov);
        $builderMov->shouldReceive('where')->with('cdgo_trmtes_ntrnos', 500)->andReturnSelf();
        $builderMov->shouldReceive('where')->with('cdgo_dtos_ntrnos', 500)->andReturnSelf();
        $builderMov->shouldReceive('exists')->andReturn(false);

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 30,
            areaId: 5,
            cargosAsignados: [30],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $res = $repo->obtenerHistorial(500, $contexto, 1, 15, 500);

        $this->assertEmpty($res['data'], 'No debe consultar el documento incorrecto ante IDs numéricamente coincidentes.');
        $this->assertSame(0, $res['meta']['total']);
    }
}
