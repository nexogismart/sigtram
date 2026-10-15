{{-- ========== MENÚ LATERAL CON SU BORDE, SOMBRA Y PADDING (RF-07) ========== --}}
<aside class="sigtram-sidebar bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden self-start xl:sticky xl:top-4 flex flex-col text-sm">
    <div class="sigtram-sidebar-header bg-slate-800 text-white px-4 py-3">
        <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Correo Interno</p>
        <h1 class="text-sm font-bold mt-0.5">Bandejas</h1>
    </div>

    <nav class="sigtram-sidebar-nav p-3 space-y-4">
        <div>
            <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Bandejas</p>
            <div class="space-y-0.5">
                <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="todos">
                    <span class="flex items-center gap-2"><i class="fas fa-layer-group w-4 text-center"></i> Todos</span>
                    <span class="badge-conteo px-2 py-0.5 text-[8px] font-black rounded-full bg-slate-100 text-slate-700" id="badge-conteo-todos">—</span>
                </button>
                <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2 bg-slate-800 text-white" data-bandeja="entrada" aria-current="page">
                    <span class="flex items-center gap-2"><i class="fas fa-inbox w-4 text-center"></i> Entrada</span>
                    <span class="badge-conteo px-2 py-0.5 text-[8px] font-black rounded-full bg-white/20 text-white" id="badge-conteo-entrada">—</span>
                </button>
                <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="enviados">
                    <span class="flex items-center gap-2"><i class="fas fa-paper-plane w-4 text-center"></i> Enviados</span>
                    <span class="badge-conteo px-2 py-0.5 text-[8px] font-black rounded-full bg-slate-100 text-slate-700" id="badge-conteo-enviados">—</span>
                </button>
                <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="recibidos">
                    <span class="flex items-center gap-2"><i class="fas fa-envelope-open w-4 text-center"></i> Recibidos</span>
                    <span class="badge-conteo px-2 py-0.5 text-[8px] font-black rounded-full bg-slate-100 text-slate-700" id="badge-conteo-recibidos">—</span>
                </button>
                <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="salida">
                    <span class="flex items-center gap-2"><i class="fas fa-right-from-bracket w-4 text-center"></i> Salida</span>
                    <span class="badge-conteo px-2 py-0.5 text-[8px] font-black rounded-full bg-slate-100 text-slate-700" id="badge-conteo-salida">—</span>
                </button>
                <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="finalizados">
                    <span class="flex items-center gap-2"><i class="fas fa-flag-checkered w-4 text-center"></i> Finalizados</span>
                    <span class="badge-conteo px-2 py-0.5 text-[8px] font-black rounded-full bg-slate-100 text-slate-700" id="badge-conteo-finalizados">—</span>
                </button>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-3">
            <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Reportes</p>
            <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="sin_finalizar">
                <span class="flex items-center gap-2"><i class="fas fa-clock w-4 text-center"></i> Sin Finalizar</span>
                <span class="badge-conteo px-2 py-0.5 text-[8px] font-black rounded-full bg-slate-100 text-slate-700" id="badge-conteo-sin_finalizar">—</span>
            </button>
        </div>

        @if (!empty($esAreaContabilidad))
        <div class="border-t border-gray-100 pt-3">
            <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Comprobantes</p>
            <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50" data-action="abrir-comprobantes">
                <i class="fas fa-list w-4 text-center"></i> Lista
            </button>
            <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center justify-between gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="contabilidad">
                <span class="flex items-center gap-2"><i class="fas fa-calculator w-4 text-center"></i> Contabilidad</span>
                <span class="badge-conteo px-2 py-0.5 text-[8px] font-black rounded-full bg-slate-100 text-slate-700" id="badge-conteo-contabilidad">—</span>
            </button>
        </div>
        @endif

        {{-- Los 6 videos tutoriales de segi_expedi.php siempre visibles --}}
        <div class="border-t border-gray-100 pt-3">
            <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Videos</p>
            @foreach ($videos as $video)
            <button type="button" class="btn-video-tutorial w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50 hover:text-slate-700 transition-colors" data-action="video-tutorial" data-video-url="{{ $video['url'] }}" data-video-titulo="{{ $video['titulo'] }}" data-video-existe="{{ $video['existe'] ? '1' : '0' }}">
                <i class="fas {{ $video['icono'] }} w-4 text-center"></i> {{ $video['titulo'] }}
            </button>
            @endforeach
        </div>

        <div class="border-t border-gray-100 pt-3">
            <a href="{{ route('mod_tram_inter.ingresar_tramite') }}" class="w-full text-left px-3 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider flex items-center gap-2 bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-100">
                <i class="fas fa-plus w-4 text-center"></i> Nuevo Trámite
            </a>
        </div>
    </nav>
</aside>
