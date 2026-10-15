{{-- =========================================================================
     MODALES DEL MÓDULO DE TRÁMITE INTERNO (RF-03 / RF-07)
     Sin handlers inline on* ni sinks DOM inseguros.
     ========================================================================= --}}

{{-- 1. Modal video tutorial institucional HD --}}
<div id="modal-video" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-3 sm:p-6" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-md transition-opacity" data-action="cerrar-video"></div>
    <div class="relative w-full max-w-4xl bg-slate-900 rounded-2xl overflow-hidden shadow-2xl border border-slate-800 z-10 flex flex-col animate-in fade-in zoom-in-95 duration-200">
        <div class="px-5 py-3.5 bg-slate-800 text-white flex justify-between items-center border-b border-slate-700/60">
            <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-full bg-red-600/20 text-red-400 flex items-center justify-center text-sm ring-1 ring-red-500/30">
                    <i class="fas fa-circle-play"></i>
                </span>
                <div>
                    <h3 id="modal-video-titulo" class="text-xs sm:text-sm font-bold tracking-wide text-white">Video Tutorial</h3>
                    <p class="text-[10px] text-slate-400 font-medium">Guía audiovisual de uso del módulo de Trámite Interno</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden sm:inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-slate-700 text-slate-300">
                    <i class="fas fa-tv text-[9px] text-amber-400"></i> Video MP4 HD
                </span>
                <button type="button" data-action="cerrar-video" class="w-8 h-8 rounded-xl bg-white/10 hover:bg-red-500 text-slate-300 hover:text-white flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        </div>
        <div class="bg-black aspect-video relative flex items-center justify-center overflow-hidden">
            <video id="modal-video-player" class="w-full h-full object-contain" controls preload="auto" playsinline></video>
            <div id="video-overlay-play" class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center cursor-pointer transition-opacity group" data-action="play-video-overlay">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-red-600 group-hover:bg-red-500 text-white flex items-center justify-center shadow-2xl transition-transform group-hover:scale-110 mb-2">
                    <i class="fas fa-play text-xl sm:text-2xl ml-1"></i>
                </div>
                <span class="text-xs font-black uppercase tracking-wider text-white drop-shadow-md">Clic para reproducir</span>
            </div>
        </div>
        <div class="px-5 py-3 bg-slate-900 border-t border-slate-800 text-[11px] text-slate-400 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-keyboard text-slate-500 text-xs"></i>
                <span>Atajos: <strong class="text-slate-300">[Espacio]</strong> Pausar/Reanudar • <strong class="text-slate-300">[ESC]</strong> Cerrar</span>
            </div>
            <button type="button" data-action="cerrar-video" class="px-4 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition-colors">
                Cerrar
            </button>
        </div>
    </div>
</div>

{{-- 2. Modal Zoom de Usuarios (SDD Spec 10 / RF-03) --}}
<div id="modal-usuarios-zoom" class="fixed inset-0 z-[320] hidden flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="zoom-movimiento-titulo" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-955/70 backdrop-blur-sm" data-action="cerrar-usuarios"></div>
    <div class="relative w-full max-w-4xl bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 flex flex-col max-h-[85vh] animate-in fade-in zoom-in-95 duration-150">
        <div class="bg-slate-900 text-white px-5 py-3.5 flex justify-between items-center border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-amber-400/20 text-amber-400 flex items-center justify-center text-sm ring-1 ring-amber-400/30">
                    <i class="fas fa-users-viewfinder"></i>
                </span>
                <div>
                    <h3 id="zoom-movimiento-titulo" class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">Participantes — Movimiento</h3>
                    <p id="zoom-movimiento-subtitulo" class="text-[10px] text-slate-400 font-medium">Trazabilidad histórica de participantes</p>
                </div>
            </div>
            <button type="button" data-action="cerrar-usuarios" id="btn-cerrar-modal-u" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-red-500 text-slate-300 hover:text-white flex items-center justify-center transition-colors" aria-label="Cerrar modal de participantes">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <div class="p-5 grid grid-cols-1 md:grid-cols-12 gap-4 overflow-y-auto custom-scroll bg-slate-50/50 flex-1">
            {{-- Panel Izquierdo: EJECUTADO POR --}}
            <div class="md:col-span-5 bg-white border border-slate-200 rounded-xl p-4 shadow-2xs flex flex-col justify-between space-y-3">
                <div>
                    <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                        <i class="fas fa-user-tag text-amber-500 text-xs"></i>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Ejecutado por</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Nombre Completo</span>
                            <h4 id="zoom-origen-nombre" class="font-extrabold text-slate-900 text-xs sm:text-sm leading-snug">-</h4>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Login</span>
                            <span id="zoom-origen-login" class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">-</span>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Cargo</span>
                            <p id="zoom-origen-cargo" class="text-xs text-slate-800 font-semibold leading-tight">-</p>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Área</span>
                            <span id="zoom-origen-area" class="inline-block mt-0.5 px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-bold">-</span>
                        </div>
                        <div id="zoom-box-perfil" class="hidden">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Perfil</span>
                            <span id="zoom-origen-perfil" class="inline-block mt-0.5 px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold">-</span>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Fecha / Estado</span>
                            <p id="zoom-origen-fecha" class="text-[11px] text-slate-600 font-semibold">-</p>
                        </div>
                    </div>
                </div>
                <div id="zoom-box-recepcionado" class="hidden p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-[10px] text-emerald-900">
                    <span class="font-bold uppercase tracking-wider block">Recepcionado por:</span>
                    <span id="zoom-recepcionado-texto" class="font-medium">-</span>
                </div>
            </div>

            {{-- Panel Derecho: DESTINO --}}
            <div class="md:col-span-7 bg-white border border-slate-200 rounded-xl p-4 shadow-2xs flex flex-col">
                <div class="flex items-center justify-between gap-2 pb-2 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-users-gear text-emerald-500 text-xs"></i>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Destino — Actualmente asignados</span>
                    </div>
                    <span id="zoom-destino-cant" class="text-[9px] font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">Cargando...</span>
                </div>

                <div class="my-3 relative">
                    <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text"
                           id="zoom-buscar-usuario"
                           placeholder="Buscar por nombre, login o cargo..."
                           class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-slate-500 outline-none transition-all"
                           autocomplete="off">
                </div>

                <div class="flex-1 min-h-[180px] max-h-[300px] overflow-y-auto custom-scroll relative" id="zoom-lista-container" aria-live="polite">
                    <div id="zoom-destino-lista" class="space-y-2"></div>
                    <div id="zoom-error-container" class="hidden text-center py-6 text-rose-500">
                        <i class="fas fa-triangle-exclamation text-lg mb-1"></i>
                        <p id="zoom-error-mensaje" class="text-xs font-semibold">Error al obtener participantes</p>
                        <button type="button" id="btn-reintentar-u" data-action="reintentar-u" class="mt-3 px-3 py-1 bg-rose-100 hover:bg-rose-200 text-rose-700 font-bold rounded-lg text-xs transition-colors">
                            <i class="fas fa-rotate-right mr-1"></i> Reintentar
                        </button>
                    </div>
                    <div id="zoom-loader" class="hidden absolute inset-0 bg-white/80 backdrop-blur-xs flex items-center justify-center">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-600">
                            <i class="fas fa-spinner fa-spin text-amber-500"></i> Cargando usuarios...
                        </div>
                    </div>
                </div>

                <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span id="zoom-paginacion-info" class="text-[11px] font-medium">Página 1 de 1</span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" id="zoom-btn-prev" data-action="pag-u-prev" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-bold text-[11px]">
                            <i class="fas fa-chevron-left text-[9px] mr-1"></i> Anterior
                        </button>
                        <button type="button" id="zoom-btn-next" data-action="pag-u-next" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-bold text-[11px]">
                            Siguiente <i class="fas fa-chevron-right text-[9px] ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-5 py-3 bg-white border-t border-slate-100 flex justify-between items-center text-xs text-slate-400 shrink-0">
            <span class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-semibold">
                    <i class="fas fa-circle-info text-[9px] text-amber-500"></i> Fuente: Asignación actual
                </span>
                <span class="hidden sm:inline">Presione <strong class="text-slate-600">[ESC]</strong> para cerrar</span>
            </span>
            <button type="button" data-action="cerrar-usuarios" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-xs transition-colors">
                Cerrar
            </button>
        </div>
    </div>
</div>

{{-- Modal Auditoría de Descargas (Columna A — Interno) --}}
<div id="modal-auditoria-descargas" class="fixed inset-0 z-[320] hidden flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="modal-audit-titulo-int" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-955/70 backdrop-blur-sm" data-action="cerrar-auditoria-int"></div>
    <div class="relative w-full max-w-xl bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 flex flex-col max-h-[85vh] animate-in fade-in zoom-in-95 duration-150">
        <header class="bg-slate-900 text-white px-5 py-3.5 flex items-center justify-between border-b border-slate-800 shrink-0">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center text-sm ring-1 ring-blue-500/30">
                    <i class="fas fa-clock-rotate-left"></i>
                </span>
                <div>
                    <h3 id="modal-audit-titulo-int" class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">Trazabilidad de Descargas</h3>
                    <p id="modal-audit-subtitulo-int" class="text-[10px] text-slate-400 font-medium">Movimiento</p>
                </div>
            </div>
            <button type="button" data-action="cerrar-auditoria-int" id="btn-cerrar-modal-audit-int" class="w-7 h-7 rounded-lg bg-white/10 hover:bg-red-500 text-slate-300 hover:text-white flex items-center justify-center transition-colors" aria-label="Cerrar modal de trazabilidad">
                <i class="fas fa-times text-xs"></i>
            </button>
        </header>

        <div class="p-5 overflow-y-auto custom-scroll flex-1 bg-slate-50/50 space-y-3" id="modal-audit-int-body">
            <div id="modal-audit-int-loader" class="hidden py-12 text-center text-slate-500">
                <i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i>
                <p class="text-xs font-bold">Cargando registros de auditoría...</p>
            </div>
            <div id="modal-audit-int-empty" class="hidden text-center py-10 text-slate-400">
                <i class="fas fa-file-circle-check text-3xl mb-2 text-slate-300"></i>
                <p class="text-xs font-bold italic">Sin descargas previas registradas</p>
                <p class="text-[10px] text-slate-400 mt-1">Este archivo no registra descargas en el sistema.</p>
            </div>
            <div id="modal-audit-int-error" class="hidden text-center py-10 text-red-500">
                <i class="fas fa-circle-exclamation text-3xl mb-2"></i>
                <p class="text-xs font-bold" id="modal-audit-int-error-msg">Error al cargar registros.</p>
                <button type="button" id="btn-retry-audit-int" class="mt-3 px-3 py-1 bg-red-100 hover:bg-red-200 text-red-700 font-bold rounded-lg text-xs transition-colors">
                    <i class="fas fa-rotate-right mr-1"></i> Reintentar
                </button>
            </div>
            <div id="modal-audit-int-lista" class="space-y-2.5"></div>
        </div>

        <footer class="px-5 py-3 bg-white border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 shrink-0">
            <span id="modal-audit-int-pag-info" class="text-[11px] font-medium">Página 1 de 1</span>
            <div class="flex items-center gap-2">
                <button type="button" id="btn-audit-int-prev" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-bold text-xs">
                    <i class="fas fa-chevron-left text-[9px] mr-1"></i> Anterior
                </button>
                <button type="button" id="btn-audit-int-next" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed font-bold text-xs">
                    Siguiente <i class="fas fa-chevron-right text-[9px] ml-1"></i>
                </button>
                <button type="button" data-action="cerrar-auditoria-int" class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-lg text-xs transition-colors ml-2">
                    Cerrar
                </button>
            </div>
        </footer>
    </div>
</div>

{{-- 3. Modal Detalle del Documento Interno (Voucher) --}}
<div id="modal-detalle-doc" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-3 sm:p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-detalle-doc"></div>
    <div class="relative w-full max-w-lg bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 animate-in fade-in zoom-in-95 duration-150">
        <div class="bg-slate-800 text-white px-5 py-3 flex justify-between items-center">
            <h3 class="text-xs font-black uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-file-lines text-amber-400"></i> Detalle del Documento Interno
            </h3>
            <button type="button" data-action="cerrar-detalle-doc" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 space-y-3.5 text-xs text-slate-800">
            <div class="text-center pb-2.5 border-b border-gray-100">
                <span class="text-[10px] font-black uppercase text-gray-400 tracking-widest block mb-0.5">Nº Trámite</span>
                <span id="doc-numero" class="text-sm sm:text-base font-extrabold text-slate-900 font-mono">-</span>
            </div>
            <div class="grid grid-cols-2 gap-3 bg-gray-50 p-3 rounded-xl border border-gray-100">
                <div>
                    <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block">Fecha</span>
                    <span id="doc-fecha" class="font-bold text-slate-800">-</span>
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block">Hora</span>
                    <span id="doc-hora" class="font-bold text-slate-800">-</span>
                </div>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block mb-0.5">Área Inicio</span>
                <span id="doc-area-inicio" class="font-bold text-slate-800 text-xs">-</span>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block mb-1">Detalles / Observaciones</span>
                <div id="doc-detalles" class="bg-gray-50 p-3 rounded-xl border border-gray-100 text-slate-700 min-h-[60px] leading-relaxed max-h-36 overflow-y-auto custom-scroll"></div>
            </div>
            <div id="doc-adjunto-container" class="hidden">
                <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block mb-1">Adjuntado</span>
                <div id="doc-adjunto-enlace" class="text-xs font-semibold text-slate-700"></div>
            </div>
            <div id="doc-reemplazar-container" class="hidden p-3 bg-amber-50/70 border border-amber-200 rounded-xl space-y-2">
                <span class="text-[10px] font-black uppercase text-amber-800 tracking-wider flex items-center gap-1.5">
                    <i class="fas fa-file-signature text-amber-600"></i> Reemplazar Documento Firmado (PDF)
                </span>
                <form id="form-reemplazar-firmado" class="space-y-2">
                    <input type="hidden" id="reemplazo-doc-id" name="id" value="">
                    <input type="file" id="archivo-firmado" name="archivo" accept="application/pdf" class="block w-full text-xs text-slate-700 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-slate-800 file:text-white hover:file:bg-slate-700 cursor-pointer" required>
                    <button type="submit" id="btn-submit-reemplazo" class="w-full py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-lg transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                        <i class="fas fa-upload"></i> Subir y Reemplazar Firmado
                    </button>
                </form>
            </div>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <button type="button" data-action="cerrar-detalle-doc" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl text-xs transition-colors">
                Cerrar
            </button>
            <div id="doc-acciones-adjunto"></div>
        </div>
    </div>
</div>

{{-- 4. Modal Comprobantes de Pago --}}
<div id="modal-comprobantes-pago" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-3 sm:p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-comprobantes"></div>
    <div class="relative w-full max-w-4xl bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 animate-in fade-in zoom-in-95 duration-150 flex flex-col max-h-[85vh]">
        <div class="bg-slate-800 text-white px-5 py-3 flex justify-between items-center shrink-0">
            <h3 class="text-xs font-black uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-receipt text-amber-400"></i> Lista de Comprobantes de Pago (Contabilidad)
            </h3>
            <button type="button" data-action="cerrar-comprobantes" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-4 border-b border-gray-100 bg-gray-50/70 flex items-center justify-between gap-3 shrink-0">
            <div class="flex-1 max-w-sm">
                <input type="text" id="filtro-comprobantes" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-xs bg-white focus:ring-2 focus:ring-slate-500 outline-none" placeholder="Buscar por N° comprobante, trámite o beneficiario...">
            </div>
            <button type="button" data-action="recargar-comprobantes" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>
        </div>
        <div class="p-4 overflow-y-auto custom-scroll flex-1">
            <div id="loader-comprobantes" class="hidden text-center py-8">
                <div class="w-7 h-7 border-4 border-slate-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                <span class="text-xs font-bold text-gray-500">Cargando comprobantes...</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-700 tracking-wider">
                        <tr>
                            <th class="px-3 py-2 border-b">Nº Comprobante</th>
                            <th class="px-3 py-2 border-b text-center">Fecha</th>
                            <th class="px-3 py-2 border-b text-right">Importe</th>
                            <th class="px-3 py-2 border-b">Beneficiario</th>
                            <th class="px-3 py-2 border-b">Nº Trámite</th>
                            <th class="px-3 py-2 border-b">Área</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-comprobantes" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end shrink-0">
            <button type="button" data-action="cerrar-comprobantes" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl text-xs transition-colors">
                Cerrar
            </button>
        </div>
    </div>
</div>

{{-- 5. Modal Voucher / Comprobante de Movimiento --}}
<div id="modal-voucher" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-voucher"></div>
    <div class="relative w-full max-w-md bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 animate-in fade-in zoom-in-95 duration-150">
        <div class="bg-slate-800 text-white px-5 py-3.5 flex justify-between items-center">
            <h3 class="text-xs font-black uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-ticket text-amber-400"></i> Comprobante de Movimiento
            </h3>
            <button type="button" data-action="cerrar-voucher" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-5 space-y-3.5 text-xs text-slate-800">
            <div class="text-center pb-2 border-b border-gray-100">
                <span class="text-[10px] font-black uppercase text-gray-400 tracking-widest block mb-0.5">Detalle del Documento Interno</span>
                <span id="vouch-numero" class="text-sm font-bold text-slate-900">-</span>
            </div>
            <div class="grid grid-cols-2 gap-3 bg-gray-50 p-3 rounded-xl border border-gray-100">
                <div>
                    <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block">Fecha</span>
                    <span id="vouch-fecha" class="font-bold text-slate-800">-</span>
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block">Hora</span>
                    <span id="vouch-hora" class="font-bold text-slate-800">-</span>
                </div>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block mb-1">Adjuntado</span>
                <div id="vouch-adjunto" class="font-semibold text-slate-700">No Tiene Adjunto</div>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider block mb-1">Detalles / Proveído</span>
                <div id="vouch-detalles" class="bg-gray-50 p-2.5 rounded-xl border border-gray-100 text-slate-700 min-h-[50px] leading-relaxed"></div>
            </div>
        </div>
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
            <button type="button" data-action="cerrar-voucher" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-xs transition-colors">
                Cerrar
            </button>
        </div>
    </div>
</div>

{{-- 6. Modal Recepcionar --}}
<div id="modal-recepcionar" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-recepcionar"></div>
    <div class="relative w-full max-w-lg bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-100 z-10">
        <div class="px-6 py-4 bg-slate-800 text-white flex justify-between items-center">
            <h3 class="text-xs font-black uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-check-double text-emerald-400"></i> Recepcionar Trámites
            </h3>
            <button type="button" data-action="cerrar-recepcionar" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-xs text-gray-600">
                Va a recepcionar <span id="recep-cant" class="font-bold text-slate-800">1</span> trámite(s) seleccionado(s).
            </p>
            <div>
                <label for="recep-fecha" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Fecha de Recepción</label>
                <input type="datetime-local" id="recep-fecha" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
            </div>
            <div>
                <label for="recep-obs" class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Observación / Proveído</label>
                <textarea id="recep-obs" rows="3" class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500">SIN NOVEDADES PARA RECEPCIONAR</textarea>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
            <button type="button" data-action="cerrar-recepcionar" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-100">Cancelar</button>
            <button type="button" data-action="submit-recep" id="btn-submit-recep" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm">
                <i class="fas fa-check"></i> Confirmar Recepción
            </button>
        </div>
    </div>
</div>

{{-- 7. Modal Notificación / Aviso --}}
<div id="modal-aviso" class="fixed inset-0 z-[310] hidden flex items-center justify-center p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-aviso"></div>
    <div class="relative w-full max-w-sm bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-100 z-10 p-5 text-center">
        <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-3 text-lg">
            <i class="fas fa-circle-info"></i>
        </div>
        <h4 id="aviso-titulo" class="text-sm font-bold text-slate-900 mb-1">Aviso</h4>
        <p id="aviso-mensaje" class="text-xs text-gray-600 mb-4 leading-relaxed"></p>
        <button type="button" data-action="cerrar-aviso" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-xs transition-colors">
            Entendido
        </button>
    </div>
</div>

{{-- 8. Modal Asignar --}}
<div id="modal-asignar" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-3 sm:p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-asignar"></div>
    <div class="relative w-full max-w-xl bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-150">
        <div class="bg-slate-800 text-white px-5 py-3.5 flex justify-between items-center">
            <div class="flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs">
                    <i class="fas fa-user-check"></i>
                </span>
                <div>
                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">Asignar Trámite Interno</h3>
                    <p id="asignar-subtitulo" class="text-[10px] text-gray-300">0 expedientes seleccionados</p>
                </div>
            </div>
            <button type="button" data-action="cerrar-asignar" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="form-asignar" class="p-5 overflow-y-auto custom-scroll space-y-3.5 text-xs text-slate-800 flex-1">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <label for="asignar-cargo" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                        Asignar a Colaborador / Cargo <span class="text-red-500">*</span>
                    </label>
                    <select id="asignar-cargo" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none" required>
                        <option value="">-- Cargando cargos... --</option>
                    </select>
                </div>
                <div>
                    <label for="asignar-fecha" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                        Fecha / Hora de Asignación
                    </label>
                    <input type="datetime-local" id="asignar-fecha" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                </div>
                <div class="flex items-center sm:pt-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs font-bold text-slate-700">
                        <input type="checkbox" id="asignar-chk-crear-doc" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                        <span>Crear Trámite Interno</span>
                    </label>
                </div>
            </div>

            <div id="asignar-subseccion-doc" class="hidden bg-slate-50 border border-slate-200 rounded-xl p-3.5 space-y-3">
                <div class="flex items-center justify-between pb-1 border-b border-slate-200">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-600">Nuevo Documento Interno Asociado</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label for="asignar-tipo-doc" class="block text-[9px] font-bold text-gray-500 mb-0.5">Tipo de Documento</label>
                        <select id="asignar-tipo-doc" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs bg-white">
                            <option value="">-- Seleccione Tipo --</option>
                        </select>
                    </div>
                    <div>
                        <label for="asignar-nu-smlla" class="block text-[9px] font-bold text-gray-500 mb-0.5">Nº Trámite / Correlativo</label>
                        <input type="text" id="asignar-nu-smlla" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs bg-white font-mono" placeholder="Ej: 1-2026">
                    </div>
                </div>
                <div>
                    <label for="asignar-pdf-firmado" class="block text-[9px] font-bold text-gray-500 mb-0.5">PDF Firmado Digitalmente</label>
                    <input type="file" id="asignar-pdf-firmado" accept="application/pdf" class="w-full border border-gray-300 rounded-lg px-2.5 py-1 text-xs bg-white file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-slate-200 file:text-slate-700">
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-[11px] font-semibold text-slate-600">
                        <input type="checkbox" id="asignar-chk-fisico" class="rounded border-gray-300 text-slate-700">
                        <span>¿Incluye traslado físico (en papel)?</span>
                    </label>
                </div>
            </div>

            <details class="bg-gray-50 border border-gray-200 rounded-xl p-2.5 text-xs group">
                <summary class="font-bold text-slate-700 cursor-pointer select-none flex items-center justify-between text-[11px]">
                    <span class="flex items-center gap-1.5"><i class="fas fa-folder-plus text-slate-500"></i> Adjuntar más archivos (Anexos digitales)</span>
                    <i class="fas fa-chevron-down text-slate-400 group-open:rotate-180 transition-transform"></i>
                </summary>
                <div class="mt-3 space-y-2 pt-2 border-t border-gray-200">
                    <div>
                        <span class="text-[9px] font-bold text-gray-500 block mb-0.5">Adjuntar Anexo 1:</span>
                        <input type="file" id="asignar-adjunto2" class="w-full text-xs">
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-gray-500 block mb-0.5">Adjuntar Anexo 2:</span>
                        <input type="file" id="asignar-adjunto3" class="w-full text-xs">
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-gray-500 block mb-0.5">Adjuntar Anexo 3:</span>
                        <input type="file" id="asignar-adjunto4" class="w-full text-xs">
                    </div>
                </div>
            </details>

            <div>
                <label for="asignar-observaciones" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Observaciones / Proveído <span class="text-red-500">*</span>
                </label>
                <textarea id="asignar-observaciones" rows="3" class="w-full border border-gray-300 rounded-xl p-2.5 text-xs font-medium focus:ring-2 focus:ring-slate-500 outline-none leading-relaxed" required>Sin novedades para asignar</textarea>
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <button type="button" data-action="cerrar-asignar" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-asignar" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5">
                    <i class="fas fa-user-check"></i> <span>Asignar Trámite(s)</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- 9. Modal Derivar --}}
<div id="modal-derivar" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-3 sm:p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-derivar"></div>
    <div class="relative w-full max-w-xl bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-150">
        <div class="bg-slate-800 text-white px-5 py-3.5 flex justify-between items-center">
            <div class="flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-lg bg-blue-500/20 text-blue-400 flex items-center justify-center text-xs">
                    <i class="fas fa-share"></i>
                </span>
                <div>
                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">Derivar Trámite Interno</h3>
                    <p id="derivar-subtitulo" class="text-[10px] text-gray-300">0 expedientes seleccionados</p>
                </div>
            </div>
            <button type="button" data-action="cerrar-derivar" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="form-derivar" class="p-5 overflow-y-auto custom-scroll space-y-3.5 text-xs text-slate-800 flex-1">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500">
                        Áreas de Destino <span class="text-red-500">*</span>
                    </label>
                    <span id="derivar-contador-areas" class="text-[10px] font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full">0 seleccionadas</span>
                </div>
                <div class="border border-gray-300 rounded-xl overflow-hidden bg-white">
                    <div class="p-2 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                        <i class="fas fa-search text-gray-400 text-xs"></i>
                        <input type="text" id="derivar-buscar-area" placeholder="Buscar área por nombre o sigla..." class="w-full bg-transparent text-xs outline-none font-medium">
                    </div>
                    <div id="derivar-lista-areas" class="max-h-36 overflow-y-auto custom-scroll p-2 divide-y divide-gray-100"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="derivar-fecha" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                        Fecha / Hora de Envío
                    </label>
                    <input type="datetime-local" id="derivar-fecha" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                </div>
                <div class="flex items-center sm:pt-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs font-bold text-slate-700">
                        <input type="checkbox" id="derivar-chk-crear-doc" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                        <span>Crear Trámite Interno</span>
                    </label>
                </div>
            </div>

            <div id="derivar-subseccion-doc" class="hidden bg-slate-50 border border-slate-200 rounded-xl p-3.5 space-y-3">
                <div class="flex items-center justify-between pb-1 border-b border-slate-200">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-600">Nuevo Documento Interno Asociado</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label for="derivar-tipo-doc" class="block text-[9px] font-bold text-gray-500 mb-0.5">Tipo de Documento</label>
                        <select id="derivar-tipo-doc" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs bg-white">
                            <option value="">-- Seleccione Tipo --</option>
                        </select>
                    </div>
                    <div>
                        <label for="derivar-nu-smlla" class="block text-[9px] font-bold text-gray-500 mb-0.5">Nº Trámite / Correlativo</label>
                        <input type="text" id="derivar-nu-smlla" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs bg-white font-mono" placeholder="Ej: 1-2026">
                    </div>
                </div>
                <div>
                    <label for="derivar-pdf-firmado" class="block text-[9px] font-bold text-gray-500 mb-0.5">PDF Firmado Digitalmente</label>
                    <input type="file" id="derivar-pdf-firmado" accept="application/pdf" class="w-full border border-gray-300 rounded-lg px-2.5 py-1 text-xs bg-white file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-slate-200 file:text-slate-700">
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-[11px] font-semibold text-slate-600">
                        <input type="checkbox" id="derivar-chk-fisico" class="rounded border-gray-300 text-slate-700">
                        <span>¿Incluye traslado físico (en papel)?</span>
                    </label>
                </div>
            </div>

            <details class="bg-gray-50 border border-gray-200 rounded-xl p-2.5 text-xs group">
                <summary class="font-bold text-slate-700 cursor-pointer select-none flex items-center justify-between text-[11px]">
                    <span class="flex items-center gap-1.5"><i class="fas fa-folder-plus text-slate-500"></i> Adjuntar más archivos (Anexos digitales)</span>
                    <i class="fas fa-chevron-down text-slate-400 group-open:rotate-180 transition-transform"></i>
                </summary>
                <div class="mt-3 space-y-2 pt-2 border-t border-gray-200">
                    <div>
                        <span class="text-[9px] font-bold text-gray-500 block mb-0.5">Adjuntar Anexo 1:</span>
                        <input type="file" id="derivar-adjunto2" class="w-full text-xs">
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-gray-500 block mb-0.5">Adjuntar Anexo 2:</span>
                        <input type="file" id="derivar-adjunto3" class="w-full text-xs">
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-gray-500 block mb-0.5">Adjuntar Anexo 3:</span>
                        <input type="file" id="derivar-adjunto4" class="w-full text-xs">
                    </div>
                </div>
            </details>

            <div>
                <label for="derivar-observaciones" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Observaciones <span class="text-red-500">*</span>
                </label>
                <textarea id="derivar-observaciones" rows="3" class="w-full border border-gray-300 rounded-xl p-2.5 text-xs font-medium focus:ring-2 focus:ring-slate-500 outline-none leading-relaxed" required>Sin novedades para enviar</textarea>
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <button type="button" data-action="cerrar-derivar" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-derivar" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5">
                    <i class="fas fa-share"></i> <span>Derivar Trámite(s)</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- 10. Modal Finalizar --}}
<div id="modal-finalizar" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-3 sm:p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-finalizar"></div>
    <div class="relative w-full max-w-lg bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-150">
        <div class="bg-slate-800 text-white px-5 py-3.5 flex justify-between items-center">
            <div class="flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center text-xs">
                    <i class="fas fa-flag-checkered"></i>
                </span>
                <div>
                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">Finalizar Trámite Interno</h3>
                    <p id="finalizar-subtitulo" class="text-[10px] text-gray-300">0 expedientes seleccionados</p>
                </div>
            </div>
            <button type="button" data-action="cerrar-finalizar" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="form-finalizar" class="p-5 overflow-y-auto custom-scroll space-y-3.5 text-xs text-slate-800 flex-1">
            <div>
                <label for="finalizar-fecha" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Fecha / Hora de Conclusión
                </label>
                <input type="datetime-local" id="finalizar-fecha" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
            </div>

            <div>
                <label for="finalizar-adjunto" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Adjunto de Conclusión (Acta, informe o resolución - opcional)
                </label>
                <input type="file" id="finalizar-adjunto" class="w-full border border-gray-300 rounded-xl px-3 py-1.5 text-xs bg-white file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-slate-200 file:text-slate-700">
            </div>

            <div>
                <label for="finalizar-observaciones" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Fundamentación / Observaciones de Cierre <span class="text-red-500">*</span>
                </label>
                <textarea id="finalizar-observaciones" rows="4" class="w-full border border-gray-300 rounded-xl p-2.5 text-xs font-medium focus:ring-2 focus:ring-slate-500 outline-none leading-relaxed" required>Sin novedades para finalizar</textarea>
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <button type="button" data-action="cerrar-finalizar" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-finalizar" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5">
                    <i class="fas fa-flag-checkered"></i> <span>Finalizar Trámite(s)</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- 11. Modal Devolver --}}
<div id="modal-devolver" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-3 sm:p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-devolver"></div>
    <div class="relative w-full max-w-lg bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-150">
        <div class="bg-slate-800 text-white px-5 py-3.5 flex justify-between items-center">
            <div class="flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center text-xs">
                    <i class="fas fa-rotate-left"></i>
                </span>
                <div>
                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">Devolver Trámite Interno</h3>
                    <p id="devolver-subtitulo" class="text-[10px] text-gray-300">0 expedientes seleccionados</p>
                </div>
            </div>
            <button type="button" data-action="cerrar-devolver" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="form-devolver" class="p-5 overflow-y-auto custom-scroll space-y-3.5 text-xs text-slate-800 flex-1">
            <div>
                <label for="devolver-fecha" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Fecha / Hora de Devolución
                </label>
                <input type="datetime-local" id="devolver-fecha" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
            </div>

            <div>
                <label for="devolver-observaciones" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Motivo / Causal de Devolución <span class="text-red-500">*</span>
                </label>
                <textarea id="devolver-observaciones" rows="4" class="w-full border border-gray-300 rounded-xl p-2.5 text-xs font-medium focus:ring-2 focus:ring-slate-500 outline-none leading-relaxed" required>Sin novedades para devolver</textarea>
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <button type="button" data-action="cerrar-devolver" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-devolver" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5">
                    <i class="fas fa-rotate-left"></i> <span>Devolver Trámite(s)</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- 12. Modal Reiterar --}}
<div id="modal-reiterar" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-3 sm:p-4" aria-hidden="true">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-action="cerrar-reiterar"></div>
    <div class="relative w-full max-w-lg bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-200 z-10 flex flex-col max-h-[90vh] animate-in fade-in zoom-in-95 duration-150">
        <div class="bg-slate-800 text-white px-5 py-3.5 flex justify-between items-center">
            <div class="flex items-center gap-2.5">
                <span class="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xs">
                    <i class="fas fa-redo"></i>
                </span>
                <div>
                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-white">Reiterativo de Trámite Interno</h3>
                    <p id="reiterar-subtitulo" class="text-[10px] text-gray-300">0 expedientes seleccionados</p>
                </div>
            </div>
            <button type="button" data-action="cerrar-reiterar" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form id="form-reiterar" class="p-5 overflow-y-auto custom-scroll space-y-3.5 text-xs text-slate-800 flex-1">
            <div>
                <label for="reiterar-fecha" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Fecha / Hora del Reiterativo
                </label>
                <input type="datetime-local" id="reiterar-fecha" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
            </div>

            <div>
                <label for="reiterar-adjunto" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Adjunto de Reiteración (Opcional)
                </label>
                <input type="file" id="reiterar-adjunto" class="w-full border border-gray-300 rounded-xl px-3 py-1.5 text-xs bg-white file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-slate-200 file:text-slate-700">
            </div>

            <div>
                <label for="reiterar-observaciones" class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">
                    Observaciones / Conminación <span class="text-red-500">*</span>
                </label>
                <textarea id="reiterar-observaciones" rows="4" class="w-full border border-gray-300 rounded-xl p-2.5 text-xs font-medium focus:ring-2 focus:ring-slate-500 outline-none leading-relaxed" required>Sin novedades para reiterar</textarea>
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <button type="button" data-action="cerrar-reiterar" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-reiterar" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5">
                    <i class="fas fa-redo"></i> <span>Reiterar Trámite(s)</span>
                </button>
            </div>
        </form>
    </div>
</div>
