<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bandeja de Consulta GDU — SIGTRAM</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
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

        .bandeja-frame {
            min-height: calc(100vh - 140px);
        }

        @media (max-width: 768px) {
            .bandeja-frame {
                min-height: 520px;
            }
        }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gdu.band_consulta_gdu'])
</head>
<body data-sigtram-screen="mod_gdu.band_consulta_gdu" class="sigtram-module bg-slate-50 text-slate-800 antialiased font-sans min-h-screen">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gdu.band_consulta_gdu'])

    <main
        class="p-4 sm:p-6 w-full max-w-[1600px] mx-auto min-h-screen flex flex-col"
        x-data="{
            activeUrl: @js($iframe_src),
            bandejas: @js($bandejas),
            setBandeja(url) {
                this.activeUrl = url;
            },
            isActive(url) {
                return this.activeUrl === url;
            }
        }"
    >
        <nav class="mb-5 flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-[10px] font-black uppercase tracking-widest text-slate-400">
                <li><a href="/inicio" class="hover:text-blue-600 transition-colors">SIGTRAM</a></li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li>GDU</li>
                <li><i class="fas fa-chevron-right text-[7px] opacity-30"></i></li>
                <li class="text-blue-600">Bandeja de Consulta</li>
            </ol>
        </nav>

        <header class="mb-5 flex items-center gap-4">
            <div class="w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center text-white shadow-lg shrink-0">
                <i class="fas fa-inbox text-xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Bandeja de Consulta GDU</h1>
                <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-widest font-black">Gestión de Desarrollo Urbano — Consulta de bandejas</p>
            </div>
        </header>

        <div class="flex flex-col lg:flex-row gap-4 flex-1 min-h-0">
            <aside class="w-full lg:w-56 shrink-0">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-800 text-white px-4 py-3 text-xs font-bold uppercase tracking-wider">
                        Bandejas
                    </div>
                    <nav class="p-2 space-y-1">
                        <template x-for="bandeja in bandejas" :key="bandeja.url">
                            <button
                                type="button"
                                @click="setBandeja(bandeja.url)"
                                class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-semibold transition text-left"
                                :class="isActive(bandeja.url)
                                    ? 'bg-slate-800 text-white shadow-sm'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                            >
                                <i class="fas text-sm" :class="bandeja.icon"></i>
                                <span x-text="bandeja.label"></span>
                            </button>
                        </template>
                    </nav>
                </div>
            </aside>

            <section class="flex-1 min-w-0 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <iframe
                    :src="activeUrl"
                    name="frame"
                    title="Contenido de la bandeja GDU"
                    class="bandeja-frame w-full flex-1 border-0"
                    scrolling="auto"
                ></iframe>
            </section>
        </div>
    </main>

</body>
</html>
