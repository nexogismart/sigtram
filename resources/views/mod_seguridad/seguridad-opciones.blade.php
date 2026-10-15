<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mantenimiento de Opciones — SIGTRAM</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_seguridad.seguridad-opciones'])
</head>
<body data-sigtram-screen="mod_seguridad.seguridad-opciones" class="sigtram-module bg-gray-50 text-gray-900 antialiased" x-data="opcionManager()">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_seguridad.seguridad-opciones'])

    <main class="p-4 sm:p-6 w-full max-w-6xl mx-auto min-h-screen flex flex-col">
        
        <!-- Breadcrumbs -->
        <nav class="mb-5 flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <li><a href="/inicio" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li><a href="#" class="hover:text-blue-600 transition-colors">Seguridad</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600">Mantenimiento de Opciones</li>
            </ol>
        </nav>

        <!-- Header -->
        <header class="mb-6 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-100">
                    <i class="fas fa-list-ul text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Mantenimiento de Opciones</h1>
                    <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-black">Gestionar opciones de menú, accesos directos y rutas</p>
                </div>
            </div>
            
            <div>
                <button @click="openCreateModal()" 
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center gap-2 active:scale-95 shadow-md shadow-blue-100">
                    <i class="fas fa-plus text-xs"></i> Nueva Opción
                </button>
            </div>
        </header>

        <!-- Messages -->
        @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl flex items-center gap-3">
            <i class="fas fa-check-circle text-lg text-emerald-500"></i>
            <span class="text-xs font-bold">{{ session('success') }}</span>
        </div>
        @endif

        @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl flex items-center gap-3">
            <i class="fas fa-exclamation-circle text-lg text-red-500"></i>
            <span class="text-xs font-bold">{{ session('error') }}</span>
        </div>
        @endif

        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex flex-col overflow-hidden">
            
            <!-- Filter Bar -->
            <div class="bg-gray-50/80 border-b border-gray-200 p-4 lg:p-6">
                <form method="GET" action="{{ route('seguridad.opciones') }}" class="flex flex-wrap items-end gap-4">
                    <div class="w-full sm:w-64">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="criterio">Buscar por texto</label>
                        <input type="text" name="criterio" id="criterio" value="{{ request('criterio') }}"
                               placeholder="Buscar..."
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                    </div>

                    <div class="w-full sm:w-48">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="campo">Buscar en</label>
                        <div class="relative">
                            <select name="campo" id="campo" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                <option value="dc_rle" {{ request('campo') == 'dc_rle' ? 'selected' : '' }}>OPCIÓN</option>
                                <option value="de_mdlo" {{ request('campo') == 'de_mdlo' ? 'selected' : '' }}>MÓDULO</option>
                            </select>
                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="px-5 py-2.5 bg-gray-800 hover:bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-sm">
                            Buscar
                        </button>
                        @if(request()->filled('criterio'))
                            <a href="{{ route('seguridad.opciones') }}" class="px-5 py-2.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-200 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center justify-center">
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="flex-1 overflow-auto custom-scrollbar p-3">
                <table class="w-full text-left border-separate border-spacing-y-2">
                    <thead>
                        <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                            <th class="px-4 py-2 text-center w-12">#</th>
                            <th class="px-6 py-2">Opción / Menú</th>
                            <th class="px-6 py-2">Ruta / Dirección</th>
                            <th class="px-6 py-2">Módulo</th>
                            <th class="px-6 py-2">Opción Superior (Padre)</th>
                            <th class="px-4 py-2 text-center w-16">Popup</th>
                            <th class="px-6 py-2 text-center w-28">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($opciones as $index => $opcion)
                        <tr class="bg-white hover:shadow-[0_8px_20px_-6px_rgba(0,0,0,0.05)] hover:-translate-y-0.5 transition-all duration-200">
                             <td class="px-4 py-2.5 first:rounded-l-2xl border-y border-transparent text-center">
                                 <span class="text-xs font-semibold text-gray-400 bg-gray-100/60 w-7 h-7 rounded-lg flex items-center justify-center font-mono mx-auto">
                                     {{ ($opciones->currentPage() - 1) * $opciones->perPage() + $index + 1 }}
                                 </span>
                             </td>
                             <td class="px-6 py-2.5 border-y border-transparent">
                                 <div class="flex flex-col">
                                     <span class="text-sm font-black text-gray-800">{{ $opcion->dc_rle }}</span>
                                     <span class="text-[9px] text-gray-400 font-bold uppercase mt-0.5">{{ $opcion->de_rle }}</span>
                                 </div>
                             </td>
                             <td class="px-6 py-2.5 border-y border-transparent">
                                 <span class="text-xs font-mono text-gray-600 bg-gray-50 px-2 py-1 rounded border border-gray-200/50">{{ $opcion->de_dreccon }}</span>
                             </td>
                             <td class="px-6 py-2.5 border-y border-transparent">
                                 <span class="text-xs text-blue-600 font-black uppercase tracking-wider">{{ $opcion->de_mdlo }}</span>
                             </td>
                             <td class="px-6 py-2.5 border-y border-transparent">
                                 <span class="text-xs text-gray-500 font-semibold">{{ $opcion->de_sub_rle_nombre ?: '-' }}</span>
                             </td>
                             <td class="px-4 py-2.5 border-y border-transparent text-center">
                                 @if($opcion->in_popup == '1')
                                     <span class="inline-block px-2 py-0.5 bg-indigo-50 text-indigo-600 border border-indigo-100 rounded text-[9px] font-black uppercase tracking-widest">SI</span>
                                 @else
                                     <span class="inline-block px-2 py-0.5 bg-gray-50 text-gray-400 border border-gray-200/60 rounded text-[9px] font-black uppercase tracking-widest">NO</span>
                                 @endif
                             </td>
                             <td class="px-6 py-2.5 last:rounded-r-2xl border-y border-transparent text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="openEditModal({{ json_encode($opcion) }})" 
                                            class="w-8 h-8 flex items-center justify-center bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition-all"
                                            title="Editar">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    
                                    <form method="POST" action="{{ route('seguridad.opciones.delete', $opcion->cdgo_rle) }}" 
                                          onsubmit="return confirm('¿Está seguro de eliminar esta opción?');" class="inline">
                                        @csrf
                                        <button type="submit" 
                                                class="w-8 h-8 flex items-center justify-center bg-red-50 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition-all"
                                                title="Eliminar">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-24 text-center text-gray-400">
                                <div class="w-20 h-20 bg-gray-50 rounded-3xl flex items-center justify-center mb-6 mx-auto border border-gray-100">
                                    <i class="fas fa-inbox text-3xl text-gray-200"></i>
                                </div>
                                <p class="text-sm font-black text-gray-500 uppercase tracking-widest">Sin opciones encontradas</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer Pagination -->
            @if($opciones->total() > 0)
            <footer class="p-5 bg-white border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                    Página {{ $opciones->currentPage() }} de {{ $opciones->lastPage() }} — {{ $opciones->total() }} TOTAL
                </div>
                <div>
                    {{ $opciones->appends(request()->query())->links() }}
                </div>
            </footer>
            @endif
        </div>
    </main>

    <!-- Modal Form (AlpineJS) -->
    <div x-show="openModal" x-cloak class="fixed inset-0 z-[150] overflow-hidden flex items-center justify-center p-4">
        <div x-show="openModal" x-transition.opacity @click="closeModal()" class="absolute inset-0 bg-gray-900/40 backdrop-blur-md"></div>

        <div x-show="openModal" x-transition.scale class="relative bg-white rounded-2xl shadow-2xl overflow-hidden max-w-md w-full border border-white/50">
            <div class="bg-gray-50/80 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-md shadow-blue-100">
                        <i class="fas fa-list-ul text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest" x-text="modalTitle"></h3>
                        <p class="text-[8px] text-gray-400 font-bold uppercase mt-0.5">Formulario de registro</p>
                    </div>
                </div>
                <button @click="closeModal()" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded-lg transition-all">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('seguridad.opciones.store') }}" @submit="validateForm($event)" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto custom-scrollbar">
                @csrf
                <input type="hidden" name="cdgo_rle" x-model="formData.cdgo_rle">

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="dc_rle">Etiqueta de Opción (Menú)</label>
                    <input type="text" name="dc_rle" id="dc_rle" x-model="formData.dc_rle" required
                           @blur="sanitizeField('dc_rle')"
                           :class="errors.dc_rle ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                           placeholder="Ej: REGISTRAR EXPEDIENTE"
                           class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold uppercase outline-none transition-all shadow-sm">
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.dc_rle" x-text="errors.dc_rle" x-cloak></span>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="de_rle">Descripción detallada</label>
                    <input type="text" name="de_rle" id="de_rle" x-model="formData.de_rle" required
                           @blur="sanitizeField('de_rle')"
                           :class="errors.de_rle ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                           placeholder="Ej: Permite el ingreso de expedientes por mesa de partes"
                           class="w-full px-4 py-2.5 border rounded-xl text-xs font-semibold outline-none transition-all shadow-sm">
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.de_rle" x-text="errors.de_rle" x-cloak></span>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="de_dreccon">URL / Ruta del archivo</label>
                    <input type="text" name="de_dreccon" id="de_dreccon" x-model="formData.de_dreccon" required
                           @keydown="filterRuta($event)"
                           @blur="sanitizeField('de_dreccon')"
                           :class="errors.de_dreccon ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                           placeholder="Ej: mod_seguridad/seguridad-usuario"
                           class="w-full px-4 py-2.5 border rounded-xl text-sm font-mono outline-none transition-all shadow-sm">
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.de_dreccon" x-text="errors.de_dreccon" x-cloak></span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="cdgo_mdlo">Módulo</label>
                        <div class="relative">
                            <select name="cdgo_mdlo" id="cdgo_mdlo" x-model="formData.cdgo_mdlo" required
                                    :class="errors.cdgo_mdlo ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                                    class="w-full px-4 py-2.5 bg-white border rounded-xl text-xs font-bold outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                <option value="">-- MÓDULO --</option>
                                @foreach($modulos as $mod)
                                    <option value="{{ $mod->cdgo_mdlo }}">{{ $mod->de_mdlo }}</option>
                                @endforeach
                            </select>
                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                        </div>
                        <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.cdgo_mdlo" x-text="errors.cdgo_mdlo" x-cloak></span>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="cdgo_sub_rle">Menú Superior (Opcional)</label>
                        <div class="relative">
                            <select name="cdgo_sub_rle" id="cdgo_sub_rle" x-model="formData.cdgo_sub_rle"
                                    :class="errors.cdgo_sub_rle ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                                    class="w-full px-4 py-2.5 bg-white border rounded-xl text-xs font-bold outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                <option value="">NINGUNO</option>
                                @foreach($parentOpciones as $p)
                                    <option value="{{ $p->cdgo_rle }}">{{ $p->dc_rle }}</option>
                                @endforeach
                            </select>
                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                        </div>
                        <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.cdgo_sub_rle" x-text="errors.cdgo_sub_rle" x-cloak></span>
                    </div>
                </div>

                <div class="flex items-center gap-3 ml-1 pt-1">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest cursor-pointer" for="in_popup">¿Abrir como Ventana Emergente (Popup)?</label>
                    <input type="checkbox" name="in_popup" id="in_popup" value="1" x-model="formData.in_popup"
                           class="w-4 h-4 rounded text-blue-600 border-gray-300 focus:ring-blue-500 focus:ring-2 outline-none transition-all shadow-sm cursor-pointer">
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" @click="closeModal()" class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all">
                        Cancelar
                    </button>
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function opcionManager() {
            return {
                openModal: false,
                modalTitle: 'Nueva Opción',
                formData: {
                    cdgo_rle: '',
                    dc_rle: '',
                    de_rle: '',
                    de_dreccon: '',
                    cdgo_mdlo: '',
                    cdgo_sub_rle: '',
                    in_popup: false
                },
                errors: {
                    dc_rle: '',
                    de_rle: '',
                    de_dreccon: '',
                    cdgo_mdlo: '',
                    cdgo_sub_rle: ''
                },
                openCreateModal() {
                    this.modalTitle = 'Nueva Opción';
                    this.formData = { cdgo_rle: '', dc_rle: '', de_rle: '', de_dreccon: '', cdgo_mdlo: '', cdgo_sub_rle: '', in_popup: false };
                    this.clearErrors();
                    this.openModal = true;
                },
                openEditModal(opcion) {
                    this.modalTitle = 'Editar Opción';
                    this.formData = {
                        cdgo_rle: opcion.cdgo_rle,
                        dc_rle: opcion.dc_rle,
                        de_rle: opcion.de_rle,
                        de_dreccon: opcion.de_dreccon,
                        cdgo_mdlo: opcion.cdgo_mdlo,
                        cdgo_sub_rle: opcion.cdgo_sub_rle || '',
                        in_popup: opcion.in_popup == '1'
                    };
                    this.clearErrors();
                    this.openModal = true;
                },
                closeModal() {
                    this.openModal = false;
                },
                clearErrors() {
                    this.errors = { dc_rle: '', de_rle: '', de_dreccon: '', cdgo_mdlo: '', cdgo_sub_rle: '' };
                },
                sanitizeField(fieldName) {
                    if (this.formData[fieldName]) {
                        if (fieldName === 'de_dreccon') {
                            // Strip spaces and convert to lowercase
                            this.formData[fieldName] = this.formData[fieldName].replace(/\s+/g, '').toLowerCase();
                        } else {
                            this.formData[fieldName] = this.formData[fieldName].replace(/\s+/g, ' ').trim().toUpperCase();
                        }
                    }
                },
                filterRuta(event) {
                    const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'];
                    if (allowedKeys.includes(event.key)) return;
                    if (!/^[A-Za-z0-9_\-\.\/]$/.test(event.key)) {
                        event.preventDefault();
                    }
                },
                validateForm(event) {
                    this.clearErrors();
                    this.sanitizeField('dc_rle');
                    this.sanitizeField('de_rle');
                    this.sanitizeField('de_dreccon');

                    let isValid = true;
                    if (!this.formData.dc_rle) {
                        this.errors.dc_rle = 'La etiqueta de la opción es obligatoria.';
                        isValid = false;
                    }
                    if (!this.formData.de_rle) {
                        this.errors.de_rle = 'La descripción es obligatoria.';
                        isValid = false;
                    }
                    if (!this.formData.de_dreccon) {
                        this.errors.de_dreccon = 'La URL o ruta es obligatoria.';
                        isValid = false;
                    } else if (/\s/.test(this.formData.de_dreccon)) {
                        this.errors.de_dreccon = 'No se permiten espacios en la ruta.';
                        isValid = false;
                    }

                    if (!this.formData.cdgo_mdlo) {
                        this.errors.cdgo_mdlo = 'Debe seleccionar un módulo.';
                        isValid = false;
                    }

                    if (!isValid) {
                        event.preventDefault();
                    }
                }
            }
        }
    </script>
</body>
</html>