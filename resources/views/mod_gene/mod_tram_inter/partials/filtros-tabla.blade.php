{{-- Tarjeta 1: Lista Cabecera (Bandeja) con filtros y tabla (RF-07) --}}
<div class="sigtram-bandeja-card bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col flex-1 min-h-0">
    {{-- Título de bandeja --}}
    <div class="sigtram-card-header bg-slate-800 text-white px-4 py-2 flex items-center justify-between gap-3 shrink-0">
        <h2 id="titulo-bandeja" class="text-sm sm:text-base font-bold uppercase tracking-wide">
            Bandeja Trámite Interno — Entrada
        </h2>
    </div>

    {{-- Búsqueda compacta para maximizar espacio de histórico --}}
    <div class="sigtram-filtros-panel border-b border-gray-100 px-3 py-2 bg-gray-50/70 shrink-0">
        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Búsqueda por</p>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
            <div class="md:col-span-3">
                <label for="filtro-criterio" class="block text-[9px] font-bold text-gray-500 mb-0.5">Criterio</label>
                <select id="filtro-criterio" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                    <option value="tramite">Nº de trámite</option>
                    <option value="envio">Nº de trámite Envío</option>
                    <option value="referencia">Nº de trámite Referencia</option>
                    <option value="area">Área de Inicio</option>
                    <option value="tipo">Tipo de documento</option>
                    <option value="observaciones">Observaciones</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label for="filtro-descripcion" class="block text-[9px] font-bold text-gray-500 mb-0.5">Descripción</label>
                <input type="text" id="filtro-descripcion" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none" placeholder="Texto a buscar...">
            </div>
            <div class="md:col-span-2">
                <label for="filtro-desde" class="block text-[9px] font-bold text-gray-500 mb-0.5">Desde</label>
                <input type="date" id="filtro-desde" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
            </div>
            <div class="md:col-span-2">
                <label for="filtro-hasta" class="block text-[9px] font-bold text-gray-500 mb-0.5">Hasta</label>
                <input type="date" id="filtro-hasta" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
            </div>
            <div class="sigtram-filter-actions md:col-span-2 flex gap-2">
                <button type="button" id="btn-buscar" data-action="consultar" class="sigtram-consult-button w-full py-1.5 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all flex items-center justify-center gap-1.5">
                    <i class="fas fa-search" aria-hidden="true"></i> <span>Consultar</span>
                </button>
                <button type="button" data-action="limpiar-filtros" title="Limpiar filtros" aria-label="Limpiar filtros" class="sigtram-clear-button py-1.5 bg-gray-500 hover:bg-gray-600 text-white rounded-lg shadow-sm transition-all flex items-center justify-center">
                    <i class="fas fa-eraser" aria-hidden="true"></i><span class="sr-only">Limpiar filtros</span>
                </button>
            </div>
        </div>
        <input type="hidden" id="filtro-bandeja" value="entrada">
        <input type="hidden" id="filtro-reporte" value="todos">
    </div>

    {{-- Acciones dinámicas por bandeja --}}
    <div id="barra-acciones" class="sigtram-actions-bar px-3 py-1.5 border-b border-gray-100 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-700 bg-white shrink-0">
        {{-- Generado dinámicamente según la bandeja seleccionada --}}
    </div>

    {{-- Tabla Principal de la Bandeja --}}
    <div class="sigtram-table-scroll relative overflow-auto custom-scroll flex-1 min-h-0">
        <div id="loader" class="absolute inset-0 bg-white/70 z-10 hidden items-center justify-center">
            <div class="flex flex-col items-center gap-2">
                <div class="w-8 h-8 border-4 border-slate-600 border-t-transparent rounded-full animate-spin"></div>
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Consultando...</span>
            </div>
        </div>

        <table class="w-full min-w-[960px] text-left text-sm border-collapse" id="tabla-bandeja">
            <thead class="bg-slate-50 sticky top-0 z-[5]">
                <tr id="thead-row">
                    {{-- Se regenera de forma segura según la bandeja --}}
                </tr>
            </thead>
            <tbody id="tbody-principal"></tbody>
        </table>

        <div id="empty-state" class="hidden py-16 text-center text-gray-400">
            <i class="fas fa-inbox text-3xl mb-3 text-gray-200"></i>
            <p class="text-xs font-bold uppercase tracking-widest">No se encontraron resultados</p>
        </div>

        <div id="error-state" class="hidden py-16 text-center text-rose-500">
            <i class="fas fa-circle-exclamation text-3xl mb-3 text-rose-300"></i>
            <p class="text-xs font-bold uppercase tracking-widest" id="error-state-mensaje">Error al cargar la bandeja</p>
        </div>
    </div>

    <div id="paginacion-bandeja-container" class="sigtram-table-footer px-3 py-2 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-600 bg-slate-50/90 shrink-0">
        <div class="flex items-center gap-3">
            <button type="button" data-action="marcar-todos" class="font-bold text-slate-700 hover:underline" id="btn-marcar">Marcar Todos</button>
            <span class="text-slate-300">|</span>
            <span id="pag-rango-info" class="font-medium text-slate-600" aria-live="polite">Cargando registros...</span>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" id="btn-ocultar-historico-footer" data-action="cerrar-historico"
                    class="hidden inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold text-slate-700 hover:text-red-600 bg-white hover:bg-red-50 border border-slate-200 rounded-lg shadow-2xs transition-colors"
                    title="Ocultar histórico y expandir lista de trámites">
                <i class="fas fa-times text-[10px] text-red-500"></i>
                <span>Ocultar Histórico</span>
            </button>

            <label for="pag-tamano" class="text-slate-500 font-semibold text-[10px] uppercase tracking-wider ml-1">Filas:</label>
            <select id="pag-tamano" class="text-xs font-bold py-0.5 px-1.5 border border-slate-300 rounded-md bg-white text-slate-700 focus:ring-1 focus:ring-slate-500">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
            </select>

            <div class="inline-flex rounded-lg shadow-2xs border border-slate-200 bg-white ml-2" role="navigation" aria-label="Paginación de la bandeja">
                <button type="button" id="pag-btn-primera" data-action="pag-primera" class="px-2 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none rounded-l-lg border-r border-slate-200" title="Primera página" aria-label="Primera página">
                    <i class="fas fa-angles-left text-[10px]"></i>
                </button>
                <button type="button" id="pag-btn-anterior" data-action="pag-anterior" class="px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none border-r border-slate-200" title="Página anterior" aria-label="Página anterior">
                    <i class="fas fa-chevron-left text-[10px]"></i> Ant.
                </button>
                <span id="pag-indicador-actual" class="px-2.5 py-1 text-xs font-black text-slate-800 bg-slate-100/70 select-none">1 / 1</span>
                <button type="button" id="pag-btn-siguiente" data-action="pag-siguiente" class="px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none border-l border-slate-200" title="Página siguiente" aria-label="Página siguiente">
                    Sig. <i class="fas fa-chevron-right text-[10px]"></i>
                </button>
                <button type="button" id="pag-btn-ultima" data-action="pag-ultima" class="px-2 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-30 disabled:pointer-events-none rounded-r-lg border-l border-slate-200" title="Última página" aria-label="Última página">
                    <i class="fas fa-angles-right text-[10px]"></i>
                </button>
            </div>
        </div>
    </div>
</div>
