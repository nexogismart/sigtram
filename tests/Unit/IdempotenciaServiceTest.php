<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\mod_gm\mod_tram_exte\IdempotenciaService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class IdempotenciaServiceTest extends TestCase
{
    private IdempotenciaService $idempotencia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->idempotencia = new IdempotenciaService(Cache::store('array'));
    }

    public function test_ejecuta_operacion_una_sola_vez_y_retorna_resultado_en_replay(): void
    {
        $contador = 0;
        $clave = 'tx-12345';

        $resultado1 = $this->idempotencia->ejecutarIdempotente($clave, 60, function () use (&$contador) {
            $contador++;
            return ['status' => 'success', 'contador' => $contador];
        });

        $this->assertSame(1, $contador);
        $this->assertSame(['status' => 'success', 'contador' => 1], $resultado1);

        // Segundo envío con la misma clave (simulando reintento por doble clic o red)
        $resultado2 = $this->idempotencia->ejecutarIdempotente($clave, 60, function () use (&$contador) {
            $contador++;
            return ['status' => 'success', 'contador' => $contador];
        });

        // El contador NO debe incrementar, debe devolver la respuesta previa
        $this->assertSame(1, $contador);
        $this->assertSame($resultado1, $resultado2);
    }

    public function test_bloquea_ejecucion_concurrente_con_misma_clave(): void
    {
        $clave = 'tx-concurrente';
        Cache::store('array')->add("idempotency_lock:{$clave}", 1, 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CONFLICTO DE CONCURRENCIA');

        $this->idempotencia->ejecutarIdempotente($clave, 60, fn() => 'ok');
    }
}
