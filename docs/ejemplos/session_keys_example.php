<?php
/**
 * EJEMPLO INTERACTIVO: SessionKeys + Permisos con Tablas Propias (sin Spatie)
 * ============================================================================
 * Este archivo NO se incluye en producción. Es una guía de referencia.
 * Regla: CODE-06, ARCH-11, ARCH-12 en GEMINI.md
 * Ruta de la clase real: app/Support/SessionKeys.php
 */

// ─────────────────────────────────────────────────────────────────────────────
// 1. USANDO SessionKeys — Antes vs. Después
// ─────────────────────────────────────────────────────────────────────────────

// ❌ ANTES (prohibido — strings literales dispersos):
$cargo   = session('usIdsCargo');
$empresa = session('usComp');
$login   = session('usCdgoLgin');

// ✅ DESPUÉS (correcto — constantes centralizadas):
use App\Support\SessionKeys;

$cargo   = session(SessionKeys::USER_CARGO);
$empresa = session(SessionKeys::USER_COMPANY);
$login   = session(SessionKeys::USER_LOGIN);


// ─────────────────────────────────────────────────────────────────────────────
// 2. PERMISOS SIN SPATIE — Usando tablas propias (r_prmsos + r_prmsos_usrios)
// ─────────────────────────────────────────────────────────────────────────────
//
// ¿Por qué NO usar Spatie Laravel Permission?
//   - Spatie crea sus propias tablas: roles, permissions, model_has_roles, etc.
//   - Nuestro sistema ya tiene: sistema.r_prmsos y sistema.r_prmsos_usrios
//   - DB-04 prohíbe ejecutar migraciones automáticas (php artisan migrate)
//   - Spatie es incompatible con la BD existente sin un bridge complejo
//
// ✅ Solución: Gates + Policies de Laravel con nuestras tablas propias
//    (Laravel nativo, cero dependencias extras, cero migraciones)

// ─────────────────────────────────────────────────────────────────────────────
// 2a. Repository de Permisos (app/Repositories/mod_sistema/PermisosRepository.php)
// ─────────────────────────────────────────────────────────────────────────────
/*
<?php
declare(strict_types=1);

namespace App\Repositories\mod_sistema;

use App\Contracts\Repositories\PermisosRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Support\SessionKeys;

class PermisosRepository implements PermisosRepositoryInterface
{
    public function obtenerPermisosUsuario(string $cdgoLgin, int $cdgoCargo, int $cdgoPerfl): array
    {
        return Cache::remember(
            SessionKeys::permKey($cdgoLgin),
            now()->addHours(8),
            function () use ($cdgoLgin, $cdgoCargo, $cdgoPerfl) {

                // Query unificada con COALESCE — una sola vuelta a la BD
                // (Ver doc/sql/roles.sql para el DDL completo)
                $rows = DB::select("
                    SELECT
                        p.cdgo_prmso,
                        p.cdgo_rle,                          -- código del menú/módulo
                        COALESCE(u.in_ver,  '1') AS in_ver,
                        COALESCE(u.in_crea, '1') AS in_crea,
                        COALESCE(u.in_edit, '1') AS in_edit,
                        COALESCE(u.in_elim, '1') AS in_elim
                    FROM sistema.r_prmsos p
                    LEFT JOIN sistema.r_prmsos_usrios u
                        ON  u.cdgo_prmso = p.cdgo_prmso
                        AND u.cdgo_lgin  = :login
                        AND u.in_esta    = '1'
                    WHERE p.cdgo_crgo   = :cargo
                      AND p.cdgo_prfle  = :perfil
                      AND p.in_esta     = '1'
                ", [
                    'login'  => $cdgoLgin,
                    'cargo'  => $cdgoCargo,
                    'perfil' => $cdgoPerfl,
                ]);

                // Indexar por código de módulo para búsqueda O(1)
                $permisos = [];
                foreach ($rows as $row) {
                    $permisos[$row->cdgo_rle] = [
                        'ver'  => $row->in_ver  === '1',
                        'crea' => $row->in_crea === '1',
                        'edit' => $row->in_edit === '1',
                        'elim' => $row->in_elim === '1',
                    ];
                }

                return $permisos;
            }
        );
    }

    public function invalidarCache(string $cdgoLgin): void
    {
        Cache::forget(SessionKeys::permKey($cdgoLgin));
    }
}
*/

// ─────────────────────────────────────────────────────────────────────────────
// 2b. Registrar en AuthServiceProvider (app/Providers/AuthServiceProvider.php)
// ─────────────────────────────────────────────────────────────────────────────
/*
use Illuminate\Support\Facades\Gate;
use App\Support\SessionKeys;

// En el método boot():
Gate::before(function ($user, $ability) {
    // Administradores de TI tienen acceso total (bypass de todos los gates)
    if ($user->esAdminTI()) {
        return true;
    }
});

// Definir gates por acción
Gate::define('ver',   fn($user, $modulo) => $user->puedeEn($modulo, 'ver'));
Gate::define('crear', fn($user, $modulo) => $user->puedeEn($modulo, 'crea'));
Gate::define('editar',fn($user, $modulo) => $user->puedeEn($modulo, 'edit'));
Gate::define('borrar',fn($user, $modulo) => $user->puedeEn($modulo, 'elim'));
*/

// ─────────────────────────────────────────────────────────────────────────────
// 2c. Método puedeEn() en el Modelo User (app/Models/User.php)
// ─────────────────────────────────────────────────────────────────────────────
/*
// En app/Models/User.php:
public function puedeEn(string $modulo, string $accion): bool
{
    $permisos = app(PermisosRepositoryInterface::class)
        ->obtenerPermisosUsuario(
            $this->cdgo_lgin,
            session(SessionKeys::USER_CARGO),
            $this->cdgo_prfle
        );

    return $permisos[$modulo][$accion] ?? false;
}
*/

// ─────────────────────────────────────────────────────────────────────────────
// 2d. USO en Controllers y Vistas
// ─────────────────────────────────────────────────────────────────────────────

// En un Controller:
/*
public function editar(Request $request, int $id): JsonResponse
{
    $this->authorize('editar', 'mod_gm_expedientes');  // Gate nativo de Laravel

    // ... lógica de edición
}
*/

// En una vista Blade:
/*
@can('crear', 'mod_gm_expedientes')
    <button id="btn-nuevo-expediente" class="btn-primary">Nuevo Expediente</button>
@endcan

@can('borrar', 'mod_gm_expedientes')
    <button id="btn-eliminar" class="btn-danger">Eliminar</button>
@endcan
*/


// ─────────────────────────────────────────────────────────────────────────────
// 3. ESTRUCTURA DE CARPETAS COMPLETA (referencia para el proyecto)
// ─────────────────────────────────────────────────────────────────────────────
/*
app/
├── Http/
│   ├── Controllers/
│   │   └── mod_<area>/
│   │       └── <Modulo>Controller.php       ← Thin controller
│   └── Requests/
│       └── mod_<area>/
│           └── <Modulo>Request.php          ← Validación de inputs
│
├── Services/
│   ├── Core/                                ← Núcleos transversales
│   │   ├── AuthCore/
│   │   │   └── AuthCoreService.php
│   │   ├── PideCore/
│   │   │   ├── PideCoreService.php
│   │   │   ├── PideOficialAdapter.php       ← Implementa PideInterface
│   │   │   ├── PideDecolectaAdapter.php     ← Implementa PideInterface
│   │   │   └── MockPideAdapter.php          ← Para tests
│   │   ├── NotificationCore/
│   │   ├── AuditCore/
│   │   ├── AdjuntosCore/
│   │   │   └── GestorAdjuntos.php
│   │   ├── DocumentGeneratorCore/
│   │   │   └── DocumentGeneratorService.php
│   │   └── FirmaCore/
│   │       ├── FirmaInterface.php            ← Puerto
│   │       ├── FirmaLocalAdapter.php
│   │       ├── FirmaNubeAdapter.php
│   │       └── FirmaMockAdapter.php
│   │
│   └── mod_<area>/                          ← Business services por módulo
│       └── <Modulo>Service.php
│
├── Repositories/                            ← ARCH-14: Obligatorio para consultas complejas
│   └── mod_<area>/
│       └── <Modulo>Repository.php
│
├── Contracts/                               ← Interfaces (Ports)
│   ├── Core/
│   │   ├── PideInterface.php
│   │   ├── FirmaInterface.php
│   │   └── NotificationInterface.php
│   └── Repositories/
│       └── <Modulo>RepositoryInterface.php
│
├── DTOs/                                    ← Data Transfer Objects
│   └── mod_<area>/
│       └── <Modulo>DTO.php
│
├── Models/
│   └── mod_<area>/
│       └── <Modulo>.php                     ← $table = 'esquema.tabla'
│
├── Support/
│   └── SessionKeys.php                      ← CODE-06: Constantes de sesión
│
├── Events/
├── Listeners/
├── Providers/
│   └── AppServiceProvider.php               ← Binding de interfaces a implementaciones
└── Exceptions/
    └── Handler.php

resources/views/
└── mod_<area>/
    └── <modulo>/
        ├── index.blade.php
        ├── create.blade.php
        └── edit.blade.php

tests/
├── Unit/
│   └── Services/
│       └── mod_<area>/
│           └── <Modulo>ServiceTest.php      ← TEST-02
└── Feature/
    └── mod_<area>/
        └── <Modulo>Test.php                 ← TEST-03
*/
