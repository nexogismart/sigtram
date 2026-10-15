<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class CorreoExternoRutasTest extends TestCase
{
    /**
     * Verifica que todas las 19 rutas de Correo Externo apunten a métodos públicos existentes en el controlador.
     */
    public function test_todas_las_rutas_de_correo_externo_resuelven_metodos_existentes(): void
    {
        $reflector = new ReflectionClass(CorreoExternoController::class);
        $routes = Route::getRoutes();

        $rutasExternas = [
            'mod_gm.segi_expedi.archivar'         => 'archivar',
            'mod_gm.segi_expedi.asignar'          => 'asignar',
            'mod_gm.segi_expedi.derivar'          => 'derivar',
            'mod_gm.segi_expedi.descargar_todos'  => 'descargarTodos',
            'mod_gm.segi_expedi.descargar'        => 'descargarAdjunto',
            'mod_gm.segi_expedi.detalle'          => 'detalle',
            'mod_gm.segi_expedi.devolver'         => 'devolver',
            'mod_gm.segi_expedi.enviar'           => 'enviar',
            'mod_gm.segi_expedi.finalizar'        => 'finalizar',
            'mod_gm.segi_expedi.historial'        => 'historial',
            'mod_gm.segi_expedi.hoja_ruta'        => 'hojaRuta',
            'mod_gm.segi_expedi.imprimir_bandeja' => 'imprimirBandeja',
            'mod_gm.segi_expedi.prestar'          => 'prestar',
            'mod_gm.segi_expedi.recepcionar'      => 'recepcionar',
            'mod_gm.segi_expedi.redirigir'        => 'redirigir',
            'mod_gm.segi_expedi.retornar_prestamo'=> 'retornarPrestamo',
            'mod_gm.segi_expedi.semilla'          => 'obtenerSemilla',
            'mod_gm.segi_expedi.solicitud_video'  => 'solicitudVideo',
            'mod_gm.segi_expedi.tabla'            => 'tabla',
        ];

        $this->assertCount(19, $rutasExternas);

        foreach ($rutasExternas as $nombreRuta => $metodoEsperado) {
            $route = $routes->getByName($nombreRuta);
            $this->assertNotNull($route, "La ruta nombrada [{$nombreRuta}] no está registrada.");

            $this->assertTrue(
                $reflector->hasMethod($metodoEsperado),
                "El método [{$metodoEsperado}] no existe en CorreoExternoController para la ruta [{$nombreRuta}]."
            );

            $method = $reflector->getMethod($metodoEsperado);
            $this->assertTrue(
                $method->isPublic(),
                "El método [{$metodoEsperado}] en CorreoExternoController debe ser público."
            );
        }
    }

    /**
     * Verifica que vistaDesdeMenu no sea privado y mantenga compatibilidad de herencia con Controller base.
     */
    public function test_vista_desde_menu_es_protegido_y_compatible_con_controlador_base(): void
    {
        $reflector = new ReflectionClass(CorreoExternoController::class);
        $this->assertTrue($reflector->hasMethod('vistaDesdeMenu'));

        $method = $reflector->getMethod('vistaDesdeMenu');
        $this->assertTrue($method->isProtected(), 'vistaDesdeMenu debe ser protegido');

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertTrue($params[0]->allowsNull());
        $this->assertTrue($params[1]->allowsNull());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
    }
}
