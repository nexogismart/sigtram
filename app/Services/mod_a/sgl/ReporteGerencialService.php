<?php

namespace App\Services\mod_a\sgl;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reporte general de pago a proveedores por U/O
 * (migrado desde sigtram_antiguo/.../pago_proveedores/reporte/reporte_gerencial.php).
 */
class ReporteGerencialService
{
    private const SCHEMA_LOGISTICA = 'logistica.';

    private readonly string $schemaSistema;

    public function __construct()
    {
        $this->schemaSistema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';
    }

    /**
     * Genera el reporte por área entre dos fechas (Y-m-d).
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function generar(string $feInicio, string $feFin): array
    {
        if (! $this->fechaValida($feInicio) || ! $this->fechaValida($feFin)) {
            return $this->fail('Fechas inválidas. Use el formato AAAA-MM-DD.');
        }

        if ($feInicio > $feFin) {
            return $this->fail('La fecha Desde no puede ser mayor que Hasta.');
        }

        try {
            $sistema = $this->schemaSistema;
            $areas = DB::select(
                "SELECT cdgo_area, de_area
                 FROM {$sistema}a_areas
                 WHERE in_esta = '1'
                 ORDER BY cdgo_area ASC"
            );

            $filas = [];
            $totales = [
                'proveedores' => 0,
                'os' => 0,
                'conformidad' => 0,
                'comprometido' => 0,
                'devengado' => 0,
                'girado' => 0,
            ];

            $i = 0;
            foreach ($areas as $area) {
                $cdgoArea = (int) $area->cdgo_area;
                $fila = $this->metricasArea($cdgoArea, $feInicio, $feFin);

                $totales['proveedores'] += $fila['proveedores'];
                $totales['os'] += $fila['os'];
                $totales['conformidad'] += $fila['conformidad'];
                $totales['comprometido'] += $fila['comprometido'];
                $totales['devengado'] += $fila['devengado'];
                $totales['girado'] += $fila['girado'];

                $filas[] = [
                    'n' => ++$i,
                    'uo' => trim((string) ($area->de_area ?? '')),
                    'proveedores' => $fila['proveedores'],
                    'os' => $fila['os'],
                    'os_pct' => $this->porcentaje($fila['os'], $fila['proveedores']),
                    'conformidad' => $fila['conformidad'],
                    'conformidad_pct' => $this->porcentaje($fila['conformidad'], $fila['os']),
                    'comprometido' => $fila['comprometido'],
                    'comprometido_pct' => $this->porcentaje($fila['comprometido'], $fila['conformidad']),
                    'devengado' => $fila['devengado'],
                    'devengado_pct' => $this->porcentaje($fila['devengado'], $fila['comprometido']),
                    'girado' => $fila['girado'],
                    'girado_pct' => $this->porcentaje($fila['girado'], $fila['devengado']),
                ];
            }

            return $this->ok([
                'filas' => $filas,
                'totales' => [
                    'proveedores' => $totales['proveedores'],
                    'os' => $totales['os'],
                    'os_pct' => $this->porcentaje($totales['os'], $totales['proveedores']),
                    'conformidad' => $totales['conformidad'],
                    'conformidad_pct' => $this->porcentaje($totales['conformidad'], $totales['os']),
                    'comprometido' => $totales['comprometido'],
                    'comprometido_pct' => $this->porcentaje($totales['comprometido'], $totales['conformidad']),
                    'devengado' => $totales['devengado'],
                    'devengado_pct' => $this->porcentaje($totales['devengado'], $totales['comprometido']),
                    'girado' => $totales['girado'],
                    'girado_pct' => $this->porcentaje($totales['girado'], $totales['devengado']),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('[reporte_gerencial] generar: ' . $e->getMessage());

            return $this->fail('No se pudo generar el reporte gerencial.');
        }
    }

    /**
     * @return array{proveedores: int, os: int, conformidad: int, comprometido: int, devengado: int, girado: int}
     */
    private function metricasArea(int $cdgoArea, string $feInicio, string $feFin): array
    {
        $os = $this->contarProveedoresOs($cdgoArea, $feInicio, $feFin);
        $ids = $this->recolectarIdsConformidad($cdgoArea, $feInicio, $feFin);

        $conformidad = $this->contarPorCampoIds(
            $cdgoArea,
            'h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad',
            $ids['cnfrmdad']
        );
        $comprometido = $this->contarPorCampoIds(
            $cdgoArea,
            'h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngar',
            $ids['dvngar']
        );
        $devengado = $this->contarPorCampoIds(
            $cdgoArea,
            'h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngdo',
            $ids['dvngdo']
        );
        $girado = $this->contarGirado($cdgoArea, $ids['cmprbnte']);

        return [
            'proveedores' => $os,
            'os' => $os,
            'conformidad' => $conformidad,
            'comprometido' => $comprometido,
            'devengado' => $devengado,
            'girado' => $girado,
        ];
    }

    private function contarProveedoresOs(int $cdgoArea, string $feInicio, string $feFin): int
    {
        $sql = '
            SELECT p_usrios.cdgo_usrios
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres
                ON p_pgo_prvdres.cdgo_pgo_prvdres = h_deta_pgo_prvdres.cdgo_pgo_prvdres
            INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos
                ON h_deta_pgo_prvdres.cdgo_deta_pgo_prvdres = h_deta_pgo_mmos.cdgo_deta_pgo_prvdres
            INNER JOIN ' . $this->schemaSistema . 'p_usrios
                ON h_deta_pgo_prvdres.cdgo_usrios = p_usrios.cdgo_usrios
            WHERE p_pgo_prvdres.in_esta NOT IN (0, 2)
              AND h_deta_pgo_prvdres.in_esta = 1
              AND p_pgo_prvdres.in_pgo_prvdres = 1
              AND p_pgo_prvdres.cdgo_area = ?
              AND h_deta_pgo_mmos.fe_pgo_aprxmdo >= ?
              AND h_deta_pgo_mmos.fe_pgo_aprxmdo <= ?
            GROUP BY p_usrios.cdgo_usrios
        ';

        return count(DB::select($sql, [$cdgoArea, $feInicio, $feFin]));
    }

    /**
     * Equivalente a criterio_2 + recolección de códigos del original.
     *
     * @return array{cnfrmdad: int[], dvngar: int[], dvngdo: int[], cmprbnte: int[]}
     */
    private function recolectarIdsConformidad(int $cdgoArea, string $feInicio, string $feFin): array
    {
        $sql = '
            SELECT h_deta_pgo_mmos.cdgo_deta_pgo_prvdres,
                   p_dtos_ntrnos.fe_ingrso,
                   h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad,
                   h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngar,
                   h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngdo,
                   h_deta_pgo_mmos.cdgo_cmprbnte_pgo
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres
                ON p_pgo_prvdres.cdgo_pgo_prvdres = h_deta_pgo_prvdres.cdgo_pgo_prvdres
            INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos
                ON h_deta_pgo_prvdres.cdgo_deta_pgo_prvdres = h_deta_pgo_mmos.cdgo_deta_pgo_prvdres
            INNER JOIN ' . $this->schemaSistema . 'p_usrios
                ON h_deta_pgo_prvdres.cdgo_usrios = p_usrios.cdgo_usrios
            INNER JOIN ' . $this->schemaSistema . 'p_dtos_ntrnos
                ON h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad = p_dtos_ntrnos.cdgo_dtos_ntrnos
            WHERE p_pgo_prvdres.in_esta NOT IN (0, 2)
              AND p_pgo_prvdres.in_pgo_prvdres = 1
              AND p_pgo_prvdres.cdgo_area = ?
              AND h_deta_pgo_mmos.fe_pgo_aprxmdo >= ?
              AND h_deta_pgo_mmos.fe_pgo_aprxmdo <= ?
              AND p_dtos_ntrnos.fe_ingrso >= ?
            GROUP BY h_deta_pgo_mmos.cdgo_deta_pgo_prvdres,
                     p_dtos_ntrnos.fe_ingrso,
                     h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad,
                     h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngar,
                     h_deta_pgo_mmos.cdgo_dtos_ntrnos_dvngdo,
                     h_deta_pgo_mmos.cdgo_cmprbnte_pgo
            ORDER BY p_dtos_ntrnos.fe_ingrso DESC
        ';

        $rows = DB::select($sql, [$cdgoArea, $feInicio, $feFin, $feInicio . ' 00:00']);

        $cnfrmdad = [];
        $dvngar = [];
        $dvngdo = [];
        $cmprbnte = [];

        foreach ($rows as $row) {
            if (! empty($row->cdgo_dtos_ntrnos_cnfrmdad)) {
                $cnfrmdad[] = (int) $row->cdgo_dtos_ntrnos_cnfrmdad;
            }
            if (! empty($row->cdgo_dtos_ntrnos_dvngar)) {
                $dvngar[] = (int) $row->cdgo_dtos_ntrnos_dvngar;
            }
            if (! empty($row->cdgo_dtos_ntrnos_dvngdo)) {
                $dvngdo[] = (int) $row->cdgo_dtos_ntrnos_dvngdo;
            }
            if (! empty($row->cdgo_cmprbnte_pgo)) {
                $cmprbnte[] = (int) $row->cdgo_cmprbnte_pgo;
            }
        }

        // El original siempre agrega "0" para que el IN no quede vacío.
        return [
            'cnfrmdad' => $this->idsConCero($cnfrmdad),
            'dvngar' => $this->idsConCero($dvngar),
            'dvngdo' => $this->idsConCero($dvngdo),
            'cmprbnte' => $this->idsConCero($cmprbnte),
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    private function contarPorCampoIds(int $cdgoArea, string $campo, array $ids): int
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = '
            SELECT p_usrios.cdgo_usrios,
                   h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad,
                   p_dtos_ntrnos.fe_ingrso
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres
                ON p_pgo_prvdres.cdgo_pgo_prvdres = h_deta_pgo_prvdres.cdgo_pgo_prvdres
            INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos
                ON h_deta_pgo_prvdres.cdgo_deta_pgo_prvdres = h_deta_pgo_mmos.cdgo_deta_pgo_prvdres
            INNER JOIN ' . $this->schemaSistema . 'p_usrios
                ON h_deta_pgo_prvdres.cdgo_usrios = p_usrios.cdgo_usrios
            INNER JOIN ' . $this->schemaSistema . 'p_dtos_ntrnos
                ON ' . $campo . ' = p_dtos_ntrnos.cdgo_dtos_ntrnos
            WHERE p_pgo_prvdres.in_esta NOT IN (0, 2)
              AND h_deta_pgo_prvdres.in_esta = 1
              AND p_pgo_prvdres.in_pgo_prvdres = 1
              AND p_pgo_prvdres.cdgo_area = ?
              AND ' . $campo . ' IN (' . $placeholders . ')
            GROUP BY p_usrios.cdgo_usrios,
                     h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad,
                     p_dtos_ntrnos.fe_ingrso
        ';

        $params = array_merge([$cdgoArea], $ids);

        return count(DB::select($sql, $params));
    }

    /**
     * @param  list<int>  $ids
     */
    private function contarGirado(int $cdgoArea, array $ids): int
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = '
            SELECT p_usrios.cdgo_usrios,
                   h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad
            FROM ' . self::SCHEMA_LOGISTICA . 'p_pgo_prvdres
            INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_prvdres
                ON p_pgo_prvdres.cdgo_pgo_prvdres = h_deta_pgo_prvdres.cdgo_pgo_prvdres
            INNER JOIN ' . self::SCHEMA_LOGISTICA . 'h_deta_pgo_mmos
                ON h_deta_pgo_prvdres.cdgo_deta_pgo_prvdres = h_deta_pgo_mmos.cdgo_deta_pgo_prvdres
            INNER JOIN ' . $this->schemaSistema . 'p_usrios
                ON h_deta_pgo_prvdres.cdgo_usrios = p_usrios.cdgo_usrios
            WHERE p_pgo_prvdres.in_esta NOT IN (0, 2)
              AND h_deta_pgo_prvdres.in_esta = 1
              AND p_pgo_prvdres.in_pgo_prvdres = 1
              AND p_pgo_prvdres.cdgo_area = ?
              AND h_deta_pgo_mmos.cdgo_cmprbnte_pgo IN (' . $placeholders . ')
            GROUP BY p_usrios.cdgo_usrios,
                     h_deta_pgo_mmos.cdgo_dtos_ntrnos_cnfrmdad
        ';

        $params = array_merge([$cdgoArea], $ids);

        return count(DB::select($sql, $params));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function idsConCero(array $ids): array
    {
        $unique = array_values(array_unique(array_map('intval', $ids)));
        $unique[] = 0;

        return $unique;
    }

    private function porcentaje(int $numerador, int $denominador): string
    {
        if ($denominador <= 0) {
            return '0.00';
        }

        return number_format(($numerador / $denominador) * 100, 2, '.', '');
    }

    private function fechaValida(string $fecha): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1;
    }

    private function ok(array $data): array
    {
        return ['success' => true, 'data' => $data];
    }

    private function fail(string $error): array
    {
        return ['success' => false, 'error' => $error];
    }
}
