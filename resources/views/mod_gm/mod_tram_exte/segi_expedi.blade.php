<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Seguimiento de Expedientes — SIGTRAM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sigtram-bandeja-page bg-[#f8fafc] text-gray-800 min-h-screen">
@php
    $frontendConfig = [
        'routes' => [
            'conteos' => route('mod_gm.segi_expedi.conteos'),
            'tabla' => route('mod_gm.segi_expedi.tabla'),
            'historial' => route('mod_gm.segi_expedi.historial'),
            'detalle' => route('mod_gm.segi_expedi.detalle'),
            'recepcionar' => route('mod_gm.segi_expedi.recepcionar'),
            'derivar' => route('mod_gm.segi_expedi.derivar'),
            'asignar' => route('mod_gm.segi_expedi.asignar'),
            'enviar' => route('mod_gm.segi_expedi.enviar'),
            'finalizar' => route('mod_gm.segi_expedi.finalizar'),
            'solicitudVideo' => route('mod_gm.segi_expedi.solicitud_video'),
            'semilla' => route('mod_gm.segi_expedi.semilla'),
            'hojaRuta' => url('/mod_gm/mod_tram_exte/segi_expedi/hoja-ruta') . '/',
            'descargarAdjunto' => url('/mod_gm/mod_tram_exte/segi_expedi/descargar') . '/',
            'imprimirBandeja' => route('mod_gm.segi_expedi.imprimir_bandeja'),
            'exportarExcel' => route('mod_gm.segi_expedi.exportar_excel'),
            'devolver' => route('mod_gm.segi_expedi.devolver'),
            'archivar' => route('mod_gm.segi_expedi.archivar'),
            'prestar' => route('mod_gm.segi_expedi.prestar'),
            'retornarPrestamo' => route('mod_gm.segi_expedi.retornar_prestamo'),
            'redirigir' => route('mod_gm.segi_expedi.redirigir'),
            'descargarTodos' => url('/mod_gm/mod_tram_exte/segi_expedi/descargar-todos') . '/',
            'participantes' => route('mod_gm.segi_expedi.participantes'),
            'auditoriaDescargas' => route('mod_gm.segi_expedi.auditoria_descargas'),
        ],
        'bandejaInicial' => (string) ($bandejaInicial ?? 'entrada'),
        'puedeExportarExcel' => !empty($puedeExportarExcel),
        'areasList' => $areasList ?? [],
        'cargosList' => $cargosList ?? [],
        'tiposDocumento' => $tiposDocumento ?? [],
        'csrfToken' => csrf_token(),
    ];
@endphp

    <div id="tramite-externo-app" data-config='@json($frontendConfig)' x-data="correoExternoApp()" class="sigtram-bandeja-shell w-full max-w-[1600px] mx-auto p-3 sm:p-5">
        <div x-show="showToast" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]">
            <div class="bg-gray-900/90 backdrop-blur-md text-white px-6 py-2.5 rounded-full shadow-2xl flex items-center gap-3">
                <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
                <p class="text-[10px] font-black uppercase tracking-widest" x-text="toastMessage"></p>
            </div>
        </div>

        <div class="sigtram-workspace grid grid-cols-1 xl:grid-cols-[220px_1fr] gap-4 min-h-[85vh] items-start">
            @include('mod_gm.mod_tram_exte.partials_segi.sidebar')
            <section class="sigtram-content min-w-0 flex flex-col gap-4">
                @include('mod_gm.mod_tram_exte.partials_segi.filtros-tabla')
                @include('mod_gm.mod_tram_exte.partials_segi.historico')
            </section>
        </div>

        @include('mod_gm.mod_tram_exte.partials_segi.modales')
    </div>
</body>
</html>
