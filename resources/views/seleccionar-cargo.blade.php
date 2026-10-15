<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <script>
        // Evitar que la selección de cargo se cargue dentro de un iframe
        if (window.self !== window.top) {
            window.top.location.href = window.self.location.href;
        }
    </script>
    <meta name="theme-color" content="#f1f5f9">
    <title>Seleccionar Cargo — SIGTRAM</title>
    
    <!-- Fuentes e Iconos -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-out forwards',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        /* Custom Scrollbar minimalista */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'seleccionar-cargo'])
</head>

<body data-sigtram-screen="seleccionar-cargo" class="sigtram-module sigtram-role-selection">
@include('components.sigtram.module-bar', ['screenKey' => 'seleccionar-cargo'])
<main class="sigtram-role-card">
    <aside class="sigtram-role-intro"><img src="{{ asset('images/nexosigtram-documental-emblema.png') }}" alt="" width="68" height="68"><h1>Elige dónde<br>vas a gestionar.</h1><p>Cada cargo reúne los trámites y las herramientas que tienes asignados.</p><div class="sigtram-role-note">Tu área y tus permisos se aplicarán al ingresar.</div></aside>
    <section class="sigtram-role-options" aria-labelledby="role-title"><div class="sigtram-role-user"><span>{{ $usuario }}</span><form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="border border-slate-200 rounded-full px-4 py-2 flex items-center gap-2 text-[10px] font-bold text-slate-500 hover:bg-red-50 hover:text-red-600 hover:border-red-200 cursor-pointer transition-colors shadow-sm">
                        <i class="fas fa-power-off"></i> Cerrar sesión
                    </button>
                </form></div><h2 id="role-title">Selecciona tu cargo</h2><p>Busca por nombre del cargo o del área.</p><label class="sigtram-role-search" for="buscador">Buscar cargo o área<input type="text" id="buscador" oninput="filtrarCargos()" autofocus placeholder="Escribe el cargo o el área..."></label>
@if ($errors->any())<p class="sigtram-role-error" role="alert">{{ $errors->first() }}</p>@endif
<div id="lista-cargos" class="sigtram-role-list">                        @foreach ($cargos as $index => $cargo)
                            <form method="POST" action="{{ route('seleccionar.cargo.guardar') }}" class="cargo-item" onsubmit="showLoading(this)">
                                @csrf
                                <input type="hidden" name="cargo_id" value="{{ $cargo->cdgo_crgo }}">
                                
                                <!-- Botón Estilo "Login Secundario" -->
                                <button type="submit" class="w-full py-3.5 px-4 bg-white border border-slate-200 hover:bg-slate-50 hover:border-red-300 text-left rounded-xl transition-all shadow-sm group flex items-center justify-between relative overflow-hidden">
                                    
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-red-100 group-hover:text-red-500 transition-colors shrink-0">
                                            <i class="fas fa-id-badge text-xs"></i>
                                        </div>
                                        <div class="flex-1 pr-2">
                                            <h3 class="text-[12px] font-bold text-slate-700 group-hover:text-red-600 transition-colors leading-tight cargo-title">
                                                {{ $cargo->de_crgo }}
                                            </h3>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mt-1 flex items-center gap-1 cargo-area">
                                                <span class="truncate max-w-[180px]">{{ $cargo->de_area ?: 'Sin área asignada' }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <i class="fas fa-chevron-right text-[10px] text-slate-300 group-hover:text-red-500 transition-colors shrink-0 ml-1"></i>

                                    <!-- Overlay de Loading -->
                                    <div class="absolute inset-0 bg-white/90 backdrop-blur-[2px] hidden items-center justify-center z-20 btn-loading">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-spinner fa-spin text-red-500 text-sm"></i>
                                            <span class="text-[11px] font-bold text-red-500 uppercase tracking-widest">Ingresando...</span>
                                        </div>
                                    </div>
                                </button>
                            </form>
                        @endforeach</div><div id="empty-state" class="hidden sigtram-role-empty">No hay cargos que coincidan con tu búsqueda.</div>
</section></main>
    <script>
        function filtrarCargos() {
            const input = document.getElementById("buscador").value.toLowerCase().trim();
            const items = document.querySelectorAll(".cargo-item");
            const emptyState = document.getElementById("empty-state");
            let visibles = 0;

            items.forEach(function(item) {
                const titulo = item.querySelector(".cargo-title").textContent.toLowerCase();
                const area = item.querySelector(".cargo-area").textContent.toLowerCase();

                if (titulo.includes(input) || area.includes(input)) {
                    item.style.display = "block";
                    visibles++;
                } else {
                    item.style.display = "none";
                }
            });

            if (visibles === 0) {
                emptyState.classList.remove("hidden");
                emptyState.classList.add("flex");
            } else {
                emptyState.classList.add("hidden");
                emptyState.classList.remove("flex");
            }
        }

        function showLoading(form) {
            const btn = form.querySelector('button[type="submit"]');
            const loading = btn.querySelector('.btn-loading');
            
            loading.classList.remove('hidden');
            loading.classList.add('flex');
            
            // Deshabilitar el input del buscador para que no filtren mientras carga
            document.getElementById("buscador").disabled = true;
            
            document.querySelectorAll('button[type="submit"]').forEach(b => {
                b.style.pointerEvents = 'none';
            });
            
            return true;
        }
    </script>
</body>
</html>