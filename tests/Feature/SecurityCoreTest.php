<?php

declare(strict_types=1);

use App\Contracts\Services\SecurityCoreInterface;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use App\Support\SessionKeys;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['auth.sigtram_sistema_schema' => 'sistema']);
    Cache::flush();
    DB::statement('CREATE TABLE sistema.r_prmsos (cdgo_prmso INTEGER PRIMARY KEY, cdgo_prfle INTEGER, cdgo_crgo INTEGER, cdgo_rle INTEGER)');
    DB::statement("CREATE TABLE sistema.r_prmsos_usrios (cdgo_lgin INTEGER, cdgo_rle INTEGER, in_esta TEXT, in_ver TEXT, in_crea TEXT, in_edit TEXT, in_elim TEXT)");
    DB::table('sistema.p_lgin')->insert(['cdgo_lgin'=>90001, 'de_lgin'=>'test-security', 'cdgo_prfle'=>4]);
    DB::table('sistema.a_areas')->insert(['cdgo_area'=>910, 'de_area'=>'Área de prueba', 'dc_area'=>'TEST', 'cdgo_cmpnia'=>2]);
    DB::table('sistema.p_crgos')->insert(['cdgo_crgo'=>920, 'cdgo_area'=>910, 'de_crgo'=>'Asistente de prueba']);
    DB::table('sistema.r_crgos_lgin')->insert(['cdgo_lgin'=>90001, 'cdgo_crgo'=>920]);
    DB::table('sistema.a_rles')->insert([
        ['cdgo_rle'=>930, 'dc_rle'=>'Trámites', 'de_dreccon'=>'', 'cdgo_sub_rle'=>null],
        ['cdgo_rle'=>931, 'dc_rle'=>'Bandeja', 'de_dreccon'=>'mod_gm/mod_tram_exte/segi_expedi', 'cdgo_sub_rle'=>930],
        ['cdgo_rle'=>932, 'dc_rle'=>'Sin permiso', 'de_dreccon'=>'test/sin_permiso', 'cdgo_sub_rle'=>930],
    ]);
    DB::table('sistema.r_prmsos')->insert([
        ['cdgo_prmso'=>1, 'cdgo_prfle'=>4, 'cdgo_crgo'=>920, 'cdgo_rle'=>930],
        ['cdgo_prmso'=>2, 'cdgo_prfle'=>4, 'cdgo_crgo'=>920, 'cdgo_rle'=>931],
    ]);
    $this->user = User::findOrFail(90001);
    $this->securityCore = app(SecurityCoreInterface::class);
});

test('puede obtener cargos del usuario', function () {
    $cargos = $this->securityCore->obtenerCargosUsuario((int) $this->user->cdgo_lgin);

    expect($cargos)->toHaveCount(1);
    expect((int) $cargos->first()->cdgo_crgo)->toBe(920);
});

test('puede verificar permisos del usuario', function () {
    expect($this->securityCore->tienePermiso(90001, 'mod_gm/mod_tram_exte/segi_expedi', 920))->toBeTrue();
    expect($this->securityCore->tienePermiso(90001, 'test/sin_permiso', 920))->toBeFalse();
});

test('puede obtener menu del usuario', function () {
    $menu = $this->securityCore->obtenerMenuUsuario(90001, 920);
    expect($menu)->toHaveCount(1);
    expect($menu[0]['id'])->toBe(930);
    expect($menu[0]['children'])->toHaveCount(1);
    expect($menu[0]['children'][0]['id'])->toBe(931);
});

test('puede limpiar cache de permisos', function () {
    $cacheKey = SessionKeys::permKey((string) $this->user->cdgo_lgin);
    Cache::put($cacheKey, ['test' => true], 60);

    expect(Cache::has($cacheKey))->toBeTrue();

    $this->securityCore->limpiarCachePermisos((string) $this->user->cdgo_lgin);

    expect(Cache::has($cacheKey))->toBeFalse();
});


test('cambiar cargo no reutiliza permisos del cargo anterior', function () {
    DB::table('sistema.r_prmsos')->insert(['cdgo_prmso'=>3, 'cdgo_prfle'=>4, 'cdgo_crgo'=>921, 'cdgo_rle'=>932]);
    expect($this->securityCore->tienePermiso(90001, 'mod_gm/mod_tram_exte/segi_expedi', 920))->toBeTrue();
    expect($this->securityCore->tienePermiso(90001, 'mod_gm/mod_tram_exte/segi_expedi', 921))->toBeFalse();
    expect($this->securityCore->tienePermiso(90001, 'test/sin_permiso', 921))->toBeTrue();
    expect($this->securityCore->tienePermiso(90001, 'test/sin_permiso', 920))->toBeFalse();
    $this->securityCore->limpiarCachePermisos('90001');
    DB::table('sistema.r_prmsos')->where('cdgo_rle', 932)->delete();
    expect($this->securityCore->tienePermiso(90001, 'test/sin_permiso', 921))->toBeFalse();
});
