<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Contracts\Services\SecurityCoreInterface;
use App\Contracts\Repositories\PermisosRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class SecurityCore
 *
 * Servicio núcleo centralizado para la orquestación y control de acceso,
 * roles, cargos y permisos en SIGTRAM3.
 */
class SecurityCore implements SecurityCoreInterface
{
    /**
     * @var PermisosRepositoryInterface
     */
    protected PermisosRepositoryInterface $permisosRepo;

    /**
     * SecurityCore constructor.
     *
     * @param PermisosRepositoryInterface $permisosRepo
     */
    public function __construct(PermisosRepositoryInterface $permisosRepo)
    {
        $this->permisosRepo = $permisosRepo;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCargosUsuario(int $loginId): Collection
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        return DB::table($schema . 'r_crgos_lgin as r')
            ->join($schema . 'p_crgos as c', 'c.cdgo_crgo', '=', 'r.cdgo_crgo')
            ->leftJoin($schema . 'a_areas as a', 'a.cdgo_area', '=', 'c.cdgo_area')
            ->where('r.cdgo_lgin', $loginId)
            ->select('c.cdgo_crgo', 'c.de_crgo', 'a.de_area', 'a.dc_area', 'c.cdgo_area')
            ->orderBy('c.cdgo_crgo')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function tienePermiso(int $loginId, string $moduloORuta, ?int $cargoId = null, string $accion = 'ver'): bool
    {
        $user = User::find($loginId);
        if (!$user) {
            return false;
        }

        // Si el usuario es Administrador de TI, tiene bypass total (Reglas de Seguridad)
        if (method_exists($user, 'esAdminTI') && $user->esAdminTI()) {
            return true;
        }

        // Bypass manual para administradores TI usando el perfil 1 (por defecto en sigtram legacy)
        if ((int) $user->cdgo_prfle === 1) {
            return true;
        }

        if ($cargoId === null) {
            $cargoId = (int) session('sigtram_cargo_id', 0);
        }

        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        // Determinar si es una ruta (contiene slashes o es mod_*) o un identificador de módulo directo
        $rolesToCheck = [];
        if (Str::contains($moduloORuta, '/') || Str::startsWith($moduloORuta, 'mod_')) {
            $rutaLimpia = $this->limpiarRutaModulo($moduloORuta);

            // Acepta en BD: sin extensión, .blade.php o .php (legado)
            $rolesToCheck = DB::table($schema . 'a_rles')
                ->whereIn('de_dreccon', $this->variantesRutaModulo($rutaLimpia))
                ->pluck('cdgo_rle')
                ->toArray();
        } else {
            $rolesToCheck = [$moduloORuta];
        }

        if (empty($rolesToCheck)) {
            return false;
        }

        $permisos = $this->permisosRepo->obtenerPermisosUsuario(
            (string) $user->cdgo_lgin,
            $cargoId,
            (int) $user->cdgo_prfle
        );

        foreach ($rolesToCheck as $cdgoRle) {
            if (isset($permisos[$cdgoRle])) {
                $keyAccion = match ($accion) {
                    'crear', 'crea' => 'crea',
                    'editar', 'edit' => 'edit',
                    'borrar', 'elim' => 'elim',
                    default => 'ver',
                };

                if ($permisos[$cdgoRle][$keyAccion] ?? false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerMenuUsuario(int $loginId, int $cargoId): array
    {
        $user = User::find($loginId);
        if (!$user) {
            return [];
        }

        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        $permisos = $this->permisosRepo->obtenerPermisosUsuario(
            (string) $user->cdgo_lgin,
            $cargoId,
            (int) $user->cdgo_prfle
        );

        if (empty($permisos)) {
            return [];
        }

        $todosLosRoles = DB::table($schema . 'a_rles')
            ->where('in_esta', '1')
            ->orderBy('dc_rle')
            ->get();

        $rolesPermitidos = $todosLosRoles->filter(function ($rol) use ($permisos) {
            return $permisos[$rol->cdgo_rle]['ver'] ?? false;
        });

        $padres = $rolesPermitidos->filter(fn($rol) => $rol->cdgo_sub_rle === null || $rol->cdgo_sub_rle === '' || (int) $rol->cdgo_sub_rle === 0);
        $hijos = $rolesPermitidos->filter(fn($rol) => $rol->cdgo_sub_rle !== null && $rol->cdgo_sub_rle !== '' && (int) $rol->cdgo_sub_rle !== 0)
            ->groupBy(fn($rol) => (int) $rol->cdgo_sub_rle);

        $menuItems = [];
        foreach ($padres as $padre) {
            $children = [];
            $padreId = (int) $padre->cdgo_rle;
            if ($hijos->has($padreId)) {
                foreach ($hijos->get($padreId) as $hijo) {
                    $children[] = [
                        'label' => $hijo->dc_rle,
                        'url' => $this->normalizarDireccion($hijo->de_dreccon),
                        'popup' => (string) $hijo->in_popup === '1',
                    ];
                }
            }

            $menuItems[] = [
                'label' => $padre->dc_rle,
                'url' => $this->normalizarDireccion($padre->de_dreccon),
                'popup' => (string) $padre->in_popup === '1',
                'children' => $children,
            ];
        }

        return $menuItems;
    }

    /**
     * {@inheritdoc}
     */
    public function limpiarCachePermisos(string $cdgoLgin): void
    {
        $this->permisosRepo->invalidarCache($cdgoLgin);
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerOpcionPorRuta(string $ruta): ?object
    {
        $schema = rtrim((string) config('auth.sigtram_sistema_schema', 'sistema'), '.') . '.';

        return DB::table($schema . 'a_rles')
            ->where('in_esta', '1')
            ->whereIn('de_dreccon', $this->variantesRutaModulo($ruta))
            ->orderBy('cdgo_rle')
            ->first();
    }

    /**
     * Normaliza la dirección de la ruta del menú.
     *
     * @param string|null $ruta
     * @return string
     */
    private function normalizarDireccion(?string $ruta): string
    {
        if (!is_string($ruta) || trim($ruta) === '') {
            return '#';
        }

        $ruta = trim($ruta);
        if (Str::startsWith($ruta, ['http://', 'https://', '/', '#'])) {
            return $ruta;
        }

        return '/' . ltrim($ruta, '/');
    }

    /**
     * Quita .blade.php / .php finales y asegura prefijo /.
     */
    private function limpiarRutaModulo(string $ruta): string
    {
        $ruta = trim($ruta);
        $ruta = preg_replace('/\.blade\.php$/i', '', $ruta) ?? $ruta;
        $ruta = preg_replace('/\.php$/i', '', $ruta) ?? $ruta;

        return '/' . ltrim($ruta, '/');
    }

    /**
     * Variantes de de_dreccon aceptadas en BD para la misma opción de menú.
     *
     * @return list<string>
     */
    private function variantesRutaModulo(string $rutaLimpia): array
    {
        $base = $this->limpiarRutaModulo($rutaLimpia);

        $sinSlash = ltrim($base, '/');

        return array_values(array_unique([
            $base,
            $base . '.blade.php',
            $base . '.php',
            $sinSlash,
            $sinSlash . '.blade.php',
            $sinSlash . '.php',
        ]));
    }
}
