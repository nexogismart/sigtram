{{-- =========================================================================
     MENÚ LATERAL DE BANDEJAS — TRÁMITE EXTERNO (RF-05 / RF-07)
     ========================================================================= --}}
<aside class="sigtram-sidebar bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden self-start xl:sticky xl:top-4 flex flex-col text-sm">
    <div class="sigtram-sidebar-header bg-slate-800 text-white px-4 py-3 shrink-0">
        <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Trámite Externo</p>
        <h1 class="text-sm font-bold mt-0.5">Bandejas</h1>
    </div>
    <nav class="sigtram-sidebar-nav p-3 space-y-4">
        <div>
            <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Bandejas</p>
            <div class="space-y-0.5">
                <button type="button" @click="activeBandeja = 'todos'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="activeBandeja === 'todos' ? 'nav-active' : 'nav-idle'">
                    <span class="flex items-center gap-2"><i class="fas fa-layer-group w-4 text-center"></i> Todos</span>
                    <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="activeBandeja === 'todos' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" :title="conteosError ? 'Error al cargar totales' : ''" x-text="conteosLoading ? '—' : (conteosError ? '!' : (conteos.todos ?? 0))"></span>
                </button>
                <button type="button" @click="activeBandeja = 'entrada'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="activeBandeja === 'entrada' ? 'nav-active' : 'nav-idle'">
                    <span class="flex items-center gap-2"><i class="fas fa-inbox w-4 text-center"></i> Entrada</span>
                    <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="activeBandeja === 'entrada' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" :title="conteosError ? 'Error al cargar totales' : ''" x-text="conteosLoading ? '—' : (conteosError ? '!' : (conteos.entrada ?? 0))"></span>
                </button>
                <button type="button" @click="activeBandeja = 'recibidos'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="activeBandeja === 'recibidos' ? 'nav-active' : 'nav-idle'">
                    <span class="flex items-center gap-2"><i class="fas fa-envelope-open w-4 text-center"></i> Recibidos</span>
                    <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="activeBandeja === 'recibidos' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" :title="conteosError ? 'Error al cargar totales' : ''" x-text="conteosLoading ? '—' : (conteosError ? '!' : (conteos.recibidos ?? 0))"></span>
                </button>
                <button type="button" @click="activeBandeja = 'salida'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="activeBandeja === 'salida' ? 'nav-active' : 'nav-idle'">
                    <span class="flex items-center gap-2"><i class="fas fa-right-from-bracket w-4 text-center"></i> Salida</span>
                    <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="activeBandeja === 'salida' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" :title="conteosError ? 'Error al cargar totales' : ''" x-text="conteosLoading ? '—' : (conteosError ? '!' : (conteos.salida ?? 0))"></span>
                </button>
                <button type="button" @click="activeBandeja = 'finalizados'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="activeBandeja === 'finalizados' ? 'nav-active' : 'nav-idle'">
                    <span class="flex items-center gap-2"><i class="fas fa-flag-checkered w-4 text-center"></i> Finalizados / Notificados</span>
                    <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="activeBandeja === 'finalizados' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" :title="conteosError ? 'Error al cargar totales' : ''" x-text="conteosLoading ? '—' : (conteosError ? '!' : (conteos.finalizados ?? conteos.finalizados_notificados ?? 0))"></span>
                </button>
                <button type="button" @click="activeBandeja = 'archivo'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="activeBandeja === 'archivo' ? 'nav-active' : 'nav-idle'">
                    <span class="flex items-center gap-2"><i class="fas fa-archive w-4 text-center"></i> Archivo</span>
                    <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="activeBandeja === 'archivo' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" :title="conteosError ? 'Error al cargar totales' : ''" x-text="conteosLoading ? '—' : (conteosError ? '!' : (conteos.archivo ?? 0))"></span>
                </button>
            </div>
        </div>
        <div class="border-t border-gray-100 pt-3">
            <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Reportes</p>
            <button type="button" @click="activeBandeja = 'sin_finalizar'" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2" :class="activeBandeja === 'sin_finalizar' ? 'nav-active' : 'nav-idle'">
                <span class="flex items-center gap-2"><i class="fas fa-clock w-4 text-center"></i> Sin Finalizar</span>
                <span class="px-2 py-0.5 text-[8px] font-black rounded-full" :class="activeBandeja === 'sin_finalizar' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'" :title="conteosError ? 'Error al cargar totales' : ''" x-text="conteosLoading ? '—' : (conteosError ? '!' : (conteos.sin_finalizar ?? 0))"></span>
            </button>
        </div>

        <div class="border-t border-gray-100 pt-3">
            <a href="{{ url('/mod_gm/mod_tram_exte/index') }}"
               @click.prevent="typeof irANuevoTramite === 'function' ? irANuevoTramite() : ((window.parent && typeof window.parent.activarModuloPorUrl === 'function' && window.parent.activarModuloPorUrl('/mod_gm/mod_tram_exte/index')) || (window.location.href = '{{ url('/mod_gm/mod_tram_exte/index') }}'))"
               class="w-full text-left px-3 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider flex items-center gap-2 bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-100">
                <i class="fas fa-plus w-4 text-center"></i> Nuevo Trámite
            </a>
        </div>
    </nav>
</aside>
