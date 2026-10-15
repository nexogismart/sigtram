<?php

declare(strict_types=1);

namespace App\Services\mod_gm\mod_tram_exte;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;

/**
 * Servicio de Idempotencia y Prevención de Doble Envío.
 * Resuelve TE-005 garantizando que solicitudes duplicadas o reintentos
 * no ejecuten mutaciones múltiples en las transiciones de expedientes.
 */
class IdempotenciaService
{
    public function __construct(
        private readonly CacheRepository $cache
    ) {}

    /**
     * Ejecuta una acción protegida por clave de idempotencia.
     * Si la clave ya fue procesada, retorna el resultado cacheado.
     * Si está en ejecución concurrente, lanza una excepción de conflicto (409).
     *
     * @template T
     * @param string $claveIdempotente Clave única provista por el cliente o generada
     * @param int $ttlSegundos Tiempo de retención de la respuesta idempotente
     * @param Closure(): T $operacion Transacción a ejecutar
     * @return T
     * @throws RuntimeException Si hay una ejecución concurrente en curso
     */
    public function ejecutarIdempotente(string $claveIdempotente, int $ttlSegundos, Closure $operacion, ?string $payloadHash = null): mixed
    {
        $cacheKey = "idempotency_transicion:{$claveIdempotente}";
        $hashKey = "idempotency_hash:{$claveIdempotente}";
        $lockKey = "idempotency_lock:{$claveIdempotente}";

        // 1. Si ya existe un payload previo con esta clave y es distinto, conflicto 409
        if ($payloadHash !== null && $this->cache->has($hashKey)) {
            $prevHash = $this->cache->get($hashKey);
            if ($prevHash !== $payloadHash) {
                throw new RuntimeException("Conflicto de idempotencia: la clave ya fue utilizada con un payload diferente.");
            }
        }

        // 2. Si ya tenemos un resultado confirmado para esta clave, retornarlo directamente (replay seguro)
        if ($this->cache->has($cacheKey)) {
            /** @var T */
            return $this->cache->get($cacheKey);
        }

        // 3. Adquirir lock atómico de aplicación para prevenir ejecuciones concurrentes simultáneas
        $lockAdquirido = $this->cache->add($lockKey, 1, 10);
        if (!$lockAdquirido) {
            throw new RuntimeException(
                "CONFLICTO DE CONCURRENCIA: Ya existe una operación en curso con la misma clave idempotente ({$claveIdempotente})."
            );
        }

        try {
            if ($payloadHash !== null) {
                $this->cache->put($hashKey, $payloadHash, $ttlSegundos);
            }
            $resultado = $operacion();
            $this->cache->put($cacheKey, $resultado, $ttlSegundos);

            return $resultado;
        } finally {
            $this->cache->forget($lockKey);
        }
    }
}
