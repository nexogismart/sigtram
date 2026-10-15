<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Reporte General de Trámites Externos' }} — SIGTRAM</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/reportes-expedientes.css', 'resources/js/app.js'])

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.reporte-expediente', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
@php
    $frontendConfig = [
        'routes' => [
            'resumen' => route('mod_gm.reporte_expediente.resumen', [], false),
            'detalle' => route('mod_gm.reporte_expediente.resumen_detalle', [], false),
            'buscaExpediDeta' => route('tramites.busca_expedi_deta', [], false),
            'adjunto' => route('tramites.adjuntar.archivo', ['nombre' => '__NOM__'], false),
        ],
        'desde' => $desde ?? now()->startOfMonth()->format('Y-m-d'),
        'hasta' => $hasta ?? now()->format('Y-m-d'),
    ];
@endphp
<body class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="reporteAreaExterna()" data-config="{{ json_encode($frontendConfig) }}">
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.reporte-expediente', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}


    <div x-show="toast.show" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]">
        <div class="backdrop-blur-md text-white px-6 py-2.5 rounded-full shadow-2xl flex items-center gap-3"
             :class="toast.type === 'error' ? 'bg-red-700/90' : 'bg-gray-900/90'">
            <i class="text-xs" :class="toast.type === 'error' ? 'fas fa-exclamation-circle text-red-200' : 'fas fa-check-circle text-emerald-400'"></i>
            <p class="text-[10px] font-black uppercase tracking-widest" x-text="toast.message"></p>
        </div>
    </div>

    <div class="p-3 sm:p-5 w-full max-w-[1600px] mx-auto">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col min-h-[85vh]">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Trámite Externo</p>
                    <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Reporte General de Trámites Externos' }}</h1>
                </div>
                <span class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full">Total Doc. <span x-text="totales.total_doc"></span></span>
            </div>

            <form @submit.prevent="buscar()" class="px-4 py-3 border-b border-gray-100 bg-gray-50/70">
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Desde</label>
                        <input type="date" x-model="filtros.desde"
                               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Hasta</label>
                        <input type="date" x-model="filtros.hasta"
                               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <button type="submit"
                            class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                </div>
            </form>

            <div class="flex-1 overflow-auto custom-scrollbar relative min-h-[320px]">
                <div x-show="loading" x-cloak class="absolute inset-0 bg-white/70 z-10 flex items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-slate-600"></i>
                </div>
                <table class="w-full text-left border-collapse min-w-[980px]">
                    <thead>
                        <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-200 bg-slate-50 sticky top-0">
                            <th class="px-2 py-2 w-10 text-center">#</th>
                            <th class="px-3 py-2">Nombre de Gerencia</th>
                            <th class="px-2 py-2 text-center">Digitalizar</th>
                            <th class="px-2 py-2 text-center">Por recibir</th>
                            <th class="px-2 py-2 text-center">Recibidos</th>
                            <th class="px-2 py-2 text-center">Derivado UU.OO</th>
                            <th class="px-2 py-2 text-center">Finalizados</th>
                            <th class="px-2 py-2 text-center">Total Doc.</th>
                            <th class="px-2 py-2 text-right">% Trabajo</th>
                            <th class="px-2 py-2 text-center">F.T</th>
                        </tr>
                    </thead>
                    <template x-for="(row, index) in filas" :key="row.id">
                        <tbody>
                            <tr class="border-b border-gray-100 hover:bg-slate-50 text-[11px]">
                                    <td class="px-2 py-2 text-center text-gray-500" x-text="index + 1"></td>
                                    <td class="px-3 py-2 font-bold text-slate-800" x-text="row.nombre"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="toggleDetalle(row, 'digitalizar', 'Digitalizar')" class="font-bold text-slate-700 hover:underline" x-text="row.digitalizar"></button>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="toggleDetalle(row, 'por_recibir', 'Por recibir')" class="font-bold text-slate-700 hover:underline" x-text="row.por_recibir"></button>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="toggleDetalle(row, 'recibidos', 'Recibidos')" class="font-bold text-slate-700 hover:underline" x-text="row.recibidos"></button>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="toggleDetalle(row, 'derivados', 'Derivado UU.OO')" class="font-bold text-slate-700 hover:underline" x-text="row.derivados"></button>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="toggleDetalle(row, 'finalizados', 'Finalizados')" class="font-bold text-slate-700 hover:underline" x-text="row.finalizados"></button>
                                    </td>
                                    <td class="px-2 py-2 text-center font-black" x-text="row.total_doc"></td>
                                    <td class="px-2 py-2 text-right font-semibold" x-text="Number(row.porcentaje).toFixed(2)"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="toggleDetalle(row, 'criticos', 'F.T')" class="font-black text-red-600 hover:underline" x-text="row.criticos || ''"></button>
                                    </td>
                                </tr>
                                <tr x-show="expanded.id === row.id" x-cloak>
                                    <td colspan="10" class="bg-slate-50 px-3 py-3">
                                        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                                            <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                                                    <span x-text="row.nombre"></span> — <span x-text="expanded.label"></span>
                                                </p>
                                                <button type="button" @click="expanded = { id: null, label: '', rows: [] }" class="text-gray-400 hover:text-slate-700">
                                                    <i class="fas fa-times text-xs"></i>
                                                </button>
                                            </div>
                                            <div class="overflow-auto custom-scrollbar max-h-[280px]">
                                                <table class="w-full text-[11px]">
                                                    <thead>
                                                        <tr class="text-[9px] font-black uppercase tracking-widest text-gray-400 bg-slate-50">
                                                            <th class="px-2 py-2 text-center">#</th>
                                                            <th class="px-2 py-2">Nº Exp.</th>
                                                            <th class="px-2 py-2">F. Inicial</th>
                                                            <th class="px-2 py-2">Nombre o Razón Social</th>
                                                            <th class="px-2 py-2">F. Ultima</th>
                                                            <th class="px-2 py-2">Observaciones</th>
                                                            <th class="px-2 py-2 w-8"></th>
                                                            <th class="px-2 py-2 w-8"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-for="(item, i) in expanded.rows" :key="item.id + '-' + i">
                                                            <tr class="border-t border-gray-100">
                                                                <td class="px-2 py-1.5 text-center text-gray-400" x-text="i + 1"></td>
                                                                <td class="px-2 py-1.5 font-bold whitespace-nowrap" x-text="item.expediente"></td>
                                                                <td class="px-2 py-1.5 whitespace-nowrap" x-text="item.fecha"></td>
                                                                <td class="px-2 py-1.5" :title="item.solicitante" x-text="item.solicitante_corto"></td>
                                                                <td class="px-2 py-1.5 whitespace-nowrap" x-text="item.fecha_ultima"></td>
                                                                <td class="px-2 py-1.5 text-gray-600" :title="item.descripcion" x-text="item.descripcion"></td>
                                                                <td class="px-2 py-1.5 text-center">
                                                                    <a x-show="item.adjunto" :href="adjuntoUrl(item.adjunto)" target="_blank" class="text-slate-600" title="Adjunto">
                                                                        <i class="fas fa-paperclip"></i>
                                                                    </a>
                                                                </td>
                                                                <td class="px-2 py-1.5 text-center">
                                                                    <button type="button" @click="abrirOjo(item)" class="text-slate-600" title="Ver expediente">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        </template>
                                                        <tr x-show="expanded.rows.length === 0">
                                                            <td colspan="8" class="px-2 py-6 text-center text-gray-400">Sin registros</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                    </template>
                    <tbody>
                        <tr x-show="!loading && filas.length === 0">
                            <td colspan="10" class="px-3 py-10 text-center text-[11px] font-bold text-gray-400">No se encontraron resultados</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50 border-t border-gray-200 text-[11px] font-black">
                        <tr>
                            <td></td>
                            <td class="px-3 py-3 text-right">TOTALES :</td>
                            <td class="px-2 py-3 text-center" x-text="fmt(totales.digitalizar)"></td>
                            <td class="px-2 py-3 text-center" x-text="fmt(totales.por_recibir)"></td>
                            <td class="px-2 py-3 text-center" x-text="fmt(totales.recibidos)"></td>
                            <td class="px-2 py-3 text-center" x-text="fmt(totales.derivados)"></td>
                            <td class="px-2 py-3 text-center" x-text="fmt(totales.finalizados)"></td>
                            <td class="px-2 py-3 text-center" x-text="fmt(totales.total_doc)"></td>
                            <td class="px-2 py-3 text-right font-semibold" x-text="Number(totales.porcentaje || 0).toFixed(2)"></td>
                            <td class="px-2 py-3 text-center text-red-600" x-text="fmt(totales.criticos)"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div x-show="modalOjo.show" x-cloak class="modal-overlay" @keydown.escape.window="modalOjo.show = false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-[520px] h-[80vh] overflow-hidden flex flex-col" @click.outside="modalOjo.show = false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Expediente</h3>
                <button type="button" @click="modalOjo.show = false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <iframe :src="modalOjo.url" class="flex-1 w-full border-0 bg-white"></iframe>
        </div>
    </div>
</body>
</html>
