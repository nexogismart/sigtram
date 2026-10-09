<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bandeja de Archivo - SIGTRAM</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        :focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .sticky-head th { position: sticky; top: 0; z-index: 10; background: #f9fafb; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased" x-data="archivoExpedientes()" x-init="init()">
    <div x-show="toast.show" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]">
        <div class="px-6 py-2.5 rounded-full shadow-2xl flex items-center gap-3 text-white" :class="toast.type === 'error' ? 'bg-red-600/95' : 'bg-gray-900/95'">
            <i class="text-xs" :class="toast.type === 'error' ? 'fas fa-circle-exclamation' : 'fas fa-circle-info text-blue-300'"></i>
            <p class="text-[10px] font-black uppercase tracking-widest" x-text="toast.message"></p>
        </div>
    </div>

    <main class="p-4 sm:p-6 w-full max-w-[1600px] mx-auto min-h-screen flex flex-col">
        <nav class="mb-5" aria-label="Breadcrumb">
            <ol class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <li><a href="#" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li><a href="#" class="hover:text-blue-600 transition-colors">Tramite Externo</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600" aria-current="page">Bandeja de Archivo</li>
            </ol>
        </nav>

        <header class="mb-6 flex flex-col xl:flex-row xl:items-end justify-between gap-5">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-100 shrink-0">
                    <i class="fas fa-box-archive text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Bandeja de Archivo</h1>
                    <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-black">Expedientes finalizados, archivados y prestados</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <template x-for="card in metricCards" :key="card.key">
                    <button type="button" @click="setBandeja(card.key)" class="bg-white rounded-xl p-3 px-4 border shadow-sm flex items-center gap-4 transition-all hover:-translate-y-0.5 hover:shadow-md" :class="bandeja === card.key ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200'">
                        <div class="p-2.5 rounded-lg" :class="card.iconClass"><i :class="card.icon"></i></div>
                        <div class="text-left">
                            <div class="text-[10px] uppercase text-gray-500 font-bold tracking-wider" x-text="card.label"></div>
                            <div class="text-xl font-bold text-gray-800 leading-none mt-1" x-text="counts[card.key] ?? 0"></div>
                        </div>
                    </button>
                </template>
            </div>
        </header>

        <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex-1 flex flex-col">
            <div class="bg-gray-50/80 border-b border-gray-200 px-4 sm:px-6 py-4 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                <div class="inline-flex bg-gray-200/70 p-1 rounded-xl gap-1 w-full sm:w-auto">
                    <template x-for="tab in tabs" :key="tab.key">
                        <button type="button" @click="setBandeja(tab.key)" class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2" :class="bandeja === tab.key ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-white'">
                            <i :class="tab.icon"></i>
                            <span x-text="tab.label"></span>
                        </button>
                    </template>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" x-show="bandeja === 'finalizados'" @click="openNotifyModal()" :disabled="!canNotify" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-2" :class="canNotify ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-100' : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        <i class="fas fa-bell"></i>
                        Notificar
                    </button>
                    <button type="button" x-show="bandeja === 'archivados'" @click="openLoanModal()" :disabled="!canLoan" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-2" :class="canLoan ? 'bg-amber-600 hover:bg-amber-700 text-white shadow-md shadow-amber-100' : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        <i class="fas fa-share-from-square"></i>
                        Prestar
                    </button>
                    <button type="button" x-show="bandeja !== 'matriz'" @click="openArchiveModal()" :disabled="!canArchive" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-2" :class="canArchive ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-100' : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        <i class="fas fa-box-archive"></i>
                        <span x-text="bandeja === 'prestados' ? 'Devolver a archivo' : 'Archivar'"></span>
                    </button>
                    <button type="button" x-show="bandeja !== 'matriz'" @click="printBandeja()" class="px-4 py-2.5 bg-white border border-gray-300 text-gray-600 hover:text-blue-600 hover:border-blue-600 text-[10px] font-black uppercase tracking-widest rounded-xl transition-colors flex items-center gap-2">
                        <i class="fas fa-print"></i>
                        Imprimir
                    </button>
                    <button type="button" @click="fetchResults(pagination.current_page || 1)" class="px-4 py-2.5 bg-white border border-gray-300 text-gray-600 hover:text-blue-600 hover:border-blue-600 text-[10px] font-black uppercase tracking-widest rounded-xl transition-colors flex items-center gap-2">
                        <i class="fas fa-rotate-right"></i>
                        Actualizar
                    </button>
                </div>
            </div>

            <div x-show="bandeja !== 'matriz'" class="p-4 sm:p-5 bg-gray-50/40 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Buscar por</label>
                        <select x-model="filters.campo" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500">
                            <template x-for="field in searchFields" :key="field.value">
                                <option :value="field.value" x-text="field.label"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Criterio</label>
                        <div class="relative">
                            <input type="text" x-model="filters.criterio" @keydown.enter="fetchResults(1)" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 pr-10 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500" placeholder="Nro, nombre, estado u observacion">
                            <button type="button" x-show="filters.criterio" @click="filters.criterio = ''; fetchResults(1)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Desde</label>
                        <input type="date" x-model="filters.desde" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Hasta</label>
                        <input type="date" x-model="filters.hasta" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Filas</label>
                        <select x-model.number="filters.per_page" @change="fetchResults(1)" class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2.5 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="8">8</option>
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="md:col-span-12 flex flex-wrap justify-end gap-2">
                        <button type="button" @click="resetFilters()" class="px-4 py-2.5 bg-white border border-gray-300 text-gray-500 hover:text-rose-600 hover:border-rose-600 text-[10px] font-black uppercase tracking-widest rounded-xl transition-colors flex items-center gap-2">
                            <i class="fas fa-eraser"></i>
                            Limpiar
                        </button>
                        <button type="button" @click="fetchResults(1)" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md shadow-blue-100 flex items-center gap-2">
                            <i class="fas fa-search"></i>
                            Consultar
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="bandeja !== 'matriz'" class="px-4 sm:px-6 py-3 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                    <span x-text="pagination.total"></span> registros
                    <span class="mx-2 text-gray-300">|</span>
                    <span x-text="selectedIds.length"></span> seleccionados
                </p>
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400" x-text="activeTabDescription"></p>
            </div>

            <div x-show="bandeja !== 'matriz'" class="relative flex-1 min-h-[420px]">
                <div x-show="loading" x-cloak class="absolute inset-0 z-30 bg-white/70 backdrop-blur-[2px] flex items-center justify-center">
                    <div class="flex items-center gap-3 text-blue-600 font-black uppercase tracking-widest text-[10px]">
                        <i class="fas fa-circle-notch fa-spin text-lg"></i>
                        Cargando expedientes
                    </div>
                </div>

                <div class="overflow-auto custom-scrollbar max-h-[620px]">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="sticky-head">
                            <tr class="text-[10px] uppercase tracking-widest text-gray-500">
                                <th class="px-4 py-3 text-left w-12">
                                    <input type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" :checked="items.length > 0 && selectedIds.length === items.length" @change="toggleAll($event.target.checked)" :disabled="!isSelectableTab">
                                </th>
                                <th class="px-4 py-3 text-left">Expediente</th>
                                <th class="px-4 py-3 text-left">Solicitante</th>
                                <th class="px-4 py-3 text-left">Fecha mov.</th>
                                <th class="px-4 py-3 text-left">Estado</th>
                                <th class="px-4 py-3 text-left">Area</th>
                                <th class="px-4 py-3 text-left">Observaciones</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <template x-for="item in items" :key="item.id">
                                <tr class="hover:bg-blue-50/40 transition-colors">
                                    <td class="px-4 py-3 align-top">
                                        <input type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" :checked="selectedIds.includes(item.id)" @change="toggleOne(item.id, $event.target.checked)" :disabled="!isSelectableTab">
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="font-black text-gray-800" x-text="item.expediente || '-'"></div>
                                        <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mt-1">
                                            ID <span x-text="item.id"></span> - Folios <span x-text="item.folios"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="font-bold text-gray-700" x-text="item.solicitante || '-'"></div>
                                        <div class="text-xs text-gray-400" x-text="item.documento || ''"></div>
                                    </td>
                                    <td class="px-4 py-3 align-top text-gray-600 font-semibold whitespace-nowrap" x-text="item.fecha_movimiento || '-'"></td>
                                    <td class="px-4 py-3 align-top">
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest" :class="statusClass(item.estado_id)" x-text="item.estado || '-'"></span>
                                    </td>
                                    <td class="px-4 py-3 align-top text-gray-600 font-semibold max-w-[180px]" x-text="item.area || '-'"></td>
                                    <td class="px-4 py-3 align-top text-gray-500 max-w-[360px]">
                                        <p class="line-clamp-2" x-text="item.observaciones || '-'"></p>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="showDetail(item.id)" class="w-9 h-9 rounded-lg bg-white border border-gray-300 text-gray-500 hover:text-blue-600 hover:border-blue-500 transition-colors" title="Ver detalle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="!loading && items.length === 0">
                                <td colspan="8" class="px-6 py-16 text-center">
                                    <div class="mx-auto w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
                                        <i class="fas fa-folder-open"></i>
                                    </div>
                                    <p class="text-sm font-black text-gray-600 uppercase tracking-widest">Sin resultados</p>
                                    <p class="text-xs text-gray-400 mt-1">Ajusta los filtros o cambia de bandeja.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div x-show="bandeja === 'matriz'" x-cloak class="relative flex-1 min-h-[520px] p-5">
                <div x-show="matrix.loading" class="absolute inset-0 z-20 bg-white/70 backdrop-blur-[2px] flex items-center justify-center">
                    <div class="flex items-center gap-3 text-blue-600 font-black uppercase tracking-widest text-[10px]">
                        <i class="fas fa-circle-notch fa-spin text-lg"></i>
                        Cargando matriz
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 xl:grid-cols-8 gap-3">
                    <template x-for="cell in matrix.items" :key="cell.id">
                        <div class="border rounded-xl p-3 bg-white shadow-sm" :class="cell.ocupados > 0 ? 'border-blue-200' : 'border-gray-200'">
                            <div class="flex items-center justify-between gap-2">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="cell.ocupados > 0 ? 'bg-blue-50 text-blue-600' : 'bg-gray-50 text-gray-400'">
                                    <i class="fas fa-box-archive"></i>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-400" x-text="cell.ocupados + '/' + cell.maximo"></span>
                            </div>
                            <p class="text-xs font-black text-gray-800 mt-3" x-text="cell.nombre || ('Archivo ' + cell.id)"></p>
                            <p class="text-[10px] font-bold text-gray-400 mt-1">Fila <span x-text="cell.fila"></span> / Col. <span x-text="cell.columna"></span></p>
                            <p class="text-[10px] text-gray-500 mt-2 line-clamp-3" x-text="cell.expedientes || 'Sin expedientes activos'"></p>
                        </div>
                    </template>
                </div>
                <div x-show="!matrix.loading && matrix.items.length === 0" class="py-20 text-center">
                    <p class="text-sm font-black uppercase tracking-widest text-gray-500">Sin matriz configurada</p>
                </div>
            </div>

            <div x-show="bandeja !== 'matriz'" class="px-4 sm:px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                    Mostrando <span x-text="paginationFrom"></span>-<span x-text="paginationTo"></span> de <span x-text="pagination.total"></span>
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="fetchResults(pagination.current_page - 1)" :disabled="pagination.current_page <= 1 || loading" class="px-4 py-2 rounded-xl border text-[10px] font-black uppercase tracking-widest" :class="pagination.current_page <= 1 ? 'bg-gray-100 text-gray-400 border-gray-200' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-500 hover:text-blue-600'">
                        Anterior
                    </button>
                    <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                        Pagina
                        <input type="number" min="1" :max="pagination.last_page" x-model.number="goPage" @keydown.enter="goToPage()" class="w-16 rounded-lg border border-gray-300 px-2 py-2 text-xs font-bold text-gray-700">
                        de <span x-text="pagination.last_page"></span>
                    </div>
                    <button type="button" @click="fetchResults(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page || loading" class="px-4 py-2 rounded-xl border text-[10px] font-black uppercase tracking-widest" :class="pagination.current_page >= pagination.last_page ? 'bg-gray-100 text-gray-400 border-gray-200' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-500 hover:text-blue-600'">
                        Siguiente
                    </button>
                </div>
            </div>
        </section>
    </main>

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
                        <p class="text-xs font-bold mt-1">Se registrara automaticamente con la hora del servidor.</p>
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
                        <p class="text-[10px] font-black uppercase tracking-widest">Fecha de notificacion</p>
                        <p class="text-xs font-bold mt-1">Se registrara automaticamente con la hora del servidor.</p>
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
                        <p class="text-[10px] font-black uppercase tracking-widest">Prestamo fisico</p>
                        <p class="text-xs font-bold mt-1">El expediente quedara en estado prestado y se liberara de la matriz fisica activa.</p>
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
                        Cargando detalle
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

    <script>
        function archivoExpedientes() {
            return {
                endpoints: {
                    tabla: @json(route('tramites.archivo.tabla')),
                    archivar: @json(route('tramites.archivo.archivar')),
                    notificar: @json(route('tramites.archivo.notificar')),
                    prestar: @json(route('tramites.archivo.prestar')),
                    detalle: @json(route('tramites.archivo.detalle')),
                    matriz: @json(route('tramites.archivo.matriz')),
                    imprimir: @json(route('tramites.archivo.imprimir')),
                },
                csrf: document.querySelector('meta[name="csrf-token"]').content,
                bandeja: 'finalizados',
                loading: false,
                items: [],
                selectedIds: [],
                counts: { finalizados: 0, archivados: 0, prestados: 0 },
                pagination: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
                goPage: 1,
                filters: {
                    campo: 'expediente',
                    criterio: '',
                    desde: @json($desde),
                    hasta: @json($hasta),
                    per_page: 10,
                },
                searchFields: @json($searchFields),
                toast: { show: false, message: '', type: 'info' },
                archiveModal: { show: false, saving: false, observaciones: 'SE ARCHIVA SIN NOVEDAD' },
                notifyModal: { show: false, saving: false, documento: null, observaciones: 'SE LE ENTREGA EL EXPEDIENTE AL ADMINISTRADO' },
                loanModal: { show: false, saving: false, observaciones: 'SE PRESTA A: ' },
                detailModal: { show: false, loading: false, data: null, historial: [] },
                matrix: { loading: false, items: [] },
                tabs: [
                    { key: 'finalizados', label: 'Finalizados', icon: 'fas fa-check-double' },
                    { key: 'archivados', label: 'Archivados', icon: 'fas fa-box-archive' },
                    { key: 'prestados', label: 'Prestados', icon: 'fas fa-share-from-square' },
                    { key: 'matriz', label: 'Matriz', icon: 'fas fa-border-all' },
                ],
                metricCards: [
                    { key: 'finalizados', label: 'Finalizados', icon: 'fas fa-check-double', iconClass: 'bg-emerald-50 text-emerald-600' },
                    { key: 'archivados', label: 'Archivados', icon: 'fas fa-box-archive', iconClass: 'bg-blue-50 text-blue-600' },
                    { key: 'prestados', label: 'Prestados', icon: 'fas fa-share-from-square', iconClass: 'bg-amber-50 text-amber-600' },
                ],
                get isSelectableTab() {
                    return this.bandeja !== 'matriz';
                },
                get canArchive() {
                    return ['finalizados', 'prestados'].includes(this.bandeja) && this.selectedIds.length > 0 && !this.loading;
                },
                get canNotify() {
                    return this.bandeja === 'finalizados' && this.selectedIds.length > 0 && !this.loading;
                },
                get canLoan() {
                    return this.bandeja === 'archivados' && this.selectedIds.length > 0 && !this.loading;
                },
                get activeTabDescription() {
                    const text = {
                        finalizados: 'Expedientes listos para archivo',
                        archivados: 'Expedientes archivados disponibles para prestamo',
                        prestados: 'Expedientes prestados pendientes de retorno',
                        matriz: 'Control visual de archivadores fisicos',
                    };
                    return text[this.bandeja] || '';
                },
                get paginationFrom() {
                    if (this.pagination.total === 0) return 0;
                    return ((this.pagination.current_page - 1) * this.pagination.per_page) + 1;
                },
                get paginationTo() {
                    return Math.min(this.pagination.current_page * this.pagination.per_page, this.pagination.total);
                },
                init() {
                    this.fetchResults(1);
                },
                async postJson(url, payload) {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: JSON.stringify(payload),
                    });
                    const json = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const message = json.message || Object.values(json.errors || {}).flat()[0] || 'No se pudo completar la operacion.';
                        throw new Error(message);
                    }
                    return json;
                },
                async postForm(url, formData) {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: formData,
                    });
                    const json = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const message = json.message || Object.values(json.errors || {}).flat()[0] || 'No se pudo completar la operacion.';
                        throw new Error(message);
                    }
                    return json;
                },
                async fetchResults(page = 1) {
                    if (this.bandeja === 'matriz') {
                        await this.fetchMatrix();
                        return;
                    }
                    if (page < 1) return;
                    this.loading = true;
                    try {
                        const json = await this.postJson(this.endpoints.tabla, {
                            bandeja: this.bandeja,
                            campo: this.filters.campo,
                            criterio: this.filters.criterio,
                            desde: this.filters.desde,
                            hasta: this.filters.hasta,
                            per_page: this.filters.per_page,
                            page,
                        });
                        this.items = json.data || [];
                        this.pagination = json.pagination || this.pagination;
                        this.goPage = this.pagination.current_page;
                        this.counts = json.counts || this.counts;
                        this.selectedIds = this.selectedIds.filter(id => this.items.some(item => item.id === id));
                    } catch (error) {
                        this.notify(error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                setBandeja(next) {
                    if (this.bandeja === next) return;
                    this.bandeja = next;
                    this.selectedIds = [];
                    if (next === 'matriz') {
                        this.fetchMatrix();
                    } else {
                        this.fetchResults(1);
                    }
                },
                goToPage() {
                    const page = Math.max(1, Math.min(Number(this.goPage || 1), this.pagination.last_page || 1));
                    this.fetchResults(page);
                },
                resetFilters() {
                    this.filters.campo = 'expediente';
                    this.filters.criterio = '';
                    this.filters.desde = '';
                    this.filters.hasta = '';
                    this.filters.per_page = 10;
                    this.fetchResults(1);
                },
                toggleAll(checked) {
                    this.selectedIds = checked && this.isSelectableTab ? this.items.map(item => item.id) : [];
                },
                toggleOne(id, checked) {
                    if (!this.isSelectableTab) return;
                    if (checked && !this.selectedIds.includes(id)) {
                        this.selectedIds.push(id);
                    }
                    if (!checked) {
                        this.selectedIds = this.selectedIds.filter(value => value !== id);
                    }
                },
                openArchiveModal() {
                    if (!this.canArchive) return;
                    this.archiveModal.observaciones = 'SE ARCHIVA SIN NOVEDAD';
                    this.archiveModal.show = true;
                },
                async archivarSeleccionados() {
                    this.archiveModal.saving = true;
                    try {
                        const json = await this.postJson(this.endpoints.archivar, {
                            ids: this.selectedIds,
                            observaciones: this.archiveModal.observaciones,
                        });
                        this.archiveModal.show = false;
                        this.selectedIds = [];
                        this.notify(json.message || 'Expedientes archivados.');
                        await this.fetchResults(1);
                    } catch (error) {
                        this.notify(error.message, 'error');
                    } finally {
                        this.archiveModal.saving = false;
                    }
                },
                openNotifyModal() {
                    if (!this.canNotify) return;
                    this.notifyModal.observaciones = 'SE LE ENTREGA EL EXPEDIENTE AL ADMINISTRADO';
                    this.notifyModal.documento = null;
                    this.notifyModal.show = true;
                },
                async notificarSeleccionados() {
                    this.notifyModal.saving = true;
                    try {
                        const form = new FormData();
                        this.selectedIds.forEach(id => form.append('ids[]', id));
                        form.append('observaciones', this.notifyModal.observaciones);
                        if (this.notifyModal.documento) {
                            form.append('documento', this.notifyModal.documento);
                        }
                        const json = await this.postForm(this.endpoints.notificar, form);
                        this.notifyModal.show = false;
                        this.selectedIds = [];
                        this.notify(json.message || 'Expedientes notificados.');
                        await this.fetchResults(1);
                    } catch (error) {
                        this.notify(error.message, 'error');
                    } finally {
                        this.notifyModal.saving = false;
                    }
                },
                openLoanModal() {
                    if (!this.canLoan) return;
                    this.loanModal.observaciones = 'SE PRESTA A: ';
                    this.loanModal.show = true;
                },
                async prestarSeleccionados() {
                    this.loanModal.saving = true;
                    try {
                        const json = await this.postJson(this.endpoints.prestar, {
                            ids: this.selectedIds,
                            observaciones: this.loanModal.observaciones,
                        });
                        this.loanModal.show = false;
                        this.selectedIds = [];
                        this.notify(json.message || 'Expedientes prestados.');
                        await this.fetchResults(1);
                    } catch (error) {
                        this.notify(error.message, 'error');
                    } finally {
                        this.loanModal.saving = false;
                    }
                },
                async fetchMatrix() {
                    this.matrix.loading = true;
                    try {
                        const response = await fetch(this.endpoints.matriz, { headers: { 'Accept': 'application/json' } });
                        const json = await response.json();
                        if (!response.ok) throw new Error(json.message || 'No se pudo cargar la matriz.');
                        this.matrix.items = json.data || [];
                    } catch (error) {
                        this.notify(error.message, 'error');
                    } finally {
                        this.matrix.loading = false;
                    }
                },
                printBandeja() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = this.endpoints.imprimir;
                    form.target = '_blank';
                    const fields = {
                        _token: this.csrf,
                        bandeja: this.bandeja,
                        campo: this.filters.campo,
                        criterio: this.filters.criterio,
                        desde: this.filters.desde,
                        hasta: this.filters.hasta,
                    };
                    Object.entries(fields).forEach(([key, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value || '';
                        form.appendChild(input);
                    });
                    document.body.appendChild(form);
                    form.submit();
                    form.remove();
                },
                async showDetail(id) {
                    this.detailModal.show = true;
                    this.detailModal.loading = true;
                    this.detailModal.data = null;
                    this.detailModal.historial = [];
                    try {
                        const json = await this.postJson(this.endpoints.detalle, { id });
                        this.detailModal.data = json.data;
                        this.detailModal.historial = json.historial || [];
                    } catch (error) {
                        this.detailModal.show = false;
                        this.notify(error.message, 'error');
                    } finally {
                        this.detailModal.loading = false;
                    }
                },
                statusClass(id) {
                    if ([3, 26].includes(Number(id))) return 'bg-emerald-50 text-emerald-700';
                    if (Number(id) === 27) return 'bg-blue-50 text-blue-700';
                    if (Number(id) === 28) return 'bg-amber-50 text-amber-700';
                    return 'bg-gray-100 text-gray-600';
                },
                notify(message, type = 'info') {
                    this.toast = { show: true, message, type };
                    clearTimeout(this.toastTimer);
                    this.toastTimer = setTimeout(() => this.toast.show = false, 3600);
                },
            };
        }
    </script>
</body>
</html>
