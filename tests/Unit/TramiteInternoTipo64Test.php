<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\mod_gene\mod_tram_inter\ValidadorPagoProveedoresTipo64Service;
use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use Mockery;

class TramiteInternoTipo64Test extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_anexos_completos_permite_recepcion(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $repo->shouldReceive('obtenerTramitesTipo64')
            ->with([50])
            ->andReturn([
                (object) ['cdgo_dtos_ntrnos' => 50, 'nu_tram_todo' => 'EXP-64-001']
            ]);

        $repo->shouldReceive('obtenerPagosProveedoresIncompletos')
            ->with([50])
            ->andReturn([]);

        $validador = new ValidadorPagoProveedoresTipo64Service($repo);
        $resultado = $validador->validarLoteParaRecepcion([50]);

        $this->assertTrue($resultado['valido']);
    }

    public function test_anexos_incompletos_bloquea_recepcion(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $repo->shouldReceive('obtenerTramitesTipo64')
            ->with([50])
            ->andReturn([
                (object) ['cdgo_dtos_ntrnos' => 50, 'nu_tram_todo' => 'EXP-64-001']
            ]);

        $repo->shouldReceive('obtenerPagosProveedoresIncompletos')
            ->with([50])
            ->andReturn([50]);

        $validador = new ValidadorPagoProveedoresTipo64Service($repo);
        $resultado = $validador->validarLoteParaRecepcion([50]);

        $this->assertFalse($resultado['valido']);
        $this->assertStringContainsString('anexos obligatorios incompletos', $resultado['mensaje']);
        $this->assertContains(50, $resultado['incompletos']);
    }

    public function test_servicio_logistica_no_disponible_aplica_bloqueo_fail_closed(): void
    {
        $repo = Mockery::mock(TramiteInternoRepositoryInterface::class);
        $repo->shouldReceive('obtenerTramitesTipo64')
            ->with([50])
            ->andReturn([
                (object) ['cdgo_dtos_ntrnos' => 50, 'nu_tram_todo' => 'EXP-64-001']
            ]);

        $repo->shouldReceive('obtenerPagosProveedoresIncompletos')
            ->with([50])
            ->andThrow(new \RuntimeException('Connection refused on logistica schema'));

        $validador = new ValidadorPagoProveedoresTipo64Service($repo);
        $resultado = $validador->validarLoteParaRecepcion([50]);

        $this->assertFalse($resultado['valido'], 'Debe fallar cerrado ante cualquier excepción del servicio');
        $this->assertTrue($resultado['servicio_no_disponible']);
        $this->assertStringContainsString('fail-closed', $resultado['mensaje']);
    }
}