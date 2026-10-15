<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Contracts\Services\mod_gm\ExcelWriterFactoryInterface;
use App\Contracts\Services\mod_gm\ExcelWriterInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Enums\mod_gm\BandejaTramiteExterno;
use App\Services\mod_gm\mod_tram_exte\ExportarTramiteExternoExcelService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Suite de pruebas unitarias para la exportación de expedientes a Excel (XLSX).
 * Valida la sanitización de fórmulas, ciclo de vida de recursos temporales,
 * manejo de fallos determinista y generación de archivos XLSX reales con OpenSpout.
 */
class ExportarTramiteExternoExcelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Requisito: Neutralización estricta de Formula Injection y preservación de texto y caracteres españoles.
     * Cubre =, +, -, @, \t (tabulación) y \r (retorno de carro).
     */
    public function test_sanitizar_formula_neutraliza_inyeccion_y_preserva_texto_seguro(): void
    {
        // Neutralización de fórmulas maliciosas con =, +, -, @
        $this->assertSame("'=SUM(A1:B10)", ExportarTramiteExternoExcelService::sanitizarFormula('=SUM(A1:B10)'));
        $this->assertSame("'+123456789", ExportarTramiteExternoExcelService::sanitizarFormula('+123456789'));
        $this->assertSame("'-cmd|'/c calc'!A1", ExportarTramiteExternoExcelService::sanitizarFormula("-cmd|'/c calc'!A1"));
        $this->assertSame("'@SUM(1,2)", ExportarTramiteExternoExcelService::sanitizarFormula('@SUM(1,2)'));

        // Con tabulación y retorno de carro
        $this->assertSame("'\t=cmd", ExportarTramiteExternoExcelService::sanitizarFormula("\t=cmd"));
        $this->assertSame("'\r+cmd", ExportarTramiteExternoExcelService::sanitizarFormula("\r+cmd"));
        $this->assertSame("'\tsafe", ExportarTramiteExternoExcelService::sanitizarFormula("\tsafe"));
        $this->assertSame("'\rsafe", ExportarTramiteExternoExcelService::sanitizarFormula("\rsafe"));

        // Con espacios iniciales (ej. "  =1+1")
        $this->assertSame("'  =1+1", ExportarTramiteExternoExcelService::sanitizarFormula('  =1+1'));
        $this->assertSame("'   +cmd", ExportarTramiteExternoExcelService::sanitizarFormula('   +cmd'));
        $this->assertSame("' -calc", ExportarTramiteExternoExcelService::sanitizarFormula(' -calc'));
        $this->assertSame("'   @exec", ExportarTramiteExternoExcelService::sanitizarFormula('   @exec'));
        $this->assertSame("'  \t=cmd", ExportarTramiteExternoExcelService::sanitizarFormula("  \t=cmd"));
        $this->assertSame("'  \r+cmd", ExportarTramiteExternoExcelService::sanitizarFormula("  \r+cmd"));

        // Valores normales con ceros iniciales (DNI/RUC y Expedientes)
        $this->assertSame('08451234', ExportarTramiteExternoExcelService::sanitizarFormula('08451234'));
        $this->assertSame('000123-2026', ExportarTramiteExternoExcelService::sanitizarFormula('000123-2026'));

        // Caracteres españoles con tildes y eñes
        $this->assertSame('Trámite de Habilitación Urbana Nº 15', ExportarTramiteExternoExcelService::sanitizarFormula('Trámite de Habilitación Urbana Nº 15'));
        $this->assertSame('Administración y Finanzas', ExportarTramiteExternoExcelService::sanitizarFormula('Administración y Finanzas'));

        // Nulos y vacíos
        $this->assertSame('', ExportarTramiteExternoExcelService::sanitizarFormula(null));
        $this->assertSame('', ExportarTramiteExternoExcelService::sanitizarFormula(''));
    }

    /**
     * Requisito: Construcción de la consulta con aislamiento por compañía,
     * consulta canónica unificada compartida con la bandeja.
     */
    public function test_construir_query_aplica_aislamiento_de_compania_y_filtros_validados(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $mainBuilder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')
            ->with('sistema.r_trmtes_dcmntos as r')
            ->once()
            ->andReturn($mainBuilder);

        $mainBuilder->shouldReceive('join')->with('sistema.p_dto_trmtes as p', 'p.cdgo_dto_trmte', '=', 'r.cdgo_dto_trmte')->once()->andReturnSelf();
        $mainBuilder->shouldReceive('leftJoin')->times(6)->andReturnSelf();

        // Aislamiento estricto por compañía activa
        $mainBuilder->shouldReceive('where')
            ->with('p.cdgo_cmpnia', 5)
            ->once()
            ->andReturnSelf();

        // Filtro de bandeja entrada
        $mainBuilder->shouldReceive('where')->with('r.cdgo_crgo_en', 10)->once()->andReturnSelf();
        $mainBuilder->shouldReceive('where')->with('r.cdgo_crgo_sa', '!=', 10)->once()->andReturnSelf();
        $mainBuilder->shouldReceive('where')->with('r.in_esta', '1')->once()->andReturnSelf();
        $mainBuilder->shouldReceive('whereNotIn')->with('r.cdgo_estdo_trmte', [5, 12, 29, 3, 26, 27, 28])->once()->andReturnSelf();

        // Filtro de descripción
        $mainBuilder->shouldReceive('where')->with('p.nu_expe_todo', 'like', '%2026-001%')->once()->andReturnSelf();

        // Orden canónico descendente por cdgo_trmte_dcmnto y selección de columnas
        $mainBuilder->shouldReceive('orderBy')->with('r.cdgo_trmte_dcmnto', 'desc')->once()->andReturnSelf();
        $mainBuilder->shouldReceive('select')->once()->andReturnSelf();

        $service = new ExportarTramiteExternoExcelService($db);
        $contexto = $this->crearContexto('jperez', 10, 5, 10, 20, [4], 15844);

        $filtros = [
            'bandeja'     => BandejaTramiteExterno::ENTRADA->value,
            'criterio'    => 'expe_todo',
            'descripcion' => '2026-001',
        ];

        $query = $service->construirQuery($contexto, $filtros);
        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Requisito: Generación real de archivo XLSX válido con OpenSpout,
     * formato binario real (no HTML), cabeceras esperadas, ceros iniciales y neutralización.
     */
    public function test_exportar_genera_archivo_xlsx_real_valido_con_todas_las_columnas(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $mainBuilder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with('sistema.r_trmtes_dcmntos as r')->andReturn($mainBuilder);
        $mainBuilder->shouldReceive('join')->andReturnSelf();
        $mainBuilder->shouldReceive('leftJoin')->andReturnSelf();
        $mainBuilder->shouldReceive('where')->andReturnSelf();
        $mainBuilder->shouldReceive('orderBy')->andReturnSelf();
        $mainBuilder->shouldReceive('select')->andReturnSelf();

        // Simulación del cursor con datos reales conteniendo ceros iniciales, tildes y caracteres de fórmula
        $filasFalsas = collect([
            (object) [
                'cdgo_trmte_dcmnto'             => 5001,
                'cdgo_dto_trmte'                => 1001,
                'nu_expe_todo'                  => '0001001-2026', // Ceros iniciales
                'fe_ingr_trmte'                 => '2026-03-15 09:30:00',
                'observacion_tramite'           => 'Solicitud de habilitación urbana con tilde',
                'numero_documento'              => 'EXP-001',
                'nu_foli_trmte'                 => 45,
                'de_docmnto_trmte'              => 'SOLICITUD',
                'in_esta_ultimo_movimiento'     => '1',
                'cdgo_estdo_trmte'              => 1,
                'dc_estdo_trmte'                => 'PENDIENTE',
                'de_estdo_trmte'                => 'PENDIENTE',
                'area_actual'                   => 'Gerencia de Desarrollo Urbano',
                'cargo_actual'                  => 'SUBGERENTE DE OBRAS',
                'fe_rcbdo_trmte'                => '2026-03-16 11:20:00',
                'observacion_ultimo_movimiento' => '=CMD|' . "'/c calc'!A1", // Intento de formula injection
                'persona_razon_social'          => 'Peña & Muñoz S.A.C.', // Caracteres especiales
                'dni_ruc'                       => '08451234', // Cero inicial que debe preservarse
                'nu_tele'                       => '01987654321', // Cero inicial
                'de_mail'                       => 'contacto@empresa.pe',
                'de_dire'                       => 'Av. San Martín Nº 450',
            ],
            (object) [
                'cdgo_trmte_dcmnto'             => 5002,
                'cdgo_dto_trmte'                => 1002,
                'nu_expe_todo'                  => '0001002-2026',
                'fe_ingr_trmte'                 => '2026-03-18 14:00:00',
                'observacion_tramite'           => '+555 fórmula de suma',
                'numero_documento'              => 'OFI-002',
                'nu_foli_trmte'                 => 12,
                'de_docmnto_trmte'              => 'OFICIO',
                'in_esta_ultimo_movimiento'     => '0',
                'cdgo_estdo_trmte'              => 5,
                'dc_estdo_trmte'                => 'FINALIZADO',
                'de_estdo_trmte'                => 'FINALIZADO',
                'area_actual'                   => 'Secretaría General',
                'cargo_actual'                  => 'ESPECIALISTA LEGAL',
                'fe_rcbdo_trmte'                => null,
                'observacion_ultimo_movimiento' => 'Trámite concluido.',
                'persona_razon_social'          => 'González López, Raúl',
                'dni_ruc'                       => '09876543210',
                'nu_tele'                       => '987654321',
                'de_mail'                       => 'raul@gmail.com',
                'de_dire'                       => 'Jr. Los Álamos 123',
            ],
        ]);

        $mainBuilder->shouldReceive('cursor')->once()->andReturn($filasFalsas);

        $service = new ExportarTramiteExternoExcelService($db);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $response = $service->exportar($contexto, ['bandeja' => 'todos']);

        // 1. Tipo MIME correcto (XLSX, NO HTML ni XLS antiguo)
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );

        // 2. Content-Disposition con archivo adjunto
        $contentDisposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment;', $contentDisposition);
        $this->assertStringContainsString('reporte_expedientes_', $contentDisposition);
        $this->assertMatchesRegularExpression('/\.xlsx"?$/', $contentDisposition);

        // 3 y 4. Archivo generado real y no vacío
        $filePath = $response->getFile()->getPathname();
        $this->assertFileExists($filePath);
        try {
            $fileSize = filesize($filePath);
            $this->assertGreaterThan(500, $fileSize, 'El archivo XLSX debe ser un archivo binario válido con tamaño > 500 bytes.');

            // 5. Validar que es un libro XLSX real que abre correctamente con OpenSpout
            $reader = new Reader();
            $reader->open($filePath);

            $sheetRows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $sheetRows[] = $row->toArray();
                }
            }
            $reader->close();

            // Verificar que existen filas (cabecera + 2 datos)
            $this->assertCount(3, $sheetRows);

            // 6. Verificar columnas de la cabecera
            $cabecera = $sheetRows[0];
            $this->assertSame('Nº', $cabecera[0]);
            $this->assertSame('ID Trámite', $cabecera[1]);
            $this->assertSame('Nº Expediente', $cabecera[2]);
            $this->assertSame('Tipo Documento', $cabecera[3]);
            $this->assertSame('Nº Folios', $cabecera[4]);
            $this->assertSame('Fecha Ingreso', $cabecera[5]);
            $this->assertSame('DNI / RUC', $cabecera[6]);
            $this->assertSame('Administrado / Razón Social', $cabecera[7]);
            $this->assertSame('Teléfono', $cabecera[8]);
            $this->assertSame('Correo Electrónico', $cabecera[9]);
            $this->assertSame('Dirección', $cabecera[10]);
            $this->assertSame('Observación del Trámite', $cabecera[11]);
            $this->assertSame('Área Actual', $cabecera[12]);
            $this->assertSame('Cargo Actual', $cabecera[13]);
            $this->assertSame('Estado Actual', $cabecera[14]);
            $this->assertSame('Condición', $cabecera[15]);
            $this->assertSame('Fecha Último Movimiento', $cabecera[16]);
            $this->assertSame('Observación Último Movimiento', $cabecera[17]);

            // Preservación de ceros iniciales en DNI y Expediente
            $fila1 = $sheetRows[1];
            $this->assertSame('0001001-2026', $fila1[2]);
            $this->assertSame('08451234', $fila1[6]);
            $this->assertSame('01987654321', $fila1[8]);

            // Formato de fecha
            $this->assertSame('15/03/2026 09:30', $fila1[5]);
            $this->assertSame('16/03/2026 11:20', $fila1[16]);

            // Preservación de tildes y eñes
            $this->assertSame('Peña & Muñoz S.A.C.', $fila1[7]);
            $this->assertSame('Av. San Martín Nº 450', $fila1[10]);

            // Neutralización de Formula Injection
            $this->assertStringStartsWith("'", $fila1[17], 'La fórmula maliciosa =CMD debe haber sido neutralizada con comilla.');

            $fila2 = $sheetRows[2];
            $this->assertStringStartsWith("'", $fila2[11], 'El texto que comienza con + debe haber sido neutralizado con comilla.');
            $this->assertSame('DESACTIVADO/INACTIVO', $fila2[15]);
        } finally {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    /**
     * Requisito: Exportación con resultado vacío produce archivo XLSX válido con solo cabeceras.
     */
    public function test_exportar_con_resultado_vacio_genera_xlsx_valido_con_cabeceras(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $mainBuilder = Mockery::mock(Builder::class);

        $db->shouldReceive('table')->with('sistema.r_trmtes_dcmntos as r')->andReturn($mainBuilder);
        $mainBuilder->shouldReceive('join')->andReturnSelf();
        $mainBuilder->shouldReceive('leftJoin')->andReturnSelf();
        $mainBuilder->shouldReceive('where')->andReturnSelf();
        $mainBuilder->shouldReceive('orderBy')->andReturnSelf();
        $mainBuilder->shouldReceive('select')->andReturnSelf();
        $mainBuilder->shouldReceive('cursor')->once()->andReturn(collect([]));

        $service = new ExportarTramiteExternoExcelService($db);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $response = $service->exportar($contexto, ['bandeja' => 'todos']);

        $filePath = $response->getFile()->getPathname();
        $this->assertFileExists($filePath);

        try {
            $reader = new Reader();
            $reader->open($filePath);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
            }
            $reader->close();

            $this->assertCount(1, $rows, 'Debe contener exactamente la fila de cabecera.');
            $this->assertSame('Nº', $rows[0][0]);
        } finally {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    /**
     * Requisito AG-EXCEL-05: Error al abrir el writer.
     * El archivo temporal debe ser eliminado y el writer no debe intentar cerrarse si nunca abrió.
     */
    public function test_exportar_falla_al_abrir_writer_y_elimina_archivo_temporal(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
                throw new RuntimeException('Error simulado al abrir el writer.');
            });
        $writerMock->shouldNotReceive('close');

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error simulado al abrir el writer.');

        try {
            $service->exportar($contexto, ['bandeja' => 'todos']);
        } finally {
            $this->assertNotNull($capturedPath);
            $this->assertFileDoesNotExist($capturedPath, 'El archivo temporal debe eliminarse tras fallo en openToFile.');
        }
    }

    /**
     * Requisito AG-EXCEL-05: Error al escribir encabezados.
     * El writer debe cerrarse y el archivo temporal debe eliminarse.
     */
    public function test_exportar_falla_al_escribir_cabeceras_cierra_writer_y_elimina_archivo(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
            });

        $writerMock->shouldReceive('addRow')
            ->once()
            ->andThrow(new RuntimeException('Error simulado al escribir cabeceras.'));

        $writerMock->shouldReceive('close')->once();

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error simulado al escribir cabeceras.');

        try {
            $service->exportar($contexto, ['bandeja' => 'todos']);
        } finally {
            $this->assertNotNull($capturedPath);
            $this->assertFileDoesNotExist($capturedPath, 'El archivo temporal debe eliminarse tras fallo en cabeceras.');
        }
    }

    /**
     * Requisito AG-EXCEL-05: Error al construir la consulta.
     * El writer debe cerrarse y el archivo temporal debe eliminarse.
     */
    public function test_exportar_falla_al_construir_consulta_cierra_writer_y_elimina_archivo(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
            });

        $writerMock->shouldReceive('addRow')->once(); // Headers ok

        $repoMock->shouldReceive('construirQueryBandejaBase')
            ->once()
            ->andThrow(new RuntimeException('Error de base de datos al construir query.'));

        $writerMock->shouldReceive('close')->once();

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error de base de datos al construir query.');

        try {
            $service->exportar($contexto, ['bandeja' => 'todos']);
        } finally {
            $this->assertNotNull($capturedPath);
            $this->assertFileDoesNotExist($capturedPath, 'El archivo temporal debe eliminarse tras fallo al construir consulta.');
        }
    }

    /**
     * Requisito AG-EXCEL-05: Error durante la primera fila.
     * El writer debe cerrarse y el archivo temporal debe eliminarse.
     */
    public function test_exportar_falla_durante_primera_fila_cierra_writer_y_elimina_archivo(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $builderMock = Mockery::mock(Builder::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
            });

        $writerMock->shouldReceive('addRow')->once(); // Headers ok

        $repoMock->shouldReceive('construirQueryBandejaBase')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('select')->once()->andReturnSelf();
        $builderMock->shouldReceive('cursor')->once()->andReturn(collect([
            (object) ['cdgo_dto_trmte' => 1, 'nu_expe_todo' => 'EXP-1'],
        ]));

        $writerMock->shouldReceive('addRow')
            ->once()
            ->andThrow(new RuntimeException('Fallo de I/O en primera fila.'));

        $writerMock->shouldReceive('close')->once();

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fallo de I/O en primera fila.');

        try {
            $service->exportar($contexto, ['bandeja' => 'todos']);
        } finally {
            $this->assertNotNull($capturedPath);
            $this->assertFileDoesNotExist($capturedPath, 'El archivo temporal debe eliminarse tras fallo en primera fila.');
        }
    }

    /**
     * Requisito AG-EXCEL-05: Error después de varias filas.
     * El writer debe cerrarse y el archivo temporal debe eliminarse.
     */
    public function test_exportar_falla_despues_de_varias_filas_cierra_writer_y_elimina_archivo(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $builderMock = Mockery::mock(Builder::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
            });

        $writerMock->shouldReceive('addRow')->once(); // Headers ok

        $repoMock->shouldReceive('construirQueryBandejaBase')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('select')->once()->andReturnSelf();
        $builderMock->shouldReceive('cursor')->once()->andReturn(collect([
            (object) ['cdgo_dto_trmte' => 1, 'nu_expe_todo' => 'EXP-1'],
            (object) ['cdgo_dto_trmte' => 2, 'nu_expe_todo' => 'EXP-2'],
            (object) ['cdgo_dto_trmte' => 3, 'nu_expe_todo' => 'EXP-3'],
        ]));

        $callCount = 0;
        $writerMock->shouldReceive('addRow')
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount >= 3) {
                    throw new RuntimeException('Disco lleno después de 2 filas.');
                }
            });

        $writerMock->shouldReceive('close')->once();

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Disco lleno después de 2 filas.');

        try {
            $service->exportar($contexto, ['bandeja' => 'todos']);
        } finally {
            $this->assertNotNull($capturedPath);
            $this->assertFileDoesNotExist($capturedPath, 'El archivo temporal debe eliminarse tras fallo después de varias filas.');
        }
    }

    /**
     * Requisito AG-EXCEL-05: Error al cerrar el writer.
     * La excepción se propaga y el archivo temporal es eliminado.
     */
    public function test_exportar_falla_al_cerrar_writer_y_elimina_archivo(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $builderMock = Mockery::mock(Builder::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
            });

        $writerMock->shouldReceive('addRow')->once(); // Headers

        $repoMock->shouldReceive('construirQueryBandejaBase')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('select')->once()->andReturnSelf();
        $builderMock->shouldReceive('cursor')->once()->andReturn(collect([]));

        $writerMock->shouldReceive('close')
            ->once()
            ->andThrow(new RuntimeException('Error de compresión ZIP al cerrar writer.'));

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error de compresión ZIP al cerrar writer.');

        try {
            $service->exportar($contexto, ['bandeja' => 'todos']);
        } finally {
            $this->assertNotNull($capturedPath);
            $this->assertFileDoesNotExist($capturedPath, 'El archivo temporal debe eliminarse tras fallo en close.');
        }
    }

    /**
     * Requisito AG-EXCEL-05: En exportación exitosa, el archivo permanece disponible
     * y la respuesta delega deleteFileAfterSend(true).
     */
    public function test_exportar_exitoso_configura_delete_file_after_send(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $builderMock = Mockery::mock(Builder::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
                file_put_contents($path, 'dummy xlsx content');
            });

        $writerMock->shouldReceive('addRow')->once(); // Headers
        $repoMock->shouldReceive('construirQueryBandejaBase')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('select')->once()->andReturnSelf();
        $builderMock->shouldReceive('cursor')->once()->andReturn(collect([]));
        $writerMock->shouldReceive('close')->once();

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $response = $service->exportar($contexto, ['bandeja' => 'todos']);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertNotNull($capturedPath);
        $this->assertFileExists($capturedPath, 'El archivo debe permanecer para que Laravel lo transmita.');

        // Limpieza de prueba
        if (file_exists($capturedPath)) {
            @unlink($capturedPath);
        }
    }

    /**
     * Requisito AG-FINAL-04 (Punto 3): Fallo al crear u obtener el writer.
     * Si la fábrica falla al instanciar el writer, el temporal creado por tempnam() debe eliminarse
     * y no debe intentarse ninguna operación sobre el writer.
     */
    public function test_exportar_falla_al_crear_u_obtener_writer_y_elimina_temporal(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $beforeFiles = array_merge(
            glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sig*.tmp') ?: [],
            glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigtram_ext_exp_*') ?: []
        );

        $capturedTempFile = null;
        $factoryMock->shouldReceive('create')
            ->once()
            ->andReturnUsing(function () use (&$capturedTempFile, $beforeFiles) {
                $currentFiles = array_merge(
                    glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sig*.tmp') ?: [],
                    glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigtram_ext_exp_*') ?: []
                );
                $diff = array_values(array_diff($currentFiles, $beforeFiles));
                $capturedTempFile = $diff[0] ?? null;

                throw new RuntimeException('Error simulado al instanciar el writer.');
            });

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error simulado al instanciar el writer.');

        try {
            $service->exportar($contexto, ['bandeja' => 'todos']);
        } finally {
            $this->assertNotNull($capturedTempFile, 'Debe haberse detectado el archivo temporal creado por tempnam.');
            $this->assertFileDoesNotExist($capturedTempFile, 'El temporal debe eliminarse tras fallo de la fábrica.');
        }
    }

    /**
     * Requisito AG-FINAL-04 (Punto 7): Fallo al obtener o recorrer cursor().
     * El writer debe cerrarse ordenadamente y el archivo temporal debe eliminarse.
     */
    public function test_exportar_falla_al_obtener_o_recorrer_cursor_cierra_writer_y_elimina_archivo(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $builderMock = Mockery::mock(Builder::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
            });

        $writerMock->shouldReceive('addRow')->once(); // Headers ok

        $repoMock->shouldReceive('construirQueryBandejaBase')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('select')->once()->andReturnSelf();
        $builderMock->shouldReceive('cursor')->once()->andThrow(new RuntimeException('Error de base de datos al obtener cursor.'));

        $writerMock->shouldReceive('close')->once();

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error de base de datos al obtener cursor.');

        try {
            $service->exportar($contexto, ['bandeja' => 'todos']);
        } finally {
            $this->assertNotNull($capturedPath);
            $this->assertFileDoesNotExist($capturedPath, 'El archivo temporal debe eliminarse tras fallo en cursor.');
        }
    }

    /**
     * Requisito AG-FINAL-04 (Punto 11): Fallo al construir BinaryFileResponse.
     * Si la construcción de la respuesta falla tras cerrar el writer, el temporal debe eliminarse.
     */
    public function test_exportar_falla_al_construir_binary_file_response_elimina_temporal(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $builderMock = Mockery::mock(Builder::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
                file_put_contents($path, 'dummy content');
            });

        $writerMock->shouldReceive('addRow')->once(); // Headers ok

        $repoMock->shouldReceive('construirQueryBandejaBase')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('select')->once()->andReturnSelf();
        $builderMock->shouldReceive('cursor')->once()->andReturn(collect([]));

        $writerMock->shouldReceive('close')->once();

        // Simular fallo durante la creación de BinaryFileResponse
        $service = new class ($repoMock, $factoryMock) extends ExportarTramiteExternoExcelService {
            protected function crearBinaryFileResponse(string $tempFile, string $nombreDescarga): BinaryFileResponse
            {
                throw new RuntimeException('Error simulado al instanciar BinaryFileResponse.');
            }
        };

        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error simulado al instanciar BinaryFileResponse.');

        try {
            $service->exportar($contexto, ['bandeja' => 'todos']);
        } finally {
            $this->assertNotNull($capturedPath);
            $this->assertFileDoesNotExist($capturedPath, 'El archivo temporal debe eliminarse tras fallo en BinaryFileResponse.');
        }
    }

    /**
     * Requisito AG-FINAL-04 (Punto 12): Eliminación de rename().
     * Verifica conductualmente que se administra una sola ruta temporal,
     * no se genera ninguna segunda ruta física (.xlsx en disco) y la respuesta
     * de descarga apunta directamente al único archivo temporal creado.
     */
    public function test_exportar_administra_una_sola_ruta_temporal_sin_segunda_ruta_ni_rename(): void
    {
        $repoMock = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $builderMock = Mockery::mock(Builder::class);
        $writerMock = Mockery::mock(ExcelWriterInterface::class);
        $factoryMock = Mockery::mock(ExcelWriterFactoryInterface::class);

        $capturedPath = null;
        $factoryMock->shouldReceive('create')->once()->andReturn($writerMock);
        $writerMock->shouldReceive('openToFile')
            ->once()
            ->andReturnUsing(function (string $path) use (&$capturedPath) {
                $capturedPath = $path;
                file_put_contents($path, 'dummy content single path');
            });

        $writerMock->shouldReceive('addRow')->once(); // Headers
        $repoMock->shouldReceive('construirQueryBandejaBase')->once()->andReturn($builderMock);
        $builderMock->shouldReceive('select')->once()->andReturnSelf();
        $builderMock->shouldReceive('cursor')->once()->andReturn(collect([]));
        $writerMock->shouldReceive('close')->once();

        $service = new ExportarTramiteExternoExcelService($repoMock, $factoryMock);
        $contexto = $this->crearContexto('jperez', 10, 3, 10, 20, [4], 15844);

        $response = $service->exportar($contexto, ['bandeja' => 'todos']);

        try {
            $this->assertInstanceOf(BinaryFileResponse::class, $response);
            $this->assertNotNull($capturedPath);

            // 1. La respuesta apunta exactamente a la única ruta administrada por tempnam()
            $this->assertSame($capturedPath, $response->getFile()->getPathname());

            // 2. No existe ninguna segunda ruta con sufijo .xlsx en disco
            $this->assertFileDoesNotExist($capturedPath . '.xlsx');

            // 3. El nombre de descarga entregado al usuario sí termina en .xlsx
            $contentDisposition = (string) $response->headers->get('Content-Disposition');
            $this->assertMatchesRegularExpression('/\.xlsx"?$/', $contentDisposition);
        } finally {
            if ($capturedPath !== null && file_exists($capturedPath)) {
                @unlink($capturedPath);
            }
        }
    }
}
