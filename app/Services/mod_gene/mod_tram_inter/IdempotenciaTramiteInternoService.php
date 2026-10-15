<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;
use InvalidArgumentException;

class IdempotenciaTramiteInternoService
{
    public function __construct(
        private readonly CacheRepository $cache
    ) {}

    /**
     * Ejecuta una operación bajo lock atómico e idempotencia para evitar doble envío.
     *
     * @template T
     * @param string $clave Clave idempotente del lote o transacción
     * @param int $ttlSegundos Tiempo de cache para repetición
     * @param Closure(): T $operacion
     * @return T
     */
    public function ejecutar(string $clave, int $ttlSegundos, Closure $operacion): mixed
    {
        $cacheKey = "idempotency_ti_resultado:{$clave}";
        $lockKey = "idempotency_ti_lock:{$clave}";

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $lockAdquirido = $this->cache->add($lockKey, 1, 10);
        if (!$lockAdquirido) {
            throw new RuntimeException(
                "CONFLICTO DE CONCURRENCIA: Existe una operación en curso para la clave ({$clave})."
            );
        }

        try {
            $resultado = $operacion();

            // Validar que no retorne éxito con cero procesados
            if (is_array($resultado) && isset($resultado['procesados']) && $resultado['procesados'] === 0) {
                throw new InvalidArgumentException("No se procesó ningún expediente del lote.");
            }

            $this->cache->put($cacheKey, $resultado, $ttlSegundos);
            return $resultado;
        } finally {
            $this->cache->forget($lockKey);
        }
    }
}