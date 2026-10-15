<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bandeja de Archivo — SIGTRAM</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/reportes-expedientes.css', 'resources/js/app.js'])
</head>
@php
    $frontendConfig = [
        'endpoints' => [
            'tabla' => route('tramites.archivo.tabla'),
            'archivar' => route('tramites.archivo.archivar'),
            'notificar' => route('tramites.archivo.notificar'),
            'prestar' => route('tramites.archivo.prestar'),
            'detalle' => route('tramites.archivo.detalle'),
            'matriz' => route('tramites.archivo.matriz'),
            'imprimir' => route('tramites.archivo.imprimir'),
        ],
        'desde' => $desde ?? '',
        'hasta' => $hasta ?? '',
        'searchFields' => $searchFields ?? [],
    ];
@endphp
<body class="bg-[#f8fafc] text-gray-800 h-screen overflow-hidden">
    <div id="tramite-externo-app" data-config='@json($frontendConfig)' x-data="archivoExpedientes()" class="p-3 sm:p-4 w-full h-full min-h-0 overflow-hidden flex flex-col">
        {{-- Toast Notificación --}}
        <div x-show="toast.show" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]">
            <div class="px-6 py-2.5 rounded-full shadow-2xl flex items-center gap-3 text-white" :class="toast.type === 'error' ? 'bg-red-600/95' : 'bg-gray-900/95'">
                <i class="text-xs" :class="toast.type === 'error' ? 'fas fa-circle-exclamation' : 'fas fa-circle-info text-blue-300'"></i>
                <p class="text-[10px] font-black uppercase tracking-widest" x-text="toast.message"></p>
            </div>
        </div>

        <div class="grid grid-cols-[220px_1fr] gap-3.5 h-full min-h-0 overflow-hidden">
            {{-- MENÚ LATERAL DE BANDEJAS — ARCHIVO EXTERNO --}}
            <aside class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-y-auto custom-scrollbar self-start max-h-full flex flex-col text-xs">
                <div class="bg-slate-800 text-white px-4 py-3 shrink-0">
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Trámite Externo</p>
                    <h1 class="text-sm font-bold mt-0.5">Archivo</h1>
                </div>
                <nav class="p-3 space-y-4">
                    <div>
                        <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Bandejas</p>
                        <div class="space-y-0.5">
                            <button type="button" @click="setBandeja('finalizados')" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="bandeja === 'finalizados' ? 'nav-active' : 'nav-idle'">
                                <span class="flex items-center gap-2"><i class="fas fa-flag-checkered w-4 text-center"></i> Finalizados</span>
                                <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="bandeja === 'finalizados' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" x-text="counts.finalizados ?? 0"></span>
                            </button>
                            <button type="button" @click="setBandeja('archivados')" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="bandeja === 'archivados' ? 'nav-active' : 'nav-idle'">
                                <span class="flex items-center gap-2"><i class="fas fa-box-archive w-4 text-center"></i> Archivados</span>
                                <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="bandeja === 'archivados' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" x-text="counts.archivados ?? 0"></span>
                            </button>
                            <button type="button" @click="setBandeja('prestados')" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="bandeja === 'prestados' ? 'nav-active' : 'nav-idle'">
                                <span class="flex items-center gap-2"><i class="fas fa-share-from-square w-4 text-center"></i> Prestados</span>
                                <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="bandeja === 'prestados' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" x-text="counts.prestados ?? 0"></span>
                            </button>
                            <button type="button" @click="setBandeja('matriz')" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="bandeja === 'matriz' ? 'nav-active' : 'nav-idle'">
                                <span class="flex items-center gap-2"><i class="fas fa-border-all w-4 text-center"></i> Matriz</span>
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-3">
                        <a href="{{ url('/mod_gm/mod_tram_exte/segi_expedi') }}" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-slate-600 hover:bg-slate-50">
                            <i class="fas fa-arrow-left w-4 text-center"></i> Corr. Externo
                        </a>
                    </div>
                </nav>
            </aside>

            {{-- CONTENIDO PRINCIPAL: FILTROS Y TABLA --}}
            <section class="min-w-0 flex flex-col gap-3 h-full min-h-0 overflow-hidden">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col flex-1 min-h-0">
                    {{-- Título de la bandeja --}}
                    <div class="bg-slate-800 text-white px-4 py-2 flex items-center justify-between gap-3 shrink-0">
                        <h2 class="text-sm sm:text-base font-bold uppercase tracking-wide">
                            Bandeja de Archivo —
                            <span x-text="{ finalizados:'Finalizados', archivados:'Archivados', prestados:'Prestados', matriz:'Matriz' }[bandeja] || bandeja"></span>
                        </h2>
                        <span class="text-[10px] uppercase tracking-widest text-slate-300 font-bold" x-text="activeTabDescription"></span>
                    </div>

                    {{-- 1. Buscador compacto --}}
                    <div x-show="bandeja !== 'matriz'" class="border-b border-gray-100 px-3 py-2 bg-gray-50/70 shrink-0">
                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Búsqueda por</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-2 items-end">
                            <div class="lg:col-span-3">
                                <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Criterio</label>
                                <select x-model="filters.campo" class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                                    <template x-for="field in searchFields" :key="field.value">
                                        <option :value="field.value" x-text="field.label"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="lg:col-span-3">
                                <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Descripción</label>
                                <div class="relative">
                                    <input type="text" x-model="filters.criterio" @keydown.enter="fetchResults(1)" placeholder="Texto a buscar..." class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none pr-7">
                                    <button type="button" x-show="filters.criterio" @click="filters.criterio = ''; fetchResults(1)" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-times-circle text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Desde</label>
                                <input type="date" x-model="filters.desde" class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Hasta</label>
                                <input type="date" x-model="filters.hasta" class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                            </div>
                            <div class="lg:col-span-2 flex gap-1.5">
                                <button type="button" @click="fetchResults(1)" class="w-full py-1.5 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all flex items-center justify-center gap-1">
                                    <i class="fas fa-search"></i> <span>Consultar</span>
                                </button>
                                <button type="button" @click="resetFilters()" title="Limpiar" class="py-1.5 px-3 bg-gray-400 hover:bg-gray-500 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all flex items-center justify-center">
                                    <i class="fas fa-eraser"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Barra de acciones dinámicas --}}
                    <div x-show="bandeja !== 'matriz'" class="px-3 py-1.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2 text-xs font-bold text-slate-700 bg-white shrink-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" x-show="bandeja === 'finalizados'" @click="openNotifyModal()" :disabled="!canNotify" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm" :class="canNotify ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                                <i class="fas fa-bell"></i> Notificar
                            </button>
                            <button type="button" x-show="bandeja === 'archivados'" @click="openLoanModal()" :disabled="!canLoan" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm" :class="canLoan ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                                <i class="fas fa-share-from-square"></i> Prestar
                            </button>
                            <button type="button" x-show="bandeja !== 'matriz'" @click="openArchiveModal()" :disabled="!canArchive" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm" :class="canArchive ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                                <i class="fas fa-box-archive"></i>
                                <span x-text="bandeja === 'prestados' ? 'Devolver a archivo' : 'Archivar'"></span>
                            </button>
                            <span class="text-gray-300">|</span>
                            <button type="button" @click="printBandeja()" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                            <span class="text-gray-300">|</span>
                            <button type="button" @click="fetchResults(pagination.current_page || 1)" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors">
                                <i class="fas fa-rotate-right"></i> Actualizar
                            </button>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                            <span x-text="pagination.total"></span> registros | <span x-text="selectedIds.length"></span> seleccionados
                        </div>
                    </div>

                    {{-- 3. Tabla de registros --}}
                    <div x-show="bandeja !== 'matriz'" class="relative flex-1 min-h-0 overflow-auto custom-scrollbar">
                        <div x-show="loading" x-cloak class="absolute inset-0 z-30 bg-white/70 backdrop-blur-[2px] flex items-center justify-center">
                            <div class="flex items-center gap-3 text-blue-600 font-black uppercase tracking-widest text-[10px]">
                                <i class="fas fa-circle-notch fa-spin text-lg"></i>
                                Cargando expedientes...
                            </div>
                        </div>

                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-gray-100/90 sticky top-0 z-10 border-b border-gray-200">
                                <tr class="text-[9px] uppercase tracking-wider text-slate-600">
                                    <th class="px-2 py-2 text-center w-10">
                                        <input type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" :checked="items.length > 0 && selectedIds.length === items.length" @change="toggleAll($event.target.checked)" :disabled="!isSelectableTab">
                                    </th>
                                    <th class="px-3 py-2 w-44 whitespace-nowrap font-bold">Expediente</th>
                                    <th class="px-3 py-2 min-w-[200px] max-w-xs font-bold">Solicitante</th>
                                    <th class="px-3 py-2 w-36 whitespace-nowrap text-center font-bold">Fecha Mov.</th>
                                    <th class="px-3 py-2 w-28 text-center font-bold">Estado</th>
                                    <th class="px-3 py-2 w-24 text-center font-bold">Área</th>
                                    <th class="px-3 py-2 min-w-[220px] max-w-md font-bold">Observaciones</th>
                                    <th class="px-3 py-2 w-16 text-center font-bold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <template x-for="item in items" :key="item.id">
                                    <tr class="hover:bg-blue-50/40 transition-colors">
                                        <td class="px-2 py-2 text-center align-middle">
                                            <input type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" :checked="selectedIds.includes(item.id)" @change="toggleOne(item.id, $event.target.checked)" :disabled="!isSelectableTab">
                                        </td>
                                        <td class="px-3 py-2 align-middle whitespace-nowrap">
                                            <div class="font-black text-slate-800" x-text="item.expediente || '-'"></div>
                                            <div class="text-[9px] font-bold uppercase tracking-wider text-gray-400">
                                                ID <span x-text="item.id"></span> · Folios <span x-text="item.folios"></span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 align-middle max-w-xs">
                                            <div class="font-bold text-slate-700 truncate" :title="item.solicitante || '-'" x-text="item.solicitante || '-'"></div>
                                            <div class="text-[10px] text-gray-400" x-text="item.documento || ''"></div>
                                        </td>
                                        <td class="px-3 py-2 align-middle text-center text-slate-600 font-semibold whitespace-nowrap" x-text="item.fecha_movimiento || '-'"></td>
                                        <td class="px-3 py-2 align-middle text-center">
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider" :class="statusClass(item.estado_id)" x-text="item.estado || '-'"></span>
                                        </td>
                                        <td class="px-3 py-2 align-middle text-center text-slate-600 font-semibold truncate max-w-[120px]" :title="item.area || '-'" x-text="item.area || '-'"></td>
                                        <td class="px-3 py-2 align-middle text-slate-500 max-w-md">
                                            <p class="line-clamp-2 text-xs" :title="item.observaciones || '-'" x-text="item.observaciones || '-'"></p>
                                        </td>
                                        <td class="px-3 py-2 align-middle text-center">
                                            <button type="button" @click="showDetail(item.id)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-white border border-gray-300 text-gray-500 hover:text-blue-600 hover:border-blue-500 transition-colors shadow-sm" title="Ver detalle">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="!loading && items.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <div class="mx-auto w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400 mb-2">
                                            <i class="fas fa-folder-open text-base"></i>
                                        </div>
                                        <p class="text-xs font-black text-gray-600 uppercase tracking-widest">Sin resultados</p>
                                        <p class="text-[10px] text-gray-400 mt-0.5">Ajusta los filtros o cambia de bandeja.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- 4. Matriz de almacenamiento --}}
                    <div x-show="bandeja === 'matriz'" x-cloak class="relative flex-1 min-h-0 overflow-auto p-4 custom-scrollbar">
                        <div x-show="matrix.loading" class="absolute inset-0 z-20 bg-white/70 backdrop-blur-[2px] flex items-center justify-center">
                            <div class="flex items-center gap-3 text-blue-600 font-black uppercase tracking-widest text-[10px]">
                                <i class="fas fa-circle-notch fa-spin text-lg"></i>
                                Cargando matriz...
                            </div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-3">
                            <template x-for="cell in matrix.items" :key="cell.id">
                                <div class="border rounded-xl p-3 bg-white shadow-sm" :class="cell.ocupados > 0 ? 'border-blue-200 ring-1 ring-blue-50' : 'border-gray-200'">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs" :class="cell.ocupados > 0 ? 'bg-blue-50 text-blue-600' : 'bg-gray-50 text-gray-400'">
                                            <i class="fas fa-box-archive"></i>
                                        </div>
                                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-400" x-text="cell.ocupados + '/' + cell.maximo"></span>
                                    </div>
                                    <p class="text-xs font-bold text-gray-800 mt-2 truncate" x-text="cell.nombre || ('Archivo ' + cell.id)"></p>
                                    <p class="text-[9px] text-gray-400 mt-0.5">Fila <span x-text="cell.fila"></span> / Col. <span x-text="cell.columna"></span></p>
                                </div>
                            </template>
                        </div>
                        <div x-show="!matrix.loading && matrix.items.length === 0" class="py-16 text-center">
                            <p class="text-xs font-black uppercase tracking-widest text-gray-400">Sin matriz configurada</p>
                        </div>
                    </div>

                    {{-- 5. Paginador al pie --}}
                    <div x-show="bandeja !== 'matriz'" class="px-3 py-2 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 shrink-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                            Mostrando <span x-text="paginationFrom"></span>-<span x-text="paginationTo"></span> de <span x-text="pagination.total"></span>
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="fetchResults(pagination.current_page - 1)" :disabled="pagination.current_page <= 1 || loading" class="px-2.5 py-1 rounded-lg border text-[10px] font-black uppercase tracking-widest transition-colors" :class="pagination.current_page <= 1 ? 'bg-gray-100 text-gray-400 border-gray-200' : 'bg-white text-gray-600 border-gray-300 hover:border-slate-500 hover:text-slate-800'">
                                <i class="fas fa-chevron-left mr-1"></i> Ant.
                            </button>
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                                <span x-text="pagination.current_page"></span> / <span x-text="pagination.last_page"></span>
                            </span>
                            <button type="button" @click="fetchResults(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page || loading" class="px-2.5 py-1 rounded-lg border text-[10px] font-black uppercase tracking-widest transition-colors" :class="pagination.current_page >= pagination.last_page ? 'bg-gray-100 text-gray-400 border-gray-200' : 'bg-white text-gray-600 border-gray-300 hover:border-slate-500 hover:text-slate-800'">
                                Sig. <i class="fas fa-chevron-right ml-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- MODALES DE ARCHIVO EXTERNO --}}
        <div x-show="archiveModal.show" x-transition.opacity x-cloak class="fixed inset-0 z-[150] bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.outside="archiveModal.show = false" class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-gray-800">Archivar expedientes</h2>
                        <p class="text-xs text-gray-400 mt-1"><span x-text="selectedIds.length"></span> expediente(s) seleccionados</p>
                    </div>
                    <button type="button" @click="archiveModal.show = false" class="w-9 h-9 rounded-lg text-gray-400 hover:bg-white hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex gap-3 text-blue-800">
                        <i class="fas fa-clock mt-0.5"></i>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest">Fecha de archivo</p>
                            <p class="text-xs font-bold mt-1">Se registrará automáticamente con la hora del servidor.</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones</label>
                        <textarea x-model="archiveModal.observaciones" rows="4" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button" @click="archiveModal.show = false" class="px-4 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-600 text-[10px] font-black uppercase tracking-widest hover:border-gray-400">Cancelar</button>
                    <button type="button" @click="archivarSeleccionados()" :disabled="archiveModal.saving" class="px-5 py-2.5 rounded-xl bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-blue-700 disabled:opacity-60 flex items-center gap-2">
                        <i class="fas" :class="archiveModal.saving ? 'fa-circle-notch fa-spin' : 'fa-check'"></i>
                        Confirmar
                    </button>
                </div>
            </div>
        </div>

        <div x-show="notifyModal.show" x-transition.opacity x-cloak class="fixed inset-0 z-[150] bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.outside="notifyModal.show = false" class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-gray-800">Notificar expedientes</h2>
                        <p class="text-xs text-gray-400 mt-1"><span x-text="selectedIds.length"></span> expediente(s) seleccionados</p>
                    </div>
                    <button type="button" @click="notifyModal.show = false" class="w-9 h-9 rounded-lg text-gray-400 hover:bg-white hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 flex gap-3 text-emerald-800">
                        <i class="fas fa-clock mt-0.5"></i>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest">Fecha de notificación</p>
                            <p class="text-xs font-bold mt-1">Se registrará automáticamente con la hora del servidor.</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Cargo adjunto</label>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="notifyModal.documento = $event.target.files[0] || null" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones</label>
                        <textarea x-model="notifyModal.observaciones" rows="4" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500 resize-none"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button" @click="notifyModal.show = false" class="px-4 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-600 text-[10px] font-black uppercase tracking-widest hover:border-gray-400">Cancelar</button>
                    <button type="button" @click="notificarSeleccionados()" :disabled="notifyModal.saving" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 disabled:opacity-60 flex items-center gap-2">
                        <i class="fas" :class="notifyModal.saving ? 'fa-circle-notch fa-spin' : 'fa-check'"></i>
                        Confirmar
                    </button>
                </div>
            </div>
        </div>

        <div x-show="loanModal.show" x-transition.opacity x-cloak class="fixed inset-0 z-[150] bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.outside="loanModal.show = false" class="bg-white w-full max-w-lg rounded-2xl shadow-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-gray-800">Prestar expedientes</h2>
                        <p class="text-xs text-gray-400 mt-1"><span x-text="selectedIds.length"></span> expediente(s) seleccionados</p>
                    </div>
                    <button type="button" @click="loanModal.show = false" class="w-9 h-9 rounded-lg text-gray-400 hover:bg-white hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 flex gap-3 text-amber-800">
                        <i class="fas fa-box-open mt-0.5"></i>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest">Préstamo físico</p>
                            <p class="text-xs font-bold mt-1">El expediente quedará en estado prestado y se liberará de la matriz física activa.</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones</label>
                        <textarea x-model="loanModal.observaciones" rows="4" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500 resize-none"></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button" @click="loanModal.show = false" class="px-4 py-2.5 rounded-xl border border-gray-300 bg-white text-gray-600 text-[10px] font-black uppercase tracking-widest hover:border-gray-400">Cancelar</button>
                    <button type="button" @click="prestarSeleccionados()" :disabled="loanModal.saving" class="px-5 py-2.5 rounded-xl bg-amber-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-amber-700 disabled:opacity-60 flex items-center gap-2">
                        <i class="fas" :class="loanModal.saving ? 'fa-circle-notch fa-spin' : 'fa-check'"></i>
                        Confirmar
                    </button>
                </div>
            </div>
        </div>

        <div x-show="detailModal.show" x-transition.opacity x-cloak class="fixed inset-0 z-[150] bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div @click.outside="detailModal.show = false" class="bg-white w-full max-w-5xl max-h-[90vh] rounded-2xl shadow-2xl border border-gray-200 overflow-hidden flex flex-col">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-gray-800">Detalle del expediente</h2>
                        <p class="text-xs text-gray-400 mt-1" x-text="detailModal.data?.expediente || ''"></p>
                    </div>
                    <button type="button" @click="detailModal.show = false" class="w-9 h-9 rounded-lg text-gray-400 hover:bg-white hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="overflow-auto custom-scrollbar p-6">
                    <template x-if="detailModal.loading">
                        <div class="py-20 text-center text-blue-600 font-black uppercase tracking-widest text-[10px]">
                            <i class="fas fa-circle-notch fa-spin text-lg mr-2"></i>
                            Cargando detalle...
                        </div>
                    </template>

                    <template x-if="!detailModal.loading && detailModal.data">
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Solicitante</p>
                                    <p class="text-sm font-bold text-gray-800 mt-1" x-text="detailModal.data.solicitante || '-'"></p>
                                </div>
                                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Documento</p>
                                    <p class="text-sm font-bold text-gray-800 mt-1" x-text="detailModal.data.documento || '-'"></p>
                                </div>
                                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Fecha ingreso</p>
                                    <p class="text-sm font-bold text-gray-800 mt-1" x-text="detailModal.data.fecha_ingreso || '-'"></p>
                                </div>
                                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Estado actual</p>
                                    <p class="text-sm font-bold text-gray-800 mt-1" x-text="detailModal.data.estado || '-'"></p>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xs font-black uppercase tracking-widest text-gray-500 mb-3">Historial de movimientos</h3>
                                <div class="space-y-3">
                                    <template x-for="step in detailModal.historial" :key="step.movimiento_id">
                                        <div class="border border-gray-200 rounded-xl p-4 flex gap-4" :class="step.activo ? 'bg-blue-50/50 border-blue-200' : 'bg-white'">
                                            <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0" :class="step.activo ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400'">
                                                <i class="fas fa-route text-xs"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1">
                                                    <p class="text-sm font-black text-gray-800" x-text="step.estado || '-'"></p>
                                                    <p class="text-xs font-bold text-gray-400" x-text="step.fecha || '-'"></p>
                                                </div>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <span x-text="step.origen"></span>
                                                    <span class="mx-2 text-gray-300">-&gt;</span>
                                                    <span x-text="step.destino"></span>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-2" x-text="step.observacion || '-'"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
