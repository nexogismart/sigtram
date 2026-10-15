<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\Services\mod_gm\mod_tram_exte\ConsolidacionExpedienteService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class ConsolidacionExpedienteServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $repository;
    private $gestorAdjuntos;
    private ConsolidacionExpedienteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(TramiteExternoRepositoryInterface::class);
        $this->gestorAdjuntos = Mockery::mock(GestorAdjuntosInterface::class);

        $this->service = new ConsolidacionExpedienteService(
            $this->repository,
            $this->gestorAdjuntos
        );
    }

    public function test_generar_zip_consolidado_crea_archivo_con_manifiesto(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);

        // Crear archivo temporal simulado
        $tmpFile = tempnam(sys_get_temp_dir(), 'sigtram_test_');
        file_put_contents($tmpFile, 'CONTENIDO_PRUEBA_DOCUMENTO_PDF');

        $mov1 = [
            'cdgo_trmte_dcmnto' => 101,
            'nom_archivo'       => 'informe_tecnico.pdf',
        ];

        $this->repository->shouldReceive('obtenerHistoricoMovimientos')
            ->with(55)
            ->once()
            ->andReturn([$mov1]);

        $this->gestorAdjuntos->shouldReceive('resolverRutaSegura')
            ->with('informe_tecnico.pdf')
            ->once()
            ->andReturn($tmpFile);

        $this->repository->shouldReceive('registrarAuditoriaDescarga')
            ->once()
            ->with(101, 'jperez', '127.0.0.1');

        $response = $this->service->generarZipConsolidado($contexto, 55, '127.0.0.1');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $zipPath = $response->getFile()->getPathname();
        $this->assertFileExists($zipPath);

        // Verificar contenido del ZIP
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath));
        $this->assertNotFalse($zip->locateName('MANIFEST.json'));

        $manifestJson = (string) $zip->getFromName('MANIFEST.json');
        $manifest = json_decode($manifestJson, true);

        $this->assertSame(55, $manifest['expediente_id']);
        $this->assertSame('jperez', $manifest['exported_by']);
        $this->assertSame(1, $manifest['files_count']);
        $this->assertCount(1, $manifest['files']);
        $this->assertSame('informe_tecnico.pdf', $manifest['files'][0]['name']);
        $this->assertNotEmpty($manifest['files'][0]['checksum_sha256']);
        $this->assertFalse($zip->locateName('UNIDO-55.pdf'), 'El archivo UNIDO-*.pdf simulado no debe ser incluido');
        $this->assertSame('BLOCKED_USER_DECISION', $manifest['pdf_unificado']['status']);

        $zip->close();

        // Limpieza de archivos temporales de prueba
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
    }

    public function test_generar_zip_lanza_excepcion_si_expediente_no_tiene_movimientos(): void
    {
        $contexto = $this->crearContexto('jperez', 10, 1, 105, 20);

        $this->repository->shouldReceive('obtenerHistoricoMovimientos')
            ->with(999)
            ->once()
            ->andReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no tiene movimientos históricos');

        $this->service->generarZipConsolidado($contexto, 999);
    }
}
