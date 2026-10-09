@php
// El controlador AdministracionTicketController provee estas variables:
// $otiPersonal, $areas, $servicios, $filtros, $usuarioNombre
$ordenColumnas = [1 => 'Requerimiento', 2 => 'Pendiente', 3 => 'Proceso', 4 => 'Finalizado', 5 => 'Confirmado'];
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reporte de servicios — Administración OTI</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Scrollbar Claro */
        .custom-scrollbar-light::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar-light::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar-light::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar-light::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Para ellipsis de múltiples líneas */
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;  
            overflow: hidden;
        }
        
        /* Modales Generales */
        .modal-overlay { background-color: rgba(17, 24, 39, 0.5); backdrop-filter: blur(2px); }
        .ck-content { color: #374151 !important; }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">

<div class="p-4 sm:p-6 w-full max-w-[1600px] mx-auto flex-1 flex flex-col">
    <!-- ENCABEZADO DE SECCIÓN -->
    <div class="mb-6 flex flex-col lg:flex-row justify-between lg:items-end gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 tracking-tight"><i class="fas fa-shield-alt text-blue-600 mr-2"></i>{{ $usuarioNombre ?? 'Administración OTI' }}</h2>
            <p class="text-sm text-gray-500 mt-1 uppercase tracking-wider font-bold">Reporte General de Servicios</p>
        </div>
        
        <!-- MÉTRICAS ESTILO BADGES -->
        <div class="flex flex-wrap gap-4">
            <div class="bg-white rounded-xl p-3 px-4 border border-gray-200 shadow-sm flex items-center gap-4">
                <div class="bg-blue-50 text-blue-600 p-2.5 rounded-lg"><i class="fas fa-ticket-alt text-lg"></i></div>
                <div>
                    <div class="text-[10px] uppercase text-gray-500 font-bold tracking-wider">Total Tickets</div>
                    <div class="text-xl font-bold text-gray-800 leading-none mt-1" id="kanban-total">0</div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-3 px-4 border border-gray-200 shadow-sm flex items-center gap-4">
                <div class="bg-green-50 text-green-600 p-2.5 rounded-lg"><i class="fas fa-check-double text-lg"></i></div>
                <div>
                    <div class="text-[10px] uppercase text-gray-500 font-bold tracking-wider">Avance Resolución</div>
                    <div class="text-xl font-bold text-gray-800 leading-none mt-1"><span id="kanban-porc">0</span>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTENEDOR PRINCIPAL MODERNO -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex-1 flex flex-col overflow-hidden">
        
        <!-- BARRA DE FILTROS AVANZADA -->
        <div class="bg-gray-50/80 border-b border-gray-200 p-4 lg:p-6">
            <form id="form-filtros" class="space-y-6">
                <!-- Fila 1: Presets y Búsqueda Rápida -->
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-2" id="presets-container">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">Rangos Rápidos:</span>
                        <button type="button" onclick="setPreset('today')" class="preset-btn px-4 py-1.5 rounded-full text-xs font-bold bg-white border border-gray-200 text-gray-600 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50 transition-all shadow-sm">Hoy</button>
                        <button type="button" onclick="setPreset('7days')" class="preset-btn px-4 py-1.5 rounded-full text-xs font-bold bg-white border border-gray-200 text-gray-600 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50 transition-all shadow-sm">Últimos 7 días</button>
                        <button type="button" onclick="setPreset('month')" class="preset-btn px-4 py-1.5 rounded-full text-xs font-bold bg-white border border-gray-200 text-gray-600 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50 transition-all shadow-sm">Este Mes</button>
                        <button type="button" onclick="setPreset('all')" class="preset-btn px-4 py-1.5 rounded-full text-xs font-bold bg-white border border-gray-200 text-gray-600 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50 transition-all shadow-sm">Todo el tiempo</button>
                    </div>
                    
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="relative flex-1 sm:w-64">
                            <i class="fas fa-hashtag absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input type="text" id="search_id" placeholder="ID de Ticket (Ej: 15243)..." class="w-full pl-9 pr-4 py-2 bg-white border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none shadow-sm font-bold placeholder:font-medium placeholder:text-gray-400">
                        </div>
                    </div>
                </div>

                <!-- Fila 2: Filtros y Acciones Principales -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
                    <div class="lg:col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Fecha Inicio</label>
                        <div class="flex items-center bg-white border border-gray-300 rounded-xl px-3 py-2.5 focus-within:ring-2 focus-within:ring-blue-500 transition-all shadow-sm group">
                            <i class="far fa-calendar-alt text-gray-400 group-focus-within:text-blue-500 mr-2 transition-colors"></i>
                            <input type="date" id="fecha_inicio" max="{{ now()->format('Y-m-d') }}" value="{{ $filtros['fecha_inicio'] ?? now()->format('Y-m-d') }}" class="bg-transparent border-none p-0 focus:ring-0 text-gray-700 text-sm font-bold w-full outline-none">
                        </div>
                    </div>
                    
                    <div class="lg:col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Fecha Fin</label>
                        <div class="flex items-center bg-white border border-gray-300 rounded-xl px-3 py-2.5 focus-within:ring-2 focus-within:ring-blue-500 transition-all shadow-sm group">
                            <i class="far fa-calendar-alt text-gray-400 group-focus-within:text-blue-500 mr-2 transition-colors"></i>
                            <input type="date" id="fecha_fin" max="{{ now()->format('Y-m-d') }}" value="{{ $filtros['fecha_fin'] ?? now()->format('Y-m-d') }}" class="bg-transparent border-none p-0 focus:ring-0 text-gray-700 text-sm font-bold w-full outline-none">
                        </div>
                    </div>

                    <div class="lg:col-span-2 relative">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Personal OTI</label>
                        <select id="oti" class="w-full bg-white border border-gray-300 rounded-xl pl-3 pr-8 py-2.5 text-gray-700 text-sm font-bold focus:ring-2 focus:ring-blue-500 transition-all shadow-sm appearance-none outline-none">
                            <option value="0">-- Todos --</option>
                            @foreach ($otiPersonal as $op)
                                <option value="{{ $op->id }}" @selected(($filtros['oti'] ?? 0) == $op->id)>{{ $op->name }}</option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down absolute bottom-3.5 right-3 text-gray-400 pointer-events-none text-xs"></i>
                    </div>
                    
                    <div class="lg:col-span-2 relative">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Área Solicitante</label>
                        <select id="area" class="w-full bg-white border border-gray-300 rounded-xl pl-3 pr-8 py-2.5 text-gray-700 text-sm font-bold focus:ring-2 focus:ring-blue-500 transition-all shadow-sm appearance-none outline-none">
                            <option value="0">-- Todas --</option>
                            @foreach ($areas as $ar)
                                <option value="{{ $ar->id }}" @selected(($filtros['area'] ?? 0) == $ar->id)>{{ $ar->name }}</option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down absolute bottom-3.5 right-3 text-gray-400 pointer-events-none text-xs"></i>
                    </div>

                    <div class="lg:col-span-2 relative">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Tipo de Servicio</label>
                        <select id="servicio" class="w-full bg-white border border-gray-300 rounded-xl pl-3 pr-8 py-2.5 text-gray-700 text-sm font-bold focus:ring-2 focus:ring-blue-500 transition-all shadow-sm appearance-none outline-none">
                            <option value="0">-- Todos --</option>
                            @foreach ($servicios as $srv)
                                <option value="{{ $srv->id }}" @selected(($filtros['servicio'] ?? 0) == $srv->id)>{{ $srv->name }}</option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down absolute bottom-3.5 right-3 text-gray-400 pointer-events-none text-xs"></i>
                    </div>

                    <div class="lg:col-span-2 flex gap-2">
                        <button type="submit" id="btn-consultar" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md shadow-blue-100 transition-all flex items-center justify-center gap-2 active:scale-95 disabled:opacity-50">
                            <i class="fas fa-search" id="icon-search"></i>
                            <i class="fas fa-spinner fa-spin hidden" id="icon-loading"></i>
                            Buscar
                        </button>
                        <button type="button" id="btn-reset" class="w-12 h-10 flex items-center justify-center bg-white hover:bg-red-50 text-gray-400 hover:text-red-600 border border-gray-200 rounded-xl shadow-sm transition-all shrink-0" title="Limpiar Filtros">
                            <i class="fas fa-undo-alt text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Fila 3: Gráficos y Herramientas Secundarias -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100/50">
                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest mr-2">Estadísticas:</span>
                    <button type="button" onclick="openChartModal('chart1')" class="px-4 py-2 bg-white hover:bg-pink-50 text-gray-500 hover:text-pink-600 border border-gray-100 rounded-xl shadow-sm transition-all flex items-center gap-2 text-[10px] font-black uppercase tracking-wider" title="Estados">
                        <i class="fas fa-chart-pie"></i> Estados
                    </button>
                    <button type="button" onclick="openChartModal('chart2')" class="px-4 py-2 bg-white hover:bg-sky-50 text-gray-500 hover:text-sky-600 border border-gray-100 rounded-xl shadow-sm transition-all flex items-center gap-2 text-[10px] font-black uppercase tracking-wider" title="Rendimiento">
                        <i class="fas fa-chart-bar"></i> Rendimiento
                    </button>
                    <button type="button" onclick="openChartModal('chart3')" class="px-4 py-2 bg-white hover:bg-emerald-50 text-gray-500 hover:text-emerald-600 border border-gray-100 rounded-xl shadow-sm transition-all flex items-center gap-2 text-[10px] font-black uppercase tracking-wider" title="Servicios">
                        <i class="fas fa-list-ol"></i> Servicios
                    </button>
                </div>

                <!-- Chips de Filtros Activos -->
                <div id="active-filters" class="flex flex-wrap items-center gap-2 pt-2 empty:hidden">
                    <!-- Se llena dinámicamente -->
                </div>
            </form>
        </div>

        <!-- TABLERO KANBAN -->
        <div class="p-4 lg:p-8 bg-gray-50 flex-1 relative min-h-[500px]">
            <!-- Overlay de Carga -->
            <div id="kanban-loader" class="absolute inset-0 bg-white/60 backdrop-blur-[2px] z-20 flex items-center justify-center hidden">
                <div class="flex flex-col items-center">
                    <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mb-4 shadow-xl shadow-blue-100"></div>
                    <p class="text-sm font-black text-blue-700 uppercase tracking-widest animate-pulse">Consultando...</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 items-start" id="kanban-board">
                @php
                    $coloresColumna = [
                        1 => 'border-purple-400 text-purple-700 bg-purple-50', // Requerimiento
                        2 => 'border-red-400 text-red-700 bg-red-50',       // Pendiente
                        3 => 'border-blue-400 text-blue-700 bg-blue-50',      // Proceso
                        4 => 'border-green-400 text-green-700 bg-green-50',     // Finalizado
                        5 => 'border-teal-400 text-teal-700 bg-teal-50',      // Confirmado
                    ];
                    $coloresBordeTop = [
                        1 => 'border-t-purple-500', 
                        2 => 'border-t-red-500', 
                        3 => 'border-t-blue-500', 
                        4 => 'border-t-green-500', 
                        5 => 'border-t-teal-500'
                    ];
                @endphp
                @foreach ($ordenColumnas as $idSituacion => $titulo)
                    <div class="flex flex-col bg-gray-50/50 border border-gray-200 border-t-4 {{ $coloresBordeTop[$idSituacion] ?? 'border-t-gray-500' }} rounded-xl overflow-hidden h-[500px] md:h-[600px] xl:h-[calc(100vh-280px)] shadow-sm hover:shadow-md transition-shadow">
                        <div class="bg-white px-4 py-3 border-b border-gray-100 flex justify-between items-center z-10 shadow-sm">
                            <span class="text-gray-800 text-[11px] tracking-widest uppercase font-extrabold">{{ $titulo }}</span>
                            <span class="{{ $coloresColumna[$idSituacion] ?? 'bg-gray-100 text-gray-600' }} border rounded-full w-7 h-7 flex items-center justify-center text-[11px] font-bold" data-badge="{{ $idSituacion }}">0</span>
                        </div>
                        <div class="p-3 flex-1 overflow-y-auto flex flex-col gap-3 custom-scrollbar-light" data-lista="{{ $idSituacion }}">
                            <!-- Las tarjetas (cards) se inyectarán aquí mediante JS -->
                            <div class="text-center p-6 text-sm text-gray-400 flex flex-col items-center justify-center h-32 border-2 border-dashed border-gray-200 rounded-xl m-2 bg-white/50">
                                <i class="fas fa-spinner fa-spin fa-2x mb-3 text-blue-400/50"></i>
                                Cargando...
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODALES TAILWIND (UI BANDEJA.BLADE.PHP) -->
<!-- ========================================== -->

<!-- MODAL: DETALLE DEL TICKET -->
<div id="modalTicket" class="fixed inset-0 z-[100] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
        <div class="fixed inset-0 modal-overlay transition-opacity" aria-hidden="true" onclick="cerrarModal('modalTicket')"></div>
        <div class="relative inline-block bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full flex flex-col max-h-[95vh] border border-gray-100">
            
            <!-- BOTÓN CERRAR FLOTANTE/ESTÁTICO -->
            <div class="absolute top-6 right-6 z-50">
                <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none bg-white/80 backdrop-blur-md p-2 rounded-xl border border-gray-200 shadow-sm hover:shadow transition-all" onclick="cerrarModal('modalTicket')">
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

<!-- MODAL: DERIVAR TICKET (ESPECÍFICO) -->
<div id="modalDerivar" class="fixed inset-0 z-[110] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
        <div class="fixed inset-0 modal-overlay transition-opacity" aria-hidden="true" onclick="cerrarModal('modalDerivar')"></div>
        <div class="relative inline-block bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full border border-gray-100">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-base font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-random text-blue-500"></i> Derivar Ticket <span id="derivar-ticket-id-label"></span>
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-500" onclick="cerrarModal('modalDerivar')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <input type="hidden" id="derivar-id-ticket">
                <input type="hidden" id="derivar-situacion">
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Seleccionar Personal OTI</label>
                    <select id="derivar-destino" class="w-full bg-white border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all shadow-sm">
                        <option value="">-- Seleccione Destino --</option>
                        @foreach ($otiPersonal as $op)
                            <option value="{{ $op->id }}">{{ $op->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="flex flex-col gap-2 mt-6">
                    <button type="button" onclick="ejecutarDerivar()" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2">
                        <i class="fas fa-check"></i> Procesar Derivación
                    </button>
                    <button type="button" onclick="cerrarModal('modalDerivar')" class="w-full py-3 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold rounded-xl transition-all">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: GRÁFICOS ESTADÍSTICOS -->
<div id="modalGraficos" class="fixed inset-0 z-[100] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
        <div class="fixed inset-0 modal-overlay transition-opacity" aria-hidden="true" onclick="cerrarModal('modalGraficos')"></div>
        
        <div class="relative bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-4xl w-full flex flex-col border border-gray-200">
            
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg leading-6 font-bold text-gray-900 uppercase tracking-wider flex items-center" id="tituloGrafico">
                    Cargando Gráfico...
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-500 focus:outline-none bg-white p-1.5 rounded-lg border border-transparent hover:border-gray-200 shadow-sm transition-all" onclick="cerrarModal('modalGraficos')">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6 bg-white flex justify-center items-center" style="min-height: 500px;">
                <div id="loadingChart" class="text-center text-gray-400 w-full hidden">
                    <i class="fas fa-spinner fa-spin fa-3x mb-4"></i>
                    <p>Procesando datos...</p>
                </div>
                <div id="errorChart" class="text-center text-red-500 w-full hidden">
                    <i class="fas fa-exclamation-triangle fa-3x mb-4"></i>
                    <p id="errorChartText">Error al cargar los datos.</p>
                </div>
                
                <div class="w-full h-full relative" style="height: 450px;">
                    <canvas id="miCanvasGrafico"></canvas>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- SCRIPTS DE FUNCIONAMIENTO -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // --------------------------------------------------------
    // 1. RECOLECCIÓN DE FILTROS Y ESTADO GLOBAL
    // --------------------------------------------------------
    let chartInstance = null;
    let autoRefreshInterval = null;

    // Obtener filtros del DOM y del Storage si existen
    function getFiltros() {
        return {
            fecha_inicio: document.getElementById('fecha_inicio').value,
            fecha_fin: document.getElementById('fecha_fin').value,
            oti: document.getElementById('oti').value,
            area: document.getElementById('area').value,
            servicio: document.getElementById('servicio').value,
            search_id: document.getElementById('search_id').value || 0,
        };
    }

    // Persistencia: Guardar filtros en sessionStorage
    function saveFiltros() {
        const f = getFiltros();
        sessionStorage.setItem('helpdesk_consultas_filtros', JSON.stringify(f));
    }

    // Persistencia: Cargar filtros al iniciar
    function loadFiltros() {
        const saved = sessionStorage.getItem('helpdesk_consultas_filtros');
        if (saved) {
            const f = JSON.parse(saved);
            if (document.getElementById('fecha_inicio')) document.getElementById('fecha_inicio').value = f.fecha_inicio;
            if (document.getElementById('fecha_fin')) document.getElementById('fecha_fin').value = f.fecha_fin;
            if (document.getElementById('oti')) document.getElementById('oti').value = f.oti;
            if (document.getElementById('area')) document.getElementById('area').value = f.area;
            if (document.getElementById('servicio')) document.getElementById('servicio').value = f.servicio || '0';
            if (document.getElementById('search_id')) document.getElementById('search_id').value = f.search_id > 0 ? f.search_id : '';
        }
        updateFilterChips();
    }

    // Rangos Rápidos (Presets)
    function setPreset(type) {
        const today = new Date();
        let start = new Date();
        let end = new Date();

        switch(type) {
            case 'today':
                break;
            case '7days':
                start.setDate(today.getDate() - 7);
                break;
            case 'month':
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            case 'all':
                start = new Date(2024, 0, 1);
                break;
        }

        document.getElementById('fecha_inicio').value = start.toISOString().split('T')[0];
        document.getElementById('fecha_fin').value = end.toISOString().split('T')[0];
        
        // Reset styles for all
        document.querySelectorAll('.preset-btn').forEach(btn => {
            btn.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
            btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
        });

        // Activate current
        const btn = event.currentTarget;
        btn.classList.remove('bg-white', 'text-gray-600', 'border-gray-200');
        btn.classList.add('bg-blue-600', 'text-white', 'border-blue-600');

        saveFiltros();
        cargarKanban();
    }

    function resetPresets() {
        document.querySelectorAll('.preset-btn').forEach(btn => {
            btn.classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
            btn.classList.add('bg-white', 'text-gray-600', 'border-gray-200');
        });
    }

    // Chips de Filtros Activos
    function updateFilterChips() {
        const container = document.getElementById('active-filters');
        if (!container) return;
        const f = getFiltros();
        let html = '';

        if (f.search_id > 0) {
            html += `<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-100 text-amber-700 text-[10px] font-bold rounded-full border border-amber-200 uppercase">ID: ${f.search_id}</span>`;
        }
        if (f.oti > 0) {
            const name = document.getElementById('oti').options[document.getElementById('oti').selectedIndex].text;
            html += `<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-100 text-blue-700 text-[10px] font-bold rounded-full border border-blue-200 uppercase">OTI: ${name}</span>`;
        }
        if (f.area > 0) {
            const name = document.getElementById('area').options[document.getElementById('area').selectedIndex].text;
            html += `<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-purple-100 text-purple-700 text-[10px] font-bold rounded-full border border-purple-200 uppercase">Área: ${name}</span>`;
        }
        if (f.servicio > 0) {
            const name = document.getElementById('servicio').options[document.getElementById('servicio').selectedIndex].text;
            html += `<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full border border-emerald-200 uppercase">Servicio: ${name}</span>`;
        }

        container.innerHTML = html;
    }

    function apiPost(url, extraParams = {}) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const f = getFiltros();
        const body = { ...f, ...extraParams };

        return fetch(url, {
            method: 'POST',
            body: JSON.stringify(body),
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        }).then(r => {
            if(!r.ok) throw new Error('Error HTTP');
            return r.json();
        });
    }

    document.getElementById('form-filtros').addEventListener('submit', function(e) {
        e.preventDefault();
        saveFiltros();
        cargarKanban();
    });

    // --------------------------------------------------------
    // 2. TABLERO KANBAN (Actualización AJAX)
    // --------------------------------------------------------
    function cargarKanban() {
        const btn = document.getElementById('btn-consultar');
        const loader = document.getElementById('kanban-loader');
        const iconSearch = document.getElementById('icon-search');
        const iconLoading = document.getElementById('icon-loading');
        const board = document.getElementById('kanban-board');

        if (!btn || !loader) return;

        // Validaciones
        const fInicio = document.getElementById('fecha_inicio').value;
        const fFin = document.getElementById('fecha_fin').value;
        if (fFin < fInicio) {
            alert('La fecha de fin no puede ser anterior a la de inicio.');
            document.getElementById('fecha_fin').classList.add('border-red-500');
            return;
        } else {
            document.getElementById('fecha_fin').classList.remove('border-red-500');
        }

        // Estado de carga
        btn.disabled = true;
        loader.classList.remove('hidden');
        iconSearch.classList.add('hidden');
        iconLoading.classList.remove('hidden');

        apiPost('/mod_ti/helpdesk/consulta/kanban')
            .then(data => {
                document.getElementById('kanban-total').textContent = data.count || 0;
                document.getElementById('kanban-porc').textContent = data.avance || 0;

                const bandeja = data.bandeja || {};
                const titulos = { 1:'Requerimiento', 2:'Pendiente', 3:'Proceso', 4:'Finalizado', 5:'Confirmado' };

                Object.keys(titulos).forEach(id => {
                    const tickets = bandeja[id] || [];
                    const contenedor = document.querySelector(`[data-lista="${id}"]`);
                    const badge = document.querySelector(`[data-badge="${id}"]`);
                    
                    if (badge) badge.textContent = tickets.length;
                    
                    if (contenedor) {
                        if (tickets.length === 0) {
                            contenedor.innerHTML = `
                                <div class="flex flex-col items-center justify-center p-10 text-center bg-white/50 rounded-2xl border-2 border-dashed border-gray-200 m-2">
                                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-gray-300 mb-3 shadow-inner">
                                        <i class="fas fa-inbox text-xl"></i>
                                    </div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Sin registros</p>
                                </div>`;
                        } else {
                            contenedor.innerHTML = tickets.map(t => {
                                const isUrgente = t.prioridad && t.prioridad.toLowerCase() === 'urgente';
                                const badgeUrgente = isUrgente ? '<span class="inline-flex items-center gap-1 bg-red-100 text-red-700 text-[9px] font-black uppercase px-2 py-0.5 rounded-full border border-red-200 shadow-sm"><i class="fas fa-fire"></i> Urgente</span>' : '';
                                const borderUrgente = isUrgente ? 'border-red-200' : 'border-gray-100';
                                
                                return `
                                <div class="bg-white border ${borderUrgente} p-5 rounded-2xl shadow-[0_4px_12px_-4px_rgba(0,0,0,0.08)] hover:shadow-[0_12px_24px_-8px_rgba(0,0,0,0.15)] hover:-translate-y-1.5 transition-all cursor-pointer relative group flex flex-col gap-3" onclick="openTicketModal(${t.id})">
                                    <div class="flex justify-between items-center">
                                        <span class="text-[10px] font-mono font-black text-blue-600 bg-blue-50 px-2 py-1 rounded-lg border border-blue-100 shadow-sm">#${t.id}</span>
                                        ${badgeUrgente}
                                    </div>
                                    <div class="text-gray-900 text-[13px] font-bold leading-relaxed line-clamp-3 group-hover:text-blue-600 transition-colors">${escapeHtml(t.texto)}</div>
                                    <div class="mt-2 pt-3 flex flex-col gap-2 border-t border-gray-50">
                                        <div class="flex items-center gap-3">
                                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center text-gray-400 text-xs shrink-0 shadow-inner">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="flex flex-col min-w-0">
                                                <span class="text-[11px] font-black text-gray-700 truncate uppercase tracking-tight">${escapeHtml(t.usuario)}</span>
                                                <span class="text-[9px] text-gray-400 font-bold truncate">${escapeHtml(t.primer_nombre)}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-end">
                                            <span class="text-[9px] font-black text-gray-400 flex items-center gap-1.5 bg-gray-50/80 border border-gray-100 px-2 py-1 rounded-lg">
                                                <i class="far fa-clock text-blue-500"></i> ${escapeHtml(t.hora.split(' ')[1] || t.hora)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                `;
                            }).join('');
                        }
                    }
                });
                updateFilterChips();
            })
            .catch(err => {
                console.error(err);
                alert('Error al consultar los datos. Por favor reintente.');
            })
            .finally(() => {
                btn.disabled = false;
                loader.classList.add('hidden');
                iconSearch.classList.remove('hidden');
                iconLoading.classList.add('hidden');
            });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Inicialización y Eventos
    document.addEventListener('DOMContentLoaded', () => {
        loadFiltros();
        cargarKanban();
        
        // Auto-refresco cada 1 minuto (menos agresivo)
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
        autoRefreshInterval = setInterval(cargarKanban, 60000);
    });

    ['oti', 'area', 'servicio', 'fecha_inicio', 'fecha_fin'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => {
                if (id === 'fecha_inicio' || id === 'fecha_fin') {
                    resetPresets();
                    
                    // Bloqueo de fechas futuras y validación lógica
                    const hoy = new Date().toISOString().split('T')[0];
                    const fInicio = document.getElementById('fecha_inicio');
                    const fFin = document.getElementById('fecha_fin');

                    if (fInicio.value > hoy) fInicio.value = hoy;
                    if (fFin.value > hoy) fFin.value = hoy;
                    
                    if (fFin.value < fInicio.value) {
                        fFin.value = fInicio.value; // Forzar que fin sea al menos igual a inicio
                    }
                }
                
                saveFiltros();
                cargarKanban();
                
                // Micro-interacción: Salto de foco de fecha
                if (id === 'fecha_inicio') document.getElementById('fecha_fin').focus();
            });
        }
    });

    // Real-time con debounce aumentado (1.2s) para no ser intrusivo
    let searchTimeout;
    document.getElementById('search_id').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            saveFiltros();
            cargarKanban();
        }, 1200);
    });

    // Reset
    document.getElementById('btn-reset').addEventListener('click', () => {
        document.getElementById('form-filtros').reset();
        document.getElementById('search_id').value = '';
        ['oti', 'area', 'servicio'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '0';
        });
        resetPresets();
        sessionStorage.removeItem('helpdesk_consultas_filtros');
        cargarKanban();
    });

    // --------------------------------------------------------
    // 3. MODALES Y DETALLE DEL TICKET (AJAX)
    // --------------------------------------------------------
    function cerrarModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function openTicketModal(idTicket) {
        const modal = document.getElementById('modalTicket');
        const content = document.getElementById('modalTicketContent');
        
        content.innerHTML = `
            <div class="py-20 text-center text-gray-400">
                <i class="fas fa-spinner fa-spin fa-3x mb-4 text-blue-400"></i>
                <p class="font-medium text-sm">Consultando base de datos...</p>
            </div>`;
        modal.classList.remove('hidden');

        apiPost('/mod_ti/helpdesk/consulta/ticket-detalle', { id_ticket: idTicket })
            .then(t => {
                if(!t || !t.id || t.error) {
                    content.innerHTML = `
                        <div class="py-16 text-center text-red-500">
                            <i class="fas fa-exclamation-triangle fa-3x mb-4"></i>
                            <p class="font-bold">Ticket no encontrado</p>
                            <p class="text-sm mt-2 text-gray-500">Es posible que el ticket haya sido eliminado o no tenga permisos para verlo.</p>
                        </div>`;
                    return;
                }
                renderTicketDetail(t);
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
                <a href="/adjuntados/${t.id}.zip" download class="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-2xl transition-all group mt-6">
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
                            <button onclick="abrirDerivar(${t.id}, ${t.situacion})" class="inline-flex items-center px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-amber-50 text-amber-600 border border-amber-200 shadow-sm hover:bg-amber-100 transition-all mr-2">
                                <i class="fas fa-random mr-2"></i> Derivar
                            </button>
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



    // --------------------------------------------------------
    // 5. DERIVACIÓN DE TICKETS (Desde Administración)
    // --------------------------------------------------------
    function abrirDerivar(idTicket, situacion) {
        document.getElementById('derivar-id-ticket').value = idTicket;
        document.getElementById('derivar-situacion').value = situacion;
        document.getElementById('derivar-ticket-id-label').textContent = '#' + idTicket;
        document.getElementById('modalDerivar').classList.remove('hidden');
    }

    function ejecutarDerivar() {
        const idTicket = document.getElementById('derivar-id-ticket').value;
        const idLoginDes = document.getElementById('derivar-destino').value;
        const situacionActual = document.getElementById('derivar-situacion').value;

        if (!idLoginDes) {
            alert('Por favor, seleccione un técnico de destino.');
            return;
        }

        const fd = new URLSearchParams();
        fd.append('id_ticket', idTicket);
        fd.append('id_login_destino', idLoginDes);
        fd.append('situacion_actual', situacionActual);

        apiPost('/mod_ti/helpdesk/consulta/derivar', Object.fromEntries(fd))
            .then(data => {
                if (data.success) {
                    alert('Ticket derivado correctamente.');
                    cerrarModal('modalDerivar');
                    cerrarModal('modalTicket');
                    cargarKanban(); // Refrescar el tablero
                } else {
                    alert(data.message || 'Error al derivar el ticket.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error técnico al intentar derivar.');
            });
    }


    // --------------------------------------------------------
    // 4. CHART.JS (Gráficos Dinámicos)
    // --------------------------------------------------------
    function openChartModal(chartType) {
        const modal = document.getElementById('modalGraficos');
        const loadingDiv = document.getElementById('loadingChart');
        const errorDiv = document.getElementById('errorChart');
        const titulo = document.getElementById('tituloGrafico');
        const canvas = document.getElementById('miCanvasGrafico');

        modal.classList.remove('hidden');
        loadingDiv.classList.remove('hidden');
        errorDiv.classList.add('hidden');
        canvas.style.display = 'none';

        if (chartType === 'chart1') titulo.innerHTML = '<i class="fas fa-chart-pie text-pink-500 mr-2"></i> Tickets por Estado';
        if (chartType === 'chart2') titulo.innerHTML = '<i class="fas fa-chart-bar text-sky-500 mr-2"></i> Casos Resueltos OTI';
        if (chartType === 'chart3') titulo.innerHTML = '<i class="fas fa-align-left text-emerald-500 mr-2"></i> Tipos de Servicios (Top 10)';

        apiPost(`/mod_ti/helpdesk/consulta/${chartType}`)
            .then(data => {
                if (data.error) throw new Error(data.error);
                loadingDiv.classList.add('hidden');
                canvas.style.display = 'block';
                renderChart(chartType, data);
            })
            .catch(err => {
                loadingDiv.classList.add('hidden');
                errorDiv.classList.remove('hidden');
                document.getElementById('errorChartText').textContent = err.message || 'Error al obtener datos.';
            });
    }

    function renderChart(type, data) {
        if (chartInstance) {
            chartInstance.destroy();
        }

        const canvas = document.getElementById('miCanvasGrafico');
        const ctx = canvas.getContext('2d');
        
        // Estilos Globales Premium
        Chart.defaults.color = '#94a3b8'; // slate-400
        Chart.defaults.font.family = "'Outfit', 'Inter', sans-serif";
        Chart.defaults.font.weight = '600';

        const labels = Object.keys(data);
        const values = Object.values(data);
        const total = values.reduce((a, b) => a + Number(b), 0);

        // Paletas de Colores Modernas (Gradients)
        const blueGrad = ctx.createLinearGradient(0, 0, 0, 400);
        blueGrad.addColorStop(0, '#3b82f6');
        blueGrad.addColorStop(1, '#60a5fa');

        const emeraldGrad = ctx.createLinearGradient(0, 0, 0, 400);
        emeraldGrad.addColorStop(0, '#10b981');
        emeraldGrad.addColorStop(1, '#34d399');

        const colors = [
            '#6366f1', // Indigo
            '#f43f5e', // Rose
            '#0ea5e9', // Sky
            '#10b981', // Emerald
            '#f59e0b', // Amber
            '#8b5cf6'  // Violet
        ];

        let config = {};

        if (type === 'chart1') {
            config = {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 0,
                        hoverOffset: 20,
                        borderRadius: 10,
                        spacing: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 25, font: { size: 11 } } },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 13 },
                            cornerRadius: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const val = context.parsed;
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) + '%' : '0%';
                                    return ` ${val} tickets (${pct})`;
                                }
                            }
                        }
                    },
                    animation: { animateScale: true, animateRotate: true }
                }
            };
        }

        if (type === 'chart2' || type === 'chart3') {
            const isHorizontal = type === 'chart3';
            config = {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: type === 'chart2' ? 'Tickets Resueltos' : 'Solicitudes por Servicio',
                        data: values,
                        backgroundColor: type === 'chart2' ? blueGrad : emeraldGrad,
                        borderRadius: 8,
                        barThickness: 25,
                        maxBarThickness: 35
                    }]
                },
                options: {
                    indexAxis: isHorizontal ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            cornerRadius: 12,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: { 
                            grid: { display: false },
                            border: { display: false },
                            ticks: { font: { size: 10 } }
                        },
                        y: { 
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            border: { display: false },
                            ticks: { precision: 0, font: { size: 10 } }
                        }
                    }
                }
            };
        }

        chartInstance = new Chart(ctx, config);
    }
</script>
</body>
</html>