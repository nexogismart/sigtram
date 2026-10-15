<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\mod_gene\mod_tram_inter\ConsolidadoExpedienteService;
use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use ZipArchive;

class TramiteInternoConsolidadoTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_genera_zip_con_unido_pdf_y_limpieza(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $db = Mockery::mock(DatabaseManager::class);
        $builderDoc = Mockery::mock(Builder::class);
        $builderMov = Mockery::mock(Builder::class);
        $builderAudit = Mockery::mock(Builder::class);

        $movDto = new \App\DTOs\mod_gene\TramiteInternoMovimientoDto(
            id: 10,
            movimientoId: 1,
            numero: 'MEMO-123',
            companiaId: 1,
            cargoSalidaId: 10,
            cargoEntradaId: 10,
            areaSalidaId: 5,
            areaEntradaId: 5,
            estadoId: 2,
            inEsta: '1',
            inCopia: '1'
        );
        $repo->shouldReceive('obtenerExpedienteAutorizadoParaConsolidado')
            ->andReturn($movDto);

        // Mock documento vía repositorio
        $repo->shouldReceive('obtenerDocumentoParaConsolidado')->with(10, 1)->andReturn((object) [
            'cdgo_dtos_ntrnos' => 10,
            'nu_tram_todo' => 'MEMO-123',
            'de_urls_adjnto' => 'adj1.pdf',
        ]);

        // Mock movimientos vía repositorio
        $repo->shouldReceive('obtenerMovimientosParaConsolidado')->with(10)->andReturn([
            (object) ['cdgo_trmtes_ntrnos' => 1, 'de_urls_agrdo' => 'adj2.docx'],
        ]);

        // Archivos temporales simulados dentro de storage_path()
        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $f1 = tempnam($tempDir, 'sigtram_adj1_');
        $f2 = tempnam($tempDir, 'sigtram_adj2_');
        file_put_contents($f1, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        file_put_contents($f2, 'CONTENIDO_DOCX');

        $gestor->shouldReceive('resolverRutaSegura')->with('adj1.pdf')->andReturn($f1);
        $gestor->shouldReceive('resolverRutaSegura')->with('adj2.docx')->andReturn($f2);

        $db->shouldReceive('transaction')->once()->andReturnUsing(function ($cb) {
            return $cb();
        });

        // Mock auditoría vía repositorio
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->atLeast()->times(1)->andReturnNull();

        $service = new ConsolidadoExpedienteService($repo, $gestor, $db);
        $contexto = $this->crearContexto('usr', 1, 1, 10, 5, [10], [], loginId: 99);

        $res = $service->generarConsolidado(10, $contexto, '127.0.0.1');

        $this->assertFileExists($res['zip_path']);
        $this->assertStringContainsString('MEMO-123', $res['zip_name']);

        // Verificar contenido del ZIP generado
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($res['zip_path']) === true);
        $this->assertNotFalse($zip->locateName('UNIDO-MEMO-123.pdf'));
        $zip->close();

        // Limpieza garantizada
        @unlink($res['zip_path']);
        @unlink($f1);
        @unlink($f2);
    }
}