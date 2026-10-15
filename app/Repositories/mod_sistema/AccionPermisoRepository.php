<?php

declare(strict_types=1);

namespace App\Repositories\mod_sistema;

use App\Contracts\Repositories\AccionPermisoRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class AccionPermisoRepository
 *
 * Implementa la consulta parametrizada para verificar permisos granulares de acciones
 * en las tablas sistema.a_acciones y sistema.r_acciones_usrios.
 */
class AccionPermisoRepository implements AccionPermisoRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function tieneAccionPermitida(int $cdgoLgin, int $cdgoCmpnia, string $codigoAccion): bool
    {
        // Fail-closed estricto: denegar si falta algún parámetro de identidad o código
        if ($cdgoLgin <= 0 || $cdgoCmpnia <= 0 || trim($codigoAccion) === '') {
            return false;
        }

        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        try {
            return DB::table($schema . 'a_acciones as a')
                ->join($schema . 'r_acciones_usrios as u', 'u.cdgo_accion', '=', 'a.cdgo_accion')
                ->where('a.de_codigo', $codigoAccion)
                ->where('a.in_esta', '1')
                ->where('u.cdgo_lgin', $cdgoLgin)
                ->where('u.cdgo_cmpnia', $cdgoCmpnia)
                ->where('u.in_permitido', '1')
                ->where('u.in_esta', '1')
                ->exists();
        } catch (Throwable $e) {
            Log::warning('Error en consulta de permiso granular de acción', [
                'accion' => $codigoAccion,
                'error'  => $e->getMessage(),
            ]);

            return false;
        }
    }
}
