<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use Carbon\Carbon;

class ReporteAreaCargoInternoService
{
    public function __construct(
        private readonly ?TramiteInternoRepositoryInterface $repository = null
    ) {
    }

    private function repo(): TramiteInternoRepositoryInterface
    {
        return $this->repository ?? app(TramiteInternoRepositoryInterface::class);
    }

    public function obtenerAreaUsuario(int $cargoId): int
    {
        return $this->repo()->obtenerAreaUsuarioPorCargo($cargoId);
    }

    public function generarDashboardV2(int $areaId, string $fechaDesdeSql, string $fechaHastaSql): array
    {
        $evoData = $this->obtenerEvolucionV2($areaId, $fechaDesdeSql, $fechaHastaSql);

        $cargos = $this->repo()->obtenerCargosPorArea($areaId);

        $rows_data = [];
        $sum_entregado = 0;
        $sum_recibido = 0;
        $sum_salida = 0;
        $sum_finalizado = 0;
        $sum_total_doc = 0;
        $sum_total_ges = 0;

        foreach ($cargos as $cargo) {
            $cdgo = (int) $cargo->cdgo_crgo;

            $con_entrada = $this->repo()->contarReporteCargoEntrada($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_recibido = $this->repo()->contarReporteCargoRecibidos($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_salida = $this->repo()->contarReporteCargoSalida($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_finalizado = $this->repo()->contarReporteCargoFinalizado($cdgo, $fechaDesdeSql, $fechaHastaSql);

            $total = $con_entrada + $con_recibido + $con_salida + $con_finalizado;
            $gestionados = $con_salida + $con_finalizado;
            $pendientes = $con_entrada + $con_recibido;

            // Lógica v2: total=0 → 100%; sin pendientes → EXCELENTE
            $porcentaje = $total > 0 ? ($gestionados / $total) * 100 : 100;

            if ($pendientes === 0) {
                $porcentaje = 100;
                $status_text = 'EXCELENTE';
                $status_color = 'var(--success)';
            } elseif ($porcentaje >= 80) {
                $status_text = 'AL DÍA';
                $status_color = 'var(--success)';
            } elseif ($porcentaje >= 50) {
                $status_text = 'REVISAR';
                $status_color = 'var(--warning)';
            } else {
                $status_text = 'CRÍTICO';
                $status_color = 'var(--danger)';
            }

            $sum_entregado += $con_entrada;
            $sum_recibido += $con_recibido;
            $sum_salida += $con_salida;
            $sum_finalizado += $con_finalizado;
            $sum_total_doc += $total;
            $sum_total_ges += $gestionados;

            $usuariosRaw = $this->repo()->obtenerUsuariosDeCargo($cdgo);
            $usuarios = [];
            foreach ($usuariosRaw as $u) {
                $usuarios[] = (string) ($u->no_crto ?: $u->de_lgin);
            }
            $iniciales = array_map(fn ($n) => $this->geneIniciales($n), $usuarios);

            $rows_data[] = [
                'id' => $cdgo,
                'cargo' => (string) $cargo->de_crgo,
                'usuarios' => $usuarios,
                'iniciales' => $iniciales,
                'entrada' => $con_entrada,
                'recibido' => $con_recibido,
                'salida' => $con_salida,
                'finalizado' => $con_finalizado,
                'total' => $total,
                'gestionados' => $gestionados,
                'porcentaje' => round($porcentaje, 1),
                'status_text' => $status_text,
                'status_color' => $status_color,
            ];
        }

        usort($rows_data, function ($a, $b) {
            $aTiene = $a['total'] > 0;
            $bTiene = $b['total'] > 0;
            if ($aTiene !== $bTiene) {
                return $bTiene <=> $aTiene;
            }
            $pctA = $a['total'] > 0 ? ($a['gestionados'] / $a['total']) * 100 : 0;
            $pctB = $b['total'] > 0 ? ($b['gestionados'] / $b['total']) * 100 : 0;

            return $pctB <=> $pctA;
        });

        $sum_gestionado_global = $sum_salida + $sum_finalizado;
        $pct_trabajo = $sum_total_doc > 0 ? round(($sum_gestionado_global / $sum_total_doc) * 100, 1) : 0;

        if ($pct_trabajo >= 80) {
            $pct_color = '#10b981';
            $pct_bg = 'rgba(16,185,129,0.1)';
        } elseif ($pct_trabajo >= 50) {
            $pct_color = '#f59e0b';
            $pct_bg = 'rgba(245,158,11,0.1)';
        } else {
            $pct_color = '#ef4444';
            $pct_bg = 'rgba(239,68,68,0.1)';
        }

        $totales = [
            'sum_entregado' => $sum_entregado,
            'sum_recibido' => $sum_recibido,
            'sum_salida' => $sum_salida,
            'sum_finalizado' => $sum_finalizado,
            'sum_total_doc' => $sum_total_doc,
            'sum_total_ges' => $sum_total_ges,
            'pct_trabajo' => $pct_trabajo,
            'pct_color' => $pct_color,
            'pct_bg' => $pct_bg,
        ];

        return [
            'rows_data' => $rows_data,
            'totales' => $totales,
            'evoData' => $evoData,
        ];
    }

    public function obtenerEvolucionV2(int $areaId, string $desdeSql, string $hastaSql): array
    {
        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $resEvo = $this->repo()->obtenerV2Evolucion($areaId, $desdeSql, $hastaSql);

        $labels = [];
        $finalizados = [];
        $pendientes = [];
        $total_fin = 0;

        foreach ($resEvo as $row) {
            $idx = (int) $row->mes - 1;
            $labels[] = $meses[$idx] . ' ' . substr((string) $row->anio, 2);
            $finalizados[] = (int) $row->finalizados;
            $pendientes[] = (int) $row->pendientes;
            $total_fin += (int) $row->finalizados;
        }

        $avg_fin = count($finalizados) > 0 ? round($total_fin / count($finalizados), 1) : 0;
        $last_val = end($finalizados);
        $prev_val = prev($finalizados);
        $trend_pct = 0;
        if ($prev_val !== false && $prev_val > 0) {
            $trend_pct = round((($last_val - $prev_val) / $prev_val) * 100);
        }

        $rawAvg = $this->repo()->obtenerV2AvgTiempo($areaId, $desdeSql, $hastaSql);
        $avg_time = $rawAvg !== null ? round($rawAvg, 1) : 1.5;

        return [
            'labels' => $labels,
            'finalizados' => $finalizados,
            'pendientes' => $pendientes,
            'total_fin' => $total_fin,
            'avg_fin' => $avg_fin,
            'trend_pct' => $trend_pct,
            'avg_time' => $avg_time,
        ];
    }

    public function obtenerTramitesDetalleV2(int $cargoId, int $tipoBandeja, string $desdeSql, string $hastaSql): array
    {
        $tramites = $this->repo()->obtenerV2TramitesDetalle($cargoId, $tipoBandeja, $desdeSql, $hastaSql);

        foreach ($tramites as $tramite) {
            $areasSql = $this->repo()->obtenerRutaAreasPorDocumento((int) $tramite->cdgo_dtos_ntrnos);

            $ruta_areas = [];
            $last_area = '';
            foreach ($areasSql as $current_area) {
                if (! empty($current_area) && $current_area !== $last_area) {
                    $ruta_areas[] = (string) $current_area;
                    $last_area = (string) $current_area;
                }
            }
            $tramite->ruta_areas = $ruta_areas;

            $dias_area = 0;
            if ($tramite->fe_rcbdo) {
                $dias_area = Carbon::parse($tramite->fe_rcbdo)->diffInDays(Carbon::now('America/Lima'));
            }
            $tramite->dias_area = $dias_area;
            $tramite->fechaini = Carbon::parse($tramite->fe_ingrso)->format('d/m/Y H:i:s');
            $tramite->fe_ultima = $tramite->fe_rcbdo
                ? Carbon::parse($tramite->fe_rcbdo)->format('d/m/Y H:i:s')
                : '-';
            $tramite->nu_tram_format = str_pad((string) $tramite->nu_tram_todo, 10, '0', STR_PAD_LEFT);
            $tramite->area_actual = ! empty($ruta_areas) ? end($ruta_areas) : ($tramite->dc_area_inco ?? '-');
        }

        return $tramites;
    }

    private function geneIniciales(string $cadena): string
    {
        $cadena = trim($cadena);
        if ($cadena === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $cadena);
        $ini = '';
        foreach ($parts as $p) {
            if ($p !== '') {
                $ini .= mb_strtoupper(mb_substr($p, 0, 1));
            }
        }
        return $ini;
    }
}
