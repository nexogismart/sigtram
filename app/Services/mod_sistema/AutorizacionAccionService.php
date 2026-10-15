<?php

declare(strict_types=1);

namespace App\Services\mod_sistema;

use App\Contracts\Repositories\AccionPermisoRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class AutorizacionAccionService
 *
 * Servicio centralizado para la autorización de acciones granulares del sistema.
 * Desacoplado de módulos específicos para permitir reutilización transversal.
 */
class AutorizacionAccionService
{
    public function __construct(
        private readonly AccionPermisoRepositoryInterface $repository
    ) {}

    /**
     * Evalúa si un usuario (por su login canónico) tiene autorización activa
     * para ejecutar una acción específica dentro del contexto de la compañía activa.
     *
     * @param int $cdgoLgin Código numérico canónico de login (p_lgin.cdgo_lgin)
     * @param int $cdgoCompania ID de la compañía activa
     * @param string $codigoAccion Código canónico de la acción (ej. tramite_externo.exportar_excel)
     * @return bool True si está estrictamente autorizado, false en caso contrario
     */
    public function puedeEjecutar(int $cdgoLgin, int $cdgoCompania, string $codigoAccion): bool
    {
        if ($cdgoLgin <= 0 || $cdgoCompania <= 0 || trim($codigoAccion) === '') {
            return false;
        }

        try {
            return $this->repository->tieneAccionPermitida($cdgoLgin, $cdgoCompania, $codigoAccion);
        } catch (Throwable $e) {
            Log::warning('Fallo al evaluar autorización de acción', [
                'accion' => $codigoAccion,
                'error'  => $e->getMessage(),
            ]);

            return false;
        }
    }
}
