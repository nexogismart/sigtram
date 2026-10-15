<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bandeja OTI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-row-hover { transition: all 0.2s ease-in-out; }
        .table-row-hover:hover { transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); z-index: 10; position: relative; }
        /* Scrollbar Claro */
        .custom-scrollbar-light::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar-light::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar-light::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar-light::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .ck-content { color: #374151 !important; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_ti.helpdesk.bandeja'])
</head>
<body data-sigtram-screen="mod_ti.helpdesk.bandeja" class="sigtram-module bg-gray-50">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_ti.helpdesk.bandeja'])
<div class="p-4 sm:p-6 w-full max-w-7xl mx-auto">

    <div class="mb-4 flex justify-between items-end">
        <div>
            <h2 class="text-xl font-bold text-gray-800 tracking-tight">Gestión de Tickets OTI</h2>
            <p class="text-[9px] text-gray-400 uppercase tracking-widest font-black">Administra y da seguimiento a los requerimientos del sistema</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

        {{-- BARRA DE FILTROS (solo para OTI) --}}
        @if($esOti)
        <div class="bg-gray-50/80 border-b border-gray-200 p-3 flex flex-wrap items-center gap-3">
            <div class="relative w-full sm:w-44">
                <input type="text" id="search_id" placeholder="Buscar ID Ticket..." class="w-full bg-white border border-gray-300 rounded-xl px-3 py-2 text-gray-750 text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm placeholder:text-gray-400">
            </div>
            <div class="flex items-center bg-white border border-gray-300 rounded-xl px-3 py-2 w-40 focus-within:ring-2 focus-within:ring-blue-500 transition-all shadow-sm">
                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <input type="date" id="txtFechaInicio" value="{{ $fechaInicio }}" class="bg-transparent border-none p-0 focus:ring-0 text-gray-700 text-xs font-semibold w-full outline-none">
            </div>
            <div class="flex items-center bg-white border border-gray-300 rounded-xl px-3 py-2 w-40 focus-within:ring-2 focus-within:ring-blue-500 transition-all shadow-sm">
                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <input type="date" id="txtFechaFin" value="{{ $fechaFin }}" class="bg-transparent border-none p-0 focus:ring-0 text-gray-700 text-xs font-semibold w-full outline-none">
            </div>
            <div class="flex-1 w-full sm:w-auto sm:max-w-xs relative">
                <select id="txtArea" class="w-full bg-white border border-gray-300 rounded-xl pl-3 pr-8 py-2 text-gray-700 text-xs font-semibold focus:ring-2 focus:ring-blue-500 transition-all shadow-sm appearance-none outline-none cursor-pointer">
                    <option value="">-- Todas las Áreas --</option>
                    @foreach($areas as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
                <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto sm:ml-auto justify-end mt-2 sm:mt-0">
                <button id="btnConsultar" class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-sm flex items-center gap-2 active:scale-95">
                    <svg class="w-3.5 h-3.5 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    Consultar
                </button>
            </div>
        </div>
        @endif

        {{-- SECCIÓN SECRETARIA OTI --}}
        @if($esSecretaria && $ticketsSecretaria->count() > 0)
        <div class="border-b border-gray-200 bg-amber-50/50 p-4">
            <h3 class="text-sm font-bold text-amber-800 mb-3 uppercase tracking-wider">Bandeja Secretaría OTI — Tickets por Derivar</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-amber-100/50"><tr class="whitespace-nowrap">
                        <th class="py-2 px-3 text-amber-700 font-semibold text-xs uppercase">ID</th>
                        <th class="py-2 px-3 text-amber-700 font-semibold text-xs uppercase">Asunto</th>
                        <th class="py-2 px-3 text-amber-700 font-semibold text-xs uppercase">Detalle</th>
                        <th class="py-2 px-3 text-amber-700 font-semibold text-xs uppercase">Fecha</th>
                        <th class="py-2 px-3 text-amber-700 font-semibold text-xs uppercase">Solicitante</th>
                        <th class="py-2 px-3 text-amber-700 font-semibold text-xs uppercase text-right">Acciones</th>
                    </tr></thead>
                    <tbody class="divide-y divide-amber-100">
                        @foreach($ticketsSecretaria as $ts)
                        <tr class="hover:bg-amber-100/30">
                            <td class="py-2 px-3 whitespace-nowrap"><span class="font-mono text-xs text-amber-800">#{{ $ts->id }}</span></td>
                            <td class="py-2 px-3 text-xs text-gray-900 max-w-[200px] break-words whitespace-normal font-semibold leading-tight">{{ $ts->asunto }}</td>
                            <td class="py-2 px-3 text-xs text-gray-600 max-w-[200px] break-words whitespace-normal leading-normal">{{ $ts->detalle }}</td>
                            <td class="py-2 px-3 text-xs text-gray-700 whitespace-nowrap">{{ $ts->fecha ? \Carbon\Carbon::parse($ts->fecha)->format('d/m/Y H:i') : '-' }}</td>
                            <td class="py-2 px-3 text-xs text-gray-900 max-w-[150px] break-words whitespace-normal">
                                <span class="inline-block max-w-[140px] truncate" title="{{ $ts->solicitante }}">{{ $ts->solicitante }}</span>
                            </td>
                            <td class="py-2 px-3 text-right whitespace-nowrap">
                                <button onclick="abrirDerivar({{ $ts->id }}, {{ $ts->situacion }})"
                                    class="inline-flex items-center px-2 py-1 bg-amber-600 text-white hover:bg-amber-700 text-xs font-semibold rounded transition-colors">
                                    Derivar
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- TABLA DE DATOS PRINCIPAL --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">
                        <th class="py-2 px-3 text-left">ID Ticket</th>
                        <th class="py-2 px-3 text-left">Prioridad</th>
                        <th class="py-2 px-3 text-left">Asunto</th>
                        <th class="py-2 px-3 text-center">Estado</th>
                        <th class="py-2 px-3 text-left">Área</th>
                        <th class="py-2 px-3 text-left">Fecha / Hora</th>
                        <th class="py-2 px-3 text-left">Solicitante</th>
                        <th class="py-2 px-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white" id="tabla-oti-body">
                    @include('mod_ti.helpdesk.partials.tabla_oti_inner')
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MODAL DETALLE TICKET --}}
<div id="ticketModal" class="fixed inset-0 z-[100] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-[2px] transition-opacity" onclick="closeTicketModal()"></div>
        <div class="relative inline-block bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full flex flex-col max-h-[95vh] border border-gray-100">
            <!-- BOTÓN CERRAR FLOTANTE/ESTÁTICO -->
            <div class="absolute top-6 right-6 z-50">
                <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none bg-white/80 backdrop-blur-md p-2 rounded-xl border border-gray-200 shadow-sm hover:shadow transition-all" onclick="closeTicketModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- CONTENEDOR DE CONTENIDO (Inyectado por JS) -->
            <div id="modalTicketContent" class="overflow-y-auto flex-1 bg-white custom-scrollbar-light">
                <div class="py-32 text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin fa-3x mb-4 text-blue-500/20"></i>
                    <p class="font-medium animate-pulse">Obteniendo información...</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DERIVAR --}}
<div id="derivarModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity" onclick="cerrarDerivar()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Derivar Ticket <span id="derivar-ticket-id" class="text-blue-600"></span></h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="cerrarDerivar()">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="px-6 py-5">
                <input type="hidden" id="derivar-id-ticket" value="">
                <input type="hidden" id="derivar-situacion" value="">
                <label class="block text-sm font-medium text-gray-700 mb-2">Personal de OTI:</label>
                <select id="derivar-destino" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    @foreach($personalOti ?? [] as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                <button onclick="cerrarDerivar()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">Cancelar</button>
                <button onclick="ejecutarDerivar()" class="px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700">Derivar</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const ES_OTI = {{ $esOti ? 'true' : 'false' }};

// ===================== AJAX: Refrescar tabla =====================
function refrescarTabla() {
    if (!ES_OTI) return;
    const fi = document.getElementById('txtFechaInicio')?.value || '';
    const ff = document.getElementById('txtFechaFin')?.value || '';
    const area = document.getElementById('txtArea')?.value || '';
    const searchId = document.getElementById('search_id')?.value || '';

    const fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fd.append('fecha_inicio', fi);
    fd.append('fecha_fin', ff);
    fd.append('area', area);
    fd.append('search_id', searchId);

    fetch('{{ route("helpdesk.bandeja.tabla") }}', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(html => { document.getElementById('tabla-oti-body').innerHTML = html; })
        .catch(err => console.error('Error refrescando tabla:', err));
}

// Auto-refresh cada 5 segundos (como el código antiguo)
@if($esOti)
setInterval(refrescarTabla, 5000);
@endif

// ===================== Filtros =====================
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('btnConsultar');
    if (btn) btn.addEventListener('click', refrescarTabla);
    const area = document.getElementById('txtArea');
    if (area) area.addEventListener('change', refrescarTabla);
    const fi = document.getElementById('txtFechaInicio');
    if (fi) fi.addEventListener('change', refrescarTabla);
    const ff = document.getElementById('txtFechaFin');
    if (ff) ff.addEventListener('change', refrescarTabla);

    // Búsqueda por ID con debounce de 600ms
    const searchId = document.getElementById('search_id');
    if (searchId) {
        let searchTimeout;
        searchId.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(refrescarTabla, 600);
        });
    }
});

// ===================== Ver Detalle =====================
function verDetalleTicket(idTicket) {
    const modal = document.getElementById('ticketModal');
    const content = document.getElementById('modalTicketContent');
    
    content.innerHTML = `
        <div class="py-20 text-center text-gray-400">
            <i class="fas fa-spinner fa-spin fa-3x mb-4 text-blue-500/20"></i>
            <p class="font-medium text-sm">Consultando base de datos...</p>
        </div>`;
    modal.classList.remove('hidden');

    const fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fd.append('id_ticket', idTicket);

    fetch('{{ route("helpdesk.bandeja.ticket_detalle") }}', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); closeTicketModal(); return; }
            renderTicketDetail(data);
        })
        .catch(err => {
            console.error(err);
            content.innerHTML = `
                <div class="py-16 text-center text-red-500">
                    <i class="fas fa-times-circle fa-3x mb-4"></i>
                    <p class="font-bold">Error de conexión</p>
                    <p class="text-sm mt-2 text-gray-500">No se pudo establecer comunicación con el servidor.</p>
                </div>`;
        });
}
function closeTicketModal() { document.getElementById('ticketModal').classList.add('hidden'); }

function renderTicketDetail(t) {
    const content = document.getElementById('modalTicketContent');
    
    // Mapeo de situaciones para el diseño
    const situacionMap = {
        1: { label: 'Requerimiento', color: 'purple', icon: 'fa-paper-plane' },
        2: { label: 'Pendiente', color: 'red', icon: 'fa-clock' },
        3: { label: 'En Proceso', color: 'blue', icon: 'fa-cogs' },
        4: { label: 'Finalizado', color: 'green', icon: 'fa-check-double' },
        5: { label: 'Confirmado', color: 'teal', icon: 'fa-check-circle' }
    };
    
    const sit = situacionMap[t.situacion] || { label: 'Desconocido', color: 'gray', icon: 'fa-question-circle' };
    const isUrgente = (t.prioridad || '').toLowerCase() === 'urgente';

    // Stepper (Línea de tiempo)
    const steps = [
        { id: 1, label: 'Enviado', active: t.situacion >= 1 },
        { id: 2, label: 'En Proceso', active: t.situacion >= 3 },
        { id: 3, label: 'Finalizado', active: t.situacion >= 4 }
    ];

    let stepperHtml = '<div class="flex items-center justify-between mb-10 px-4 sm:px-12">';
    steps.forEach((s, idx) => {
        const isActive = s.active;
        const isLast = idx === steps.length - 1;
        stepperHtml += `
            <div class="flex flex-col items-center relative flex-1">
                <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all duration-500 z-10 ${isActive ? 'bg-blue-600 border-blue-600 text-white shadow-lg shadow-blue-100' : 'bg-white border-gray-200 text-gray-300'}">
                    ${isActive ? '<i class="fas fa-check text-[10px]"></i>' : `<span class="text-[10px] font-bold">${s.id}</span>`}
                </div>
                <span class="mt-2 text-[10px] font-black uppercase tracking-widest ${isActive ? 'text-blue-600' : 'text-gray-400'}">${s.label}</span>
                ${!isLast ? `<div class="absolute top-4 left-1/2 w-full h-[2px] ${steps[idx+1].active ? 'bg-blue-600' : 'bg-gray-100'} -z-0"></div>` : ''}
            </div>
        `;
    });
    stepperHtml += '</div>';

    // Botón de adjuntos
    let btnZip = '';
    if (t.has_zip) {
        btnZip = `
            <a href="${t.url_zip}" download class="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-2xl transition-all group mt-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-blue-500 shadow-sm group-hover:scale-110 transition-transform">
                        <i class="fas fa-file-archive text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-800">Archivos Adjuntos</p>
                        <p class="text-xs text-gray-500 font-medium">Paquete comprimido .ZIP</p>
                    </div>
                </div>
                <i class="fas fa-download text-gray-300 group-hover:text-blue-500 transition-colors mr-2"></i>
            </a>`;
    }

    content.innerHTML = `
        <div class="flex flex-col bg-white">
            <!-- CABECERA DEL DETALLE -->
            <div class="p-6 lg:p-10 border-b border-gray-50 bg-gradient-to-b from-gray-50/50 to-white">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-blue-600">
                            <i class="fas ${sit.icon} text-xl"></i>
                        </div>
                        <div>
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-0.5">Gestión de Requerimiento</h4>
                            <p class="text-sm font-mono font-bold text-gray-800">#${t.id} <span class="mx-2 text-gray-200">|</span> <span class="text-gray-500">${t.fecha_formateada}</span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-white border border-gray-200 shadow-sm ${isUrgente ? 'text-red-600 border-red-100' : 'text-gray-600'}">
                            <i class="fas ${isUrgente ? 'fa-bolt text-red-500' : 'fa-info-circle text-gray-400'} mr-2"></i>${isUrgente ? 'URGENTE' : 'NORMAL'}
                        </span>
                        <span class="inline-flex items-center px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-blue-600 text-white shadow-md shadow-blue-100">
                            ${sit.label}
                        </span>
                    </div>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 leading-tight">${escapeHtml(t.asunto)}</h2>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="p-6 lg:p-10 flex-1">
                ${stepperHtml}

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- COLUMNA INFO -->
                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-gray-50 rounded-3xl p-6 border border-gray-100 shadow-sm">
                            <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-5 flex items-center gap-2">
                                <i class="fas fa-id-card text-blue-400"></i> Datos del Solicitante
                            </h5>
                            <div class="space-y-5">
                                <div>
                                    <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">Usuario</label>
                                    <p class="text-sm font-bold text-gray-800">${escapeHtml(t.solicitante)}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">Área / Cargo</label>
                                    <p class="text-xs font-bold text-gray-700">${escapeHtml(t.area || '-')}</p>
                                    <p class="text-[10px] text-blue-600 font-semibold mt-0.5">${escapeHtml(t.cargo || '-')}</p>
                                </div>
                                <div class="pt-4 border-t border-gray-200/50 grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">Celular</label>
                                        <p class="text-xs font-bold text-gray-700">${escapeHtml(t.telefono || '---')}</p>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">AnyDesk</label>
                                        <p class="text-xs font-bold text-gray-700 font-mono">${escapeHtml(t.anydesk || '---')}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50/50 rounded-3xl p-6 border border-blue-100">
                            <h5 class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <i class="fas fa-tools"></i> Detalle Técnico
                            </h5>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">Servicio</label>
                                    <p class="text-xs font-bold text-gray-800">${escapeHtml(t.servicio || 'General')}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">Atendido por</label>
                                    <p class="text-xs font-bold text-blue-700">${escapeHtml(t.atendido_por || 'Pendiente de asignación')}</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">Finalización</label>
                                    <p class="text-xs font-bold text-gray-800">${t.fecha_fin || '-'}</p>
                                </div>
                            </div>
                        </div>
                        
                        ${btnZip}
                    </div>

                    <!-- COLUMNA DESCRIPCIÓN -->
                    <div class="lg:col-span-2 space-y-6">
                        ${isUrgente ? `
                        <div class="bg-red-50 p-4 rounded-2xl border border-red-100 flex items-start gap-4">
                            <div class="bg-red-100 p-2 rounded-lg text-red-600"><i class="fas fa-exclamation-triangle"></i></div>
                            <div>
                                <h4 class="text-xs font-bold text-red-800 uppercase tracking-wider">Justificación de Urgencia</h4>
                                <p class="text-sm text-red-700 mt-1 font-medium">${escapeHtml(t.prioridad_razon || 'No especificada')}</p>
                            </div>
                        </div>` : ''}

                        <div>
                            <div class="flex items-center justify-between mb-3 px-2">
                                <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                    <i class="fas fa-align-left text-gray-300"></i> Descripción del Requerimiento
                                </h5>
                            </div>
                            <div class="bg-white rounded-3xl border border-gray-200 p-6 lg:p-8 shadow-sm relative overflow-hidden min-h-[300px]">
                                <div class="prose prose-blue max-w-none text-gray-700 leading-relaxed ck-content">
                                    ${t.descripcion_html}
                                </div>
                                <div class="absolute bottom-4 right-4 opacity-[0.03] pointer-events-none select-none">
                                    <i class="fas fa-headset text-9xl text-gray-900"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===================== Confirmar (sit 2 → 3) =====================
function confirmarTicketOti(idTicket) {
    if (!confirm('¿Confirmar este ticket? Pasará a estado "En Proceso".')) return;
    const fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fd.append('id_ticket', idTicket);
    fetch('{{ route("helpdesk.bandeja.confirmar") }}', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if (data.success) refrescarTabla(); else alert(data.message || 'Error'); })
        .catch(err => { console.error(err); alert('Error al confirmar'); });
}

// ===================== Finalizar (sit 3 → 4) =====================
function finalizarTicketOti(idTicket) {
    if (!confirm('¿Finalizar este ticket?')) return;
    const fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fd.append('id_ticket', idTicket);
    fetch('{{ route("helpdesk.bandeja.finalizar") }}', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if (data.success) refrescarTabla(); else alert(data.message || 'Error'); })
        .catch(err => { console.error(err); alert('Error al finalizar'); });
}

// ===================== Derivar =====================
function abrirDerivar(idTicket, situacionActual) {
    document.getElementById('derivar-id-ticket').value = idTicket;
    document.getElementById('derivar-situacion').value = situacionActual;
    document.getElementById('derivar-ticket-id').textContent = '#' + idTicket;
    document.getElementById('derivarModal').classList.remove('hidden');
}
function cerrarDerivar() { document.getElementById('derivarModal').classList.add('hidden'); }
function ejecutarDerivar() {
    const idTicket = document.getElementById('derivar-id-ticket').value;
    const idLoginDes = document.getElementById('derivar-destino').value;
    const situacion = document.getElementById('derivar-situacion').value;
    if (!idLoginDes) { alert('Seleccione un destino'); return; }
    const fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fd.append('id_ticket', idTicket);
    fd.append('id_login_destino', idLoginDes);
    fd.append('situacion_actual', situacion);
    fetch('{{ route("helpdesk.bandeja.derivar") }}', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) { cerrarDerivar(); refrescarTabla(); if (!ES_OTI) location.reload(); }
            else alert(data.message || 'Error');
        })
        .catch(err => { console.error(err); alert('Error al derivar'); });
}
</script>
</body>
</html>