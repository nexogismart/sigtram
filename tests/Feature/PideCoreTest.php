<?php

declare(strict_types=1);

use App\Contracts\Services\PideCoreInterface;
use App\DTOs\PersonaDto;
use App\DTOs\EmpresaDto;
use App\Models\PUsrrio;
use App\Models\UsrioNatural;
use App\Models\UsrioJuridico;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Limpiamos el rate limit para cada test
    RateLimiter::clear('pide:0');
    RateLimiter::clear('pide-ip:127.0.0.1');

    // Configuramos los switches activos y credenciales de prueba
    Config::set('pide.usar_oficial', true);
    Config::set('pide.usar_local', true);
    Config::set('pide.usar_decolecta', true);
    Config::set('pide.timeout', 2);
    Config::set('pide.credentials_pool', [
        ['usuario' => '70068099', 'clave' => 'secret_pide']
    ]);
    Config::set('pide.ruc_institucional', '20131379502');
    Config::set('pide.decolecta_tokens', ['token_1', 'token_2']);
    Config::set('pide.usar_sunarp', false);

    $this->pideCore = app(PideCoreInterface::class);
});

test('consultarDetalle obtiene persona natural desde PIDE oficial y no la persiste en BD local', function () {
    Http::fake([
        'https://ws2.pide.gob.pe/*' => Http::response([
            'consultarResponse' => [
                'return' => [
                    'coResultado' => '0000',
                    'datosPersona' => [
                        'prenombres' => 'MARIA',
                        'apPrimer' => 'QUISPE',
                        'apSegundo' => 'MAMANI',
                        'direccion' => 'CALLE REAL 100',
                    ]
                ]
            ]
        ], 200)
    ]);

    $res = $this->pideCore->consultarDetalle('00777777', 'DNI');

    expect($res)->toBeInstanceOf(PersonaDto::class)
        ->and($res->dni)->toBe('00777777')
        ->and($res->nombres)->toBe('MARIA')
        ->and($res->apellidoPaterno)->toBe('QUISPE')
        ->and($res->apellidoMaterno)->toBe('MAMANI')
        ->and($res->nombreCompleto)->toBe('MARIA QUISPE MAMANI')
        ->and($res->direccion)->toBe('CALLE REAL 100')
        ->and($res->fuente)->toBe('PIDE')
        ->and($res->cdgoUsrios)->toBeNull(); // Importante: No debe estar guardada en local

    // Validar que no se guardó en la base de datos municipal
    $dbUser = PUsrrio::where('nu_docu', '00777777')->first();
    expect($dbUser)->toBeNull();
});

test('buscarYRegistrarParaTramite registra a un ciudadano nuevo (UPSERT) en BD local y le asigna cdgo_usrio', function () {
    Http::fake([
        'https://ws2.pide.gob.pe/*' => Http::response([
            'consultarResponse' => [
                'return' => [
                    'coResultado' => '0000',
                    'datosPersona' => [
                        'prenombres' => 'CARLOS',
                        'apPrimer' => 'GARCIA',
                        'apSegundo' => 'RODRIGUEZ',
                        'direccion' => 'AV. EL SOL 200',
                    ]
                ]
            ]
        ], 200)
    ]);

    $res = $this->pideCore->buscarYRegistrarParaTramite('00888888', 'DNI');

    expect($res)->toBeInstanceOf(PersonaDto::class)
        ->and($res->dni)->toBe('00888888')
        ->and($res->cdgoUsrios)->not->toBeNull(); // Debe tener ID local generado

    // Verificar persistencia física en la base de datos municipal
    $dbUser = PUsrrio::where('nu_docu', '00888888')->first();
    expect($dbUser)->not->toBeNull()
        ->and($dbUser->in_tipo_pers)->toBe('N')
        ->and($dbUser->de_dire)->toBe('AV. EL SOL 200');

    $dbNatural = UsrioNatural::where('cdgo_usrios', $dbUser->cdgo_usrios)->first();
    expect($dbNatural)->not->toBeNull()
        ->and($dbNatural->no_usrio)->toBe('CARLOS')
        ->and($dbNatural->ap_pate)->toBe('GARCIA')
        ->and($dbNatural->ap_mate)->toBe('RODRIGUEZ');
});

test('buscarYRegistrarParaTramite detecta discrepancias de datos existentes y no sobreescribe local sin aprobacion', function () {
    // 1. Insertamos un ciudadano local pre-existente
    $localUser = PUsrrio::create([
        'nu_docu' => '00999999',
        'de_dire' => 'DIRECCION ANTIGUA',
        'in_tipo_pers' => 'N',
        'in_esta' => '1',
        'no_crto_sstma' => 'JUAN PEREZ GARCIA'
    ]);
    UsrioNatural::create([
        'cdgo_usrios' => $localUser->cdgo_usrios,
        'no_usrio' => 'JUAN',
        'ap_pate' => 'PEREZ',
        'ap_mate' => 'GARCIA',
        'cdgo_dcmntos_iden' => 1,
        'cdgo_estdo_cvil' => 1
    ]);

    // 2. Simulamos que RENIEC retorna un dato diferente (discrepante: tiene segundo nombre y otra direccion)
    Http::fake([
        'https://ws2.pide.gob.pe/*' => Http::response([
            'consultarResponse' => [
                'return' => [
                    'coResultado' => '0000',
                    'datosPersona' => [
                        'prenombres' => 'JUAN CARLOS', // Discrepa de "JUAN"
                        'apPrimer' => 'PEREZ',
                        'apSegundo' => 'GARCIA',
                        'direccion' => 'AV. JAVIER PRADO 500', // Discrepa de "DIRECCION ANTIGUA"
                    ]
                ]
            ]
        ], 200)
    ]);

    $res = $this->pideCore->buscarYRegistrarParaTramite('00999999', 'DNI');

    expect($res->tieneDiscrepancia)->toBeTrue()
        ->and($res->cdgoUsrios)->toBe($localUser->cdgo_usrios)
        ->and($res->nombres)->toBe('JUAN CARLOS')
        ->and($res->datosLocales)->toBeArray()
        ->and($res->datosLocales['nombres'])->toBe('JUAN')
        ->and($res->datosLocales['direccion'])->toBe('DIRECCION ANTIGUA');

    // Comprobar que en base de datos local sigue estando el dato original (no se chancó automáticamente)
    $dbNatural = UsrioNatural::where('cdgo_usrios', $localUser->cdgo_usrios)->first();
    expect($dbNatural->no_usrio)->toBe('JUAN');
});

test('DecolectaProvider rota de token ante error 429 de cuota superada', function () {
    // Apagamos PIDE para ir directo al fallback de Decolecta
    Config::set('pide.usar_oficial', false);

    // Primer token token_1 devuelve 429 (Cuota excedida). Segundo token_2 responde con éxito.
    Http::fake([
        'https://api.decolecta.com/v1/reniec/dni?numero=00555555' => function ($request) {
            $auth = $request->header('Authorization')[0] ?? '';
            if (str_contains($auth, 'token_1')) {
                return Http::response(['error' => 'Rate limit exceeded'], 429);
            }
            return Http::response([
                'nombres' => 'LUIS',
                'apellido_paterno' => 'ROJAS',
                'apellido_materno' => 'CRUZ',
                'direccion' => 'CALLE LIMA 300'
            ], 200);
        }
    ]);

    $res = $this->pideCore->buscarYRegistrarParaTramite('00555555', 'DNI');

    expect($res)->not->toBeNull()
        ->and($res->nombres)->toBe('LUIS')
        ->and($res->fuente)->toBe('API DECOLECTA')
        ->and($res->fuenteDetalle)->toBe('token_2...'); // Se debió usar token_2 tras fallar token_1
});

test('cuando fallan todos los proveedores externos y no existe localmente retorna null habilitando entrada manual', function () {
    // Forzamos caída de red de PIDE y Decolecta
    Http::fake([
        'https://ws2.pide.gob.pe/*' => Http::response(null, 500),
        'https://api.decolecta.com/*' => Http::response(null, 500)
    ]);

    $res = $this->pideCore->buscarYRegistrarParaTramite('00444444', 'DNI');

    expect($res)->toBeNull()
        ->and($this->pideCore->obtenerUltimoAviso())->toBe('EXTERNAL_SERVICE_UNAVAILABLE')
        ->and($this->pideCore->obtenerMensajeError('DNI'))->toContain('fuentes externas no responden');
});

test('Rate Limiter restringe consultas a mas de 30 por minuto', function () {
    Http::fake([
        'https://ws2.pide.gob.pe/*' => Http::response([
            'consultarResponse' => [
                'return' => [
                    'coResultado' => '0000',
                    'datosPersona' => [
                        'prenombres' => 'TEST',
                        'apPrimer' => 'TEST',
                        'apSegundo' => 'TEST',
                    ]
                ]
            ]
        ], 200)
    ]);

    // Ejecutamos 30 consultas exitosas
    for ($i = 0; $i < 30; $i++) {
        $this->pideCore->consultarDetalle('1000000' . $i, 'DNI');
    }

    // La consulta 31 debe arrojar excepción por Rate Limit
    expect(fn () => $this->pideCore->consultarDetalle('20000000', 'DNI'))
        ->toThrow(RuntimeException::class, 'Excede el límite de seguridad de consultas');
});

test('buscarYRegistrarParaTramite registra una empresa nueva (RUC) usando Decolecta y la persiste en BD local', function () {
    // Apagamos PIDE oficial para ir directo al mock de Decolecta
    Config::set('pide.usar_oficial', false);

    Http::fake([
        'https://api.decolecta.com/v1/sunat/ruc?numero=20123456789' => Http::response([
            'razon_social' => 'EMPRESA TEST SAC',
            'direccion' => 'CALLE JAVIER PRADO 123',
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO'
        ], 200)
    ]);

    $res = $this->pideCore->buscarYRegistrarParaTramite('20123456789', 'RUC');

    expect($res)->toBeInstanceOf(EmpresaDto::class)
        ->and($res->ruc)->toBe('20123456789')
        ->and($res->razonSocial)->toBe('EMPRESA TEST SAC')
        ->and($res->cdgoUsrios)->not->toBeNull();

    // Verificar persistencia física en la base de datos municipal
    $dbUser = PUsrrio::where('nu_docu', '20123456789')->first();
    expect($dbUser)->not->toBeNull()
        ->and($dbUser->in_tipo_pers)->toBe('J')
        ->and($dbUser->de_dire)->toBe('CALLE JAVIER PRADO 123');

    $dbJuridico = UsrioJuridico::where('cdgo_usrios', $dbUser->cdgo_usrios)->first();
    expect($dbJuridico)->not->toBeNull()
        ->and($dbJuridico->rz_soci)->toBe('EMPRESA TEST SAC');
});

test('buscarYRegistrarParaTramite con RUC detecta discrepancias de datos existentes y no sobreescribe local sin aprobacion', function () {
    // 1. Insertamos una empresa local pre-existente
    $localUser = PUsrrio::create([
        'nu_docu' => '20999999999',
        'de_dire' => 'DIRECCION ANTIGUA EMPRESA',
        'in_tipo_pers' => 'J',
        'in_esta' => '1',
        'no_crto_sstma' => 'EMPRESA ANTIGUA S.A.'
    ]);
    UsrioJuridico::create([
        'cdgo_usrios' => $localUser->cdgo_usrios,
        'rz_soci' => 'EMPRESA ANTIGUA S.A.',
        'ts_usua_modi' => now(),
        'in_esta' => '1'
    ]);

    // 2. Simulamos que SUNAT/Decolecta retorna un dato diferente (discrepante)
    Config::set('pide.usar_oficial', false);
    Http::fake([
        'https://api.decolecta.com/v1/sunat/ruc?numero=20999999999' => Http::response([
            'razon_social' => 'EMPRESA NUEVA S.A.', // Discrepa
            'direccion' => 'CALLE NUEVA 456', // Discrepa
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO'
        ], 200)
    ]);

    $res = $this->pideCore->buscarYRegistrarParaTramite('20999999999', 'RUC', ['Decolecta']);

    expect($res->tieneDiscrepancia)->toBeTrue()
        ->and($res->cdgoUsrios)->toBe($localUser->cdgo_usrios)
        ->and($res->razonSocial)->toBe('EMPRESA NUEVA S.A.')
        ->and($res->datosLocales)->toBeArray()
        ->and($res->datosLocales['razon_social'])->toBe('EMPRESA ANTIGUA S.A.')
        ->and($res->datosLocales['direccion'])->toBe('DIRECCION ANTIGUA EMPRESA');

    // Comprobar que en base de datos local sigue estando el dato original (no se chancó automáticamente)
    $dbJuridico = UsrioJuridico::where('cdgo_usrios', $localUser->cdgo_usrios)->first();
    expect($dbJuridico->rz_soci)->toBe('EMPRESA ANTIGUA S.A.');
});

test('consultarDetalle con usar_sunarp activo complementa la respuesta oficial con datos registrales de SUNARP', function () {
    // Activamos SUNARP
    Config::set('pide.usar_sunarp', true);

    // Mockeamos la respuesta del API oficial PIDE (RENIEC)
    Http::fake([
        'https://ws2.pide.gob.pe/Rest/RENIEC/Consultar*' => Http::response([
            'consultarResponse' => [
                'return' => [
                    'coResultado' => '0000',
                    'datosPersona' => [
                        'prenombres' => 'MARCOS',
                        'apPrimer' => 'GOMEZ',
                        'apSegundo' => 'PEREZ',
                        'direccion' => 'CALLE REAL 123'
                    ]
                ]
            ]
        ], 200),
        'https://ws2.pide.gob.pe/Rest/SUNARP/Titularidad*' => Http::response([
            'titularidadResponse' => [
                'listaBienes' => [
                    [
                        'partida' => '99887766',
                        'registro' => 'PREDIOS',
                        'zona' => 'IX - SEDE LIMA',
                        'descripcion' => 'CASA PLAYA ASIA S/N'
                    ]
                ]
            ]
        ], 200)
    ]);

    $res = $this->pideCore->consultarDetalle('44556677', 'DNI');

    expect($res)->toBeInstanceOf(PersonaDto::class)
        ->and($res->dni)->toBe('44556677')
        ->and($res->nombres)->toBe('MARCOS')
        ->and($res->fuente)->toBe('PIDE') // Fuente principal de identidad sigue siendo PIDE
        ->and($res->bienes)->toBeArray()
        ->and($res->bienes[0]['partida'])->toBe('99887766')
        ->and($res->bienes[0]['descripcion'])->toBe('CASA PLAYA ASIA S/N');
});
