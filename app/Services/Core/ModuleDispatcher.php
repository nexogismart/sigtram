<?php

declare(strict_types=1);

namespace App\Services\Core;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ModuleDispatcher
 *
 * Despachador determinista centralizado para módulos autorizados de SIGTRAM.
 * Resuelve y ejecuta el controlador o vista correspondiente exclusivamente
 * a partir de la ruta canónica normalizada.
 */
class ModuleDispatcher
{
    public const INVOCATION_REQUEST = 'request';
    public const INVOCATION_NO_PARAMS = 'no_params';
    public const INVOCATION_ABRIR_MODULO = 'abrir_modulo';
    public const INVOCATION_VIEW = 'view';

    /**
     * Registro declarativo de rutas canónicas migradas.
     *
     * @var array<string, array{controller: class-string|null, method?: string, type: string, view?: string}>
     */
    protected array $registry = [
        // ==========================================
        // TRÁMITE INTERNO (mod_gene.mod_tram_inter)
        // ==========================================
        'mod_gene.mod_tram_inter.correo-interno' => [
            'controller' => \App\Http\Controllers\mod_gene\mod_tram_inter\CorreoInternoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_gene.mod_tram_inter.buscar-tramite' => [
            'controller' => \App\Http\Controllers\mod_gene\mod_tram_inter\BuscarTramiteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_gene.mod_tram_inter.ingresar-tramite' => [
            'controller' => \App\Http\Controllers\mod_gene\mod_tram_inter\IngresarTramiteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_gene.mod_tram_inter.reporte-cargo-interno' => [
            'controller' => \App\Http\Controllers\mod_gene\mod_tram_inter\ReporteCargoInternoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_gene.mod_tram_inter.v2_lista_reporte_area_cargo' => [
            'controller' => \App\Http\Controllers\mod_gene\mod_tram_inter\V2ListaReporteAreaCargoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_gene.mod_tram_inter.ver_secuencial_area' => [
            'controller' => \App\Http\Controllers\mod_gene\mod_tram_inter\SecuencialTramiteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_gene.mod_tram_inter.reporte-tramite' => [
            'controller' => \App\Http\Controllers\mod_gene\mod_tram_inter\ReporteTramiteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],

        // ==========================================
        // TRÁMITE EXTERNO (mod_gm.mod_tram_exte)
        // ==========================================
        'mod_gm.mod_tram_exte.index' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.index',
        ],
        'mod_gm.mod_tram_exte.segi_expedi' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.segi_expedi',
        ],
        'mod_gm.mod_tram_exte.segi_adjuntar' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\AdjuntarTramiteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.segi_adjuntar',
        ],
        'mod_gm.mod_tram_exte.segi_archivo' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\ArchivoExpedienteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.segi_archivo',
        ],
        'mod_gm.mod_tram_exte.segi_verexpedi' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\SegiVerExpediController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.segi_verexpedi',
        ],
        'mod_gm.mod_tram_exte.lista_expedi_todo' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\ListaExpediTodoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.lista_expedi_todo',
        ],
        'mod_gm.mod_tram_exte.lista_repor_area' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\ListaReporAreaController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.lista_repor_area',
        ],
        'mod_gm.mod_tram_exte.v2_lista_reporte_area_cargo' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\V2ListaReporteAreaCargoExternoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.v2_lista_reporte_area_cargo',
        ],
        'mod_gm.mod_tram_exte.reporte-expediente' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\ReporteExpedienteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.reporte-expediente',
        ],
        'mod_gm.mod_tram_exte.lista_reporte' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\ListaReporteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.lista_reporte',
        ],
        'mod_gm.mod_tram_exte.redirigir' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\RedirigirExpedienteController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.redirigir',
        ],
        'mod_gm.mod_tram_exte.notificar_pltfrma' => [
            'controller' => \App\Http\Controllers\mod_gm\mod_tram_exte\NotificarPlataformaController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_gm.mod_tram_exte.notificar_pltfrma',
        ],
        'mod_gm.mod_tram_exte.prescripcion' => [
            'controller' => null,
            'type' => self::INVOCATION_VIEW,
            'view' => 'mod_gm.mod_tram_exte.prescripcion',
        ],

        // ==========================================
        // PAGO A PROVEEDORES (mod_a.sgl.pago_proveedores)
        // ==========================================
        'mod_a.sgl.pago_proveedores.index' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\PagoProveedoresIndexController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_a.sgl.pago_proveedores.index',
        ],
        'mod_a.sgl.pago_proveedores.ver_requerimiento_finanzas' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\VerRequerimientoFinanzasController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_a.sgl.pago_proveedores.ver_requerimiento_finanzas',
        ],
        'mod_a.sgl.pago_proveedores.ver_requerimiento_locador' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\VerRequerimientoLocadorController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_a.sgl.pago_proveedores.ver_requerimiento_locador',
        ],
        'mod_a.sgl.pago_proveedores.ver_requerimiento_logistica' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\VerRequerimientoLogisticaController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_a.sgl.pago_proveedores.ver_requerimiento_logistica',
        ],
        'mod_a.sgl.pago_proveedores.ver_requerimiento_presupuesto' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\VerRequerimientoPresupuestoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_a.sgl.pago_proveedores.ver_requerimiento_presupuesto',
        ],
        'mod_a.sgl.pago_proveedores.ver_requerimiento_tesoreria' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\VerRequerimientoTesoreriaController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_a.sgl.pago_proveedores.ver_requerimiento_tesoreria',
        ],
        'mod_a.sgl.pago_proveedores.buscar_requerimiento_pago' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\BuscarRequerimientoPagoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_a.sgl.buscar_requerimiento_pago' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\BuscarRequerimientoPagoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_a.sgl.pago_proveedores.reporte.reporte_gerencial' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\ReporteGerencialController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_a.sgl.pago_proveedores.v2_lista_reporte_requerimientos_general' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\V2ListaReporteRequerimientosGeneralController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_a.sgl.index' => [
            'controller' => \App\Http\Controllers\mod_a\sgl\FichaSeleccionSeaceController::class,
            'method' => 'index',
            'type' => self::INVOCATION_ABRIR_MODULO,
            'view' => 'mod_a.sgl.index',
        ],

        // ==========================================
        // MÓDULOS TI / HELPDESK
        // ==========================================
        'mod_ti.helpdesk.index' => [
            'controller' => \App\Http\Controllers\mod_ti\helpdesk\IngresarServiciosController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.helpdesk.bandeja_externa' => [
            'controller' => \App\Http\Controllers\mod_ti\helpdesk\IngresarServiciosController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.helpdesk.consulta' => [
            'controller' => \App\Http\Controllers\mod_ti\helpdesk\ConsultasController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.helpdesk.administracion' => [
            'controller' => \App\Http\Controllers\mod_ti\helpdesk\ConsultasController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.helpdesk.bandeja' => [
            'controller' => \App\Http\Controllers\mod_ti\helpdesk\IngresarBandejaController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.helpdesk.pantalla' => [
            'controller' => \App\Http\Controllers\mod_ti\helpdesk\TicketsController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.dispositivo_seg' => [
            'controller' => \App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.dispositivo_seg.index' => [
            'controller' => \App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.dispositivo_seg.mapa' => [
            'controller' => \App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegController::class,
            'method' => 'mapa',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.dispositivo_seg.graficos' => [
            'controller' => \App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegController::class,
            'method' => 'graficos',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.firma_email' => [
            'controller' => \App\Http\Controllers\mod_ti\firma_email\FirmaEmailController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.firma_email.index' => [
            'controller' => \App\Http\Controllers\mod_ti\firma_email\FirmaEmailController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.reporte_publicidad' => [
            'controller' => \App\Http\Controllers\mod_ti\reporte_publicidad\ReportePublicidadController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.reporte_publicidad.index' => [
            'controller' => \App\Http\Controllers\mod_ti\reporte_publicidad\ReportePublicidadController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.reporte_publicidad_2026' => [
            'controller' => \App\Http\Controllers\mod_ti\reporte_publicidad_2026\ReportePublicidad2026Controller::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_ti.reporte_publicidad_2026.index' => [
            'controller' => \App\Http\Controllers\mod_ti\reporte_publicidad_2026\ReportePublicidad2026Controller::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],

        // ==========================================
        // GESTIÓN PÚBLICA, PROMESA, OAC, GDU
        // ==========================================
        'mod_gp.omaped' => [
            'controller' => \App\Http\Controllers\mod_gp\omaped\OmapedController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_gp.omaped.index' => [
            'controller' => \App\Http\Controllers\mod_gp\omaped\OmapedController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_promesa' => [
            'controller' => \App\Http\Controllers\mod_promesa\PromesaController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_promesa.index' => [
            'controller' => \App\Http\Controllers\mod_promesa\PromesaController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_oac.acceso_informacion.acceso_info' => [
            'controller' => \App\Http\Controllers\mod_oac\AccesoInfoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_gdu.band_consulta_gdu' => [
            'controller' => \App\Http\Controllers\mod_gdu\BandConsultaGduController::class,
            'method' => 'index',
            'type' => self::INVOCATION_NO_PARAMS,
        ],
        'mod_gdu.procedente_constancia' => [
            'controller' => \App\Http\Controllers\mod_gdu\BandConsultaGduController::class,
            'method' => 'procedenteConstancia',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_gdu.procedente_licencias' => [
            'controller' => \App\Http\Controllers\mod_gdu\BandConsultaGduController::class,
            'method' => 'procedenteLicencias',
            'type' => self::INVOCATION_REQUEST,
        ],

        // ==========================================
        // REPORTES Y SEGURIDAD
        // ==========================================
        'mod_reportes.reporte_tramites' => [
            'controller' => \App\Http\Controllers\mod_reportes\ReporteTramitesController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_seguridad.seguridad-perfiles' => [
            'controller' => \App\Http\Controllers\mod_seguridad\PerfilController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_seguridad.seguridad-cargos' => [
            'controller' => \App\Http\Controllers\mod_seguridad\CargoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_seguridad.seguridad-opciones' => [
            'controller' => \App\Http\Controllers\mod_seguridad\OpcionController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_seguridad.seguridad-usuario' => [
            'controller' => \App\Http\Controllers\mod_seguridad\UsuarioController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_seguridad.seguridad-accesos' => [
            'controller' => \App\Http\Controllers\mod_seguridad\AccesoController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_seguridad.seguridad-perfiles-opciones' => [
            'controller' => \App\Http\Controllers\mod_seguridad\PerfilOpcionController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_seguridad.cambiar-contrasena' => [
            'controller' => \App\Http\Controllers\mod_seguridad\PasswordController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
        'mod_seguridad.cambiar-contraseña' => [
            'controller' => \App\Http\Controllers\mod_seguridad\PasswordController::class,
            'method' => 'index',
            'type' => self::INVOCATION_REQUEST,
        ],
    ];

    /**
     * Determina si una ruta canónica tiene un handler registrado.
     *
     * @param string $canonicalRoute
     * @return bool
     */
    public function has(string $canonicalRoute): bool
    {
        return isset($this->registry[$canonicalRoute]);
    }

    /**
     * Retorna el descriptor declarativo de una ruta registrada o null.
     *
     * @param string $canonicalRoute
     * @return array{controller: class-string|null, method?: string, type: string, view?: string}|null
     */
    public function getDescriptor(string $canonicalRoute): ?array
    {
        return $this->registry[$canonicalRoute] ?? null;
    }

    /**
     * Retorna todas las rutas registradas (para inspección y pruebas).
     *
     * @return array<string, array{controller: class-string|null, method?: string, type: string, view?: string}>
     */
    public function getRegisteredRoutes(): array
    {
        return $this->registry;
    }

    /**
     * Despacha la solicitud al controlador o vista correspondiente según el descriptor registrado.
     *
     * @param string $canonicalRoute
     * @param Request $request
     * @param object|null $opcionMenu
     * @return mixed
     */
    public function dispatch(string $canonicalRoute, Request $request, ?object $opcionMenu = null): mixed
    {
        $descriptor = $this->registry[$canonicalRoute] ?? null;
        if ($descriptor === null) {
            return null;
        }

        $type = $descriptor['type'];

        return match ($type) {
            self::INVOCATION_ABRIR_MODULO => $this->dispatchAbrirModulo($descriptor, $canonicalRoute, $request, $opcionMenu),
            self::INVOCATION_REQUEST => $this->dispatchRequest($descriptor, $request),
            self::INVOCATION_NO_PARAMS => $this->dispatchNoParams($descriptor),
            self::INVOCATION_VIEW => $this->dispatchView($descriptor, $canonicalRoute),
            default => abort(500, "Tipo de invocación desconocido en el despachador: {$type}"),
        };
    }

    /**
     * Despacho con patrón abrirModulo: ($request, $view, $opcionMenu).
     *
     * @param array{controller: class-string|null, method?: string, view?: string} $descriptor
     */
    protected function dispatchAbrirModulo(array $descriptor, string $canonicalRoute, Request $request, ?object $opcionMenu): mixed
    {
        $view = $descriptor['view'] ?? $canonicalRoute;
        if (!$this->existeVistaBlade($view)) {
            abort(404, 'El archivo de vista no existe.');
        }

        $controllerClass = $descriptor['controller'];
        if ($controllerClass === null) {
            abort(500, "Controlador no definido para {$canonicalRoute}");
        }

        $controller = app($controllerClass);
        $method = $descriptor['method'] ?? 'index';

        return $controller->$method($request, $view, $opcionMenu);
    }

    /**
     * Despacho estándar pasando únicamente el Request: ($request).
     *
     * @param array{controller: class-string|null, method?: string} $descriptor
     */
    protected function dispatchRequest(array $descriptor, Request $request): mixed
    {
        $controllerClass = $descriptor['controller'];
        if ($controllerClass === null) {
            abort(500, 'Controlador no configurado.');
        }

        $controller = app($controllerClass);
        $method = $descriptor['method'] ?? 'index';

        return $controller->$method($request);
    }

    /**
     * Despacho sin parámetros: ().
     *
     * @param array{controller: class-string|null, method?: string} $descriptor
     */
    protected function dispatchNoParams(array $descriptor): mixed
    {
        $controllerClass = $descriptor['controller'];
        if ($controllerClass === null) {
            abort(500, 'Controlador no configurado.');
        }

        $controller = app($controllerClass);
        $method = $descriptor['method'] ?? 'index';

        return $controller->$method();
    }

    /**
     * Despacho directo de vista Blade autorizada.
     *
     * @param array{view?: string} $descriptor
     */
    protected function dispatchView(array $descriptor, string $canonicalRoute): mixed
    {
        $view = $descriptor['view'] ?? $canonicalRoute;
        if (!$this->existeVistaBlade($view)) {
            abort(404, 'El archivo de vista no existe.');
        }

        return view($view);
    }

    /**
     * Comprueba si la vista Blade existe físicamente en resources/views.
     *
     * @param string $pathVista
     * @return bool
     */
    public function existeVistaBlade(string $pathVista): bool
    {
        if ($pathVista === '') {
            return false;
        }

        $relViewPath = 'views/' . str_replace('.', '/', $pathVista) . '.blade.php';

        if (function_exists('app') && method_exists(app(), 'resourcePath')) {
            $archivo = resource_path($relViewPath);
        } else {
            $archivo = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relViewPath);
        }

        return is_file($archivo);
    }
}
