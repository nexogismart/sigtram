<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bandeja OTI - Mesa de Ayuda</title>
    
    <!-- Carga de Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        .custom-scrollbar-light::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar-light::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar-light::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar-light::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Pequeños ajustes que Tailwind no cubre nativamente */
        .ck-powered-by { display: none !important; }
        .ck-editor__editable_inline { min-height: 200px; border-bottom-left-radius: 0.75rem !important; border-bottom-right-radius: 0.75rem !important; }
        .ck-toolbar { border-top-left-radius: 0.75rem !important; border-top-right-radius: 0.75rem !important; background-color: #f8fafc !important;}
        
        .table-row-hover { transition: all 0.2s ease-in-out; }
        .table-row-hover:hover { transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); z-index: 10; position: relative; }
        
        tr { transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out; }
        .no-results { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Modales Generales */
        .modal-overlay { background-color: rgba(17, 24, 39, 0.5); backdrop-filter: blur(2px); }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased flex flex-col min-h-screen">
<div class="p-4 sm:p-6 w-full max-w-[1400px] mx-auto flex-1">

    {{-- ENCABEZADO Y ACCIONES --}}
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 tracking-tight"><i class="fas fa-headset text-blue-600 mr-2"></i>Mesa de Ayuda - OTI</h2>
            <p class="text-sm text-gray-500 mt-1 uppercase tracking-wider font-bold">Genera y administra tus requerimientos de soporte</p>
        </div>
        <div class="hidden sm:block">
            <span class="bg-blue-50 text-blue-700 px-4 py-2 rounded-xl text-xs font-bold border border-blue-100">
                <i class="fas fa-info-circle mr-1"></i> Sistema de Soporte Técnico
            </span>
        </div>
    </div>

    {{-- TARJETA DE INFORMACIÓN DE CONTACTO (INDEPENDIENTE) --}}
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 mb-6 relative overflow-hidden transition-shadow hover:shadow-md">
        <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
        <div class="flex items-start gap-4 mb-5">
            <div class="bg-blue-100 text-blue-600 p-3 rounded-lg flex-shrink-0">
                <i class="fas fa-address-book text-xl"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-800">Información de Contacto</h3>
                <p class="text-xs text-gray-500 mt-0.5">Mantenga sus datos actualizados para una mejor atención</p>
            </div>
            <div id="unsavedWarning" class="ml-auto hidden animate-bounce flex items-center gap-2 bg-amber-50 text-amber-700 px-3 py-1.5 rounded-lg border border-amber-200 text-[11px] font-bold">
                <i class="fas fa-exclamation-circle"></i> Cambios sin guardar
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
            {{-- Celular de Contacto --}}
            <div class="relative group">
                <label for="telefono_contacto" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2 transition-colors group-focus-within:text-blue-600">
                    <i class="fas fa-mobile-alt text-gray-400 mr-1 group-focus-within:text-blue-500"></i> Número de celular
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 transition-colors">
                        <span class="text-sm font-semibold">+51</span>
                    </div>
                    <input type="text" id="telefono_contacto" 
                        value="{{ $phone_val ?? '' }}" 
                        placeholder="987654321" 
                        class="w-full bg-white border border-gray-300 rounded-lg pl-12 pr-10 py-2.5 text-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm"
                        maxlength="9">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none hidden" id="telefonoIcon">
                        <i class="fas fa-check-circle text-green-500 hidden" id="telefonoSuccessIcon"></i>
                        <i class="fas fa-exclamation-circle text-red-500 hidden" id="telefonoErrorIcon"></i>
                    </div>
                </div>
                <span id="telefonoError" class="text-red-500 text-[11px] mt-1.5 hidden flex items-start gap-1 font-medium transition-all"><i class="fas fa-info-circle mt-0.5"></i> <span id="telefonoErrorText">Celular requerido.</span></span>
            </div>

            {{-- Código AnyDesk --}}
            <div class="relative group">
                <label for="anydesk_contacto" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2 transition-colors group-focus-within:text-blue-600">
                    <i class="fas fa-desktop text-gray-400 mr-1 group-focus-within:text-blue-500"></i> Código AnyDesk
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 transition-colors">
                        <i class="fas fa-key text-xs"></i>
                    </div>
                    <input type="text" id="anydesk_contacto" 
                        value="{{ $anydesk_val ?? '' }}" 
                        placeholder="Ej. 123 456 789 o ALIAS" 
                        class="w-full bg-white border border-gray-300 rounded-lg pl-10 pr-10 py-2.5 text-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none hidden" id="anydeskIcon">
                        <i class="fas fa-check-circle text-green-500 hidden" id="anydeskSuccessIcon"></i>
                        <i class="fas fa-exclamation-circle text-red-500 hidden" id="anydeskErrorIcon"></i>
                    </div>
                </div>
                <span id="anydeskError" class="text-red-500 text-[11px] mt-1.5 hidden flex items-start gap-1 font-medium transition-all"><i class="fas fa-info-circle mt-0.5"></i> <span id="anydeskErrorText">Código AnyDesk requerido.</span></span>
            </div>
        </div>
        
        <div>
            <button type="button" id="btnUpdateContact" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold py-2 px-5 rounded-lg shadow-sm hover:shadow transition-all flex items-center gap-2">
                <i class="fas fa-save"></i> Actualizar Datos
            </button>
        </div>
    </div>

    {{-- ALERTAS Y MENSAJES DE ESTADO --}}
    @if(session('ticket_enviado'))
            <div class="bg-emerald-50 border border-emerald-200 p-4 mb-6 rounded-xl shadow-sm flex items-start gap-4" id="successMessage">
                <div class="bg-emerald-100 p-2 rounded-lg text-emerald-600"><i class="fas fa-check-double text-lg"></i></div>
                <div>
                    <h4 class="text-sm font-bold text-emerald-800">Ticket Registrado Exitosamente</h4>
                    <p class="text-sm text-emerald-700 mt-1">Se ha generado el ticket <strong>#{{ session('ticket_id') }}</strong> y enviado a la bandeja de OTI.</p>
                </div>
            </div>
            <script>try { localStorage.removeItem('ticketDraft'); } catch(e) {}</script>
        @endif

        @if($showPhoneAlert || !$phone_ok)
            <div class="bg-red-50 border border-red-200 p-4 mb-6 rounded-xl shadow-sm flex items-start gap-4" id="phoneAlert">
                <div class="bg-red-100 p-2 rounded-lg text-red-600"><i class="fas fa-exclamation-triangle text-lg"></i></div>
                <div>
                    <h4 class="text-sm font-bold text-red-800">Atención Requerida</h4>
                    <p class="text-sm text-red-700 mt-1">Debe completar su número de teléfono o anexo para poder enviar tickets, así podremos contactarlo rápidamente.</p>
                </div>
            </div>
        @endif

        @if(!$isRestricted)
        {{-- FORMULARIO DE GENERACIÓN DE TICKET --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8 transition-shadow hover:shadow-md">
            <div class="bg-gradient-to-r from-gray-50 to-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-plus-circle text-blue-500"></i> Nuevo Requerimiento
                </h3>
            </div>
            
            <div class="p-6">
                <form id="ticketForm" method="post" action="{{ route('helpdesk.index.store') }}" enctype="multipart/form-data">
                    @csrf
                    
                    {{-- Hidden inputs to submit contact info along with ticket if not updated --}}
                    <input type="hidden" name="telefono_contacto" id="hidden_telefono_contacto" value="{{ $phone_val ?? '' }}">
                    <input type="hidden" name="anydesk_contacto" id="hidden_anydesk_contacto" value="{{ $anydesk_val ?? '' }}">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        {{-- Asunto Breve --}}
                        <div class="relative group">
                            <label for="asunto" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2 transition-colors group-focus-within:text-blue-600">
                                <i class="fas fa-tag text-gray-400 mr-1 group-focus-within:text-blue-500"></i> Asunto Breve
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 transition-colors">
                                    <i class="fas fa-pen text-xs"></i>
                                </div>
                                <input type="text" id="asunto" name="asunto" placeholder="Ej. Problema con la impresora..." 
                                    class="w-full bg-white border border-gray-300 rounded-lg pl-10 pr-4 py-2.5 text-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                            </div>
                            <span id="asuntoError" class="text-red-500 text-[11px] mt-1.5 hidden flex items-start gap-1 font-medium transition-all"><i class="fas fa-info-circle mt-0.5"></i> <span>Complete este campo.</span></span>
                        </div>

                        {{-- Tipo de Servicio --}}
                        <div class="relative group">
                            <label for="incidencia" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2 transition-colors group-focus-within:text-blue-600">
                                <i class="fas fa-concierge-bell text-gray-400 mr-1 group-focus-within:text-blue-500"></i> Tipo de Servicio
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 transition-colors z-10">
                                    <i class="fas fa-list-ul text-xs"></i>
                                </div>
                                <select id="incidencia" name="incidencia" 
                                    class="w-full bg-white border border-gray-300 rounded-lg pl-10 pr-10 py-2.5 text-gray-700 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                    <option value="">-- SELECCIONE SERVICIO --</option>
                                    @foreach ($incidencias as $inc)
                                        <option value="{{ $inc->id }}">{{ $inc->detalle }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <span id="incidenciaError" class="text-red-500 text-[11px] mt-1.5 hidden flex items-start gap-1 font-medium transition-all"><i class="fas fa-info-circle mt-0.5"></i> <span>Seleccione un servicio.</span></span>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="editor" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Descripción Detallada del Problema</label>
                        <div class="border border-gray-300 rounded-xl shadow-sm bg-white overflow-hidden focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500 transition-all">
                            <textarea id="editor" name="descripcion" placeholder="Escriba los detalles aquí..."></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-6 bg-gray-50 p-5 rounded-xl border border-gray-100">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-3"><i class="fas fa-paperclip text-gray-400 mr-1"></i> Archivos Adjuntos (Opcional)</label>
                            <input type="file" name="archivos[]" id="archivos" multiple class="block w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 transition-all cursor-pointer bg-white border border-gray-200 rounded-xl shadow-sm">
                            <p class="text-[10px] text-gray-400 mt-2">Puede seleccionar múltiples archivos simultáneamente.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-3"><i class="fas fa-tachometer-alt text-gray-400 mr-1"></i> Nivel de Urgencia</label>
                            <div class="flex items-center gap-4 bg-white p-2 rounded-xl border border-gray-200 shadow-sm w-max">
                                <label class="relative flex items-center cursor-pointer px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <input type="radio" id="normal" name="urgencia" value="normal" checked class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 focus:ring-2">
                                    <span class="ml-2 text-sm font-semibold text-gray-700">Normal</span>
                                </label>
                                <div class="w-px h-6 bg-gray-200"></div>
                                <label class="relative flex items-center cursor-pointer px-4 py-2 rounded-lg hover:bg-red-50 transition-colors">
                                    <input type="radio" id="urgente" name="urgencia" value="urgente" class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 focus:ring-red-500 focus:ring-2">
                                    <span class="ml-2 text-sm font-bold text-red-600">Urgente</span>
                                </label>
                            </div>
                            <input type="text" id="urgencia-descripcion" name="urgencia_descripcion" placeholder="Especifique brevemente el motivo de urgencia..." class="mt-3 w-full bg-white border border-red-200 rounded-xl px-4 py-2.5 text-gray-700 text-sm focus:ring-2 focus:ring-red-500 outline-none transition-all shadow-sm hidden">
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" id="btnSubmit" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2 transform hover:-translate-y-0.5">
                            <i class="fas fa-paper-plane"></i> Enviar Requerimiento a OTI
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

    {{-- TABLA DE HISTORIAL --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-[600px]">
        <div class="bg-gray-50 border-b border-gray-200 px-6 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 z-10 shadow-sm">
            <h3 class="text-base font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-history text-gray-400"></i> Mi Historial de Tickets
            </h3>
            <div class="relative w-full sm:w-72">
                <input type="text" id="tableSearch" placeholder="Buscar en historial..." class="w-full bg-white border border-gray-300 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all shadow-sm">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                    <i class="fas fa-search text-xs"></i>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto flex-1 custom-scrollbar-light">
            <table class="w-full text-left border-collapse whitespace-nowrap min-w-[800px]">
                <thead class="bg-white border-b border-gray-200 sticky top-0 z-20 shadow-sm">
                    <tr>
                        <th class="py-4 px-6 text-gray-400 font-extrabold text-[10px] uppercase tracking-widest">Ticket</th>
                        <th class="py-4 px-6 text-gray-400 font-extrabold text-[10px] uppercase tracking-widest">Asunto</th>
                        <th class="py-4 px-6 text-gray-400 font-extrabold text-[10px] uppercase tracking-widest">Servicio</th>
                        <th class="py-4 px-6 text-gray-400 font-extrabold text-[10px] uppercase tracking-widest">Fecha Envío</th>
                        <th class="py-4 px-6 text-gray-400 font-extrabold text-[10px] uppercase tracking-widest text-center">Estado</th>
                        <th class="py-4 px-6 text-gray-400 font-extrabold text-[10px] uppercase tracking-widest text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse($tickets as $t)
                        <tr class="table-row-hover hover:bg-gray-50/50">
                            <td class="py-3 px-6 text-sm font-mono font-bold text-gray-600">
                                <span class="bg-gray-100 px-2 py-1 rounded-md border border-gray-200">#{{ $t->id }}</span>
                            </td>
                            <td class="py-3 px-6 text-sm font-semibold text-gray-800">{{ Str::limit($t->asunto, 40) }}</td>
                            <td class="py-3 px-6 text-xs font-medium text-gray-500">{{ $t->detalle ?? 'No especificado' }}</td>
                            <td class="py-3 px-6 text-xs font-medium text-gray-500 flex items-center gap-1"><i class="far fa-calendar-alt text-gray-400"></i> {{ $t->fecha_fmt }}</td>
                            <td class="py-3 px-6 text-center">
                                @if($t->situacion == 1)
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-extrabold bg-blue-50 text-blue-600 border border-blue-100 uppercase tracking-wider"><i class="fas fa-paper-plane mr-1"></i> Enviado</span>
                                @elseif($t->situacion == 2)
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-extrabold bg-yellow-50 text-yellow-600 border border-yellow-100 uppercase tracking-wider"><i class="fas fa-clock mr-1"></i> Pendiente</span>
                                @elseif($t->situacion == 3)
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-extrabold bg-orange-50 text-orange-600 border border-orange-100 uppercase tracking-wider"><i class="fas fa-cogs mr-1"></i> En Proceso</span>
                                @elseif($t->situacion == 4 || $t->situacion == 5)
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-extrabold bg-green-50 text-green-600 border border-green-100 uppercase tracking-wider"><i class="fas fa-check-double mr-1"></i> Finalizado</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-extrabold bg-gray-50 text-gray-600 border border-gray-200 uppercase tracking-wider">{{ $t->situacion }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-6 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick="openAjaxModal({{ $t->id }}, 'detalle')" class="w-8 h-8 rounded-lg bg-white border border-gray-200 text-gray-500 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50 shadow-sm transition-all flex items-center justify-center" title="Ver Detalles"><i class="fa fa-eye"></i></button>
                                    @if($t->situacion == 1)
                                        <button onclick="openAjaxModal({{ $t->id }}, 'anular')" class="w-8 h-8 rounded-lg bg-white border border-gray-200 text-gray-500 hover:text-red-600 hover:border-red-300 hover:bg-red-50 shadow-sm transition-all flex items-center justify-center" title="Anular Ticket"><i class="fa fa-ban"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-gray-400 bg-gray-50/50">
                                <i class="fas fa-inbox fa-3x mb-3 text-gray-300"></i>
                                <p class="text-sm font-medium">Aún no has generado ningún ticket de soporte.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 text-[11px] font-bold text-gray-400 uppercase tracking-wider flex justify-between">
            <span>Últimos {{ count($tickets) }} registros mostrados</span>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODALES -->
<!-- ========================================== -->


<!-- Modal AJAX Genérico -->
<div id="modal_ajax" class="fixed inset-0 z-[100] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
        <div class="fixed inset-0 modal-overlay transition-opacity" aria-hidden="true" onclick="closeAjaxModal()"></div>
        <div class="relative inline-block bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-3xl w-full flex flex-col max-h-[90vh]">
            
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center sticky top-0 z-10">
                <h3 class="text-base leading-6 font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2"><i class="fas fa-info-circle text-blue-500"></i> Detalle del Requerimiento</h3>
                <button type="button" class="text-gray-400 hover:text-gray-500 focus:outline-none bg-white p-1.5 rounded-lg border border-transparent hover:border-gray-200 shadow-sm transition-all" onclick="closeAjaxModal()">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <!-- CONTENEDOR AJAX -->
            <div id="modal_ajax_content" class="bg-white flex-1 overflow-y-auto custom-scrollbar-light min-h-[300px]">
                <div class="py-20 text-center text-gray-400"><i class="fas fa-spinner fa-spin fa-3x mb-4 text-blue-400"></i><p class="font-medium text-sm">Consultando base de datos...</p></div>
            </div>
            
        </div>
    </div>
</div>

<!-- SCRIPTS JS -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
    // Configuración CSRF para Fetch
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // 1. SMART SEARCH TABLE
    const tableSearch = document.getElementById('tableSearch');
    if (tableSearch) {
        tableSearch.addEventListener('keyup', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr:not(.no-results)');
            let hasResults = false;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.indexOf(value) > -1) {
                    row.style.display = "";
                    hasResults = true;
                } else {
                    row.style.display = "none";
                }
            });

            // Handle no results
            let noResultsRow = document.querySelector('.no-results');
            if (!hasResults) {
                if (!noResultsRow) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.className = 'no-results';
                    noResultsRow.innerHTML = `<td colspan="6" class="py-16 text-center text-gray-400 bg-gray-50/50">
                        <i class="fas fa-search fa-3x mb-3 text-gray-200"></i>
                        <p class="text-sm font-medium">No se encontraron tickets que coincidan con "${value}"</p>
                    </td>`;
                    document.querySelector('tbody').appendChild(noResultsRow);
                } else {
                    noResultsRow.querySelector('p').textContent = `No se encontraron tickets que coincidan con "${value}"`;
                    noResultsRow.style.display = "";
                }
            } else if (noResultsRow) {
                noResultsRow.style.display = "none";
            }
        });
    }

    // 2. MODAL AJAX
    const modalAjax = document.getElementById('modal_ajax');
    const containerAjax = document.getElementById('modal_ajax_content');

    function openAjaxModal(id, action) {
        containerAjax.innerHTML = '<div class="py-20 text-center text-gray-400"><i class="fas fa-spinner fa-spin fa-3x mb-4 text-blue-400"></i><p class="font-medium text-sm">Consultando base de datos...</p></div>';
        modalAjax.classList.remove('hidden');

        // Determinar endpoint
        let endpoint = `/mod_ti/helpdesk/index/${action}`;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            if (action === 'detalle') renderDetalle(data);
            else if (action === 'calificar') renderConstruccion('Calificar Ticket #' + id, 'fa-star text-yellow-400');
            else if (action === 'anular') renderConstruccion('Anular Ticket #' + id, 'fa-ban text-red-500');
        })
        .catch(error => {
            containerAjax.innerHTML = '<div class="py-16 text-center text-red-500"><i class="fas fa-times-circle fa-3x mb-4"></i><p>Error de conexión con el servidor.</p></div>';
        });
    }

    function closeAjaxModal() {
        if(modalAjax) modalAjax.classList.add('hidden');
    }

    function renderConstruccion(titulo, iconClass) {
        containerAjax.innerHTML = `
            <div class='py-16 text-center px-6'>
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-gray-50 mb-4 border border-gray-100 shadow-sm">
                    <i class='fas ${iconClass} text-4xl'></i>
                </div>
                <h3 class='text-xl font-bold text-gray-800 mb-2'>${titulo}</h3>
                <p class='text-sm text-gray-500'>Este módulo se encuentra actualmente en construcción o requiere permisos adicionales.</p>
            </div>
        `;
    }

    function renderDetalle(t) {
        // Mapeo inteligente de situaciones (1: Pendiente, 2: En Proceso, 3: Atendido, 4: Anulado)
        const situacionMap = {
            1: { label: 'Recibido', color: 'blue', icon: 'fa-inbox', desc: 'Esperando asignación técnica' },
            2: { label: 'En Revisión', color: 'amber', icon: 'fa-microscope', desc: 'Técnico analizando el caso' },
            3: { label: 'Atendido', color: 'emerald', icon: 'fa-check-double', desc: 'Solución implementada' },
            4: { label: 'Anulado', color: 'red', icon: 'fa-ban', desc: 'Ticket cancelado' }
        };
        
        const sit = situacionMap[t.situacion] || situacionMap[1];
        const isUrgente = (t.prioridad_nombre && t.prioridad_nombre.toLowerCase() === 'urgente');

        // Renderizado de la línea de tiempo (Stepper)
        const steps = [
            { id: 1, label: 'Generado', active: t.situacion >= 1 },
            { id: 2, label: 'En Proceso', active: t.situacion >= 2 },
            { id: 3, label: 'Finalizado', active: t.situacion >= 3 }
        ];

        let stepperHtml = '<div class="flex items-center justify-between mb-10 px-2 sm:px-10">';
        steps.forEach((s, idx) => {
            const isActive = s.active;
            const isLast = idx === steps.length - 1;
            stepperHtml += `
                <div class="flex flex-col items-center relative flex-1">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all duration-500 z-10 ${isActive ? 'bg-blue-600 border-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-white border-gray-200 text-gray-300'}">
                        ${isActive ? '<i class="fas fa-check text-[10px]"></i>' : `<span class="text-[10px] font-bold">${s.id}</span>`}
                    </div>
                    <span class="mt-2 text-[10px] font-black uppercase tracking-widest ${isActive ? 'text-blue-600' : 'text-gray-400'}">${s.label}</span>
                    ${!isLast ? `<div class="absolute top-4 left-1/2 w-full h-[2px] ${steps[idx+1].active ? 'bg-blue-600' : 'bg-gray-100'} -z-0"></div>` : ''}
                </div>
            `;
        });
        stepperHtml += '</div>';

        let btnZip = '';
        if (t.has_zip) {
            btnZip = `
                <a href='${t.url_zip}' download class='flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-2xl transition-all group mt-6'>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-blue-500 shadow-sm group-hover:scale-110 transition-transform">
                            <i class='fas fa-file-archive text-xl'></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-800">Archivos Adjuntos</p>
                            <p class="text-xs text-gray-500 font-medium">Paquete comprimido .ZIP</p>
                        </div>
                    </div>
                    <i class="fas fa-download text-gray-300 group-hover:text-blue-500 transition-colors mr-2"></i>
                </a>
            `;
        }

        containerAjax.innerHTML = `
            <div class="flex flex-col h-full bg-white">
                <!-- Header con Gradiente Sutil -->
                <div class="p-6 lg:p-10 border-b border-gray-50 bg-gradient-to-b from-gray-50/50 to-white">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-blue-600">
                                <i class="fas ${sit.icon} text-xl"></i>
                            </div>
                            <div>
                                <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-0.5">Ticket de Soporte</h4>
                                <p class="text-sm font-mono font-bold text-gray-800">#${t.id} <span class="mx-2 text-gray-200">|</span> <span class="text-gray-500">${t.fecha_fmt}</span></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <span class="inline-flex items-center px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-white border border-gray-200 shadow-sm ${isUrgente ? 'text-red-600 border-red-100' : 'text-gray-600'}">
                                <i class="fas ${isUrgente ? 'fa-bolt text-red-500' : 'fa-info-circle text-gray-400'} mr-2"></i>Prioridad ${isUrgente ? 'Urgente' : 'Normal'}
                            </span>
                            <span class="inline-flex items-center px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-blue-600 text-white shadow-md shadow-blue-100">
                                ${sit.label}
                            </span>
                        </div>
                    </div>
                    
                    <h2 class="text-2xl lg:text-3xl font-bold text-gray-900 leading-tight">${escapeHtml(t.asunto)}</h2>
                </div>

                <!-- Cuerpo del Detalle -->
                <div class="p-6 lg:p-10 flex-1">
                    ${stepperHtml}

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Columna Izquierda: Información -->
                        <div class="lg:col-span-1 space-y-6">
                            <div class="bg-gray-50 rounded-3xl p-6 border border-gray-100">
                                <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-5 flex items-center gap-2">
                                    <i class="fas fa-id-card text-blue-400"></i> Ficha Técnica
                                </h5>
                                <div class="space-y-5">
                                    <div>
                                        <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">Categoría</label>
                                        <p class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-blue-500"></span> ${escapeHtml(t.nombre_incidencia || 'General')}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">Usuario</label>
                                        <p class="text-sm font-bold text-gray-800">${t.no_crto || 'Anonimo'}</p>
                                    </div>
                                    <div class="pt-4 border-t border-gray-200/50 grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">Celular</label>
                                            <p class="text-xs font-bold text-gray-700">${t.nu_tele || '---'}</p>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] text-gray-500 font-bold uppercase mb-1">AnyDesk</label>
                                            <p class="text-xs font-bold text-gray-700 font-mono">${t.de_dire || '---'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${btnZip}
                            
                            <button onclick="window.print()" class="w-full flex items-center justify-center gap-2 py-3 border-2 border-dashed border-gray-200 rounded-2xl text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-all text-xs font-bold uppercase tracking-widest">
                                <i class="fas fa-print"></i> Imprimir Reporte
                            </button>
                        </div>

                        <!-- Columna Derecha: Descripción -->
                        <div class="lg:col-span-2">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                    <i class="fas fa-comment-alt text-gray-300"></i> Descripción del Caso
                                </h5>
                                <span class="text-[10px] font-bold text-gray-300 italic">Formato Enriquecido</span>
                            </div>
                            <div class="bg-white rounded-3xl border border-gray-200 p-6 lg:p-8 shadow-sm relative overflow-hidden min-h-[300px]">
                                <div class="prose prose-blue max-w-none text-gray-700 leading-relaxed custom-scrollbar-light">
                                    ${t.descripcion_html || '<p class="italic text-gray-400 text-center py-10">No se proporcionó una descripción adicional.</p>'}
                                </div>
                                <!-- Marca de agua decorativa -->
                                <div class="absolute bottom-4 right-4 opacity-[0.03] pointer-events-none select-none">
                                    <i class="fas fa-headset text-9xl text-gray-900"></i>
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

    // 3. LÓGICA DEL FORMULARIO Y CKEDITOR
    document.addEventListener('DOMContentLoaded', function () {
        const radioNormal = document.getElementById('normal');
        const radioUrgente = document.getElementById('urgente');
        const campoUrgencia = document.getElementById('urgencia-descripcion');
        
        function syncUrgencia() {
            if (!campoUrgencia) return;
            if (radioUrgente && radioUrgente.checked) {
                campoUrgencia.classList.remove('hidden');
                campoUrgencia.setAttribute('required', 'required');
            } else {
                campoUrgencia.classList.add('hidden');
                campoUrgencia.removeAttribute('required');
            }
        }
        if (radioNormal) radioNormal.addEventListener('change', syncUrgencia);
        if (radioUrgente) radioUrgente.addEventListener('change', syncUrgencia);
        syncUrgencia();

        // 4. CKEDITOR CLASSIC CON ADAPTADOR BASE64 PERSONALIZADO
        // Esto garantiza que la subida de imágenes funcione sin errores de Superbuild ni de colaboración.
        class Base64UploadAdapter {
            constructor(loader) { this.loader = loader; }
            upload() {
                return this.loader.file.then(file => new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve({ default: reader.result });
                    reader.onerror = error => reject(error);
                    reader.readAsDataURL(file);
                }));
            }
            abort() {}
        }

        function Base64UploadAdapterPlugin(editor) {
            editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
                return new Base64UploadAdapter(loader);
            };
        }

        let theEditor;
        const editorEl = document.getElementById('editor');
        if (editorEl) {
            ClassicEditor.create(editorEl, {
                extraPlugins: [Base64UploadAdapterPlugin],
                toolbar: {
                    items: [
                        'heading', '|',
                        'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|',
                        'outdent', 'indent', '|',
                        'imageUpload', 'insertTable', 'blockQuote', 'undo', 'redo'
                    ],
                    shouldNotGroupWhenFull: true
                },
                placeholder: 'Describa el problema detalladamente, adjunte capturas de pantalla si es necesario...',
                image: {
                    toolbar: [
                        'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|',
                        'toggleImageCaption', 'imageTextAlternative'
                    ]
                }
            }).then(editor => {
                theEditor = editor;
                
                if (!document.getElementById('successMessage')) {
                    const savedData = localStorage.getItem('ticketDraft');
                    if (savedData) {
                        try {
                            const parsed = JSON.parse(savedData);
                            if (parsed.descripcion) editor.setData(parsed.descripcion);
                            if (parsed.asunto) document.getElementById('asunto').value = parsed.asunto;
                            if (parsed.incidencia) document.getElementById('incidencia').value = parsed.incidencia;
                            if (parsed.urgencia === 'urgente') {
                                if(radioUrgente) radioUrgente.checked = true;
                                syncUrgencia();
                            }
                            if (parsed.urgencia_descripcion) document.getElementById('urgencia-descripcion').value = parsed.urgencia_descripcion;
                        } catch(e){}
                    }
                }
                editor.model.document.on('change:data', saveDraft);
            }).catch(error => console.error('Error CKEditor:', error));
        }

        // Autoguardado
        function saveDraft() {
            const data = {
                asunto: document.getElementById('asunto')?.value || '',
                incidencia: document.getElementById('incidencia')?.value || '',
                urgencia: (radioUrgente && radioUrgente.checked) ? 'urgente' : 'normal',
                urgencia_descripcion: document.getElementById('urgencia-descripcion')?.value || '',
                descripcion: theEditor ? theEditor.getData() : ''
            };
            localStorage.setItem('ticketDraft', JSON.stringify(data));
        }

        ['asunto', 'incidencia', 'urgencia-descripcion', 'telefono_contacto', 'anydesk_contacto'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', saveDraft);
        });

        // Validación en Tiempo Real (UX)
        const initialContactValues = {
            telefono: document.getElementById('telefono_contacto')?.value || '',
            anydesk: document.getElementById('anydesk_contacto')?.value || ''
        };

        const inputsToValidate = {
            asunto: { el: document.getElementById('asunto'), errEl: document.getElementById('asuntoError'), errText: document.getElementById('asuntoError') ? document.getElementById('asuntoError').querySelector('span') : null, validate: val => val.trim().length > 3 ? '' : 'El asunto debe ser claro y tener más de 3 caracteres.' },
            incidencia: { el: document.getElementById('incidencia'), errEl: document.getElementById('incidenciaError'), errText: document.getElementById('incidenciaError') ? document.getElementById('incidenciaError').querySelector('span') : null, validate: val => val !== '' ? '' : 'Debe seleccionar una categoría de servicio.' },
            telefono: { el: document.getElementById('telefono_contacto'), errEl: document.getElementById('telefonoError'), errText: document.getElementById('telefonoErrorText'), iconContainer: document.getElementById('telefonoIcon'), iconSuccess: document.getElementById('telefonoSuccessIcon'), iconError: document.getElementById('telefonoErrorIcon'), validate: val => {
                const num = val.replace(/\D/g, '');
                if (num.length === 0) return 'El celular es obligatorio.';
                if (num[0] !== '9') return 'El celular debe empezar con el número 9.';
                if (num.length !== 9) return `El celular debe tener 9 dígitos (ingresados: ${num.length}).`;
                return '';
            }},
            anydesk: { el: document.getElementById('anydesk_contacto'), errEl: document.getElementById('anydeskError'), errText: document.getElementById('anydeskErrorText'), iconContainer: document.getElementById('anydeskIcon'), iconSuccess: document.getElementById('anydeskSuccessIcon'), iconError: document.getElementById('anydeskErrorIcon'), validate: val => {
                const clean = val.trim();
                if (clean.length === 0) return 'El código AnyDesk es requerido para el soporte remoto.';
                if (clean.length < 5) return 'AnyDesk inválido. Revise la información ingresada.';
                return '';
            }}
        };

        function checkDirty() {
            const currentTel = document.getElementById('telefono_contacto')?.value || '';
            const currentAny = document.getElementById('anydesk_contacto')?.value || '';
            
            const telDirty = currentTel !== initialContactValues.telefono;
            const anyDirty = currentAny !== initialContactValues.anydesk;
            const isDirty = telDirty || anyDirty;

            const warning = document.getElementById('unsavedWarning');
            if (warning) {
                if (isDirty) warning.classList.remove('hidden');
                else warning.classList.add('hidden');
            }

            // Forzar actualización visual de los inputs de contacto
            const tMsg = inputsToValidate.telefono.validate(currentTel);
            const aMsg = inputsToValidate.anydesk.validate(currentAny);
            setValidationState('telefono', tMsg === '', tMsg);
            setValidationState('anydesk', aMsg === '', aMsg);
        }

        function setValidationState(fieldKey, isValid, errorMsg) {
            const field = inputsToValidate[fieldKey];
            if (!field || !field.el) return;

            // Verificar si es un campo de contacto y si tiene cambios sin guardar
            let isDirty = false;
            if (fieldKey === 'telefono') isDirty = field.el.value !== initialContactValues.telefono;
            if (fieldKey === 'anydesk') isDirty = field.el.value !== initialContactValues.anydesk;

            if (!isValid) {
                if (field.errEl) {
                    field.errEl.classList.remove('hidden');
                    field.errEl.classList.add('animate-pulse');
                    if (field.errText) field.errText.innerText = errorMsg;
                }
                field.el.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                field.el.classList.remove('border-green-500', 'border-blue-400', 'focus:ring-blue-500', 'focus:border-blue-500', 'border-gray-300');
                
                if (field.iconContainer) {
                    field.iconContainer.classList.remove('hidden');
                    field.iconError.classList.remove('hidden');
                    field.iconSuccess.classList.add('hidden');
                }
            } else {
                if (field.errEl) field.errEl.classList.add('hidden');
                field.el.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500', 'border-gray-300', 'border-green-500', 'border-blue-400');
                
                if (isDirty) {
                    // Si es válido pero no se ha guardado, borde azul/neutro y sin iconos
                    field.el.classList.add('border-blue-400', 'focus:ring-blue-500', 'focus:border-blue-500');
                    if (field.iconContainer) field.iconContainer.classList.add('hidden');
                } else {
                    // Si es válido y guardado, borde verde y check
                    field.el.classList.add('border-green-500', 'focus:ring-green-500', 'focus:border-green-500');
                    if (field.iconContainer) {
                        field.iconContainer.classList.remove('hidden');
                        field.iconError.classList.add('hidden');
                        field.iconSuccess.classList.remove('hidden');
                    }
                }
            }
        }

        // Asignar eventos de tiempo real
        Object.keys(inputsToValidate).forEach(key => {
            const field = inputsToValidate[key];
            if (field && field.el) {
                // Sincronizar inputs visibles con los hidden form inputs
                if (key === 'telefono') {
                    field.el.addEventListener('input', function(e) {
                        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 9);
                        const hidden = document.getElementById('hidden_telefono_contacto');
                        if (hidden) hidden.value = e.target.value;
                        checkDirty();
                    });
                }
                if (key === 'anydesk') {
                    field.el.addEventListener('input', function(e) {
                        const hidden = document.getElementById('hidden_anydesk_contacto');
                        if (hidden) hidden.value = e.target.value;
                        checkDirty();
                    });
                }

                field.el.addEventListener('input', function() {
                    const errorMsg = field.validate(this.value);
                    setValidationState(key, errorMsg === '', errorMsg);
                });
                field.el.addEventListener('blur', function() {
                    const errorMsg = field.validate(this.value);
                    setValidationState(key, errorMsg === '', errorMsg);
                });

                // Validar inicialmente para mostrar check verde si ya traen datos
                if (field.el.value) {
                    const errorMsg = field.validate(field.el.value);
                    setValidationState(key, errorMsg === '', errorMsg);
                }
            }
        });

        // Actualización de contacto AJAX
        const btnUpdateContact = document.getElementById('btnUpdateContact');
        if (btnUpdateContact) {
            btnUpdateContact.addEventListener('click', function() {
                const telEl = document.getElementById('telefono_contacto');
                const anyEl = document.getElementById('anydesk_contacto');
                const tMsg = inputsToValidate.telefono.validate(telEl.value);
                const aMsg = inputsToValidate.anydesk.validate(anyEl.value);

                setValidationState('telefono', tMsg === '', tMsg);
                setValidationState('anydesk', aMsg === '', aMsg);

                if (tMsg !== '' || aMsg !== '') return;

                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
                this.disabled = true;

                fetch('{{ route("helpdesk.index.update_contacto") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        telefono_contacto: telEl.value,
                        anydesk_contacto: anyEl.value
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                    if (data.success) {
                        initialContactValues.telefono = telEl.value;
                        initialContactValues.anydesk = anyEl.value;
                        checkDirty();
                        Swal.fire({
                            icon: 'success',
                            title: 'Actualizado',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Ocurrió un error al actualizar.', 'error');
                    }
                })
                .catch(err => {
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                    Swal.fire('Error', 'Error de conexión.', 'error');
                });
            });
        }


        // Validación final al enviar
        const form = document.getElementById('ticketForm');
        if (form) {
            form.addEventListener('submit', function (event) {
                let ok = true;
                
                Object.keys(inputsToValidate).forEach(key => {
                    const field = inputsToValidate[key];
                    if (field && field.el) {
                        const errorMsg = field.validate(field.el.value);
                        if (errorMsg !== '') {
                            setValidationState(key, false, errorMsg);
                            ok = false;
                        } else {
                            setValidationState(key, true, '');
                        }
                    }
                });

                if (!ok) {
                    event.preventDefault();
                    // Scroll al primer error
                    const firstError = document.querySelector('.border-red-500');
                    if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    if (theEditor) editorEl.value = theEditor.getData();
                    const btnSubmit = document.getElementById('btnSubmit');
                    if (btnSubmit) {
                        btnSubmit.disabled = true;
                        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Procesando y Subiendo...';
                        btnSubmit.classList.add('opacity-75', 'cursor-wait');
                    }
                }
            });
        }
    });
</script>
</body>
</html>