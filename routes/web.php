<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\mod_a\sgl\BuscarRequerimientoPagoController;
use App\Http\Controllers\mod_a\sgl\FichaSeleccionSeaceController;
use App\Http\Controllers\mod_a\sgl\PagoProveedoresIndexController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoFinanzasController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoLocadorController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoLogisticaController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoPresupuestoController;
use App\Http\Controllers\mod_a\sgl\VerRequerimientoTesoreriaController;
use App\Http\Controllers\mod_a\sgl\ReporteGerencialController;
use App\Http\Controllers\mod_a\sgl\V2ListaReporteRequerimientosGeneralController;
use App\Http\Controllers\mod_gene\mod_tram_inter\BuscarTramiteController;
use App\Http\Controllers\mod_gene\mod_tram_inter\CorreoInternoController;
use App\Http\Controllers\mod_gene\mod_tram_inter\IngresarTramiteController;
use App\Http\Controllers\mod_gene\mod_tram_inter\ReporteCargoInternoController;
use App\Http\Controllers\mod_gene\mod_tram_inter\ReporteTramiteController;
use App\Http\Controllers\mod_gene\mod_tram_inter\SecuencialTramiteController;
use App\Http\Controllers\mod_gene\mod_tram_inter\V2ListaReporteAreaCargoController;
use App\Http\Controllers\mod_gm\mod_tram_exte\AdjuntarTramiteController;
use App\Http\Controllers\mod_gm\mod_tram_exte\ArchivoExpedienteController;
use App\Http\Controllers\mod_gm\mod_tram_exte\BuscaExpediController;
use App\Http\Controllers\mod_gm\mod_tram_exte\CorreoExternoController;
use App\Http\Controllers\mod_gm\mod_tram_exte\IngresoExpedienteController;
use App\Http\Controllers\mod_gm\mod_tram_exte\SegiVerExpediController;
use App\Http\Controllers\mod_gm\mod_tram_exte\ListaExpediTodoController;
use App\Http\Controllers\mod_gm\mod_tram_exte\ListaReporAreaController;
use App\Http\Controllers\mod_gm\mod_tram_exte\V2ListaReporteAreaCargoExternoController;
use App\Http\Controllers\mod_gm\mod_tram_exte\ListaReporteController;
use App\Http\Controllers\mod_gm\mod_tram_exte\NotificarPlataformaController;
use App\Http\Controllers\mod_gm\mod_tram_exte\RedirigirExpedienteController;
use App\Http\Controllers\mod_gm\mod_tram_exte\ReporteExpedienteController;
use App\Http\Controllers\mod_reportes\ReporteTramitesController;
use App\Http\Controllers\mod_seguridad\AccesoController;
use App\Http\Controllers\mod_seguridad\CargoController;
use App\Http\Controllers\mod_seguridad\OpcionController;
use App\Http\Controllers\mod_seguridad\PerfilController;
use App\Http\Controllers\mod_seguridad\PerfilOpcionController;
use App\Http\Controllers\mod_seguridad\UsuarioController;
use App\Http\Controllers\mod_seguridad\PasswordController;
use App\Http\Controllers\mod_gp\omaped\OmapedController;
use App\Http\Controllers\mod_promesa\PromesaController;
use App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegController;
use App\Http\Controllers\mod_ti\firma_email\FirmaEmailController;
use App\Http\Controllers\mod_ti\reporte_publicidad\ReportePublicidadController;
use App\Http\Controllers\mod_ti\reporte_publicidad_2026\ReportePublicidad2026Controller;
use App\Http\Controllers\mod_ti\helpdesk\ConsultasController;
use App\Http\Controllers\mod_ti\helpdesk\IngresarBandejaController;
use App\Http\Controllers\mod_ti\helpdesk\IngresarServiciosController;
use App\Http\Controllers\mod_ti\helpdesk\TicketsController;
use App\Http\Controllers\Core\PideController;
use App\Http\Controllers\mod_oac\AccesoInfoController;
use App\Http\Controllers\mod_gdu\BandConsultaGduController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::redirect('/mockups/mod_gm/reporte-expediente', '/mod_gm/mod_tram_exte/reporte-expediente');

// OMAPED público (QR)
Route::get('/mod_gp/omaped/entrada', [OmapedController::class, 'entrada'])->name('omaped.entrada');
Route::post('/mod_gp/omaped/entrada', [OmapedController::class, 'entrada'])->name('omaped.entrada.post');
Route::get('/mod_gp/omaped/ver-datos', [OmapedController::class, 'verDatos'])->name('omaped.ver_datos');
Route::get('/mod_gp/omaped/archivo-privado', [OmapedController::class, 'archivoPrivado'])->name('omaped.archivo_privado');
Route::get('/mod_gp/omaped/qr-imagen/{token}', [OmapedController::class, 'qrImagen'])->name('omaped.qr_imagen');

// SEGUIMIENTO PÚBLICO (QR)
Route::get('/tramite/seguimiento/{token}', [\App\Http\Controllers\Publico\SeguimientoPublicoController::class, 'consultar'])
    ->name('tramites.seguimiento_publico')
    ->middleware('throttle:30,1');

Route::middleware('auth')->group(function () {
    Route::get('/seleccionar-compania', [AuthController::class, 'seleccionarCompania'])->name('seleccionar.compania');
    Route::post('/seleccionar-compania', [AuthController::class, 'guardarCompania'])->name('seleccionar.compania.guardar');
    Route::get('/seleccionar-cargo', [AuthController::class, 'seleccionarCargo'])->name('seleccionar.cargo');
    Route::post('/seleccionar-cargo', [AuthController::class, 'guardarCargo'])->name('seleccionar.cargo.guardar');
    Route::get('/inicio', [AuthController::class, 'inicio'])->name('inicio');

    // NUEVA BANDEJA EXTERNA -> INGRESAR SERVICIOS
    Route::get('/mod_ti/helpdesk/index', [IngresarServiciosController::class, 'index'])->name('helpdesk.index');
    Route::post('/mod_ti/helpdesk/index/store', [IngresarServiciosController::class, 'store'])->name('helpdesk.index.store');
    Route::post('/mod_ti/helpdesk/index/detalle', [IngresarServiciosController::class, 'detalle'])->name('helpdesk.index.detalle');
    Route::post('/mod_ti/helpdesk/index/calificar', [IngresarServiciosController::class, 'calificar'])->name('helpdesk.index.calificar');
    Route::post('/mod_ti/helpdesk/index/anular', [IngresarServiciosController::class, 'anular'])->name('helpdesk.index.anular');
    Route::post('/mod_ti/helpdesk/index/tabla', [IngresarServiciosController::class, 'tablaExternos'])->name('helpdesk.index.tabla');
    Route::post('/mod_ti/helpdesk/index/update_contacto', [IngresarServiciosController::class, 'updateContacto'])->name('helpdesk.index.update_contacto');

    // NUEVA ADMINISTRACION -> CONSULTA (debe coincidir con de_dreccon del menú en BD)
    Route::get('/mod_ti/helpdesk/consulta', [ConsultasController::class, 'index'])->name('helpdesk.consulta');
    Route::post('/mod_ti/helpdesk/consulta/kanban', [ConsultasController::class, 'kanban'])->name('helpdesk.consulta.kanban');
    Route::post('/mod_ti/helpdesk/consulta/chart1', [ConsultasController::class, 'chart1'])->name('helpdesk.consulta.chart1');
    Route::post('/mod_ti/helpdesk/consulta/chart2', [ConsultasController::class, 'chart2'])->name('helpdesk.consulta.chart2');
    Route::post('/mod_ti/helpdesk/consulta/chart3', [ConsultasController::class, 'chart3'])->name('helpdesk.consulta.chart3');
    Route::post('/mod_ti/helpdesk/consulta/ticket-detalle', [ConsultasController::class, 'ticketDetalle'])->name('helpdesk.consulta.ticket-detalle');
    Route::post('/mod_ti/helpdesk/consulta/derivar', [ConsultasController::class, 'derivar'])->name('helpdesk.consulta.derivar');

    Route::get('/mod_ti/helpdesk/bandeja', [IngresarBandejaController::class, 'index'])->name('helpdesk.bandeja');
    Route::post('/mod_ti/helpdesk/bandeja/tabla', [IngresarBandejaController::class, 'tablaOti'])->name('helpdesk.bandeja.tabla');
    Route::post('/mod_ti/helpdesk/bandeja/ticket-detalle', [IngresarBandejaController::class, 'ticketDetalle'])->name('helpdesk.bandeja.ticket_detalle');
    Route::post('/mod_ti/helpdesk/bandeja/confirmar', [IngresarBandejaController::class, 'confirmarTicket'])->name('helpdesk.bandeja.confirmar');
    Route::post('/mod_ti/helpdesk/bandeja/finalizar', [IngresarBandejaController::class, 'finalizarTicket'])->name('helpdesk.bandeja.finalizar');
    Route::post('/mod_ti/helpdesk/bandeja/derivar', [IngresarBandejaController::class, 'derivarTicket'])->name('helpdesk.bandeja.derivar');
    Route::post('/mod_ti/helpdesk/bandeja/areas', [IngresarBandejaController::class, 'listarAreasJson'])->name('helpdesk.bandeja.areas');

    // TICKETS (pantalla TV) — menú BD: /mod_ti/helpdesk/pantalla
    Route::get('/mod_ti/helpdesk/pantalla', [TicketsController::class, 'index'])->name('helpdesk.pantalla');
    Route::get('/mod_ti/helpdesk/pantalla/datos', [TicketsController::class, 'datos'])->name('helpdesk.pantalla.datos');

    // DISPOSITIVOS DE SEGURIDAD
    Route::get('/mod_ti/dispositivo_seg', [DispositivoSegController::class, 'index'])->name('dispositivo_seg.index');
    Route::get('/mod_ti/dispositivo_seg/index', [DispositivoSegController::class, 'index'])->name('dispositivo_seg.index.alt');
    Route::get('/mod_ti/dispositivo_seg/mapa', [DispositivoSegController::class, 'mapa'])->name('dispositivo_seg.mapa');
    Route::get('/mod_ti/dispositivo_seg/graficos', [DispositivoSegController::class, 'graficos'])->name('dispositivo_seg.graficos');
    Route::get('/mod_ti/dispositivo_seg/listar', [DispositivoSegController::class, 'listar'])->name('dispositivo_seg.listar');
    Route::get('/mod_ti/dispositivo_seg/detalle', [DispositivoSegController::class, 'detalle'])->name('dispositivo_seg.detalle');
    Route::post('/mod_ti/dispositivo_seg/guardar', [DispositivoSegController::class, 'guardar'])->name('dispositivo_seg.guardar');
    Route::post('/mod_ti/dispositivo_seg/actualizar', [DispositivoSegController::class, 'actualizar'])->name('dispositivo_seg.actualizar');
    Route::post('/mod_ti/dispositivo_seg/eliminar', [DispositivoSegController::class, 'eliminar'])->name('dispositivo_seg.eliminar');
    Route::get('/mod_ti/dispositivo_seg/sectores', [DispositivoSegController::class, 'sectores'])->name('dispositivo_seg.sectores');
    Route::get('/mod_ti/dispositivo_seg/subsectores', [DispositivoSegController::class, 'subsectores'])->name('dispositivo_seg.subsectores');
    Route::get('/mod_ti/dispositivo_seg/exportar', [DispositivoSegController::class, 'exportar'])->name('dispositivo_seg.exportar');
    Route::get('/mod_ti/dispositivo_seg/estadisticas', [DispositivoSegController::class, 'estadisticas'])->name('dispositivo_seg.estadisticas');
    Route::post('/mod_ti/dispositivo_seg/ping', [DispositivoSegController::class, 'ping'])->name('dispositivo_seg.ping');
    Route::get('/mod_ti/dispositivo_seg/zonas-geojson', [DispositivoSegController::class, 'zonasGeojson'])->name('dispositivo_seg.zonas_geojson');
    Route::get('/mod_ti/dispositivo_seg/sectores-geojson', [DispositivoSegController::class, 'sectoresGeojson'])->name('dispositivo_seg.sectores_geojson');
    Route::get('/mod_ti/dispositivo_seg/subsectores-geojson', [DispositivoSegController::class, 'subsectoresGeojson'])->name('dispositivo_seg.subsectores_geojson');
    Route::get('/mod_ti/dispositivo_seg/reporte-visores', [DispositivoSegController::class, 'reporteVisores'])->name('dispositivo_seg.reporte_visores');
    Route::get('/mod_ti/dispositivo_seg/stream', [\App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegStreamController::class, 'stream'])->name('dispositivo_seg.stream');
    Route::get('/mod_ti/dispositivo_seg/tail-log', [\App\Http\Controllers\mod_ti\dispositivo_seg\DispositivoSegStreamController::class, 'tailLog'])->name('dispositivo_seg.tail_log');

    // Generador de firmas de correo
    Route::get('/mod_ti/firma_email', [FirmaEmailController::class, 'index'])->name('firma_email.index');
    Route::get('/mod_ti/firma_email/index', [FirmaEmailController::class, 'index'])->name('firma_email.index.alt');
    Route::post('/mod_ti/firma_email/buscar-dni', [FirmaEmailController::class, 'buscarPorDni'])->name('firma_email.buscar_dni');
    Route::post('/mod_ti/firma_email/generar-individual', [FirmaEmailController::class, 'generarIndividual'])->name('firma_email.generar_individual');
    Route::post('/mod_ti/firma_email/generar-masivo', [FirmaEmailController::class, 'generarMasivo'])->name('firma_email.generar_masivo');
    Route::get('/mod_ti/firma_email/plantilla', [FirmaEmailController::class, 'descargarPlantilla'])->name('firma_email.plantilla');
    Route::get('/mod_ti/firma_email/descargar', [FirmaEmailController::class, 'descargarTemp'])->name('firma_email.descargar_temp');

    // Reportes de publicidad estatal
    Route::get('/mod_ti/reporte_publicidad', [ReportePublicidadController::class, 'index'])->name('reporte_publicidad.index');
    Route::get('/mod_ti/reporte_publicidad/index', [ReportePublicidadController::class, 'index'])->name('reporte_publicidad.index.alt');
    Route::get('/mod_ti/reporte_publicidad/listar', [ReportePublicidadController::class, 'listar'])->name('reporte_publicidad.listar');
    Route::get('/mod_ti/reporte_publicidad/cuadros', [ReportePublicidadController::class, 'cuadros'])->name('reporte_publicidad.cuadros');
    Route::get('/mod_ti/reporte_publicidad/editar', [ReportePublicidadController::class, 'editar'])->name('reporte_publicidad.editar');
    Route::post('/mod_ti/reporte_publicidad/guardar', [ReportePublicidadController::class, 'guardar'])->name('reporte_publicidad.guardar');
    Route::post('/mod_ti/reporte_publicidad/eliminar', [ReportePublicidadController::class, 'eliminar'])->name('reporte_publicidad.eliminar');
    Route::get('/mod_ti/reporte_publicidad/pdf', [ReportePublicidadController::class, 'pdf'])->name('reporte_publicidad.pdf');
    Route::get('/mod_ti/reporte_publicidad/imagen', [ReportePublicidadController::class, 'imagen'])->name('reporte_publicidad.imagen');

    // Reportes de publicidad estatal 2026
    Route::get('/mod_ti/reporte_publicidad_2026', [ReportePublicidad2026Controller::class, 'index'])->name('reporte_publicidad_2026.index');
    Route::get('/mod_ti/reporte_publicidad_2026/index', [ReportePublicidad2026Controller::class, 'index'])->name('reporte_publicidad_2026.index.alt');
    Route::get('/mod_ti/reporte_publicidad_2026/formulario', [ReportePublicidad2026Controller::class, 'formulario'])->name('reporte_publicidad_2026.formulario');
    Route::get('/mod_ti/reporte_publicidad_2026/listar', [ReportePublicidad2026Controller::class, 'listar'])->name('reporte_publicidad_2026.listar');
    Route::get('/mod_ti/reporte_publicidad_2026/obtener', [ReportePublicidad2026Controller::class, 'obtener'])->name('reporte_publicidad_2026.obtener');
    Route::post('/mod_ti/reporte_publicidad_2026/guardar', [ReportePublicidad2026Controller::class, 'guardar'])->name('reporte_publicidad_2026.guardar');
    Route::post('/mod_ti/reporte_publicidad_2026/eliminar', [ReportePublicidad2026Controller::class, 'eliminar'])->name('reporte_publicidad_2026.eliminar');
    Route::get('/mod_ti/reporte_publicidad_2026/pdf', [ReportePublicidad2026Controller::class, 'pdf'])->name('reporte_publicidad_2026.pdf');
    Route::get('/mod_ti/reporte_publicidad_2026/imagen', [ReportePublicidad2026Controller::class, 'imagen'])->name('reporte_publicidad_2026.imagen');

    // OMAPED (Gerencia de Prevención)
    Route::get('/mod_gp/omaped', [OmapedController::class, 'index'])->name('omaped.index');
    Route::get('/mod_gp/omaped/index', [OmapedController::class, 'index'])->name('omaped.index.alt');
    Route::get('/mod_gp/omaped/listar', [OmapedController::class, 'listar'])->name('omaped.listar');
    Route::get('/mod_gp/omaped/detalle', [OmapedController::class, 'detalle'])->name('omaped.detalle');
    Route::get('/mod_gp/omaped/catalogos', [OmapedController::class, 'catalogos'])->name('omaped.catalogos');
    Route::get('/mod_gp/omaped/buscar-documento', [OmapedController::class, 'buscarDocumento'])->name('omaped.buscar_documento');
    Route::post('/mod_gp/omaped/guardar', [OmapedController::class, 'guardar'])->name('omaped.guardar');
    Route::post('/mod_gp/omaped/actualizar', [OmapedController::class, 'actualizar'])->name('omaped.actualizar');
    Route::post('/mod_gp/omaped/eliminar', [OmapedController::class, 'eliminar'])->name('omaped.eliminar');
    Route::post('/mod_gp/omaped/historico-guardar', [OmapedController::class, 'historicoGuardar'])->name('omaped.historico_guardar');
    Route::post('/mod_gp/omaped/toggle-pariente', [OmapedController::class, 'togglePariente'])->name('omaped.toggle_pariente');
    Route::get('/mod_gp/omaped/qr-png', [OmapedController::class, 'qrPng'])->name('omaped.qr_png');

    // AUDIENCIAS PÚBLICAS (Promesas / Compromisos)
    Route::get('/mod_promesa', [PromesaController::class, 'index'])->name('promesa.index');
    Route::get('/mod_promesa/index', [PromesaController::class, 'index'])->name('promesa.index.alt');
    Route::get('/mod_promesa/listar-lugares', [PromesaController::class, 'listarLugares'])->name('promesa.listar_lugares');
    Route::get('/mod_promesa/detalle-lugar', [PromesaController::class, 'detalleLugar'])->name('promesa.detalle_lugar');
    Route::get('/mod_promesa/obtener-lugar', [PromesaController::class, 'obtenerLugar'])->name('promesa.obtener_lugar');
    Route::post('/mod_promesa/guardar-lugar', [PromesaController::class, 'guardarLugar'])->name('promesa.guardar_lugar');
    Route::post('/mod_promesa/actualizar-lugar', [PromesaController::class, 'actualizarLugar'])->name('promesa.actualizar_lugar');
    Route::post('/mod_promesa/eliminar-lugar', [PromesaController::class, 'eliminarLugar'])->name('promesa.eliminar_lugar');
    Route::get('/mod_promesa/listar-promesas', [PromesaController::class, 'listarPromesas'])->name('promesa.listar_promesas');
    Route::get('/mod_promesa/obtener-promesa', [PromesaController::class, 'obtenerPromesa'])->name('promesa.obtener_promesa');
    Route::post('/mod_promesa/guardar-promesa', [PromesaController::class, 'guardarPromesa'])->name('promesa.guardar_promesa');
    Route::post('/mod_promesa/actualizar-promesa', [PromesaController::class, 'actualizarPromesa'])->name('promesa.actualizar_promesa');
    Route::post('/mod_promesa/eliminar-promesa', [PromesaController::class, 'eliminarPromesa'])->name('promesa.eliminar_promesa');
    Route::get('/mod_promesa/listar-seguimiento', [PromesaController::class, 'listarSeguimiento'])->name('promesa.listar_seguimiento');
    Route::post('/mod_promesa/guardar-actividad', [PromesaController::class, 'guardarActividad'])->name('promesa.guardar_actividad');
    Route::post('/mod_promesa/extender-actividad', [PromesaController::class, 'extenderActividad'])->name('promesa.extender_actividad');
    Route::post('/mod_promesa/finalizar-actividad', [PromesaController::class, 'finalizarActividad'])->name('promesa.finalizar_actividad');
    Route::post('/mod_promesa/eliminar-actividad', [PromesaController::class, 'eliminarActividad'])->name('promesa.eliminar_actividad');
    Route::post('/mod_promesa/finalizar-seguimiento', [PromesaController::class, 'finalizarSeguimiento'])->name('promesa.finalizar_seguimiento');
    Route::get('/mod_promesa/catalogos', [PromesaController::class, 'catalogos'])->name('promesa.catalogos');
    Route::get('/mod_promesa/personal-por-area', [PromesaController::class, 'personalPorArea'])->name('promesa.personal');
    Route::get('/mod_promesa/imprimir', [PromesaController::class, 'imprimir'])->name('promesa.imprimir');
    Route::get('/mod_promesa/form-reunion', [PromesaController::class, 'formReunion'])->name('promesa.form_reunion');
    Route::get('/mod_promesa/form-promesa', [PromesaController::class, 'formPromesa'])->name('promesa.form_promesa');
    Route::get('/mod_promesa/form-seguimiento', [PromesaController::class, 'formSeguimiento'])->name('promesa.form_seguimiento');
    Route::get('/mod_promesa/form-eliminar-reunion', [PromesaController::class, 'formEliminarReunion'])->name('promesa.form_eliminar_reunion');
    Route::get('/mod_promesa/form-eliminar-promesa', [PromesaController::class, 'formEliminarPromesa'])->name('promesa.form_eliminar_promesa');

    // Pago a proveedores — principal (página GET vía menú BD / catch-all)
    Route::match(['get', 'post'], '/mod_a/sgl/pago_proveedores/api/listar', [PagoProveedoresIndexController::class, 'listar'])->name('sgl.pago_proveedores.listar');
    Route::post('/mod_a/sgl/pago_proveedores/api/detalle', [PagoProveedoresIndexController::class, 'detalle'])->name('sgl.pago_proveedores.detalle');
    Route::post('/mod_a/sgl/pago_proveedores/api/guardar', [PagoProveedoresIndexController::class, 'guardar'])->name('sgl.pago_proveedores.guardar');
    Route::post('/mod_a/sgl/pago_proveedores/api/anular', [PagoProveedoresIndexController::class, 'anular'])->name('sgl.pago_proveedores.anular');
    Route::post('/mod_a/sgl/pago_proveedores/api/finalizar', [PagoProveedoresIndexController::class, 'finalizar'])->name('sgl.pago_proveedores.finalizar');
    Route::post('/mod_a/sgl/pago_proveedores/api/baja-item', [PagoProveedoresIndexController::class, 'bajaItem'])->name('sgl.pago_proveedores.baja_item');
    Route::post('/mod_a/sgl/pago_proveedores/api/semilla', [PagoProveedoresIndexController::class, 'semilla'])->name('sgl.pago_proveedores.semilla');
    Route::post('/mod_a/sgl/pago_proveedores/api/generar-tramite', [PagoProveedoresIndexController::class, 'generarTramite'])->name('sgl.pago_proveedores.generar_tramite');
    Route::post('/mod_a/sgl/pago_proveedores/api/generar-memo', [PagoProveedoresIndexController::class, 'generarMemo'])->name('sgl.pago_proveedores.generar_memo');
    Route::post('/mod_a/sgl/pago_proveedores/api/item-detalle', [PagoProveedoresIndexController::class, 'itemDetalle'])->name('sgl.pago_proveedores.item_detalle');
    Route::post('/mod_a/sgl/pago_proveedores/api/guardar-tdr', [PagoProveedoresIndexController::class, 'guardarTdrSiga'])->name('sgl.pago_proveedores.guardar_tdr');
    Route::post('/mod_a/sgl/pago_proveedores/api/guardar-anexos', [PagoProveedoresIndexController::class, 'guardarAnexos'])->name('sgl.pago_proveedores.guardar_anexos');
    Route::post('/mod_a/sgl/pago_proveedores/api/guardar-conformidad', [PagoProveedoresIndexController::class, 'guardarConformidad'])->name('sgl.pago_proveedores.guardar_conformidad');
    Route::get('/mod_a/sgl/pago_proveedores/api/archivo', [PagoProveedoresIndexController::class, 'archivo'])->name('sgl.pago_proveedores.archivo');

    // Pago a proveedores — Finanzas (página GET vía menú BD / catch-all)
    Route::match(['get', 'post'], '/mod_a/sgl/pago_proveedores/finanzas/listar', [VerRequerimientoFinanzasController::class, 'listar'])->name('sgl.pago_proveedores.finanzas.listar');
    Route::post('/mod_a/sgl/pago_proveedores/finanzas/detalle', [VerRequerimientoFinanzasController::class, 'detalle'])->name('sgl.pago_proveedores.finanzas.detalle');
    Route::post('/mod_a/sgl/pago_proveedores/finanzas/item-detalle', [VerRequerimientoFinanzasController::class, 'itemDetalle'])->name('sgl.pago_proveedores.finanzas.item_detalle');
    Route::post('/mod_a/sgl/pago_proveedores/finanzas/finalizar', [VerRequerimientoFinanzasController::class, 'finalizar'])->name('sgl.pago_proveedores.finanzas.finalizar');
    Route::post('/mod_a/sgl/pago_proveedores/finanzas/devolver', [VerRequerimientoFinanzasController::class, 'devolver'])->name('sgl.pago_proveedores.finanzas.devolver');
    Route::get('/mod_a/sgl/pago_proveedores/finanzas/archivo', [VerRequerimientoFinanzasController::class, 'archivo'])->name('sgl.pago_proveedores.finanzas.archivo');

    // Pago a proveedores — Locador (página GET vía menú BD / catch-all)
    Route::match(['get', 'post'], '/mod_a/sgl/pago_proveedores/locador/listar', [VerRequerimientoLocadorController::class, 'listar'])->name('sgl.pago_proveedores.locador.listar');
    Route::post('/mod_a/sgl/pago_proveedores/locador/detalle', [VerRequerimientoLocadorController::class, 'detalle'])->name('sgl.pago_proveedores.locador.detalle');
    Route::post('/mod_a/sgl/pago_proveedores/locador/item-detalle', [VerRequerimientoLocadorController::class, 'itemDetalle'])->name('sgl.pago_proveedores.locador.item_detalle');
    Route::post('/mod_a/sgl/pago_proveedores/locador/guardar-tdr', [VerRequerimientoLocadorController::class, 'guardarTdrSiga'])->name('sgl.pago_proveedores.locador.guardar_tdr');
    Route::post('/mod_a/sgl/pago_proveedores/locador/guardar-anexos', [VerRequerimientoLocadorController::class, 'guardarAnexos'])->name('sgl.pago_proveedores.locador.guardar_anexos');
    Route::post('/mod_a/sgl/pago_proveedores/locador/guardar-conformidad', [VerRequerimientoLocadorController::class, 'guardarConformidad'])->name('sgl.pago_proveedores.locador.guardar_conformidad');
    Route::post('/mod_a/sgl/pago_proveedores/locador/baja-item', [VerRequerimientoLocadorController::class, 'bajaItem'])->name('sgl.pago_proveedores.locador.baja_item');
    Route::post('/mod_a/sgl/pago_proveedores/locador/guardar-plantilla', [VerRequerimientoLocadorController::class, 'guardarPlantilla'])->name('sgl.pago_proveedores.locador.guardar_plantilla');
    Route::post('/mod_a/sgl/pago_proveedores/locador/ver-plantilla', [VerRequerimientoLocadorController::class, 'verPlantilla'])->name('sgl.pago_proveedores.locador.ver_plantilla');
    Route::get('/mod_a/sgl/pago_proveedores/locador/archivo', [VerRequerimientoLocadorController::class, 'archivo'])->name('sgl.pago_proveedores.locador.archivo');

    // Pago a proveedores — Logística (página GET vía menú BD / catch-all)
    Route::match(['get', 'post'], '/mod_a/sgl/pago_proveedores/logistica/listar', [VerRequerimientoLogisticaController::class, 'listar'])->name('sgl.pago_proveedores.logistica.listar');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/detalle', [VerRequerimientoLogisticaController::class, 'detalle'])->name('sgl.pago_proveedores.logistica.detalle');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/item-detalle', [VerRequerimientoLogisticaController::class, 'itemDetalle'])->name('sgl.pago_proveedores.logistica.item_detalle');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/semilla', [VerRequerimientoLogisticaController::class, 'semilla'])->name('sgl.pago_proveedores.logistica.semilla');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/publicar', [VerRequerimientoLogisticaController::class, 'publicar'])->name('sgl.pago_proveedores.logistica.publicar');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/actualizar-campo', [VerRequerimientoLogisticaController::class, 'actualizarCampo'])->name('sgl.pago_proveedores.logistica.actualizar_campo');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/actualizar-monto', [VerRequerimientoLogisticaController::class, 'actualizarMontoMemo'])->name('sgl.pago_proveedores.logistica.actualizar_monto');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/guardar-habilitacion', [VerRequerimientoLogisticaController::class, 'guardarHabilitacion'])->name('sgl.pago_proveedores.logistica.guardar_habilitacion');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/guardar-os', [VerRequerimientoLogisticaController::class, 'guardarOs'])->name('sgl.pago_proveedores.logistica.guardar_os');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/anular-migra', [VerRequerimientoLogisticaController::class, 'anularMigra'])->name('sgl.pago_proveedores.logistica.anular_migra');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/buscar-proveedor', [VerRequerimientoLogisticaController::class, 'buscarProveedor'])->name('sgl.pago_proveedores.logistica.buscar_proveedor');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/guardar-proveedor', [VerRequerimientoLogisticaController::class, 'guardarProveedor'])->name('sgl.pago_proveedores.logistica.guardar_proveedor');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/subir-zip', [VerRequerimientoLogisticaController::class, 'subirZip'])->name('sgl.pago_proveedores.logistica.subir_zip');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/enviar-finanzas', [VerRequerimientoLogisticaController::class, 'enviarFinanzas'])->name('sgl.pago_proveedores.logistica.enviar_finanzas');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/enviar-informe', [VerRequerimientoLogisticaController::class, 'enviarInforme'])->name('sgl.pago_proveedores.logistica.enviar_informe');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/enviar-presupuesto', [VerRequerimientoLogisticaController::class, 'enviarPresupuesto'])->name('sgl.pago_proveedores.logistica.enviar_presupuesto');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/devolver', [VerRequerimientoLogisticaController::class, 'devolver'])->name('sgl.pago_proveedores.logistica.devolver');
    Route::post('/mod_a/sgl/pago_proveedores/logistica/ver-borrador', [VerRequerimientoLogisticaController::class, 'verBorrador'])->name('sgl.pago_proveedores.logistica.ver_borrador');
    Route::get('/mod_a/sgl/pago_proveedores/logistica/archivo', [VerRequerimientoLogisticaController::class, 'archivo'])->name('sgl.pago_proveedores.logistica.archivo');

    // Pago a proveedores — Presupuesto (página GET vía menú BD / catch-all)
    Route::match(['get', 'post'], '/mod_a/sgl/pago_proveedores/presupuesto/listar', [VerRequerimientoPresupuestoController::class, 'listar'])->name('sgl.pago_proveedores.presupuesto.listar');
    Route::post('/mod_a/sgl/pago_proveedores/presupuesto/detalle', [VerRequerimientoPresupuestoController::class, 'detalle'])->name('sgl.pago_proveedores.presupuesto.detalle');
    Route::post('/mod_a/sgl/pago_proveedores/presupuesto/item-detalle', [VerRequerimientoPresupuestoController::class, 'itemDetalle'])->name('sgl.pago_proveedores.presupuesto.item_detalle');
    Route::post('/mod_a/sgl/pago_proveedores/presupuesto/semilla', [VerRequerimientoPresupuestoController::class, 'semilla'])->name('sgl.pago_proveedores.presupuesto.semilla');
    Route::post('/mod_a/sgl/pago_proveedores/presupuesto/publicar', [VerRequerimientoPresupuestoController::class, 'publicar'])->name('sgl.pago_proveedores.presupuesto.publicar');
    Route::post('/mod_a/sgl/pago_proveedores/presupuesto/actualizar-siaf', [VerRequerimientoPresupuestoController::class, 'actualizarSiaf'])->name('sgl.pago_proveedores.presupuesto.actualizar_siaf');
    Route::post('/mod_a/sgl/pago_proveedores/presupuesto/guardar-habilitacion', [VerRequerimientoPresupuestoController::class, 'guardarHabilitacion'])->name('sgl.pago_proveedores.presupuesto.guardar_habilitacion');
    Route::post('/mod_a/sgl/pago_proveedores/presupuesto/enviar-abastecimiento', [VerRequerimientoPresupuestoController::class, 'enviarAbastecimiento'])->name('sgl.pago_proveedores.presupuesto.enviar_abastecimiento');
    Route::post('/mod_a/sgl/pago_proveedores/presupuesto/devolver', [VerRequerimientoPresupuestoController::class, 'devolver'])->name('sgl.pago_proveedores.presupuesto.devolver');
    Route::post('/mod_a/sgl/pago_proveedores/presupuesto/ver-borrador', [VerRequerimientoPresupuestoController::class, 'verBorrador'])->name('sgl.pago_proveedores.presupuesto.ver_borrador');
    Route::get('/mod_a/sgl/pago_proveedores/presupuesto/archivo', [VerRequerimientoPresupuestoController::class, 'archivo'])->name('sgl.pago_proveedores.presupuesto.archivo');

    // Pago a proveedores — Tesorería (página GET vía menú BD / catch-all)
    Route::match(['get', 'post'], '/mod_a/sgl/pago_proveedores/tesoreria/listar', [VerRequerimientoTesoreriaController::class, 'listar'])->name('sgl.pago_proveedores.tesoreria.listar');
    Route::post('/mod_a/sgl/pago_proveedores/tesoreria/detalle', [VerRequerimientoTesoreriaController::class, 'detalle'])->name('sgl.pago_proveedores.tesoreria.detalle');
    Route::post('/mod_a/sgl/pago_proveedores/tesoreria/item-detalle', [VerRequerimientoTesoreriaController::class, 'itemDetalle'])->name('sgl.pago_proveedores.tesoreria.item_detalle');
    Route::post('/mod_a/sgl/pago_proveedores/tesoreria/terminar', [VerRequerimientoTesoreriaController::class, 'terminar'])->name('sgl.pago_proveedores.tesoreria.terminar');
    Route::post('/mod_a/sgl/pago_proveedores/tesoreria/form-comprobante', [VerRequerimientoTesoreriaController::class, 'formComprobante'])->name('sgl.pago_proveedores.tesoreria.form_comprobante');
    Route::post('/mod_a/sgl/pago_proveedores/tesoreria/guardar-comprobante', [VerRequerimientoTesoreriaController::class, 'guardarComprobante'])->name('sgl.pago_proveedores.tesoreria.guardar_comprobante');
    Route::get('/mod_a/sgl/pago_proveedores/tesoreria/archivo', [VerRequerimientoTesoreriaController::class, 'archivo'])->name('sgl.pago_proveedores.tesoreria.archivo');

    // Fichas de selección SE@CE (página GET vía menú BD / catch-all)
    Route::post('/mod_a/sgl/pago_proveedores/ficha-seleccion/buscar', [FichaSeleccionSeaceController::class, 'buscar'])->name('sgl.ficha_seleccion.buscar');
    Route::post('/mod_a/sgl/pago_proveedores/ficha-seleccion/detalle', [FichaSeleccionSeaceController::class, 'detalle'])->name('sgl.ficha_seleccion.detalle');
    Route::post('/mod_a/sgl/pago_proveedores/ficha-seleccion/guardar', [FichaSeleccionSeaceController::class, 'guardar'])->name('sgl.ficha_seleccion.guardar');
    Route::post('/mod_a/sgl/pago_proveedores/ficha-seleccion/cronograma', [FichaSeleccionSeaceController::class, 'cronogramaFecha'])->name('sgl.ficha_seleccion.cronograma');

    // BUSCAR REQUERIMIENTO DE PAGO (solo consulta)
    // Buscar requerimiento de pago (SGL / pago_proveedores)
    Route::get('/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago', [BuscarRequerimientoPagoController::class, 'index'])->name('sgl.buscar_requerimiento_pago');
    Route::get('/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago/listar', [BuscarRequerimientoPagoController::class, 'listar'])->name('sgl.buscar_requerimiento_pago.listar');
    Route::get('/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago/detalle', [BuscarRequerimientoPagoController::class, 'detalle'])->name('sgl.buscar_requerimiento_pago.detalle');
    Route::get('/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago/archivo', [BuscarRequerimientoPagoController::class, 'archivo'])->name('sgl.buscar_requerimiento_pago.archivo');
    Route::get('/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago/seguimiento-tramite', [BuscarRequerimientoPagoController::class, 'seguimientoTramite'])->name('sgl.buscar_requerimiento_pago.seguimiento_tramite');
    Route::get('/mod_a/sgl/pago_proveedores/buscar_requerimiento_pago/voucher-movimiento', [BuscarRequerimientoPagoController::class, 'voucherMovimiento'])->name('sgl.buscar_requerimiento_pago.voucher_movimiento');

    // Reporte gerencial de pago a proveedores
    Route::match(['get', 'post'], '/mod_a/sgl/pago_proveedores/reporte/reporte_gerencial', [ReporteGerencialController::class, 'index'])->name('sgl.reporte_gerencial');

    // Reporte general de requerimientos de servicios (v2)
    Route::match(['get', 'post'], '/mod_a/sgl/pago_proveedores/v2_lista_reporte_requerimientos_general', [V2ListaReporteRequerimientosGeneralController::class, 'index'])->name('sgl.v2_lista_reporte_requerimientos_general');
    Route::get('/mod_a/sgl/pago_proveedores/v2_lista_reporte_requerimientos_general/detalle', [V2ListaReporteRequerimientosGeneralController::class, 'detalle'])->name('sgl.v2_lista_reporte_requerimientos_general.detalle');
    Route::get('/mod_a/sgl/pago_proveedores/v2_lista_reporte_requerimientos_general/export-detalle', [V2ListaReporteRequerimientosGeneralController::class, 'exportDetalle'])->name('sgl.v2_lista_reporte_requerimientos_general.export_detalle');
    Route::get('/mod_a/sgl/pago_proveedores/v2_lista_reporte_requerimientos_general/export-areas', [V2ListaReporteRequerimientosGeneralController::class, 'exportAreas'])->name('sgl.v2_lista_reporte_requerimientos_general.export_areas');

    Route::get('/mod_gene/mod_tram_inter/buscar_tramite', [BuscarTramiteController::class, 'index'])->name('mod_tram_inter.buscar_tramite');
    Route::get('/mod_gene/mod_tram_inter/buscar_tramite/detalle', [BuscarTramiteController::class, 'detalle'])->name('mod_tram_inter.buscar_tramite.detalle');
    Route::get('/mod_gene/mod_tram_inter/buscar_tramite/tipos', [BuscarTramiteController::class, 'tiposPorArea'])->name('mod_tram_inter.buscar_tramite.tipos');
    Route::post('/mod_gene/mod_tram_inter/buscar_tramite/historial', [BuscarTramiteController::class, 'historial'])->name('mod_tram_inter.buscar_tramite.historial');
    Route::get('/mod_gene/mod_tram_inter/descargar', [BuscarTramiteController::class, 'descargar'])->name('mod_tram_inter.descargar');
    // Compatibilidad con URL legacy (busca_imagen.php?nom_archivo=priXXX.zip)
    Route::get('/mod_gene/mod_tram_inter/adjunto/busca_imagen.php', [BuscarTramiteController::class, 'descargar'])->name('mod_tram_inter.adjunto.busca_imagen');

    // INGRESAR TRAMITES INTERNOS (NUEVO)
    Route::get('/mod_gene/mod_tram_inter/ingresar_tramite', [IngresarTramiteController::class, 'index'])->name('mod_tram_inter.ingresar_tramite');
    Route::get('/mod_gene/mod_tram_inter/ingresar_tramite/ajax/semilla', [IngresarTramiteController::class, 'getSemilla'])->name('mod_tram_inter.ingresar_tramite.semilla');
    Route::get('/mod_gene/mod_tram_inter/ingresar_tramite/ajax/cargos', [IngresarTramiteController::class, 'getCargosArea'])->name('mod_tram_inter.ingresar_tramite.cargos');
    Route::post('/mod_gene/mod_tram_inter/ingresar_tramite/generar-borrador', [IngresarTramiteController::class, 'generarBorrador'])->name('mod_tram_inter.ingresar_tramite.generar_borrador');
    Route::post('/mod_gene/mod_tram_inter/ingresar_tramite/store', [IngresarTramiteController::class, 'store'])->name('mod_tram_inter.ingresar_tramite.store');

    // CORREO INTERNO (NUEVO)
    Route::get('/mod_gene/mod_tram_inter/correo_interno', [CorreoInternoController::class, 'index'])->name('mod_tram_inter.correo_interno');
    Route::post('/mod_gene/mod_tram_inter/correo_interno/tabla', [CorreoInternoController::class, 'tabla'])->name('mod_tram_inter.correo_interno.tabla');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/conteos', [CorreoInternoController::class, 'conteos'])->name('mod_tram_inter.correo_interno.conteos');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/participantes', [CorreoInternoController::class, 'participantes'])->name('mod_tram_inter.correo_interno.participantes');
    Route::post('/mod_gene/mod_tram_inter/correo_interno/historial', [CorreoInternoController::class, 'historial'])->name('mod_tram_inter.correo_interno.historial');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/auditoria_descargas', [CorreoInternoController::class, 'auditoriaDescargas'])->name('mod_tram_inter.correo_interno.auditoria_descargas');
    Route::post('/mod_gene/mod_tram_inter/correo_interno/recepcionar', [CorreoInternoController::class, 'recepcionar'])->name('mod_tram_inter.correo_interno.recepcionar');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/detalle_documento/{id}', [CorreoInternoController::class, 'detalleDocumento'])->name('mod_tram_inter.correo_interno.detalle_documento');
    Route::post('/mod_gene/mod_tram_inter/correo_interno/reemplazar_firmado', [CorreoInternoController::class, 'reemplazarFirmado'])->name('mod_tram_inter.correo_interno.reemplazar_firmado');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/comprobantes_pago', [CorreoInternoController::class, 'comprobantesPago'])->name('mod_tram_inter.correo_interno.comprobantes_pago');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/voucher/{id}', [CorreoInternoController::class, 'voucher'])->name('mod_tram_inter.correo_interno.voucher');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/descargar-completo/{id}', [CorreoInternoController::class, 'descargarCompleto'])->name('mod_tram_inter.correo_interno.descargar_completo');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/descargar-lote', [CorreoInternoController::class, 'descargarLote'])->name('mod_tram_inter.correo_interno.descargar_lote');
    Route::match(['get', 'post'], '/mod_gene/mod_tram_inter/correo_interno/imprimir_bandeja', [CorreoInternoController::class, 'imprimirBandeja'])->name('mod_tram_inter.correo_interno.imprimir_bandeja');

    // SPEC-03: Rutas Transaccionales y Modales
    Route::get('/mod_gene/mod_tram_inter/correo_interno/datos_transaccionales', [CorreoInternoController::class, 'datosTransaccionales'])->name('mod_tram_inter.correo_interno.datos_transaccionales');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/correlativo_interno', [CorreoInternoController::class, 'correlativoInterno'])->name('mod_tram_inter.correo_interno.correlativo_interno');
    Route::post('/mod_gene/mod_tram_inter/correo_interno/asignar', [CorreoInternoController::class, 'asignar'])->name('mod_tram_inter.correo_interno.asignar');
    Route::post('/mod_gene/mod_tram_inter/correo_interno/derivar', [CorreoInternoController::class, 'derivar'])->name('mod_tram_inter.correo_interno.derivar');
    Route::post('/mod_gene/mod_tram_inter/correo_interno/finalizar', [CorreoInternoController::class, 'finalizar'])->name('mod_tram_inter.correo_interno.finalizar');
    Route::post('/mod_gene/mod_tram_inter/correo_interno/devolver', [CorreoInternoController::class, 'devolver'])->name('mod_tram_inter.correo_interno.devolver');
    Route::post('/mod_gene/mod_tram_inter/correo_interno/reiterar', [CorreoInternoController::class, 'reiterar'])->name('mod_tram_inter.correo_interno.reiterar');
    Route::get('/mod_gene/mod_tram_inter/correo_interno/hoja_ruta/{id}', [CorreoInternoController::class, 'hojaRuta'])->name('mod_tram_inter.correo_interno.hoja_ruta');

    // REPORTE GENERAL DE TRÁMITES INTERNOS (= lista_reporte_area.php)
    Route::match(['get', 'post'], '/mod_gene/mod_tram_inter/reporte_tramite', [ReporteTramiteController::class, 'index'])->name('mod_tram_inter.reporte_tramite');
    Route::match(['get', 'post'], '/mod_gene/mod_tram_inter/reporte-tramite', [ReporteTramiteController::class, 'index']);
    Route::match(['get', 'post'], '/mod_gene/mod_tram_inter/lista_reporte_area', [ReporteTramiteController::class, 'index']);
    Route::post('/mod_gene/mod_tram_inter/reporte_tramite/detalle', [ReporteTramiteController::class, 'ajaxDetalle'])->name('mod_tram_inter.reporte_tramite.detalle');

    // SECUENCIAL DEL ÁREA (= ver_secuencial_area.php) — nombre igual que en BD
    Route::match(['get', 'post'], '/mod_gene/mod_tram_inter/ver_secuencial_area', [SecuencialTramiteController::class, 'index'])->name('mod_tram_inter.ver_secuencial_area');
    Route::post('/mod_gene/mod_tram_inter/ver_secuencial_area/lista', [SecuencialTramiteController::class, 'lista'])->name('mod_tram_inter.ver_secuencial_area.lista');

    // REPORTE DE CARGO INTERNO (= lista_reporte_area_cargo.php)
    Route::match(['get', 'post'], '/mod_gene/mod_tram_inter/reporte_cargo_interno', [ReporteCargoInternoController::class, 'index'])->name('mod_tram_inter.reporte_cargo_interno');
    Route::match(['get', 'post'], '/mod_gene/mod_tram_inter/reporte-cargo-interno', [ReporteCargoInternoController::class, 'index']);
    Route::match(['get', 'post'], '/mod_gene/mod_tram_inter/lista_reporte_area_cargo', [ReporteCargoInternoController::class, 'index']);
    Route::post('/mod_gene/mod_tram_inter/reporte_cargo_interno/detalle', [ReporteCargoInternoController::class, 'ajaxDetalle'])->name('mod_tram_inter.reporte_cargo_interno.detalle');

    // DASHBOARD ANALÍTICO POR CARGO (= v2_lista_reporte_area_cargo.php)
    Route::match(['get', 'post'], '/mod_gene/mod_tram_inter/v2_lista_reporte_area_cargo', [V2ListaReporteAreaCargoController::class, 'index'])->name('mod_tram_inter.v2_lista_reporte_area_cargo');
    Route::post('/mod_gene/mod_tram_inter/v2_lista_reporte_area_cargo/detalle', [V2ListaReporteAreaCargoController::class, 'ajaxDetalle'])->name('mod_tram_inter.v2_lista_reporte_area_cargo.detalle');

    // TRAMITES EXTERNOS — la página GET la resuelve AuthController desde a_rles.de_dreccon
    Route::post('/mod_gm/mod_tram_exte/index/store', [IngresoExpedienteController::class, 'store'])->name('tramites.ingresar.store');
    Route::get('/mod_gm/mod_tram_exte/index/buscar_usuario', [IngresoExpedienteController::class, 'buscarUsuario'])->name('tramites.ingresar.buscar_usuario');
    Route::get('/mod_gm/mod_tram_exte/index/buscar_juridico', [IngresoExpedienteController::class, 'buscarJuridico'])->name('tramites.ingresar.buscar_juridico');
    Route::get('/mod_gm/mod_tram_exte/index/sugerir_juridicos', [IngresoExpedienteController::class, 'sugerirJuridicos'])->name('tramites.ingresar.sugerir_juridicos');
    Route::get('/mod_gm/mod_tram_exte/index/subgerencias/{gerencia}', [IngresoExpedienteController::class, 'listarSubgerencias'])->name('tramites.ingresar.subgerencias');
    Route::get('/mod_gm/mod_tram_exte/index/tramites/{servicio}', [IngresoExpedienteController::class, 'listarTramites'])->name('tramites.ingresar.tramites');
    Route::get('/mod_gm/mod_tram_exte/index/requisitos/{tramite}', [IngresoExpedienteController::class, 'listarRequisitos'])->name('tramites.ingresar.requisitos');
    Route::get('/mod_gm/mod_tram_exte/index/buscar_tramites_tupa', [IngresoExpedienteController::class, 'buscarTramitesTupa'])->name('tramites.ingresar.buscar_tramites_tupa');
    Route::get('/mod_gm/mod_tram_exte/index/lista', [IngresoExpedienteController::class, 'lista'])->name('tramites.ingresar.lista');
    Route::post('/mod_gm/mod_tram_exte/index/contar_folios', [IngresoExpedienteController::class, 'contarFolios'])->name('tramites.ingresar.contar_folios');
    Route::get('/mod_gm/mod_tram_exte/voucher/{id}', [IngresoExpedienteController::class, 'voucher'])->name('tramites.voucher');
    Route::get('/mod_gm/mod_tram_exte/busca_expedi', [BuscaExpediController::class, 'show'])->name('tramites.busca_expedi');
    Route::post('/mod_gm/mod_tram_exte/busca_expedi', [BuscaExpediController::class, 'update'])->name('tramites.busca_expedi.update');
    Route::get('/mod_gm/mod_tram_exte/busca_expedi_deta', [BuscaExpediController::class, 'detallePopup'])->name('tramites.busca_expedi_deta');
    Route::get('/mod_gm/mod_tram_exte/impr_expedi', [BuscaExpediController::class, 'imprimir'])->name('tramites.impr_expedi');
    Route::get('/mod_gm/mod_tram_exte/agre_anexo', [BuscaExpediController::class, 'agreAnexoForm'])->name('tramites.agre_anexo');
    Route::post('/mod_gm/mod_tram_exte/agre_anexo', [BuscaExpediController::class, 'agreAnexoStore'])->name('tramites.agre_anexo.store');
    Route::get('/mod_gm/mod_tram_exte/lista_vaucher', [BuscaExpediController::class, 'listaVaucher'])->name('tramites.lista_vaucher');
    Route::get('/mod_gm/mod_tram_exte/voucher_anexo', [BuscaExpediController::class, 'voucherAnexo'])->name('tramites.voucher_anexo');
    Route::get('/mod_gm/mod_tram_exte/gene_expedi_cita', [BuscaExpediController::class, 'citaVirtualForm'])->name('tramites.gene_expedi_cita');
    Route::post('/mod_gm/mod_tram_exte/gene_expedi_cita', [BuscaExpediController::class, 'citaVirtualStore'])->name('tramites.gene_expedi_cita.store');
    Route::get('/mod_gm/mod_tram_exte/gene_expedi_obser', [BuscaExpediController::class, 'generarExpedienteForm'])->name('tramites.gene_expedi_obser');
    Route::post('/mod_gm/mod_tram_exte/gene_expedi_obser', [BuscaExpediController::class, 'generarExpedienteStore'])->name('tramites.gene_expedi_obser.store');

    Route::get('/mod_gm/mod_tram_exte/segi_adjuntar/detalle', [AdjuntarTramiteController::class, 'detalle'])->name('tramites.adjuntar.detalle');
    Route::post('/mod_gm/mod_tram_exte/segi_adjuntar/{movimientoId}', [AdjuntarTramiteController::class, 'upload'])->name('tramites.adjuntar.upload');
    Route::get('/mod_gm/mod_tram_exte/segi_adjuntar/archivo/{nombre}', [AdjuntarTramiteController::class, 'verArchivo'])->name('tramites.adjuntar.archivo')->where('nombre', '.*');

    // BANDEJA DE ARCHIVO — el GET del menú lo resuelve AuthController desde a_rles.de_dreccon
    Route::post('/mod_gm/mod_tram_exte/segi_archivo/tabla', [ArchivoExpedienteController::class, 'tabla'])->name('tramites.archivo.tabla');
    Route::post('/mod_gm/mod_tram_exte/segi_archivo/archivar', [ArchivoExpedienteController::class, 'archivar'])->name('tramites.archivo.archivar');
    Route::post('/mod_gm/mod_tram_exte/segi_archivo/notificar', [ArchivoExpedienteController::class, 'notificar'])->name('tramites.archivo.notificar');
    Route::post('/mod_gm/mod_tram_exte/segi_archivo/prestar', [ArchivoExpedienteController::class, 'prestar'])->name('tramites.archivo.prestar');
    Route::post('/mod_gm/mod_tram_exte/segi_archivo/imprimir', [ArchivoExpedienteController::class, 'imprimir'])->name('tramites.archivo.imprimir');
    Route::post('/mod_gm/mod_tram_exte/segi_archivo/detalle', [ArchivoExpedienteController::class, 'detalle'])->name('tramites.archivo.detalle');
    Route::get('/mod_gm/mod_tram_exte/segi_archivo/matriz', [ArchivoExpedienteController::class, 'matriz'])->name('tramites.archivo.matriz');

    // NOTIFICAR PLATAFORMA — el GET del menú lo resuelve AuthController desde a_rles.de_dreccon
    Route::post('/mod_gm/mod_tram_exte/notificar_pltfrma/tabla', [NotificarPlataformaController::class, 'tabla'])->name('tramites.notificar.tabla');
    Route::post('/mod_gm/mod_tram_exte/notificar_pltfrma/notificar/{id}', [NotificarPlataformaController::class, 'notificar'])->name('tramites.notificar.action');

    // TRÁMITE EXTERNO — segi_expedi (Bandeja y APIs)
    Route::get('/mod_gm/mod_tram_exte/segi_expedi', [CorreoExternoController::class, 'index'])->name('tramites.bandeja');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/tabla', [CorreoExternoController::class, 'tabla'])->name('mod_gm.segi_expedi.tabla');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/historial', [CorreoExternoController::class, 'historial'])->name('mod_gm.segi_expedi.historial');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/recepcionar', [CorreoExternoController::class, 'recepcionar'])->name('mod_gm.segi_expedi.recepcionar');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/detalle', [CorreoExternoController::class, 'detalle'])->name('mod_gm.segi_expedi.detalle');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/derivar', [CorreoExternoController::class, 'derivar'])->name('mod_gm.segi_expedi.derivar');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/asignar', [CorreoExternoController::class, 'asignar'])->name('mod_gm.segi_expedi.asignar');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/enviar', [CorreoExternoController::class, 'enviar'])->name('mod_gm.segi_expedi.enviar');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/finalizar', [CorreoExternoController::class, 'finalizar'])->name('mod_gm.segi_expedi.finalizar');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/solicitud_video', [CorreoExternoController::class, 'solicitudVideo'])->name('mod_gm.segi_expedi.solicitud_video');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/semilla', [CorreoExternoController::class, 'obtenerSemilla'])->name('mod_gm.segi_expedi.semilla');
    Route::get('/mod_gm/mod_tram_exte/segi_expedi/hoja-ruta/{id}', [CorreoExternoController::class, 'hojaRuta'])->name('mod_gm.segi_expedi.hoja_ruta');
    Route::get('/mod_gm/mod_tram_exte/expedientes/{id}/hoja-ruta', [CorreoExternoController::class, 'hojaRuta'])->name('mod_gm.segi_expedi.hoja_ruta.alias');
    Route::get('/mod_gm/mod_tram_exte/segi_expedi/descargar/{id}', [CorreoExternoController::class, 'descargarAdjunto'])->name('mod_gm.segi_expedi.descargar');
    Route::match(['get', 'post'], '/mod_gm/mod_tram_exte/segi_expedi/imprimir-bandeja', [CorreoExternoController::class, 'imprimirBandeja'])->name('mod_gm.segi_expedi.imprimir_bandeja');
    Route::get('/mod_gm/mod_tram_exte/segi_expedi/exportar-excel', [CorreoExternoController::class, 'exportarExcel'])->name('mod_gm.segi_expedi.exportar_excel');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/devolver', [CorreoExternoController::class, 'devolver'])->name('mod_gm.segi_expedi.devolver');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/archivar', [CorreoExternoController::class, 'archivar'])->name('mod_gm.segi_expedi.archivar');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/prestar', [CorreoExternoController::class, 'prestar'])->name('mod_gm.segi_expedi.prestar');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/retornar_prestamo', [CorreoExternoController::class, 'retornarPrestamo'])->name('mod_gm.segi_expedi.retornar_prestamo');
    Route::post('/mod_gm/mod_tram_exte/segi_expedi/redirigir', [CorreoExternoController::class, 'redirigir'])->name('mod_gm.segi_expedi.redirigir');
    Route::get('/mod_gm/mod_tram_exte/segi_expedi/descargar-todos/{id}', [CorreoExternoController::class, 'descargarTodos'])->name('mod_gm.segi_expedi.descargar_todos');
    Route::get('/mod_gm/mod_tram_exte/segi_expedi/conteos', [CorreoExternoController::class, 'conteos'])->name('mod_gm.segi_expedi.conteos');
    Route::get('/mod_gm/mod_tram_exte/segi_expedi/participantes', [CorreoExternoController::class, 'participantes'])->name('mod_gm.segi_expedi.participantes');
    Route::get('/mod_gm/mod_tram_exte/segi_expedi/auditoria-descargas', [CorreoExternoController::class, 'auditoriaDescargas'])->name('mod_gm.segi_expedi.auditoria_descargas');

    Route::post('/mod_gm/mod_tram_exte/reporte-expediente/buscar', [ReporteExpedienteController::class, 'buscar'])->name('mod_gm.reporte_expediente.buscar');
    Route::post('/mod_gm/mod_tram_exte/reporte-expediente/resumen', [ReporteExpedienteController::class, 'resumen'])->name('mod_gm.reporte_expediente.resumen');
    Route::post('/mod_gm/mod_tram_exte/reporte-expediente/resumen-detalle', [ReporteExpedienteController::class, 'resumenDetalle'])->name('mod_gm.reporte_expediente.resumen_detalle');
    Route::post('/mod_gm/mod_tram_exte/reporte-expediente/detalle', [ReporteExpedienteController::class, 'detalle'])->name('mod_gm.reporte_expediente.detalle');
    Route::post('/mod_gm/mod_tram_exte/reporte-expediente/imprimir', [ReporteExpedienteController::class, 'imprimirReporte'])->name('mod_gm.reporte_expediente.imprimir');
    Route::get('/mod_gm/mod_tram_exte/reporte-expediente/imprimir/{id}', [ReporteExpedienteController::class, 'imprimirExpediente'])->name('mod_gm.reporte_expediente.imprimir_expediente');

    Route::post('/mod_gm/mod_tram_exte/lista_reporte/buscar', [ListaReporteController::class, 'buscar'])->name('mod_gm.lista_reporte.buscar');
    Route::post('/mod_gm/mod_tram_exte/lista_reporte/imprimir', [ListaReporteController::class, 'imprimir'])->name('mod_gm.lista_reporte.imprimir');
    Route::post('/mod_gm/mod_tram_exte/lista_repor_area/buscar', [ListaReporAreaController::class, 'buscar'])->name('mod_gm.lista_repor_area.buscar');
    Route::match(['get', 'post'], '/mod_gm/mod_tram_exte/v2_lista_reporte_area_cargo', [V2ListaReporteAreaCargoExternoController::class, 'index'])->name('mod_gm.v2_lista_reporte_area_cargo');
    Route::post('/mod_gm/mod_tram_exte/v2_lista_reporte_area_cargo/detalle', [V2ListaReporteAreaCargoExternoController::class, 'ajaxDetalle'])->name('mod_gm.v2_lista_reporte_area_cargo.detalle');
    Route::get('/mod_gm/mod_tram_exte/v2_lista_reporte_area_cargo/historico/{id}', [V2ListaReporteAreaCargoExternoController::class, 'historico'])->name('mod_gm.v2_lista_reporte_area_cargo.historico');
    Route::post('/mod_gm/mod_tram_exte/lista_expedi_todo/buscar', [ListaExpediTodoController::class, 'buscar'])->name('mod_gm.lista_expedi_todo.buscar');
    Route::post('/mod_gm/mod_tram_exte/lista_expedi_todo/imprimir', [ListaExpediTodoController::class, 'imprimir'])->name('mod_gm.lista_expedi_todo.imprimir');
    Route::post('/mod_gm/mod_tram_exte/segi_verexpedi/buscar', [SegiVerExpediController::class, 'buscar'])->name('mod_gm.segi_verexpedi.buscar');
    Route::post('/mod_gm/mod_tram_exte/segi_verexpedi/historico', [SegiVerExpediController::class, 'historico'])->name('mod_gm.segi_verexpedi.historico');
    Route::post('/mod_gm/mod_tram_exte/segi_verexpedi/cambiar-estado', [SegiVerExpediController::class, 'cambiarEstado'])->name('mod_gm.segi_verexpedi.cambiar_estado');

    // REPORTES — reporte_tramites (página vía menú BD / catch-all; solo APIs aquí)
    Route::post('/mod_reportes/reporte_tramites/buscar', [ReporteTramitesController::class, 'buscar'])->name('mod_reportes.reporte_tramites.buscar');
    Route::post('/mod_reportes/reporte_tramites/tipos', [ReporteTramitesController::class, 'tipos'])->name('mod_reportes.reporte_tramites.tipos');

    // REDIRIGIR EXPEDIENTE — el GET del menú lo resuelve AuthController desde a_rles.de_dreccon
    Route::post('/mod_gm/mod_tram_exte/redirigir/buscar', [RedirigirExpedienteController::class, 'buscar'])->name('mod_gm.redirigir.buscar');
    Route::post('/mod_gm/mod_tram_exte/redirigir/detalle', [RedirigirExpedienteController::class, 'detalle'])->name('mod_gm.redirigir.detalle');
    Route::post('/mod_gm/mod_tram_exte/redirigir/derivar', [RedirigirExpedienteController::class, 'derivar'])->name('mod_gm.redirigir.derivar');
    Route::post('/mod_gm/mod_tram_exte/redirigir/anular', [RedirigirExpedienteController::class, 'anular'])->name('mod_gm.redirigir.anular');
    Route::post('/mod_gm/mod_tram_exte/redirigir/imprimir', [RedirigirExpedienteController::class, 'imprimir'])->name('mod_gm.redirigir.imprimir');

    // MÓDULO DE SEGURIDAD
    Route::prefix('mod_seguridad')->group(function () {
        // Perfiles
        Route::get('/seguridad-perfiles', [PerfilController::class, 'index'])->name('seguridad.perfiles');
        Route::post('/seguridad-perfiles/store', [PerfilController::class, 'store'])->name('seguridad.perfiles.store');
        Route::post('/seguridad-perfiles/delete/{id}', [PerfilController::class, 'destroy'])->name('seguridad.perfiles.delete');

        // Cargos
        Route::get('/seguridad-cargos', [CargoController::class, 'index'])->name('seguridad.cargos');
        Route::post('/seguridad-cargos/store', [CargoController::class, 'store'])->name('seguridad.cargos.store');
        Route::post('/seguridad-cargos/delete/{id}', [CargoController::class, 'destroy'])->name('seguridad.cargos.delete');

        // Opciones
        Route::get('/seguridad-opciones', [OpcionController::class, 'index'])->name('seguridad.opciones');
        Route::post('/seguridad-opciones/store', [OpcionController::class, 'store'])->name('seguridad.opciones.store');
        Route::post('/seguridad-opciones/delete/{id}', [OpcionController::class, 'destroy'])->name('seguridad.opciones.delete');

        // Usuarios
        Route::get('/seguridad-usuario', [UsuarioController::class, 'index'])->name('seguridad.usuario');
        Route::post('/seguridad-usuario/store', [UsuarioController::class, 'store'])->name('seguridad.usuario.store');
        Route::get('/seguridad-usuario/buscar-dni/{dni}', [UsuarioController::class, 'buscarDni'])->name('seguridad.usuario.buscar-dni');

        // Accesos
        Route::get('/seguridad-accesos', [AccesoController::class, 'index'])->name('seguridad.accesos');
        Route::post('/seguridad-accesos/toggle-acceso', [AccesoController::class, 'toggleAcceso'])->name('seguridad.accesos.toggle');
        Route::get('/seguridad-accesos/cargos-area/{areaId}', [AccesoController::class, 'cargosArea'])->name('seguridad.accesos.cargos-area');
        Route::get('/seguridad-accesos/cargos-usuario/{loginId}', [AccesoController::class, 'cargosUsuario'])->name('seguridad.accesos.cargos-usuario');
        Route::post('/seguridad-accesos/asignar-cargo', [AccesoController::class, 'asignarCargo'])->name('seguridad.accesos.asignar-cargo');
        Route::post('/seguridad-accesos/eliminar-cargo', [AccesoController::class, 'eliminarCargo'])->name('seguridad.accesos.eliminar-cargo');
        Route::post('/seguridad-accesos/cambiar-principal', [AccesoController::class, 'cambiarCargoPrincipal'])->name('seguridad.accesos.cambiar-principal');

        // Perfiles - Opciones
        Route::get('/seguridad-perfiles-opciones', [PerfilOpcionController::class, 'index'])->name('seguridad.perfiles-opciones');
        Route::get('/seguridad-perfiles-opciones/asignadas/{perfilId}', [PerfilOpcionController::class, 'opcionesAsignadas'])->name('seguridad.perfiles-opciones.asignadas');
        Route::post('/seguridad-perfiles-opciones/asignar', [PerfilOpcionController::class, 'asignar'])->name('seguridad.perfiles-opciones.asignar');
        Route::post('/seguridad-perfiles-opciones/desasignar', [PerfilOpcionController::class, 'desasignar'])->name('seguridad.perfiles-opciones.desasignar');

        // Cambiar contraseña
        Route::get('/cambiar-contraseña', [PasswordController::class, 'index'])->name('seguridad.cambiar-contrasena');
        Route::post('/cambiar-contraseña/enviar-codigo', [PasswordController::class, 'enviarCodigo'])->name('seguridad.cambiar-contrasena.enviar-codigo');
        Route::post('/cambiar-contraseña/confirmar', [PasswordController::class, 'confirmar'])->name('seguridad.cambiar-contrasena.confirmar');
    });

    // Rutas de Consultas de Identidad y SUNARP (PideCore)
    Route::prefix('mod_gene/pide')->group(function () {
        Route::post('/consultar', [PideController::class, 'consultar'])->name('pide.consultar');
        Route::post('/buscar-registrar', [PideController::class, 'buscarYRegistrar'])->name('pide.buscar-registrar');
    });

    // ACCESO A LA INFORMACION (OAC)
    Route::get('/mod_oac/acceso_informacion/acceso_info', [AccesoInfoController::class, 'index'])->name('oac.acceso_info');
    Route::get('/mod_oac/acceso_informacion/acceso_info.php', [AccesoInfoController::class, 'index']);

    // GDU — Bandeja de consulta
    Route::get('/mod_gdu/band_consulta_gdu', [BandConsultaGduController::class, 'index'])->name('gdu.band_consulta');
    Route::get('/mod_gdu/band_consulta_gdu.php', [BandConsultaGduController::class, 'index']);
    Route::get('/mod_gdu/procedente_constancia', [BandConsultaGduController::class, 'procedenteConstancia'])->name('gdu.procedente_constancia');
    Route::get('/mod_gdu/procedente_constancia.php', [BandConsultaGduController::class, 'procedenteConstancia']);
    Route::get('/mod_gdu/procedente_licencias', [BandConsultaGduController::class, 'procedenteLicencias'])->name('gdu.procedente_licencias');
    Route::get('/mod_gdu/procedente_licencias.php', [BandConsultaGduController::class, 'procedenteLicencias']);

    // Esta ruta captura TODO lo que venga después de la base
    // Ejemplo: /mod_ti/helpdesk/consulta o /admin/reportes/ventas/anual

    Route::any('/{any}', [AuthController::class, 'cargarModulo'])
        ->where('any', '.*')
        ->name('modulo.dinamico');
});
