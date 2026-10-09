<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mantenimiento de Perfil y Opciones — SIGTRAM</title>
    
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
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased" x-data="perfilOpcionManager()">

    <main class="p-4 sm:p-6 w-full max-w-7xl mx-auto min-h-screen flex flex-col">
        
        <!-- Breadcrumbs -->
        <nav class="mb-5 flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <li><a href="/inicio" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li><a href="#" class="hover:text-blue-600 transition-colors">Seguridad</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600">Perfiles y Opciones</li>
            </ol>
        </nav>

        <!-- Header -->
        <header class="mb-6 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-100">
                    <i class="fas fa-user-tag text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Mantenimiento de Perfil y Opciones</h1>
                    <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-black">Asociar opciones del sistema y permisos por perfil de usuario</p>
                </div>
            </div>

            <!-- Perfil Selector -->
            <div class="w-full sm:w-72">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Seleccionar Perfil de Trabajo</label>
                <div class="relative">
                    <select x-model="selectedPerfil" @change="loadOpcionesAsignadas()"
                            class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                        <option value="">-- SELECCIONE --</option>
                        @foreach($perfiles as $p)
                            <option value="{{ $p->cdgo_prfle }}">{{ $p->nc_prfle }}</option>
                        @endforeach
                    </select>
                    <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                </div>
            </div>
        </header>

        <!-- Split Grid Container -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch flex-1">
            
            <!-- Columna Izquierda: Opciones del Sistema -->
            <div class="lg:col-span-6 bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col h-[650px] overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-200 px-5 py-4">
                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-500">Opciones Disponibles del Sistema</h3>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-5 space-y-6">
                    @foreach($modulos as $mod)
                        <div class="space-y-2">
                            <h4 class="text-xs font-black text-blue-600 uppercase tracking-wider bg-blue-50/50 px-3 py-1.5 rounded-lg flex items-center justify-between">
                                <span>📦 {{ $mod->de_mdlo }}</span>
                                <span class="text-[9px] text-gray-400">ID: {{ $mod->cdgo_mdlo }}</span>
                            </h4>
                            
                            <div class="grid grid-cols-1 gap-2 pl-2">
                                @if(isset($opcionesPorModulo[$mod->cdgo_mdlo]))
                                    @foreach($opcionesPorModulo[$mod->cdgo_mdlo] as $opc)
                                        <div class="flex items-center justify-between p-2.5 bg-white border border-gray-200/80 rounded-xl hover:bg-gray-50 transition-all shadow-sm">
                                            <div class="min-w-0 pr-2">
                                                <p class="text-xs font-bold text-gray-800">{{ $opc->dc_rle }}</p>
                                                <p class="text-[9px] text-gray-400 truncate">{{ $opc->de_dreccon }}</p>
                                            </div>
                                            
                                            <button @click="asignarOpcion({{ $opc->cdgo_rle }})" :disabled="!selectedPerfil || isAssigned({{ $opc->cdgo_rle }})"
                                                    :class="isAssigned({{ $opc->cdgo_rle }}) ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-200' : 'bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white border-blue-150 active:scale-95'"
                                                    class="w-7 h-7 rounded-lg border flex items-center justify-center transition-all shadow-sm font-bold disabled:opacity-50 shrink-0">
                                                <i class="fas fa-arrow-right text-[10px]"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-[10px] text-gray-400 font-bold italic pl-2">Sin opciones registradas en este módulo</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Columna Derecha: Opciones Asignadas al Perfil Seleccionado -->
            <div class="lg:col-span-6 bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col h-[650px] overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-200 px-5 py-4">
                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-500">Opciones Asignadas al Perfil</h3>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-5">
                    <!-- Estado vacío cuando no hay perfil seleccionado -->
                    <template x-if="!selectedPerfil">
                        <div class="flex flex-col items-center justify-center py-24 text-gray-400">
                            <i class="fas fa-mouse-pointer text-3xl text-gray-200 mb-4 animate-bounce"></i>
                            <p class="text-[10px] font-black uppercase tracking-widest text-center">Selecciona un perfil en la cabecera para ver o gestionar sus permisos</p>
                        </div>
                    </template>

                    <!-- Estado cargando -->
                    <template x-if="selectedPerfil && loading">
                        <div class="flex flex-col items-center justify-center py-24">
                            <div class="w-8 h-8 border-3 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                            <p class="text-[9px] font-black text-blue-700 uppercase tracking-widest mt-4">Cargando asignaciones...</p>
                        </div>
                    </template>

                    <!-- Listado de Asignaciones -->
                    <template x-if="selectedPerfil && !loading">
                        <div class="space-y-4">
                            <template x-for="opcId in asignadas" :key="opcId">
                                <div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-[0_4px_10px_-4px_rgba(0,0,0,0.05)] transition-all">
                                    <button @click="desasignarOpcion(opcId)" 
                                            class="w-7 h-7 bg-red-50 hover:bg-red-600 text-red-600 hover:text-white border border-red-150 rounded-lg flex items-center justify-center transition-all shadow-sm active:scale-95 mr-3 shrink-0"
                                            title="Retirar Permiso">
                                        <i class="fas fa-arrow-left text-[10px]"></i>
                                    </button>
                                    
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-black text-gray-800" x-text="getOpcionName(opcId)"></p>
                                        <p class="text-[9px] text-gray-400 truncate" x-text="getOpcionRoute(opcId)"></p>
                                    </div>
                                </div>
                            </template>

                            <template x-if="asignadas.length === 0">
                                <p class="text-[10px] text-gray-400 font-bold italic text-center py-12">El perfil seleccionado no tiene opciones asignadas</p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </main>

    <!-- Toast Notifications Container -->
    <div class="fixed bottom-5 right-5 z-[200] flex flex-col gap-2 max-w-sm w-full" x-show="toast.show" x-transition x-cloak>
        <div class="p-4 rounded-2xl shadow-2xl border text-xs font-bold flex items-start gap-2.5"
             :class="toast.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
            <i class="fas" :class="toast.type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'" class="mt-0.5 text-sm"></i>
            <div class="flex-1" x-text="toast.message"></div>
            <button @click="toast.show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <script>
        function perfilOpcionManager() {
            return {
                selectedPerfil: '',
                asignadas: [],
                loading: false,
                opcionesCatalogo: @json($opcionesPorModulo),
                toast: {
                    show: false,
                    message: '',
                    type: 'success',
                    showToast(message, type = 'success') {
                        this.message = message;
                        this.type = type;
                        this.show = true;
                        setTimeout(() => {
                            this.show = false;
                        }, 4000);
                    }
                },

                init() {
                    // Flatten options list to easily search for names by ID client-side
                    this.flatOpciones = [];
                    Object.values(this.opcionesCatalogo).forEach(moduleList => {
                        moduleList.forEach(opc => {
                            this.flatOpciones.push(opc);
                        });
                    });
                },

                loadOpcionesAsignadas() {
                    if (this.selectedPerfil) {
                        this.loading = true;
                        fetch(`/mod_seguridad/seguridad-perfiles-opciones/asignadas/${this.selectedPerfil}`)
                            .then(res => {
                                if (!res.ok) throw new Error('Error al cargar opciones asignadas.');
                                return res.json();
                            })
                            .then(res => {
                                this.asignadas = res;
                                this.loading = false;
                            })
                            .catch(err => {
                                this.loading = false;
                                this.toast.showToast(err.message || 'Error de conexión.', 'error');
                            });
                    } else {
                        this.asignadas = [];
                    }
                },

                isAssigned(opcId) {
                    return this.asignadas.includes(opcId);
                },

                getOpcionName(opcId) {
                    const match = this.flatOpciones.find(o => o.cdgo_rle == opcId);
                    return match ? match.dc_rle : 'OPCIÓN #' + opcId;
                },

                getOpcionRoute(opcId) {
                    const match = this.flatOpciones.find(o => o.cdgo_rle == opcId);
                    return match ? match.de_dreccon : '';
                },

                asignarOpcion(opcId) {
                    if (!this.selectedPerfil) return;

                    fetch('/mod_seguridad/seguridad-perfiles-opciones/asignar', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ cdgo_prfle: this.selectedPerfil, cdgo_rle: opcId })
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(data => {
                                throw new Error(data.message || 'Error al asignar permiso.');
                            }).catch(() => {
                                throw new Error('Error al asignar permiso.');
                            });
                        }
                        return res.json();
                    })
                    .then(res => {
                        if (res.success) {
                            this.toast.showToast(res.message || 'Permiso asignado correctamente.', 'success');
                            this.loadOpcionesAsignadas();
                        } else {
                            this.toast.showToast(res.message || 'Error al asignar permiso.', 'error');
                        }
                    })
                    .catch(err => {
                        this.toast.showToast(err.message || 'Error de conexión.', 'error');
                    });
                },

                desasignarOpcion(opcId) {
                    if (!this.selectedPerfil) return;

                    fetch('/mod_seguridad/seguridad-perfiles-opciones/desasignar', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ cdgo_prfle: this.selectedPerfil, cdgo_rle: opcId })
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(data => {
                                throw new Error(data.message || 'Error al retirar permiso.');
                            }).catch(() => {
                                throw new Error('Error al retirar permiso.');
                            });
                        }
                        return res.json();
                    })
                    .then(res => {
                        if (res.success) {
                            this.toast.showToast(res.message || 'Permiso retirado correctamente.', 'success');
                            this.loadOpcionesAsignadas();
                        } else {
                            this.toast.showToast(res.message || 'Error al retirar permiso.', 'error');
                        }
                    })
                    .catch(err => {
                        this.toast.showToast(err.message || 'Error de conexión.', 'error');
                    });
                }
            }
        }
    </script>
</body>
</html>