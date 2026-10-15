<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;
use App\Models\PUsrrio;
use App\Models\User;
use App\Support\SessionKeys;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AutenticacionCompaniaCargoDinamicoTest extends TestCase
{
    private User $user;
    /** @var SecurityCoreInterface&MockInterface */
    private $securityCoreMock;
    private AuthController $authController;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new User([
            'cdgo_lgin' => 500,
            'de_lgin' => 'funcionario_test',
            'cdgo_usrios' => 1000,
            'cdgo_cmpnia' => 2,
            'cdgo_prfle' => 1,
            'in_esta' => '1',
        ]);
        $this->user->cdgo_lgin = 500;
        $this->user->de_lgin = 'funcionario_test';
        $this->user->cdgo_usrios = 1000;
        $this->user->cdgo_cmpnia = 2;

        $pUsrio = new PUsrrio(['no_crto' => 'JUAN PEREZ']);
        $pUsrio->no_crto = 'JUAN PEREZ';
        $this->user->setRelation('usrio', $pUsrio);

        /** @var SecurityCoreInterface&MockInterface $mock */
        $mock = Mockery::mock(SecurityCoreInterface::class);
        $this->securityCoreMock = $mock;
        $this->app->instance(SecurityCoreInterface::class, $this->securityCoreMock);

        $contextoRepoMock = Mockery::mock(\App\Contracts\Repositories\mod_sistema\ContextoUsuarioAutenticadoRepositoryInterface::class);
        $contextoRepoMock->shouldReceive('validarYObtenerContexto')
            ->byDefault()
            ->andReturnUsing(function (int $idLogin, int $idCompania, int $idCargo) {
                if ($idCargo === 99 || $idCargo === 999) {
                    return null;
                }
                return new \App\DTOs\Core\ContextoUsuarioAutenticadoDto(
                    idLogin: $idLogin,
                    idUsuario: 1000,
                    nombreLogin: 'funcionario_test',
                    idPerfil: 1,
                    idCompania: $idCompania,
                    idCargo: $idCargo,
                    idArea: 100,
                    nombreCompania: 'MUNICIPALIDAD DE PUENTE PIEDRA',
                    nombreCargo: 'SUBGERENTE DE TI',
                    nombreArea: 'GERENCIA DE TI'
                );
            });
        $this->app->instance(\App\Contracts\Repositories\mod_sistema\ContextoUsuarioAutenticadoRepositoryInterface::class, $contextoRepoMock);

        $this->authController = new AuthController($this->securityCoreMock);

        Auth::login($this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function crearRequestConSesion(string $uri, string $method = 'GET', array $parameters = []): Request
    {
        $request = Request::create($uri, $method, $parameters);
        $sessionStore = $this->app['session']->driver();
        $request->setLaravelSession($sessionStore);

        return $request;
    }

    /**
     * 1. Compañía antigua en sesión no autorizada para el nuevo login.
     * Si la sesión contiene una compañía antigua que no aparece en r_cmpnias_lgin del login,
     * seleccionarCargo() limpia todo el contexto operativo y redirige a seleccionar.compania.
     */
    public function test_compania_antigua_en_sesion_no_autorizada_para_nuevo_login_limpia_y_redirige(): void
    {
        $companiasAutorizadas = collect([
            (object) [
                'cdgo_cmpnia' => 2,
                'de_cmpnia' => 'MUNICIPALIDAD DE PUENTE PIEDRA',
                'in_esta' => '1',
            ],
        ]);

        /** @var Expectation $expectation */
        $expectation = $this->securityCoreMock->shouldReceive('obtenerCompaniasUsuario');
        $expectation->with(500)->once()->andReturn($companiasAutorizadas);

        $request = $this->crearRequestConSesion('/seleccionar-cargo', 'GET');
        // Fijamos compañía antigua no autorizada (ej. 99)
        $request->session()->put(SessionKeys::USER_COMPANY, 99);
        $request->session()->put('sigtram_cargo_id', 55);

        $response = $this->authController->seleccionarCargo($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('seleccionar.compania'), $response->getTargetUrl());
        $this->assertNull($request->session()->get(SessionKeys::USER_COMPANY));
        $this->assertNull($request->session()->get('usComp'));
        $this->assertNull($request->session()->get('sigtram_cargo_id'));
    }

    /**
     * 2. Acceso directo a seleccionar-cargo sin compañía (con múltiples compañías).
     * Si el usuario no tiene compañía activa en sesión y tiene varias autorizadas,
     * debe ser redirigido a seleccionar.compania.
     */
    public function test_acceso_directo_a_seleccionar_cargo_sin_compania_redirige_a_seleccionar_compania(): void
    {
        $companias = collect([
            (object) [
                'cdgo_cmpnia' => 2,
                'de_cmpnia' => 'MUNICIPALIDAD DE PUENTE PIEDRA',
                'in_esta' => '1',
            ],
            (object) [
                'cdgo_cmpnia' => 3,
                'de_cmpnia' => 'PALACIO CULTURA',
                'in_esta' => '1',
            ],
        ]);

        /** @var Expectation $expectation */
        $expectation = $this->securityCoreMock->shouldReceive('obtenerCompaniasUsuario');
        $expectation->with(500)->once()->andReturn($companias);

        $request = $this->crearRequestConSesion('/seleccionar-cargo', 'GET');
        // Sin compañía en sesión

        $response = $this->authController->seleccionarCargo($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('seleccionar.compania'), $response->getTargetUrl());
    }

    /**
     * 3. POST de cargo sin compañía.
     * En guardarCargo(), si no existe compañía activa, debe fallar cerrado redirigiendo
     * a seleccionar.compania y nunca deducir la compañía desde el cargo.
     */
    public function test_post_de_cargo_sin_compania_falla_cerrado_y_redirige_a_seleccionar_compania(): void
    {
        $requestPost = $this->crearRequestConSesion('/seleccionar-cargo', 'POST', [
            'cargo_id' => 10,
        ]);
        // Sin compañía activa en sesión

        $response = $this->authController->guardarCargo($requestPost);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('seleccionar.compania'), $response->getTargetUrl());
        $this->assertNull($requestPost->session()->get(SessionKeys::USER_CARGO));
        $this->assertNull($requestPost->session()->get('sigtram_cargo_id'));
        $this->assertNull($requestPost->session()->get(SessionKeys::USER_COMPANY));
    }

    /**
     * 4. /inicio con cargo de otra compañía.
     * Si el cargo no pertenece a la compañía activa en sesión, inicio() limpia
     * todo el contexto operativo y redirigir a seleccionar.cargo.
     */
    public function test_inicio_con_cargo_de_otra_compania_limpia_contexto_y_redirige(): void
    {
        $companias = collect([
            (object) [
                'cdgo_cmpnia' => 2,
                'de_cmpnia' => 'MUNICIPALIDAD DE PUENTE PIEDRA',
                'in_esta' => '1',
            ],
        ]);

        // Cargos autorizados en la compañía activa 2
        $cargosValidosComp2 = collect([
            (object) [
                'cdgo_crgo' => 10,
                'de_crgo' => 'SUBGERENTE DE TI',
                'cdgo_area' => 100,
                'de_area' => 'GERENCIA DE TI',
                'cdgo_cmpnia' => 2,
            ],
        ]);

        /** @var Expectation $expectationCmp */
        $expectationCmp = $this->securityCoreMock->shouldReceive('obtenerCompaniasUsuario');
        $expectationCmp->with(500)->once()->andReturn($companias);

        /** @var Expectation $expectationCrg */
        $expectationCrg = $this->securityCoreMock->shouldReceive('obtenerCargosUsuario');
        $expectationCrg->with(500, 2)->once()->andReturn($cargosValidosComp2);

        $request = $this->crearRequestConSesion('/inicio', 'GET');
        $request->session()->put(SessionKeys::USER_COMPANY, 2);
        // Cargo 99 pertenece a otra compañía y no está en la colección de la compañía 2
        $request->session()->put('sigtram_cargo_id', 99);
        $request->session()->put(SessionKeys::USER_CARGO, 99);

        $response = $this->authController->inicio($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('seleccionar.cargo'), $response->getTargetUrl());
        $this->assertNull($request->session()->get(SessionKeys::USER_CARGO));
        $this->assertNull($request->session()->get('sigtram_cargo_id'));
    }

    /**
     * 5. Login limpia todas las claves anteriores.
     * Verifica que limpiarContextoOperativo() remueva exhaustivamente las 13 claves operativas.
     */
    public function test_login_limpia_todas_las_claves_anteriores(): void
    {
        $request = $this->crearRequestConSesion('/login', 'POST');

        // Poblamos todas las claves operativas previas
        foreach (AuthController::CLAVES_CONTEXTO_OPERATIVO as $clave) {
            $request->session()->put($clave, 'valor_antiguo');
        }

        $this->authController->limpiarContextoOperativo($request);

        foreach (AuthController::CLAVES_CONTEXTO_OPERATIVO as $clave) {
            $this->assertNull(
                $request->session()->get($clave),
                "La clave operativa [{$clave}] debió ser limpiada de la sesión."
            );
        }
    }

    /**
     * 6. Cambio de compañía invalida cargo y área.
     * Al cambiar de compañía mediante guardarCompania(), se deben olvidar todos los datos
     * de cargo y área previamente seleccionados.
     */
    public function test_cambio_de_compania_invalida_cargo_y_area(): void
    {
        $companias = collect([
            (object) [
                'cdgo_cmpnia' => 2,
                'de_cmpnia' => 'MUNICIPALIDAD DE PUENTE PIEDRA',
                'in_esta' => '1',
            ],
            (object) [
                'cdgo_cmpnia' => 3,
                'de_cmpnia' => 'PALACIO CULTURA',
                'in_esta' => '1',
            ],
        ]);

        /** @var Expectation $expectation */
        $expectation = $this->securityCoreMock->shouldReceive('obtenerCompaniasUsuario');
        $expectation->with(500)->once()->andReturn($companias);

        $requestPost = $this->crearRequestConSesion('/seleccionar-compania', 'POST', [
            'compania_id' => 3,
        ]);

        // Simular contexto previo con cargo y área de la compañía 2
        $requestPost->session()->put('sigtram_cargo_id', 10);
        $requestPost->session()->put('sigtram_cargo_nombre', 'CARGO ANTERIOR');
        $requestPost->session()->put('sigtram_area_nombre', 'AREA ANTERIOR');
        $requestPost->session()->put('sigtram_area_id', 100);
        $requestPost->session()->put(SessionKeys::USER_CARGO, 10);
        $requestPost->session()->put(SessionKeys::USER_AREA, 100);

        $response = $this->authController->guardarCompania($requestPost);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('seleccionar.cargo'), $response->getTargetUrl());
        $this->assertEquals(3, $requestPost->session()->get(SessionKeys::USER_COMPANY));
        $this->assertNull($requestPost->session()->get('sigtram_compania_id'));
        $this->assertEquals('PALACIO CULTURA', $requestPost->session()->get('usNomComp'));

        // Verificar que cargo y área fueron invalidados
        $this->assertNull($requestPost->session()->get('sigtram_cargo_id'));
        $this->assertNull($requestPost->session()->get('sigtram_cargo_nombre'));
        $this->assertNull($requestPost->session()->get('sigtram_area_nombre'));
        $this->assertNull($requestPost->session()->get('sigtram_area_id'));
        $this->assertNull($requestPost->session()->get(SessionKeys::USER_CARGO));
        $this->assertNull($requestPost->session()->get(SessionKeys::USER_AREA));
    }

    /**
     * 7. Una compañía y un cargo entra automáticamente a inicio.
     * Si el usuario tiene una sola compañía autorizada y un solo cargo en esa compañía,
     * seleccionarCargo() debe configurar todo el contexto y redirigir directamente a /inicio.
     */
    public function test_una_compania_y_un_cargo_entra_automaticamente_a_inicio(): void
    {
        $companias = collect([
            (object) [
                'cdgo_cmpnia' => 2,
                'de_cmpnia' => 'MUNICIPALIDAD DE PUENTE PIEDRA',
                'in_esta' => '1',
            ],
        ]);

        $cargos = collect([
            (object) [
                'cdgo_crgo' => 10,
                'de_crgo' => 'SUBGERENTE DE TI',
                'cdgo_area' => 100,
                'de_area' => 'GERENCIA DE TI',
                'cdgo_cmpnia' => 2,
            ],
        ]);

        /** @var Expectation $expectationCmp */
        $expectationCmp = $this->securityCoreMock->shouldReceive('obtenerCompaniasUsuario');
        $expectationCmp->with(500)->once()->andReturn($companias);

        /** @var Expectation $expectationCrg */
        $expectationCrg = $this->securityCoreMock->shouldReceive('obtenerCargosUsuario');
        $expectationCrg->with(500, 2)->once()->andReturn($cargos);

        $request = $this->crearRequestConSesion('/seleccionar-cargo', 'GET');
        // Sin compañía previa en sesión

        $response = $this->authController->seleccionarCargo($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('inicio'), $response->getTargetUrl());

        // Verificar persistencia de compañía
        $this->assertEquals(2, $request->session()->get(SessionKeys::USER_COMPANY));
        $this->assertNull($request->session()->get('sigtram_compania_id'));
        $this->assertEquals('MUNICIPALIDAD DE PUENTE PIEDRA', $request->session()->get('usNomComp'));

        // Verificar persistencia de cargo y área
        $this->assertEquals(10, $request->session()->get('sigtram_cargo_id'));
        $this->assertEquals(10, $request->session()->get(SessionKeys::USER_CARGO));
        $this->assertEquals('SUBGERENTE DE TI', $request->session()->get('sigtram_cargo_nombre'));
        $this->assertEquals(100, $request->session()->get('sigtram_area_id'));
        $this->assertEquals(100, $request->session()->get(SessionKeys::USER_AREA));
        $this->assertEquals('GERENCIA DE TI', $request->session()->get('sigtram_area_nombre'));
        $this->assertEquals(500, $request->session()->get(SessionKeys::USER_ID));
    }

    /**
     * 8. Múltiples compañías obliga a seleccionar.
     * Si el usuario tiene más de una compañía autorizada activa, seleccionarCompania()
     * debe retornar la vista 'seleccionar-compania' para obligar al usuario a elegir.
     */
    public function test_multiples_companias_obliga_a_seleccionar(): void
    {
        $companias = collect([
            (object) [
                'cdgo_cmpnia' => 2,
                'de_cmpnia' => 'MUNICIPALIDAD DE PUENTE PIEDRA',
                'in_esta' => '1',
            ],
            (object) [
                'cdgo_cmpnia' => 3,
                'de_cmpnia' => 'PALACIO CULTURA',
                'in_esta' => '1',
            ],
        ]);

        /** @var Expectation $expectation */
        $expectation = $this->securityCoreMock->shouldReceive('obtenerCompaniasUsuario');
        $expectation->with(500)->once()->andReturn($companias);

        $request = $this->crearRequestConSesion('/seleccionar-compania', 'GET');

        $viewResponse = $this->authController->seleccionarCompania($request);

        $this->assertEquals('seleccionar-compania', $viewResponse->name());
        $this->assertCount(2, $viewResponse->getData()['companias']);
        $this->assertNull($request->session()->get(SessionKeys::USER_COMPANY));
    }

    /**
     * Escenario adicional: Intento de selección de compañía NO autorizada para el login.
     * Debe fallar de forma controlada lanzando ValidationException y no persistir nada.
     */
    public function test_compania_no_autorizada_es_rechazada_con_excepcion_de_validacion(): void
    {
        $companias = collect([
            (object) [
                'cdgo_cmpnia' => 2,
                'de_cmpnia' => 'MUNICIPALIDAD DE PUENTE PIEDRA',
                'in_esta' => '1',
            ],
        ]);

        /** @var Expectation $expectation */
        $expectation = $this->securityCoreMock->shouldReceive('obtenerCompaniasUsuario');
        $expectation->with(500)->once()->andReturn($companias);

        $requestPost = $this->crearRequestConSesion('/seleccionar-compania', 'POST', [
            'compania_id' => 99, // Compañía no autorizada
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->authController->guardarCompania($requestPost);
        } finally {
            $this->assertNull($requestPost->session()->get(SessionKeys::USER_COMPANY));
        }
    }

    /**
     * Escenario adicional: Cargo perteneciente a OTRA compañía distinta a la activa.
     * Con compañía activa = 2, si intenta guardar un cargo que pertenece a compañía 3,
     * debe ser rechazado de forma controlada con ValidationException (fail-closed).
     */
    public function test_cargo_de_otra_compania_es_rechazado_fail_closed(): void
    {
        $requestPost = $this->crearRequestConSesion('/seleccionar-cargo', 'POST', [
            'cargo_id' => 99,
        ]);

        // Fijamos compañía activa 2 en sesión
        $requestPost->session()->put(SessionKeys::USER_COMPANY, 2);

        $companiasAutorizadas = collect([
            (object) [
                'cdgo_cmpnia' => 2,
                'de_cmpnia' => 'MUNICIPALIDAD DE PUENTE PIEDRA',
                'in_esta' => '1',
            ],
        ]);

        // SecurityCore solo retorna cargos de la compañía activa (filtro estricto)
        $cargosValidosComp2 = collect([
            (object) [
                'cdgo_crgo' => 10,
                'de_crgo' => 'SUBGERENTE DE TI',
                'cdgo_area' => 100,
                'de_area' => 'GERENCIA DE TI',
                'cdgo_cmpnia' => 2,
            ],
        ]);

        /** @var Expectation $expectationCmp */
        $expectationCmp = $this->securityCoreMock->shouldReceive('obtenerCompaniasUsuario');
        $expectationCmp->with(500)->once()->andReturn($companiasAutorizadas);

        /** @var Expectation $expectationCrg */
        $expectationCrg = $this->securityCoreMock->shouldReceive('obtenerCargosUsuario');
        $expectationCrg->with(500, 2)->once()->andReturn($cargosValidosComp2);

        $this->expectException(ValidationException::class);

        try {
            $this->authController->guardarCargo($requestPost);
        } finally {
            $this->assertNull($requestPost->session()->get(SessionKeys::USER_CARGO));
            $this->assertNull($requestPost->session()->get('sigtram_cargo_id'));
        }
    }

    /**
     * Escenario adicional: Operaciones de trámite sin compañía activa en sesión fallan fail-closed (403).
     * Comprueba que resolverCompaniaActiva() nunca asume un fallback hardcodeado (?? 2 o ?? 3).
     */
    public function test_operacion_tramite_sin_compania_activa_falla_con_403_sin_fallback_fijo(): void
    {
        // Limpiamos sesión de compañía
        session()->forget([SessionKeys::USER_COMPANY, 'usComp', 'sigtram_compania_id']);

        $controller = $this->app->make(IngresoExpedienteController::class);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('resolverCompaniaActiva');
        $method->setAccessible(true);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No tiene una compañía activa válida en la sesión para registrar trámites.');

        $method->invoke($controller);
    }
}
