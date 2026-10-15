<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Services\SecurityCoreInterface;
use App\Http\Controllers\AuthController;
use App\Models\User;
use App\Services\Core\ModuleDispatcher;
use App\Services\Core\ModuleRouteNormalizer;
use App\Support\SessionKeys;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeterministicModuleResolutionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 19. Etiquetas duplicadas no modifican el handler.
     * 20. Cambiar dc_rle no cambia el controlador mientras la ruta sea la misma.
     */
    public function test_cambiar_dc_rle_o_etiquetas_duplicadas_no_cambia_el_controlador(): void
    {
        $normalizer = new ModuleRouteNormalizer();
        $dispatcher = new ModuleDispatcher();

        // Ambas opciones tienen distinta etiqueta visual dc_rle, pero la misma ruta canónica de_dreccon
        $opcionA = (object) ['dc_rle' => 'Ingreso de Bandeja Expedientes', 'de_dreccon' => '/mod_gm/mod_tram_exte/segi_expedi.blade.php'];
        $opcionB = (object) ['dc_rle' => 'Seguimiento de Documentos Externos', 'de_dreccon' => '/mod_gm/mod_tram_exte/segi_expedi.blade.php'];

        $canonA = $normalizer->normalize((string) $opcionA->de_dreccon);
        $canonB = $normalizer->normalize((string) $opcionB->de_dreccon);

        $this->assertSame('mod_gm.mod_tram_exte.segi_expedi', $canonA);
        $this->assertSame('mod_gm.mod_tram_exte.segi_expedi', $canonB);

        $descA = $dispatcher->getDescriptor($canonA);
        $descB = $dispatcher->getDescriptor($canonB);

        $this->assertSame($descA, $descB);
        $this->assertSame(\App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController::class, $descA['controller']);
    }

    /**
     * 21. Una ruta desconocida no coincide parcialmente con otra.
     */
    public function test_ruta_desconocida_no_coincide_parcialmente(): void
    {
        $dispatcher = new ModuleDispatcher();

        $this->assertFalse($dispatcher->has('mod_gene.mod_tram_inter.correo'));
        $this->assertFalse($dispatcher->has('mod_gene.mod_tram_inter'));
        $this->assertFalse($dispatcher->has('mod_gm.mod_tram_exte.segi_expedi_extra'));
        $this->assertFalse($dispatcher->has('mod_a.sgl.pago_proveedores.ver_requerimiento'));
    }

    /**
     * 22. Un usuario sin permiso recibe 403.
     */
    public function test_usuario_sin_permiso_recibe_403(): void
    {
        $securityCore = Mockery::mock(SecurityCoreInterface::class);
        $normalizer = new ModuleRouteNormalizer();
        $dispatcher = new ModuleDispatcher();

        $securityCore->shouldReceive('obtenerOpcionPorRuta')->never();
        $securityCore->shouldReceive('tienePermiso')
            ->once()
            ->with(9999, '/mod_gm/mod_tram_exte/segi_expedi', 10)
            ->andReturn(false);

        $controller = new AuthController($securityCore, $normalizer, $dispatcher);

        $user = new User();
        $user->cdgo_lgin = 9999;
        $this->actingAs($user);

        $sessionStore = app('session.store');
        $sessionStore->put(SessionKeys::CARGO_ID, 10);
        $request = Request::create('/mod_gm/mod_tram_exte/segi_expedi.blade.php', 'GET');
        $request->setLaravelSession($sessionStore);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No tienes permiso para acceder a este módulo.');

        try {
            $controller->cargarModulo($request, 'mod_gm/mod_tram_exte/segi_expedi.blade.php');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * Verifica que usuario sin cargo activo o cargo cero recibe 403 controlado antes de llamar a SecurityCore.
     */
    public function test_usuario_sin_cargo_activo_recibe_403_controlado_sin_consultar_security(): void
    {
        $securityCore = Mockery::mock(SecurityCoreInterface::class);
        $securityCore->shouldReceive('tienePermiso')->never();
        $securityCore->shouldReceive('obtenerOpcionPorRuta')->never();

        $normalizer = new ModuleRouteNormalizer();
        $dispatcher = new ModuleDispatcher();

        $controller = new AuthController($securityCore, $normalizer, $dispatcher);

        $user = new User();
        $user->cdgo_lgin = 9999;
        $this->actingAs($user);

        $sessionStore = app('session.store');
        $sessionStore->put(SessionKeys::CARGO_ID, 0);
        $request = Request::create('/mod_gm/mod_tram_exte/segi_expedi.blade.php', 'GET');
        $request->setLaravelSession($sessionStore);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Debe seleccionar un cargo activo.');

        try {
            $controller->cargarModulo($request, 'mod_gm/mod_tram_exte/segi_expedi.blade.php');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * 23. Una ruta inexistente recibe 404.
     * 24. Ningún módulo redirige silenciosamente a /inicio.
     */
    public function test_ruta_inexistente_recibe_404_y_no_redirige_a_inicio(): void
    {
        $securityCore = Mockery::mock(SecurityCoreInterface::class);
        $normalizer = new ModuleRouteNormalizer();
        $dispatcher = new ModuleDispatcher();

        $securityCore->shouldReceive('obtenerOpcionPorRuta')->andReturn(null);
        $securityCore->shouldReceive('tienePermiso')->once()->andReturn(true);

        $controller = new AuthController($securityCore, $normalizer, $dispatcher);

        $user = new User();
        $user->cdgo_lgin = 9999;
        $this->actingAs($user);

        $sessionStore = app('session.store');
        $sessionStore->put(SessionKeys::CARGO_ID, 10);
        $request = Request::create('/modulo_inexistente_xyz', 'GET');
        $request->setLaravelSession($sessionStore);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('El archivo de vista no existe.');

        try {
            $controller->cargarModulo($request, 'modulo_inexistente_xyz');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            throw $e;
        }
    }

    /**
     * Prueba de integración aislada del flujo real:
     * ruta HTTP -> normalize() -> toUrlPath() -> tienePermiso() -> obtenerOpcionPorRuta() -> ModuleDispatcher.
     * Verifica que SecurityCore recibe exactamente la URL normalizada no vacía.
     */
    public function test_flujo_integracion_real_envia_url_correcta_y_no_vacia_a_security(): void
    {
        $securityCore = Mockery::mock(SecurityCoreInterface::class);
        $normalizer = new ModuleRouteNormalizer();
        $dispatcher = Mockery::mock(ModuleDispatcher::class);

        // 1. tienePermiso DEBE recibir exactamente '/mod_gm/mod_tram_exte/segi_expedi', NUNCA ''
        $securityCore->shouldReceive('tienePermiso')
            ->once()
            ->with(5555, '/mod_gm/mod_tram_exte/segi_expedi', 20)
            ->andReturn(true);

        // 2. obtenerOpcionPorRuta DEBE recibir exactamente '/mod_gm/mod_tram_exte/segi_expedi', NUNCA ''
        $opcionEsperada = (object) [
            'dc_rle' => 'Corr. Externo',
            'de_dreccon' => '/mod_gm/mod_tram_exte/segi_expedi.blade.php',
        ];
        $securityCore->shouldReceive('obtenerOpcionPorRuta')
            ->once()
            ->with('/mod_gm/mod_tram_exte/segi_expedi')
            ->andReturn($opcionEsperada);

        // 3. ModuleDispatcher recibe la clave canonica y despacha
        $dispatcher->shouldReceive('has')
            ->with('mod_gm.mod_tram_exte.segi_expedi')
            ->andReturn(true);

        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with('mod_gm.mod_tram_exte.segi_expedi', Mockery::type(Request::class), $opcionEsperada)
            ->andReturn(response('OK_DISPATCHED'));

        $controller = new AuthController($securityCore, $normalizer, $dispatcher);

        $user = new User();
        $user->cdgo_lgin = 5555;
        $this->actingAs($user);

        $sessionStore = app('session.store');
        $sessionStore->put(SessionKeys::CARGO_ID, 20);
        $request = Request::create('/mod_gm/mod_tram_exte/segi_expedi.blade.php', 'GET');
        $request->setLaravelSession($sessionStore);

        $response = $controller->cargarModulo($request, 'mod_gm/mod_tram_exte/segi_expedi.blade.php');

        $this->assertNotNull($response);
        $this->assertSame('OK_DISPATCHED', $response->getContent());
    }

    /**
     * 27. No quedan llamadas a los detectores difusos eliminados.
     * 28. No quedan str_contains($label, ...) para despachar módulos.
     */
    public function test_no_quedan_metodos_difusos_ni_str_contains_en_authcontroller(): void
    {
        $reflector = new \ReflectionClass(AuthController::class);

        $fuzzyMethods = [
            'etiquetaOpcion',
            'esOpcionIngresoExpedientes',
            'esOpcionNotificarPlataforma',
            'esOpcionCorreoExterno',
            'esOpcionVerExpedi',
            'esOpcionAdjuntar',
            'esOpcionArchivoExpediente',
            'esOpcionReporteExpediente',
            'esOpcionPagoProveedoresIndex',
            'esOpcionVerRequerimientoFinanzas',
            'esOpcionVerRequerimientoLocador',
            'esOpcionVerRequerimientoLogistica',
            'esOpcionVerRequerimientoPresupuesto',
            'esOpcionVerRequerimientoTesoreria',
            'esOpcionFichaSeleccionSeace',
            'esOpcionV2ListaReporteAreaCargoExterno',
            'esOpcionListaReporArea',
            'esOpcionListaExpediTodo',
            'esOpcionListaReporte',
            'esOpcionRedirigir',
        ];

        foreach ($fuzzyMethods as $method) {
            $this->assertFalse(
                $reflector->hasMethod($method),
                "El método difuso {$method} aún existe en AuthController."
            );
        }

        $sourceCode = file_get_contents($reflector->getFileName());
        $this->assertStringNotContainsString('str_contains($label', $sourceCode);
        $this->assertStringNotContainsString('str_contains', $sourceCode);
    }
}
