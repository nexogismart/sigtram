<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Correo Interno — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: "Figtree", "Segoe UI", Tahoma, sans-serif; }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        :focus-visible { outline: 2px solid #334155; outline-offset: 2px; }
        /* Colores de referencia / trámite como el antiguo */
        .celda-ref { background-color: #fef08a; }
        .celda-tram { background-color: #f5f5dc; }
        .fila-alt { background-color: #f8fafc; }
        .fila-hover:hover { background-color: #f8fafc !important; }
    </style>
</head>
<body class="bg-[#f8fafc] text-gray-800 min-h-screen">

<div class="p-3 sm:p-5 w-full max-w-[1600px] mx-auto">

    {{-- Modelo segi_expedi: sidebar 10% + contenido 90% --}}
    <div class="grid grid-cols-1 xl:grid-cols-[220px_1fr] gap-4 min-h-[85vh]">

        {{-- ========== MENÚ LATERAL (segi_expedi.php) ========== --}}
        <aside class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden xl:sticky xl:top-4 self-start">
            <div class="bg-slate-800 text-white px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Correo Interno</p>
                <h1 class="text-sm font-bold mt-0.5">Bandejas</h1>
            </div>

            <nav class="p-3 space-y-4">
                <div>
                    <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Bandejas</p>
                    <div class="space-y-0.5">
                        <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 bg-slate-800 text-white" data-bandeja="entrada" onclick="irBandeja('entrada', this)">
                            <i class="fas fa-inbox w-4 text-center"></i> Entrada
                        </button>
                        <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="enviados" onclick="irBandeja('enviados', this)">
                            <i class="fas fa-paper-plane w-4 text-center"></i> Enviados
                        </button>
                        <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="todos" onclick="irBandeja('todos', this)">
                            <i class="fas fa-layer-group w-4 text-center"></i> Todos
                        </button>
                        <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="recibidos" onclick="irBandeja('recibidos', this)">
                            <i class="fas fa-envelope-open w-4 text-center"></i> Recibidos
                        </button>
                        <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="salida" onclick="irBandeja('salida', this)">
                            <i class="fas fa-right-from-bracket w-4 text-center"></i> Salida
                        </button>
                        <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="finalizados" onclick="irBandeja('finalizados', this)">
                            <i class="fas fa-flag-checkered w-4 text-center"></i> Finalizados
                        </button>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-3">
                    <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Reportes</p>
                    <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="sin_finalizar" onclick="irBandeja('sin_finalizar', this)">
                        <i class="fas fa-clock w-4 text-center"></i> Sin Finalizar
                    </button>
                </div>

                @if (!empty($esAreaContabilidad))
                <div class="border-t border-gray-100 pt-3">
                    <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Comprobantes</p>
                    <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50" onclick="alert('Lista de comprobantes: pendiente de migración (ver_comprobantes_pago.php)')">
                        <i class="fas fa-list w-4 text-center"></i> Lista
                    </button>
                    <button type="button" class="nav-btn w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50" data-bandeja="contabilidad" onclick="irBandeja('contabilidad', this)">
                        <i class="fas fa-calculator w-4 text-center"></i> Contabilidad
                    </button>
                </div>
                @endif

                <div class="border-t border-gray-100 pt-3">
                    <p class="px-3 mb-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">Videos</p>
                    @foreach ($videos as $video)
                        @if ($video['existe'])
                        <button type="button" onclick="abrirVideo('{{ $video['url'] }}','{{ $video['titulo'] }}')" class="w-full text-left px-3 py-2 rounded-lg text-xs font-bold flex items-center gap-2 text-gray-600 hover:bg-slate-50 hover:text-slate-700">
                            <i class="fas {{ $video['icono'] }} w-4 text-center"></i> {{ $video['titulo'] }}
                        </button>
                        @endif
                    @endforeach
                </div>

                <div class="border-t border-gray-100 pt-3">
                    <a href="{{ route('mod_tram_inter.ingresar_tramite') }}" class="w-full text-left px-3 py-2.5 rounded-lg text-xs font-black uppercase tracking-wider flex items-center gap-2 bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-100">
                        <i class="fas fa-plus w-4 text-center"></i> Nuevo Trámite
                    </a>
                </div>
            </nav>
        </aside>

        {{-- ========== PANEL BANDEJA (como el iframe de segi_entrada) ========== --}}
        <section class="min-w-0 flex flex-col gap-3">

            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                {{-- Título de bandeja --}}
                <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between gap-3">
                    <h2 id="titulo-bandeja" class="text-sm sm:text-base font-bold uppercase tracking-wide">
                        Bandeja Trámite Interno — Entrada
                    </h2>
                    <span id="contador-registros" class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full">0 registros</span>
                </div>

                {{-- Búsqueda (fieldset del antiguo) --}}
                <div class="border-b border-gray-100 px-4 py-4 bg-gray-50/70">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Búsqueda por</p>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <div class="md:col-span-3">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Criterio</label>
                            <select id="filtro-criterio" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                                <option value="tramite">Nº de trámite</option>
                                <option value="envio">Nº de trámite Envío</option>
                                <option value="referencia">Nº de trámite Referencia</option>
                                <option value="area">Área de Inicio</option>
                                <option value="tipo">Tipo de documento</option>
                                <option value="observaciones">Observaciones</option>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Descripción</label>
                            <input type="text" id="filtro-descripcion" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none" placeholder="Texto a buscar...">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Desde</label>
                            <input type="date" id="filtro-desde" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Hasta</label>
                            <input type="date" id="filtro-hasta" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <button type="button" id="btn-buscar" onclick="consultar()" class="w-full py-2.5 bg-slate-600 hover:bg-slate-700 text-white text-[11px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all">
                                <i class="fas fa-search mr-1"></i> Consultar
                            </button>
                        </div>
                    </div>
                    <input type="hidden" id="filtro-bandeja" value="entrada">
                    <input type="hidden" id="filtro-reporte" value="todos">
                </div>

                {{-- Acciones (Recepcionar | Imprimir | faltantes) --}}
                <div id="barra-acciones" class="px-4 py-2.5 border-b border-gray-100 flex flex-wrap items-center gap-3 text-xs font-bold text-slate-700 bg-white">
                    <button type="button" onclick="accionRecepcionar()" class="inline-flex items-center gap-1.5 hover:text-slate-900" id="btn-recepcionar">
                        <i class="fas fa-check-double"></i> Recepcionar
                    </button>
                    <span class="text-gray-300">|</span>
                    <button type="button" onclick="accionImprimir()" class="inline-flex items-center gap-1.5 hover:text-slate-900">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <span class="text-gray-300" id="sep-faltantes">|</span>
                    <label id="lbl-faltantes" class="inline-flex items-center gap-2 text-red-800 cursor-pointer select-none">
                        <input type="checkbox" id="chk-faltantes" class="rounded border-gray-300 text-slate-600 focus:ring-slate-500" onchange="toggleFaltantes()">
                        Mostrar solo faltantes
                    </label>
                </div>

                {{-- Tabla --}}
                <div class="relative overflow-auto custom-scroll max-h-[480px]">
                    <div id="loader" class="absolute inset-0 bg-white/70 z-10 hidden items-center justify-center">
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-8 h-8 border-4 border-slate-600 border-t-transparent rounded-full animate-spin"></div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Consultando...</span>
                        </div>
                    </div>

                    <table class="w-full text-left text-sm border-collapse" id="tabla-bandeja">
                        <thead class="bg-slate-50 sticky top-0 z-[5]">
                            <tr id="thead-row">
                                {{-- Se regenera según bandeja --}}
                            </tr>
                        </thead>
                        <tbody id="tbody-principal"></tbody>
                    </table>

                    <div id="empty-state" class="hidden py-16 text-center text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-3 text-gray-200"></i>
                        <p class="text-xs font-bold uppercase tracking-widest">No se encontraron resultados</p>
                    </div>
                </div>

                <div class="px-4 py-2 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-500 bg-gray-50">
                    <button type="button" onclick="marcarTodos()" class="font-bold text-slate-600 hover:underline" id="btn-marcar">Todos</button>
                    <button type="button" onclick="consultar()" class="font-bold text-slate-600 hover:underline">Ver todo / Actualizar</button>
                </div>
            </div>

            {{-- Panel inferior = iframe frame02 (detalle del trámite) --}}
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden min-h-[280px]">
                <div class="bg-gray-100 px-4 py-2 border-b border-gray-200 flex items-center justify-between">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                        <i class="fas fa-file-lines mr-1 text-slate-600"></i>
                        Detalle del trámite
                        <span id="detalle-titulo" class="text-slate-700 ml-2"></span>
                    </p>
                    <button type="button" onclick="limpiarDetalle()" class="text-gray-400 hover:text-red-500 text-xs"><i class="fas fa-times"></i></button>
                </div>
                <div id="panel-detalle" class="p-4 max-h-[320px] overflow-auto custom-scroll">
                    <p class="text-xs text-gray-400 italic text-center py-10">Seleccione un trámite de la lista para ver su detalle / historial.</p>
                </div>
            </div>
        </section>
    </div>
</div>

{{-- Modal video --}}
<div id="modal-video" class="fixed inset-0 z-[300] hidden">
    <div class="absolute inset-0 bg-gray-900/60" onclick="cerrarVideo()"></div>
    <div class="relative mx-auto mt-[8vh] w-[95%] max-w-2xl bg-white rounded-2xl overflow-hidden shadow-2xl">
        <div class="px-4 py-3 bg-slate-800 text-white flex justify-between items-center">
            <h3 id="modal-video-titulo" class="text-sm font-bold">Video</h3>
            <button type="button" onclick="cerrarVideo()" class="hover:opacity-80"><i class="fas fa-times"></i></button>
        </div>
        <div class="bg-black aspect-video">
            <video id="modal-video-player" class="w-full h-full" controls></video>
        </div>
    </div>
</div>

{{-- Modal Recepcionar --}}
<div id="modal-recepcionar" class="fixed inset-0 z-[300] hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="cerrarModalRecepcionar()"></div>
    <div class="relative w-full max-w-lg bg-white rounded-2xl overflow-hidden shadow-2xl border border-gray-100 z-10">
        <div class="px-6 py-4 bg-slate-800 text-white flex justify-between items-center">
            <h3 class="text-xs font-black uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-check-double text-emerald-400"></i> Recepcionar Trámites
            </h3>
            <button type="button" onclick="cerrarModalRecepcionar()" class="text-gray-300 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-xs text-gray-600">
                Va a recepcionar <span id="recep-cant" class="font-bold text-slate-800">1</span> trámite(s) seleccionado(s).
            </p>
            <div>
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Fecha de Recepción</label>
                <input type="datetime-local" id="recep-fecha" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1.5">Observación / Proveído</label>
                <textarea id="recep-obs" rows="3" class="w-full bg-gray-50 border border-gray-300 rounded-xl p-3 text-xs outline-none focus:ring-2 focus:ring-slate-500">SIN NOVEDADES PARA RECEPCIONAR</textarea>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
            <button type="button" onclick="cerrarModalRecepcionar()" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-100 transition-colors">Cancelar</button>
            <button type="button" id="btn-submit-recep" onclick="submitRecepcionar()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md transition-colors flex items-center gap-1.5">
                <i class="fas fa-check"></i> Confirmar Recepción
            </button>
        </div>
    </div>
</div>

<script>
const API = {
    tabla: "{{ route('mod_tram_inter.correo_interno.tabla') }}",
    historial: "{{ route('mod_tram_inter.correo_interno.historial') }}",
    recepcionar: "{{ route('mod_tram_inter.correo_interno.recepcionar') }}"
};
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

const TITULOS = {
    entrada: 'Entrada',
    enviados: 'Enviados',
    recibidos: 'Recibidos',
    salida: 'Salida',
    finalizados: 'Finalizados',
    todos: 'Todos',
    contabilidad: 'Contabilidad',
    sin_finalizar: 'Sin Finalizar'
};

let datosActuales = [];

document.addEventListener('DOMContentLoaded', () => {
    // Como el antiguo: fechas vacías = sin filtrar por fecha (no forzar "hoy")
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('filtro-desde').max = hoy;
    document.getElementById('filtro-hasta').max = hoy;
    renderThead('entrada');
    consultar();
});

function activarNav(btn) {
    document.querySelectorAll('.nav-btn').forEach(b => {
        b.classList.remove('bg-slate-600', 'text-white');
        b.classList.add('text-gray-600');
    });
    if (btn) {
        btn.classList.add('bg-slate-600', 'text-white');
        btn.classList.remove('text-gray-600');
    }
}

function irBandeja(clave, btn) {
    activarNav(btn);
    const esFaltantesUi = false;
    document.getElementById('filtro-bandeja').value = clave;
    document.getElementById('filtro-reporte').value = 'todos';
    document.getElementById('chk-faltantes').checked = false;
    document.getElementById('titulo-bandeja').textContent = 'Bandeja Trámite Interno — ' + (TITULOS[clave] || clave);

    // Acciones según bandeja (como el antiguo)
    const esEntrada = clave === 'entrada';
    document.getElementById('btn-recepcionar').style.display = esEntrada ? '' : 'none';
    document.getElementById('lbl-faltantes').style.display = esEntrada ? '' : 'none';
    document.getElementById('sep-faltantes').style.display = esEntrada ? '' : 'none';

    renderThead(clave);
    limpiarDetalle();
    consultar();
}

function toggleFaltantes() {
    const on = document.getElementById('chk-faltantes').checked;
    document.getElementById('filtro-bandeja').value = 'entrada';
    document.getElementById('filtro-reporte').value = on ? 'sin_finalizar' : 'todos';
    document.getElementById('titulo-bandeja').textContent = on
        ? 'Bandeja Trámite Interno — Entrada (solo faltantes)'
        : 'Bandeja Trámite Interno — Entrada';
    consultar();
}

function renderThead(bandeja) {
    const row = document.getElementById('thead-row');
    // Entrada: modelo segi_entrada
    if (bandeja === 'entrada') {
        row.innerHTML = `
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 w-10"></th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Trámite</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center whitespace-nowrap">F. Entrada</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Trámite Refer.</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Observaciones</th>
            <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-10">A</th>
            <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-10">D</th>`;
        return;
    }
    if (bandeja === 'enviados') {
        row.innerHTML = `
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 w-10"></th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Tipo Tram.</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Nº Tram.</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Área</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center">F. Envío</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Observaciones</th>
            <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center">Usua</th>
            <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-10">A</th>`;
        return;
    }
    if (bandeja === 'todos') {
        row.innerHTML = `
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 w-10"></th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Tipo Tram.</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Nº Tram.</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Envia</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center">F. Entrada</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Observaciones</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Estado</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Cargo</th>
            <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-10">A</th>
            <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-10">D</th>`;
        return;
    }
    if (bandeja === 'sin_finalizar' || bandeja === 'contabilidad') {
        row.innerHTML = `
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 w-10"></th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Tipo Tram.</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Nº Tram.</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Área Envia</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center">F. Entrada</th>
            <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Observaciones</th>
            ${bandeja === 'sin_finalizar' ? '<th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Estado</th>' : ''}
            <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-10">A</th>
            <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-10">D</th>`;
        return;
    }
    // recibidos / salida / finalizados
    row.innerHTML = `
        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 w-10"></th>
        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Trámite</th>
        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center">F. Registro</th>
        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Observaciones</th>
        <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-10">A</th>
        <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-10">D</th>`;
}

async function consultar() {
    const loader = document.getElementById('loader');
    const tbody = document.getElementById('tbody-principal');
    const empty = document.getElementById('empty-state');
    loader.classList.remove('hidden');
    loader.classList.add('flex');
    tbody.innerHTML = '';
    empty.classList.add('hidden');

    const params = {
        criterio: document.getElementById('filtro-criterio').value,
        descripcion: document.getElementById('filtro-descripcion').value,
        bandeja: document.getElementById('filtro-bandeja').value,
        reporte: document.getElementById('filtro-reporte').value,
        faltantes: document.getElementById('chk-faltantes').checked ? 1 : 0,
        desde: document.getElementById('filtro-desde').value,
        hasta: document.getElementById('filtro-hasta').value
    };

    try {
        const res = await fetch(API.tabla, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(params)
        });
        const json = await res.json();
        datosActuales = (json.status === 'success' && json.data) ? json.data : [];
        document.getElementById('contador-registros').textContent = datosActuales.length + ' registros';

        if (!datosActuales.length) {
            empty.classList.remove('hidden');
        } else {
            renderFilas(params.bandeja, datosActuales);
        }
    } catch (e) {
        console.error(e);
        empty.classList.remove('hidden');
        document.getElementById('contador-registros').textContent = '0 registros';
    } finally {
        loader.classList.add('hidden');
        loader.classList.remove('flex');
    }
}

function pad8(n) {
    return String(n ?? '').padStart(8, '0');
}

function fmtFecha(f) {
    if (!f) return '-';
    try {
        return new Date(f).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) { return f; }
}

function textoTramite(item) {
    const tipo = item.dc_tpo_trmte || '';
    const num = pad8(item.nu_tram_todo);
    const area = String(item.dc_area || '').replace(/\./g, '');
    return (tipo + ' ' + num + (area ? '-' + area : '')).trim();
}

function textoReferencia(item) {
    if (item.dc_tpo_trmte_ref && item.nu_tram_todo_ref) {
        const area = String(item.dc_area_ref || '').replace(/\./g, '');
        return item.dc_tpo_trmte_ref + ' ' + pad8(item.nu_tram_todo_ref) + (area ? '-' + area : '');
    }
    if (item.nu_tram_todo_rfncia) return item.nu_tram_todo_rfncia;
    return '';
}

function titleRef(item) {
    if (item.de_tpo_trmte_ref) {
        return (item.de_tpo_trmte_ref + ' ' + (item.nu_tram_todo_ref || '') + ' ' + (item.de_area_ref || '')).trim();
    }
    return '';
}

function titleTram(item) {
    return ((item.de_tpo_trmte || item.dc_tpo_trmte || '') + ' ' + (item.nu_tram_todo || '') + ' ' + (item.de_area || '')).trim();
}

function renderFilas(bandeja, items) {
    const tbody = document.getElementById('tbody-principal');

    if (bandeja === 'entrada') {
        tbody.innerHTML = items.map((item, i) => {
            const ref = textoReferencia(item);
            const tram = textoTramite(item);
            const fecha = fmtFecha(item.fe_ingrso_ref || item.fe_ingrso);
            const obs = (item.de_obser || '').substring(0, 62);
            const adj = item.de_urls_adjnto_ref || item.de_urls_adjnto || '';
            const urgente = String(item.in_prrdad_trmte) === '0' || String(item.in_prrdad_trmte) === '2';
            const alt = i % 2 === 1 ? 'fila-alt' : 'bg-white';

            let colTram = '';
            let colRef = '';
            if (ref) {
                colTram = `<td class="px-3 py-2 celda-ref text-[12px] font-bold" title="${esc(titleRef(item))}">${esc(ref)}</td>`;
                colRef = `<td class="px-3 py-2 celda-tram text-[12px] font-bold" title="${esc(titleTram(item))}">${esc(tram)}</td>`;
            } else {
                colTram = `<td class="px-3 py-2 celda-tram text-[12px] font-bold" title="${esc(titleTram(item))}">${esc(tram)}</td>`;
                colRef = `<td class="px-3 py-2 text-[12px]"></td>`;
            }

            return `<tr class="${alt} fila-hover cursor-pointer border-b border-gray-100" onclick="verDetalle('${item.cdgo_dtos_ntrnos}', '${esc(item.nu_tram_todo)}')">
                <td class="px-3 py-2" onclick="event.stopPropagation()">
                    <input type="checkbox" class="chk-tramite rounded border-gray-300 text-slate-600" value="${item.cdgo_dtos_ntrnos}" data-tipo="${item.cdgo_tpo_trmte || ''}" data-tramite="${item.nu_tram_todo || ''}">
                </td>
                ${colTram}
                <td class="px-3 py-2 text-center text-[11px] font-semibold text-gray-600 whitespace-nowrap">${fecha}</td>
                ${colRef}
                <td class="px-3 py-2 text-[11px] text-gray-600" title="${esc(item.de_obser || '')}">${esc(obs) || '<span class="text-gray-300">—</span>'}</td>
                <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                    ${adj ? `<button type="button" onclick="verAdjunto('${esc(adj)}')" class="text-slate-600 hover:text-slate-800" title="Adjunto"><i class="fas fa-paperclip"></i></button>` : ''}
                </td>
                <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                    <button type="button" onclick="verDetalle('${item.cdgo_dtos_ntrnos}', '${esc(item.nu_tram_todo)}')" class="${urgente ? 'text-red-600' : 'text-slate-600'} hover:opacity-80" title="${urgente ? 'Urgente' : 'Normal'}">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
        return;
    }

    if (bandeja === 'enviados') {
        tbody.innerHTML = items.map((item, i) => {
            const alt = i % 2 === 1 ? 'fila-alt' : 'bg-white';
            return `<tr class="${alt} fila-hover cursor-pointer border-b border-gray-100" onclick="verDetalle('${item.cdgo_dtos_ntrnos}', '${esc(item.nu_tram_todo)}')">
                <td class="px-3 py-2" onclick="event.stopPropagation()">
                    <input type="checkbox" class="chk-tramite rounded border-gray-300 text-slate-600" value="${item.cdgo_dtos_ntrnos}">
                </td>
                <td class="px-3 py-2 text-[12px] font-bold">${esc(item.dc_tpo_trmte || '-')}</td>
                <td class="px-3 py-2 text-[12px] font-mono font-bold text-slate-700">${pad8(item.nu_tram_todo)}</td>
                <td class="px-3 py-2 text-[11px]">${esc(item.dc_area || item.de_area || '-')}</td>
                <td class="px-3 py-2 text-center text-[11px] whitespace-nowrap">${fmtFecha(item.fe_ingrso)}</td>
                <td class="px-3 py-2 text-[11px] text-gray-600" title="${esc(item.de_obser || '')}">${esc((item.de_obser || '').substring(0, 62))}</td>
                <td class="px-2 py-2 text-center text-[10px] font-bold">${esc((item.no_crto || '').split(' ')[0] || '-')}</td>
                <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                    ${item.de_urls_adjnto ? `<button type="button" onclick="verAdjunto('${esc(item.de_urls_adjnto)}')" class="text-slate-600"><i class="fas fa-paperclip"></i></button>` : ''}
                </td>
            </tr>`;
        }).join('');
        return;
    }

    if (bandeja === 'todos') {
        tbody.innerHTML = items.map((item, i) => {
            const alt = i % 2 === 1 ? 'fila-alt' : 'bg-white';
            return `<tr class="${alt} fila-hover cursor-pointer border-b border-gray-100" onclick="verDetalle('${item.cdgo_dtos_ntrnos}', '${esc(item.nu_tram_todo)}')">
                <td class="px-3 py-2" onclick="event.stopPropagation()"><input type="checkbox" class="chk-tramite rounded border-gray-300 text-slate-600" value="${item.cdgo_dtos_ntrnos}"></td>
                <td class="px-3 py-2 text-[12px] font-bold">${esc(item.dc_tpo_trmte || '-')}</td>
                <td class="px-3 py-2 text-[12px] font-mono font-bold text-slate-700">${pad8(item.nu_tram_todo)}</td>
                <td class="px-3 py-2 text-[11px]">${esc(item.dc_area || item.de_area || '-')}</td>
                <td class="px-3 py-2 text-center text-[11px] whitespace-nowrap">${fmtFecha(item.fe_rcbdo || item.fe_ingrso)}</td>
                <td class="px-3 py-2 text-[11px] text-gray-600" title="${esc(item.de_obser || '')}">${esc((item.de_obser || '').substring(0, 62))}</td>
                <td class="px-3 py-2 text-[11px] font-semibold">${esc(item.dc_estdo_trmte || '-')}</td>
                <td class="px-3 py-2 text-[11px]">${esc(item.de_crgo || '-')}</td>
                <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                    ${item.de_urls_adjnto ? `<button type="button" onclick="verAdjunto('${esc(item.de_urls_adjnto)}')" class="text-slate-600"><i class="fas fa-paperclip"></i></button>` : ''}
                </td>
                <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                    <button type="button" onclick="verDetalle('${item.cdgo_dtos_ntrnos}', '${esc(item.nu_tram_todo)}')" class="text-slate-600"><i class="fas fa-eye"></i></button>
                </td>
            </tr>`;
        }).join('');
        return;
    }

    if (bandeja === 'sin_finalizar' || bandeja === 'contabilidad') {
        tbody.innerHTML = items.map((item, i) => {
            const alt = i % 2 === 1 ? 'fila-alt' : 'bg-white';
            return `<tr class="${alt} fila-hover cursor-pointer border-b border-gray-100" onclick="verDetalle('${item.cdgo_dtos_ntrnos}', '${esc(item.nu_tram_todo)}')">
                <td class="px-3 py-2" onclick="event.stopPropagation()"><input type="checkbox" class="chk-tramite rounded border-gray-300 text-slate-600" value="${item.cdgo_dtos_ntrnos}"></td>
                <td class="px-3 py-2 text-[12px] font-bold">${esc(item.dc_tpo_trmte || '-')}</td>
                <td class="px-3 py-2 text-[12px] font-mono font-bold text-slate-700">${pad8(item.nu_tram_todo)}</td>
                <td class="px-3 py-2 text-[11px]">${esc(item.dc_area || item.de_area || '-')}</td>
                <td class="px-3 py-2 text-center text-[11px] whitespace-nowrap">${fmtFecha(item.fe_ingrso || item.fe_rcbdo)}</td>
                <td class="px-3 py-2 text-[11px] text-gray-600" title="${esc(item.de_obser || '')}">${esc((item.de_obser || '').substring(0, 62))}</td>
                ${bandeja === 'sin_finalizar' ? `<td class="px-3 py-2 text-[11px] font-semibold">${esc(item.dc_estdo_trmte || '-')}</td>` : ''}
                <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                    ${item.de_urls_adjnto ? `<button type="button" onclick="verAdjunto('${esc(item.de_urls_adjnto)}')" class="text-slate-600"><i class="fas fa-paperclip"></i></button>` : ''}
                </td>
                <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                    <button type="button" onclick="verDetalle('${item.cdgo_dtos_ntrnos}', '${esc(item.nu_tram_todo)}')" class="text-slate-600"><i class="fas fa-eye"></i></button>
                </td>
            </tr>`;
        }).join('');
        return;
    }

    // recibidos / salida / finalizados
    tbody.innerHTML = items.map((item, i) => {
        const alt = i % 2 === 1 ? 'fila-alt' : 'bg-white';
        const urgente = String(item.in_prrdad_trmte) === '0' || String(item.in_prrdad_trmte) === '2';
        return `<tr class="${alt} fila-hover cursor-pointer border-b border-gray-100" onclick="verDetalle('${item.cdgo_dtos_ntrnos}', '${esc(item.nu_tram_todo)}')">
            <td class="px-3 py-2" onclick="event.stopPropagation()">
                <input type="checkbox" class="chk-tramite rounded border-gray-300 text-slate-600" value="${item.cdgo_dtos_ntrnos}">
            </td>
            <td class="px-3 py-2 celda-tram text-[12px] font-bold" title="${esc(titleTram(item))}">${esc(textoTramite(item))}</td>
            <td class="px-3 py-2 text-center text-[11px] whitespace-nowrap">${fmtFecha(item.fe_ingrso || item.fe_rcbdo)}</td>
            <td class="px-3 py-2 text-[11px] text-gray-600" title="${esc(item.de_obser || '')}">${esc((item.de_obser || '').substring(0, 62))}</td>
            <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                ${item.de_urls_adjnto ? `<button type="button" onclick="verAdjunto('${esc(item.de_urls_adjnto)}')" class="text-slate-600"><i class="fas fa-paperclip"></i></button>` : ''}
            </td>
            <td class="px-2 py-2 text-center" onclick="event.stopPropagation()">
                <button type="button" onclick="verDetalle('${item.cdgo_dtos_ntrnos}', '${esc(item.nu_tram_todo)}')" class="${urgente ? 'text-red-600' : 'text-slate-600'}" title="${urgente ? 'Urgente' : 'Normal'}">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

function esc(t) {
    if (t === null || t === undefined) return '';
    return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function marcarTodos() {
    const checks = document.querySelectorAll('.chk-tramite');
    const allOn = Array.from(checks).every(c => c.checked);
    checks.forEach(c => { c.checked = !allOn; });
    document.getElementById('btn-marcar').textContent = allOn ? 'Todos' : 'Ninguno';
}

function seleccionados() {
    return Array.from(document.querySelectorAll('.chk-tramite:checked')).map(c => c.value);
}

function ahoraISO() {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    const local = new Date(now.getTime() - offset);
    return local.toISOString().slice(0, 16);
}

function accionRecepcionar() {
    const ids = seleccionados();
    if (!ids.length) {
        alert('Debe seleccionar al menos un trámite');
        return;
    }
    document.getElementById('recep-cant').textContent = ids.length;
    document.getElementById('recep-fecha').value = ahoraISO();
    document.getElementById('recep-obs').value = 'SIN NOVEDADES PARA RECEPCIONAR';
    document.getElementById('modal-recepcionar').classList.remove('hidden');
}

function cerrarModalRecepcionar() {
    document.getElementById('modal-recepcionar').classList.add('hidden');
}

async function submitRecepcionar() {
    const ids = seleccionados();
    if (!ids.length) {
        alert('Debe seleccionar al menos un trámite');
        cerrarModalRecepcionar();
        return;
    }

    const fecha = document.getElementById('recep-fecha').value;
    const observaciones = document.getElementById('recep-obs').value;
    const btn = document.getElementById('btn-submit-recep');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Recepcionando...';

    try {
        const res = await fetch(API.recepcionar, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify({ ids, fecha, observaciones })
        });
        const json = await res.json();

        if (json.status === 'success') {
            alert(json.message || 'Trámite(s) recepcionado(s) correctamente.');
            cerrarModalRecepcionar();
            consultar();
            limpiarDetalle();
        } else {
            alert(json.message || 'Error al recepcionar trámite(s).');
        }
    } catch (err) {
        console.error(err);
        alert('Error de conexión al procesar la recepción.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmar Recepción';
    }
}

function accionImprimir() {
    alert('Imprimir bandeja: equivalente a impr_bandeja.php (pendiente de migración). Use la consulta actual para revisar los registros.');
}

async function verDetalle(id, numero) {
    const panel = document.getElementById('panel-detalle');
    document.getElementById('detalle-titulo').textContent = 'Nº ' + (numero || id);
    panel.innerHTML = '<p class="text-center text-xs text-slate-600 py-8"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando historial...</p>';

    try {
        const res = await fetch(API.historial, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        const items = (json.status === 'success' && json.data) ? json.data : [];

        if (!items.length) {
            panel.innerHTML = '<p class="text-center text-xs text-gray-400 py-8">Sin movimientos históricos.</p>';
            return;
        }

        panel.innerHTML = `
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700">Fecha Mov.</th>
                        <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700">Origen</th>
                        <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700 text-center">Estado</th>
                        <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700">Destino</th>
                        <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700">Observación</th>
                        <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700 text-center">Doc</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map(it => `
                        <tr class="border-b border-gray-100 hover:bg-slate-50/40">
                            <td class="px-3 py-2 whitespace-nowrap font-semibold">${fmtFecha(it.fe_rcbdo)}</td>
                            <td class="px-3 py-2"><div class="font-bold">${esc(it.dc_area || '-')}</div><div class="text-gray-400">${esc(it.de_area || '')}</div></td>
                            <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-slate-50 text-slate-700 border border-slate-100 font-black uppercase text-[9px]">${esc(it.dc_estdo_trmte || '-')}</span></td>
                            <td class="px-3 py-2"><div class="font-bold">${esc(it.dc_area_1 || '-')}</div><div class="text-gray-400">${esc(it.de_area_1 || '')}</div></td>
                            <td class="px-3 py-2 text-gray-600 italic">${esc(it.de_obser_ntrnos || '-')}</td>
                            <td class="px-3 py-2 text-center">${it.de_urls_agrdo ? `<button type="button" onclick="verAdjunto('${esc(it.de_urls_agrdo)}')" class="text-slate-600"><i class="fas fa-download"></i></button>` : '-'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>`;
    } catch (e) {
        console.error(e);
        panel.innerHTML = '<p class="text-center text-xs text-red-500 py-8">Error al cargar el detalle.</p>';
    }
}

function limpiarDetalle() {
    document.getElementById('detalle-titulo').textContent = '';
    document.getElementById('panel-detalle').innerHTML = '<p class="text-xs text-gray-400 italic text-center py-10">Seleccione un trámite de la lista para ver su detalle / historial.</p>';
}

function verAdjunto(url) {
    if (!url) return;
    // Soporta rutas nuevas (documentos/internos/...) y nombres legacy (priXXX.zip)
    const esRutaNueva = String(url).includes('/');
    const qs = esRutaNueva
        ? 'path=' + encodeURIComponent(url)
        : 'nom_archivo=' + encodeURIComponent(url);
    window.open('/mod_gene/mod_tram_inter/descargar?' + qs, '_blank');
}

function abrirVideo(url, titulo) {
    document.getElementById('modal-video-titulo').textContent = titulo;
    const player = document.getElementById('modal-video-player');
    player.src = url;
    document.getElementById('modal-video').classList.remove('hidden');
    player.play().catch(() => {});
}

function cerrarVideo() {
    const player = document.getElementById('modal-video-player');
    player.pause();
    player.removeAttribute('src');
    player.load();
    document.getElementById('modal-video').classList.add('hidden');
}

document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') { 
        cerrarVideo(); 
        cerrarModalRecepcionar(); 
    } 
});
</script>
</body>
</html>
