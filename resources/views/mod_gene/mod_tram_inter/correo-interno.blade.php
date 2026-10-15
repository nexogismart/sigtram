<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Correo Interno — SIGTRAM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gene.mod_tram_inter.correo-interno', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body class="sigtram-bandeja-page bg-[#f8fafc] text-gray-800 min-h-screen">
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gene.mod_tram_inter.correo-interno', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}

@php
    $frontendConfig = [
        'routes' => [
            'tabla' => route('mod_tram_inter.correo_interno.tabla'),
            'historial' => route('mod_tram_inter.correo_interno.historial'),
            'recepcionar' => route('mod_tram_inter.correo_interno.recepcionar'),
            'conteos' => route('mod_tram_inter.correo_interno.conteos'),
            'participantes' => route('mod_tram_inter.correo_interno.participantes'),
            'auditoriaDescargas' => route('mod_tram_inter.correo_interno.auditoria_descargas'),
            'voucher' => url('/mod_gene/mod_tram_inter/correo_interno/voucher') . '/',
            'detalleDocumento' => url('/mod_gene/mod_tram_inter/correo_interno/detalle_documento') . '/',
            'descargarCompleto' => url('/mod_gene/mod_tram_inter/correo_interno/descargar-completo') . '/',
            'datosTransaccionales' => route('mod_tram_inter.correo_interno.datos_transaccionales'),
            'correlativoInterno' => route('mod_tram_inter.correo_interno.correlativo_interno'),
            'asignar' => route('mod_tram_inter.correo_interno.asignar'),
            'derivar' => route('mod_tram_inter.correo_interno.derivar'),
            'finalizar' => route('mod_tram_inter.correo_interno.finalizar'),
            'devolver' => route('mod_tram_inter.correo_interno.devolver'),
            'reiterar' => route('mod_tram_inter.correo_interno.reiterar'),
            'hojaRuta' => url('/mod_gene/mod_tram_inter/correo_interno/hoja_ruta') . '/',
            'imprimirBandeja' => route('mod_tram_inter.correo_interno.imprimir_bandeja'),
            'descargarLote' => route('mod_tram_inter.correo_interno.descargar_lote'),
            'reemplazarFirmado' => route('mod_tram_inter.correo_interno.reemplazar_firmado'),
            'comprobantesPago' => route('mod_tram_inter.correo_interno.comprobantes_pago'),
        ],
        'context' => [
            'areaId' => (int) ($areaId ?? session(\App\Support\SessionKeys::USER_AREA_ID, 0)),
            'cargoId' => (int) ($cargoId ?? session(\App\Support\SessionKeys::USER_CARGO_ID, 0)),
            'esAreaContabilidad' => !empty($esAreaContabilidad),
        ],
        'csrfToken' => csrf_token(),
        'bandejas' => [
            'entrada' => 'Bandeja Trámite Interno — Entrada',
            'enviados' => 'Bandeja Trámite Interno — Enviados',
            'todos' => 'Bandeja Trámite Interno — Todos',
            'recibidos' => 'Bandeja Trámite Interno — Recibidos',
            'salida' => 'Bandeja Trámite Interno — Salida',
            'finalizados' => 'Bandeja Trámite Interno — Finalizados',
            'sin_finalizar' => 'Reporte Trámite Interno — Sin Finalizar',
            'contabilidad' => 'Bandeja Trámite Interno — Contabilidad',
        ],
    ];
@endphp
    <div id="tramite-interno-app" data-config='@json($frontendConfig)' class="sigtram-bandeja-shell w-full max-w-[1600px] mx-auto p-3 sm:p-5">
        <div class="sigtram-workspace grid grid-cols-1 xl:grid-cols-[220px_1fr] gap-4 min-h-[85vh] items-start">
            @include('mod_gene.mod_tram_inter.partials.sidebar')
            <main class="sigtram-content min-w-0 flex flex-col gap-4">
                @include('mod_gene.mod_tram_inter.partials.filtros-tabla')
                @include('mod_gene.mod_tram_inter.partials.historico')
            </main>
        </div>
        @include('mod_gene.mod_tram_inter.partials.modales')
    </div>
</body>
</html>
