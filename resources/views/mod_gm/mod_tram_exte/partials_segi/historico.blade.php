{{-- =========================================================================
     TARJETA 2: PANEL DE DETALLE / HISTÓRICO — TRÁMITE EXTERNO (RF-05 / RF-07)
     ========================================================================= --}}
<div x-show="selectedExpediente" x-transition x-cloak class="sigtram-history-card bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col flex-1 min-h-0 transition-all duration-300">
    <div class="sigtram-history-header bg-slate-50 px-4 py-2 border-b border-gray-200 flex items-center justify-between shrink-0 sticky top-0 z-20 shadow-xs">
        <div class="flex items-center gap-2.5 min-w-0">
            <i class="fas fa-file-lines text-slate-500 mr-0.5"></i>
            <span class="text-xs font-bold text-slate-700 whitespace-nowrap">Histórico de:</span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-black uppercase tracking-wide bg-amber-100/90 text-amber-950 border border-amber-300/80 truncate shadow-2xs"
                  x-text="getTituloHistorico()">
            </span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="selectedExpediente = null" class="text-gray-400 hover:text-red-500 text-xs transition-colors p-1 rounded" title="Cerrar / Ocultar histórico">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="overflow-auto overflow-x-auto custom-scrollbar flex-1 min-h-0">
        <table class="w-full text-left border-collapse">
            <thead class="sticky top-0 bg-slate-50 z-20 shadow-xs">
                <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-200 bg-slate-50">
                    <th class="px-4 py-3 bg-slate-50">Entrada</th>
                    <th class="px-4 py-3 text-center bg-slate-50">T.E.</th>
                    <th class="px-4 py-3 bg-slate-50">Área Salida</th>
                    <th class="px-4 py-3 bg-slate-50">Estado</th>
                    <th class="px-4 py-3 bg-slate-50">Área Envío</th>
                    <th class="px-4 py-3 bg-slate-50">Observaciones</th>
                    <th class="px-4 py-3 text-center bg-slate-50">U</th>
                    <th class="px-4 py-3 text-center bg-slate-50">A</th>
                </tr>
            </thead>
            <tbody>
                <template x-if="historyLoading">
                    <tr>
                        <td colspan="8" class="text-center py-8 text-xs text-slate-500 font-medium">
                            <i class="fas fa-spinner fa-spin mr-2 text-slate-600"></i> Cargando historial del expediente...
                        </td>
                    </tr>
                </template>
                <template x-if="!historyLoading && (!history || history.length === 0)">
                    <tr>
                        <td colspan="8" class="text-center py-8 text-xs text-gray-400 italic">
                            Sin movimientos históricos registrados para este expediente.
                        </td>
                    </tr>
                </template>
                <template x-for="hist in history" :key="hist.cdgo_trmte_dcmnto">
                    <tr class="border-b border-gray-100 text-xs hover:bg-slate-50/80 transition-colors">
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap" x-text="hist.fe_entrada"></td>
                        <td class="px-4 py-3 text-center font-bold text-slate-700 whitespace-nowrap" x-text="hist.dias"></td>
                        <td class="px-4 py-3 text-gray-600 font-medium" x-text="hist.area_origen || '—'"></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-[9px] font-bold"
                                  :class="getEstadoBadgeClass(hist.cdgo_estdo_trmte, hist.estado)"
                                  x-text="hist.estado"></span>
                        </td>
                        <td class="px-4 py-3 font-bold text-gray-800" x-text="hist.area"></td>
                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate" :title="hist.observaciones" x-text="hist.observaciones"></td>

                        <!-- Columna U (3%): Disparador U Canónico (SDD Spec 10) -->
                        <td class="px-4 py-3 text-center" @click.stop>
                            <button type="button"
                                    @click="openModalUsuarios(hist, $el)"
                                    class="px-2 py-0.5 rounded-md border border-slate-200 bg-white hover:bg-amber-50 hover:border-amber-300 text-slate-700 font-bold text-[11px] inline-flex items-center gap-1 transition-all shadow-2xs mx-auto"
                                    :title="'Ver ' + (hist.usuarios_resumen?.total ?? (hist.usuarios_destino?.length ?? 0)) + ' participantes del movimiento'"
                                    aria-haspopup="dialog"
                                    :aria-label="'Ver ' + (hist.usuarios_resumen?.total ?? (hist.usuarios_destino?.length ?? 0)) + ' participantes del movimiento'">
                                <span class="text-xs">👥</span> <span>U</span>
                                <span class="px-1 py-0.2 rounded-full bg-amber-100 text-amber-900 text-[10px] font-extrabold" x-text="hist.usuarios_resumen?.total ?? (hist.usuarios_destino?.length ?? 0)"></span>
                            </button>
                        </td>

                        <!-- Columna A: Adjunto con descarga privada + Disparador Modal Trazabilidad de Descargas -->
                        <td class="px-4 py-3 text-center">
                            <template x-if="hist.nom_archivo">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a :href="(routes.descargarAdjunto || '/mod_gm/mod_tram_exte/segi_expedi/descargar/') + hist.cdgo_trmte_dcmnto" target="_blank" rel="noopener noreferrer"
                                       class="p-1 text-slate-700 hover:text-slate-900 transition-colors" title="Descargar documento">
                                        <i class="fas fa-file-pdf text-rose-500 text-sm"></i>
                                    </a>
                                    <template x-if="hist.tiene_descargas">
                                        <button type="button"
                                                @click="abrirModalAuditoria(hist, $el)"
                                                class="text-gray-400 hover:text-slate-600 text-[10px] p-1 rounded hover:bg-slate-100 transition-colors focus:ring-1 focus:ring-slate-400"
                                                title="Ver trazabilidad de descargas"
                                                aria-label="Ver trazabilidad de descargas"
                                                aria-haspopup="dialog">
                                            <i class="fas fa-circle-info"></i>
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!hist.nom_archivo">
                                <span class="text-gray-300 text-xs">—</span>
                            </template>
                        </td>
                    </tr>
                </template>
                <template x-if="!historyLoading && history && history.length > 0">
                    <tr class="bg-amber-50/60 border-t border-amber-200">
                        <td colspan="7" class="text-right font-extrabold text-slate-800 py-2.5 pr-3 text-xs tracking-wide">
                            Descargar Todos =&gt;
                        </td>
                        <td class="text-center py-2.5" @click.stop>
                            <a :href="(routes.descargarTodos || '/mod_gm/mod_tram_exte/segi_expedi/descargar-todos/') + selectedExpediente?.cdgo_dto_trmte"
                               target="_blank"
                               class="inline-flex items-center justify-center p-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-800 transition-colors shadow-sm"
                               title="Descargar todos los adjuntos (ZIP con Manifiesto y PDF unificado)">
                                <i class="fas fa-file-zipper text-sm"></i>
                            </a>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
