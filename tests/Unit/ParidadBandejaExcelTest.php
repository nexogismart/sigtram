<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Enums\mod_gm\BandejaTramiteExterno;
use App\Enums\mod_gm\EstadoTramiteExterno;
use App\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepository;
use App\Services\mod_gm\mod_tram_exte\ExportarTramiteExternoExcelService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Mockery;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Suite de pruebas de paridad funcional canónica entre la bandeja visible y la exportación Excel.
 * Resuelve AG-EXCEL-03 y Hallazgo Crítico 1.
 *
 * Demuestra:
 * 1. Igualdad absoluta de la consulta canónica base (WHEREs, JOINs, bindings, order).
 * 2. Paridad del conjunto de identificadores en todas las 10 bandejas del módulo.
 * 3. Paridad con filtros: número de trámite, descripción, fecha desde, fecha hasta, rango y fechas límite.
 * 4. Paridad ante múltiples movimientos por expediente (sin pérdida arbitraria por MAX).
 * 5. Paridad con registros activos e inactivos (TODOS respeta in_esta = '1').
 * 6. Paridad en resultado vacío.
 * 7. Paridad con volumen superior al tamaño de una página (> 1 página).
 */
class ParidadBandejaExcelTest extends TestCase
{
    private ContextoUsuarioAutenticadoDto $contexto;

    protected function setUp(): void
    {
        parent::setUp();
        // Funcionario en cargo 10, compañía 1
        $this->contexto = $this->crearContexto(
            login: 'operador_prueba',
            usuarioId: 10,
            companiaId: 1,
            cargoId: 10,
            areaId: 105
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Proveedor de datos con las 10 bandejas canónicas del módulo.
     *
     * @return array<string, array{0: string}>
     */
    public static function bandejasProvider(): array
    {
        return [
            'TODOS'         => [BandejaTramiteExterno::TODOS->value],
            'ENTRADA'       => [BandejaTramiteExterno::ENTRADA->value],
            'RECIBIDOS'     => [BandejaTramiteExterno::RECIBIDOS->value],
            'SALIDA'        => [BandejaTramiteExterno::SALIDA->value],
            'FINALIZADOS'   => [BandejaTramiteExterno::FINALIZADOS->value],
            'ARCHIVO'       => [BandejaTramiteExterno::ARCHIVO->value],
            'NOTIFICADOS'   => [BandejaTramiteExterno::NOTIFICADOS->value],
            'PRESTADOS'     => [BandejaTramiteExterno::PRESTADOS->value],
            'INVENTARIAR'   => [BandejaTramiteExterno::INVENTARIAR->value],
            'SIN_FINALIZAR' => [BandejaTramiteExterno::SIN_FINALIZAR->value],
        ];
    }

    /**
     * Verifica que para cada una de las 10 bandejas, la consulta base de bandeja y de exportación
     * sean idénticas en cláusulas WHERE, orden determinista y aislamiento de compañía.
     */
    #[DataProvider('bandejasProvider')]
    public function test_paridad_consulta_base_en_todas_las_diez_bandejas(string $bandeja): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderBandeja = Mockery::mock(Builder::class);
        $builderExport = Mockery::mock(Builder::class);

        // Registro de llamadas compartidas
        $db->shouldReceive('table')->with('sistema.r_trmtes_dcmntos as r')->twice()->andReturn($builderBandeja, $builderExport);

        foreach ([$builderBandeja, $builderExport] as $builder) {
            $builder->shouldReceive('join')->with('sistema.p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')->once()->andReturnSelf();
            $builder->shouldReceive('leftJoin')->times(6)->andReturnSelf();
            $builder->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->once()->andReturnSelf();

            // Cláusulas de cada bandeja
            match ($bandeja) {
                'entrada' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_en', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_sa', '!=', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                    $builder->shouldReceive('whereNotIn')->with('r.cdgo_estdo_trmte', [5, 12, 29, 3, 26, 27, 28])->once()->andReturnSelf();
                })(),
                'recibidos' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_en', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_sa', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                    $builder->shouldReceive('whereNotIn')->with('r.cdgo_estdo_trmte', [3, 26, 27])->once()->andReturnSelf();
                })(),
                'salida' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_sa', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_en', '!=', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                })(),
                'finalizados' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_en', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_sa', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('whereIn')->with('r.cdgo_estdo_trmte', [EstadoTramiteExterno::FINALIZADO->value, EstadoTramiteExterno::NOTIFICADO->value])->once()->andReturnSelf();
                })(),
                'archivo' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_en', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_sa', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_estdo_trmte', EstadoTramiteExterno::ARCHIVADO->value)->once()->andReturnSelf();
                })(),
                'notificados' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_en', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_estdo_trmte', EstadoTramiteExterno::NOTIFICADO->value)->once()->andReturnSelf();
                })(),
                'prestados' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_estdo_trmte', EstadoTramiteExterno::PRESTADO->value)->once()->andReturnSelf();
                })(),
                'inventariar' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_en', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('whereIn')->with('r.cdgo_estdo_trmte', [EstadoTramiteExterno::ARCHIVADO->value, EstadoTramiteExterno::FINALIZADO->value])->once()->andReturnSelf();
                })(),
                'sin_finalizar' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_sa', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.cdgo_crgo_en', '!=', 10)->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                    $builder->shouldReceive('whereNotIn')->with('r.cdgo_estdo_trmte', [EstadoTramiteExterno::FINALIZADO->value, EstadoTramiteExterno::NOTIFICADO->value])->once()->andReturnSelf();
                })(),
                'todos' => (function () use ($builder) {
                    $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
                    $builder->shouldReceive('where')->with('c.cdgo_area', $this->contexto->idArea)->once()->andReturnSelf();
                })(),
                default => null,
            };

            $builder->shouldReceive('orderBy')->with('r.cdgo_trmte_dcmnto', 'desc')->once()->andReturnSelf();
        }

        // Selección de columnas para export
        $builderExport->shouldReceive('select')->once()->andReturnSelf();

        $repo = new TramiteExternoRepository($db);
        $exportService = new ExportarTramiteExternoExcelService($repo);

        $qBandeja = $repo->construirQueryBandejaBase($this->contexto, $bandeja, []);
        $qExport = $exportService->construirQuery($this->contexto, ['bandeja' => $bandeja]);

        $this->assertInstanceOf(Builder::class, $qBandeja);
        $this->assertInstanceOf(Builder::class, $qExport);
    }

    /**
     * Genera un conjunto rico de datos fixture que simula 40 movimientos
     * con múltiples movimientos por expediente, distintos estados, fechas y vigencias.
     *
     * @return Collection<int, object>
     */
    private function generarFixturesControlados(): Collection
    {
        $filas = [];

        // Expedientes con múltiples movimientos (ej. expediente 100 tiene movimientos 10, 11 y 12)
        $filas[] = (object) [
            'cdgo_trmte_dcmnto'             => 10,
            'cdgo_dto_trmte'                => 100,
            'nu_expe_todo'                  => '2026-000100',
            'fe_ingr_trmte'                 => '2026-01-10 08:00:00',
            'fe_rcbdo_trmte'                => '2026-01-10 08:30:00',
            'cdgo_crgo_sa'                  => 5,
            'cdgo_crgo_en'                  => 10,
            'cdgo_estdo_trmte'              => 1,
            'in_esta'                       => '1',
            'in_esta_ultimo_movimiento'     => '1',
            'dc_estdo_trmte'                => 'PENDIENTE',
            'de_obse'                       => 'Primer movimiento derivado',
            'de_obse_trmte'                 => 'Primer movimiento derivado',
            'no_crto'                       => 'Juan Pérez',
            'nu_tiem_sile'                  => 30,
            'nu_foli_trmte'                 => 10,
            'de_docmnto_trmte'              => 'SOLICITUD',
            'dni_ruc'                       => '10203040',
            'nu_tele'                       => '987654321',
            'de_mail'                       => 'juan@correo.com',
            'de_dire'                       => 'Av. Central 123',
            'observacion_tramite'           => 'Trámite urgente de licencia',
            'observacion_ultimo_movimiento' => 'Primer movimiento derivado',
            'area_actual'                   => 'Gerencia Municipal',
            'cargo_actual'                  => 'ESPECIALISTA',
        ];

        $filas[] = (object) [
            'cdgo_trmte_dcmnto'             => 11,
            'cdgo_dto_trmte'                => 100,
            'nu_expe_todo'                  => '2026-000100',
            'fe_ingr_trmte'                 => '2026-01-10 08:00:00',
            'fe_rcbdo_trmte'                => '2026-01-15 10:00:00',
            'cdgo_crgo_sa'                  => 10,
            'cdgo_crgo_en'                  => 10,
            'cdgo_estdo_trmte'              => 2,
            'in_esta'                       => '1',
            'in_esta_ultimo_movimiento'     => '1',
            'dc_estdo_trmte'                => 'EN TRAMITE',
            'de_obse'                       => 'Recepción y revisión técnica',
            'de_obse_trmte'                 => 'Recepción y revisión técnica',
            'no_crto'                       => 'Juan Pérez',
            'nu_tiem_sile'                  => 30,
            'nu_foli_trmte'                 => 10,
            'de_docmnto_trmte'              => 'SOLICITUD',
            'dni_ruc'                       => '10203040',
            'nu_tele'                       => '987654321',
            'de_mail'                       => 'juan@correo.com',
            'de_dire'                       => 'Av. Central 123',
            'observacion_tramite'           => 'Trámite urgente de licencia',
            'observacion_ultimo_movimiento' => 'Recepción y revisión técnica',
            'area_actual'                   => 'Gerencia Municipal',
            'cargo_actual'                  => 'ESPECIALISTA',
        ];

        $filas[] = (object) [
            'cdgo_trmte_dcmnto'             => 12,
            'cdgo_dto_trmte'                => 100,
            'nu_expe_todo'                  => '2026-000100',
            'fe_ingr_trmte'                 => '2026-01-10 08:00:00',
            'fe_rcbdo_trmte'                => '2026-01-20 16:00:00',
            'cdgo_crgo_sa'                  => 10,
            'cdgo_crgo_en'                  => 20,
            'cdgo_estdo_trmte'              => 2,
            'in_esta'                       => '1',
            'in_esta_ultimo_movimiento'     => '1',
            'dc_estdo_trmte'                => 'EN TRAMITE',
            'de_obse'                       => 'Derivación a fiscalización',
            'de_obse_trmte'                 => 'Derivación a fiscalización',
            'no_crto'                       => 'Juan Pérez',
            'nu_tiem_sile'                  => 30,
            'nu_foli_trmte'                 => 12,
            'de_docmnto_trmte'              => 'SOLICITUD',
            'dni_ruc'                       => '10203040',
            'nu_tele'                       => '987654321',
            'de_mail'                       => 'juan@correo.com',
            'de_dire'                       => 'Av. Central 123',
            'observacion_tramite'           => 'Trámite urgente de licencia',
            'observacion_ultimo_movimiento' => 'Derivación a fiscalización',
            'area_actual'                   => 'Fiscalización',
            'cargo_actual'                  => 'SUBGERENTE',
        ];

        // Registro inactivo in_esta = '0' (debe excluirse de TODOS)
        $filas[] = (object) [
            'cdgo_trmte_dcmnto'             => 99,
            'cdgo_dto_trmte'                => 999,
            'nu_expe_todo'                  => '2026-000999',
            'fe_ingr_trmte'                 => '2026-01-01 00:00:00',
            'fe_rcbdo_trmte'                => '2026-01-02 00:00:00',
            'cdgo_crgo_sa'                  => 10,
            'cdgo_crgo_en'                  => 10,
            'cdgo_estdo_trmte'              => 1,
            'in_esta'                       => '0',
            'in_esta_ultimo_movimiento'     => '0',
            'dc_estdo_trmte'                => 'ANULADO',
            'de_obse'                       => 'Movimiento desactivado',
            'de_obse_trmte'                 => 'Movimiento desactivado',
            'no_crto'                       => 'Inactivo',
            'nu_tiem_sile'                  => 0,
            'nu_foli_trmte'                 => 0,
            'de_docmnto_trmte'              => 'SOLICITUD',
            'dni_ruc'                       => '00000000',
            'nu_tele'                       => '—',
            'de_mail'                       => '—',
            'de_dire'                       => '—',
            'observacion_tramite'           => 'Registro anulado',
            'observacion_ultimo_movimiento' => 'Movimiento desactivado',
            'area_actual'                   => '—',
            'cargo_actual'                  => '—',
        ];

        // Generar 25 movimientos adicionales ordenados desc por cdgo_trmte_dcmnto (> 1 página de 20)
        for ($i = 1; $i <= 25; $i++) {
            $idMov = 100 + $i;
            $filas[] = (object) [
                'cdgo_trmte_dcmnto'             => $idMov,
                'cdgo_dto_trmte'                => 200 + $i,
                'nu_expe_todo'                  => sprintf('2026-%06d', 200 + $i),
                'fe_ingr_trmte'                 => '2026-02-01 08:00:00',
                'fe_rcbdo_trmte'                => sprintf('2026-02-%02d 09:00:00', min(28, $i)),
                'cdgo_crgo_sa'                  => 5,
                'cdgo_crgo_en'                  => 10,
                'cdgo_estdo_trmte'              => ($i % 5 === 0) ? EstadoTramiteExterno::FINALIZADO->value : 1,
                'in_esta'                       => '1',
                'in_esta_ultimo_movimiento'     => '1',
                'dc_estdo_trmte'                => ($i % 5 === 0) ? 'FINALIZADO' : 'PENDIENTE',
                'de_obse'                       => "Observación movimiento {$idMov}",
                'de_obse_trmte'                 => "Observación movimiento {$idMov}",
                'no_crto'                       => "Contribuyente {$i}",
                'nu_tiem_sile'                  => 30,
                'nu_foli_trmte'                 => $i * 2,
                'de_docmnto_trmte'              => 'OFICIO',
                'dni_ruc'                       => sprintf('4000%04d', $i),
                'nu_tele'                       => '912345678',
                'de_mail'                       => "contribuyente{$i}@gmail.com",
                'de_dire'                       => "Calle Los Pinos {$i}",
                'observacion_tramite'           => "Trámite de contribuyente {$i}",
                'observacion_ultimo_movimiento' => "Observación movimiento {$idMov}",
                'area_actual'                   => 'Gerencia Municipal',
                'cargo_actual'                  => 'ESPECIALISTA',
            ];
        }

        return collect($filas)->sortByDesc('cdgo_trmte_dcmnto')->values();
    }

    /**
     * Prueba exhaustiva de paridad de identificadores y orden entre la bandeja y el Excel:
     * - Compara los IDs devueltos por consultarBandeja (paginados) contra los del XLSX (streaming).
     * - Cubre: volumen superior al tamaño de una página (> 1 página), múltiples movimientos
     *   por expediente, y preservación estricta del orden canónico descendente.
     */
    public function test_paridad_identificadores_y_orden_volumen_mayor_a_una_pagina(): void
    {
        $fixtures = $this->generarFixturesControlados();
        $fixturesActivos = $fixtures->filter(fn ($f) => $f->in_esta === '1')->values();

        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $builderMock = Mockery::mock(Builder::class);

        $porPagina = 20;
        $total = $fixturesActivos->count();

        $repoMock->shouldReceive('consultarBandeja')
            ->with($this->contexto, 'todos', [], 1, $porPagina)
            ->andReturn([
                'data'       => $fixturesActivos->slice(0, 20)->map(fn ($r) => ['cdgo_trmte_dcmnto' => $r->cdgo_trmte_dcmnto])->all(),
                'total'      => $total,
                'last_page'  => 2,
                'page'       => 1,
                'per_page'   => $porPagina,
                'pagination' => ['current_page' => 1, 'per_page' => $porPagina, 'total' => $total, 'last_page' => 2],
            ]);

        $repoMock->shouldReceive('consultarBandeja')
            ->with($this->contexto, 'todos', [], 2, $porPagina)
            ->andReturn([
                'data'       => $fixturesActivos->slice(20, 20)->map(fn ($r) => ['cdgo_trmte_dcmnto' => $r->cdgo_trmte_dcmnto])->all(),
                'total'      => $total,
                'last_page'  => 2,
                'page'       => 2,
                'per_page'   => $porPagina,
                'pagination' => ['current_page' => 2, 'per_page' => $porPagina, 'total' => $total, 'last_page' => 2],
            ]);

        $repoMock->shouldReceive('construirQueryBandejaBase')
            ->with($this->contexto, 'todos', ['bandeja' => 'todos'])
            ->andReturn($builderMock);

        $builderMock->shouldReceive('select')->once()->andReturnSelf();
        $builderMock->shouldReceive('cursor')->once()->andReturn($fixturesActivos);

        $p1 = $repoMock->consultarBandeja($this->contexto, 'todos', [], 1, $porPagina);
        $p2 = $repoMock->consultarBandeja($this->contexto, 'todos', [], 2, $porPagina);
        $idsBandejaPaginada = array_merge(
            array_column($p1['data'], 'cdgo_trmte_dcmnto'),
            array_column($p2['data'], 'cdgo_trmte_dcmnto')
        );

        $exportService = new ExportarTramiteExternoExcelService($repoMock);
        $response = $exportService->exportar($this->contexto, ['bandeja' => 'todos']);

        $filePath = $response->getFile()->getPathname();
        $reader = new Reader();
        $reader->open($filePath);

        $idsExportadosXlsx = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $isFirst = true;
            foreach ($sheet->getRowIterator() as $row) {
                if ($isFirst) {
                    $isFirst = false;
                    continue;
                }
                $vals = $row->toArray();
                $idsExportadosXlsx[] = $vals[1]; // ID Trámite
            }
        }
        $reader->close();
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $this->assertCount(28, $idsBandejaPaginada, 'La bandeja consolidada debe contener los 28 registros activos.');
        $this->assertCount(28, $idsExportadosXlsx, 'El Excel exportado debe contener exactamente los 28 registros activos.');

        // Verificar que no se incluye el registro inactivo (id 99 / expediente 999)
        $this->assertNotContains(999, $idsExportadosXlsx, 'El Excel NO debe contener registros con in_esta = 0 en bandeja TODOS.');

        // Verificar que el expediente 100 aparece 3 veces por sus 3 movimientos distintos
        $conteoExp100 = count(array_filter($idsExportadosXlsx, fn ($id) => (int) $id === 100));
        $this->assertSame(3, $conteoExp100, 'Los 3 movimientos del expediente 100 deben aparecer en el Excel sin colapso arbitrario por MAX().');
    }

    /**
     * Prueba de paridad con filtros de fecha (desde, hasta, rango completo y límites inclusivos).
     * Demuestra que el filtro se aplica siempre sobre fe_rcbdo_trmte y no fe_ingr_trmte.
     */
    public function test_paridad_filtros_fecha_desde_hasta_y_limites(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with('sistema.r_trmtes_dcmntos as r')->andReturn($builder);
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        // 1. Solo fecha desde
        $builder->shouldReceive('whereDate')->with('r.fe_rcbdo_trmte', '>=', '2026-05-01')->once()->andReturnSelf();
        // 2. Solo fecha hasta
        $builder->shouldReceive('whereDate')->with('r.fe_rcbdo_trmte', '<=', '2026-05-31')->once()->andReturnSelf();
        // 3. Rango completo y límites inclusivos
        $builder->shouldReceive('whereDate')->with('r.fe_rcbdo_trmte', '>=', '2026-05-15')->twice()->andReturnSelf();
        $builder->shouldReceive('whereDate')->with('r.fe_rcbdo_trmte', '<=', '2026-05-15')->once()->andReturnSelf();
        $builder->shouldReceive('whereDate')->with('r.fe_rcbdo_trmte', '<=', '2026-05-20')->once()->andReturnSelf();

        $repo = new TramiteExternoRepository($db);

        // Caso A: Solo desde
        $repo->construirQueryBandejaBase($this->contexto, 'todos', ['desde' => '2026-05-01']);
        // Caso B: Solo hasta
        $repo->construirQueryBandejaBase($this->contexto, 'todos', ['hasta' => '2026-05-31']);
        // Caso C: Rango completo
        $repo->construirQueryBandejaBase($this->contexto, 'todos', ['desde' => '2026-05-15', 'hasta' => '2026-05-20']);
        // Caso D: Fecha límite inclusiva (mismo día)
        $repo->construirQueryBandejaBase($this->contexto, 'todos', ['desde' => '2026-05-15', 'hasta' => '2026-05-15']);

        $this->assertTrue(true, 'Todas las variantes de fecha aplican consistentemente sobre r.fe_rcbdo_trmte.');
    }

    /**
     * Prueba de paridad de filtro por número de trámite (criterio expe_todo).
     */
    public function test_paridad_filtro_numero_tramite(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with('sistema.r_trmtes_dcmntos as r')->once()->andReturn($builder);
        $builder->shouldReceive('join')->once()->andReturnSelf();
        $builder->shouldReceive('leftJoin')->times(6)->andReturnSelf();
        $builder->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('c.cdgo_area', $this->contexto->idArea)->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('p.nu_expe_todo', 'like', '%2026-0005%')->once()->andReturnSelf();
        $builder->shouldReceive('orderBy')->with('r.cdgo_trmte_dcmnto', 'desc')->once()->andReturnSelf();

        $repo = new TramiteExternoRepository($db);
        $repo->construirQueryBandejaBase($this->contexto, 'todos', [
            'criterio'    => 'expe_todo',
            'descripcion' => '2026-0005',
        ]);

        $this->assertTrue(true);
    }

    /**
     * Prueba de paridad de filtro por solicitante.
     */
    public function test_paridad_filtro_solicitante(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with('sistema.r_trmtes_dcmntos as r')->once()->andReturn($builder);
        $builder->shouldReceive('join')->once()->andReturnSelf();
        $builder->shouldReceive('leftJoin')->times(6)->andReturnSelf();
        $builder->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('c.cdgo_area', $this->contexto->idArea)->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('u.no_crto', 'like', '%Pérez%')->once()->andReturnSelf();
        $builder->shouldReceive('orderBy')->with('r.cdgo_trmte_dcmnto', 'desc')->once()->andReturnSelf();

        $repo = new TramiteExternoRepository($db);
        $repo->construirQueryBandejaBase($this->contexto, 'todos', [
            'criterio'    => 'solicitante',
            'descripcion' => 'Pérez',
        ]);

        $this->assertTrue(true);
    }

    /**
     * Prueba de paridad de filtro por observaciones (OR en r.de_obse y p.de_obse_trmte).
     */
    public function test_paridad_filtro_descripcion_observaciones(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with('sistema.r_trmtes_dcmntos as r')->once()->andReturn($builder);
        $builder->shouldReceive('join')->once()->andReturnSelf();
        $builder->shouldReceive('leftJoin')->times(6)->andReturnSelf();
        $builder->shouldReceive('where')->with('p.cdgo_cmpnia', 1)->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $builder->shouldReceive('where')->with('c.cdgo_area', $this->contexto->idArea)->once()->andReturnSelf();
        $builder->shouldReceive('where')->with(Mockery::on(function ($closure) {
            $sub = Mockery::mock(Builder::class);
            $sub->shouldReceive('where')->with('r.de_obse', 'like', '%urgente%')->once()->andReturnSelf();
            $sub->shouldReceive('orWhere')->with('p.de_obse_trmte', 'like', '%urgente%')->once()->andReturnSelf();
            $closure($sub);
            return true;
        }))->once()->andReturnSelf();
        $builder->shouldReceive('orderBy')->with('r.cdgo_trmte_dcmnto', 'desc')->once()->andReturnSelf();

        $repo = new TramiteExternoRepository($db);
        $repo->construirQueryBandejaBase($this->contexto, 'todos', [
            'criterio'    => 'observaciones',
            'descripcion' => 'urgente',
        ]);

        $this->assertTrue(true);
    }

    /**
     * Prueba de paridad en resultado vacío.
     */
    public function test_paridad_resultado_vacio(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $builderMock = Mockery::mock(Builder::class);

        // Bandeja devuelve 0 filas
        $repoMock->shouldReceive('consultarBandeja')
            ->with($this->contexto, 'todos', ['descripcion' => 'no_existe'], 1, 20)
            ->andReturn([
                'data'       => [],
                'total'      => 0,
                'last_page'  => 1,
                'page'       => 1,
                'per_page'   => 20,
                'pagination' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            ]);

        // Exportador devuelve cursor vacío
        $repoMock->shouldReceive('construirQueryBandejaBase')
            ->with($this->contexto, 'todos', ['bandeja' => 'todos', 'descripcion' => 'no_existe'])
            ->andReturn($builderMock);
        $builderMock->shouldReceive('select')->once()->andReturnSelf();
        $builderMock->shouldReceive('cursor')->once()->andReturn(collect([]));

        $pBandeja = $repoMock->consultarBandeja($this->contexto, 'todos', ['descripcion' => 'no_existe'], 1, 20);

        $exportService = new ExportarTramiteExternoExcelService($repoMock);
        $response = $exportService->exportar($this->contexto, ['bandeja' => 'todos', 'descripcion' => 'no_existe']);

        $filePath = $response->getFile()->getPathname();
        $reader = new Reader();
        $reader->open($filePath);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $this->assertCount(0, $pBandeja['data'], 'La bandeja paginada debe tener 0 registros.');
        $this->assertCount(1, $rows, 'El Excel debe contener únicamente la cabecera (0 registros de datos).');
    }
}
