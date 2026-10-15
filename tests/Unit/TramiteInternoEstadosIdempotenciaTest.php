<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Enums\mod_gene\EstadoTramiteInterno;
use App\Services\mod_gene\mod_tram_inter\MaquinaEstadosTramiteInterno;
use App\Services\mod_gene\mod_tram_inter\IdempotenciaTramiteInternoService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery;
use RuntimeException;
use InvalidArgumentException;

class TramiteInternoEstadosIdempotenciaTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_transiciones_legales_permitidas(): void
    {
        $maquina = new MaquinaEstadosTramiteInterno();

        // 1 (Pendiente) -> recepcionar -> 2 (Recibido)
        $this->assertTrue($maquina->esTransicionValida(EstadoTramiteInterno::PENDIENTE, 'recepcionar'));
        $this->assertEquals(EstadoTramiteInterno::RECIBIDO, $maquina->obtenerEstadoDestino(EstadoTramiteInterno::PENDIENTE, 'recepcionar'));

        // 2 (Recibido) -> derivar -> 1 (Pendiente en destino)
        $this->assertTrue($maquina->esTransicionValida(EstadoTramiteInterno::RECIBIDO, 'derivar'));
        $this->assertEquals(EstadoTramiteInterno::PENDIENTE, $maquina->obtenerEstadoDestino(EstadoTramiteInterno::RECIBIDO, 'derivar'));

        // 2 (Recibido) -> asignar -> 7 (Asignado)
        $this->assertTrue($maquina->esTransicionValida(EstadoTramiteInterno::RECIBIDO, 'asignar'));
        $this->assertEquals(EstadoTramiteInterno::ASIGNADO, $maquina->obtenerEstadoDestino(EstadoTramiteInterno::RECIBIDO, 'asignar'));

        // 2 (Recibido) -> finalizar -> 3 (Finalizado)
        $this->assertTrue($maquina->esTransicionValida(EstadoTramiteInterno::RECIBIDO, 'finalizar'));
        $this->assertEquals(EstadoTramiteInterno::FINALIZADO, $maquina->obtenerEstadoDestino(EstadoTramiteInterno::RECIBIDO, 'finalizar'));

        // 1 (Pendiente) -> reiterar -> 53 (Reiterado)
        $this->assertTrue($maquina->esTransicionValida(EstadoTramiteInterno::PENDIENTE, 'reiterar'));
        $this->assertEquals(EstadoTramiteInterno::REITERADO, $maquina->obtenerEstadoDestino(EstadoTramiteInterno::PENDIENTE, 'reiterar'));
    }

    public function test_transiciones_ilegales_lanzan_excepcion(): void
    {
        $maquina = new MaquinaEstadosTramiteInterno();

        // No se puede asignar si está pendiente sin recepcionar
        $this->assertFalse($maquina->esTransicionValida(EstadoTramiteInterno::PENDIENTE, 'asignar'));

        $this->expectException(InvalidArgumentException::class);
        $maquina->obtenerEstadoDestino(EstadoTramiteInterno::PENDIENTE, 'asignar');
    }

    public function test_idempotencia_retorna_resultado_cacheado_y_previene_doble_envio(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $service = new IdempotenciaTramiteInternoService($cache);

        // Caso 1: Clave ya existe en cache (replay seguro)
        $cache->shouldReceive('has')->with('idempotency_ti_resultado:token-123')->andReturn(true);
        $cache->shouldReceive('get')->with('idempotency_ti_resultado:token-123')->andReturn(['procesados' => 2]);

        $llamado = false;
        $res = $service->ejecutar('token-123', 60, function () use (&$llamado) {
            $llamado = true;
            return ['procesados' => 5];
        });

        $this->assertEquals(['procesados' => 2], $res);
        $this->assertFalse($llamado, 'No debe volver a ejecutar la operación si la clave ya fue confirmada');
    }

    public function test_idempotencia_rechaza_ejecucion_concurrente(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $service = new IdempotenciaTramiteInternoService($cache);

        $cache->shouldReceive('has')->with('idempotency_ti_resultado:token-456')->andReturn(false);
        // Lock no adquirido (operación paralela en curso)
        $cache->shouldReceive('add')->with('idempotency_ti_lock:token-456', 1, 10)->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CONFLICTO DE CONCURRENCIA');

        $service->ejecutar('token-456', 60, function () {
            return ['procesados' => 1];
        });
    }

    public function test_idempotencia_falla_si_procesados_es_cero(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $service = new IdempotenciaTramiteInternoService($cache);

        $cache->shouldReceive('has')->with('idempotency_ti_resultado:token-789')->andReturn(false);
        $cache->shouldReceive('add')->with('idempotency_ti_lock:token-789', 1, 10)->andReturn(true);
        $cache->shouldReceive('forget')->with('idempotency_ti_lock:token-789')->andReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No se procesó ningún expediente del lote.');

        $service->ejecutar('token-789', 60, function () {
            return ['procesados' => 0];
        });
    }
}