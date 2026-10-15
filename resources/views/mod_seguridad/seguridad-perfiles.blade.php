<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mantenimiento de Perfiles — SIGTRAM</title>
    
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
    @include('components.sigtram.module-head', ['screenKey' => 'mod_seguridad.seguridad-perfiles'])
</head>
<body data-sigtram-screen="mod_seguridad.seguridad-perfiles" class="sigtram-module bg-gray-50 text-gray-900 antialiased" x-data="profileManager()">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_seguridad.seguridad-perfiles'])

    <main class="p-4 sm:p-6 w-full max-w-5xl mx-auto min-h-screen flex flex-col">
        
        <!-- Breadcrumbs -->
        <nav class="mb-5 flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <li><a href="/inicio" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li><a href="#" class="hover:text-blue-600 transition-colors">Seguridad</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600">Mantenimiento de Perfiles</li>
            </ol>
        </nav>

        <!-- Header -->
        <header class="mb-6 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-100">
                    <i class="fas fa-id-card text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Mantenimiento de Perfiles</h1>
                    <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-black">Crear, editar y dar de baja perfiles del sistema</p>
                </div>
            </div>
            
            <div>
                <button @click="openCreateModal()" 
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center gap-2 active:scale-95 shadow-md shadow-blue-100">
                    <i class="fas fa-plus text-xs"></i> Nuevo Perfil
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
                <form method="GET" action="{{ route('seguridad.perfiles') }}" class="flex flex-wrap items-end gap-4">
                    <div class="w-full sm:w-64">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="criterio">Buscar por texto</label>
                        <input type="text" name="criterio" id="criterio" value="{{ request('criterio') }}"
                               placeholder="Nombre o descripción..."
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                    </div>

                    <div class="w-full sm:w-48">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="campo">Buscar en</label>
                        <div class="relative">
                            <select name="campo" id="campo" class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                <option value="nc_prfle" {{ request('campo') == 'nc_prfle' ? 'selected' : '' }}>NOMBRE DE PERFIL</option>
                                <option value="de_prfle" {{ request('campo') == 'de_prfle' ? 'selected' : '' }}>DESCRIPCIÓN</option>
                            </select>
                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="px-5 py-2.5 bg-gray-800 hover:bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-sm">
                            Buscar
                        </button>
                        @if(request()->filled('criterio'))
                            <a href="{{ route('seguridad.perfiles') }}" class="px-5 py-2.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-200 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center justify-center">
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
                        <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest">
                            <th class="px-3 py-1 text-center w-10">#</th>
                            <th class="px-4 py-1">Nombre del Perfil</th>
                            <th class="px-4 py-1">Descripción</th>
                            <th class="px-4 py-1 text-center w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($perfiles as $index => $perfil)
                        <tr class="bg-white hover:shadow-[0_4px_12px_-4px_rgba(0,0,0,0.05)] hover:-translate-y-0.5 transition-all duration-200">
                            <td class="px-3 py-2.5 first:rounded-l-xl border-y border-transparent text-center">
                                <span class="text-xs font-semibold text-gray-400 bg-gray-100/60 w-6 h-6 rounded-lg flex items-center justify-center font-mono mx-auto">
                                    {{ ($perfiles->currentPage() - 1) * $perfiles->perPage() + $index + 1 }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 border-y border-transparent">
                                <span class="text-xs font-black text-gray-800">{{ $perfil->nc_prfle }}</span>
                            </td>
                            <td class="px-4 py-2.5 border-y border-transparent">
                                <span class="text-xs text-gray-500 font-semibold leading-relaxed">{{ $perfil->de_prfle }}</span>
                            </td>
                            <td class="px-4 py-2.5 last:rounded-r-xl border-y border-transparent text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="openEditModal({{ json_encode($perfil) }})" 
                                            class="w-7 h-7 flex items-center justify-center bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition-all"
                                            title="Editar">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    
                                    <form method="POST" action="{{ route('seguridad.perfiles.delete', $perfil->cdgo_prfle) }}" 
                                          onsubmit="return confirm('¿Está seguro de eliminar este perfil?');" class="inline">
                                        @csrf
                                        <button type="submit" 
                                                class="w-7 h-7 flex items-center justify-center bg-red-50 hover:bg-red-600 text-red-600 hover:text-white rounded-lg transition-all"
                                                title="Eliminar">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="py-16 text-center text-gray-400">
                                <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mb-4 mx-auto border border-gray-100">
                                    <i class="fas fa-inbox text-2xl text-gray-250"></i>
                                </div>
                                <p class="text-[11px] font-black text-gray-500 uppercase tracking-widest">Sin registros encontrados</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer Pagination -->
            @if($perfiles->total() > 0)
            <footer class="p-4 bg-white border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">
                    Página {{ $perfiles->currentPage() }} de {{ $perfiles->lastPage() }} — {{ $perfiles->total() }} TOTAL
                </div>
                <div>
                    {{ $perfiles->appends(request()->query())->links() }}
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
                        <i class="fas fa-id-card text-lg"></i>
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

            <form method="POST" action="{{ route('seguridad.perfiles.store') }}" @submit="validateForm($event)" class="p-6 space-y-5">
                @csrf
                <input type="hidden" name="cdgo_prfle" x-model="formData.cdgo_prfle">

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="nc_prfle">Nombre del Perfil</label>
                    <input type="text" name="nc_prfle" id="nc_prfle" x-model="formData.nc_prfle" required
                           @blur="sanitizeField('nc_prfle')"
                           :class="errors.nc_prfle ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                           placeholder="Ej: ADMINISTRADOR"
                           class="w-full px-4 py-2.5 border rounded-xl text-sm font-bold uppercase outline-none transition-all shadow-sm">
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.nc_prfle" x-text="errors.nc_prfle" x-cloak></span>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="de_prfle">Descripción del Perfil</label>
                    <textarea name="de_prfle" id="de_prfle" x-model="formData.de_prfle" required rows="3"
                              @blur="sanitizeField('de_prfle')"
                              :class="errors.de_prfle ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                              placeholder="Describe las funciones del perfil..."
                              class="w-full px-4 py-2.5 border rounded-xl text-xs font-semibold outline-none transition-all shadow-sm"></textarea>
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.de_prfle" x-text="errors.de_prfle" x-cloak></span>
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
        function profileManager() {
            return {
                openModal: false,
                modalTitle: 'Nuevo Perfil',
                formData: {
                    cdgo_prfle: '',
                    nc_prfle: '',
                    de_prfle: ''
                },
                errors: {
                    nc_prfle: '',
                    de_prfle: ''
                },
                openCreateModal() {
                    this.modalTitle = 'Nuevo Perfil';
                    this.formData = { cdgo_prfle: '', nc_prfle: '', de_prfle: '' };
                    this.clearErrors();
                    this.openModal = true;
                },
                openEditModal(perfil) {
                    this.modalTitle = 'Editar Perfil';
                    this.formData = {
                        cdgo_prfle: perfil.cdgo_prfle,
                        nc_prfle: perfil.nc_prfle,
                        de_prfle: perfil.de_prfle
                    };
                    this.clearErrors();
                    this.openModal = true;
                },
                closeModal() {
                    this.openModal = false;
                },
                clearErrors() {
                    this.errors = { nc_prfle: '', de_prfle: '' };
                },
                sanitizeField(fieldName) {
                    if (this.formData[fieldName]) {
                        // Trim and collapse multiple spaces, then uppercase
                        this.formData[fieldName] = this.formData[fieldName].replace(/\s+/g, ' ').trim().toUpperCase();
                    }
                },
                validateForm(event) {
                    this.clearErrors();
                    this.sanitizeField('nc_prfle');
                    this.sanitizeField('de_prfle');

                    let isValid = true;
                    if (!this.formData.nc_prfle) {
                        this.errors.nc_prfle = 'El nombre del perfil es obligatorio.';
                        isValid = false;
                    }
                    if (!this.formData.de_prfle) {
                        this.errors.de_prfle = 'La descripción del perfil es obligatoria.';
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