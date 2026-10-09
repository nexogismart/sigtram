<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Acceso a la Información Pública — SIGTRAM</title>
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    
    <!-- Alpine JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        
        /* Foco Accesible Global */
        :focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        /* Scrollbar Estilo */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Micro-interacciones */
        .hover-card {
            transition: all 0.3s ease;
        }
        .hover-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                color: black !important;
            }
            .print-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans min-h-screen flex flex-col">

    <main class="p-4 sm:p-6 w-full max-w-[1600px] mx-auto min-h-screen flex flex-col print-container">
        
        <!-- BREADCRUMBS -->
        <nav class="mb-5 flex no-print" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-slate-400">
                <li><a href="/inicio" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li>OAC</li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600">Acceso a la Información</li>
            </ol>
        </nav>

        @if (!$ver_detalle)
            <!-- ========================================== -->
            <!--            LISTADO DE EXPEDIENTES          -->
            <!-- ========================================== -->
            
            <!-- ENCABEZADO Y ESTADÍSTICAS -->
            <header class="mb-6 flex flex-col lg:flex-row justify-between lg:items-end gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center text-white shadow-lg shrink-0">
                        <i class="fas fa-folder-open text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Expedientes - Acceso a la Información Pública</h1>
                        <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-widest font-black">Módulo de control de transparencia y solicitudes ciudadanas</p>
                    </div>
                </div>

                <!-- METRICAS DE EXPEDIENTES -->
                <div class="flex flex-wrap items-center gap-4 no-print">
                    <div class="bg-white rounded-xl p-3 px-4 border border-slate-200 shadow-sm flex items-center gap-4 hover-card min-w-[140px]">
                        <div class="bg-slate-100 text-slate-700 p-2 rounded-lg"><i class="fas fa-list text-md"></i></div>
                        <div>
                            <div class="text-[9px] uppercase text-slate-500 font-bold tracking-wider">Total</div>
                            <div class="text-lg font-bold text-slate-800 leading-none mt-1">{{ $stats['total'] }}</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-3 px-4 border border-slate-200 shadow-sm flex items-center gap-4 hover-card min-w-[140px]">
                        <div class="bg-yellow-50 text-yellow-600 p-2 rounded-lg"><i class="fas fa-clock text-md"></i></div>
                        <div>
                            <div class="text-[9px] uppercase text-slate-500 font-bold tracking-wider">Pendientes</div>
                            <div class="text-lg font-bold text-slate-800 leading-none mt-1">{{ $stats['pendientes'] }}</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-3 px-4 border border-slate-200 shadow-sm flex items-center gap-4 hover-card min-w-[140px]">
                        <div class="bg-blue-50 text-blue-600 p-2 rounded-lg"><i class="fas fa-sync text-md animate-spin-slow"></i></div>
                        <div>
                            <div class="text-[9px] uppercase text-slate-500 font-bold tracking-wider">En Proceso</div>
                            <div class="text-lg font-bold text-slate-800 leading-none mt-1">{{ $stats['en_proceso'] }}</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-3 px-4 border border-slate-200 shadow-sm flex items-center gap-4 hover-card min-w-[140px]">
                        <div class="bg-emerald-50 text-emerald-600 p-2 rounded-lg"><i class="fas fa-check text-md"></i></div>
                        <div>
                            <div class="text-[9px] uppercase text-slate-500 font-bold tracking-wider">Resueltas</div>
                            <div class="text-lg font-bold text-slate-800 leading-none mt-1">{{ $stats['resueltas'] }}</div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- FILTROS DE BÚSQUEDA -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6 no-print">
                <form method="GET" action="{{ route('oac.acceso_info') }}" id="filtrosForm">
                    <!-- Selector de Tipo de Expediente -->
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl mb-5">
                        <label class="font-bold text-xs mb-3 block text-slate-700 uppercase tracking-wider">
                            <i class="fas fa-filter text-blue-500 mr-1.5"></i> Filtrar por Tipo de Expediente:
                        </label>
                        <div class="flex flex-col sm:flex-row gap-6 sm:items-center">
                            <label class="flex items-center cursor-pointer font-medium text-sm text-slate-600">
                                <input type="radio" name="tipo_expediente" value="todos" 
                                       {{ $filtro_tipo_expediente === 'todos' ? 'checked' : '' }}
                                       onchange="this.form.submit();"
                                       class="mr-2.5 w-4.5 h-4.5 text-blue-600 border-slate-300 focus:ring-blue-500">
                                <span>Mostrar todos los expedientes</span>
                            </label>
                            <label class="flex items-center cursor-pointer font-medium text-sm text-slate-600">
                                <input type="radio" name="tipo_expediente" value="acceso_informacion" 
                                       {{ $filtro_tipo_expediente === 'acceso_informacion' ? 'checked' : '' }}
                                       onchange="this.form.submit();"
                                       class="mr-2.5 w-4.5 h-4.5 text-blue-600 border-slate-300 focus:ring-blue-500">
                                <span>Solo expedientes de acceso a la información</span>
                            </label>
                        </div>
                        <small class="text-slate-400 mt-2 block text-xs">
                            @if ($filtro_tipo_expediente === 'acceso_informacion')
                                Mostrando solo expedientes de tipo: <strong class="text-blue-600">ACCESO A LA INFORMACION PUBLICA</strong>
                            @else
                                Mostrando todos los tipos de expedientes del sistema
                            @endif
                        </small>
                    </div>

                    <!-- Campos de Filtro -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <div class="flex flex-col">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Número de Expediente</label>
                            <input type="text" name="numero" placeholder="Ej: AI-1-2025" value="{{ $filtro_numero }}"
                                   class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Documento del Solicitante</label>
                            <input type="text" name="documento" placeholder="DNI o RUC" value="{{ $filtro_documento }}"
                                   class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Estado</label>
                            <select name="estado" class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition bg-white">
                                <option value="">Todos</option>
                                <option value="PENDIENTE" {{ $filtro_estado == 'PENDIENTE' ? 'selected' : '' }}>Pendiente</option>
                                <option value="EN_PROCESO" {{ $filtro_estado == 'EN_PROCESO' ? 'selected' : '' }}>En Proceso</option>
                                <option value="RESUELTA" {{ $filtro_estado == 'RESUELTA' ? 'selected' : '' }}>Resuelta</option>
                                <option value="RECHAZADA" {{ $filtro_estado == 'RECHAZADA' ? 'selected' : '' }}>Rechazada</option>
                            </select>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Fecha Desde</label>
                            <input type="date" name="fecha_desde" value="{{ $filtro_fecha_desde }}"
                                   class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" value="{{ $filtro_fecha_hasta }}"
                                   class="border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex justify-end gap-3 mt-5">
                        <a href="{{ route('oac.acceso_info') }}" class="px-5 py-2 border border-slate-300 text-slate-600 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-slate-50 hover:text-slate-800 transition flex items-center gap-1.5">
                            <i class="fas fa-eraser"></i> Limpiar
                        </a>
                        <button type="submit" class="px-6 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-bold uppercase tracking-wider shadow-sm transition flex items-center gap-1.5">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </form>
            </div>

            <!-- TABLA DE RESULTADOS -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex-1 flex flex-col">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="p-4 px-6 text-xs font-bold text-slate-500 uppercase tracking-wider w-[160px]">Nº Expediente</th>
                                <th class="p-4 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider w-[120px]">Fecha</th>
                                <th class="p-4 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Solicitante</th>
                                <th class="p-4 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider w-[130px]">Documento</th>
                                <th class="p-4 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Descripción</th>
                                <th class="p-4 px-4 text-xs font-bold text-slate-500 uppercase tracking-wider w-[120px]">Estado</th>
                                <th class="p-4 px-6 text-xs font-bold text-slate-500 uppercase tracking-wider w-[100px] text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @if ($expedientes->count() > 0)
                                @foreach ($expedientes as $exp)
                                    <tr class="hover:bg-slate-50/80 cursor-pointer transition" onclick="window.location='{{ route('oac.acceso_info', ['id' => $exp->cdgo_dto_trmte]) }}'">
                                        <td class="p-4 px-6 font-semibold text-slate-800 text-sm whitespace-nowrap">{{ $exp->nu_expe_todo }}</td>
                                        <td class="p-4 px-4 text-slate-600 text-sm whitespace-nowrap">{{ date('d/m/Y', strtotime($exp->fe_ingr_trmte)) }}</td>
                                        <td class="p-4 px-4 font-medium text-slate-700 text-sm">{{ $exp->solicitante }}</td>
                                        <td class="p-4 px-4 text-slate-500 text-sm whitespace-nowrap">{{ $exp->nu_docu }}</td>
                                        <td class="p-4 px-4 text-slate-500 text-sm max-w-[280px] truncate" title="{{ $exp->de_obse_trmte }}">
                                            {{ $exp->de_obse_trmte }}
                                        </td>
                                        <td class="p-4 px-4 whitespace-nowrap">
                                            @php
                                                $badgeClass = 'bg-slate-100 text-slate-700';
                                                if ($exp->estado_actual === 'PENDIENTE') $badgeClass = 'bg-yellow-50 text-yellow-700 border border-yellow-200';
                                                elseif ($exp->estado_actual === 'EN_PROCESO') $badgeClass = 'bg-blue-50 text-blue-700 border border-blue-200';
                                                elseif ($exp->estado_actual === 'RESUELTA') $badgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                                                elseif ($exp->estado_actual === 'RECHAZADA') $badgeClass = 'bg-rose-50 text-rose-700 border border-rose-200';
                                            @endphp
                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badgeClass }}">
                                                {{ $exp->estado_actual ?: 'SIN ESTADO' }}
                                            </span>
                                        </td>
                                        <td class="p-4 px-6 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                            <a href="{{ route('oac.acceso_info', ['id' => $exp->cdgo_dto_trmte]) }}" class="inline-flex items-center gap-1 px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 hover:text-slate-900 rounded-lg text-xs font-bold tracking-wide transition shadow-xs">
                                                <i class="fas fa-eye"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="7" class="p-12 text-center text-slate-400">
                                        <i class="fas fa-inbox text-5xl mb-4 text-slate-200 block"></i>
                                        <p class="font-medium text-sm">No se encontraron expedientes</p>
                                        <p class="text-xs mt-1 text-slate-400">Intente modificando sus filtros de búsqueda.</p>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- PAGINACIÓN -->
                @if ($expedientes->hasPages())
                    <footer class="p-4 px-6 border-t border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row justify-between items-center gap-4 no-print">
                        <div class="text-xs text-slate-500 font-medium">
                            Mostrando <span class="font-bold text-slate-700">{{ $expedientes->firstItem() ?: 0 }}</span> a 
                            <span class="font-bold text-slate-700">{{ $expedientes->lastItem() ?: 0 }}</span> de 
                            <span class="font-bold text-slate-700">{{ $expedientes->total() }}</span> expedientes
                        </div>
                        <div class="flex items-center gap-1.5">
                            {{-- Primero --}}
                            @if ($expedientes->onFirstPage())
                                <span class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-300 flex items-center justify-center text-xs cursor-not-allowed"><i class="fas fa-angle-double-left"></i></span>
                                <span class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-300 flex items-center justify-center text-xs cursor-not-allowed"><i class="fas fa-angle-left"></i></span>
                            @else
                                <a href="{{ $expedientes->url(1) }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 flex items-center justify-center text-xs transition"><i class="fas fa-angle-double-left"></i></a>
                                <a href="{{ $expedientes->previousPageUrl() }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 flex items-center justify-center text-xs transition"><i class="fas fa-angle-left"></i></a>
                            @endif

                            {{-- Páginas intermedio --}}
                            @php
                                $start = max(1, $expedientes->currentPage() - 2);
                                $end = min($expedientes->lastPage(), $expedientes->currentPage() + 2);
                            @endphp
                            @for ($i = $start; $i <= $end; $i++)
                                @if ($i == $expedientes->currentPage())
                                    <span class="w-8 h-8 rounded-lg bg-slate-800 text-white flex items-center justify-center text-xs font-bold">{{ $i }}</span>
                                @else
                                    <a href="{{ $expedientes->url($i) }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 flex items-center justify-center text-xs transition font-semibold">{{ $i }}</a>
                                @endif
                            @endfor

                            {{-- Último --}}
                            @if ($expedientes->hasMorePages())
                                <a href="{{ $expedientes->nextPageUrl() }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 flex items-center justify-center text-xs transition"><i class="fas fa-angle-right"></i></a>
                                <a href="{{ $expedientes->url($expedientes->lastPage()) }}" class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 flex items-center justify-center text-xs transition"><i class="fas fa-angle-double-right"></i></a>
                            @else
                                <span class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-300 flex items-center justify-center text-xs cursor-not-allowed"><i class="fas fa-angle-right"></i></span>
                                <span class="w-8 h-8 rounded-lg border border-slate-200 bg-white text-slate-300 flex items-center justify-center text-xs cursor-not-allowed"><i class="fas fa-angle-double-right"></i></span>
                            @endif
                        </div>
                    </footer>
                @endif
            </div>

        @else
            <!-- ========================================== -->
            <!--             DETALLE DEL EXPEDIENTE         -->
            <!-- ========================================== -->
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                @if ($expediente)
                    <!-- Encabezado del Detalle -->
                    <div class="bg-slate-800 text-white p-5 px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <a href="{{ route('oac.acceso_info') }}" class="no-print inline-flex items-center gap-2 px-3 py-1.5 bg-slate-700/80 hover:bg-slate-700 border border-slate-600 hover:border-slate-500 text-white rounded-lg text-xs font-semibold transition">
                                <i class="fas fa-arrow-left"></i> Volver al Listado
                            </a>
                            <h1 class="text-md font-bold uppercase tracking-wide flex items-center gap-2"><i class="fas fa-file-alt text-blue-400"></i> Detalle del Expediente</h1>
                        </div>
                        <span class="bg-white/10 border border-white/20 px-3.5 py-1 rounded-lg text-sm font-bold tracking-wider">
                            N° {{ $expediente->nu_expe_todo }}
                        </span>
                    </div>

                    <div class="p-6">
                        <!-- GRID GENERAL Y SOLICITANTE -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- INFORMACIÓN GENERAL -->
                            <div>
                                <h2 class="text-sm font-bold text-slate-800 border-b-2 border-slate-800 pb-2 mb-4 flex items-center gap-2">
                                    <i class="fas fa-info-circle text-blue-500"></i> INFORMACIÓN GENERAL
                                </h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                        <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Código de Expediente</div>
                                        <div class="text-sm font-semibold text-slate-800">{{ $expediente->cdgo_dto_trmte }}</div>
                                    </div>
                                    <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                        <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Fecha de Ingreso</div>
                                        <div class="text-sm font-semibold text-slate-800">{{ date('d/m/Y H:i', strtotime($expediente->fe_ingr_trmte)) }}</div>
                                    </div>
                                    <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                        <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Tipo de Trámite</div>
                                        <div class="text-sm font-semibold text-slate-800">{{ $expediente->de_tpo_trmte }}</div>
                                    </div>
                                    <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                        <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Número de Folios</div>
                                        <div class="text-sm font-semibold text-slate-800">{{ $expediente->nu_foli_trmte ?: '0' }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- DATOS DEL SOLICITANTE -->
                            <div>
                                <h2 class="text-sm font-bold text-slate-800 border-b-2 border-slate-800 pb-2 mb-4 flex items-center gap-2">
                                    <i class="fas fa-user text-blue-500"></i> DATOS DEL SOLICITANTE
                                </h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                        <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">
                                            {{ $expediente->rz_soci ? 'RUC / Empresa' : 'Documento de Identidad' }}
                                        </div>
                                        <div class="text-sm font-semibold text-slate-800">{{ $expediente->nu_docu }}</div>
                                    </div>

                                    @if ($expediente->rz_soci)
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg sm:col-span-2">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Razón Social</div>
                                            <div class="text-sm font-bold text-slate-800">{{ $expediente->rz_soci }}</div>
                                        </div>
                                        @if ($solicitud_completa && (!empty($solicitud_completa->rep_nombres) || !empty($solicitud_completa->rep_apellido_paterno) || !empty($solicitud_completa->rep_apellido_materno)))
                                            <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg sm:col-span-2">
                                                <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Representante Legal</div>
                                                <div class="text-sm font-semibold text-slate-800">
                                                    {{ trim(($solicitud_completa->rep_nombres ?? '') . ' ' . ($solicitud_completa->rep_apellido_paterno ?? '') . ' ' . ($solicitud_completa->rep_apellido_materno ?? '')) }}
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        @if ($expediente->no_usrio)
                                            <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg sm:col-span-2">
                                                <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Nombres y Apellidos</div>
                                                <div class="text-sm font-bold text-slate-800">
                                                    {{ $expediente->no_usrio }} {{ $expediente->ap_pate }} {{ $expediente->ap_mate }}
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- INFORMACIÓN ADICIONAL DEL SOLICITANTE -->
                        @if ($solicitud_completa && (
                            !empty($solicitud_completa->pais_residencia) || 
                            !empty($solicitud_completa->direccion_completa) ||
                            !empty($solicitud_completa->area_geografica_procedencia) ||
                            !empty($solicitud_completa->correo_opcional) ||
                            !empty($solicitud_completa->celular_opcional) ||
                            !empty($solicitud_completa->autoidentificacion_etnica) || 
                            !empty($solicitud_completa->lengua_materna) || 
                            !empty($solicitud_completa->persona_discapacidad) ||
                            !empty($solicitud_completa->edad_opcional) ||
                            !empty($solicitud_completa->sexo_opcional)
                        ))
                            <div class="mt-6">
                                <h2 class="text-sm font-bold text-slate-800 border-b-2 border-slate-800 pb-2 mb-4 flex items-center gap-2">
                                    <i class="fas fa-user-circle text-blue-500"></i> INFORMACIÓN ADICIONAL DEL SOLICITANTE
                                </h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                    @if (!empty($solicitud_completa->pais_residencia))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">País</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->pais_residencia }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->direccion_completa))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg sm:col-span-2">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Dirección</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->direccion_completa }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->area_geografica_procedencia))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Área Geográfica de Procedencia</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->area_geografica_procedencia }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->correo_opcional))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Correo Opcional</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->correo_opcional }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->celular_opcional))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Celular Opcional</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->celular_opcional }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->autoidentificacion_etnica))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Autoidentificación Étnica</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->autoidentificacion_etnica }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->lengua_materna))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Lengua Materna</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->lengua_materna }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->persona_discapacidad))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Persona con Discapacidad</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->persona_discapacidad }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->edad_opcional))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Edad</div>
                                            <div class="text-sm font-semibold text-slate-855">{{ $solicitud_completa->edad_opcional }} años</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->sexo_opcional))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Sexo</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->sexo_opcional }}</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- MODALIDADES DE NOTIFICACIÓN -->
                        @if ($solicitud_completa && (
                            !empty($solicitud_completa->modalidad_notificacion) || 
                            !empty($solicitud_completa->email_notificacion) ||
                            !empty($solicitud_completa->nombre_aplicacion_mensajeria) ||
                            !empty($solicitud_completa->telefono_celular_contacto) ||
                            !empty($solicitud_completa->direccion_notificacion) ||
                            !empty($solicitud_completa->modalidad_otro)
                        ))
                            <div class="mt-6">
                                <h2 class="text-sm font-bold text-slate-800 border-b-2 border-slate-800 pb-2 mb-4 flex items-center gap-2">
                                    <i class="fas fa-bell text-blue-500"></i> MODALIDADES DE NOTIFICACIÓN
                                </h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                    @if (!empty($solicitud_completa->modalidad_notificacion))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Modalidad</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->modalidad_notificacion }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->email_notificacion))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Email Notificación</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->email_notificacion }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->nombre_aplicacion_mensajeria))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">App Mensajería</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->nombre_aplicacion_mensajeria }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->telefono_celular_contacto))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Teléfono</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->telefono_celular_contacto }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->direccion_notificacion))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg sm:col-span-2">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Dirección Notificación</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->direccion_notificacion }}</div>
                                        </div>
                                    @endif
                                    @if (!empty($solicitud_completa->modalidad_otro))
                                        <div class="bg-slate-50 border border-slate-200/60 p-3 rounded-lg">
                                            <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider mb-1">Otra Modalidad</div>
                                            <div class="text-sm font-semibold text-slate-850">{{ $solicitud_completa->modalidad_otro }}</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- FORMAS DE ENTREGA -->
                        @if (isset($formas_entrega) && count($formas_entrega) > 0)
                            <div class="mt-6">
                                <h2 class="text-sm font-bold text-slate-800 border-b-2 border-slate-800 pb-2 mb-4 flex items-center gap-2">
                                    <i class="fas fa-truck text-blue-500"></i> FORMAS DE ENTREGA SELECCIONADAS
                                </h2>
                                <div class="grid grid-cols-1 gap-2.5">
                                    @foreach ($formas_entrega as $index => $forma)
                                        <div class="flex items-start bg-slate-50 border border-slate-200 border-l-4 border-l-emerald-500 rounded-lg p-3">
                                            <div class="bg-emerald-100 text-emerald-800 font-bold w-6 h-6 rounded-full flex items-center justify-center text-xs shrink-0 mr-3">
                                                {{ $index + 1 }}
                                            </div>
                                            <div class="text-sm text-slate-700 leading-relaxed font-medium">
                                                {!! nl2br(e($forma->de_descripcion)) !!}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- DESCRIPCIÓN DE LA SOLICITUD -->
                        <div class="mt-6">
                            <h2 class="text-sm font-bold text-slate-800 border-b-2 border-slate-800 pb-2 mb-4 flex items-center gap-2">
                                <i class="fas fa-pen-alt text-blue-500"></i> DESCRIPCIÓN DE LA SOLICITUD
                            </h2>
                            <div class="bg-slate-50 border border-slate-200 p-4 rounded-lg space-y-3">
                                @if ($solicitud_completa && !empty($solicitud_completa->area_informacion))
                                    <div class="text-sm">
                                        <strong class="text-slate-500 font-bold text-xs uppercase block mb-0.5">Área de Información</strong>
                                        <span class="text-slate-850 font-medium">{{ $solicitud_completa->area_informacion }}</span>
                                    </div>
                                @endif
                                <div class="text-sm">
                                    <strong class="text-slate-500 font-bold text-xs uppercase block mb-0.5">Descripción / Detalle del Pedido</strong>
                                    <span class="text-slate-700 leading-relaxed whitespace-pre-line block font-medium">{{ $expediente->de_obse_trmte }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- HISTORIAL DE LA SOLICITUD -->
                        <div class="mt-6">
                            <h2 class="text-sm font-bold text-slate-800 border-b-2 border-slate-800 pb-2 mb-4 flex items-center gap-2">
                                <i class="fas fa-history text-blue-500"></i> HISTORIAL DE LA SOLICITUD
                            </h2>
                            @if (isset($resultado_historial) && $resultado_historial->count() > 0)
                                <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                                    <!-- Cabecera del Historial (Web) -->
                                    <div class="hidden md:flex bg-slate-50 font-bold text-[10px] text-slate-500 uppercase tracking-wider border-b border-slate-200">
                                        <div class="p-3 w-[150px] shrink-0">Fecha</div>
                                        <div class="p-3 w-[200px] shrink-0">Área</div>
                                        <div class="p-3 w-[140px] shrink-0">Estado</div>
                                        <div class="p-3 flex-1">Descripción / Observación</div>
                                        <div class="p-3 w-[90px] shrink-0 text-center">Adjunto</div>
                                    </div>
                                    <div class="divide-y divide-slate-150">
                                        @foreach ($resultado_historial as $hist)
                                            <div class="flex flex-col md:flex-row md:items-center text-sm text-slate-700 hover:bg-slate-50/50 transition p-4 md:p-0">
                                                <div class="md:p-3 md:w-[150px] shrink-0 text-slate-500 font-medium whitespace-nowrap">
                                                    <span class="md:hidden font-bold text-xs text-slate-400 block uppercase mb-0.5">Fecha</span>
                                                    {{ date('d/m/Y H:i', strtotime($hist->fe_rcbdo_trmte)) }}
                                                </div>
                                                <div class="md:p-3 md:w-[200px] shrink-0 text-slate-700 font-semibold md:whitespace-nowrap md:truncate" title="{{ $hist->de_area }}">
                                                    <span class="md:hidden font-bold text-xs text-slate-400 block uppercase mb-0.5">Área</span>
                                                    {{ $hist->de_area ?: '-' }}
                                                </div>
                                                <div class="md:p-3 md:w-[140px] shrink-0">
                                                    <span class="md:hidden font-bold text-xs text-slate-400 block uppercase mb-0.5">Estado</span>
                                                    @php
                                                        $badgeColor = 'bg-slate-100 text-slate-700';
                                                        if ($hist->de_estdo_trmte === 'PENDIENTE') $badgeColor = 'bg-yellow-50 text-yellow-750';
                                                        elseif ($hist->de_estdo_trmte === 'EN_PROCESO') $badgeColor = 'bg-blue-50 text-blue-750';
                                                        elseif ($hist->de_estdo_trmte === 'RESUELTA') $badgeColor = 'bg-emerald-50 text-emerald-755';
                                                        elseif ($hist->de_estdo_trmte === 'RECHAZADA') $badgeColor = 'bg-rose-50 text-rose-755';
                                                    @endphp
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $badgeColor }}">
                                                        {{ $hist->de_estdo_trmte }}
                                                    </span>
                                                </div>
                                                <div class="md:p-3 flex-1 text-slate-600 font-medium leading-relaxed mt-2 md:mt-0">
                                                    <span class="md:hidden font-bold text-xs text-slate-400 block uppercase mb-0.5">Descripción</span>
                                                    {{ $hist->de_obse ?: 'Sin observaciones' }}
                                                </div>
                                                <div class="md:p-3 md:w-[90px] shrink-0 text-left md:text-center mt-2 md:mt-0">
                                                    <span class="md:hidden font-bold text-xs text-slate-400 block uppercase mb-0.5">Adjunto</span>
                                                    @if ($hist->de_urls_imag)
                                                        <a href="/Presentacion/mod_gm/mod_tram_exte/adjunto/ver_archivo.php?nom_archivo={{ urlencode($hist->de_urls_imag) }}" 
                                                           target="_blank" rel="noopener"
                                                           class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-semibold hover:underline"
                                                           title="Descargar {{ $hist->de_urls_imag }}">
                                                            <i class="fas fa-file-download text-lg"></i> <span class="md:hidden text-xs">Descargar</span>
                                                        </a>
                                                    @else
                                                        <span class="text-slate-400 font-bold">-</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="p-6 bg-slate-50 border border-slate-200 rounded-xl text-center text-slate-400 text-sm font-semibold">
                                    <i class="fas fa-info-circle text-lg mr-1 text-blue-500"></i> No hay historial disponible para este expediente.
                                </div>
                            @endif
                        </div>

                        <!-- ACCIONES FINALES -->
                        <div class="mt-8 pt-5 border-t border-slate-200 flex justify-center gap-3 no-print">
                            <a href="{{ route('oac.acceso_info') }}" class="px-6 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 hover:text-slate-850 rounded-lg text-xs font-bold uppercase tracking-wider transition flex items-center gap-1.5">
                                <i class="fas fa-arrow-left"></i> Volver al Listado
                            </a>
                            <button onclick="window.print()" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold uppercase tracking-wider shadow-sm transition flex items-center gap-1.5">
                                <i class="fas fa-print"></i> Imprimir Expediente
                            </button>
                        </div>
                    </div>
                @else
                    <div class="p-12 text-center text-slate-400">
                        <i class="fas fa-exclamation-triangle text-5xl mb-4 text-yellow-500"></i>
                        <h2 class="text-lg font-bold text-slate-750">Expediente no encontrado</h2>
                        <p class="text-xs mt-1">El expediente solicitado no existe o no cuenta con los permisos necesarios para acceder a él.</p>
                        <a href="{{ route('oac.acceso_info') }}" class="mt-5 inline-flex items-center gap-1.5 px-5 py-2 bg-slate-850 hover:bg-slate-700 text-white rounded-lg text-xs font-bold uppercase tracking-wider shadow-sm transition">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </a>
                    </div>
                @endif
            </div>
        @endif

    </main>

</body>
</html>
