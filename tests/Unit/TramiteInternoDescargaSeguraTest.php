<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\mod_gene\mod_tram_inter\DescargaSeguraTramiteInternoService;
use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use App\Contracts\Services\GestorAdjuntosInterface;
use App\Policies\mod_gene\TramiteInternoPolicy;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use App\DTOs\mod_gene\TramiteInternoMovimientoDto;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Mockery;
use InvalidArgumentException;
use RuntimeException;

class TramiteInternoDescargaSeguraTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rechaza_path_traversal(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $service = new DescargaSeguraTramiteInternoService($repo, $gestor, $policy, $db);
        $contexto = $this->crearContexto('usr', 1, 1, 10, 5, [10], []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path Traversal detectado');

        $service->procesarDescarga(1, '../../etc/passwd', $contexto, '127.0.0.1');
    }

    public function test_rechaza_idor_si_movimiento_no_existe_o_es_ajeno(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $repo->shouldReceive('obtenerMovimientoPorId')->with(999, 1)->andReturn(null);

        $service = new DescargaSeguraTramiteInternoService($repo, $gestor, $policy, $db);
        $contexto = $this->crearContexto('usr', 1, 1, 10, 5, [10], []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El recurso solicitado no fue encontrado');

        $service->procesarDescarga(999, 'adjunto.pdf', $contexto, '127.0.0.1');
    }

    public function test_rechaza_si_policy_deniega_descarga(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);

        $movimiento = new TramiteInternoMovimientoDto(
            id: 5,
            movimientoId: 10,
            numero: 'DOC-1',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 30, // Ajeno al contexto
            areaSalidaId: 2,
            areaEntradaId: 3,
            estadoId: 1,
            inEsta: '1',
            inCopia: '0'
        );

        $repo->shouldReceive('obtenerMovimientoPorId')->with(10, 1)->andReturn($movimiento);
        $policy->shouldReceive('descargar')->andReturn(false);

        $service = new DescargaSeguraTramiteInternoService($repo, $gestor, $policy, $db);
        $contexto = $this->crearContexto('usr', 1, 1, 99, 9, [99], []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tiene permisos para descargar adjuntos');

        $service->procesarDescarga(10, 'adjunto.pdf', $contexto, '127.0.0.1');
    }

    public function test_descarga_exitosa_registra_auditoria_en_p_sgmto_dscrgas(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $movimiento = new TramiteInternoMovimientoDto(
            id: 5,
            movimientoId: 10,
            numero: 'DOC-1',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 30,
            areaSalidaId: 2,
            areaEntradaId: 3,
            estadoId: 2,
            inEsta: '1',
            inCopia: '0'
        );

        $repo->shouldReceive('obtenerMovimientoPorId')->with(10, 1)->andReturn($movimiento);
        $policy->shouldReceive('descargar')->andReturn(true);

        // Usar un archivo temporal real existente dentro de storage_path() para probar resolución realpath
        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $tempFile = tempnam($tempDir, 'sigtram_test_');
        $fileName = basename($tempFile);

        $gestor->shouldReceive('resolverRutaSegura')->with($fileName)->andReturn($tempFile);

        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->once();

        $service = new DescargaSeguraTramiteInternoService($repo, $gestor, $policy, $db);
        $contexto = $this->crearContexto('usr_test', 1, 1, 30, 3, [30], [], loginId: 99);

        $resultado = $service->procesarDescarga(10, $fileName, $contexto, '192.168.1.50');

        $this->assertEquals(realpath($tempFile), $resultado['ruta_fisica']);
        $this->assertEquals($fileName, $resultado['nombre_descarga']);

        @unlink($tempFile);
    }

    public function test_fallo_de_auditoria_aborta_descarga_fail_closed(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $gestor = Mockery::mock(GestorAdjuntosInterface::class);
        $policy = Mockery::mock(TramiteInternoPolicy::class);
        $db = Mockery::mock(DatabaseManager::class);
        $builder = Mockery::mock(Builder::class);

        $movimiento = new TramiteInternoMovimientoDto(
            id: 5,
            movimientoId: 10,
            numero: 'DOC-1',
            companiaId: 1,
            cargoSalidaId: 20,
            cargoEntradaId: 30,
            areaSalidaId: 2,
            areaEntradaId: 3,
            estadoId: 2,
            inEsta: '1',
            inCopia: '0'
        );

        $repo->shouldReceive('obtenerMovimientoPorId')->with(10, 1)->andReturn($movimiento);
        $policy->shouldReceive('descargar')->andReturn(true);

        $tempDir = storage_path('app/temp');
        @mkdir($tempDir, 0777, true);
        $tempFile = tempnam($tempDir, 'sigtram_test_');
        $fileName = basename($tempFile);

        $gestor->shouldReceive('resolverRutaSegura')->with($fileName)->andReturn($tempFile);

        // Simular falla catastrófica en BD durante la auditoría
        $repo->shouldReceive('registrarAuditoriaDescargaInterna')->once()->andThrow(new \Exception('DB Connection drop'));

        $service = new DescargaSeguraTramiteInternoService($repo, $gestor, $policy, $db);
        $contexto = $this->crearContexto('usr_test', 1, 1, 30, 3, [30], [], loginId: 99);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fallo en el registro de auditoría legal de descarga: no es posible proceder.');

        try {
            $service->procesarDescarga(10, $fileName, $contexto, '192.168.1.50');
        } finally {
            @unlink($tempFile);
        }
    }
}