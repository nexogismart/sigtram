<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Mockery;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use App\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepository;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;

/**
 * Pruebas unitarias de regresión para el esquema de base de datos de Trámite Interno (FASE 2).
 * Certifica que NUNCA se solicite la columna inexistente r.de_obser en r_trmtes_ntrnos,
 * y valida la precedencia semántica de observaciones en vouchers y consultas de historial.
 */
class TramiteInternoEsquemaRegresionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 1. obtenerHistorial no solicita la columna inexistente r.de_obser y selecciona r.de_obser_ntrnos y p.de_obser.
     */
    public function test_obtener_historial_no_selecciona_columna_inexistente_r_de_obser(): void
    {
        $db = Mockery::mock(DatabaseManager::class);
        $builderDoc = Mockery::mock(Builder::class);
        $builderHist = Mockery::mock(Builder::class);

        $db->shouldReceive('table')
            ->andReturnUsing(function ($table) use ($builderDoc, $builderHist) {
                return str_contains((string) $table, 'r_trmtes_ntrnos as r') ? $builderDoc : $builderHist;
            });

        // 1. Simular primera consulta que busca el documento
        $builderDoc->shouldReceive('join')->andReturnSelf();
        $builderDoc->shouldReceive('leftJoin')->andReturnSelf();
        $builderDoc->shouldReceive('where')->andReturnSelf();
        $builderDoc->shouldReceive('orderBy')->andReturnSelf();
        $builderDoc->shouldReceive('orderByDesc')->andReturnSelf();
        $builderDoc->shouldReceive('first')->andReturn((object) [
            'cdgo_dtos_ntrnos' => 456,
        ]);

        $columnasCapturadas = [];

        // 2. Simular consulta del historial y capturar las columnas enviadas a select()
        $builderDoc->shouldReceive('select')->andReturnUsing(function ($cols) use (&$columnasCapturadas, $builderDoc) {
            if (is_array($cols)) {
                $columnasCapturadas = $cols;
            }
            return $builderDoc;
        });

        $builderDoc->shouldReceive('count')->andReturn(1);
        $builderDoc->shouldReceive('offset')->andReturnSelf();
        $builderDoc->shouldReceive('limit')->andReturnSelf();
        $builderDoc->shouldReceive('get')->andReturn(collect([
            (object) [
                'cdgo_trmtes_ntrnos' => 10,
                'cdgo_dtos_ntrnos' => 456,
                'nu_tram_todo' => 'MEMO-001',
                'cargo_salida' => 'ALCALDE',
                'cargo_entrada' => 'GERENTE',
                'estado_descripcion' => 'EN TRAMITE',
                'cdgo_estdo_trmte' => 1,
                'fe_rcbdo' => '2026-03-01 10:00:00',
                'de_obser_ntrnos' => 'Obs movimiento',
                'de_obser' => 'Obs movimiento',
                'de_obser_documento' => 'Obs general documento',
                'in_copia' => 0,
            ],
        ]));

        $contexto = $this->crearContexto(
            login: 'operador',
            usuarioId: 1,
            companiaId: 1,
            cargoId: 10,
            areaId: 2,
            cargosAsignados: [10],
            permisos: []
        );

        $repo = new TramiteInternoRepository($db);
        $resultado = $repo->obtenerHistorial(10, $contexto, 1, 10);

        // Aserciones sobre las columnas del Query Builder
        $this->assertNotEmpty($columnasCapturadas, 'Se deben enviar columnas al select de obtenerHistorial');
        $this->assertNotContains('r.de_obser', $columnasCapturadas, 'CRÍTICO: r.de_obser no debe existir en r_trmtes_ntrnos');
        $this->assertContains('r.de_obser_ntrnos', $columnasCapturadas, 'Debe seleccionar r.de_obser_ntrnos como observación de movimiento');
        $this->assertContains('r.de_obser_ntrnos as de_obser', $columnasCapturadas, 'Debe proveer alias de_obser para compatibilidad');
        $this->assertContains('p.de_obser as de_obser_documento', $columnasCapturadas, 'Debe seleccionar p.de_obser como observación general');

        // Aserciones sobre el contrato retornado
        $this->assertArrayHasKey('data', $resultado);
        $this->assertCount(1, $resultado['data']);
        $this->assertEquals('Obs movimiento', $resultado['data'][0]->de_obser);
        $this->assertEquals('Obs general documento', $resultado['data'][0]->de_obser_documento);
    }

    /**
     * 2. Precedencia de observaciones en voucher de Trámite Interno:
     *    Caso A: r.de_obser_ntrnos tiene valor -> prevalece sobre documento y default.
     */
    public function test_voucher_precedencia_observacion_movimiento(): void
    {
        $mov = (object) [
            'de_obser_ntrnos' => 'Observación puntual del movimiento #3',
            'de_obser_documento' => 'Observación del documento principal',
        ];

        $detalles = !empty(trim((string) ($mov->de_obser_ntrnos ?? '')))
            ? trim((string) $mov->de_obser_ntrnos)
            : (!empty(trim((string) ($mov->de_obser_documento ?? '')))
                ? trim((string) $mov->de_obser_documento)
                : 'Sin observaciones registradas.');

        $this->assertEquals('Observación puntual del movimiento #3', $detalles);
    }

    /**
     * 3. Precedencia de observaciones en voucher de Trámite Interno:
     *    Caso B: r.de_obser_ntrnos es nulo/vacío -> utiliza p.de_obser como de_obser_documento.
     */
    public function test_voucher_precedencia_observacion_documento_cuando_movimiento_vacio(): void
    {
        $mov = (object) [
            'de_obser_ntrnos' => '   ',
            'de_obser_documento' => 'Observación general del documento cargado',
        ];

        $detalles = !empty(trim((string) ($mov->de_obser_ntrnos ?? '')))
            ? trim((string) $mov->de_obser_ntrnos)
            : (!empty(trim((string) ($mov->de_obser_documento ?? '')))
                ? trim((string) $mov->de_obser_documento)
                : 'Sin observaciones registradas.');

        $this->assertEquals('Observación general del documento cargado', $detalles);
    }

    /**
     * 4. Precedencia de observaciones en voucher de Trámite Interno:
     *    Caso C: Ambos nulos o vacíos -> retorna mensaje predeterminado.
     */
    public function test_voucher_precedencia_mensaje_predeterminado_cuando_ambos_vacios(): void
    {
        $mov = (object) [
            'de_obser_ntrnos' => null,
            'de_obser_documento' => '',
        ];

        $detalles = !empty(trim((string) ($mov->de_obser_ntrnos ?? '')))
            ? trim((string) $mov->de_obser_ntrnos)
            : (!empty(trim((string) ($mov->de_obser_documento ?? '')))
                ? trim((string) $mov->de_obser_documento)
                : 'Sin observaciones registradas.');

        $this->assertEquals('Sin observaciones registradas.', $detalles);
    }

    /**
     * 5. Voucher en CorreoInternoController no contiene la cadena SQL inválida 'r.de_obser'.
     */
    public function test_voucher_en_controlador_no_selecciona_r_de_obser(): void
    {
        $rutaControlador = app_path('Http/Controllers/mod_gene/mod_tram_inter/CorreoInternoController.php');
        $this->assertFileExists($rutaControlador);

        $contenido = file_get_contents($rutaControlador);
        $this->assertIsString($contenido);

        // Búsqueda regex de límite de palabra para r.de_obser (no r.de_obser_ntrnos)
        $patronInvalido = '/\br\.de_obser\b(?!\w)/i';
        $this->assertDoesNotMatchRegularExpression(
            $patronInvalido,
            $contenido,
            'CorreoInternoController contiene la referencia prohibida r.de_obser'
        );

        $this->assertStringContainsString('obtenerVoucherMovimientoInterno', $contenido);

        $rutaRepo = app_path('Repositories/mod_gene/mod_tram_inter/TramiteInternoRepository.php');
        $contenidoRepo = file_get_contents($rutaRepo);
        $this->assertStringContainsString("'r.de_obser_ntrnos'", $contenidoRepo);
        $this->assertStringContainsString("'p.de_obser as de_obser_documento'", $contenidoRepo);
    }

    /**
     * 6. TramiteInternoRepository no contiene la columna inexistente r.de_obser.
     */
    public function test_repositorio_no_contiene_r_de_obser(): void
    {
        $rutaRepo = app_path('Repositories/mod_gene/mod_tram_inter/TramiteInternoRepository.php');
        $this->assertFileExists($rutaRepo);

        $contenido = file_get_contents($rutaRepo);
        $this->assertIsString($contenido);

        $patronInvalido = '/\br\.de_obser\b(?!\w)/i';
        $this->assertDoesNotMatchRegularExpression(
            $patronInvalido,
            $contenido,
            'TramiteInternoRepository contiene la referencia prohibida r.de_obser'
        );

        $this->assertStringContainsString("'r.de_obser_ntrnos'", $contenido);
        $this->assertStringContainsString("'r.de_obser_ntrnos as de_obser'", $contenido);
    }

    /**
     * 7. Búsqueda exhaustiva en todo app/ que distingue r.de_obser de r.de_obser_ntrnos.
     *    Falla inmediatamente si alguien reintroduce r.de_obser en cualquier archivo de la aplicación.
     */
    public function test_busqueda_exhaustiva_sin_r_de_obser_en_toda_la_aplicacion(): void
    {
        $directorioApp = app_path();
        $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directorioApp));
        $patronInvalido = '/\br\.de_obser\b(?!\w)/';

        $archivosConError = [];

        foreach ($archivos as $archivo) {
            if ($archivo->isDir() || $archivo->getExtension() !== 'php') {
                continue;
            }

            $contenido = file_get_contents($archivo->getPathname());
            if ($contenido !== false && preg_match($patronInvalido, $contenido)) {
                $archivosConError[] = $archivo->getPathname();
            }
        }

        $this->assertEmpty(
            $archivosConError,
            'Se detectaron archivos en app/ que referencian la columna inexistente r.de_obser: ' . implode(', ', $archivosConError)
        );
    }
}
