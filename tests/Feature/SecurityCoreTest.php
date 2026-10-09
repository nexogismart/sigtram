<?php

declare(strict_types=1);

use App\Contracts\Services\SecurityCoreInterface;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use App\Support\SessionKeys;
use Illuminate\Database\QueryException;

beforeEach(function () {
    // Obtener un usuario activo
    $this->user = User::where('in_esta', '1')->first();
    $this->securityCore = app(SecurityCoreInterface::class);
});

test('puede obtener cargos del usuario', function () {
    if (!$this->user) {
        $this->markTestSkipped('No hay usuarios activos en la BD.');
    }

    $cargos = $this->securityCore->obtenerCargosUsuario((int) $this->user->cdgo_lgin);

    expect($cargos)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('puede verificar permisos del usuario', function () {
    if (!$this->user) {
        $this->markTestSkipped('No hay usuarios activos en la BD.');
    }

    try {
        $tienePermiso = $this->securityCore->tienePermiso(
            (int) $this->user->cdgo_lgin,
            '/ruta-ficticia-no-existente',
            9999
        );

        expect($tienePermiso)->toBeBool();
    } catch (QueryException $e) {
        if (str_contains($e->getMessage(), 'r_prmsos_usrios')) {
            $this->markTestSkipped('La tabla de excepciones r_prmsos_usrios no existe en la base de datos.');
        }
        throw $e;
    }
});

test('puede obtener menu del usuario', function () {
    if (!$this->user) {
        $this->markTestSkipped('No hay usuarios activos en la BD.');
    }

    $cargos = $this->securityCore->obtenerCargosUsuario((int) $this->user->cdgo_lgin);
    if ($cargos->isEmpty()) {
        $this->markTestSkipped('El usuario no tiene cargos asignados.');
    }

    $cargoId = $cargos->first()->cdgo_crgo;

    try {
        $menu = $this->securityCore->obtenerMenuUsuario((int) $this->user->cdgo_lgin, (int) $cargoId);
        expect($menu)->toBeArray();
    } catch (QueryException $e) {
        if (str_contains($e->getMessage(), 'r_prmsos_usrios')) {
            $this->markTestSkipped('La tabla de excepciones r_prmsos_usrios no existe en la base de datos.');
        }
        throw $e;
    }
});

test('puede limpiar cache de permisos', function () {
    if (!$this->user) {
        $this->markTestSkipped('No hay usuarios activos en la BD.');
    }

    $cacheKey = SessionKeys::permKey((string) $this->user->de_lgin);
    Cache::put($cacheKey, ['test' => true], 60);

    expect(Cache::has($cacheKey))->toBeTrue();

    $this->securityCore->limpiarCachePermisos((string) $this->user->de_lgin);

    expect(Cache::has($cacheKey))->toBeFalse();
});
