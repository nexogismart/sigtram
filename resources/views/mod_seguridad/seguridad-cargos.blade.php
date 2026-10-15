<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mantenimiento de Cargos — SIGTRAM</title>
    
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
    @include('components.sigtram.module-head', ['screenKey' => 'mod_seguridad.seguridad-cargos'])
</head>
<body data-sigtram-screen="mod_seguridad.seguridad-cargos" class="sigtram-module bg-gray-50 text-gray-900 antialiased" x-data="cargoManager()">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_seguridad.seguridad-cargos'])

    <main class="p-4 sm:p-6 w-full max-w-5xl mx-auto min-h-screen flex flex-col">
        
        <!-- Breadcrumbs -->
        <nav class="mb-5 flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <li><a href="/inicio" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li><a href="#" class="hover:text-blue-600 transition-colors">Seguridad</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600">Mantenimiento de Cargos</li>
            </ol>
        </nav>

        <!-- Header -->
        <header class="mb-6 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-100">
                    <i class="fas fa-briefcase text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Mantenimiento de Cargos</h1>
                    <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-black">Gestionar cargos y asignaciones por áreas</p>
                </div>
            </div>
            
            <div>
                <button @click="openCreateModal()" 
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center gap-2 active:scale-95 shadow-md shadow-blue-100">
                    <i class="fas fa-plus text-xs"></i> Nuevo Cargo
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
                <form method="GET" action="{{ route('seguridad.cargos') }}" class="flex flex-wrap items-end gap-4">
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
                                <option value="de_crgo" {{ request('campo') == 'de_crgo' ? 'selected' : '' }}>CARGO</option>
                                <option value="de_area" {{ request('campo') == 'de_area' ? 'selected' : '' }}>ÁREA / GERENCIA</option>
                            </select>
                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="px-5 py-2.5 bg-gray-800 hover:bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-sm">
                            Buscar
                        </button>
                        @if(request()->filled('criterio'))
                            <a href="{{ route('seguridad.cargos') }}" class="px-5 py-2.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-200 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center justify-center">
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
                            <th class="px-6 py-2">Código/Abrev.</th>
                            <th class="px-6 py-2">Cargo / Puesto</th>
                            <th class="px-6 py-2">Área / Gerencia</th>
                            <th class="px-6 py-2 text-center w-28">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cargos as $index => $cargo)
                        <tr class="bg-white hover:shadow-[0_8px_20px_-6px_rgba(0,0,0,0.05)] hover:-translate-y-0.5 transition-all duration-200">
                            <td class="px-4 py-2.5 first:rounded-l-2xl border-y border-transparent text-center">
                                <span class="text-xs font-semibold text-gray-400 bg-gray-100/60 w-7 h-7 rounded-lg flex items-center justify-center font-mono mx-auto">
                                    {{ ($cargos->currentPage() - 1) * $cargos->perPage() + $index + 1 }}
                                </span>
                            </td>
                            <td class="px-6 py-2.5 border-y border-transparent">
                                <span class="text-xs font-black text-blue-600 bg-blue-50/50 px-2 py-1 rounded-md">{{ $cargo->dc_crgo }}</span>
                            </td>
                            <td class="px-6 py-2.5 border-y border-transparent">
                                <span class="text-sm font-black text-gray-800">{{ $cargo->de_crgo }}</span>
                            </td>
                            <td class="px-6 py-2.5 border-y border-transparent">
                                <span class="text-xs text-gray-600 font-bold uppercase">{{ $cargo->de_area }}</span>
                            </td>
                            <td class="px-6 py-2.5 last:rounded-r-2xl border-y border-transparent text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="openEditModal({{ json_encode($cargo) }})" 
                                            class="w-8 h-8 flex items-center justify-center bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition-all"
                                            title="Editar">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    
                                    <form method="POST" action="{{ route('seguridad.cargos.delete', $cargo->cdgo_crgo) }}" 
                                          onsubmit="return confirm('¿Está seguro de eliminar este cargo?');" class="inline">
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
                            <td colspan="5" class="py-24 text-center text-gray-400">
                                <div class="w-20 h-20 bg-gray-50 rounded-3xl flex items-center justify-center mb-6 mx-auto border border-gray-100">
                                    <i class="fas fa-inbox text-3xl text-gray-200"></i>
                                </div>
                                <p class="text-sm font-black text-gray-500 uppercase tracking-widest">Sin cargos encontrados</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer Pagination -->
            @if($cargos->total() > 0)
            <footer class="p-5 bg-white border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                    Página {{ $cargos->currentPage() }} de {{ $cargos->lastPage() }} — {{ $cargos->total() }} TOTAL
                </div>
                <div>
                    {{ $cargos->appends(request()->query())->links() }}
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
                        <i class="fas fa-briefcase text-lg"></i>
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

            <form method="POST" action="{{ route('seguridad.cargos.store') }}" @submit="validateForm($event)" class="p-6 space-y-5">
                @csrf
                <input type="hidden" name="cdgo_crgo" x-model="formData.cdgo_crgo">

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="dc_crgo">Código / Abrev.</label>
                    <input type="text" name="dc_crgo" id="dc_crgo" x-model="formData.dc_crgo" required
                           @keydown="filterAbreviatura($event)"
                           @blur="sanitizeField('dc_crgo')"
                           :class="errors.dc_crgo ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                           placeholder="Ej: GER_TI, SEC_GEN"
                           class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold uppercase outline-none transition-all shadow-sm">
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.dc_crgo" x-text="errors.dc_crgo" x-cloak></span>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="de_crgo">Cargo / Puesto</label>
                    <input type="text" name="de_crgo" id="de_crgo" x-model="formData.de_crgo" required
                           @blur="sanitizeField('de_crgo')"
                           :class="errors.de_crgo ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                           placeholder="Ej: GERENTE DE TECNOLOGÍAS"
                           class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold uppercase outline-none transition-all shadow-sm">
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.de_crgo" x-text="errors.de_crgo" x-cloak></span>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="cdgo_area">Área / Gerencia</label>
                    <div class="relative">
                        <select name="cdgo_area" id="cdgo_area" x-model="formData.cdgo_area" required
                                :class="errors.cdgo_area ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                                class="w-full px-4 py-2.5 bg-white border rounded-xl text-xs font-bold outline-none transition-all shadow-sm appearance-none cursor-pointer">
                            <option value="">-- SELECCIONE --</option>
                            @foreach($areas as $area)
                                <option value="{{ $area->cdgo_area }}">{{ $area->de_area }}</option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                    </div>
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.cdgo_area" x-text="errors.cdgo_area" x-cloak></span>
                </div>

                <div class="flex justify-end gap-2 pt-2">
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
        function cargoManager() {
            return {
                openModal: false,
                modalTitle: 'Nuevo Cargo',
                formData: {
                    cdgo_crgo: '',
                    dc_crgo: '',
                    de_crgo: '',
                    cdgo_area: ''
                },
                errors: {
                    dc_crgo: '',
                    de_crgo: '',
                    cdgo_area: ''
                },
                openCreateModal() {
                    this.modalTitle = 'Nuevo Cargo';
                    this.formData = { cdgo_crgo: '', dc_crgo: '', de_crgo: '', cdgo_area: '' };
                    this.clearErrors();
                    this.openModal = true;
                },
                openEditModal(cargo) {
                    this.modalTitle = 'Editar Cargo';
                    this.formData = {
                        cdgo_crgo: cargo.cdgo_crgo,
                        dc_crgo: cargo.dc_crgo,
                        de_crgo: cargo.de_crgo,
                        cdgo_area: cargo.cdgo_area
                    };
                    this.clearErrors();
                    this.openModal = true;
                },
                closeModal() {
                    this.openModal = false;
                },
                clearErrors() {
                    this.errors = { dc_crgo: '', de_crgo: '', cdgo_area: '' };
                },
                sanitizeField(fieldName) {
                    if (this.formData[fieldName]) {
                        if (fieldName === 'dc_crgo') {
                            // Trim, uppercase, and strip non-alphanumeric/underscore
                            this.formData[fieldName] = this.formData[fieldName].replace(/[^A-Za-z0-9_]/g, '').toUpperCase();
                        } else {
                            this.formData[fieldName] = this.formData[fieldName].replace(/\s+/g, ' ').trim().toUpperCase();
                        }
                    }
                },
                filterAbreviatura(event) {
                    const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'];
                    if (allowedKeys.includes(event.key)) return;
                    if (!/^[A-Za-z0-9_]$/.test(event.key)) {
                        event.preventDefault();
                    }
                },
                validateForm(event) {
                    this.clearErrors();
                    this.sanitizeField('dc_crgo');
                    this.sanitizeField('de_crgo');

                    let isValid = true;
                    if (!this.formData.dc_crgo) {
                        this.errors.dc_crgo = 'La abreviatura es obligatoria.';
                        isValid = false;
                    } else if (!/^[A-Z0-9_]+$/.test(this.formData.dc_crgo)) {
                        this.errors.dc_crgo = 'Solo se permiten letras, números y guiones bajos (sin espacios).';
                        isValid = false;
                    }

                    if (!this.formData.de_crgo) {
                        this.errors.de_crgo = 'El nombre del cargo es obligatorio.';
                        isValid = false;
                    }

                    if (!this.formData.cdgo_area) {
                        this.errors.cdgo_area = 'Debe seleccionar un área o gerencia.';
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