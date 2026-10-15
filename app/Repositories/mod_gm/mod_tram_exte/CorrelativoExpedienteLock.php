<?php

declare(strict_types=1);

namespace App\Repositories\mod_gm\mod_tram_exte;

use App\Contracts\Repositories\mod_gm\mod_tram_exte\BuscaExpedienteRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\IngresoExpedienteRepositoryInterface;
use App\Contracts\Repositories\mod_gm\mod_tram_exte\TramiteExternoRepositoryInterface;

use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

/**
 * Gestor concurrente y atómico de generación de correlativos y control de años para expedientes.
 *
 * Implementa bloqueo transaccional PostgreSQL fail-closed por compañía + año común,
 * garantizando la serialización tanto de la creación de nuevos años como de la
 * obtención de correlativos secuenciales sin condiciones de carrera.
 */
class CorrelativoExpedienteLock
{
    private string $schema;

    public function __construct(
        private readonly DatabaseManager $db
    ) {
        $this->schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    /**
     * Retorna la clave determinística para serialización PostgreSQL común por año.
     *
     * La clave es común para cualquier tipo documental dentro del mismo año para
     * evitar condiciones de carrera al crear el primer registro del año en a_anios.
     */
    public function obtenerClaveSerializacion(int $currYear, int $tpoTrmtes = 0): int
    {
        return $currYear;
    }

    /**
     * Retorna los parámetros vinculados [cmpnia, clave] para el advisory lock.
     *
     * @return array{0: int, 1: int}
     */
    public function obtenerParametrosLock(int $cmpnia, int $currYear, int $tpoTrmtes = 0): array
    {
        return [$cmpnia, $this->obtenerClaveSerializacion($currYear, $tpoTrmtes)];
    }

    /**
     * Aplica advisory transaction lock fail-closed si la conexión es PostgreSQL.
     *
     * Reglas de auditoría:
     * 1. Obtiene el driver explícitamente.
     * 2. Si no es pgsql, retorna sin ejecutar advisory lock (p.ej. SQLite en tests).
     * 3. Si es pgsql, ejecuta el advisory lock con parámetros vinculados.
     * 4. NO captura Throwable ni QueryException: propaga cualquier fallo de forma inmediata.
     */
    public function serializarCorrelativoPostgres(int $cmpnia, int $currYear, int $tpoTrmtes = 0): void
    {
        $driver = $this->db->connection()->getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }

        $params = $this->obtenerParametrosLock($cmpnia, $currYear, $tpoTrmtes);
        $this->db->statement('SELECT pg_advisory_xact_lock(?, ?)', $params);
    }

    /**
     * Resuelve de forma atómica y concurrente el año y el correlativo del expediente.
     *
     * @return array{idAnio: int, deAnio: int, nuExpe: int}
     */
    public function resolver(int $cmpnia, int $tpoTrmtes, int $currYear): array
    {
        if ($cmpnia <= 0) {
            throw new InvalidArgumentException('Compañía inválida para generación de correlativo.');
        }

        // 1. Advisory Lock transaccional fail-closed común para compañía + año
        $this->serializarCorrelativoPostgres($cmpnia, $currYear, $tpoTrmtes);

        // 2. Búsqueda específica por compañía y año exactos con lock de fila cuando existe
        $anioRow = $this->db->table($this->schema . 'a_anios')
            ->where('cdgo_cmpnia', $cmpnia)
            ->where('de_anio', $currYear)
            ->lockForUpdate()
            ->first();

        if (!$anioRow) {
            $idAnio = (int) $this->db->table($this->schema . 'a_anios')->insertGetId([
                'de_anio' => $currYear,
                'dc_anio' => substr((string) $currYear, 2, 2),
                'cdgo_cmpnia' => $cmpnia,
            ], 'cdgo_anio');
            $deAnio = $currYear;
            $nuExpe = 1;
        } else {
            $idAnio = (int) $anioRow->cdgo_anio;
            $deAnio = (int) $anioRow->de_anio;

            // 3. MAX() ejecutado sin FOR UPDATE (PostgreSQL prohíbe FOR UPDATE en agregados)
            $maxExp = $this->db->table($this->schema . 'p_dto_trmtes')
                ->where('cdgo_anio', $idAnio)
                ->where('cdgo_tpo_trmte', $tpoTrmtes)
                ->where('cdgo_cmpnia', $cmpnia)
                ->max('nu_expe_trmte');

            $nuExpe = ($maxExp !== null ? (int) $maxExp : 0) + 1;
        }

        return [
            'idAnio' => $idAnio,
            'deAnio' => $deAnio,
            'nuExpe' => $nuExpe,
        ];
    }
}
