<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cambiar Contraseña — SIGTRAM</title>
    
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
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_seguridad.cambiar-contraseña'])
</head>
<body data-sigtram-screen="mod_seguridad.cambiar-contraseña" class="sigtram-module bg-gray-50 text-gray-900 antialiased" x-data="passwordManager()">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_seguridad.cambiar-contraseña'])

    <main class="p-4 sm:p-6 w-full max-w-lg mx-auto min-h-screen flex flex-col justify-center">
        
        <!-- Breadcrumbs -->
        <nav class="mb-5 flex justify-start" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <li><a href="/inicio" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li><a href="#" class="hover:text-blue-600 transition-colors">Seguridad</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600">Cambiar Contraseña</li>
            </ol>
        </nav>

        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
            
            <!-- Header -->
            <div class="bg-gray-50/80 px-6 py-5 border-b border-gray-200 flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-100">
                    <i class="fas fa-key text-xl"></i>
                </div>
                <div>
                    <h1 class="text-lg font-black text-gray-800 uppercase tracking-widest">Cambiar Contraseña</h1>
                    <p class="text-[9px] text-gray-400 font-bold uppercase mt-0.5">Actualiza las credenciales de tu cuenta</p>
                    @if(!empty($maskedEmail))
                        <p class="text-[9px] text-gray-500 font-semibold uppercase mt-0.5">El código se enviará a: <span class="text-blue-600 font-bold">{{ $maskedEmail }}</span></p>
                    @endif
                </div>
            </div>

            <!-- Form -->
            <form method="POST" action="{{ route('seguridad.cambiar-contrasena.confirmar') }}" @submit="validateForm($event)" class="p-6 space-y-5">
                @csrf

                <!-- Banners de Alerta -->
                @if(session('success'))
                    <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl text-xs font-bold flex items-start gap-2.5 mb-1">
                        <i class="fas fa-check-circle text-green-500 mt-0.5 text-sm"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                @if($errors->has('error'))
                    <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl text-xs font-bold flex items-start gap-2.5 mb-1">
                        <i class="fas fa-exclamation-circle text-red-500 mt-0.5 text-sm"></i>
                        <div>{{ $errors->first('error') }}</div>
                    </div>
                @endif

                <div x-show="alertMessage" x-transition class="p-4 rounded-xl text-xs font-bold flex items-start gap-2.5 mb-1"
                     :class="alertType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'" x-cloak>
                    <i class="fas" :class="alertType === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'" class="mt-0.5 text-sm"></i>
                    <div x-text="alertMessage"></div>
                </div>

                <!-- Contraseña Actual -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="password_actual">Contraseña Actual</label>
                    <div class="relative">
                        <input :type="showActual ? 'text' : 'password'" name="password_actual" id="password_actual" x-model="formData.password_actual"
                               :class="(errors.password_actual || '{{ $errors->has('password_actual') }}') ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                               placeholder="••••••••"
                               class="w-full pl-4 pr-10 py-2.5 border rounded-xl text-sm font-semibold outline-none transition-all shadow-sm">
                        <button type="button" @click="showActual = !showActual" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas" :class="showActual ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.password_actual" x-text="errors.password_actual" x-cloak></span>
                    @error('password_actual')
                        <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Nueva Contraseña -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="nueva_contrasena">Nueva Contraseña</label>
                    <div class="relative">
                        <input :type="showNueva ? 'text' : 'password'" name="nueva_contrasena" id="nueva_contrasena" x-model="formData.nueva_contrasena"
                               :class="(errors.nueva_contrasena || '{{ $errors->has('nueva_contrasena') }}') ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                               placeholder="Al menos 8 caracteres complejos"
                               class="w-full pl-4 pr-10 py-2.5 border rounded-xl text-sm font-semibold outline-none transition-all shadow-sm">
                        <button type="button" @click="showNueva = !showNueva" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas" :class="showNueva ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                    
                    <!-- Lista de requisitos de complejidad de contraseña -->
                    <div class="mt-2 text-[10px] font-bold space-y-1.5 bg-gray-50/50 p-3 rounded-xl border border-gray-100">
                        <p class="text-gray-400 uppercase tracking-wider mb-1">Requisitos de la nueva contraseña:</p>
                        <div class="flex items-center gap-1.5" :class="meetsLength ? 'text-green-600' : 'text-gray-400'">
                            <i class="fas" :class="meetsLength ? 'fa-check-circle' : 'fa-circle-notch'"></i>
                            <span>Mínimo 8 caracteres</span>
                        </div>
                        <div class="flex items-center gap-1.5" :class="meetsUpper ? 'text-green-600' : 'text-gray-400'">
                            <i class="fas" :class="meetsUpper ? 'fa-check-circle' : 'fa-circle-notch'"></i>
                            <span>Al menos una mayúscula (A-Z)</span>
                        </div>
                        <div class="flex items-center gap-1.5" :class="meetsLower ? 'text-green-600' : 'text-gray-400'">
                            <i class="fas" :class="meetsLower ? 'fa-check-circle' : 'fa-circle-notch'"></i>
                            <span>Al menos una minúscula (a-z)</span>
                        </div>
                        <div class="flex items-center gap-1.5" :class="meetsNumber ? 'text-green-600' : 'text-gray-400'">
                            <i class="fas" :class="meetsNumber ? 'fa-check-circle' : 'fa-circle-notch'"></i>
                            <span>Al menos un número (0-9)</span>
                        </div>
                        <div class="flex items-center gap-1.5" :class="meetsSpecial ? 'text-green-600' : 'text-gray-400'">
                            <i class="fas" :class="meetsSpecial ? 'fa-check-circle' : 'fa-circle-notch'"></i>
                            <span>Al menos un símbolo especial (@$!%*?&#.)</span>
                        </div>
                    </div>

                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.nueva_contrasena" x-text="errors.nueva_contrasena" x-cloak></span>
                    @error('nueva_contrasena')
                        <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Confirmar Nueva Contraseña -->
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1" for="confirmar_nueva_contrasena">Confirmar Nueva Contraseña</label>
                    <div class="relative">
                        <input :type="showConfirmar ? 'text' : 'password'" name="confirmar_nueva_contrasena" id="confirmar_nueva_contrasena" x-model="formData.confirmar_nueva_contrasena"
                               :class="errors.confirmar_nueva_contrasena ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500'"
                               placeholder="Repite la nueva contraseña"
                               class="w-full pl-4 pr-10 py-2.5 border rounded-xl text-sm font-semibold outline-none transition-all shadow-sm">
                        <button type="button" @click="showConfirmar = !showConfirmar" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas" :class="showConfirmar ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                    <span class="text-red-600 text-[10px] font-bold mt-1 block ml-1" x-show="errors.confirmar_nueva_contrasena" x-text="errors.confirmar_nueva_contrasena" x-cloak></span>
                </div>

                <!-- Código de Verificación (Se revela al enviar código exitosamente) -->
                <div x-show="codeSent" x-transition x-cloak class="p-4 bg-blue-50/50 border border-blue-100 rounded-2xl space-y-3">
                    <label class="block text-[10px] font-black text-blue-800 uppercase tracking-widest ml-1" for="codigo_verificacion">Código de Verificación por Correo</label>
                    <div class="relative">
                        <input type="text" name="codigo_verificacion" id="codigo_verificacion" x-model="formData.codigo_verificacion" maxlength="6"
                               :class="(errors.codigo_verificacion || '{{ $errors->has('codigo_verificacion') }}') ? 'border-red-500 focus:ring-red-500 focus:border-red-500 bg-red-50/10' : 'border-blue-300 focus:ring-blue-500 focus:border-blue-500'"
                               placeholder="Ingresa el código de 6 dígitos"
                               class="w-full pl-4 pr-10 py-2.5 border bg-white rounded-xl text-sm font-bold text-center tracking-[4px] outline-none transition-all shadow-sm">
                        <div class="absolute right-3.5 top-1/2 -translate-y-1/2 text-blue-500">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center px-1">
                        <div>
                            <span class="text-red-600 text-[10px] font-bold block" x-show="errors.codigo_verificacion" x-text="errors.codigo_verificacion" x-cloak></span>
                            @error('codigo_verificacion')
                                <span class="text-red-600 text-[10px] font-bold block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="text-[10px] font-black uppercase text-gray-400">
                            <span x-show="countdown > 0">Reenviar en <span class="text-blue-600 font-extrabold" x-text="countdown"></span>s</span>
                            <button type="button" x-show="countdown === 0" @click="enviarCodigoVerificacion()" :disabled="isLoading" class="text-blue-600 hover:underline">
                                Reenviar Código
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                    <a href="/inicio" class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all flex items-center justify-center">
                        Cancelar
                    </a>
                    
                    <!-- Solicitar Código -->
                    <button type="button" x-show="!codeSent" @click="enviarCodigoVerificacion()" :disabled="isLoading" 
                            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md flex items-center gap-2">
                        <span x-text="isLoading ? 'Enviando...' : 'Solicitar Código'"></span>
                        <i class="fas fa-spinner fa-spin" x-show="isLoading" x-cloak></i>
                    </button>
                    
                    <!-- Confirmar Cambio -->
                    <button type="submit" x-show="codeSent" :disabled="isLoading" 
                            class="px-6 py-2.5 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md flex items-center gap-2">
                        <span x-text="isLoading ? 'Cambiando...' : 'Guardar Cambios'"></span>
                        <i class="fas fa-spinner fa-spin" x-show="isLoading" x-cloak></i>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        function passwordManager() {
            return {
                showActual: false,
                showNueva: false,
                showConfirmar: false,
                codeSent: {{ $errors->has('codigo_verificacion') ? 'true' : 'false' }},
                isLoading: false,
                countdown: 0,
                timer: null,
                alertMessage: '',
                alertType: '',
                formData: {
                    password_actual: '{{ old('password_actual') }}',
                    nueva_contrasena: '{{ old('nueva_contrasena') }}',
                    confirmar_nueva_contrasena: '{{ old('confirmar_nueva_contrasena') }}',
                    codigo_verificacion: '{{ old('codigo_verificacion') }}'
                },
                errors: {
                    password_actual: '',
                    nueva_contrasena: '',
                    confirmar_nueva_contrasena: '',
                    codigo_verificacion: ''
                },
                
                // Getters para verificar complejidad en tiempo real
                get meetsLength() { return this.formData.nueva_contrasena.length >= 8; },
                get meetsUpper() { return /[A-Z]/.test(this.formData.nueva_contrasena); },
                get meetsLower() { return /[a-z]/.test(this.formData.nueva_contrasena); },
                get meetsNumber() { return /[0-9]/.test(this.formData.nueva_contrasena); },
                get meetsSpecial() { return /[@$!%*?&#.]/.test(this.formData.nueva_contrasena); },
                get isPasswordValid() {
                    return this.meetsLength && this.meetsUpper && this.meetsLower && this.meetsNumber && this.meetsSpecial;
                },
                
                startCountdown() {
                    this.countdown = 60;
                    if (this.timer) {
                        clearInterval(this.timer);
                    }
                    this.timer = setInterval(() => {
                        if (this.countdown > 0) {
                            this.countdown--;
                        } else {
                            clearInterval(this.timer);
                        }
                    }, 1000);
                },
                
                enviarCodigoVerificacion() {
                    this.errors = {
                        password_actual: '',
                        nueva_contrasena: '',
                        confirmar_nueva_contrasena: '',
                        codigo_verificacion: ''
                    };
                    this.alertMessage = '';
                    
                    let isValid = true;
                    if (!this.formData.password_actual) {
                        this.errors.password_actual = 'La contraseña actual es obligatoria.';
                        isValid = false;
                    }
                    if (!this.formData.nueva_contrasena) {
                        this.errors.nueva_contrasena = 'La nueva contraseña es obligatoria.';
                        isValid = false;
                    } else if (!this.isPasswordValid) {
                        this.errors.nueva_contrasena = 'La contraseña debe cumplir con todos los requisitos de complejidad.';
                        isValid = false;
                    }
                    if (!this.formData.confirmar_nueva_contrasena) {
                        this.errors.confirmar_nueva_contrasena = 'Por favor confirma la nueva contraseña.';
                        isValid = false;
                    } else if (this.formData.nueva_contrasena !== this.formData.confirmar_nueva_contrasena) {
                        this.errors.confirmar_nueva_contrasena = 'Las contraseñas no coinciden.';
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        // Hacer scroll al primer error
                        this.$nextTick(() => {
                            const errorEl = document.querySelector('.border-red-500');
                            if (errorEl) errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        });
                        return;
                    }
                    
                    this.isLoading = true;
                    
                    fetch('{{ route('seguridad.cambiar-contrasena.enviar-codigo') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            password_actual: this.formData.password_actual,
                            nueva_contrasena: this.formData.nueva_contrasena,
                            confirmar_nueva_contrasena: this.formData.confirmar_nueva_contrasena
                        })
                    })
                    .then(res => {
                        if (res.status === 422) {
                            return res.json().then(data => {
                                if (data.errors) {
                                    Object.keys(data.errors).forEach(key => {
                                        this.errors[key] = data.errors[key][0];
                                    });
                                }
                                throw new Error('Validación fallida');
                            });
                        }
                        if (!res.ok) {
                            return res.json().then(data => {
                                throw new Error(data.message || 'Error de conexión');
                            }).catch(() => {
                                throw new Error('Error en el servidor');
                            });
                        }
                        return res.json();
                    })
                    .then(data => {
                        this.isLoading = false;
                        this.codeSent = true;
                        this.alertMessage = data.message;
                        this.alertType = 'success';
                        this.startCountdown();
                    })
                    .catch(err => {
                        this.isLoading = false;
                        if (err.message !== 'Validación fallida') {
                            this.alertMessage = err.message || 'Error al enviar el código. Por favor reintente.';
                            this.alertType = 'error';
                        }
                    });
                },
                
                validateForm(event) {
                    this.errors.codigo_verificacion = '';
                    
                    // Si el código no ha sido enviado, el botón de enviar código hace todo por AJAX
                    if (!this.codeSent) {
                        event.preventDefault();
                        this.enviarCodigoVerificacion();
                        return;
                    }
                    
                    // Si ya fue enviado, validamos el código de verificación al subir
                    let isValid = true;
                    if (!this.formData.codigo_verificacion) {
                        this.errors.codigo_verificacion = 'El código de verificación es obligatorio.';
                        isValid = false;
                    } else if (this.formData.codigo_verificacion.length !== 6 || !/^\d+$/.test(this.formData.codigo_verificacion)) {
                        this.errors.codigo_verificacion = 'El código de verificación debe contener exactamente 6 dígitos numéricos.';
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        event.preventDefault();
                    } else {
                        this.isLoading = true;
                    }
                }
            }
        }
    </script>
</body>
</html>