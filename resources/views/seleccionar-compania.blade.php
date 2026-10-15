<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <script>
        // Evitar que la selección de compañía se cargue dentro de un iframe
        if (window.self !== window.top) {
            window.top.location.href = window.self.location.href;
        }
    </script>
    <meta name="theme-color" content="#f1f5f9">
    <title>Seleccionar Compañía — SIGTRAM3</title>
    
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
</head>

<body class="min-h-screen relative flex items-center justify-center p-4 lg:p-8 bg-[#f1f5f9] overflow-x-hidden">
    
    <!-- BACKGROUND SLANTED SPLIT (Igual al Login) -->
    <div class="absolute inset-y-0 left-0 w-[65%] lg:w-[60%] bg-slate-900 pointer-events-none" style="clip-path: polygon(0 0, 100% 0, 75% 100%, 0% 100%);">
        <div class="absolute inset-0 bg-cover bg-center opacity-30 mix-blend-luminosity" style="background-image: url('{{ asset('images/puentepiedra.jpg') }}');"></div>
    </div>

    <!-- MAIN CARD -->
    <div class="relative z-10 w-full max-w-[1000px] bg-white rounded-[2.5rem] shadow-[0_20px_60px_rgba(0,0,0,0.08)] flex flex-col lg:flex-row p-3 min-h-[600px] opacity-0 animate-fade-in">
        
        <!-- LEFT COLUMN: INSET IMAGE -->
        <div class="hidden lg:flex flex-col justify-between w-[48%] bg-slate-800 rounded-[2rem] p-8 relative overflow-hidden shadow-inner">
            <div class="absolute inset-0 bg-cover bg-center transition-transform duration-1000 hover:scale-105" style="background-image: url('{{ asset('images/puentepiedra.jpg') }}');"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-slate-900/60 via-slate-900/20 to-slate-900/90"></div>
            
            <div class="relative z-10 flex justify-between items-center w-full">
                <span class="text-white font-bold text-sm tracking-wide">Plataforma SGT</span>
                <div class="flex gap-4 items-center">
                    <span class="border border-white/30 rounded-full px-4 py-1.5 text-white text-[11px] font-bold backdrop-blur-md">V 3.0</span>
                </div>
            </div>

            <div class="relative z-10 flex justify-between items-end w-full">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-white/10 backdrop-blur-md border border-white/20 p-2 flex items-center justify-center shadow-lg">
                        <img src="{{ asset('images/imagen2.png') }}" class="w-full h-full object-contain" alt="Escudo">
                    </div>
                    <div>
                        <p class="text-white font-black text-sm leading-tight tracking-wide">Gestión Institucional</p>
                        <p class="text-white/70 text-xs font-medium">Puente Piedra</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="w-8 h-8 rounded-full border border-white/20 flex items-center justify-center text-white/70 hover:bg-white/20 hover:text-white backdrop-blur-md transition-all"><i class="fas fa-arrow-left text-[10px]"></i></button>
                    <button class="w-8 h-8 rounded-full border border-white/20 flex items-center justify-center text-white/70 hover:bg-white/20 hover:text-white backdrop-blur-md transition-all"><i class="fas fa-arrow-right text-[10px]"></i></button>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: SELECCIÓN DE COMPAÑÍA -->
        <div class="w-full lg:w-[52%] flex flex-col p-8 sm:p-12 lg:px-16 lg:py-10 relative">
            
            <!-- Header Right: Logo & Botón Cerrar Sesión -->
            <div class="flex justify-between items-center mb-8 lg:mb-10">
                <img src="{{ asset('images/mpp.png') }}" class="h-6 sm:h-7 object-contain" alt="SIGTRAM">
                
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="border border-slate-200 rounded-full px-4 py-2 flex items-center gap-2 text-[10px] font-bold text-slate-500 hover:bg-red-50 hover:text-red-600 hover:border-red-200 cursor-pointer transition-colors shadow-sm">
                        <i class="fas fa-power-off"></i> Cerrar sesión
                    </button>
                </form>
            </div>

            <!-- Form Container -->
            <div class="flex-1 flex flex-col justify-center relative w-full max-w-[360px] mx-auto">
                
                <div class="text-center mb-8">
                    <h2 class="text-[28px] font-black text-slate-900 tracking-tight leading-none mb-2">¡Hola, {{ $usuario }}!</h2>
                    <p class="text-[13px] font-medium text-slate-500">Selecciona la compañía o entidad para continuar</p>
                </div>

                <!-- Buscador Minimalista -->
                <div class="relative w-full mb-6">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-search text-slate-400"></i>
                    </div>
                    <input type="text" id="buscador" onkeyup="filtrarCompanias()" autofocus
                        class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-[12px] font-semibold text-slate-800 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all outline-none placeholder:text-slate-400 shadow-sm" 
                        placeholder="Buscar por compañía o entidad...">
                </div>

                @if ($errors->any())
                    <div class="mb-6 bg-red-50 border border-red-100 p-3 rounded-xl flex items-start gap-3 animate-shake">
                        <i class="fas fa-exclamation-circle text-red-500 mt-0.5 text-sm"></i>
                        <div>
                            <p class="text-xs font-bold text-red-800">Error</p>
                            <p class="text-[11px] font-medium text-red-600 mt-0.5">{{ $errors->first() }}</p>
                        </div>
                    </div>
                @endif

                <!-- Contenedor Fijo para evitar saltos en el layout -->
                <div class="h-[280px] relative w-full">
                    <!-- Lista de Compañías -->
                    <div class="absolute inset-0 flex flex-col gap-3 overflow-y-auto pr-2 pb-2 custom-scrollbar" id="lista-companias">
                        @foreach ($companias as $index => $compania)
                            <form method="POST" action="{{ route('seleccionar.compania.guardar') }}" class="compania-item" onsubmit="showLoading(this)">
                                @csrf
                                <input type="hidden" name="compania_id" value="{{ $compania->cdgo_cmpnia }}">
                                
                                <button type="submit" class="w-full py-3.5 px-4 bg-white border border-slate-200 hover:bg-slate-50 hover:border-red-300 text-left rounded-xl transition-all shadow-sm group flex items-center justify-between relative overflow-hidden">
                                    
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-red-100 group-hover:text-red-500 transition-colors shrink-0">
                                            <i class="fas fa-building text-xs"></i>
                                        </div>
                                        <div class="flex-1 pr-2">
                                            <h3 class="text-[12px] font-bold text-slate-700 group-hover:text-red-600 transition-colors leading-tight compania-title">
                                                {{ $compania->de_cmpnia }}
                                            </h3>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mt-1 flex items-center gap-1">
                                                <span>Código: {{ $compania->cdgo_cmpnia }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <i class="fas fa-chevron-right text-[10px] text-slate-300 group-hover:text-red-500 transition-colors shrink-0 ml-1"></i>

                                    <!-- Overlay de Loading -->
                                    <div class="absolute inset-0 bg-white/90 backdrop-blur-[2px] hidden items-center justify-center z-20 btn-loading">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-spinner fa-spin text-red-500 text-sm"></i>
                                            <span class="text-[11px] font-bold text-red-500 uppercase tracking-widest">Cargando...</span>
                                        </div>
                                    </div>
                                </button>
                            </form>
                        @endforeach
                    </div>

                    <!-- Empty State -->
                    <div id="empty-state" class="absolute inset-0 hidden flex-col items-center justify-center text-center bg-white z-10">
                        <div class="w-12 h-12 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-300 mb-3">
                            <i class="fas fa-search-minus text-xl"></i>
                        </div>
                        <p class="text-[13px] font-black text-slate-700">Sin resultados</p>
                        <p class="text-[11px] font-medium text-slate-400 mt-1">Verifica tu búsqueda</p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function filtrarCompanias() {
            const input = document.getElementById("buscador").value.toLowerCase().trim();
            const items = document.querySelectorAll(".compania-item");
            const emptyState = document.getElementById("empty-state");
            let visibles = 0;

            items.forEach(function(item) {
                const titulo = item.querySelector(".compania-title").textContent.toLowerCase();

                if (titulo.includes(input)) {
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
            
            document.getElementById("buscador").disabled = true;
            
            document.querySelectorAll('button[type="submit"]').forEach(b => {
                b.style.pointerEvents = 'none';
            });
            
            return true;
        }
    </script>
</body>
</html>
