<?php

declare(strict_types=1);

use App\Services\Core\ModuleDispatcher;
use App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController;
use App\Http\Controllers\mod_gene\mod_tram_inter\CorreoInternoController;
use App\Http\Controllers\mod_gm\mod_tram_exte\ListaExpediTodoController;
use App\Http\Controllers\mod_gm\mod_tram_exte\ListaReporAreaController;
use App\Http\Controllers\mod_gm\mod_tram_exte\SegiVerExpediController;
use App\Http\Controllers\mod_gm\mod_tram_exte\ArchivoExpedienteController;
use App\Http\Controllers\mod_gm\mod_tram_exte\AdjuntarTramiteController;
use App\Http\Controllers\mod_gm\mod_tram_exte\NotificarPlataformaController;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;
use App\Http\Controllers\mod_a\sgl\PagoProveedoresIndexController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoFinanzasController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoLocadorController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoLogisticaController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoPresupuestoController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoTesoreriaController;

describe('ModuleDispatcher', function () {
    $dispatcher = new ModuleDispatcher();

    test('8. Corr. Externo resuelve exactamente a CorreoExternoController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gm.mod_tram_exte.segi_expedi');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(CorreoExternoController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_ABRIR_MODULO);
        expect($desc['view'])->toBe('mod_gm.mod_tram_exte.segi_expedi');
    });

    test('9. Corr. Interno resuelve exactamente a CorreoInternoController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gene.mod_tram_inter.correo-interno');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(CorreoInternoController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_REQUEST);
    });

    test('9b. Buscar Tramite Interno resuelve a BuscarTramiteController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gene.mod_tram_inter.buscar-tramite');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(\App\Http\Controllers\mod_gene\mod_tram_inter\BuscarTramiteController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_REQUEST);
    });

    test('9c. Ingresar Tramite Interno resuelve a IngresarTramiteController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gene.mod_tram_inter.ingresar-tramite');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(\App\Http\Controllers\mod_gene\mod_tram_inter\IngresarTramiteController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_REQUEST);
    });

    test('10. Ver Expedientes resuelve a ListaExpediTodoController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gm.mod_tram_exte.lista_expedi_todo');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(ListaExpediTodoController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_ABRIR_MODULO);
    });

    test('11. Reporte de Movimientos resuelve a ListaReporAreaController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gm.mod_tram_exte.lista_repor_area');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(ListaReporAreaController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_ABRIR_MODULO);
    });

    test('12. Ver Expedientes Area resuelve a SegiVerExpediController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gm.mod_tram_exte.segi_verexpedi');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(SegiVerExpediController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_ABRIR_MODULO);
    });

    test('13. Corr. Archivo resuelve a ArchivoExpedienteController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gm.mod_tram_exte.segi_archivo');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(ArchivoExpedienteController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_ABRIR_MODULO);
    });

    test('14. Adjuntar Archivo resuelve a AdjuntarTramiteController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gm.mod_tram_exte.segi_adjuntar');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(AdjuntarTramiteController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_ABRIR_MODULO);
    });

    test('15. Notificar Finalizados utiliza notificar_pltfrma', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gm.mod_tram_exte.notificar_pltfrma');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(NotificarPlataformaController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_ABRIR_MODULO);
        expect($desc['view'])->toBe('mod_gm.mod_tram_exte.notificar_pltfrma');
    });

    test('16. Ingresar Expedientes utiliza mod_gm.mod_tram_exte.index', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_gm.mod_tram_exte.index');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(IngresoExpedienteController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_ABRIR_MODULO);
        expect($desc['view'])->toBe('mod_gm.mod_tram_exte.index');
    });

    test('17. Registro Requerimiento utiliza PagoProveedoresIndexController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_a.sgl.pago_proveedores.index');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(PagoProveedoresIndexController::class);
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_ABRIR_MODULO);
    });

    test('18. Cada Requ. usa su controlador especifico', function () use ($dispatcher) {
        expect($dispatcher->getDescriptor('mod_a.sgl.pago_proveedores.ver_requerimiento_finanzas')['controller'])
            ->toBe(VerRequerimientoFinanzasController::class);
        expect($dispatcher->getDescriptor('mod_a.sgl.pago_proveedores.ver_requerimiento_locador')['controller'])
            ->toBe(VerRequerimientoLocadorController::class);
        expect($dispatcher->getDescriptor('mod_a.sgl.pago_proveedores.ver_requerimiento_logistica')['controller'])
            ->toBe(VerRequerimientoLogisticaController::class);
        expect($dispatcher->getDescriptor('mod_a.sgl.pago_proveedores.ver_requerimiento_presupuesto')['controller'])
            ->toBe(VerRequerimientoPresupuestoController::class);
        expect($dispatcher->getDescriptor('mod_a.sgl.pago_proveedores.ver_requerimiento_tesoreria')['controller'])
            ->toBe(VerRequerimientoTesoreriaController::class);
    });

    test('21. Una ruta desconocida no coincide parcialmente con otra', function () use ($dispatcher) {
        expect($dispatcher->has('mod_gm.mod_tram_exte.segi_expedi_falso'))->toBeFalse();
        expect($dispatcher->has('mod_gm.mod_tram_exte.segi'))->toBeFalse();
        expect($dispatcher->has('mod_gm.mod_tram_exte'))->toBeFalse();
        expect($dispatcher->has('mod_a.sgl.pago_proveedores.finanzas'))->toBeFalse();
    });

    test('no existen alias inventados en el despachador', function () use ($dispatcher) {
        expect($dispatcher->has('mod_gm.mod_tram_exte.correo-externo'))->toBeFalse();
        expect($dispatcher->has('mod_gm.mod_tram_exte.ingreso-expedientes'))->toBeFalse();
        expect($dispatcher->has('mod_gm.mod_tram_exte.ingresar_expedientes'))->toBeFalse();
        expect($dispatcher->has('mod_gm.mod_tram_exte.notificar-plataforma'))->toBeFalse();
        expect($dispatcher->has('mod_gm.mod_tram_exte.notificar_plataforma'))->toBeFalse();
        expect($dispatcher->has('mod_gm.mod_tram_exte.redirigir_expediente'))->toBeFalse();
    });

    test('compatibilidad legacy: mod_ti.helpdesk.bandeja_externa resuelve a IngresarServiciosController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_ti.helpdesk.bandeja_externa');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(\App\Http\Controllers\mod_ti\helpdesk\IngresarServiciosController::class);
        expect($desc['method'] ?? 'index')->toBe('index');
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_REQUEST);
    });

    test('compatibilidad legacy: mod_ti.helpdesk.administracion resuelve a ConsultasController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_ti.helpdesk.administracion');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(\App\Http\Controllers\mod_ti\helpdesk\ConsultasController::class);
        expect($desc['method'] ?? 'index')->toBe('index');
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_REQUEST);
    });

    test('compatibilidad legacy: mod_a.sgl.buscar_requerimiento_pago resuelve a BuscarRequerimientoPagoController', function () use ($dispatcher) {
        $desc = $dispatcher->getDescriptor('mod_a.sgl.buscar_requerimiento_pago');
        expect($desc)->not->toBeNull();
        expect($desc['controller'])->toBe(\App\Http\Controllers\mod_a\sgl\BuscarRequerimientoPagoController::class);
        expect($desc['method'] ?? 'index')->toBe('index');
        expect($desc['type'])->toBe(ModuleDispatcher::INVOCATION_REQUEST);
    });

    test('rutas legacy y canonicas resuelven al mismo controlador esperado', function () use ($dispatcher) {
        $pares = [
            [
                'legacy' => 'mod_ti.helpdesk.bandeja_externa',
                'canonica' => 'mod_ti.helpdesk.index',
                'controller' => \App\Http\Controllers\mod_ti\helpdesk\IngresarServiciosController::class,
            ],
            [
                'legacy' => 'mod_ti.helpdesk.administracion',
                'canonica' => 'mod_ti.helpdesk.consulta',
                'controller' => \App\Http\Controllers\mod_ti\helpdesk\ConsultasController::class,
            ],
            [
                'legacy' => 'mod_a.sgl.buscar_requerimiento_pago',
                'canonica' => 'mod_a.sgl.pago_proveedores.buscar_requerimiento_pago',
                'controller' => \App\Http\Controllers\mod_a\sgl\BuscarRequerimientoPagoController::class,
            ],
        ];

        foreach ($pares as $par) {
            $descLegacy = $dispatcher->getDescriptor($par['legacy']);
            $descCanonica = $dispatcher->getDescriptor($par['canonica']);

            expect($descLegacy)->not->toBeNull();
            expect($descCanonica)->not->toBeNull();
            expect($descLegacy['controller'])->toBe($par['controller']);
            expect($descCanonica['controller'])->toBe($par['controller']);
            expect($descLegacy['controller'])->toBe($descCanonica['controller']);
            expect($descLegacy['type'])->toBe($descCanonica['type']);
        }
    });

    test('FASE 4: validacion exhaustiva y programatica de todo el registro de ModuleDispatcher', function () use ($dispatcher) {
        $normalizer = new \App\Services\Core\ModuleRouteNormalizer();
        $rutas = $dispatcher->getRegisteredRoutes();

        expect($rutas)->not->toBeEmpty();

        $allowedTypes = [
            ModuleDispatcher::INVOCATION_REQUEST,
            ModuleDispatcher::INVOCATION_NO_PARAMS,
            ModuleDispatcher::INVOCATION_ABRIR_MODULO,
            ModuleDispatcher::INVOCATION_VIEW,
        ];

        foreach ($rutas as $canonicalRoute => $descriptor) {
            expect($canonicalRoute)->not->toBeEmpty();
            expect($normalizer->normalize($canonicalRoute))->toBe($canonicalRoute);
            expect($descriptor)->toHaveKey('type');
            expect($descriptor['type'])->toBeIn($allowedTypes);

            $type = $descriptor['type'];

            if ($type === ModuleDispatcher::INVOCATION_VIEW) {
                expect($descriptor)->toHaveKey('view');
                expect($dispatcher->existeVistaBlade($descriptor['view']))->toBeTrue(
                    "La vista declarada [{$descriptor['view']}] para [{$canonicalRoute}] no existe."
                );
            } else {
                expect($descriptor['controller'])->not->toBeNull();
                expect(class_exists($descriptor['controller']))->toBeTrue(
                    "El controlador [{$descriptor['controller']}] para [{$canonicalRoute}] no existe."
                );

                $method = $descriptor['method'] ?? 'index';
                $reflection = new \ReflectionClass($descriptor['controller']);
                expect($reflection->hasMethod($method))->toBeTrue(
                    "El método [{$method}] en [{$descriptor['controller']}] no existe."
                );

                $methodReflection = $reflection->getMethod($method);
                expect($methodReflection->isPublic())->toBeTrue();

                if ($type === ModuleDispatcher::INVOCATION_ABRIR_MODULO) {
                    $view = $descriptor['view'] ?? $canonicalRoute;
                    expect($dispatcher->existeVistaBlade($view))->toBeTrue(
                        "La vista [{$view}] para [{$canonicalRoute}] debe existir físicamente."
                    );
                }
            }
        }
    });
});
