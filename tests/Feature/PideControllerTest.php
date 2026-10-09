<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\PUsrrio;
use App\Models\UsrioNatural;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Limpiamos rate limiter
    RateLimiter::clear('pide:0');
    RateLimiter::clear('pide-ip:127.0.0.1');

    // Desactivamos SUNARP por defecto para aislar la prueba de identidad
    Config::set('pide.usar_sunarp', false);
    Config::set('pide.usar_oficial', true);
    Config::set('pide.usar_local', true);
    Config::set('pide.usar_decolecta', true);
    Config::set('pide.credentials_pool', [
        ['usuario' => '70068099', 'clave' => 'secret_pide']
    ]);

    // Creamos y autenticamos un usuario administrador
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('POST /mod_gene/pide/consultar obtiene DNI exitoso', function () {
    Http::fake([
        'https://ws2.pide.gob.pe/*' => Http::response([
            'consultarResponse' => [
                'return' => [
                    'coResultado' => '0000',
                    'datosPersona' => [
                        'prenombres' => 'ANDREA',
                        'apPrimer' => 'LOPEZ',
                        'apSegundo' => 'ROJAS',
                        'direccion' => 'AV. BRASIL 456'
                    ]
                ]
            ]
        ], 200)
    ]);

    $response = $this->postJson(route('pide.consultar'), [
        'documento' => '00888888',
        'tipo' => 'DNI'
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'dni' => '00888888',
                'nombres' => 'ANDREA',
                'apellido_paterno' => 'LOPEZ',
                'apellido_materno' => 'ROJAS'
            ]
        ]);
});

test('POST /mod_gene/pide/consultar falla ante validacion incorrecta', function () {
    $response = $this->postJson(route('pide.consultar'), [
        'documento' => '123', // Invalido
        'tipo' => 'OTRO' // Invalido
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['documento', 'tipo']);
});

test('POST /mod_gene/pide/buscar-registrar guarda nuevo ciudadano', function () {
    Http::fake([
        'https://ws2.pide.gob.pe/*' => Http::response([
            'consultarResponse' => [
                'return' => [
                    'coResultado' => '0000',
                    'datosPersona' => [
                        'prenombres' => 'JORGE',
                        'apPrimer' => 'HUAMAN',
                        'apSegundo' => 'SANCHEZ',
                        'direccion' => 'JR. LIMA 789'
                    ]
                ]
            ]
        ], 200)
    ]);

    $response = $this->postJson(route('pide.buscar-registrar'), [
        'documento' => '00555555',
        'tipo' => 'DNI'
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'dni' => '00555555',
                'nombres' => 'JORGE',
                'apellido_paterno' => 'HUAMAN',
                'apellido_materno' => 'SANCHEZ'
            ]
        ]);

    // Verificar que se guardó localmente
    $dbUser = PUsrrio::where('nu_docu', '00555555')->first();
    expect($dbUser)->not->toBeNull();
});

test('POST /mod_gene/pide/buscar-registrar detecta discrepancias si ya existe localmente', function () {
    // 1. Insertamos un registro local
    $localUser = PUsrrio::create([
        'nu_docu' => '00444444',
        'de_dire' => 'DIRECCION ANTIGUA',
        'in_tipo_pers' => 'N',
        'in_esta' => '1',
        'no_crto_sstma' => 'ANDRES DIAZ'
    ]);
    UsrioNatural::create([
        'cdgo_usrios' => $localUser->cdgo_usrios,
        'no_usrio' => 'ANDRES',
        'ap_pate' => 'DIAZ',
        'ap_mate' => 'ORTIZ',
        'cdgo_dcmntos_iden' => 1,
        'cdgo_estdo_cvil' => 1,
        'ts_usua_modi' => now(),
        'in_esta' => '1'
    ]);

    // 2. Simulamos que PIDE oficial devuelve otro nombre (discrepancia)
    Http::fake([
        'https://ws2.pide.gob.pe/*' => Http::response([
            'consultarResponse' => [
                'return' => [
                    'coResultado' => '0000',
                    'datosPersona' => [
                        'prenombres' => 'ANDRES ALBERTO', // Discrepa
                        'apPrimer' => 'DIAZ',
                        'apSegundo' => 'ORTIZ',
                        'direccion' => 'CALLE NUEVA 123'
                    ]
                ]
            ]
        ], 200)
    ]);

    $response = $this->postJson(route('pide.buscar-registrar'), [
        'documento' => '00444444',
        'tipo' => 'DNI'
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'discrepancia' => true,
            'data' => [
                'dni' => '00444444',
                'nombres' => 'ANDRES ALBERTO',
                'tiene_discrepancia' => true,
                'datos_locales' => [
                    'nombres' => 'ANDRES',
                    'direccion' => 'DIRECCION ANTIGUA'
                ]
            ]
        ]);
});
