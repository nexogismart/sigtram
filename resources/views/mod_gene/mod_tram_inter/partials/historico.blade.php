{{-- Tarjeta 2: Panel inferior = Histórico del trámite (Homologado con Trámite Externo) --}}
<div id="card-detalle-historico" class="sigtram-history-card hidden bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col flex-1 min-h-0 transition-all duration-300">
    <div id="cabecera-historico" class="sigtram-history-header bg-slate-50 px-4 py-2 border-b border-gray-200 flex items-center justify-between shrink-0 sticky top-0 z-20 shadow-xs">
        <div class="flex items-center gap-2 min-w-0">
            <i class="fas fa-file-lines text-slate-500 mr-0.5"></i>
            <span class="text-xs font-bold text-slate-700 whitespace-nowrap">Histórico de:</span>
            <span id="detalle-badge-titulo" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-black uppercase tracking-wide bg-amber-100/90 text-amber-950 border border-amber-300/80 truncate shadow-2xs">
                —
            </span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" data-action="maximizar-historico" id="btn-toggle-maximizar" class="text-gray-400 hover:text-slate-600 text-xs transition-colors p-1 rounded" title="Maximizar / Restaurar panel">
                <i class="fas fa-expand" id="icon-maximizar"></i>
            </button>
            <button type="button" data-action="cerrar-historico" class="text-gray-400 hover:text-red-500 text-xs transition-colors p-1 rounded" title="Cerrar / Ocultar histórico">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div id="panel-detalle" class="overflow-auto custom-scroll flex-1 min-h-0">
        <p class="text-xs text-gray-400 italic text-center py-10">Seleccione un trámite de la lista para ver su detalle / historial.</p>
    </div>
</div>
