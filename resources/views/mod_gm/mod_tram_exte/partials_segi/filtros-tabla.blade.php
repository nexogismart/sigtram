{{-- =========================================================================
     TARJETA 1: FILTROS Y TABLA PRINCIPAL — TRÁMITE EXTERNO (RF-05 / RF-07)
     ========================================================================= --}}
<div class="sigtram-bandeja-card bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col flex-1 min-h-0">
    {{-- Título de la bandeja --}}
    <div class="sigtram-card-header bg-slate-800 text-white px-4 py-2 flex items-center justify-between gap-3 shrink-0">
        <h2 class="text-sm sm:text-base font-bold uppercase tracking-wide">
            Bandeja Trámite Externo —
            <span x-text="{ todos:'Todos', entrada:'Entrada', recibidos:'Recibidos', salida:'Salida', finalizados:'Finalizados / Notificados', finalizados_notificados:'Finalizados / Notificados', archivo:'Archivo', sin_finalizar:'Sin Finalizar' }[activeBandeja] || activeBandeja"></span>
        </h2>
    </div>

    {{-- 1. Buscador compacto (Búsqueda por) --}}
    <div class="sigtram-filtros-panel border-b border-gray-100 px-3 py-2 bg-gray-50/70 shrink-0">
        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Búsqueda por</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-2 items-end">
            <div class="lg:col-span-3">
                <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Criterio</label>
                <select x-model="criterio" @change="page=1; fetchItems()" class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    <option value="expe_todo">Nº de Expediente</option>
                    <option value="expe_varios">Nº de Expediente Varios</option>
                    <option value="solicitante">Nombre/Razón Social</option>
                    <option value="observaciones">Observaciones</option>
                </select>
            </div>
            <div class="lg:col-span-3">
                <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Descripción</label>
                <input type="text" x-model="descripcion" @input.debounce.500ms="page=1; fetchItems()" placeholder="Texto a buscar..." class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Desde</label>
                <input type="date" x-model="desde" @change="page=1; fetchItems()" class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-[9px] font-bold text-gray-500 mb-0.5">Hasta</label>
                <input type="date" x-model="hasta" @change="page=1; fetchItems()" class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
            </div>
            <div class="sigtram-filter-actions lg:col-span-2 flex gap-2">
                <button @click="page=1; fetchItems()" class="sigtram-consult-button w-full py-1.5 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all flex items-center justify-center gap-1.5">
                    <i class="fas fa-search" aria-hidden="true"></i> <span>Consultar</span>
                </button>
                <button @click="cleanFilters()" type="button" title="Limpiar filtros" aria-label="Limpiar filtros" class="sigtram-clear-button py-1.5 bg-gray-500 hover:bg-gray-600 text-white rounded-lg shadow-sm transition-all flex items-center justify-center">
                    <i class="fas fa-eraser" aria-hidden="true"></i><span class="sr-only">Limpiar filtros</span>
                </button>
            </div>
        </div>
    </div>

    {{-- 2. Barra de acciones dinámicas por bandeja (debajo de búsqueda, encima de tabla) --}}
    <div id="barra-acciones-externo" class="sigtram-actions-bar px-3 py-1.5 border-b border-gray-100 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-700 bg-white shrink-0">
        {{-- Entrada --}}
        <template x-if="activeBandeja === 'entrada'">
            <div class="flex items-center gap-2">
                <button type="button" @click="modalRecepcionar = true" :disabled="selectedIds.length === 0"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 text-white hover:bg-slate-700 disabled:opacity-40 text-xs font-bold transition-all shadow-sm">
                    <i class="fas fa-check-double text-emerald-400"></i> Recepcionar
                </button>
                <span class="text-gray-300">|</span>
                <form action="{{ route('mod_gm.segi_expedi.imprimir_bandeja') }}" method="POST" target="_blank" class="inline-flex items-center">
                    @csrf
                    <input type="hidden" name="bandeja" :value="activeBandeja">
                    <input type="hidden" name="criterio" :value="criterio">
                    <input type="hidden" name="descripcion" :value="descripcion">
                    <input type="hidden" name="desde" :value="desde">
                    <input type="hidden" name="hasta" :value="hasta">
                    <button type="submit" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </form>
            </div>
        </template>

        {{-- Recibidos --}}
        <template x-if="activeBandeja === 'recibidos'">
            <div class="flex items-center gap-2">
                <button type="button" @click="modalDerivar = true" :disabled="selectedIds.length === 0"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-700 hover:text-blue-700 hover:bg-blue-50 text-xs font-bold transition-colors disabled:opacity-40">
                    <i class="fas fa-share text-blue-600"></i> Derivar
                </button>
                <span class="text-gray-300">|</span>
                <button type="button" @click="modalAsignar = true" :disabled="selectedIds.length === 0"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-700 hover:text-indigo-700 hover:bg-indigo-50 text-xs font-bold transition-colors disabled:opacity-40">
                    <i class="fas fa-user-tag text-indigo-600"></i> Asignar
                </button>
                <span class="text-gray-300">|</span>
                <button type="button" @click="modalEnviar = true" :disabled="selectedIds.length === 0"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-700 hover:text-emerald-700 hover:bg-emerald-50 text-xs font-bold transition-colors disabled:opacity-40">
                    <i class="fas fa-layer-group text-emerald-600"></i> Enviar
                </button>
                <span class="text-gray-300">|</span>
                <button type="button" @click="modalFinalizar = true" :disabled="selectedIds.length === 0"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-700 hover:text-rose-700 hover:bg-rose-50 text-xs font-bold transition-colors disabled:opacity-40">
                    <i class="fas fa-check-circle text-rose-600"></i> Finalizar
                </button>
                <span class="text-gray-300">|</span>
                <form action="{{ route('mod_gm.segi_expedi.imprimir_bandeja') }}" method="POST" target="_blank" class="inline-flex items-center">
                    @csrf
                    <input type="hidden" name="bandeja" :value="activeBandeja">
                    <input type="hidden" name="criterio" :value="criterio">
                    <input type="hidden" name="descripcion" :value="descripcion">
                    <input type="hidden" name="desde" :value="desde">
                    <input type="hidden" name="hasta" :value="hasta">
                    <button type="submit" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </form>
            </div>
        </template>

        {{-- Salida --}}
        <template x-if="activeBandeja === 'salida'">
            <div class="flex items-center gap-2">
                <button type="button" @click="abrirModalDevolver()" :disabled="selectedIds.length !== 1"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-700 hover:text-amber-700 hover:bg-amber-50 text-xs font-bold transition-colors disabled:opacity-40">
                    <i class="fas fa-undo text-amber-600"></i> Devolver
                </button>
                <span class="text-gray-300">|</span>
                <button type="button" @click="abrirModalRedirigir()" :disabled="selectedIds.length !== 1"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-700 hover:text-sky-700 hover:bg-sky-50 text-xs font-bold transition-colors disabled:opacity-40">
                    <i class="fas fa-arrows-split-up-and-left text-sky-600"></i> Redirigir / Anular
                </button>
                <span class="text-gray-300">|</span>
                <form action="{{ route('mod_gm.segi_expedi.imprimir_bandeja') }}" method="POST" target="_blank" class="inline-flex items-center">
                    @csrf
                    <input type="hidden" name="bandeja" :value="activeBandeja">
                    <input type="hidden" name="criterio" :value="criterio">
                    <input type="hidden" name="descripcion" :value="descripcion">
                    <input type="hidden" name="desde" :value="desde">
                    <input type="hidden" name="hasta" :value="hasta">
                    <button type="submit" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </form>
            </div>
        </template>

        {{-- Archivo / Finalizados / Notificados --}}
        <template x-if="activeBandeja === 'archivo' || activeBandeja === 'finalizados'">
            <div class="flex items-center gap-2">
                <button type="button" @click="modalArchivar = true" :disabled="selectedIds.length === 0"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-700 hover:text-slate-900 hover:bg-slate-100 text-xs font-bold transition-colors disabled:opacity-40">
                    <i class="fas fa-box-archive text-slate-600"></i> Archivar
                </button>
                <span class="text-gray-300">|</span>
                <button type="button" @click="abrirModalPrestar()" :disabled="selectedIds.length !== 1"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-700 hover:text-emerald-700 hover:bg-emerald-50 text-xs font-bold transition-colors disabled:opacity-40">
                    <i class="fas fa-hand-holding-hand text-emerald-600"></i> Prestar
                </button>
                <template x-if="activeBandeja === 'archivo'">
                    <div class="inline-flex items-center gap-2">
                        <span class="text-gray-300">|</span>
                        <button type="button" @click="abrirModalRetorno()" :disabled="selectedIds.length !== 1"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-700 hover:text-emerald-700 hover:bg-emerald-50 text-xs font-bold transition-colors disabled:opacity-40">
                            <i class="fas fa-undo text-emerald-600"></i> Retornar Préstamo
                        </button>
                    </div>
                </template>
                <span class="text-gray-300">|</span>
                <form action="{{ route('mod_gm.segi_expedi.imprimir_bandeja') }}" method="POST" target="_blank" class="inline-flex items-center">
                    @csrf
                    <input type="hidden" name="bandeja" :value="activeBandeja">
                    <input type="hidden" name="criterio" :value="criterio">
                    <input type="hidden" name="descripcion" :value="descripcion">
                    <input type="hidden" name="desde" :value="desde">
                    <input type="hidden" name="hasta" :value="hasta">
                    <button type="submit" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </form>
            </div>
        </template>

        {{-- Todos / Sin Finalizar --}}
        <template x-if="activeBandeja === 'todos' || activeBandeja === 'sin_finalizar'">
            <form action="{{ route('mod_gm.segi_expedi.imprimir_bandeja') }}" method="POST" target="_blank" class="inline-flex items-center">
                @csrf
                <input type="hidden" name="bandeja" :value="activeBandeja">
                <input type="hidden" name="criterio" :value="criterio">
                <input type="hidden" name="descripcion" :value="descripcion">
                <input type="hidden" name="desde" :value="desde">
                <input type="hidden" name="hasta" :value="hasta">
                <button type="submit" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </form>
        </template>

        {{-- Exportar Excel a la derecha si aplica --}}
        @if(!empty($puedeExportarExcel))
        <template x-if="puedeExportarExcel">
            <div class="ml-auto flex items-center">
                <button type="button"
                        @click="exportarExcel()"
                        :disabled="exportandoExcel"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-emerald-700 hover:text-emerald-900 hover:bg-emerald-50 text-xs font-bold transition-colors disabled:opacity-50"
                        title="Exportar bandeja actual a Excel (XLSX)">
                    <template x-if="!exportandoExcel">
                        <i class="fas fa-file-excel text-emerald-600"></i>
                    </template>
                    <template x-if="exportandoExcel">
                        <i class="fas fa-spinner fa-spin text-emerald-600"></i>
                    </template>
                    <span x-text="exportandoExcel ? 'Exportando...' : 'Exportar Excel'"></span>
                </button>
            </div>
        </template>
        @endif
    </div>

    {{-- 3. Tabla Principal de Expedientes (Filas compactas de 1 línea con cebra y check) --}}
    <div class="sigtram-table-scroll overflow-auto custom-scrollbar flex-1 min-h-0">
        <table class="w-full text-left border-collapse min-w-[1080px]">
            <thead class="bg-slate-50 sticky top-0 z-10 shadow-xs">
                <tr class="text-[10px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-200 bg-slate-50">
                    <th class="px-3 py-2 text-center w-10 shrink-0">
                        <input type="checkbox" @click="toggleSelectAll()" :checked="items.length > 0 && selectedIds.length === items.length" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                    </th>
                    <th class="px-3 py-2 w-10 text-center shrink-0">Orig.</th>
                    <th class="px-3 py-2 whitespace-nowrap min-w-[150px]">Nº Exp.</th>
                    <th class="px-3 py-2 min-w-[300px] max-w-[420px]">Nombre o Razón Social</th>
                    <th class="px-3 py-2 whitespace-nowrap min-w-[150px]">F. Entrada</th>
                    <th class="px-3 py-2 whitespace-nowrap min-w-[110px]">Estado</th>
                    <th class="px-3 py-2 min-w-[220px] max-w-[320px]">Observaciones</th>
                    <th class="px-3 py-2 text-center whitespace-nowrap min-w-[80px]">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(item, idx) in items" :key="item.movimiento_id || item.cdgo_trmte_dcmnto">
                    <tr class="border-b border-gray-100 text-xs transition-colors cursor-pointer select-none"
                        @click="showDetail(item)"
                        :class="selectedExpediente && ((selectedExpediente.movimiento_id || selectedExpediente.cdgo_trmte_dcmnto) === (item.movimiento_id || item.cdgo_trmte_dcmnto)) ? 'bg-amber-100/70 ring-1 ring-amber-300' : (idx % 2 === 1 ? 'bg-slate-50/70 hover:bg-amber-50/30' : 'bg-white hover:bg-amber-50/30')">
                        <td class="px-3 py-1.5 text-center w-10 shrink-0" @click.stop>
                            <input type="checkbox" :value="item.movimiento_id || item.cdgo_trmte_dcmnto" x-model="selectedIds" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                        </td>
                        <td class="px-3 py-1.5 text-center font-bold text-xs text-slate-700 w-10 shrink-0 cursor-help" :title="item.de_tpo_trmte || item.orig_doc" x-text="item.orig_doc"></td>
                        <td class="px-3 py-1.5 whitespace-nowrap min-w-[150px] font-bold text-xs text-slate-800" x-html="highlightMatch(item.nu_expe_todo)"></td>
                        <td class="px-3 py-1.5 text-xs text-slate-700 min-w-[300px] max-w-[420px]" :title="item.no_crto">
                            <span class="line-clamp-2 block break-words" x-html="highlightMatch(item.no_crto)"></span>
                        </td>
                        <td class="px-3 py-1.5 text-xs text-slate-600 whitespace-nowrap min-w-[150px]" x-text="item.fe_rcbdo_trmte"></td>
                        <td class="px-3 py-1.5 whitespace-nowrap min-w-[110px]">
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase tracking-wider inline-block"
                                  :class="getEstadoBadgeClass(item.cdgo_estdo_trmte, item.dc_estdo_trmte)"
                                  x-text="item.dc_estdo_trmte"></span>
                        </td>
                        <td class="px-3 py-1.5 text-xs text-slate-600 min-w-[220px] max-w-[320px]" :title="item.de_obse_trmte">
                            <span class="line-clamp-2 block break-words" x-html="highlightMatch(item.de_obse_trmte)"></span>
                        </td>
                        <td class="px-3 py-1.5 text-center whitespace-nowrap min-w-[80px]" @click.stop>
                            <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                <!-- 1. Hoja de Ruta -->
                                <button type="button"
                                        @click="window.open((routes.hojaRuta || '/mod_gm/mod_tram_exte/segi_expedi/hoja-ruta/') + (item.documento_id || item.cdgo_dto_trmte), '_blank', 'width=800,height=600')"
                                        class="p-1 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded transition-colors"
                                        title="Imprimir hoja de ruta"
                                        aria-label="Imprimir hoja de ruta">
                    <i class="fas fa-print text-xs"></i>
                                </button>

                                <!-- 2. Detalle del expediente con indicador integrado de Silencio Administrativo -->
                                <button type="button"
                                        @click="showDetalle(item)"
                                        class="p-1 rounded transition-colors relative"
                                        :class="item.dias_acumulados >= (item.nu_tiem_sile + item.nu_tiem_sile_adcnal) && (item.nu_tiem_sile > 0)
                                            ? 'text-rose-600 hover:bg-rose-50'
                                            : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100'"
                                        :title="item.dias_acumulados >= (item.nu_tiem_sile + item.nu_tiem_sile_adcnal) && (item.nu_tiem_sile > 0)
                                            ? 'Ver detalle del expediente — Plazo de silencio excedido (' + item.dias_acumulados + '/' + (item.nu_tiem_sile + item.nu_tiem_sile_adcnal) + ' días)'
                                            : 'Ver detalle del expediente'"
                                        aria-label="Ver detalle del expediente">
                                    <i class="fas text-xs" :class="item.dias_acumulados >= (item.nu_tiem_sile + item.nu_tiem_sile_adcnal) && (item.nu_tiem_sile > 0) ? 'fa-triangle-exclamation text-rose-500' : 'fa-eye'"></i>
                                    <template x-if="item.dias_acumulados >= (item.nu_tiem_sile + item.nu_tiem_sile_adcnal) && (item.nu_tiem_sile > 0)">
                                        <span class="absolute top-0 right-0 w-2 h-2 bg-rose-500 rounded-full"></span>
                                    </template>
                                </button>

                                <!-- Video Solicitud (condicional cuando exista) -->
                                <template x-if="item.has_video">
                                    <button type="button"
                                            @click="showVideoRequests(item)"
                                            class="p-1 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded transition-colors"
                                            title="Ver Solicitudes de Video"
                                            aria-label="Ver solicitudes de video">
                                        <i class="fas fa-video text-xs"></i>
                                    </button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </template>
                <template x-if="tableError && !loading">
                    <tr>
                        <td colspan="10" class="px-6 py-8 text-center bg-rose-50/50">
                            <div class="inline-flex flex-col items-center justify-center text-rose-600">
                                <i class="fas fa-triangle-exclamation text-2xl mb-2 text-rose-500"></i>
                                <p class="text-xs font-bold" x-text="tableError"></p>
                                <button type="button" @click="fetchItems()" class="mt-2 px-3 py-1 bg-white border border-rose-200 text-rose-700 hover:bg-rose-50 text-[10px] font-bold rounded-lg shadow-2xs">Reintentar</button>
                            </div>
                        </td>
                    </tr>
                </template>
                <template x-if="!tableError && !loading && items.length === 0">
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-folder-open text-3xl mb-2 text-slate-300 block"></i>
                            <p class="text-xs font-semibold">No se encontraron expedientes en esta bandeja</p>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- 4. Footer / Paginación homologado con Trámite Interno y botón para ocultar/ver histórico --}}
    <div class="sigtram-table-footer px-3 py-2 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-600 bg-slate-50/90 shrink-0">
        <div class="flex items-center gap-3">
            <button type="button" @click="toggleSelectAll()" class="font-bold text-slate-700 hover:underline">Marcar Todos</button>
            <span class="text-slate-300">|</span>
            <span class="font-medium text-slate-600" aria-live="polite" x-text="getRangeInfo()">Cargando registros...</span>
        </div>

        <div class="flex items-center gap-2">
            {{-- Botón para cerrar/ocultar el histórico y liberar espacio --}}
            <template x-if="selectedExpediente">
                <button type="button" @click="selectedExpediente = null"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold text-slate-700 hover:text-red-600 bg-white hover:bg-red-50 border border-slate-200 rounded-lg shadow-2xs transition-colors"
                        title="Ocultar histórico y expandir lista de expedientes">
                    <i class="fas fa-times text-[10px] text-red-500"></i>
                    <span>Ocultar Histórico</span>
                </button>
            </template>

            <label class="text-slate-500 font-semibold text-[10px] uppercase tracking-wider ml-1">Filas:</label>
            <select x-model.number="perPage" @change="page=1; fetchItems()" class="text-xs font-bold py-0.5 px-1.5 border border-slate-300 rounded-md bg-white text-slate-700 focus:ring-1 focus:ring-slate-500">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>

            <div class="inline-flex rounded-lg shadow-2xs border border-slate-200 bg-white ml-2" role="navigation" aria-label="Paginación de la bandeja">
                <button type="button" @click="firstPage()" :disabled="page <= 1" class="px-2 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none rounded-l-lg border-r border-slate-200" title="Primera página" aria-label="Primera página">
                    <i class="fas fa-angles-left text-[10px]"></i>
                </button>
                <button type="button" @click="prevPage()" :disabled="page <= 1" class="px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none border-r border-slate-200" title="Página anterior" aria-label="Página anterior">
                    <i class="fas fa-chevron-left text-[10px]"></i> Ant.
                </button>
                <span class="px-2.5 py-1 text-xs font-black text-slate-800 bg-slate-100/70 select-none" x-text="`${page} / ${lastPage}`">1 / 1</span>
                <button type="button" @click="nextPage()" :disabled="page >= lastPage" class="px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none border-l border-slate-200" title="Página siguiente" aria-label="Página siguiente">
                    Sig. <i class="fas fa-chevron-right text-[10px]"></i>
                </button>
                <button type="button" @click="lastPageNav()" :disabled="page >= lastPage" class="px-2 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none rounded-r-lg border-l border-slate-200" title="Última página" aria-label="Última página">
                    <i class="fas fa-angles-right text-[10px]"></i>
                </button>
            </div>
        </div>
    </div>
</div>
