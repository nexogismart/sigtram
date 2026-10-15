<?php

declare(strict_types=1);

namespace App\Repositories\mod_sistema;

use App\Contracts\Repositories\mod_sistema\ContextoUsuarioAutenticadoRepositoryInterface;
use App\DTOs\Core\ContextoUsuarioAutenticadoDto;
use Illuminate\Support\Facades\DB;

/**
 * Repositorio de base de datos para la resolución y validación atómica
 * de asignaciones de login, funcionario, compañía, cargo y área.
 */
class ContextoUsuarioAutenticadoRepository implements ContextoUsuarioAutenticadoRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function validarYObtenerContexto(int $idLogin, int $idCompania, int $idCargo): ?ContextoUsuarioAutenticadoDto
    {
        if ($idLogin <= 0 || $idCompania <= 0 || $idCargo <= 0) {
            return null;
        }

        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        $sql = "
            SELECT 
                l.cdgo_lgin,
                l.cdgo_usrios,
                l.de_lgin,
                l.cdgo_prfle,
                c.cdgo_cmpnia,
                c.de_cmpnia,
                cr.cdgo_crgo,
                cr.de_crgo,
                a.cdgo_area,
                a.de_area
            FROM {$schema}p_lgin l
            JOIN {$schema}p_usrios u ON u.cdgo_usrios = l.cdgo_usrios
            JOIN {$schema}r_cmpnias_lgin rcl ON rcl.cdgo_lgin = l.cdgo_lgin AND rcl.cdgo_cmpnia = :idCompania AND rcl.in_esta = '1'
            JOIN {$schema}a_cmpnias c ON c.cdgo_cmpnia = rcl.cdgo_cmpnia AND c.in_esta = '1'
            JOIN {$schema}r_crgos_lgin rcr ON rcr.cdgo_lgin = l.cdgo_lgin AND rcr.cdgo_crgo = :idCargo AND rcr.in_esta = '1'
            JOIN {$schema}p_crgos cr ON cr.cdgo_crgo = rcr.cdgo_crgo AND cr.in_esta = '1'
            JOIN {$schema}a_areas a ON a.cdgo_area = cr.cdgo_area AND a.cdgo_cmpnia = c.cdgo_cmpnia AND a.in_esta = '1'
            WHERE l.cdgo_lgin = :idLogin
              AND l.in_esta = '1'
            LIMIT 1
        ";

        $rows = DB::select($sql, [
            'idLogin'    => $idLogin,
            'idCompania' => $idCompania,
            'idCargo'    => $idCargo,
        ]);

        if (empty($rows)) {
            return null;
        }

        $row = (object) $rows[0];

        $cargosAsignadosRows = DB::table($schema . 'r_crgos_lgin')
            ->where('cdgo_lgin', $idLogin)
            ->where('in_esta', '1')
            ->pluck('cdgo_crgo')
            ->map(fn ($val) => (int) $val)
            ->all();

        $idArea = (int) $row->cdgo_area;
        $esContabilidad = ($idArea === 21);

        return new ContextoUsuarioAutenticadoDto(
            idLogin: (int) $row->cdgo_lgin,
            idUsuario: (int) $row->cdgo_usrios,
            nombreLogin: (string) $row->de_lgin,
            idPerfil: (int) $row->cdgo_prfle,
            idCompania: (int) $row->cdgo_cmpnia,
            idCargo: (int) $row->cdgo_crgo,
            idArea: $idArea,
            cargosAsignados: $cargosAsignadosRows,
            permisos: [],
            roles: [(int) $row->cdgo_prfle],
            esContabilidad: $esContabilidad,
            nombreCompania: (string) $row->de_cmpnia,
            nombreCargo: (string) $row->de_crgo,
            nombreArea: (string) $row->de_area
        );
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCompaniasUsuario(int $idLogin): array
    {
        if ($idLogin <= 0) {
            return [];
        }

        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        return DB::table($schema . 'r_cmpnias_lgin as rcl')
            ->join($schema . 'a_cmpnias as c', 'c.cdgo_cmpnia', '=', 'rcl.cdgo_cmpnia')
            ->where('rcl.cdgo_lgin', $idLogin)
            ->where('rcl.in_esta', '1')
            ->where('c.in_esta', '1')
            ->orderBy('c.cdgo_cmpnia')
            ->select('c.cdgo_cmpnia as id', 'c.de_cmpnia as nombre')
            ->get()
            ->map(fn ($item) => ['id' => (int) $item->id, 'nombre' => (string) $item->nombre])
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCargosUsuario(int $idLogin, ?int $idCompania = null): array
    {
        if ($idLogin <= 0) {
            return [];
        }

        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        $query = DB::table($schema . 'r_crgos_lgin as rcr')
            ->join($schema . 'p_crgos as cr', 'cr.cdgo_crgo', '=', 'rcr.cdgo_crgo')
            ->join($schema . 'a_areas as a', 'a.cdgo_area', '=', 'cr.cdgo_area')
            ->where('rcr.cdgo_lgin', $idLogin)
            ->where('rcr.in_esta', '1')
            ->where('cr.in_esta', '1')
            ->where('a.in_esta', '1')
            ->orderBy('cr.cdgo_crgo')
            ->select(
                'cr.cdgo_crgo as id',
                'cr.de_crgo as nombre',
                'a.cdgo_area as id_area',
                'a.de_area as nombre_area',
                'a.cdgo_cmpnia as id_compania'
            );

        if ($idCompania !== null && $idCompania > 0) {
            $query->where('a.cdgo_cmpnia', $idCompania);
        }

        return $query->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nombre' => (string) $item->nombre,
                'id_area' => (int) $item->id_area,
                'nombre_area' => (string) $item->nombre_area,
                'id_compania' => (int) $item->id_compania,
            ])
            ->all();
    }
}
