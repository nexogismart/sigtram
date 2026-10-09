<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Seguimiento de Expedientes — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: "Figtree", "Segoe UI", Tahoma, sans-serif; }
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        :focus-visible { outline: 2px solid #334155; outline-offset: 2px; }
        .celda-ref { background-color: #fef08a; }
        .celda-tram { background-color: #f5f5dc; }
        .modal-overlay {
            position: fixed; inset: 0; z-index: 150;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 1rem; background: rgb(15 23 42 / 0.7); backdrop-filter: blur(8px);
        }
        .nav-active { background-color: #1e293b; color: #fff; }
        .nav-idle { color: #4b5563; }
        .nav-idle:hover { background-color: #f8fafc; }
    </style>
</head>
<body class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="correoExternoApp()">

    <div x-show="showToast" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]">
        <div class="bg-gray-900/90 backdrop-blur-md text-white px-6 py-2.5 rounded-full shadow-2xl flex items-center gap-3">
            <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
            <p class="text-[10px] font-black uppercase tracking-widest" x-text="toastMessage"></p>
        </div>
    </div>

    <div class="p-3 sm:p-5 w-full max-w-[1600px] mx-auto">
    <div class="grid grid-cols-1 xl:grid-cols-[220px_1fr] gap-4 min-h-[85vh]">

        <aside class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden xl:sticky xl:top-4 self-start">
            <div class="bg-slate-800 text-white px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Trámite Externo</p>
                <h1 class="text-sm font-bold mt-0.5">Bandejas</h1>
            </div>
            <nav class="p-3 space-y-4">
                <div>
                    <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Bandejas</p>
                    <div class="space-y-0.5">
                        <button type="button" @click="activeBandeja = 'entrada'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="activeBandeja === 'entrada' ? 'nav-active' : 'nav-idle'">
                            <span class="flex items-center gap-2"><i class="fas fa-inbox w-4 text-center"></i> Entrada</span>
                            <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="activeBandeja === 'entrada' ? 'bg-white/20 text-white' : 'bg-red-500 text-white'">{{ $countEntrada }}</span>
                        </button>
                        <button type="button" @click="activeBandeja = 'recibidos'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="activeBandeja === 'recibidos' ? 'nav-active' : 'nav-idle'">
                            <span class="flex items-center gap-2"><i class="fas fa-envelope-open w-4 text-center"></i> Recibidos</span>
                            <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="activeBandeja === 'recibidos' ? 'bg-white/20 text-white' : 'bg-emerald-500 text-white'">{{ $countRecibidos }}</span>
                        </button>
                        <button type="button" @click="activeBandeja = 'salida'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2" :class="activeBandeja === 'salida' ? 'nav-active' : 'nav-idle'">
                            <i class="fas fa-right-from-bracket w-4 text-center"></i> Salida
                        </button>
                        <button type="button" @click="activeBandeja = 'finalizados'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2" :class="activeBandeja === 'finalizados' ? 'nav-active' : 'nav-idle'">
                            <i class="fas fa-flag-checkered w-4 text-center"></i> Finali-Notifi
                        </button>
                        <button type="button" @click="activeBandeja = 'archivo'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2" :class="activeBandeja === 'archivo' ? 'nav-active' : 'nav-idle'">
                            <i class="fas fa-archive w-4 text-center"></i> Archivo
                        </button>
                        <button type="button" @click="activeBandeja = 'todos'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2" :class="activeBandeja === 'todos' ? 'nav-active' : 'nav-idle'">
                            <i class="fas fa-layer-group w-4 text-center"></i> Todos
                        </button>
                    </div>
                </div>
                <div class="border-t border-gray-100 pt-3">
                    <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Reportes</p>
                    <button type="button" @click="activeBandeja = 'sin_finalizar'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2" :class="activeBandeja === 'sin_finalizar' ? 'nav-active' : 'nav-idle'">
                        <i class="fas fa-clock w-4 text-center"></i> Sin Finalizar
                    </button>
                </div>
            </nav>
        </aside>

        <section class="min-w-0 flex flex-col gap-3">
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm sm:text-base font-bold uppercase tracking-wide">
                        Bandeja Trámite Externo —
                        <span x-text="{ entrada:'Entrada', recibidos:'Recibidos', salida:'Salida', finalizados:'Finali-Notifi', archivo:'Archivo', todos:'Todos', sin_finalizar:'Sin Finalizar' }[activeBandeja] || activeBandeja"></span>
                    </h2>
                    <span class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full"><span x-text="selectedIds.length"></span> seleccionados</span>
                </div>

                <div class="px-4 py-2.5 border-b border-gray-100 flex flex-wrap items-center gap-3 text-xs font-bold text-slate-700 bg-white">
                    <div class="flex flex-wrap items-center gap-2 flex-1">
                        <template x-if="activeBandeja === 'entrada'">
                            <button @click="modalRecepcionar = true" :disabled="selectedIds.length === 0" 
                                    class="px-4 py-2 bg-slate-600 hover:bg-slate-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md flex items-center gap-2">
                                <i class="fas fa-clipboard-check"></i>
                                Recepcionar
                            </button>
                        </template>

                       
                        <template x-if="activeBandeja === 'recibidos'">
                            <div class="flex flex-wrap items-center gap-2">
                                <button @click="modalDerivar = true" :disabled="selectedIds.length === 0" 
                                        class="px-4 py-2 bg-slate-600 hover:bg-slate-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md flex items-center gap-2">
                                    <i class="fas fa-share-square"></i>
                                    Derivar
                                </button>
                                <button @click="modalAsignar = true" :disabled="selectedIds.length === 0" 
                                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md flex items-center gap-2">
                                    <i class="fas fa-user-tag"></i>
                                    Asignar
                                </button>
                                <button @click="modalEnviar = true" :disabled="selectedIds.length === 0" 
                                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md flex items-center gap-2">
                                    <i class="fas fa-layer-group"></i>
                                    Enviar
                                </button>
                                <button @click="modalFinalizar = true" :disabled="selectedIds.length === 0" 
                                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md flex items-center gap-2">
                                    <i class="fas fa-check-circle"></i>
                                    Finalizar
                                </button>
                            </div>
                        </template>

                        
                        <form action="{{ route('mod_gm.segi_expedi.imprimir_bandeja') }}" method="POST" target="_blank" class="inline-block">
                            @csrf
                            <input type="hidden" name="bandeja" :value="activeBandeja">
                            <input type="hidden" name="criterio" :value="criterio">
                            <input type="hidden" name="descripcion" :value="descripcion">
                            <input type="hidden" name="estado" :value="estado">
                            <input type="hidden" name="desde" :value="desde">
                            <input type="hidden" name="hasta" :value="hasta">
                            <button type="submit" class="px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center gap-2">
                                <i class="fas fa-print"></i>
                                Imprimir Bandeja
                            </button>
                        </form>
                    </div>
                </div>

                
                <div class="border-b border-gray-100 px-4 py-4 bg-gray-50/70">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Búsqueda por</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Criterio</label>
                            <select x-model="criterio" @change="page=1; fetchItems()" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                                <option value="expe_todo">Nº de Expediente</option>
                                <option value="expe_varios">Nº de Expediente Varios</option>
                                <option value="solicitante">Nombre/Razón Social</option>
                                <option value="observaciones">Observaciones</option>
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Descripción</label>
                            <input type="text" x-model="descripcion" @input.debounce.500ms="page=1; fetchItems()" placeholder="Texto a buscar..." class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Estado</label>
                            <select x-model="estado" @change="page=1; fetchItems()" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                                <option value="0">Buscar en todos</option>
                                <template x-for="e in estados" :key="e.cdgo_estdo_trmte">
                                    <option :value="e.cdgo_estdo_trmte" x-text="e.dc_estdo_trmte"></option>
                                </template>
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Desde</label>
                            <input type="date" x-model="desde" @change="page=1; fetchItems()" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Hasta</label>
                            <input type="date" x-model="hasta" @change="page=1; fetchItems()" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                        </div>
                        <div class="lg:col-span-2 flex gap-2">
                            <button @click="page=1; fetchItems()" class="w-full py-2.5 bg-slate-600 hover:bg-slate-700 text-white text-[11px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all flex items-center justify-center gap-1">
                                <i class="fas fa-search"></i> <span class="hidden xl:inline">Consultar</span>
                            </button>
                            <button @click="cleanFilters()" title="Limpiar" class="w-full py-2.5 bg-gray-400 hover:bg-gray-500 text-white text-[11px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all flex items-center justify-center">
                                <i class="fas fa-eraser"></i>
                            </button>
                        </div>
                    </div>
                </div>

                
                <div class="overflow-auto custom-scrollbar max-h-[400px]">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-200 bg-slate-50 sticky top-0 z-10 shadow-sm">
                                <th class="px-6 py-4 text-center w-12">
                                    <input type="checkbox" @click="toggleSelectAll()" :checked="selectedIds.length === items.length" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                                </th>
                                <th class="px-6 py-4 w-12 text-center">Orig.</th>
                                <th class="px-6 py-4">Nº Exp.</th>
                                <th class="px-6 py-4">Nombre o Razón Social</th>
                                <th class="px-6 py-4">F. Entrada</th>
                                <th class="px-6 py-4">Estado</th>
                                <th class="px-6 py-4">Observaciones</th>
                                <th class="px-6 py-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in items" :key="item.cdgo_dto_trmte">
                                <tr class="border-b border-gray-100 hover:bg-slate-50/50 cursor-pointer transition-colors" @click="showDetail(item)" :class="selectedExpediente?.cdgo_dto_trmte === item.cdgo_dto_trmte ? 'bg-slate-50/50' : ''">
                                    <td class="px-6 py-4 text-center" @click.stop>
                                        <input type="checkbox" :value="item.cdgo_dto_trmte" x-model="selectedIds" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                                    </td>
                                    <td class="px-6 py-4 text-center font-black text-xs" x-text="item.orig_doc"></td>
                                    <td class="px-6 py-4 font-black text-sm text-gray-800" x-html="highlightMatch(item.nu_expe_todo)"></td>
                                    <td class="px-6 py-4 text-xs font-bold text-gray-600" x-html="highlightMatch(item.no_crto)"></td>
                                    <td class="px-6 py-4 text-xs text-gray-500" x-text="item.fe_rcbdo_trmte"></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1 text-[9px] font-black rounded-full uppercase tracking-wider"
                                              :class="item.dc_estdo_trmte === 'PENDIENTE' ? 'bg-yellow-50 text-yellow-700 border border-yellow-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'"
                                              x-text="item.dc_estdo_trmte"></span>
                                    </td>
                                    <td class="px-6 py-4 text-xs text-gray-500 max-w-xs truncate" x-html="highlightMatch(item.de_obse_trmte)"></td>
                                    <td class="px-6 py-4 text-center" @click.stop>
                                        <div class="flex items-center justify-center gap-2">
                                            
                                            <button @click="showDetalle(item)" class="w-8 h-8 flex items-center justify-center bg-gray-50 hover:bg-slate-200 text-gray-500 rounded-lg transition-colors border border-gray-200" title="Ver Detalle">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            
                                            <button @click="window.open('/mod_gm/mod_tram_exte/segi_expedi/hoja-ruta/' + item.cdgo_dto_trmte, '_blank', 'width=800,height=600')" class="w-8 h-8 flex items-center justify-center bg-gray-50 hover:bg-slate-200 text-gray-500 rounded-lg transition-colors border border-gray-200" title="Hoja de Ruta">
                                                <i class="fas fa-print text-xs"></i>
                                            </button>
                                            
                                            <!-- Indicador de semaforización (Silencio Administrativo) -->
                                            <button @click="showSilencio(item)" class="w-8 h-8 flex items-center justify-center rounded-lg border" 
                                                    :class="item.dias_acumulados >= (item.nu_tiem_sile + item.nu_tiem_sile_adcnal) && (item.nu_tiem_sile > 0) ? 'bg-red-50 hover:bg-red-100 text-red-600 border-red-200' : 'bg-gray-50 hover:bg-slate-200 text-gray-400 border-gray-200'"
                                                    :title="'Días Acumulados: ' + item.dias_acumulados + ' / Silencio: ' + (item.nu_tiem_sile + item.nu_tiem_sile_adcnal)">
                                                <i class="fas text-[10px]" :class="item.dias_acumulados >= (item.nu_tiem_sile + item.nu_tiem_sile_adcnal) && (item.nu_tiem_sile > 0) ? 'fa-exclamation-triangle' : 'fa-eye'"></i>
                                            </button>

                                            <!-- Video Solicitud (si tiene) -->
                                            <template x-if="item.has_video">
                                                <button @click="showVideoRequests(item)" class="w-8 h-8 flex items-center justify-center bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-lg transition-colors border border-slate-200" title="Ver Solicitudes de Video">
                                                    <i class="fas fa-video text-xs"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Paginación -->
                <footer class="p-4 bg-slate-50 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                        <div class="flex justify-center space-x-4 mt-4">
                            <button @click="prevPage" :disabled="page <= 1" class="px-3 py-1 bg-white border border-gray-300 hover:bg-gray-50 rounded shadow-sm disabled:opacity-50 transition-colors">← Anterior</button>
                            <span class="flex items-center gap-1">Página <span class="text-slate-700 px-2 font-bold" x-text="page"></span> de <span x-text="lastPage"></span></span>
                            <button @click="nextPage" :disabled="page >= lastPage" class="px-3 py-1 bg-white border border-gray-300 hover:bg-gray-50 rounded shadow-sm disabled:opacity-50 transition-colors">Siguiente →</button>
                        </div>
                    </div>
                </footer>
            </div>{{-- fin card lista --}}

            <div x-show="selectedExpediente" x-transition x-cloak class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden min-h-[220px]">
                <div class="bg-gray-100 px-4 py-2 border-b border-gray-200 flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                        <i class="fas fa-file-lines mr-1 text-slate-600"></i>
                        Detalle / historial del expediente
                        <span class="text-slate-700 ml-2" x-text="selectedExpediente?.nu_expe_todo"></span>
                    </p>
                    <button type="button" @click="selectedExpediente = null" class="text-gray-400 hover:text-red-500 text-xs"><i class="fas fa-times"></i></button>
                </div>
                <div class="overflow-auto custom-scrollbar max-h-[320px] p-2">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-200 bg-slate-50 sticky top-0 z-10 shadow-sm">
                            <th class="px-4 py-3">F. Entrada</th>
                            <th class="px-4 py-3 text-center">T.E (Tiempo Transcurrido)</th>
                            <th class="px-4 py-3">Área / Oficina</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Observaciones</th>
                            <th class="px-4 py-3 text-center">U (Usuario)</th>
                            <th class="px-4 py-3 text-center">Adjuntos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="hist in history">
                            <tr class="border-b border-gray-100 text-xs">
                                <td class="px-4 py-3 text-gray-500" x-text="hist.fe_entrada"></td>
                                <td class="px-4 py-3 text-center font-bold text-slate-700" x-text="hist.dias"></td>
                                <td class="px-4 py-3 font-bold text-gray-800" x-text="hist.area"></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold"
                                          :class="hist.estado === 'PENDIENTE' ? 'bg-yellow-100 text-yellow-800' : 'bg-emerald-100 text-emerald-800'"
                                          x-text="hist.estado"></span>
                                </td>
                                <td class="px-4 py-3 text-gray-600" x-text="hist.observaciones"></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="cursor-help px-2 py-1 bg-gray-100 hover:bg-slate-200 text-[10px] font-bold rounded-lg" :title="hist.usuario">
                                        <i class="fas fa-user-circle"></i> Info
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a :href="'/mod_gm/mod_tram_exte/segi_expedi/descargar/' + hist.cdgo_trmte_dcmnto" target="_blank" class="text-slate-700 hover:text-slate-900" title="Descargar Adjunto">
                                        <i class="fas fa-paperclip"></i>
                                    </a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
            </div>
        </section>
    </div>
    </div>
    

    <!-- MODAL SILENCIO ADMINISTRATIVO -->
    <div x-show="modalSilencio" x-transition x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden" @click.outside="modalSilencio = false">
            <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold flex items-center gap-2">
                    <i class="fas fa-clock text-white/80"></i>
                    Silencio Administrativo
                </h3>
                <button @click="modalSilencio = false" class="hover:opacity-80"><i class="fas fa-times"></i></button>
            </header>
            <div class="p-6 space-y-4" x-show="silencioData">
                <div class="text-center p-4 rounded-xl border" :class="silencioData?.dias_acumulados >= (silencioData?.nu_tiem_sile + silencioData?.nu_tiem_sile_adcnal) ? 'bg-red-50 border-red-200' : 'bg-emerald-50 border-emerald-200'">
                    <i class="fas fa-exclamation-triangle text-3xl mb-2" :class="silencioData?.dias_acumulados >= (silencioData?.nu_tiem_sile + silencioData?.nu_tiem_sile_adcnal) ? 'text-red-500' : 'text-emerald-500'"></i>
                    <h4 class="font-black text-gray-800" x-text="silencioData?.dias_acumulados >= (silencioData?.nu_tiem_sile + silencioData?.nu_tiem_sile_adcnal) ? 'Plazo Vencido' : 'En Plazo'"></h4>
                </div>
                
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Días Permitidos de Ley</span>
                        <span class="font-bold text-gray-800 text-lg" x-text="silencioData?.nu_tiem_sile"></span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Días Adicionales (Prórroga)</span>
                        <span class="font-bold text-gray-800 text-lg" x-text="silencioData?.nu_tiem_sile_adcnal"></span>
                    </div>
                    <div class="col-span-2 pt-2 border-t border-gray-100">
                        <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Días Transcurridos desde Ingreso</span>
                        <span class="font-bold text-lg" :class="silencioData?.dias_acumulados >= (silencioData?.nu_tiem_sile + silencioData?.nu_tiem_sile_adcnal) ? 'text-red-600' : 'text-emerald-600'" x-text="silencioData?.dias_acumulados"></span>
                    </div>
                </div>
            </div>
            <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end">
                <button type="button" @click="modalSilencio = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cerrar</button>
            </footer>
        </div>
    </div>

    <!-- MODAL RECEPCIONAR -->
    <div x-show="modalRecepcionar" x-transition x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-xl overflow-hidden" @click.outside="modalRecepcionar = false">
            <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold flex items-center gap-2">
                    <i class="fas fa-clipboard-check text-white/80"></i>
                    Recepcionar Expedientes
                </h3>
                <button @click="modalRecepcionar = false" class="hover:opacity-80"><i class="fas fa-times"></i></button>
            </header>
            <div class="p-6 space-y-4">
                <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">
                    Va a recepcionar <span class="text-slate-700" x-text="selectedIds.length"></span> expediente(s) seleccionado(s).
                </p>
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Fecha de Recepción</label>
                    <input type="datetime-local" x-model="recepcionFecha" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones</label>
                    <textarea x-model="recepcionObs" rows="4" class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
                </div>
            </div>
            <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
                <button @click="modalRecepcionar = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
                <button @click="submitRecepcionar()" class="px-4 py-2 bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md hover:bg-slate-700 transition-colors">Recepcionar</button>
            </footer>
        </div>
    </div>

    <!-- MODAL DERIVAR -->
    <div x-show="modalDerivar" x-transition x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-2xl overflow-hidden" @click.outside="modalDerivar = false">
            <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold flex items-center gap-2">
                    <i class="fas fa-share-square text-white/80"></i>
                    Derivar Expedientes
                </h3>
                <button @click="modalDerivar = false" class="hover:opacity-80"><i class="fas fa-times"></i></button>
            </header>
            <form x-ref="formDerivar" @submit.prevent="submitDerivar($refs.formDerivar)" enctype="multipart/form-data">
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">
                        Va a derivar <span class="text-slate-700" x-text="selectedIds.length"></span> expediente(s) seleccionado(s).
                    </p>
                    
                    <fieldset class="border border-gray-200 rounded-xl p-4 space-y-3">
                        <legend class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-2">Destino</legend>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Gerencia Destino</label>
                            <select x-model="derivarGerencia" @change="derivarSubgerencia = '0'" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                <option value="0">-- Seleccione --</option>
                                <template x-for="g in getGerencias()" :key="g.id">
                                    <option :value="g.id" x-text="g.nombre"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Sub Gerencia Destino</label>
                            <select x-model="derivarSubgerencia" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                <option value="0">-- Seleccione --</option>
                                <template x-for="s in getSubgerencias(derivarGerencia)" :key="s.id">
                                    <option :value="s.id" x-text="s.nombre"></option>
                                </template>
                            </select>
                        </div>
                    </fieldset>

                    <div class="flex items-center justify-end">
                        <label class="flex items-center gap-2 text-xs font-bold text-gray-600 cursor-pointer">
                            <input type="checkbox" x-model="derivarConTramite" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                            Enviar con trámite interno asociado
                        </label>
                    </div>

                    <!-- Formulario de trámite interno asociado -->
                    <div x-show="derivarConTramite" x-transition x-cloak class="border border-slate-200 bg-slate-50/50 rounded-xl p-4 space-y-3">
                        <h4 class="text-[10px] font-black text-slate-700 uppercase tracking-widest border-b border-slate-100 pb-1.5">Crear Trámite Interno</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Tipo de Documento</label>
                                <select x-model="derivarDocTipo" @change="fetchSemilla('derivar')" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                    <option value="0">-- Seleccione --</option>
                                    <template x-for="td in tiposDocumento" :key="td.cdgo_tpo_trmte">
                                        <option :value="td.cdgo_tpo_trmte" x-text="td.de_tpo_trmte"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">N° Trámite</label>
                                <div class="flex gap-1.5">
                                    <input type="text" x-model="derivarTramiteNro" placeholder="0001-2026" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                    <button type="button" @click="fetchSemilla('derivar')" class="px-3 bg-slate-600 hover:bg-slate-700 text-white text-[9px] font-black rounded-xl uppercase tracking-widest transition-colors shadow-sm">Generar</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">PDF Firmado digitalmente</label>
                            <input type="file" name="fle_adjunto_pdf" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-slate-500">
                        </div>
                    </div>

                    <!-- Adjuntar Anexos Múltiples -->
                    <fieldset class="border border-gray-200 rounded-xl p-4 space-y-2.5">
                        <legend class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-2">Adjuntar Anexos</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="file" name="fle_adjunto1" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                            <input type="file" name="fle_adjunto2" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                            <input type="file" name="fle_adjunto3" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                            <input type="file" name="fle_adjunto4" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-24">
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">N° Folios</label>
                                <input type="number" x-model="derivarFolios" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none">
                            </div>
                            <p class="text-[10px] text-gray-400 font-medium self-end mb-1">Cantidad total de folios adjuntados.</p>
                        </div>
                    </fieldset>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Fecha de Derivación</label>
                        <input type="datetime-local" x-model="derivarFecha" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones</label>
                        <textarea x-model="derivarObs" rows="3" class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
                    </div>
                </div>
                <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
                    <button type="button" @click="modalDerivar = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md hover:bg-slate-700 transition-colors">Derivar</button>
                </footer>
            </form>
        </div>
    </div>

    <!-- MODAL ASIGNAR -->
    <div x-show="modalAsignar" x-transition x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-2xl overflow-hidden" @click.outside="modalAsignar = false">
            <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold flex items-center gap-2">
                    <i class="fas fa-user-tag text-white/80"></i>
                    Asignar Expedientes
                </h3>
                <button @click="modalAsignar = false" class="hover:opacity-80"><i class="fas fa-times"></i></button>
            </header>
            <form x-ref="formAsignar" @submit.prevent="submitAsignar($refs.formAsignar)" enctype="multipart/form-data">
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">
                        Va a asignar <span class="text-slate-700" x-text="selectedIds.length"></span> expediente(s) seleccionado(s) a personal de su misma área.
                    </p>
                    
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Asignar a Responsable</label>
                        <select x-model="asignarCargo" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                            <option value="0">-- Seleccione cargo del área --</option>
                            <template x-for="c in cargos" :key="c.cdgo_crgo">
                                <option :value="c.cdgo_crgo" x-text="c.de_crgo + ' (' + c.no_crto + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div class="flex items-center justify-end">
                        <label class="flex items-center gap-2 text-xs font-bold text-gray-600 cursor-pointer">
                            <input type="checkbox" x-model="asignarConTramite" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                            Enviar con trámite interno asociado
                        </label>
                    </div>

                    <!-- Formulario de trámite interno asociado -->
                    <div x-show="asignarConTramite" x-transition x-cloak class="border border-slate-200 bg-slate-50/50 rounded-xl p-4 space-y-3">
                        <h4 class="text-[10px] font-black text-slate-700 uppercase tracking-widest border-b border-slate-100 pb-1.5">Crear Trámite Interno</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Tipo de Documento</label>
                                <select x-model="asignarDocTipo" @change="fetchSemilla('asignar')" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                    <option value="0">-- Seleccione --</option>
                                    <template x-for="td in tiposDocumento" :key="td.cdgo_tpo_trmte">
                                        <option :value="td.cdgo_tpo_trmte" x-text="td.de_tpo_trmte"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">N° Trámite</label>
                                <div class="flex gap-1.5">
                                    <input type="text" x-model="asignarTramiteNro" placeholder="0001-2026" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                    <button type="button" @click="fetchSemilla('asignar')" class="px-3 bg-slate-600 hover:bg-slate-700 text-white text-[9px] font-black rounded-xl uppercase tracking-widest transition-colors shadow-sm">Generar</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">PDF Firmado digitalmente</label>
                            <input type="file" name="fle_adjunto_pdf" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-slate-500">
                        </div>
                    </div>

                    <!-- Adjuntar Anexos Múltiples -->
                    <fieldset class="border border-gray-200 rounded-xl p-4 space-y-2.5">
                        <legend class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-2">Adjuntar Anexos</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="file" name="fle_adjunto1" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                            <input type="file" name="fle_adjunto2" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-24">
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">N° Folios</label>
                                <input type="number" x-model="asignarFolios" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none">
                            </div>
                        </div>
                    </fieldset>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Fecha de Asignación</label>
                        <input type="datetime-local" x-model="asignarFecha" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones</label>
                        <textarea x-model="asignarObs" rows="3" class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
                    </div>
                </div>
                <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
                    <button type="button" @click="modalAsignar = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md hover:bg-indigo-700 transition-colors">Asignar</button>
                </footer>
            </form>
        </div>
    </div>

    <!-- MODAL ENVIAR (MULTIPLE) -->
    <div x-show="modalEnviar" x-transition x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-2xl overflow-hidden" @click.outside="modalEnviar = false">
            <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold flex items-center gap-2">
                    <i class="fas fa-layer-group text-white/80"></i>
                    Enviar a Múltiples Áreas
                </h3>
                <button @click="modalEnviar = false" class="hover:opacity-80"><i class="fas fa-times"></i></button>
            </header>
            <form x-ref="formEnviar" @submit.prevent="submitEnviar($refs.formEnviar)" enctype="multipart/form-data">
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">
                        Va a enviar <span class="text-slate-700" x-text="selectedIds.length"></span> expediente(s) a múltiples oficinas de destino en paralelo.
                    </p>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Áreas de Destino</label>
                        <div class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 h-48 overflow-y-auto custom-scrollbar">
                            <template x-for="a in areas" :key="a.id">
                                <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer py-1.5 hover:bg-gray-200 rounded px-2 transition-colors" :class="a.level === 0 ? 'font-bold' : (a.level === 1 ? 'ml-4' : 'ml-8')">
                                    <input type="checkbox" :value="a.id" x-model="enviarAreas" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500 w-4 h-4">
                                    <span x-text="a.nombre"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div class="flex items-center justify-end">
                        <label class="flex items-center gap-2 text-xs font-bold text-gray-600 cursor-pointer">
                            <input type="checkbox" x-model="enviarConTramite" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                            Enviar con trámite interno asociado
                        </label>
                    </div>

                    <!-- Formulario de trámite interno asociado -->
                    <div x-show="enviarConTramite" x-transition x-cloak class="border border-slate-200 bg-slate-50/50 rounded-xl p-4 space-y-3">
                        <h4 class="text-[10px] font-black text-slate-700 uppercase tracking-widest border-b border-slate-100 pb-1.5">Crear Trámite Interno</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Tipo de Documento</label>
                                <select x-model="enviarDocTipo" @change="fetchSemilla('enviar')" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                    <option value="0">-- Seleccione --</option>
                                    <template x-for="td in tiposDocumento" :key="td.cdgo_tpo_trmte">
                                        <option :value="td.cdgo_tpo_trmte" x-text="td.de_tpo_trmte"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">N° Trámite</label>
                                <div class="flex gap-1.5">
                                    <input type="text" x-model="enviarTramiteNro" placeholder="0001-2026" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                    <button type="button" @click="fetchSemilla('enviar')" class="px-3 bg-slate-600 hover:bg-slate-700 text-white text-[9px] font-black rounded-xl uppercase tracking-widest transition-colors shadow-sm">Generar</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">PDF Firmado digitalmente</label>
                            <input type="file" name="fle_adjunto_pdf" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-slate-500">
                        </div>
                    </div>

                    <!-- Adjuntar Anexos Múltiples -->
                    <fieldset class="border border-gray-200 rounded-xl p-4 space-y-2.5">
                        <legend class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-2">Adjuntar Anexos</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="file" name="fle_adjunto1" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                            <input type="file" name="fle_adjunto2" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-24">
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">N° Folios</label>
                                <input type="number" x-model="enviarFolios" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none">
                            </div>
                        </div>
                    </fieldset>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Fecha de Envió</label>
                        <input type="datetime-local" x-model="enviarFecha" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones</label>
                        <textarea x-model="enviarObs" rows="3" class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
                    </div>
                </div>
                <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
                    <button type="button" @click="modalEnviar = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md hover:bg-emerald-700 transition-colors">Enviar Copias</button>
                </footer>
            </form>
        </div>
    </div>

    <!-- MODAL FINALIZAR -->
    <div x-show="modalFinalizar" x-transition x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-2xl overflow-hidden" @click.outside="modalFinalizar = false">
            <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold flex items-center gap-2">
                    <i class="fas fa-check-circle text-rose-600"></i>
                    Finalizar Expediente
                </h3>
                <button @click="modalFinalizar = false" class="hover:opacity-80"><i class="fas fa-times"></i></button>
            </header>
            <form x-ref="formFinalizar" @submit.prevent="submitFinalizar($refs.formFinalizar)" enctype="multipart/form-data">
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">
                        Va a dar por finalizado <span class="text-slate-700" x-text="selectedIds.length"></span> expediente(s) seleccionado(s).
                    </p>

                    <div class="flex items-center justify-end">
                        <label class="flex items-center gap-2 text-xs font-bold text-gray-600 cursor-pointer">
                            <input type="checkbox" x-model="finalizarConTramite" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                            Enviar con trámite interno asociado
                        </label>
                    </div>

                    <!-- Formulario de trámite interno asociado -->
                    <div x-show="finalizarConTramite" x-transition x-cloak class="border border-slate-200 bg-slate-50/50 rounded-xl p-4 space-y-3">
                        <h4 class="text-[10px] font-black text-slate-700 uppercase tracking-widest border-b border-slate-100 pb-1.5">Crear Trámite Interno</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Tipo de Documento</label>
                                <select x-model="finalizarDocTipo" @change="fetchSemilla('finalizar')" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                    <option value="0">-- Seleccione --</option>
                                    <template x-for="td in tiposDocumento" :key="td.cdgo_tpo_trmte">
                                        <option :value="td.cdgo_tpo_trmte" x-text="td.de_tpo_trmte"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">N° Trámite</label>
                                <div class="flex gap-1.5">
                                    <input type="text" x-model="finalizarTramiteNro" placeholder="0001-2026" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                                    <button type="button" @click="fetchSemilla('finalizar')" class="px-3 bg-slate-600 hover:bg-slate-700 text-white text-[9px] font-black rounded-xl uppercase tracking-widest transition-colors shadow-sm">Generar</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">PDF Firmado digitalmente</label>
                            <input type="file" name="fle_adjunto_pdf" class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-slate-500">
                        </div>
                    </div>

                    <!-- Adjuntar Anexos Múltiples -->
                    <fieldset class="border border-gray-200 rounded-xl p-4 space-y-2.5">
                        <legend class="text-[10px] font-black text-gray-400 uppercase tracking-widest px-2">Adjuntar Documentos Finales</legend>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="file" name="fle_adjunto1" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                            <input type="file" name="fle_adjunto2" class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-24">
                                <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">N° Folios</label>
                                <input type="number" x-model="finalizarFolios" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none">
                            </div>
                        </div>
                    </fieldset>

                    <!-- Notificación Correo -->
                    <div class="bg-slate-50 border border-gray-200 rounded-xl p-4 space-y-3">
                        <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-200 pb-1.5">Notificar al Solicitante</h4>
                        <div class="flex items-center gap-6">
                            <span class="text-xs font-bold text-gray-600">¿Enviar correo de confirmación?</span>
                            <div class="flex gap-4">
                                <label class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 cursor-pointer">
                                    <input type="radio" value="1" x-model="finalizarEnviarCorreo" class="text-slate-700 focus:ring-slate-500">
                                    Sí
                                </label>
                                <label class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 cursor-pointer">
                                    <input type="radio" value="2" x-model="finalizarEnviarCorreo" class="text-slate-700 focus:ring-slate-500">
                                    No
                                </label>
                            </div>
                        </div>
                        <div x-show="finalizarEnviarCorreo === '1'" x-transition x-cloak class="bg-white border border-gray-200 rounded-lg p-2.5 text-[11px] font-semibold text-gray-500">
                            📧 El correo se enviará a la dirección registrada por el ciudadano.
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Fecha de Finalización</label>
                        <input type="datetime-local" x-model="finalizarFecha" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones Finales / Detalle de Cierre</label>
                        <textarea x-model="finalizarObs" rows="3" placeholder="Detalle final del trámite..." class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
                    </div>
                </div>
                <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
                    <button type="button" @click="modalFinalizar = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-rose-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md hover:bg-rose-700 transition-colors">Finalizar</button>
                </footer>
            </form>
        </div>
    </div>

    <!-- MODAL SOLICITUDES DE VIDEO -->
    <div x-show="modalVideoRequests" x-transition x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-2xl overflow-hidden" @click.outside="modalVideoRequests = false">
            <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold flex items-center gap-2">
                    <i class="fas fa-video text-white/80"></i>
                    Solicitudes de Copia de Video
                </h3>
                <button @click="modalVideoRequests = false" class="hover:opacity-80"><i class="fas fa-times"></i></button>
            </header>
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">
                    Solicitudes asociadas al expediente actual.
                </p>
                <div class="space-y-4">
                    <template x-for="req in videoRequests" :key="req.cdgo_solicitud_video">
                        <div class="border border-gray-200 rounded-xl p-4 bg-slate-50/50 space-y-3">
                            <div class="flex justify-between items-start border-b border-gray-100 pb-2">
                                <div>
                                    <h4 class="text-xs font-black text-gray-800 uppercase" x-text="req.de_nombre_usuario || 'Solicitante Anónimo'"></h4>
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider" x-text="(req.de_cargo ? req.de_cargo + ' - ' : '') + (req.de_institucion_uo || '')"></p>
                                </div>
                                <span class="px-2.5 py-1 text-[9px] font-black rounded-full uppercase tracking-wider bg-slate-50 text-slate-700 border border-slate-200" 
                                      x-text="'Doc: ' + (req.tipo_documento || 'DNI') + ' ' + (req.nu_documento || '')"></span>
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-xs">
                                <div>
                                    <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5">Correo</span>
                                    <span class="font-bold text-gray-700" x-text="req.de_correo_solicitante || '—'"></span>
                                </div>
                                <div>
                                    <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5">F. Solicitud</span>
                                    <span class="font-bold text-gray-700" x-text="req.fe_solicitud || '—'"></span>
                                </div>
                                <div>
                                    <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5 text-red-500">Rango de Grabación</span>
                                    <span class="font-bold text-gray-700 block" x-text="'Inicio: ' + (req.fe_inicio_grabacion || '—')"></span>
                                    <span class="font-bold text-gray-700 block" x-text="'Fin: ' + (req.fe_fin_grabacion || '—')"></span>
                                </div>
                                <div>
                                    <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5">F. Entrega</span>
                                    <span class="font-bold text-gray-700" x-text="req.fe_entrega || 'Pendiente de entrega'"></span>
                                </div>
                            </div>
                            <div class="text-xs pt-1 border-t border-gray-100">
                                <span class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5">Observaciones de la Solicitud</span>
                                <p class="text-gray-600 italic" x-text="req.de_observaciones || 'Sin observaciones'"></p>
                            </div>
                        </div>
                    </template>
                    <div x-show="videoRequests.length === 0" class="text-center py-8 text-gray-400 italic text-xs">
                        No hay solicitudes de video registradas para este expediente.
                    </div>
                </div>
            </div>
            <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end">
                <button type="button" @click="modalVideoRequests = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cerrar</button>
            </footer>
        </div>
    </div>

    <!-- MODAL DETALLE -->
    <div x-show="modalDetalle" x-transition x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-2xl overflow-hidden" @click.outside="modalDetalle = false">
            <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-800">Detalle del Expediente <span class="text-slate-700" x-text="detalleData?.expediente"></span></h3>
                <button @click="modalDetalle = false" class="hover:opacity-80"><i class="fas fa-times"></i></button>
            </header>
            <div class="p-6 text-xs text-gray-600 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <template x-if="detalleData">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="md:col-span-2">
                                <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-1">Solicitante</strong>
                                <span class="font-bold text-gray-800" x-text="detalleData.solicitante"></span>
                            </div>
                            <div>
                                <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-1">Doc. Identidad</strong>
                                <span x-text="detalleData.documento_id || '—'"></span>
                            </div>
                            <div>
                                <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-1">Tipo</strong>
                                <span x-text="detalleData.tipo_documento"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-1">Domicilio Fiscal</strong>
                                <span x-text="detalleData.domicilio || '—'"></span>
                            </div>
                            <div>
                                <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-1">Contacto</strong>
                                <div>Tel: <span x-text="detalleData.telefono || '—'"></span></div>
                                <div>Email: <span x-text="detalleData.correo || '—'"></span></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 border-t border-gray-100 pt-4">
                            <div class="md:col-span-2">
                                <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-1">Servicio</strong>
                                <span class="font-bold text-gray-800" x-text="detalleData.servicio"></span>
                            </div>
                            <div>
                                <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-1">Silencio Admin.</strong>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-50 text-slate-700" x-text="detalleData.silencio_admin"></span>
                            </div>
                            <div>
                                <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-1">Folios</strong>
                                <span x-text="detalleData.folios"></span>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-4">
                            <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-1">Observaciones Generales</strong>
                            <p class="text-gray-700 italic" x-text="detalleData.observaciones || 'Sin observaciones'"></p>
                        </div>

                        <template x-if="detalleData.requisitos && detalleData.requisitos.length > 0">
                            <div class="border-t border-gray-100 pt-4">
                                <strong class="block text-[9px] text-gray-400 uppercase tracking-widest mb-2">Requisitos</strong>
                                <ul class="space-y-1">
                                    <template x-for="req in detalleData.requisitos">
                                        <li class="flex items-start gap-2">
                                            <i class="fas mt-0.5" :class="req.cumplido === '1' ? 'fa-check-circle text-emerald-500' : 'fa-times-circle text-rose-500'"></i>
                                            <span x-text="req.nombre"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!detalleData">
                    <div class="flex justify-center items-center py-8 text-gray-400">
                        <i class="fas fa-spinner fa-spin text-2xl"></i>
                    </div>
                </template>
            </div>
            <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end">
                <button type="button" @click="modalDetalle = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cerrar</button>
            </footer>
        </div>
    </div>

    <script>
        function correoExternoApp() {
            return {
                loading: false,
                activeBandeja: @json($bandejaInicial ?? 'entrada'),
                selectedIds: [],
                selectedExpediente: null,
                showToast: false,
                toastMessage: '',
                currentPage: 1,
                lastPage: 1,
                page: 1,
                perPage: 10,
                
                estado: '0',
                estados: @json($estadosList ?? []),
                criterio: 'expe_todo',
                descripcion: '',
                desde: '',
                hasta: '',
                detalleData: null,

                areas: @json($areasList),
                cargos: @json($cargosList),
                tiposDocumento: @json($tiposDocumento),
                
                modalRecepcionar: false,
                modalDerivar: false,
                modalAsignar: false,
                modalEnviar: false,
                modalFinalizar: false,
                modalSilencio: false,
                silencioData: null,
                modalVideoRequests: false,
                modalDetalle: false,
                videoRequests: [],
                
                recepcionObs: 'SIN NOVEDADES PARA RECEPCIONAR',
                recepcionFecha: '{{ date("Y-m-d") }}T{{ date("H:i") }}',
                
                derivarGerencia: '0',
                derivarSubgerencia: '0',
                derivarFecha: '{{ date("Y-m-d") }}T{{ date("H:i") }}',
                derivarConTramite: false,
                derivarDocTipo: '0',
                derivarTramiteNro: '',
                derivarObs: 'SIN NOVEDADES PARA ENVIAR',
                derivarFolios: 1,
                
                asignarCargo: '0',
                asignarFecha: '{{ date("Y-m-d") }}T{{ date("H:i") }}',
                asignarConTramite: false,
                asignarDocTipo: '0',
                asignarTramiteNro: '',
                asignarObs: 'SIN NOVEDADES PARA ASIGNAR',
                asignarFolios: 1,
                
                enviarAreas: [],
                enviarFecha: '{{ date("Y-m-d") }}T{{ date("H:i") }}',
                enviarConTramite: false,
                enviarDocTipo: '0',
                enviarTramiteNro: '',
                enviarObs: 'SIN NOVEDADES PARA ENVIAR',
                enviarFolios: 1,
                
                finalizarFecha: '{{ date("Y-m-d") }}T{{ date("H:i") }}',
                finalizarObs: 'TRÁMITE FINALIZADO',
                finalizarEnviarCorreo: '1',
                finalizarConTramite: false,
                finalizarDocTipo: '0',
                finalizarTramiteNro: '',
                finalizarFolios: 1,

                // Datos reales
                items: [],
                history: [],

                triggerToast(msg) {
                    this.toastMessage = msg;
                    this.showToast = true;
                    setTimeout(() => { this.showToast = false; }, 3000);
                },

                toggleSelectAll() {
                    if (this.selectedIds.length === this.items.length) {
                        this.selectedIds = [];
                    } else {
                        this.selectedIds = this.items.map(i => i.cdgo_dto_trmte);
                    }
                },

                init() {
                    this.fetchItems();
                    this.$watch('activeBandeja', value => {
                        this.fetchItems();
                    });
                },

                fetchItems() {
                    this.loading = true;
                    this.selectedIds = [];
                    this.selectedExpediente = null;
                    fetch('{{ route("mod_gm.segi_expedi.tabla", [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            bandeja: this.activeBandeja,
                            criterio: this.criterio,
                            descripcion: this.descripcion,
                            estado: this.estado,
                            desde: this.desde,
                            hasta: this.hasta,
                            page: this.page
                        })
                    })
                    .then(response => response.json())
                    .then(res => {
                        this.loading = false;
                        if (res.status === 'success') {
                            this.items = res.data;
                            if (res.pagination) {
                                this.page = res.pagination.current_page;
                                this.lastPage = res.pagination.last_page;
                            }
                        } else {
                            this.triggerToast('Error: ' + res.message);
                        }
                    })
                    .catch(err => {
                        this.loading = false;
                        console.error(err);
                        this.triggerToast('Error de conexión con el servidor');
                    });
                },

                cleanFilters() {
                    this.criterio = 'expe_todo';
                    this.descripcion = '';
                    this.estado = '0';
                    this.desde = '';
                    this.hasta = '';
                    this.page = 1;
                    this.fetchItems();
                },

                highlightMatch(text) {
                    if (!text) return '';
                    if (!this.descripcion) return text;
                    const regex = new RegExp(`(${this.descripcion})`, 'gi');
                    return text.replace(regex, '<mark class="bg-yellow-200 text-yellow-900 rounded px-1">$1</mark>');
                },

                prevPage() {
                    if (this.page > 1) {
                        this.page--;
                        this.fetchItems();
                    }
                },

                nextPage() {
                    if (this.page < this.lastPage) {
                        this.page++;
                        this.fetchItems();
                    }
                },

                showDetail(item) {
                    this.selectedExpediente = item;
                    this.history = [];
                    fetch('{{ route("mod_gm.segi_expedi.historial", [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ id: item.cdgo_dto_trmte })
                    })
                    .then(response => response.json())
                    .then(res => {
                        if (res.status === 'success') {
                            this.history = res.data;
                        } else {
                            this.triggerToast('Error: ' + res.message);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        this.triggerToast('Error de conexión al cargar historial');
                    });
                },

                showDetalle(item) {
                    this.selectedExpediente = item;
                    this.detalleData = null;
                    this.modalDetalle = true;
                    fetch('{{ route("mod_gm.segi_expedi.detalle", [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ id: item.cdgo_dto_trmte })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.status === 'success') {
                            this.detalleData = res.data;
                        } else {
                            this.triggerToast('Error: ' + res.message);
                            this.modalDetalle = false;
                        }
                    })
                    .catch(err => {
                        this.triggerToast('Error de conexión al obtener detalles');
                        this.modalDetalle = false;
                    });
                },

                fetchSemilla(modal) {
                    let docTipo = '0';
                    if (modal === 'derivar') docTipo = this.derivarDocTipo;
                    if (modal === 'asignar') docTipo = this.asignarDocTipo;
                    if (modal === 'enviar') docTipo = this.enviarDocTipo;
                    if (modal === 'finalizar') docTipo = this.finalizarDocTipo;

                    if (docTipo === '0') return;

                    fetch('{{ route("mod_gm.segi_expedi.semilla", [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ doc_tipo: docTipo })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.status === 'success') {
                            if (modal === 'derivar') this.derivarTramiteNro = res.semilla;
                            if (modal === 'asignar') this.asignarTramiteNro = res.semilla;
                            if (modal === 'enviar') this.enviarTramiteNro = res.semilla;
                            if (modal === 'finalizar') this.finalizarTramiteNro = res.semilla;
                        }
                    });
                },

                showSilencio(item) {
                    if (item.nu_tiem_sile > 0) {
                        this.silencioData = item;
                        this.modalSilencio = true;
                    } else {
                        this.triggerToast('El trámite no tiene silencio administrativo configurado.');
                    }
                },

                getGerencias() {
                    return this.areas.filter(a => a.level === 0);
                },

                getSubgerencias(gerId) {
                    if (!gerId || gerId === '0') return [];
                    return this.areas.filter(a => a.cdgo_area_prim == gerId);
                },

                showVideoRequests(item) {
                    this.videoRequests = [];
                    this.modalVideoRequests = true;
                    fetch('{{ route("mod_gm.segi_expedi.solicitud_video", [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ id: item.cdgo_dto_trmte })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.status === 'success') {
                            this.videoRequests = res.data;
                        } else {
                            this.triggerToast('Error: ' + res.message);
                        }
                    });
                },

                submitRecepcionar() {
                    this.loading = true;
                    fetch('{{ route("mod_gm.segi_expedi.recepcionar", [], false) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            ids: this.selectedIds,
                            fecha: this.recepcionFecha,
                            observaciones: this.recepcionObs
                        })
                    })
                    .then(r => r.json())
                    .then(res => {
                        this.loading = false;
                        if (res.status === 'success') {
                            this.modalRecepcionar = false;
                            this.triggerToast(res.message);
                            this.fetchItems();
                        } else {
                            this.triggerToast('Error: ' + res.message);
                        }
                    })
                    .catch(err => {
                        this.loading = false;
                        this.triggerToast('Error de conexión');
                    });
                },

                submitDerivar(formEl) {
                    this.loading = true;
                    let fd = new FormData(formEl);
                    this.selectedIds.forEach(id => fd.append('ids[]', id));
                    fd.append('fecha', this.derivarFecha);
                    fd.append('observaciones', this.derivarObs);
                    fd.append('gerencia', this.derivarGerencia);
                    fd.append('subgerencia', this.derivarSubgerencia);
                    fd.append('enviar_con_tramite', this.derivarConTramite ? '1' : '0');
                    fd.append('doc_tipo', this.derivarDocTipo);
                    fd.append('tramite_nro', this.derivarTramiteNro);
                    fd.append('folios', this.derivarFolios);

                    fetch('{{ route("mod_gm.segi_expedi.derivar", [], false) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        this.loading = false;
                        if (res.status === 'success') {
                            this.modalDerivar = false;
                            this.triggerToast(res.message);
                            this.fetchItems();
                        } else {
                            this.triggerToast('Error: ' + res.message);
                        }
                    })
                    .catch(err => {
                        this.loading = false;
                        this.triggerToast('Error de conexión');
                    });
                },

                submitAsignar(formEl) {
                    this.loading = true;
                    let fd = new FormData(formEl);
                    this.selectedIds.forEach(id => fd.append('ids[]', id));
                    fd.append('fecha', this.asignarFecha);
                    fd.append('observaciones', this.asignarObs);
                    fd.append('cargo', this.asignarCargo);
                    fd.append('enviar_con_tramite', this.asignarConTramite ? '1' : '0');
                    fd.append('doc_tipo', this.asignarDocTipo);
                    fd.append('tramite_nro', this.asignarTramiteNro);
                    fd.append('folios', this.asignarFolios);

                    fetch('{{ route("mod_gm.segi_expedi.asignar", [], false) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        this.loading = false;
                        if (res.status === 'success') {
                            this.modalAsignar = false;
                            this.triggerToast(res.message);
                            this.fetchItems();
                        } else {
                            this.triggerToast('Error: ' + res.message);
                        }
                    })
                    .catch(err => {
                        this.loading = false;
                        this.triggerToast('Error de conexión');
                    });
                },

                submitEnviar(formEl) {
                    this.loading = true;
                    let fd = new FormData(formEl);
                    this.selectedIds.forEach(id => fd.append('ids[]', id));
                    fd.append('fecha', this.enviarFecha);
                    fd.append('observaciones', this.enviarObs);
                    this.enviarAreas.forEach(a => fd.append('areas[]', a));
                    fd.append('enviar_con_tramite', this.enviarConTramite ? '1' : '0');
                    fd.append('doc_tipo', this.enviarDocTipo);
                    fd.append('tramite_nro', this.enviarTramiteNro);
                    fd.append('folios', this.enviarFolios);

                    fetch('{{ route("mod_gm.segi_expedi.enviar", [], false) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        this.loading = false;
                        if (res.status === 'success') {
                            this.modalEnviar = false;
                            this.triggerToast(res.message);
                            this.fetchItems();
                        } else {
                            this.triggerToast('Error: ' + res.message);
                        }
                    })
                    .catch(err => {
                        this.loading = false;
                        this.triggerToast('Error de conexión');
                    });
                },

                submitFinalizar(formEl) {
                    this.loading = true;
                    let fd = new FormData(formEl);
                    this.selectedIds.forEach(id => fd.append('ids[]', id));
                    fd.append('fecha', this.finalizarFecha);
                    fd.append('observaciones', this.finalizarObs);
                    fd.append('enviar_con_tramite', this.finalizarConTramite ? '1' : '0');
                    fd.append('doc_tipo', this.finalizarDocTipo);
                    fd.append('tramite_nro', this.finalizarTramiteNro);
                    fd.append('enviar_correo', this.finalizarEnviarCorreo);
                    fd.append('folios', this.finalizarFolios);

                    fetch('{{ route("mod_gm.segi_expedi.finalizar", [], false) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        this.loading = false;
                        if (res.status === 'success') {
                            this.modalFinalizar = false;
                            this.triggerToast(res.message);
                            this.fetchItems();
                        } else {
                            this.triggerToast('Error: ' + res.message);
                        }
                    })
                    .catch(err => {
                        this.loading = false;
                        this.triggerToast('Error de conexión');
                    });
                }
            };
        }
    </script>

</body>
</html>
