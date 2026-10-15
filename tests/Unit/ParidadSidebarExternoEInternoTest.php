<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Core\ModuleRouteNormalizer;
use App\Services\Core\ModuleDispatcher;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;

class ParidadSidebarExternoEInternoTest extends TestCase
{
    /**
     * FASE 2: Verifica presencia, ausencia y orden exacto de las bandejas visibles en el sidebar externo.
     */
    public function test_paridad_orden_y_ausencias_sidebar_externo(): void
    {
        $sidebarPath = resource_path('views/mod_gm/mod_tram_exte/partials_segi/sidebar.blade.php');
        $this->assertFileExists($sidebarPath);

        $html = file_get_contents($sidebarPath);

        // Extraer los botones con activeBandeja en orden de aparición en el DOM
        preg_match_all('/activeBandeja\s*===\s*\'([^\']+)\'/', $html, $matches);
        $bandejasEncontradas = array_values(array_unique($matches[1]));

        $ordenEsperado = [
            'todos',
            'entrada',
            'recibidos',
            'salida',
            'finalizados',
            'archivo',
            'sin_finalizar',
        ];

        $this->assertEquals(
            $ordenEsperado,
            $bandejasEncontradas,
            'El orden exacto del DOM de bandejas visibles en Corr. Externo debe ser: Todos, Entrada, Recibidos, Salida, Finalizados / Notificados, Archivo, Sin Finalizar.'
        );

        // Verificar etiqueta unificada "Finalizados / Notificados"
        $this->assertStringContainsString('Finalizados / Notificados', $html);

        // Confirmar ausencia de bandejas no expuestas en el legacy
        $this->assertStringNotContainsString("activeBandeja = 'notificados'", $html, 'Bandeja Notificados no debe existir independientemente.');
        $this->assertStringNotContainsString("activeBandeja = 'prestados'", $html, 'Bandeja Prestados no debe estar en el menú principal.');
        $this->assertStringNotContainsString("activeBandeja = 'inventariar'", $html, 'Bandeja Inventariar no debe estar en el menú principal.');

        // Confirmar presencia de botón "Nuevo Trámite"
        $this->assertStringContainsString('Nuevo Trámite', $html);
        $this->assertStringContainsString('/mod_gm/mod_tram_exte/index', $html);
    }

    /**
     * FASE 3: Verifica ausencia del selector Estado en filtros-tabla de Trámite Externo
     * y presencia de los filtros Criterio, Descripción, Fechas y botones.
     */
    public function test_ausencia_selector_estado_en_filtros_externo(): void
    {
        $filtrosPath = resource_path('views/mod_gm/mod_tram_exte/partials_segi/filtros-tabla.blade.php');
        $this->assertFileExists($filtrosPath);

        $html = file_get_contents($filtrosPath);

        // Ausencia de selector x-model="estado"
        $this->assertStringNotContainsString('x-model="estado"', $html, 'No debe existir x-model="estado" en la vista.');
        $this->assertStringNotContainsString('<select name="estado"', $html, 'No debe existir selector select con name="estado".');
        $this->assertStringNotContainsString('<input type="hidden" name="estado"', $html, 'No debe existir input hidden con name="estado" para reportes.');

        // Presencia de los demás controles
        $this->assertStringContainsString('x-model="criterio"', $html);
        $this->assertStringContainsString('x-model="descripcion"', $html);
        $this->assertStringContainsString('x-model="desde"', $html);
        $this->assertStringContainsString('x-model="hasta"', $html);
        $this->assertStringContainsString('fetchItems()', $html);
        $this->assertStringContainsString('cleanFilters()', $html);
    }

    /**
     * FASE 4: Resolución de ruta y despacho canónico para "Nuevo Trámite" de Trámite Externo.
     */
    public function test_resolucion_ruta_nuevo_tramite_externo(): void
    {
        $normalizer = new ModuleRouteNormalizer();
        $dispatcher = new ModuleDispatcher();

        $url = '/mod_gm/mod_tram_exte/index';
        $claveCanonica = $normalizer->normalize($url);

        $this->assertEquals('mod_gm.mod_tram_exte.index', $claveCanonica, 'La URL debe normalizarse a mod_gm.mod_tram_exte.index');
        $this->assertTrue($dispatcher->has($claveCanonica), 'ModuleDispatcher debe registrar mod_gm.mod_tram_exte.index');

        $definicion = $dispatcher->getRegisteredRoutes()[$claveCanonica];
        $this->assertEquals(IngresoExpedienteController::class, $definicion['controller']);
        $this->assertEquals('index', $definicion['method']);
    }

    /**
     * FASE 5: Verifica el orden exacto del DOM de las bandejas en el sidebar interno.
     */
    public function test_paridad_orden_sidebar_interno(): void
    {
        $sidebarPath = resource_path('views/mod_gene/mod_tram_inter/partials/sidebar.blade.php');
        $this->assertFileExists($sidebarPath);

        $html = file_get_contents($sidebarPath);

        // Extraer los botones con data-bandeja en orden de aparición en el DOM
        preg_match_all('/data-bandeja\s*=\s*"([^"]+)"/', $html, $matches);
        $bandejasEncontradas = $matches[1];

        $ordenEsperado = [
            'todos',
            'entrada',
            'enviados',
            'recibidos',
            'salida',
            'finalizados',
            'sin_finalizar',
        ];

        // Verificar que los primeros 7 corresponden al orden definido
        $primeras7 = array_slice($bandejasEncontradas, 0, 7);
        $this->assertEquals(
            $ordenEsperado,
            $primeras7,
            'El orden de bandejas del sidebar interno debe ser: Todos, Entrada, Enviados, Recibidos, Salida, Finalizados, Sin Finalizar.'
        );

        // Videos y Nuevo Trámite presentes al final
        $this->assertStringContainsString('btn-video-tutorial', $html);
        $this->assertStringContainsString('Nuevo Trámite', $html);
    }
}
