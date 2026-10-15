<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use Carbon\Carbon;

class ReporteCargoInternoService
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

    public function generarReporte(int $areaId, string $fechaDesdeSql, string $fechaHastaSql): array
    {
        $cargos = $this->repo()->obtenerCargosPorArea($areaId);

        $rows_data = [];
        $totales = [
            'sum_entregado' => 0,
            'sum_recibido' => 0,
            'sum_salida' => 0,
            'sum_finalizado' => 0,
            'sum_total_doc' => 0,
            'sum_total_ges' => 0,
            'porcentaje' => 0.0,
        ];

        foreach ($cargos as $cargo) {
            $cdgo = (int) $cargo->cdgo_crgo;

            $con_entrada = $this->repo()->contarReporteCargoEntrada($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_recibido = $this->repo()->contarReporteCargoRecibidos($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_salida = $this->repo()->contarReporteCargoSalida($cdgo, $fechaDesdeSql, $fechaHastaSql);
            $con_finalizado = $this->repo()->contarReporteCargoFinalizado($cdgo, $fechaDesdeSql, $fechaHastaSql);

            $total = $con_entrada + $con_recibido + $con_salida + $con_finalizado;
            $gestionados = $con_salida + $con_finalizado;

            $porcentaje = $total > 0
                ? round(($gestionados / $total) * 100, 2)
                : 0.0;

            $totales['sum_entregado'] += $con_entrada;
            $totales['sum_recibido'] += $con_recibido;
            $totales['sum_salida'] += $con_salida;
            $totales['sum_finalizado'] += $con_finalizado;
            $totales['sum_total_doc'] += $total;
            $totales['sum_total_ges'] += $gestionados;

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
                'porcentaje' => $porcentaje,
            ];
        }

        $totales['porcentaje'] = $totales['sum_total_doc'] > 0
            ? round(($totales['sum_total_ges'] / $totales['sum_total_doc']) * 100, 2)
            : 0.0;

        return [
            'rows_data' => $rows_data,
            'totales' => $totales,
        ];
    }

    public function obtenerTramitesDetalle(int $cargoId, int $tipoBandeja, string $desdeSql, string $hastaSql): array
    {
        $tramites = $this->repo()->obtenerTramitesReporteCargoDetalle($cargoId, $tipoBandeja, $desdeSql, $hastaSql);

        foreach ($tramites as $tramite) {
            $tramite->fechaini = Carbon::parse($tramite->fe_ingrso)->format('d/m/Y H:i:s');
            $tramite->fe_ultima = $tramite->fe_rcbdo
                ? Carbon::parse($tramite->fe_rcbdo)->format('d/m/Y H:i:s')
                : '-';
            $tramite->nu_tram_format = str_pad((string) $tramite->nu_tram_todo, 10, '0', STR_PAD_LEFT);
            $tramite->de_obser_corto = mb_substr((string) ($tramite->de_obser ?? ''), 0, 55);
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
