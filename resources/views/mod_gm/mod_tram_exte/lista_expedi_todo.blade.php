<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Lista de Expedientes' }} — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: "Figtree", "Segoe UI", Tahoma, sans-serif; }
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        :focus-visible { outline: 2px solid #334155; outline-offset: 2px; }
        .modal-overlay {
            position: fixed; inset: 0; z-index: 150;
            display: flex; align-items: center; justify-content: center;
            padding: 1rem; background: rgb(15 23 42 / 0.7); backdrop-filter: blur(8px);
        }
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; inset: 0; background: #fff; padding: 12px; }
        }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.lista_expedi_todo'])
</head>
<body data-sigtram-screen="mod_gm.mod_tram_exte.lista_expedi_todo" class="sigtram-module bg-[#f8fafc] text-gray-800 min-h-screen" x-data="listaExpediTodo()" x-init="init()">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.lista_expedi_todo'])

    <div x-show="toast.show" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]">
        <div class="backdrop-blur-md text-white px-6 py-2.5 rounded-full shadow-2xl flex items-center gap-3"
             :class="toast.type === 'error' ? 'bg-red-700/90' : 'bg-gray-900/90'">
            <i class="text-xs" :class="toast.type === 'error' ? 'fas fa-exclamation-circle text-red-200' : 'fas fa-check-circle text-emerald-400'"></i>
            <p class="text-[10px] font-black uppercase tracking-widest" x-text="toast.message"></p>
        </div>
    </div>

    <div class="p-3 sm:p-5 w-full max-w-[1600px] mx-auto">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col min-h-[85vh]">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Trámite Externo</p>
                    <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Lista de Expedientes' }}</h1>
                </div>
                <span class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full"><span x-text="total"></span> expedientes</span>
            </div>

            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/70 space-y-3">
                <div class="inline-flex bg-gray-200/60 p-1 rounded-xl gap-1">
                    <button type="button" @click="filtros.modo = 'generico'"
                            class="py-1.5 px-4 text-[10px] font-black uppercase tracking-wider rounded-lg"
                            :class="filtros.modo === 'generico' ? 'bg-slate-800 text-white' : 'text-gray-500'">
                        Buscar genéricos
                    </button>
                    <button type="button" @click="filtros.modo = 'fechas'"
                            class="py-1.5 px-4 text-[10px] font-black uppercase tracking-wider rounded-lg"
                            :class="filtros.modo === 'fechas' ? 'bg-slate-800 text-white' : 'text-gray-500'">
                        Buscar por fechas
                    </button>
                </div>

                <div x-show="filtros.modo === 'generico'" class="flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Palabra</label>
                        <input type="text" x-model="filtros.palabra" maxlength="80" @keydown.enter.prevent="pagina=1; fetchLista()"
                               class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <div class="w-full sm:w-64">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Buscar por</label>
                        <select x-model="filtros.campo" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                            <option value="p_dto_trmtes.nu_expe_todo">Nº de Expediente</option>
                            <option value="p_usrios.nu_tele">Nº Telefonico</option>
                            <option value="p_usrios.nu_docu">Nº de Identificación</option>
                            <option value="p_usrios.no_crto">Nombre o Razon Social</option>
                            <option value="a_areas.de_area">Gerencia</option>
                            <option value="a_tpo_trmtes.de_tpo_trmte">Tipo de documento</option>
                            <option value="a_estdos_trmtes.dc_estdo_trmte">Estado</option>
                            <option value="p_dto_trmtes.de_obse_todo">Observaciones</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Fecha</label>
                        <input type="date" x-model="filtros.fecha" class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <button type="button" @click="pagina=1; fetchLista()" class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                </div>

                <div x-show="filtros.modo === 'fechas'" x-cloak class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Desde</label>
                        <input type="date" x-model="filtros.desde" class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Hasta</label>
                        <input type="date" x-model="filtros.hasta" class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <button type="button" @click="pagina=1; fetchLista()" class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-auto custom-scrollbar relative min-h-[320px]">
                <div x-show="loading" x-cloak class="absolute inset-0 bg-white/70 z-10 flex items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-slate-600"></i>
                </div>
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead>
                        <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-200 bg-slate-50 sticky top-0">
                            <th class="px-3 py-2">Nº Expe</th>
                            <th class="px-2 py-2 text-center">Sil</th>
                            <th class="px-2 py-2 text-center">T.A</th>
                            <th class="px-3 py-2">Oficina</th>
                            <th class="px-2 py-2 text-center">Fo</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-2 py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in items" :key="item.id">
                            <tr class="border-b border-gray-100 hover:bg-slate-50 cursor-pointer text-[11px]"
                                @click="abrirHojaRuta(item.id)">
                                <td class="px-3 py-2 font-bold text-slate-800 whitespace-nowrap" x-text="item.expediente"></td>
                                <td class="px-2 py-2 text-center" x-text="item.silencio"></td>
                                <td class="px-2 py-2 text-center font-semibold" x-text="item.ta"></td>
                                <td class="px-3 py-2" x-text="item.oficina"></td>
                                <td class="px-2 py-2 text-center" x-text="item.folios"></td>
                                <td class="px-3 py-2" x-text="item.estado"></td>
                                <td class="px-2 py-2 text-center" @click.stop>
                                    <button type="button" @click="abrirOjo(item)" class="text-slate-600 hover:text-slate-900" title="Ver expediente">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && items.length === 0">
                            <td colspan="7" class="px-3 py-10 text-center text-[11px] font-bold text-gray-400">No se encontraron resultados</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-4 py-2 flex flex-wrap items-center justify-between gap-2 text-[10px] font-bold text-gray-500">
                <div class="flex items-center gap-2">
                    <button type="button" @click="pagina--; fetchLista()" :disabled="pagina <= 1" class="disabled:opacity-30">Anterior</button>
                    <template x-for="n in paginasVisibles()" :key="n">
                        <button type="button" @click="pagina = n; fetchLista()"
                                :class="n === pagina ? 'text-slate-900 font-black' : 'text-gray-400 hover:text-slate-700'"
                                x-text="n"></button>
                    </template>
                    <button type="button" @click="pagina++; fetchLista()" :disabled="pagina >= paginas" class="disabled:opacity-30">Siguiente</button>
                </div>
                <div class="flex items-center gap-4">
                    <span x-text="total + ' Expedientes'"></span>
                    <button type="button" @click="abrirImprimir()" class="uppercase tracking-widest hover:text-slate-800">Imprimir</button>
                    <button type="button" @click="verTodo()" class="uppercase tracking-widest hover:text-slate-800">Ver todo</button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="modalOjo.show" x-cloak class="modal-overlay" @keydown.escape.window="modalOjo.show = false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-[520px] h-[80vh] overflow-hidden flex flex-col" @click.outside="modalOjo.show = false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Expediente</h3>
                <button type="button" @click="modalOjo.show = false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <iframe :src="modalOjo.url" class="flex-1 w-full border-0 bg-white"></iframe>
        </div>
    </div>

    <div x-show="modalRuta.show" x-cloak class="modal-overlay" @keydown.escape.window="modalRuta.show = false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-[820px] h-[80vh] overflow-hidden flex flex-col" @click.outside="modalRuta.show = false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Hoja de ruta</h3>
                <button type="button" @click="modalRuta.show = false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <iframe :src="modalRuta.url" class="flex-1 w-full border-0 bg-white"></iframe>
        </div>
    </div>

    <div x-show="modalPrint.show" x-cloak class="modal-overlay" @keydown.escape.window="modalPrint.show = false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col" @click.outside="modalPrint.show = false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0 print:hidden">
                <h3 class="text-sm font-bold uppercase tracking-wide">Imprimir</h3>
                <div class="flex items-center gap-2">
                    <button type="button" @click="window.print()" class="px-3 py-1 rounded-lg bg-white/15 text-[10px] font-black uppercase">Imprimir</button>
                    <button type="button" @click="modalPrint.show = false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
                </div>
            </div>
            <div id="printArea" class="p-5 overflow-auto custom-scrollbar text-xs">
                <p class="text-center font-black uppercase text-sm mb-3">{{ $tituloOpcion ?? 'Lista de Expedientes' }}</p>
                <table class="w-full border border-slate-300 border-collapse">
                    <thead>
                        <tr class="bg-slate-100">
                            <th class="border border-slate-300 px-2 py-1">#</th>
                            <th class="border border-slate-300 px-2 py-1">Fecha</th>
                            <th class="border border-slate-300 px-2 py-1">Nº Expe</th>
                            <th class="border border-slate-300 px-2 py-1">Tipo</th>
                            <th class="border border-slate-300 px-2 py-1 text-left">Oficina Destino</th>
                            <th class="border border-slate-300 px-2 py-1">Folios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, i) in modalPrint.rows" :key="'p'+i">
                            <tr>
                                <td class="border border-slate-300 px-2 py-1 text-center" x-text="i + 1"></td>
                                <td class="border border-slate-300 px-2 py-1 text-center" x-text="item.fecha"></td>
                                <td class="border border-slate-300 px-2 py-1 text-center" x-text="item.expediente_fmt"></td>
                                <td class="border border-slate-300 px-2 py-1 text-center" x-text="item.tipo_corto"></td>
                                <td class="border border-slate-300 px-2 py-1" x-text="item.oficina"></td>
                                <td class="border border-slate-300 px-2 py-1 text-center" x-text="item.folios"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function listaExpediTodo() {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const routes = {
                buscar: @json(route('mod_gm.lista_expedi_todo.buscar', [], false)),
                imprimir: @json(route('mod_gm.lista_expedi_todo.imprimir', [], false)),
                buscaExpediDeta: @json(route('tramites.busca_expedi_deta', [], false)),
                hojaRuta: @json(route('mod_gm.segi_expedi.hoja_ruta', ['id' => '__HID__'], false)),
            };

            return {
                loading: false,
                items: [],
                total: 0,
                pagina: 1,
                paginas: 1,
                toast: { show: false, message: '', type: 'success' },
                filtros: {
                    modo: 'generico',
                    palabra: '',
                    campo: 'p_dto_trmtes.nu_expe_todo',
                    fecha: '',
                    desde: '',
                    hasta: '',
                },
                modalOjo: { show: false, url: '' },
                modalRuta: { show: false, url: '' },
                modalPrint: { show: false, rows: [] },

                init() { this.fetchLista(); },

                showToast(message, type = 'success') {
                    this.toast = { show: true, message, type };
                    setTimeout(() => { this.toast.show = false; }, 3500);
                },
                payload() {
                    return { ...this.filtros, page: this.pagina };
                },
                verTodo() {
                    this.filtros.palabra = '';
                    this.filtros.fecha = '';
                    this.filtros.desde = '';
                    this.filtros.hasta = '';
                    this.filtros.modo = 'generico';
                    this.pagina = 1;
                    this.fetchLista();
                },
                paginasVisibles() {
                    const tam = 12;
                    const seccion = Math.floor((this.pagina - 1) / tam);
                    const inicio = seccion * tam + 1;
                    const final = Math.min(inicio + tam - 1, this.paginas);
                    const arr = [];
                    for (let i = inicio; i <= final; i++) arr.push(i);
                    return arr;
                },
                abrirOjo(item) {
                    this.modalOjo.url = routes.buscaExpediDeta + '?cdgo_tram=' + item.id;
                    this.modalOjo.show = true;
                },
                abrirHojaRuta(id) {
                    this.modalRuta.url = routes.hojaRuta.replace('__HID__', id);
                    this.modalRuta.show = true;
                },
                async fetchLista() {
                    this.loading = true;
                    try {
                        const res = await fetch(routes.buscar, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(this.payload()),
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            this.items = data.data || [];
                            this.total = data.total || 0;
                            this.pagina = data.current_page || 1;
                            this.paginas = data.last_page || 1;
                        } else {
                            this.showToast(data.message || 'No se pudo cargar el listado.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al cargar expedientes.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                async abrirImprimir() {
                    try {
                        const res = await fetch(routes.imprimir, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(this.payload()),
                        });
                        const data = await res.json();
                        this.modalPrint = { show: true, rows: data.data || [] };
                    } catch (e) {
                        this.showToast('No se pudo generar la impresión.', 'error');
                    }
                },
            };
        }
    </script>
</body>
</html>
