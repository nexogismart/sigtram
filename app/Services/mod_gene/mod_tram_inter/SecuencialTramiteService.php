<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;
use Carbon\Carbon;

class SecuencialTramiteService
{
    public function __construct(
        private readonly ?TramiteInternoRepositoryInterface $repository = null
    ) {
    }

    private function repo(): TramiteInternoRepositoryInterface
    {
        return $this->repository ?? app(TramiteInternoRepositoryInterface::class);
    }

    public function obtenerDatosIndex(int $companiaId, int $areaId): array
    {
        $anios = $this->repo()->obtenerAniosCatalogo();
        $aniosCol = collect($anios);
        $anioActual = (int) date('Y');
        $cdgoAnioActual = (int) ($aniosCol->firstWhere('de_anio', (string) $anioActual)->cdgo_anio
            ?? $aniosCol->first()->cdgo_anio
            ?? 0);

        $tipos = $this->repo()->obtenerTiposPorArea($areaId);

        return [
            'anios' => $aniosCol,
            'cdgoAnioActual' => $cdgoAnioActual,
            'tipos' => collect($tipos),
        ];
    }

    public function obtenerListaSecuenciales(int $cdgoTpo, int $cdgoAnio, int $areaId, int $cargoId): array
    {
        if ($cdgoTpo <= 0 || $cdgoAnio <= 0) {
            return [];
        }

        $inScncal = $this->repo()->obtenerInScncalTipo($cdgoTpo);
        $rows = $this->repo()->obtenerListaSecuenciales($cdgoAnio, $cdgoTpo, $areaId, $cargoId, $inScncal);

        foreach ($rows as $row) {
            $num = $row->nu_scncal_tpo_trmtes_area ?? $row->nu_smlla_intrno ?? 0;
            $row->documento = str_pad((string) $num . '-' . ($row->de_anio ?? date('Y')), 8, '0', STR_PAD_LEFT);
            $fecha = $row->fe_scncal_tpo_trmtes_area ?? null;
            $row->fecha_fmt = $fecha
                ? Carbon::parse($fecha)->timezone('America/Lima')->format('d/m/Y H:i')
                : '-';
            $row->usu_ini = $this->geneIniciales((string) ($row->no_crto ?? ''));
        }

        return $rows;
    }

    public function generarSecuencial(int $cdgoTpo, int $cdgoAnio, string $detalle, int $areaId, int $cargoId, int $userId): int
    {
        $inScncal = $this->repo()->obtenerInScncalTipo($cdgoTpo);
        $max = $this->repo()->obtenerMaxSecuencial($cdgoAnio, $cdgoTpo, $areaId, $cargoId, $inScncal);
        $nu = $max + 1;

        $historial = $detalle . '+' . $userId . '+' . Carbon::now('America/Lima')->format('d/m/Y H:i:s');

        $this->repo()->insertarSecuencial([
            'cdgo_tpo_trmte' => $cdgoTpo,
            'cdgo_area' => $areaId,
            'cdgo_crgo' => $cargoId,
            'cdgo_anio' => $cdgoAnio,
            'fe_scncal_tpo_trmtes_area' => now(),
            'nu_scncal_tpo_trmtes_area' => $nu,
            'de_scncal_tpo_trmtes_area' => $detalle,
            'de_scncal_tpo_trmtes_area_hstral' => mb_strtoupper($historial),
            'ts_usua_modi' => now(),
            'co_usua_modi' => $userId,
            'in_esta' => '1',
        ]);

        return $nu;
    }

    public function obtenerContextoCargoArea(int $cargoId): array
    {
        $cargo = $this->repo()->obtenerCargoInfo($cargoId);
        return [
            'area_id' => (int) ($cargo->cdgo_area ?? 0),
            'area_nombre' => (string) ($cargo->de_area ?? ''),
            'cmpnia' => (int) ($cargo->cdgo_cmpnia ?? 0),
        ];
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
