<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mantenimiento de Usuarios — SIGTRAM</title>
    
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
</head>
<body class="bg-gray-50 text-gray-900 antialiased" x-data="userManager()">

    <main class="p-4 sm:p-6 w-full max-w-7xl mx-auto min-h-screen flex flex-col">
        
        <!-- Breadcrumbs -->
        <nav class="mb-4 flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <li><a href="/inicio" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li><a href="#" class="hover:text-blue-600 transition-colors">Seguridad</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600">Mantenimiento de Usuarios</li>
            </ol>
        </nav>

        <!-- Header -->
        <header class="mb-5 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-100">
                    <i class="fas fa-users text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800 tracking-tight">Mantenimiento de Usuarios</h1>
                    <p class="text-[9px] text-gray-400 uppercase tracking-widest font-black">Registrar credenciales, perfiles y cargos de los servidores públicos</p>
                </div>
            </div>
            
            <div>
                <button @click="openCreateModal()" 
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center gap-2 active:scale-95 shadow-md shadow-blue-100">
                    <i class="fas fa-user-plus text-xs"></i> Nuevo Usuario
                </button>
            </div>
        </header>

        <!-- Errors/Messages -->
        @if(session('success'))
        <div class="mb-4 p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl flex items-center gap-3">
            <i class="fas fa-check-circle text-base text-emerald-500"></i>
            <span class="text-xs font-bold">{{ session('success') }}</span>
        </div>
        @endif

        @if($errors->any())
        <div class="mb-4 p-3.5 bg-red-50 border border-red-200 text-red-800 rounded-xl space-y-1">
            @foreach($errors->all() as $error)
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-xs text-red-500"></i>
                    <span class="text-xs font-bold">{{ $error }}</span>
                </div>
            @endforeach
        </div>
        @endif

        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex flex-col overflow-hidden">
            
            <!-- Filter Bar -->
            <div class="bg-gray-50/80 border-b border-gray-200 p-4">
                <form method="GET" action="{{ route('seguridad.usuario') }}" class="flex items-end gap-3">
                    <div class="w-full sm:w-80">
                        <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="criterio">Filtrar por Nombre, Login o DNI</label>
                        <input type="text" name="criterio" id="criterio" value="{{ request('criterio') }}"
                               placeholder="Buscar usuario..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-sm">
                            Buscar
                        </button>
                        @if(request()->filled('criterio'))
                            <a href="{{ route('seguridad.usuario') }}" class="px-4 py-2 bg-white hover:bg-gray-100 text-gray-700 border border-gray-200 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center justify-center">
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Table (Compact Layout) -->
            <div class="flex-1 overflow-auto custom-scrollbar p-4">
                <table class="w-full text-left border-separate border-spacing-y-2">
                    <thead>
                        <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest">
                            <th class="px-3 py-1 text-center w-10">#</th>
                            <th class="px-4 py-1">DNI / Documento</th>
                            <th class="px-4 py-1">Servidor Público</th>
                            <th class="px-4 py-1">Usuario</th>
                            <th class="px-4 py-1">Perfil</th>
                            <th class="px-4 py-1">Contacto</th>
                            <th class="px-4 py-1 text-center w-20">Estado</th>
                            <th class="px-4 py-1 text-center w-16">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usuarios as $index => $usu)
                        <tr class="bg-white hover:shadow-[0_4px_12px_-4px_rgba(0,0,0,0.05)] hover:-translate-y-0.5 transition-all duration-200">
                            <td class="px-3 py-2.5 first:rounded-l-xl border-y border-transparent text-center">
                                <span class="text-xs font-semibold text-gray-400 bg-gray-100/60 w-6 h-6 rounded-lg flex items-center justify-center font-mono mx-auto">
                                    {{ ($usuarios->currentPage() - 1) * $usuarios->perPage() + $index + 1 }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 border-y border-transparent">
                                <span class="text-xs font-mono font-bold text-gray-700 bg-gray-50 px-2 py-0.5 rounded">{{ $usu->nu_docu }}</span>
                            </td>
                            <td class="px-4 py-2.5 border-y border-transparent">
                                <span class="text-xs font-black text-gray-800">{{ $usu->no_crto }}</span>
                            </td>
                            <td class="px-4 py-2.5 border-y border-transparent">
                                <span class="text-[10px] text-blue-600 font-black tracking-wide bg-blue-50 px-2 py-0.5 rounded-full uppercase">{{ $usu->de_lgin }}</span>
                            </td>
                            <td class="px-4 py-2.5 border-y border-transparent">
                                <span class="text-[10px] text-gray-500 font-bold uppercase">{{ $usu->nc_prfle }}</span>
                            </td>
                            <td class="px-4 py-2.5 border-y border-transparent">
                                <div class="flex flex-col">
                                    <span class="text-xs text-gray-600 font-medium">{{ $usu->de_mail ?: '-' }}</span>
                                    <span class="text-[9px] text-gray-400 font-bold">{{ $usu->nu_tele ?: '-' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 border-y border-transparent text-center">
                                @if((string) $usu->lgin_esta === '1' || (int) $usu->lgin_esta === 1)
                                    <span class="inline-block px-2 py-0.5 bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-full text-[9px] font-black uppercase tracking-widest">ACTIVO</span>
                                @else
                                    <span class="inline-block px-2 py-0.5 bg-red-50 text-red-600 border border-red-100 rounded-full text-[9px] font-black uppercase tracking-widest">SUSPENDIDO</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 last:rounded-r-xl border-y border-transparent text-center">
                                <button @click="openEditModal({{ \Illuminate\Support\Js::from([
                                        'cdgo_lgin' => $usu->cdgo_lgin,
                                        'nu_docu' => $usu->nu_docu,
                                        'ap_pate' => $usu->ap_pate ?? '',
                                        'ap_mate' => $usu->ap_mate ?? '',
                                        'no_usrio' => $usu->no_usrio ?? ($usu->no_crto ?? ''),
                                        'de_lgin' => $usu->de_lgin,
                                        'cdgo_prfle' => $usu->cdgo_prfle,
                                        'de_mail' => $usu->de_mail,
                                        'nu_tele' => $usu->nu_tele,
                                        'de_dire' => $usu->de_dire ?? '',
                                        'in_esta' => ((string) $usu->lgin_esta === '0' || (int) $usu->lgin_esta === 0) ? '0' : '1',
                                    ]) }})"
                                        class="w-7 h-7 flex items-center justify-center bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white rounded-lg transition-all mx-auto shadow-sm active:scale-95"
                                        title="Editar">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center text-gray-400">
                                <div class="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mb-4 mx-auto border border-gray-100">
                                    <i class="fas fa-inbox text-2xl text-gray-250"></i>
                                </div>
                                <p class="text-[11px] font-black text-gray-500 uppercase tracking-widest">Sin usuarios registrados</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer Pagination -->
            @if($usuarios->total() > 0)
            <footer class="p-4 bg-white border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-[9px] font-black text-gray-400 uppercase tracking-widest">
                    Página {{ $usuarios->currentPage() }} de {{ $usuarios->lastPage() }} — {{ $usuarios->total() }} TOTAL
                </div>
                <div>
                    {{ $usuarios->appends(request()->query())->links() }}
                </div>
            </footer>
            @endif
        </div>
    </main>

    <!-- Modal Form (AlpineJS) -->
    <div x-show="openModal" x-cloak class="fixed inset-0 z-[150] overflow-hidden flex items-center justify-center p-4">
        <div x-show="openModal" x-transition.opacity @click="closeModal()" class="absolute inset-0 bg-gray-900/40 backdrop-blur-md"></div>

        <div x-show="openModal" x-transition.scale class="relative bg-white rounded-2xl shadow-2xl overflow-hidden max-w-2xl w-full border border-white/50">
            
            <!-- Modal Loader -->
            <div x-show="modalLoading" class="absolute inset-0 bg-white/85 backdrop-blur-sm z-[160] flex items-center justify-center">
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-[9px] font-black text-blue-700 uppercase tracking-widest mt-4">Procesando información...</p>
                </div>
            </div>

            <div class="bg-gray-50/80 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-md shadow-blue-100">
                        <i class="fas fa-user-plus text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-gray-800 uppercase tracking-widest" x-text="modalTitle"></h3>
                        <p class="text-[8px] text-gray-400 font-bold uppercase mt-0.5">Formulario de Registro y Accesos</p>
                    </div>
                </div>
                <button @click="closeModal()" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-500 rounded-lg transition-all">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('seguridad.usuario.store') }}" autocomplete="off" @submit="prepareSubmit($event)" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto custom-scrollbar">
                @csrf
                <input type="hidden" name="cdgo_lgin" :value="formData.cdgo_lgin || ''">
                <input type="hidden" name="in_esta" x-ref="inEsta" value="1">

                <!-- Sección Datos Personales -->
                <div class="space-y-3">
                    <h4 class="text-[9px] font-black text-blue-600 uppercase tracking-widest border-b pb-1">1. Datos del Servidor</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="nu_docu">Número DNI</label>
                            <div class="relative">
                                <input type="text" name="nu_docu" id="nu_docu" x-model="formData.nu_docu" required maxlength="8"
                                       @keydown="if (['e', 'E', '+', '-', '.'].includes($event.key)) $event.preventDefault(); if (!$event.metaKey && !$event.ctrlKey && $event.key.length === 1 && !/\d/.test($event.key)) $event.preventDefault();"
                                       @input="formData.nu_docu = formData.nu_docu.replace(/\D/g, '')"
                                       @blur="buscarPorDni()" placeholder="DNI de 8 dígitos"
                                       class="w-full pl-3 pr-9 py-2 border border-gray-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                                <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-600 cursor-pointer text-xs" @click="buscarPorDni()"></i>
                            </div>
                            <p x-show="formData.nu_docu && !/^\d{8}$/.test(formData.nu_docu)" x-cloak class="text-[10px] text-red-500 font-bold mt-1">El DNI debe tener exactamente 8 dígitos numéricos.</p>
                        </div>
                        
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="no_usrio">Nombres</label>
                            <input type="text" name="no_usrio" id="no_usrio" x-model="formData.no_usrio" required
                                   @blur="formData.no_usrio = formData.no_usrio.trim().replace(/\s+/g, ' ')"
                                   placeholder="Nombres completos"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-bold uppercase focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                            <p x-show="formData.no_usrio && !/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]+$/.test(formData.no_usrio)" x-cloak class="text-[10px] text-red-500 font-bold mt-1">Solo letras, espacios, guiones o apóstrofes.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="ap_pate">Apellido Paterno</label>
                            <input type="text" name="ap_pate" id="ap_pate" x-model="formData.ap_pate" required
                                   @blur="formData.ap_pate = formData.ap_pate.trim().replace(/\s+/g, ' ')"
                                   placeholder="Apellido Paterno"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-bold uppercase focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                            <p x-show="formData.ap_pate && !/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]+$/.test(formData.ap_pate)" x-cloak class="text-[10px] text-red-500 font-bold mt-1">Solo letras, espacios, guiones o apóstrofes.</p>
                        </div>

                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="ap_mate">Apellido Materno</label>
                            <input type="text" name="ap_mate" id="ap_mate" x-model="formData.ap_mate" required
                                   @blur="formData.ap_mate = formData.ap_mate.trim().replace(/\s+/g, ' ')"
                                   placeholder="Apellido Materno"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-bold uppercase focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                            <p x-show="formData.ap_mate && !/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]+$/.test(formData.ap_mate)" x-cloak class="text-[10px] text-red-500 font-bold mt-1">Solo letras, espacios, guiones o apóstrofes.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="de_dire">Dirección Residencial</label>
                            <input type="text" name="de_dire" id="de_dire" x-model="formData.de_dire"
                                   placeholder="Calle, Av, Jr..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="nu_tele">Teléfono / Celular</label>
                            <input type="text" name="nu_tele" id="nu_tele" x-model="formData.nu_tele"
                                   @keydown="if (['e', 'E', '+', '-', '.'].includes($event.key)) $event.preventDefault(); if (!$event.metaKey && !$event.ctrlKey && $event.key.length === 1 && !/\d/.test($event.key)) $event.preventDefault();"
                                   @input="formData.nu_tele = formData.nu_tele.replace(/\D/g, '').substring(0,9)"
                                   placeholder="999888777"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                            <p x-show="formData.nu_tele && !/^9\d{8}$/.test(formData.nu_tele)" x-cloak class="text-[10px] text-red-500 font-bold mt-1">El celular debe ser de 9 dígitos y empezar con 9.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="de_mail">Correo Electrónico</label>
                        <input type="email" name="de_mail" id="de_mail" x-model="formData.de_mail"
                               @blur="formData.de_mail = formData.de_mail.trim().toLowerCase()"
                               placeholder="servidor@muni.gob.pe"
                               class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                        <p x-show="formData.de_mail && (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.de_mail) || formData.de_mail.length > 254)" x-cloak class="text-[10px] text-red-500 font-bold mt-1">Correo inválido o mayor a 254 caracteres.</p>
                    </div>
                </div>

                <!-- Sección Acceso al Sistema -->
                <div class="space-y-3">
                    <h4 class="text-[9px] font-black text-blue-600 uppercase tracking-widest border-b pb-1">2. Credenciales y Perfil de Acceso</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="de_lgin">Usuario / Login</label>
                            <input type="text" name="de_lgin" id="de_lgin" x-model="formData.de_lgin" :required="!esEdicion"
                                   @input="formData.de_lgin = formData.de_lgin.toLowerCase().replace(/[^a-z0-9_.-]/g, '')"
                                   placeholder="Ej: jperez" :readonly="esEdicion"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-bold lowercase focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm disabled:opacity-60 disabled:bg-gray-100 read-only:opacity-60 read-only:bg-gray-100">
                            <p x-show="formData.de_lgin && !/^[a-z0-9_.-]+$/.test(formData.de_lgin)" x-cloak class="text-[10px] text-red-500 font-bold mt-1">El usuario debe estar en minúsculas y sin caracteres especiales o espacios.</p>
                        </div>

                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="cdgo_prfle">Perfil de Seguridad</label>
                            <div class="relative">
                                <select name="cdgo_prfle" id="cdgo_prfle" x-model="formData.cdgo_prfle" required
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                    <option value="">-- SELECCIONE --</option>
                                    @foreach($perfiles as $p)
                                        <option value="{{ $p->cdgo_prfle }}">{{ $p->nc_prfle }}</option>
                                    @endforeach
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="de_pass" x-text="esEdicion ? 'Nueva Contraseña (Opcional)' : 'Contraseña'"></label>
                            <input type="password" name="de_pass" id="de_pass" x-model="formData.de_pass" :required="!esEdicion"
                                   autocomplete="new-password" readonly @focus="$el.removeAttribute('readonly')"
                                   :placeholder="esEdicion ? 'Dejar vacío para no cambiar' : 'Mínimo 4 caracteres'"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                            <p x-show="formData.de_pass && formData.de_pass.length < 4" x-cloak class="text-[10px] text-red-500 font-bold mt-1">Mínimo 4 caracteres.</p>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="de_pass_confirmation" x-text="esEdicion ? 'Repetir nueva contraseña' : 'Repetir contraseña'"></label>
                            <input type="password" name="de_pass_confirmation" id="de_pass_confirmation" x-model="formData.de_pass_confirmation" :required="!esEdicion"
                                   autocomplete="new-password" readonly @focus="$el.removeAttribute('readonly')"
                                   placeholder="Repita la contraseña"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm">
                            <p x-show="formData.de_pass && formData.de_pass_confirmation && formData.de_pass !== formData.de_pass_confirmation" x-cloak class="text-[10px] text-red-500 font-bold mt-1">Las contraseñas no coinciden.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1" for="cdgo_cmpnia">Compañía / Municipalidad</label>
                            <div class="relative">
                                <select name="cdgo_cmpnia" id="cdgo_cmpnia" x-model="formData.cdgo_cmpnia" required
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                    <option value="">-- SELECCIONE --</option>
                                    @foreach($companias as $c)
                                        <option value="{{ $c->cdgo_cmpnia }}">{{ $c->de_cmpnia }}</option>
                                    @endforeach
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="in_esta">Estado de Acceso</label>
                            <div class="relative">
                                <select id="in_esta" x-model="formData.in_esta" required
                                        @change="syncEstadoAcceso()"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                    <option value="1">ACTIVO</option>
                                    <option value="0">INACTIVO / SUSPENDIDO</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección Asignación de Cargos Directa -->
                <div class="space-y-3">
                    <h4 class="text-[9px] font-black text-blue-600 uppercase tracking-widest border-b pb-1">3. Asignación Directa de Cargos</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1">Área / Unidad Orgánica</label>
                            <div class="relative">
                                <select x-model="tempArea" @change="loadCargosArea()"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-[11px] font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                    <option value="">-- SELECCIONE ÁREA --</option>
                                    @foreach($areas as $area)
                                        <option value="{{ $area->cdgo_area }}">{{ $area->de_area }}</option>
                                    @endforeach
                                </select>
                                <i class="fas fa-chevron-down absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <select x-model="tempCargo" :disabled="!tempArea"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-xl text-[11px] font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all shadow-sm appearance-none cursor-pointer disabled:opacity-50">
                                    <option value="">-- SELECCIONE CARGO --</option>
                                    <template x-for="c in tempCargosList" :key="c.cdgo_crgo">
                                        <option :value="c.cdgo_crgo" x-text="c.de_crgo"></option>
                                    </template>
                                </select>
                                <i class="fas fa-chevron-down absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                            
                            <button type="button" @click="addCargoToList()" :disabled="!tempCargo"
                                    class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-sm text-xs font-bold flex items-center justify-center active:scale-95 disabled:opacity-50">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Lista de Cargos Seleccionados -->
                    <div class="border border-gray-200 rounded-xl p-3 bg-gray-50/40 space-y-2">
                        <label class="block text-[8px] font-black text-gray-400 uppercase tracking-widest ml-1">Cargos a Asignar</label>
                        
                        <div class="space-y-1.5 max-h-[150px] overflow-y-auto custom-scrollbar">
                            <template x-for="c in selectedCargos" :key="c.cdgo_crgo">
                                <div class="flex items-center justify-between bg-white px-3 py-2 rounded-lg border border-gray-200 shadow-sm text-[11px]">
                                    <!-- Campos ocultos para enviar al controlador en el form submit -->
                                    <input type="hidden" name="cargos[]" :value="c.cdgo_crgo">
                                    
                                    <div class="flex-1 min-w-0 pr-2">
                                        <p class="font-bold text-gray-800 truncate" x-text="c.de_crgo"></p>
                                        <p class="text-[9px] text-gray-400 uppercase tracking-wider" x-text="'Área: ' + c.de_area"></p>
                                    </div>
                                    
                                    <div class="flex items-center gap-3 shrink-0">
                                        <div class="flex items-center gap-1.5">
                                            <input type="radio" name="cargo_principal" :value="c.cdgo_crgo" :checked="cargoPrincipalId == c.cdgo_crgo" @change="cargoPrincipalId = c.cdgo_crgo"
                                                   class="w-3.5 h-3.5 text-blue-600 focus:ring-blue-500 border-gray-300 cursor-pointer">
                                            <span class="text-[8px] font-black uppercase text-gray-400">Jefe</span>
                                        </div>
                                        
                                        <button type="button" @click="removeCargoFromList(c.cdgo_crgo)" 
                                                class="text-red-500 hover:text-red-700 p-1">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="selectedCargos.length === 0">
                                <p class="text-[10px] text-gray-400 font-bold italic text-center py-2">Ningún cargo seleccionado</p>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
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
        function userManager() {
            return {
                openModal: false,
                modalTitle: 'Nuevo Usuario',
                modalLoading: false,
                tempArea: '',
                tempCargo: '',
                tempCargosList: [],
                selectedCargos: [],
                cargoPrincipalId: '',
                areas: @json($areas),
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
                    @if(session('success'))
                        this.toast.showToast("{{ session('success') }}", 'success');
                    @endif
                    @if($errors->any())
                        @foreach($errors->all() as $error)
                            this.toast.showToast("{{ $error }}", 'error');
                        @endforeach
                    @endif
                },
                formData: {
                    cdgo_lgin: '',
                    nu_docu: '',
                    ap_pate: '',
                    ap_mate: '',
                    no_usrio: '',
                    de_dire: '',
                    nu_tele: '',
                    de_mail: '',
                    de_lgin: '',
                    cdgo_prfle: '',
                    cdgo_cmpnia: '',
                    de_pass: '',
                    de_pass_confirmation: '',
                    in_esta: '1'
                },
                get esEdicion() {
                    return this.formData.cdgo_lgin !== '' && this.formData.cdgo_lgin !== null && this.formData.cdgo_lgin !== 0;
                },
                estadoAccesoValor() {
                    return String(this.formData.in_esta) === '0' ? '0' : '1';
                },
                syncEstadoAcceso() {
                    this.formData.in_esta = this.estadoAccesoValor();
                    if (this.$refs.inEsta) {
                        this.$refs.inEsta.value = this.formData.in_esta;
                    }
                },
                prepareSubmit(e) {
                    this.syncEstadoAcceso();
                    if (!this.validateForm(e)) {
                        e.preventDefault();
                    }
                },
                validateForm(e) {
                    // DNI validation
                    if (!/^\d{8}$/.test(this.formData.nu_docu)) {
                        this.toast.showToast('El DNI debe tener exactamente 8 dígitos.', 'error');
                        return false;
                    }
                    // Nombres
                    if (!/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]+$/.test(this.formData.no_usrio)) {
                        this.toast.showToast('Los nombres contienen caracteres no permitidos.', 'error');
                        return false;
                    }
                    // Apellido Paterno
                    if (!/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]+$/.test(this.formData.ap_pate)) {
                        this.toast.showToast('El apellido paterno contiene caracteres no permitidos.', 'error');
                        return false;
                    }
                    // Apellido Materno
                    if (!/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü' -]+$/.test(this.formData.ap_mate)) {
                        this.toast.showToast('El apellido materno contiene caracteres no permitidos.', 'error');
                        return false;
                    }
                    // Correo
                    if (this.formData.de_mail && (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.formData.de_mail) || this.formData.de_mail.length > 254)) {
                        this.toast.showToast('El correo electrónico no es válido.', 'error');
                        return false;
                    }
                    // Login
                    if (!/^[a-z0-9_.-]+$/.test(this.formData.de_lgin)) {
                        this.toast.showToast('El usuario debe estar en minúsculas y sin caracteres especiales.', 'error');
                        return false;
                    }
                    // Contraseña: en edición es opcional (activar/inactivar no exige cambiarla)
                    const clave = (this.formData.de_pass || '').trim();
                    const clave2 = (this.formData.de_pass_confirmation || '').trim();
                    if (this.esEdicion) {
                        if (clave.length === 0 && clave2.length === 0) {
                            return true;
                        }
                        if (clave.length === 0 || clave2.length === 0) {
                            this.formData.de_pass = '';
                            this.formData.de_pass_confirmation = '';
                            return true;
                        }
                    }
                    if (clave.length < 4) {
                        this.toast.showToast('La contraseña debe tener al menos 4 caracteres.', 'error');
                        return false;
                    }
                    if (clave !== clave2) {
                        this.toast.showToast('La contraseña y su confirmación no coinciden.', 'error');
                        return false;
                    }
                    return true;
                },
                openCreateModal() {
                    this.modalTitle = 'Nuevo Usuario';
                    this.tempArea = '';
                    this.tempCargo = '';
                    this.tempCargosList = [];
                    this.selectedCargos = [];
                    this.cargoPrincipalId = '';
                    this.formData = {
                        cdgo_lgin: '', nu_docu: '', ap_pate: '', ap_mate: '', no_usrio: '',
                        de_dire: '', nu_tele: '', de_mail: '', de_lgin: '', cdgo_prfle: '',
                        cdgo_cmpnia: '2', de_pass: '', de_pass_confirmation: '', in_esta: '1'
                    };
                    this.$nextTick(() => this.syncEstadoAcceso());
                    this.openModal = true;
                },
                openEditModal(user) {
                    this.modalTitle = 'Editar Usuario';
                    this.modalLoading = true;
                    this.tempArea = '';
                    this.tempCargo = '';
                    this.tempCargosList = [];
                    this.selectedCargos = [];
                    this.cargoPrincipalId = '';

                    // Inicializar inmediatamente formData con los datos exactos del usuario seleccionado
                    this.formData = {
                        cdgo_lgin: user.cdgo_lgin,
                        nu_docu: user.nu_docu || '',
                        ap_pate: user.ap_pate || '',
                        ap_mate: user.ap_mate || '',
                        no_usrio: user.no_usrio || '',
                        de_dire: user.de_dire || '',
                        nu_tele: user.nu_tele || '',
                        de_mail: user.de_mail || '',
                        de_lgin: user.de_lgin || '',
                        cdgo_prfle: user.cdgo_prfle || '',
                        cdgo_cmpnia: '2',
                        de_pass: '',
                        de_pass_confirmation: '',
                        in_esta: String(user.in_esta) === '0' ? '0' : '1'
                    };

                    this.openModal = true;
                    this.$nextTick(() => this.syncEstadoAcceso());
                    setTimeout(() => {
                        this.formData.de_pass = '';
                        this.formData.de_pass_confirmation = '';
                    }, 250);

                    // Cargar cargos del usuario editado
                    fetch(`/mod_seguridad/seguridad-accesos/cargos-usuario/${user.cdgo_lgin}`)
                        .then(res => {
                            if (!res.ok) throw new Error('Error al cargar cargos de usuario.');
                            return res.json();
                        })
                        .then(res => {
                            this.selectedCargos = res.map(c => {
                                if (c.in_crgo_prmro === '1') {
                                    this.cargoPrincipalId = c.cdgo_crgo;
                                }
                                return {
                                    cdgo_crgo: c.cdgo_crgo,
                                    de_crgo: c.de_crgo,
                                    de_area: c.de_area
                                };
                            });
                            this.modalLoading = false;
                        })
                        .catch(err => {
                            this.modalLoading = false;
                            this.toast.showToast(err.message || 'Error al editar usuario.', 'error');
                        });
                },
                buscarPorDni() {
                    const dni = this.formData.nu_docu;
                    if (dni && dni.length === 8) {
                        this.modalLoading = true;
                        fetch(`/mod_seguridad/seguridad-usuario/buscar-dni/${dni}`)
                            .then(res => {
                                if (!res.ok) throw new Error('Error al consultar DNI.');
                                return res.json();
                            })
                            .then(res => {
                                if (res.success) {
                                    this.formData.ap_pate = res.user.ap_pate;
                                    this.formData.ap_mate = res.user.ap_mate;
                                    this.formData.no_usrio = res.user.no_usrio;
                                    this.formData.de_dire = res.user.de_dire || '';
                                    this.formData.nu_tele = res.user.nu_tele || '';
                                    this.formData.de_mail = res.user.de_mail || '';
                                    this.toast.showToast('Datos de DNI cargados.', 'success');
                                } else {
                                    this.toast.showToast('DNI no encontrado en el padrón local.', 'error');
                                }
                                this.modalLoading = false;
                            })
                            .catch(err => {
                                this.modalLoading = false;
                                this.toast.showToast(err.message || 'Error al buscar DNI.', 'error');
                            });
                    }
                },
                loadCargosArea() {
                    if (this.tempArea) {
                        fetch(`/mod_seguridad/seguridad-accesos/cargos-area/${this.tempArea}`)
                            .then(res => {
                                if (!res.ok) throw new Error('Error al cargar cargos de la área.');
                                return res.json();
                            })
                            .then(res => {
                                this.tempCargosList = res;
                                this.tempCargo = '';
                            })
                            .catch(err => {
                                this.toast.showToast(err.message || 'Error de conexión.', 'error');
                            });
                    } else {
                        this.tempCargosList = [];
                        this.tempCargo = '';
                    }
                },
                addCargoToList() {
                    if (!this.tempCargo) return;

                    // Verificar si ya está agregado
                    const exists = this.selectedCargos.some(c => c.cdgo_crgo == this.tempCargo);
                    if (exists) {
                        this.toast.showToast('Este cargo ya está en la lista de asignación.', 'error');
                        return;
                    }

                    // Encontrar el nombre del cargo y área
                    const cargoObj = this.tempCargosList.find(c => c.cdgo_crgo == this.tempCargo);
                    const areaName = this.areas.find(a => a.cdgo_area == this.tempArea)?.de_area || 'Área';

                    this.selectedCargos.push({
                        cdgo_crgo: this.tempCargo,
                        de_crgo: cargoObj.de_crgo,
                        de_area: areaName
                    });

                    // Si es el primer cargo, marcarlo como principal automáticamente
                    if (this.selectedCargos.length === 1) {
                        this.cargoPrincipalId = this.tempCargo;
                    }

                    this.tempCargo = '';
                },
                removeCargoFromList(cargoId) {
                    this.selectedCargos = this.selectedCargos.filter(c => c.cdgo_crgo != cargoId);
                    
                    // Si se borró el principal, reasignarlo al primero restante
                    if (this.cargoPrincipalId == cargoId && this.selectedCargos.length > 0) {
                        this.cargoPrincipalId = this.selectedCargos[0].cdgo_crgo;
                    } else if (this.selectedCargos.length === 0) {
                        this.cargoPrincipalId = '';
                    }
                },
                closeModal() {
                    this.openModal = false;
                }
            }
        }
    </script>
</body>
</html>