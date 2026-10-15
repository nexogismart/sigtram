<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Mockery;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;

class HistoricoOrdenLegacyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * TEST 1: Histórico Interno - Confirma orden exacto [movimiento 3, movimiento 2, movimiento 1]
     * con r_trmtes_ntrnos.cdgo_trmtes_ntrnos DESC (el más reciente primero).
     */
    public function test_historico_interno_entrega_orden_descendente_exacto_tres_movimientos(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderDoc = Mockery::mock(Builder::class);
        $builderHist = Mockery::mock(Builder::class);

        // Validación de pertenencia a compañía
        $db->shouldReceive('table')->with('sistema.p_dtos_ntrnos as p')->andReturn($builderDoc);
        $builderDoc->shouldReceive('where')->with('p.cdgo_dtos_ntrnos', 5001)->andReturnSelf();
        $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->andReturnSelf();
        $builderDoc->shouldReceive('exists')->andReturn(true);

        // Movimientos simulados (1=más antiguo, 2=intermedio, 3=más reciente)
        $mov1 = (object) [
            'cdgo_trmtes_ntrnos' => 101,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'MEMO. 00000101-2026',
            'cargo_salida' => 'SECRETARIA',
            'cargo_entrada' => 'SUBGERENCIA',
            'estado_descripcion' => 'INGRESADO',
            'cdgo_estdo_trmte' => 1,
            'fe_rcbdo' => '2026-10-01 08:00:00',
            'de_obser_ntrnos' => 'Primer movimiento (antiguo)',
            'de_obser' => 'Primer movimiento (antiguo)',
            'in_esta' => '0',
            'in_copia' => '0',
        ];

        $mov2 = (object) [
            'cdgo_trmtes_ntrnos' => 102,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'MEMO. 00000101-2026',
            'cargo_salida' => 'SUBGERENCIA',
            'cargo_entrada' => 'GERENCIA',
            'estado_descripcion' => 'RECEPCIONADO',
            'cdgo_estdo_trmte' => 2,
            'fe_rcbdo' => '2026-10-02 10:00:00',
            'de_obser_ntrnos' => 'Segundo movimiento (intermedio)',
            'de_obser' => 'Segundo movimiento (intermedio)',
            'in_esta' => '0',
            'in_copia' => '0',
        ];

        $mov3 = (object) [
            'cdgo_trmtes_ntrnos' => 103,
            'cdgo_dtos_ntrnos' => 5001,
            'nu_tram_todo' => 'MEMO. 00000101-2026',
            'cargo_salida' => 'GERENCIA',
            'cargo_entrada' => 'ALCALDIA',
            'estado_descripcion' => 'DERIVADO',
            'cdgo_estdo_trmte' => 2,
            'fe_rcbdo' => '2026-10-03 14:00:00',
            'de_obser_ntrnos' => 'Tercer movimiento (más reciente)',
            'de_obser' => 'Tercer movimiento (más reciente)',
            'in_esta' => '1',
            'in_copia' => '0',
        ];

        // Construcción de la consulta: debe ordenar explícitamente por orderByDesc('r.cdgo_trmtes_ntrnos')
        $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos as r')->andReturn($builderHist);
        $builderHist->shouldReceive('join')->andReturnSelf();
        $builderHist->shouldReceive('leftJoin')->times(3)->andReturnSelf();
        $builderHist->shouldReceive('where')->with('r.cdgo_dtos_ntrnos', 5001)->andReturnSelf();
        $builderHist->shouldReceive('orderByDesc')->with('r.cdgo_trmtes_ntrnos')->once()->andReturnSelf();
        $builderHist->shouldReceive('select')->andReturnSelf();

        // Conteo total y paginación
        $builderHist->shouldReceive('count')->andReturn(3);
        $builderHist->shouldReceive('offset')->with(0)->andReturnSelf();
        $builderHist->shouldReceive('limit')->with(50)->andReturnSelf();

        // Base de datos entrega en orden DESC: [mov3, mov2, mov1]
        $builderHist->shouldReceive('get')->andReturn(collect([$mov3, $mov2, $mov1]));

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 20,
            areaId: 5,
        );

        $repo = new TramiteInternoRepository($db);
        $resultado = $repo->obtenerHistorial(0, $contexto, 1, 50, 5001);

        $this->assertCount(3, $resultado['data'], 'Debe retornar los tres movimientos.');

        $idsObtenidos = array_map(fn($item) => $item->cdgo_trmtes_ntrnos, $resultado['data']);

        // Aserción estricta: [movimiento 3, movimiento 2, movimiento 1]
        $this->assertSame([103, 102, 101], $idsObtenidos, 'El histórico interno debe ordenarse estrictamente [movimiento 3, movimiento 2, movimiento 1].');
        $this->assertSame(103, $resultado['data'][0]->cdgo_trmtes_ntrnos, 'El movimiento más reciente (3) debe ser el primero.');
        $this->assertSame(102, $resultado['data'][1]->cdgo_trmtes_ntrnos, 'El movimiento intermedio (2) debe ser el segundo.');
        $this->assertSame(101, $resultado['data'][2]->cdgo_trmtes_ntrnos, 'El movimiento más antiguo (1) debe ser el tercero.');
    }

    /**
     * TEST 2: Histórico Externo - Confirma orden exacto [movimiento 3, movimiento 2, movimiento 1]
     * con r_trmtes_dcmntos.cdgo_trmte_dcmnto DESC (el más reciente primero).
     */
    public function test_historico_externo_entrega_orden_descendente_exacto_tres_movimientos(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderHist = Mockery::mock(Builder::class);

        $mov1 = (object) [
            'cdgo_trmte_dcmnto' => 301,
            'cdgo_dto_trmte' => 9999,
            'fe_rcbdo_trmte' => '2026-10-01 09:00:00',
            'cdgo_estdo_trmte' => 1,
            'dc_estdo_trmte' => 'INGRESADO',
            'de_obse' => 'Mesa de partes (antiguo)',
            'nom_archivo' => null,
            'cdgo_crgo_sa' => 10,
            'cdgo_crgo_en' => 20,
            'cargo_salida' => 'MESA DE PARTES',
            'cargo_entrada' => 'SUBGERENCIA',
            'area_salida' => 'TRAMITE',
            'area_entrada' => 'SUBGERENCIA',
            'cdgo_lgin' => 5,
            'login_actor' => 'mesa1',
            'usuario_actor' => 'OPERADOR MESA',
            'perfil_id_actor' => 2,
            'perfil_actor' => 'OPERADOR',
            'in_esta' => '0',
        ];

        $mov2 = (object) [
            'cdgo_trmte_dcmnto' => 302,
            'cdgo_dto_trmte' => 9999,
            'fe_rcbdo_trmte' => '2026-10-02 11:30:00',
            'cdgo_estdo_trmte' => 2,
            'dc_estdo_trmte' => 'RECEPCIONADO',
            'de_obse' => 'Pase a Gerencia (intermedio)',
            'nom_archivo' => null,
            'cdgo_crgo_sa' => 20,
            'cdgo_crgo_en' => 30,
            'cargo_salida' => 'SUBGERENCIA',
            'cargo_entrada' => 'GERENCIA',
            'area_salida' => 'SUBGERENCIA',
            'area_entrada' => 'GERENCIA MUNICIPAL',
            'cdgo_lgin' => 6,
            'login_actor' => 'subg1',
            'usuario_actor' => 'SUBGERENTE',
            'perfil_id_actor' => 2,
            'perfil_actor' => 'OPERADOR',
            'in_esta' => '0',
        ];

        $mov3 = (object) [
            'cdgo_trmte_dcmnto' => 303,
            'cdgo_dto_trmte' => 9999,
            'fe_rcbdo_trmte' => '2026-10-03 16:00:00',
            'cdgo_estdo_trmte' => 2,
            'dc_estdo_trmte' => 'RECEPCIONADO',
            'de_obse' => 'Para resolución final (más reciente)',
            'nom_archivo' => null,
            'cdgo_crgo_sa' => 30,
            'cdgo_crgo_en' => 40,
            'cargo_salida' => 'GERENCIA',
            'cargo_entrada' => 'ASESORIA JURIDICA',
            'area_salida' => 'GERENCIA MUNICIPAL',
            'area_entrada' => 'ASESORIA JURIDICA',
            'cdgo_lgin' => 7,
            'login_actor' => 'ger1',
            'usuario_actor' => 'GERENTE',
            'perfil_id_actor' => 2,
            'perfil_actor' => 'OPERADOR',
            'in_esta' => '1',
        ];

        // Construcción de la consulta: debe ordenar explícitamente por orderByDesc('r.cdgo_trmte_dcmnto')
        $db->shouldReceive('table')->with(Mockery::pattern('/r_trmtes_dcmntos as r/'))->andReturn($builderHist);
        $builderHist->shouldReceive('leftJoin')->times(8)->andReturnSelf();
        $builderHist->shouldReceive('where')->with('r.cdgo_dto_trmte', 9999)->once()->andReturnSelf();
        $builderHist->shouldReceive('select')->andReturnSelf();
        $builderHist->shouldReceive('orderByDesc')->with('r.cdgo_trmte_dcmnto')->once()->andReturnSelf();

        // Base de datos entrega en orden DESC: [mov3, mov2, mov1]
        $builderHist->shouldReceive('get')->andReturn(collect([$mov3, $mov2, $mov1]));

        // Consultas complementarias masivas
        $builderDest = Mockery::mock(Builder::class);
        $db->shouldReceive('table')->with(Mockery::pattern('/r_crgos_lgin as rl/'))->andReturn($builderDest);
        $builderDest->shouldReceive('join')->twice()->andReturnSelf();
        $builderDest->shouldReceive('whereIn')->andReturnSelf();
        $builderDest->shouldReceive('where')->andReturnSelf();
        $builderDest->shouldReceive('select')->andReturnSelf();
        $builderDest->shouldReceive('get')->andReturn(collect([]));

        $repo = new TramiteExternoRepository($db);
        $resultado = $repo->obtenerHistoricoMovimientos(9999);

        $this->assertCount(3, $resultado, 'Debe retornar los tres movimientos externos.');

        $idsObtenidos = array_column($resultado, 'cdgo_trmte_dcmnto');

        // Aserción estricta: [movimiento 3, movimiento 2, movimiento 1]
        $this->assertSame([303, 302, 301], $idsObtenidos, 'El histórico externo debe ordenarse estrictamente [movimiento 3, movimiento 2, movimiento 1].');
        $this->assertSame(303, $resultado[0]['cdgo_trmte_dcmnto'], 'El movimiento más reciente (3) debe ser el primero.');
        $this->assertSame(302, $resultado[1]['cdgo_trmte_dcmnto'], 'El movimiento intermedio (2) debe ser el segundo.');
        $this->assertSame(301, $resultado[2]['cdgo_trmte_dcmnto'], 'El movimiento más antiguo (1) debe ser el tercero.');
    }

    /**
     * TEST 3: Paginación interna - Confirma que mantiene orden descendente en todas las páginas,
     * y no repite ni omite movimientos.
     */
    public function test_paginacion_interna_mantiene_orden_descendente_en_todas_las_paginas_sin_repetir_ni_omitir(): void
    {
        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 20,
            areaId: 5,
        );

        // 6 movimientos en orden descendente estricto [6, 5, 4, 3, 2, 1]
        $todosLosMovimientos = [
            (object) ['cdgo_trmtes_ntrnos' => 600, 'cdgo_dtos_ntrnos' => 8888, 'in_esta' => '1', 'nu_tram_todo' => 'DOC 8888'],
            (object) ['cdgo_trmtes_ntrnos' => 500, 'cdgo_dtos_ntrnos' => 8888, 'in_esta' => '0', 'nu_tram_todo' => 'DOC 8888'],
            (object) ['cdgo_trmtes_ntrnos' => 400, 'cdgo_dtos_ntrnos' => 8888, 'in_esta' => '0', 'nu_tram_todo' => 'DOC 8888'],
            (object) ['cdgo_trmtes_ntrnos' => 300, 'cdgo_dtos_ntrnos' => 8888, 'in_esta' => '0', 'nu_tram_todo' => 'DOC 8888'],
            (object) ['cdgo_trmtes_ntrnos' => 200, 'cdgo_dtos_ntrnos' => 8888, 'in_esta' => '0', 'nu_tram_todo' => 'DOC 8888'],
            (object) ['cdgo_trmtes_ntrnos' => 100, 'cdgo_dtos_ntrnos' => 8888, 'in_esta' => '0', 'nu_tram_todo' => 'DOC 8888'],
        ];

        $perPage = 2;
        $total = count($todosLosMovimientos); // 6
        $totalPaginas = (int) ceil($total / $perPage); // 3 páginas

        $idsRecorridosPorPaginas = [];

        for ($pagina = 1; $pagina <= $totalPaginas; $pagina++) {
            $offset = ($pagina - 1) * $perPage;
            $sliceEsperado = array_slice($todosLosMovimientos, $offset, $perPage);

            $db = Mockery::mock(DatabaseManager::class);
            $builderDoc = Mockery::mock(Builder::class);
            $builderHist = Mockery::mock(Builder::class);

            $db->shouldReceive('table')->with('sistema.p_dtos_ntrnos as p')->andReturn($builderDoc);
            $builderDoc->shouldReceive('where')->with('p.cdgo_dtos_ntrnos', 8888)->andReturnSelf();
            $builderDoc->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->andReturnSelf();
            $builderDoc->shouldReceive('exists')->andReturn(true);

            $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos as r')->andReturn($builderHist);
            $builderHist->shouldReceive('join')->andReturnSelf();
            $builderHist->shouldReceive('leftJoin')->times(3)->andReturnSelf();
            $builderHist->shouldReceive('where')->with('r.cdgo_dtos_ntrnos', 8888)->andReturnSelf();
            $builderHist->shouldReceive('orderByDesc')->with('r.cdgo_trmtes_ntrnos')->once()->andReturnSelf();
            $builderHist->shouldReceive('select')->andReturnSelf();
            $builderHist->shouldReceive('count')->andReturn($total);
            $builderHist->shouldReceive('offset')->with($offset)->once()->andReturnSelf();
            $builderHist->shouldReceive('limit')->with($perPage)->once()->andReturnSelf();
            $builderHist->shouldReceive('get')->andReturn(collect($sliceEsperado));

            if ($pagina > 1) {
                $builderFrontera = Mockery::mock(Builder::class);
                $db->shouldReceive('table')->with('sistema.r_trmtes_ntrnos')->andReturn($builderFrontera);
                $builderFrontera->shouldReceive('where')->with('cdgo_dtos_ntrnos', 8888)->andReturnSelf();
                $builderFrontera->shouldReceive('orderByDesc')->with('cdgo_trmtes_ntrnos')->andReturnSelf();
                $builderFrontera->shouldReceive('offset')->with($offset - 1)->andReturnSelf();
                $builderFrontera->shouldReceive('limit')->with(1)->andReturnSelf();
                $builderFrontera->shouldReceive('value')->with('fe_rcbdo')->andReturn(
                    $todosLosMovimientos[$offset - 1]->fe_rcbdo ?? null
                );
            }

            $repo = new TramiteInternoRepository($db);
            $resPagina = $repo->obtenerHistorial(0, $contexto, $pagina, $perPage, 8888);

            $this->assertSame($pagina, $resPagina['meta']['current_page']);
            $this->assertSame($total, $resPagina['meta']['total']);
            $this->assertSame($totalPaginas, $resPagina['meta']['last_page']);
            $this->assertCount(2, $resPagina['data']);

            foreach ($resPagina['data'] as $mov) {
                $idsRecorridosPorPaginas[] = $mov->cdgo_trmtes_ntrnos;
            }
        }

        // Validación global de paginación descendente:
        // 1. Debe haber exactamente 6 movimientos
        $this->assertCount(6, $idsRecorridosPorPaginas, 'Deben haberse recuperado los 6 movimientos en total.');

        // 2. No debe haber ningún ID repetido
        $this->assertSame(
            count($idsRecorridosPorPaginas),
            count(array_unique($idsRecorridosPorPaginas)),
            'La paginación descendente no debe repetir ningún movimiento entre páginas.'
        );

        // 3. No debe faltar ningún movimiento del universo total
        $this->assertSame(
            [600, 500, 400, 300, 200, 100],
            $idsRecorridosPorPaginas,
            'La unión de todas las páginas debe ser exactamente la secuencia descendente completa sin omisiones ni saltos.'
        );

        // 4. Cada elemento debe ser estrictamente menor que el anterior
        for ($i = 0; $i < count($idsRecorridosPorPaginas) - 1; $i++) {
            $this->assertGreaterThan(
                $idsRecorridosPorPaginas[$i + 1],
                $idsRecorridosPorPaginas[$i],
                "El movimiento en la posición {$i} debe ser mayor que el de la posición " . ($i + 1)
            );
        }
    }
}
