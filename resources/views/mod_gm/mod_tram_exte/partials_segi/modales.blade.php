{{-- =========================================================================
     MODALES TRANSACCIONALES — TRÁMITE EXTERNO (RF-05 / RF-07)
     ========================================================================= --}}

<div id="modal-usuarios" x-show="modalUsuarios" x-cloak class="fixed inset-0 z-[320] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="modal-u-titulo" @keydown.escape.window="if (modalUsuarios) closeModalUsuarios()">
    <div class="fixed inset-0 bg-gray-955/70 backdrop-blur-sm" @click="closeModalUsuarios()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-4xl overflow-hidden flex flex-col max-h-[85vh] z-10 animate-in fade-in zoom-in-95 duration-150">
        <header class="bg-slate-900 text-white px-5 py-3.5 flex items-center justify-between border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-amber-400/20 text-amber-400 flex items-center justify-center text-sm ring-1 ring-amber-400/30">
                    <i class="fas fa-users-viewfinder"></i>
                </span>
                <div>
                    <h3 id="modal-u-titulo" class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">Participantes — Movimiento</h3>
                    <p class="text-[10px] text-slate-400 font-medium" x-text="(selectedMovimientoU?.estado || 'MOVIMIENTO') + ' • Entrada: ' + (selectedMovimientoU?.fe_entrada || '-')"></p>
                </div>
            </div>
            <button type="button" @click="closeModalUsuarios()" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-red-500 text-slate-300 hover:text-white flex items-center justify-center transition-colors" aria-label="Cerrar modal de participantes">
                <i class="fas fa-times text-xs"></i>
            </button>
        </header>
        <div class="p-5 grid grid-cols-1 md:grid-cols-12 gap-4 overflow-y-auto custom-scrollbar bg-slate-50/50 flex-1">
            <!-- Panel Izquierdo: EJECUTADO POR -->
            <div class="md:col-span-5 bg-white border border-slate-200 rounded-xl p-4 shadow-2xs flex flex-col justify-between space-y-3">
                <div>
                    <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                        <i class="fas fa-user-tag text-amber-500 text-xs"></i>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Ejecutado por</span>
                    </div>
                    <div class="mt-3 space-y-2.5">
                        <!-- 1. Nombre Completo -->
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Nombre Completo</span>
                            <h4 class="font-extrabold text-slate-900 text-xs sm:text-sm leading-snug"
                                x-text="uOrigenEjecutor?.nombre || selectedMovimientoU?.actor?.nombre || selectedMovimientoU?.usuario || 'Usuario no identificado'"></h4>
                        </div>
                        <!-- 2. Usuario / Login -->
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Usuario / Login</span>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded"
                                      x-text="uOrigenEjecutor?.login || selectedMovimientoU?.actor?.login || '—'"></span>
                                <template x-if="uOrigenEjecutor?.login_id || selectedMovimientoU?.actor?.login_id">
                                    <span class="text-[10px] text-slate-400 font-mono"
                                          x-text="'(ID: ' + (uOrigenEjecutor?.login_id || selectedMovimientoU?.actor?.login_id) + ')'"></span>
                                </template>
                            </div>
                        </div>
                        <!-- 3. Perfil -->
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Perfil</span>
                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold uppercase tracking-wide"
                                  x-text="uOrigenEjecutor?.perfil || selectedMovimientoU?.actor?.perfil || 'Perfil no registrado'"></span>
                        </div>
                        <!-- 4. Cargo Histórico -->
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Cargo Histórico</span>
                            <p class="text-xs text-slate-800 font-medium leading-tight mt-0.5"
                               x-text="uOrigenEjecutor?.cargo || selectedMovimientoU?.actor?.cargo || 'Cargo histórico no registrado'"></p>
                        </div>
                        <!-- 5. Área Histórica -->
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Área Histórica</span>
                            <span class="inline-block mt-0.5 px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-bold"
                                  x-text="uOrigenEjecutor?.area || selectedMovimientoU?.actor?.area || selectedMovimientoU?.area_origen || 'Área histórica no registrada'"></span>
                        </div>
                        <!-- 6. Fecha y Estado -->
                        <div class="pt-2 border-t border-slate-100">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Fecha / Estado</span>
                            <p class="text-[11px] text-slate-600 font-semibold"
                               x-text="(selectedMovimientoU?.fe_entrada || '-') + ' — ' + (selectedMovimientoU?.estado || 'REGISTRADO')"></p>
                        </div>
                    </div>
                </div>
                <div x-show="selectedMovimientoU?.recepcionado_por" class="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-[10px] text-emerald-900">
                    <span class="font-bold uppercase tracking-wider block">Recepcionado por:</span>
                    <span class="font-medium" x-text="typeof selectedMovimientoU?.recepcionado_por === 'string' ? selectedMovimientoU?.recepcionado_por : ((selectedMovimientoU?.recepcionado_por?.nombre || '') + ' (' + (selectedMovimientoU?.recepcionado_por?.fecha || '') + ')')"></span>
                </div>
            </div>

            <!-- Panel Derecho: DESTINO — actualmente asignados -->
            <div class="md:col-span-7 bg-white border border-slate-200 rounded-xl p-4 shadow-2xs flex flex-col">
                <div class="flex items-center justify-between gap-2 pb-2 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-users-gear text-emerald-500 text-xs"></i>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Destino — Actualmente asignados</span>
                    </div>
                    <span class="text-[9px] font-black px-2 py-0.5 rounded-full"
                          :class="uLoading ? 'bg-slate-100 text-slate-500' : (uError ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-800')"
                          x-text="uLoading ? 'Cargando...' : (uError ? 'No disponible' : ((uPagination.total ?? uData.length) + ' asignados'))"></span>
                </div>

                <!-- Buscador con debounce -->
                <div class="my-3 relative">
                    <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text"
                           x-model="uSearch"
                           @input.debounce.300ms="uPage = 1; fetchParticipantesU()"
                           x-ref="uSearchInput"
                           placeholder="Buscar por nombre, login o cargo..."
                           class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-slate-500 outline-none transition-all"
                           autocomplete="off">
                </div>

                <!-- Contenedor de lista con scroll y aria-live -->
                <div class="flex-1 min-h-[180px] max-h-[300px] overflow-y-auto custom-scrollbar relative" aria-live="polite">
                    <div x-show="uLoading" class="absolute inset-0 bg-white/80 backdrop-blur-xs flex items-center justify-center z-10">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-600">
                            <i class="fas fa-spinner fa-spin text-amber-500"></i> Cargando usuarios...
                        </div>
                    </div>
                    <div x-show="!uLoading && !uError && uData.length === 0" class="text-center py-8 text-slate-400">
                        <i class="fas fa-users-slash text-2xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-medium">Sin destinatarios actualmente asignados.</p>
                    </div>
                    <div x-show="!uLoading && uError" class="text-center py-6 text-rose-500">
                        <i class="fas fa-triangle-exclamation text-lg mb-1"></i>
                        <p class="text-xs font-semibold" x-text="uErrorMsg || 'Error al obtener participantes'"></p>
                        <button type="button" @click="fetchParticipantesU(uPage)" class="mt-3 px-3 py-1 bg-rose-100 hover:bg-rose-200 text-rose-700 font-bold rounded-lg text-xs transition-colors">
                            <i class="fas fa-rotate-right mr-1"></i> Reintentar
                        </button>
                    </div>
                    <div class="space-y-2" x-show="!uLoading && !uError && uData.length > 0">
                        <template x-for="(u, i) in uData" :key="u.login || i">
                            <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/70 hover:bg-emerald-50/40 transition-colors flex items-start gap-2.5">
                                <div class="w-6 h-6 rounded-md bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-[10px] shrink-0 mt-0.5" x-text="String(((uPagination.current_page - 1) * (uPagination.per_page || 10)) + i + 1).padStart(2, '0')"></div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-1">
                                        <h5 class="font-bold text-slate-900 text-xs leading-snug truncate" x-text="u.nombre || u.no_crto || '-'"></h5>
                                        <template x-if="u.login">
                                            <span class="font-mono text-[9px] bg-slate-200 text-slate-700 px-1 py-0.2 rounded font-bold shrink-0" x-text="u.login"></span>
                                        </template>
                                    </div>
                                    <p class="text-[10px] text-slate-500 font-medium leading-tight mt-0.5" x-text="(u.cargo || u.de_crgo || 'Sin cargo') + ' · ' + (u.area || u.de_area || 'Sin área')"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Controles de Paginación -->
                <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span class="text-[11px] font-medium" x-text="uError ? 'Página 1 de 1' : ('Página ' + (uPagination.current_page || 1) + ' de ' + (uPagination.last_page || 1))"></span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="changeUPage(-1)" :disabled="uError || (uPagination.current_page || 1) <= 1" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-bold text-[11px]">
                            <i class="fas fa-chevron-left text-[9px] mr-1"></i> Anterior
                        </button>
                        <button type="button" @click="changeUPage(1)" :disabled="uError || (uPagination.current_page || 1) >= (uPagination.last_page || 1)" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-bold text-[11px]">
                            Siguiente <i class="fas fa-chevron-right text-[9px] ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <footer class="px-5 py-3 bg-white border-t border-slate-100 flex justify-between items-center text-xs text-slate-400 shrink-0">
            <span class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold">
                    <i class="fas fa-circle-info text-[9px] text-amber-500"></i> Fuente: Asignación actual
                </span>
                <span class="hidden sm:inline">Presione <strong class="text-slate-600">[ESC]</strong> para cerrar</span>
            </span>
            <button type="button" @click="closeModalUsuarios()" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-xs transition-colors">
                Cerrar
            </button>
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
                    <div class="grid grid-cols-2 gap-2" style="display: none;">
                        <input type="file" name="fle_adjunto1" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        <input type="file" name="fle_adjunto2" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        <input type="file" name="fle_adjunto3" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        <input type="file" name="fle_adjunto4" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
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
                    <div class="grid grid-cols-2 gap-2" style="display: none;">
                        <input type="file" name="fle_adjunto1" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        <input type="file" name="fle_adjunto2" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
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
                    <div class="grid grid-cols-2 gap-2" style="display: none;">
                        <input type="file" name="fle_adjunto1" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        <input type="file" name="fle_adjunto2" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
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
                    <div class="grid grid-cols-2 gap-2" style="display: none;">
                        <input type="file" name="fle_adjunto1" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
                        <input type="file" name="fle_adjunto2" disabled class="w-full bg-gray-50 border border-gray-200 rounded-lg p-1.5 text-xs">
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
<div x-show="modalDetalle" x-transition x-cloak class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalDetalleTitle" @keydown.escape.window="modalDetalle = false">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-2xl overflow-hidden" @click.outside="modalDetalle = false">
        <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
            <h3 id="modalDetalleTitle" class="text-base font-bold text-white flex items-center gap-2">
                <i class="fas fa-file-lines text-amber-400"></i>
                <span>Detalle del Expediente</span>
                <span class="text-amber-400 font-extrabold" x-text="detalleData?.expediente"></span>
            </h3>
            <button type="button" @click="modalDetalle = false" class="hover:opacity-80 text-white" aria-label="Cerrar"><i class="fas fa-times"></i></button>
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

<!-- MODAL DEVOLVER / REGRESAR (AG-11 / TE-015) -->
<div x-show="modalDevolver" x-transition x-cloak class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalDevolverTitle" @keydown.escape.window="modalDevolver = false">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-xl overflow-hidden" @click.outside="modalDevolver = false">
        <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
            <h3 id="modalDevolverTitle" class="text-sm font-bold flex items-center gap-2">
                <i class="fas fa-reply text-amber-400"></i>
                Devolver / Regresar Expediente(s)
            </h3>
            <button type="button" @click="modalDevolver = false" class="hover:opacity-80" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </header>
        <div class="p-6 space-y-4">
            <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">
                Va a devolver <span class="text-slate-700" x-text="selectedIds.length"></span> expediente(s) seleccionado(s) a la oficina remitente.
            </p>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Fecha de Devolución</label>
                <input type="datetime-local" x-model="devolverFecha" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Motivo Obligatorio de Devolución *</label>
                <textarea x-model="devolverMotivo" rows="4" placeholder="Indique claramente la justificación técnica/administrativa del retorno..." class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
                <span x-show="!devolverMotivo || devolverMotivo.trim().length < 5" class="text-[10px] text-rose-500 font-bold">El motivo es obligatorio (mínimo 5 caracteres).</span>
            </div>
        </div>
        <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
            <button type="button" @click="modalDevolver = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="button" @click="submitDevolver()" :disabled="!devolverMotivo || devolverMotivo.trim().length < 5 || loading" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md transition-colors flex items-center gap-2">
                <i class="fas fa-reply"></i>
                <span>Devolver Expediente(s)</span>
            </button>
        </footer>
    </div>
</div>

<!-- MODAL ARCHIVAR (AG-12 / TE-016) -->
<div x-show="modalArchivar" x-transition x-cloak class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalArchivarTitle" @keydown.escape.window="modalArchivar = false">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-xl overflow-hidden" @click.outside="modalArchivar = false">
        <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
            <h3 id="modalArchivarTitle" class="text-sm font-bold flex items-center gap-2">
                <i class="fas fa-archive text-amber-400"></i>
                Archivar / Inventariar Expediente(s)
            </h3>
            <button type="button" @click="modalArchivar = false" class="hover:opacity-80" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </header>
        <div class="p-6 space-y-4">
            <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">
                Va a archivar <span class="text-slate-700" x-text="selectedIds.length"></span> expediente(s) seleccionado(s).
            </p>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Ubicación Física / Archivador / Caja *</label>
                <input type="text" x-model="archivarUbicacion" placeholder="Ej: ESTANTE-04 / CAJA-12 / ARCHIVADOR-A" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones de Archivo</label>
                <textarea x-model="archivarObs" rows="3" placeholder="Observaciones adicionales de inventario..." class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
            </div>
        </div>
        <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
            <button type="button" @click="modalArchivar = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="button" @click="submitArchivar()" :disabled="!archivarUbicacion || archivarUbicacion.trim().length < 2 || loading" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md transition-colors flex items-center gap-2">
                <i class="fas fa-box-archive"></i>
                <span>Confirmar Archivamiento</span>
            </button>
        </footer>
    </div>
</div>

<!-- MODAL PRESTAR (AG-12 / TE-025) -->
<div x-show="modalPrestar" x-transition x-cloak class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalPrestarTitle" @keydown.escape.window="modalPrestar = false">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-xl overflow-hidden" @click.outside="modalPrestar = false">
        <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
            <h3 id="modalPrestarTitle" class="text-sm font-bold flex items-center gap-2">
                <i class="fas fa-hand-holding text-emerald-400"></i>
                Préstamo de Expediente Físico
            </h3>
            <button type="button" @click="modalPrestar = false" class="hover:opacity-80" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </header>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Prestatario / Funcionario Solicitante *</label>
                <input type="text" x-model="prestarPrestatario" placeholder="Nombre completo del solicitante o funcionario..." class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Finalidad / Motivo del Préstamo *</label>
                <textarea x-model="prestarFinalidad" rows="2" placeholder="Motivo o diligencia que requiere el préstamo..." class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Fecha de Salida *</label>
                    <input type="datetime-local" x-model="prestarSalida" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Fecha Prevista de Retorno</label>
                    <input type="datetime-local" x-model="prestarRetorno" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                </div>
            </div>
        </div>
        <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
            <button type="button" @click="modalPrestar = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="button" @click="submitPrestar()" :disabled="!prestarPrestatario || !prestarFinalidad || loading" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md transition-colors flex items-center gap-2">
                <i class="fas fa-hand-holding-hand"></i>
                <span>Registrar Préstamo</span>
            </button>
        </footer>
    </div>
</div>

<!-- MODAL RETORNAR PRÉSTAMO (AG-12 / TE-025) -->
<div x-show="modalRetornarPrestamo" x-transition x-cloak class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalRetornoTitle" @keydown.escape.window="modalRetornarPrestamo = false">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-xl overflow-hidden" @click.outside="modalRetornarPrestamo = false">
        <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
            <h3 id="modalRetornoTitle" class="text-sm font-bold flex items-center gap-2">
                <i class="fas fa-undo text-emerald-400"></i>
                Confirmar Retorno de Préstamo
            </h3>
            <button type="button" @click="modalRetornarPrestamo = false" class="hover:opacity-80" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </header>
        <div class="p-6 space-y-4">
            <p class="text-xs text-gray-600 font-bold">
                Va a confirmar la devolución física del expediente prestado a archivo.
            </p>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Observaciones de Recepción / Estado Físico</label>
                <textarea x-model="retornoObs" rows="3" placeholder="Detalle sobre el estado de los folios recibidos..." class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
            </div>
        </div>
        <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
            <button type="button" @click="modalRetornarPrestamo = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="button" @click="submitRetornarPrestamo()" :disabled="loading" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md transition-colors flex items-center gap-2">
                <i class="fas fa-check"></i>
                <span>Confirmar Retorno</span>
            </button>
        </footer>
    </div>
</div>

<!-- MODAL REDIRIGIR (AG-13 / TE-044) -->
<div x-show="modalRedirigir" x-transition x-cloak class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalRedirigirTitle" @keydown.escape.window="modalRedirigir = false">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-xl overflow-hidden" @click.outside="modalRedirigir = false">
        <header class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
            <h3 id="modalRedirigirTitle" class="text-sm font-bold flex items-center gap-2">
                <i class="fas fa-arrows-split-up-and-left text-sky-400"></i>
                Redirigir / Corregir Destino
            </h3>
            <button type="button" @click="modalRedirigir = false" class="hover:opacity-80" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </header>
        <div class="p-6 space-y-4">
            <p class="text-xs text-gray-600 font-bold">
                Permite redirigir administrativamente el expediente hacia el cargo u oficina competente, registrando un nuevo movimiento histórico inmutable.
            </p>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Nuevo Cargo Destino *</label>
                <select x-model="redirigirCargoId" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2.5 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
                    <option value="0">-- Seleccione cargo de destino --</option>
                    <template x-for="c in cargos" :key="c.cdgo_crgo">
                        <option :value="c.cdgo_crgo" x-text="c.de_crgo + ' (' + c.no_crto + ')'"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Motivo de Redirección *</label>
                <textarea x-model="redirigirMotivo" rows="3" placeholder="Indique la justificación para reasignar el destino..." class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500"></textarea>
            </div>
        </div>
        <footer class="bg-slate-50 border-t border-gray-100 px-6 py-4 flex items-center justify-end gap-3">
            <button type="button" @click="modalRedirigir = false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="button" @click="submitRedirigir()" :disabled="redirigirCargoId === '0' || !redirigirMotivo || loading" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 disabled:opacity-40 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md transition-colors flex items-center gap-2">
                <i class="fas fa-arrows-turn-to-dots"></i>
                <span>Confirmar Redirección</span>
            </button>
        </footer>
    </div>
</div>



<!-- MODAL TRAZABILIDAD DE DESCARGAS (Columna A — AUDITORÍA) -->
<div id="modal-trazabilidad-descargas" x-show="modalAuditoria" x-cloak class="fixed inset-0 z-[320] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="modal-audit-titulo" @keydown.escape.window="if (modalAuditoria) cerrarModalAuditoria()">
    <div class="fixed inset-0 bg-gray-955/70 backdrop-blur-sm" @click="cerrarModalAuditoria()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-xl overflow-hidden flex flex-col max-h-[85vh] z-10 animate-in fade-in zoom-in-95 duration-150">
        <header class="bg-slate-900 text-white px-5 py-3.5 flex items-center justify-between border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center text-sm ring-1 ring-blue-500/30">
                    <i class="fas fa-clock-rotate-left"></i>
                </span>
                <div>
                    <h3 id="modal-audit-titulo" class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">Trazabilidad de Descargas</h3>
                    <p class="text-[10px] text-slate-400 font-medium" x-text="'Movimiento N° ' + (selectedMovimientoAudit?.cdgo_trmte_dcmnto || '') + (selectedMovimientoAudit?.nom_archivo ? ' • ' + selectedMovimientoAudit.nom_archivo : '')"></p>
                </div>
            </div>
            <button type="button" @click="cerrarModalAuditoria()" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-red-500 text-slate-300 hover:text-white flex items-center justify-center transition-colors" aria-label="Cerrar modal de trazabilidad">
                <i class="fas fa-times text-xs"></i>
            </button>
        </header>
        <div class="p-5 overflow-y-auto custom-scrollbar flex-1 bg-slate-50/50 space-y-3">
            <div x-show="auditLoading" class="py-12 text-center text-slate-500">
                <i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i>
                <p class="text-xs font-bold">Cargando registros de auditoría...</p>
            </div>
            <div x-show="!auditLoading && auditError" class="text-center py-10 text-rose-500">
                <i class="fas fa-circle-exclamation text-3xl mb-2"></i>
                <p class="text-xs font-bold" x-text="auditErrorMsg || 'Error al cargar registros.'"></p>
                <button type="button" @click="fetchAuditoriaDescargas(auditPagination.current_page || 1)" class="mt-3 px-3 py-1 bg-rose-100 hover:bg-rose-200 text-rose-700 font-bold rounded-lg text-xs transition-colors">
                    <i class="fas fa-rotate-right mr-1"></i> Reintentar
                </button>
            </div>
            <div x-show="!auditLoading && !auditError && auditData.length === 0" class="text-center py-10 text-slate-400">
                <i class="fas fa-file-circle-check text-3xl mb-2 text-slate-300"></i>
                <p class="text-xs font-bold italic">Sin descargas previas registradas</p>
                <p class="text-[10px] text-slate-400 mt-1">Este archivo no registra descargas en el sistema.</p>
            </div>
            <div x-show="!auditLoading && !auditError && auditData.length > 0" class="space-y-2.5">
                <template x-for="(d, idx) in auditData" :key="idx">
                    <div class="p-3.5 rounded-xl border border-slate-200 bg-white shadow-2xs hover:border-blue-200 transition-colors">
                        <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-2 mb-2">
                            <div>
                                <h4 class="font-extrabold text-slate-900 text-xs sm:text-sm leading-snug"
                                    x-text="d.nombre_usuario || 'Usuario no disponible'"></h4>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Usuario:</span>
                                    <span class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-1.5 py-0.2 rounded"
                                          x-text="d.login || '—'"></span>
                                    <template x-if="d.login_id">
                                        <span class="text-[10px] text-slate-400 font-mono" x-text="'(ID: ' + d.login_id + ')'"></span>
                                    </template>
                                </div>
                            </div>
                            <span class="text-[10px] font-black bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full shrink-0"
                                  x-text="'#' + (((auditPagination.current_page - 1) * (auditPagination.per_page || 10)) + idx + 1)"></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div class="flex items-center gap-1.5 text-slate-600">
                                <i class="fas fa-calendar-days text-[11px] text-slate-400"></i>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Fecha:</span>
                                <span class="font-semibold text-slate-800" x-text="d.fecha || d.fe_dscrga || '—'"></span>
                            </div>
                            <div class="flex items-center gap-1.5 text-slate-600">
                                <i class="fas fa-network-wired text-[11px] text-slate-400"></i>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">IP:</span>
                                <span class="font-mono text-slate-700 font-semibold" x-text="d.ip || d.ip_dscrga || '—'"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        <footer class="px-5 py-3 bg-white border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 shrink-0">
            <span class="text-[11px] font-medium" x-text="auditError ? 'Página 1 de 1 (0 registros)' : ('Página ' + (auditPagination.current_page || 1) + ' de ' + (auditPagination.last_page || 1) + ' (' + (auditPagination.total || auditData.length) + ' registros)')"></span>
            <div class="flex items-center gap-2">
                <button type="button" @click="changeAuditPage(-1)" :disabled="auditError || (auditPagination.current_page || 1) <= 1"
                        class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-bold text-xs">
                    <i class="fas fa-chevron-left text-[9px] mr-1"></i> Anterior
                </button>
                <button type="button" @click="changeAuditPage(1)" :disabled="auditError || (auditPagination.current_page || 1) >= (auditPagination.last_page || 1)"
                        class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-bold text-xs">
                    Siguiente <i class="fas fa-chevron-right text-[9px] ml-1"></i>
                </button>
                <button type="button" @click="cerrarModalAuditoria()"
                        class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-lg text-xs transition-colors ml-2">
                    Cerrar
                </button>
            </div>
        </footer>
    </div>
</div>

