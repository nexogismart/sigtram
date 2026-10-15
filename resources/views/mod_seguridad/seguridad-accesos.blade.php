<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Control de Accesos y Privilegios — SIGTRAM</title>
    
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
    @include('components.sigtram.module-head', ['screenKey' => 'mod_seguridad.seguridad-accesos'])
</head>
<body data-sigtram-screen="mod_seguridad.seguridad-accesos" class="sigtram-module bg-gray-50 text-gray-900 antialiased" x-data="accesoManager()">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_seguridad.seguridad-accesos'])

    <main class="p-4 sm:p-6 w-full max-w-7xl mx-auto min-h-screen flex flex-col">
        
        <!-- Breadcrumbs -->
        <nav class="mb-5 flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <li><a href="/inicio" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li><a href="#" class="hover:text-blue-600 transition-colors">Seguridad</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600">Control de Accesos y Privilegios</li>
            </ol>
        </nav>

        <!-- Header -->
        <header class="mb-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-100">
                    <i class="fas fa-user-shield text-xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Control de Accesos y Privilegios</h1>
                    <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-widest font-black">Activar accesos y asignar cargos por áreas de la municipalidad</p>
                </div>
            </div>
        </header>

        <!-- 3-Column Dashboard Container -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch flex-1">
            
            <!-- Columna 1: Usuarios Registrados (Sin acceso) -->
            <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col h-[650px] overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-200 px-5 py-4 flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-500">Sin Acceso</h3>
                    <span class="px-2 py-0.5 bg-red-50 text-red-600 text-[10px] font-black rounded-full border border-red-100" x-text="usuariosSinAcceso.length">0</span>
                </div>
                
                <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-2">
                    <template x-for="u in usuariosSinAcceso" :key="u.cdgo_lgin">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-150 hover:bg-gray-100/60 transition-all">
                            <div class="flex-1 min-w-0 pr-2">
                                <p class="text-xs font-black text-gray-800 truncate" x-text="u.no_crto"></p>
                                <p class="text-[9px] text-gray-400 font-bold uppercase mt-0.5" x-text="'Login: ' + u.de_lgin"></p>
                            </div>
                            <button @click="toggleAcceso(u.cdgo_lgin, '1')" 
                                    class="w-7 h-7 bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white rounded-lg flex items-center justify-center transition-all shadow-sm active:scale-95" 
                                    title="Activar Acceso">
                                <i class="fas fa-arrow-right text-[10px]"></i>
                            </button>
                        </div>
                    </template>
                    <template x-if="usuariosSinAcceso.length === 0">
                        <p class="text-[10px] text-gray-400 font-bold text-center pt-8">Ningún usuario desactivado</p>
                    </template>
                </div>
            </div>

            <!-- Columna 2: Usuarios con Acceso Activo -->
            <div class="lg:col-span-4 bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col h-[650px] overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-200 px-5 py-4 flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-500">Con Acceso Activo</h3>
                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full border border-emerald-100" x-text="usuariosConAcceso.length">0</span>
                </div>
                
                <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-2">
                    <template x-for="u in usuariosConAcceso" :key="u.cdgo_lgin">
                        <div @click="selectUser(u)" 
                             :class="selectedUser && selectedUser.cdgo_lgin === u.cdgo_lgin ? 'border-blue-500 bg-blue-50/60 ring-2 ring-blue-100' : 'border-gray-200 bg-white hover:bg-gray-50'"
                             class="flex items-center justify-between p-3 rounded-xl border transition-all cursor-pointer shadow-sm group">
                            
                            <button @click.stop="toggleAcceso(u.cdgo_lgin, '0')" 
                                    class="w-7 h-7 bg-red-50 hover:bg-red-600 text-red-600 hover:text-white rounded-lg flex items-center justify-center transition-all mr-3 shadow-sm active:scale-95 shrink-0" 
                                    title="Suspender Acceso">
                                <i class="fas fa-arrow-left text-[10px]"></i>
                            </button>
                            
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-black text-gray-800 truncate" x-text="u.no_crto"></p>
                                <p class="text-[9px] text-gray-400 font-bold uppercase mt-0.5" x-text="'Perfil: ' + u.nc_prfle"></p>
                            </div>
                            
                            <i class="fas fa-chevron-right text-[10px] text-gray-300 group-hover:text-blue-500 transition-colors ml-2 shrink-0"></i>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Columna 3: Asignación de Cargos por Áreas -->
            <div class="lg:col-span-5 bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col h-[650px] overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-200 px-5 py-4 flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-500">Asignar Cargos</h3>
                    <span x-show="selectedUser" x-cloak class="text-[10px] font-black uppercase tracking-wider text-blue-600" x-text="'Para: ' + selectedUser?.no_crto"></span>
                </div>
                
                <div class="p-5 space-y-4 border-b border-gray-100 bg-gray-50/40">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Seleccionar Área Organizativa</label>
                        <div class="relative">
                            <select x-model="selectedArea" @change="loadCargosArea()" :disabled="!selectedUser"
                                    class="w-full px-4 py-2.5 bg-white border border-gray-300 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <option value="">-- SELECCIONE --</option>
                                <template x-for="a in areas" :key="a.cdgo_area">
                                    <option :value="a.cdgo_area" x-text="a.de_area"></option>
                                </template>
                            </select>
                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                        </div>
                    </div>

                    <!-- Cargos Disponibles en el Área -->
                    <div x-show="selectedArea" x-cloak class="space-y-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">Cargos en esta Área</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="c in cargosDisponibles" :key="c.cdgo_crgo">
                                <button @click="asignarCargo(c.cdgo_crgo)" 
                                        class="px-3 py-1.5 bg-blue-50 hover:bg-blue-600 text-blue-700 hover:text-white border border-blue-150 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all shadow-sm active:scale-95">
                                    + <span x-text="c.de_crgo"></span>
                                </button>
                            </template>
                            <template x-if="cargosDisponibles.length === 0">
                                <p class="text-[10px] text-gray-400 font-bold italic py-1">No hay cargos registrados en esta área</p>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Cargos Asignados al Usuario Seleccionado -->
                <div class="flex-1 overflow-y-auto custom-scrollbar p-5">
                    <template x-if="!selectedUser">
                        <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                            <i class="fas fa-mouse-pointer text-3xl text-gray-200 mb-4 animate-bounce"></i>
                            <p class="text-[10px] font-black uppercase tracking-widest text-center">Selecciona un usuario de la lista activa para ver o asignar cargos</p>
                        </div>
                    </template>

                    <template x-if="selectedUser">
                        <div class="space-y-4">
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b pb-1">Cargos Asignados</h4>
                            
                            <div class="space-y-3">
                                <template x-for="c in cargosAsignados" :key="c.cdgo_crgo_lgin">
                                    <div class="bg-white rounded-xl p-4 border border-gray-200 shadow-sm flex items-center justify-between gap-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-black text-gray-800" x-text="c.de_crgo"></p>
                                            <p class="text-[9px] text-gray-400 font-bold uppercase mt-0.5" x-text="'Área: ' + c.de_area"></p>
                                        </div>
                                        
                                        <!-- Opciones de Gestión del Cargo -->
                                        <div class="flex items-center gap-4 shrink-0">
                                            <div class="flex items-center gap-1.5">
                                                <input type="radio" :name="'primary_cargo_' + selectedUser.cdgo_lgin" 
                                                       :checked="c.in_crgo_prmro === '1'" @change="cambiarCargoPrincipal(c.cdgo_crgo_lgin)"
                                                       class="w-3.5 h-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 cursor-pointer">
                                                <span class="text-[8px] font-black uppercase tracking-widest text-gray-400">Jefe</span>
                                            </div>
                                            
                                            <button @click="eliminarCargo(c.cdgo_crgo_lgin)" 
                                                    class="w-7 h-7 bg-red-50 hover:bg-red-600 text-red-600 hover:text-white rounded-lg flex items-center justify-center transition-all shadow-sm"
                                                    title="Retirar Cargo">
                                                <i class="fas fa-times text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="cargosAsignados.length === 0">
                                    <p class="text-[10px] text-gray-400 font-bold italic text-center py-8">Sin cargos asignados actualmente</p>
                                </template>
                            </div>
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
        function accesoManager() {
            return {
                usuariosSinAcceso: @json($usuariosSinAcceso),
                usuariosConAcceso: @json($usuariosConAcceso),
                areas: @json($areas),
                selectedUser: null,
                selectedArea: '',
                cargosDisponibles: [],
                cargosAsignados: [],
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

                selectUser(user) {
                    this.selectedUser = user;
                    this.selectedArea = '';
                    this.cargosDisponibles = [];
                    this.loadCargosUsuario();
                },

                toggleAcceso(loginId, in_esta) {
                    fetch('/mod_seguridad/seguridad-accesos/toggle-acceso', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ cdgo_lgin: loginId, in_esta: in_esta })
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(data => {
                                throw new Error(data.message || 'Error al modificar acceso.');
                            }).catch(() => {
                                throw new Error('Error al modificar acceso.');
                            });
                        }
                        return res.json();
                    })
                    .then(res => {
                        if (res.success) {
                            this.toast.showToast(res.message || 'Acceso modificado correctamente.', 'success');
                            setTimeout(() => {
                                this.refreshUserLists();
                            }, 1000);
                            if (this.selectedUser && this.selectedUser.cdgo_lgin === loginId) {
                                this.selectedUser = null;
                            }
                        } else {
                            this.toast.showToast(res.message || 'Error al modificar acceso.', 'error');
                        }
                    })
                    .catch(err => {
                        this.toast.showToast(err.message || 'Error de conexión.', 'error');
                    });
                },

                refreshUserLists() {
                    fetch('/mod_seguridad/seguridad-accesos')
                        .then(res => {
                            if (!res.ok) throw new Error('Error al refrescar las listas de usuarios.');
                            return res.text();
                        })
                        .then(html => {
                            location.reload();
                        })
                        .catch(err => {
                            this.toast.showToast(err.message || 'Error al refrescar las listas.', 'error');
                        });
                },

                loadCargosArea() {
                    if (this.selectedArea) {
                        fetch(`/mod_seguridad/seguridad-accesos/cargos-area/${this.selectedArea}`)
                            .then(res => {
                                if (!res.ok) throw new Error('Error al cargar cargos del área.');
                                return res.json();
                            })
                            .then(res => {
                                this.cargosDisponibles = res;
                            })
                            .catch(err => {
                                this.toast.showToast(err.message || 'Error de conexión.', 'error');
                            });
                    } else {
                        this.cargosDisponibles = [];
                    }
                },

                loadCargosUsuario() {
                    if (this.selectedUser) {
                        fetch(`/mod_seguridad/seguridad-accesos/cargos-usuario/${this.selectedUser.cdgo_lgin}`)
                            .then(res => {
                                if (!res.ok) throw new Error('Error al cargar cargos del usuario.');
                                return res.json();
                            })
                            .then(res => {
                                this.cargosAsignados = res;
                            })
                            .catch(err => {
                                this.toast.showToast(err.message || 'Error de conexión.', 'error');
                            });
                    }
                },

                asignarCargo(cargoId) {
                    if (!this.selectedUser) return;
                    
                    fetch('/mod_seguridad/seguridad-accesos/asignar-cargo', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            cdgo_lgin: this.selectedUser.cdgo_lgin,
                            cdgo_crgo: cargoId,
                            in_crgo_prmro: '2'
                        })
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(data => {
                                throw new Error(data.message || 'Error al asignar cargo.');
                            }).catch(() => {
                                throw new Error('Error al asignar cargo.');
                            });
                        }
                        return res.json();
                    })
                    .then(res => {
                        if (res.success) {
                            this.toast.showToast(res.message || 'Cargo asignado correctamente.', 'success');
                            this.loadCargosUsuario();
                        } else {
                            this.toast.showToast(res.message || 'Error al asignar cargo.', 'error');
                        }
                    })
                    .catch(err => {
                        this.toast.showToast(err.message || 'Error de conexión.', 'error');
                    });
                },

                eliminarCargo(cdgo_crgo_lgin) {
                    if (!confirm('¿Está seguro de retirar este cargo al usuario?')) return;

                    fetch('/mod_seguridad/seguridad-accesos/eliminar-cargo', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ cdgo_crgo_lgin: cdgo_crgo_lgin })
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(data => {
                                throw new Error(data.message || 'Error al retirar cargo.');
                            }).catch(() => {
                                throw new Error('Error al retirar cargo.');
                            });
                        }
                        return res.json();
                    })
                    .then(res => {
                        if (res.success) {
                            this.toast.showToast(res.message || 'Cargo retirado correctamente.', 'success');
                            this.loadCargosUsuario();
                        } else {
                            this.toast.showToast(res.message || 'Error al retirar cargo.', 'error');
                        }
                    })
                    .catch(err => {
                        this.toast.showToast(err.message || 'Error de conexión.', 'error');
                    });
                },

                cambiarCargoPrincipal(cdgo_crgo_lgin) {
                    if (!this.selectedUser) return;

                    fetch('/mod_seguridad/seguridad-accesos/cambiar-principal', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            cdgo_crgo_lgin: cdgo_crgo_lgin,
                            cdgo_lgin: this.selectedUser.cdgo_lgin
                        })
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(data => {
                                throw new Error(data.message || 'Error al cambiar cargo principal.');
                            }).catch(() => {
                                throw new Error('Error al cambiar cargo principal.');
                            });
                        }
                        return res.json();
                    })
                    .then(res => {
                        if (res.success) {
                            this.toast.showToast(res.message || 'Cargo principal actualizado.', 'success');
                            this.loadCargosUsuario();
                        } else {
                            this.toast.showToast(res.message || 'Error al cambiar cargo principal.', 'error');
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