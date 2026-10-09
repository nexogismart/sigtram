<?php

declare(strict_types=1);

namespace App\Repositories\mod_sistema;

use App\Contracts\Repositories\PermisosRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Support\SessionKeys;

/**
 * Class PermisosRepository
 *
 * Implementa el contrato de acceso a base de datos de permisos con
 * caché integrada (8 horas) e invalidación de caché.
 */
class PermisosRepository implements PermisosRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function obtenerPermisosUsuario(string $cdgoLgin, int $cdgoCargo, int $cdgoPerfl): array
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        return Cache::remember(
            SessionKeys::permKey($cdgoLgin),
            now()->addHours(8),
            function () use ($cdgoLgin, $cdgoCargo, $cdgoPerfl, $schema) {
                $hasExceptionsTable = \Illuminate\Support\Facades\Schema::hasTable(
                    rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.r_prmsos_usrios'
                );

                $tieneOpcionesPorCargo = DB::table($schema . 'r_prmsos')
                    ->where('cdgo_prfle', $cdgoPerfl)
                    ->where('cdgo_crgo', $cdgoCargo)
                    ->exists();

                if ($hasExceptionsTable) {
                    $query = "
                        SELECT
                            p.cdgo_prmso,
                            p.cdgo_rle,
                            COALESCE(u.in_ver,  '1') AS in_ver,
                            COALESCE(u.in_crea, '1') AS in_crea,
                            COALESCE(u.in_edit, '1') AS in_edit,
                            COALESCE(u.in_elim, '1') AS in_elim
                        FROM {$schema}r_prmsos p
                        LEFT JOIN {$schema}r_prmsos_usrios u
                            ON  u.cdgo_rle   = p.cdgo_rle
                            AND u.cdgo_lgin  = :login
                            AND u.in_esta    = '1'
                        WHERE p.cdgo_prfle  = :perfil
                    ";

                    $bindings = [
                        'login'  => $cdgoLgin,
                        'perfil' => $cdgoPerfl,
                    ];

                    if ($tieneOpcionesPorCargo) {
                        $query .= " AND (p.cdgo_crgo IS NULL OR p.cdgo_crgo = :cargo)";
                        $bindings['cargo'] = $cdgoCargo;
                    } else {
                        $query .= " AND p.cdgo_crgo IS NULL";
                    }

                    $rows = DB::select($query, $bindings);
                } else {
                    $query = "
                        SELECT
                            p.cdgo_prmso,
                            p.cdgo_rle,
                            '1' AS in_ver,
                            '1' AS in_crea,
                            '1' AS in_edit,
                            '1' AS in_elim
                        FROM {$schema}r_prmsos p
                        WHERE p.cdgo_prfle  = :perfil
                    ";

                    $bindings = [
                        'perfil' => $cdgoPerfl,
                    ];

                    if ($tieneOpcionesPorCargo) {
                        $query .= " AND (p.cdgo_crgo IS NULL OR p.cdgo_crgo = :cargo)";
                        $bindings['cargo'] = $cdgoCargo;
                    } else {
                        $query .= " AND p.cdgo_crgo IS NULL";
                    }

                    $rows = DB::select($query, $bindings);
                }

                $permisos = [];
                foreach ($rows as $row) {
                    $permisos[$row->cdgo_rle] = [
                        'ver'  => (string) $row->in_ver  === '1',
                        'crea' => (string) $row->in_crea === '1',
                        'edit' => (string) $row->in_edit === '1',
                        'elim' => (string) $row->in_elim === '1',
                    ];
                }

                return $permisos;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function invalidarCache(string $cdgoLgin): void
    {
        Cache::forget(SessionKeys::permKey($cdgoLgin));
    }
}
