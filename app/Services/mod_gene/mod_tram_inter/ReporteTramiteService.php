<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use Carbon\Carbon;

class ReporteTramiteService
{
    public function __construct(
        private readonly ?TramiteInternoRepositoryInterface $repository = null
    ) {
    }

    private function repo(): TramiteInternoRepositoryInterface
    {
        return $this->repository ?? app(TramiteInternoRepositoryInterface::class);
    }

    public function generarReporte(string $fechaDesdeSql, string $fechaHastaSql): array
    {
        $data = $this->repo()->obtenerReporteTramitesPorAreasDetallado($fechaDesdeSql, $fechaHastaSql);
        $areas = $data['areas'];
        $q1Results = $data['q1'];
        $q2Results = $data['q2'];

        $reportes = [];
        $totales = [
            'sum_entregado' => 0,
            'sum_recibido' => 0,
            'sum_salida' => 0,
            'sum_finalizado' => 0,
            'sum_total_doc' => 0,
            'sum_total_ges' => 0,
            'porcentaje' => '0.00',
        ];

        foreach ($areas as $cdgoArea => $area) {
            $porRecibir = isset($q1Results[$cdgoArea]) ? (int) $q1Results[$cdgoArea]->por_recibir : 0;
            $recibidos = isset($q1Results[$cdgoArea]) ? (int) $q1Results[$cdgoArea]->recibidos : 0;
            $finalizados = isset($q1Results[$cdgoArea]) ? (int) $q1Results[$cdgoArea]->finalizados : 0;
            $derivados = isset($q2Results[$cdgoArea]) ? (int) $q2Results[$cdgoArea]->derivados : 0;
            $totalDoc = $porRecibir + $recibidos + $derivados + $finalizados;

            $porcentaje = $totalDoc > 0
                ? number_format((($derivados + $finalizados) / $totalDoc) * 100, 2, '.', '')
                : number_format(0, 2, '.', '');

            $r = (object) [
                'cdgo_area' => (int) $cdgoArea,
                'de_area' => $area->de_area,
                'por_recibir' => $porRecibir,
                'recibidos' => $recibidos,
                'derivados' => $derivados,
                'finalizados' => $finalizados,
                'total_doc' => $totalDoc,
                'porcentaje' => $porcentaje,
                'porcentaje_num' => (float) $porcentaje,
            ];

            $totales['sum_entregado'] += $r->por_recibir;
            $totales['sum_recibido'] += $r->recibidos;
            $totales['sum_salida'] += $r->derivados;
            $totales['sum_finalizado'] += $r->finalizados;
            $totales['sum_total_doc'] += $r->total_doc;
            $totales['sum_total_ges'] += ($r->derivados + $r->finalizados);

            $reportes[] = $r;
        }

        // ORDER BY nu_prcntje DESC, nu_total DESC
        usort($reportes, function ($a, $b) {
            if ($b->porcentaje_num !== $a->porcentaje_num) {
                return $b->porcentaje_num <=> $a->porcentaje_num;
            }

            return $b->total_doc <=> $a->total_doc;
        });

        $totales['porcentaje'] = $totales['sum_total_doc'] > 0
            ? number_format(($totales['sum_total_ges'] / $totales['sum_total_doc']) * 100, 2, '.', '')
            : number_format(0, 2, '.', '');

        return [
            'reportes' => $reportes,
            'totales' => $totales,
        ];
    }

    public function obtenerTramitesDetalle(int $areaId, int $tipo, string $fechaDesdeSql, string $fechaHastaSql): array
    {
        $tramites = $this->repo()->obtenerTramitesReporteAreaDetalle($areaId, $tipo, $fechaDesdeSql, $fechaHastaSql);

        foreach ($tramites as $tramite) {
            $tramite->fechaini = Carbon::parse($tramite->fe_ingrso)->timezone('America/Lima')->format('d/m/Y H:i:s');
            $tramite->fe_ultima = $tramite->fe_rcbdo
                ? Carbon::parse($tramite->fe_rcbdo)->timezone('America/Lima')->format('d/m/Y H:i:s')
                : '-';
            $tramite->nu_tram_format = str_pad((string) $tramite->nu_tram_todo, 10, '0', STR_PAD_LEFT);
            $tramite->de_obser_corto = mb_substr((string) ($tramite->de_obser ?? ''), 0, 55);
        }

        return $tramites;
    }
}
