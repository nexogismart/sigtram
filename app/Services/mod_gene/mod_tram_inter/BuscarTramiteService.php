<?php

declare(strict_types=1);

namespace App\Services\mod_gene\mod_tram_inter;

use App\Contracts\Repositories\mod_gene\mod_tram_inter\TramiteInternoRepositoryInterface;

class BuscarTramiteService
{
    public function __construct(
        private readonly ?TramiteInternoRepositoryInterface $repository = null
    ) {
    }

    private function repo(): TramiteInternoRepositoryInterface
    {
        return $this->repository ?? app(TramiteInternoRepositoryInterface::class);
    }

    public function obtenerAreas(bool $isAdmin, int $areaUsuario): array
    {
        return $this->repo()->obtenerAreasCatalogo($isAdmin, $areaUsuario);
    }

    public function obtenerDestinoAreas(): array
    {
        return $this->repo()->obtenerDestinoAreasCatalogo();
    }

    public function obtenerTiposDocumento(?int $areaId): array
    {
        return $this->repo()->obtenerTiposPorArea($areaId);
    }

    public function buscarTramites(array $filtros, int $perPage = 36, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->repo()->buscarTramitesInternos($filtros, $perPage, $page);
    }

    public function obtenerDetalleTramite(int $id): ?object
    {
        return $this->repo()->obtenerDetalleTramite($id);
    }

    public function obtenerHistorialMovimientos(int $id): array
    {
        return $this->repo()->obtenerHistorialMovimientos($id);
    }

    public function obtenerAreaUsuario(int $cargoId): int
    {
        $cargo = $this->repo()->obtenerCargoInfo($cargoId);
        return (int) ($cargo->cdgo_area ?? 0);
    }
}
