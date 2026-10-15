<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Redirigir' }} — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
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
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.redirigir'])
</head>
<body data-sigtram-screen="mod_gm.mod_tram_exte.redirigir" class="sigtram-module bg-[#f8fafc] text-gray-800 min-h-screen" x-data="redirigirExpedientes()" x-init="init()">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.redirigir'])

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
                    <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Redirigir' }}</h1>
                </div>
                <span class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full"><span x-text="total"></span> registros</span>
            </div>

            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/70">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Palabra</label>
                        <input type="text" x-model="filtros.palabra" maxlength="80" @keydown.enter.prevent="buscarConValidacion()"
                               class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <div class="w-full sm:w-64">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Buscar por</label>
                        <select x-model="filtros.buscar_por" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                            <option value="p_dto_trmtes.nu_expe_todo">Nº de Expediente</option>
                            <option value="a_areas.de_area">Area</option>
                            <option value="p_usrios.no_crto">Nombre o Razon Social</option>
                            <option value="a_tpo_trmtes.dc_tpo_trmte">Tipo de documento</option>
                            <option value="p_dto_trmtes.de_obse_todo">Observaciones</option>
                            <option value="p_usrios_1.no_crto">Usuario de Plataforma</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Fecha</label>
                        <input type="date" x-model="filtros.fecha"
                               class="bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <button type="button" @click="buscarConValidacion()"
                            class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                    <button type="button" @click="verTodo()"
                            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-lg">
                        Ver todo
                    </button>
                </div>
            </div>

            <div class="px-4 py-2 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <button type="button" @click="abrirRedirigir()"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-slate-700 hover:text-slate-900">
                    <i class="fas fa-share-from-square"></i> Redirigir
                </button>
                <div class="flex items-center gap-2 text-[10px] font-bold text-gray-500">
                    <button type="button" @click="pagina--; fetchLista()" :disabled="pagina <= 1" class="disabled:opacity-30">Anterior</button>
                    <template x-for="n in paginasVisibles()" :key="n">
                        <button type="button" @click="pagina = n; fetchLista()"
                                class="px-1.5"
                                :class="n === pagina ? 'text-slate-900 font-black' : 'text-gray-400 hover:text-slate-700'"
                                x-text="n"></button>
                    </template>
                    <button type="button" @click="pagina++; fetchLista()" :disabled="pagina >= paginas" class="disabled:opacity-30">Siguiente</button>
                </div>
            </div>

            <div class="flex-1 overflow-auto custom-scrollbar relative min-h-[320px]">
                <div x-show="loading" x-cloak class="absolute inset-0 bg-white/70 z-10 flex items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-slate-600"></i>
                </div>
                <table class="w-full text-left border-collapse min-w-[1100px]">
                    <thead>
                        <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-200 bg-slate-50 sticky top-0">
                            <th class="px-2 py-2 w-8"></th>
                            <th class="px-3 py-2">Nº Exp.</th>
                            <th class="px-3 py-2">Nombre o Razón Social</th>
                            <th class="px-2 py-2 text-center">Ubi.</th>
                            <th class="px-3 py-2">F. Entrada</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-2 py-2 text-center">Area</th>
                            <th class="px-3 py-2">Observaciones</th>
                            <th class="px-2 py-2 w-8"></th>
                            <th class="px-2 py-2">Usuario</th>
                            <th class="px-2 py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in items" :key="item.cdgo_trmte_dcmnto">
                            <tr class="border-b border-gray-100 hover:bg-slate-50 text-[11px]"
                                :class="selectedIds.includes(Number(item.cdgo_dto_trmte)) ? 'bg-slate-50' : ''">
                                <td class="px-2 py-2 text-center">
                                    <input type="checkbox" class="rounded text-slate-700"
                                           :value="item.cdgo_dto_trmte"
                                           :checked="selectedIds.includes(Number(item.cdgo_dto_trmte))"
                                           @change="toggleId(item.cdgo_dto_trmte)">
                                </td>
                                <td class="px-3 py-2 font-bold text-slate-800 whitespace-nowrap" x-text="item.nu_expe_todo"></td>
                                <td class="px-3 py-2 text-gray-700" :title="item.solicitante" x-text="item.solicitante_corto"></td>
                                <td class="px-2 py-2 text-center font-bold" x-text="item.ubicacion"></td>
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap" x-text="item.fecha"></td>
                                <td class="px-3 py-2 text-gray-600" x-text="item.estado"></td>
                                <td class="px-2 py-2 text-center text-gray-600" :title="item.area" x-text="item.area_corta"></td>
                                <td class="px-3 py-2 text-gray-600" :title="item.observaciones" x-text="item.observaciones_corta"></td>
                                <td class="px-2 py-2 text-center">
                                    <button type="button" @click="abrirOjo(item)"
                                            :class="item.alerta ? 'text-amber-600' : 'text-slate-600'"
                                            :title="item.alerta ? 'Plazo próximo a vencer' : 'Ver expediente'">
                                        <i class="fas" :class="item.alerta ? 'fa-triangle-exclamation' : 'fa-eye'"></i>
                                    </button>
                                </td>
                                <td class="px-2 py-2 text-gray-500" x-text="item.usuario"></td>
                                <td class="px-2 py-2 text-center">
                                    <button type="button" @click="abrirAnular(item)" class="text-rose-600 hover:text-rose-800" title="Anular">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && items.length === 0">
                            <td colspan="11" class="px-3 py-10 text-center text-[11px] font-bold text-gray-400">No se encontraron resultados</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-4 py-2 flex items-center justify-between text-[10px] font-bold text-gray-500">
                <div class="flex items-center gap-3">
                    <button type="button" @click="marcarTodos()" class="uppercase tracking-widest hover:text-slate-800">Todos</button>
                    <button type="button" @click="selectedIds = []" class="uppercase tracking-widest hover:text-slate-800">Ninguno</button>
                </div>
                <div class="flex items-center gap-4">
                    <span>Total: <span x-text="total"></span></span>
                    <button type="button" @click="abrirImprimir()" class="uppercase tracking-widest hover:text-slate-800">Imprimir</button>
                    <button type="button" @click="verTodo()" class="uppercase tracking-widest hover:text-slate-800">Ver todo</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Overlay redirigir (segi_otra.php) --}}
    <div x-show="modalRedirigir.show" x-cloak class="modal-overlay" @keydown.escape.window="cerrarRedirigir()">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden" @click.outside="cerrarRedirigir()">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wide">Nuevo Destino del Expediente</h3>
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Redirigir</p>
                </div>
                <button type="button" @click="cerrarRedirigir()" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <form @submit.prevent="enviarRedirigir()" class="p-5 space-y-4">
                <div x-show="modalRedirigir.errors.length" class="bg-red-50 border border-red-200 rounded-xl p-3 text-xs font-semibold text-red-700">
                    <template x-for="err in modalRedirigir.errors" :key="err"><p x-text="err"></p></template>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Gerencia</label>
                    <select x-model="formRed.gerencia" @change="onGerenciaChange()"
                            class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                        <option value="0">-- Seleccione --</option>
                        @foreach ($gerencias as $ger)
                            <option value="{{ $ger->cdgo_area }}" data-in-part="{{ $ger->in_part }}">{{ $ger->de_area }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Sub Gerencia</label>
                    <div class="flex items-center gap-2">
                        <select x-model="formRed.subgerencia" :disabled="subgerencias.length === 0"
                                class="flex-1 bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none disabled:bg-gray-100">
                            <option value="0">-- Seleccione la subgerencia --</option>
                            <template x-for="sg in subgerencias" :key="sg.id">
                                <option :value="sg.id" x-text="sg.nombre"></option>
                            </template>
                        </select>
                        <label class="inline-flex items-center gap-1 text-[10px] font-bold text-gray-500 whitespace-nowrap">
                            <input type="checkbox" x-model="formRed.usar_sub" :disabled="subgerencias.length === 0" class="rounded text-slate-700">
                            Usar sub
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Fecha</label>
                    <p class="text-sm font-semibold text-slate-700" x-text="formRed.fecha"></p>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Observaciones</label>
                    <textarea x-model="formRed.observaciones" rows="5"
                              class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" @click="cerrarRedirigir()" :disabled="guardando"
                            class="px-4 py-2 border border-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-600">Cancelar</button>
                    <button type="submit" :disabled="guardando"
                            class="px-5 py-2 bg-slate-700 hover:bg-slate-800 disabled:opacity-50 text-white rounded-xl text-[10px] font-black uppercase tracking-widest">
                        <span x-show="!guardando">Enviar</span>
                        <span x-show="guardando" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Procesando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Overlay anular (segi_detalle_anular.php) --}}
    <div x-show="modalAnular.show" x-cloak class="modal-overlay" @keydown.escape.window="cerrarAnular()">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-md overflow-hidden" @click.outside="cerrarAnular()">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wide">Anular trámite N° <span x-text="modalAnular.expediente"></span></h3>
                <button type="button" @click="cerrarAnular()" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <form @submit.prevent="enviarAnular()" class="p-5 space-y-4">
                <div x-show="modalAnular.errors.length" class="bg-red-50 border border-red-200 rounded-xl p-3 text-xs font-semibold text-red-700">
                    <template x-for="err in modalAnular.errors" :key="err"><p x-text="err"></p></template>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Motivo</label>
                    <textarea x-model="modalAnular.motivo" rows="5"
                              class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" @click="cerrarAnular()" :disabled="guardandoAnular"
                            class="px-4 py-2 border border-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-600">Cancelar</button>
                    <button type="submit" :disabled="guardandoAnular"
                            class="px-5 py-2 bg-rose-700 hover:bg-rose-800 disabled:opacity-50 text-white rounded-xl text-[10px] font-black uppercase tracking-widest">
                        <span x-show="!guardandoAnular">Anular Trámite</span>
                        <span x-show="guardandoAnular" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Procesando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Overlay ojo (busca_expedi.php?anex=1) --}}
    <div x-show="modalOjo.show" x-cloak class="modal-overlay" @keydown.escape.window="modalOjo.show = false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-[520px] h-[80vh] overflow-hidden flex flex-col" @click.outside="modalOjo.show = false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Expediente</h3>
                <button type="button" @click="modalOjo.show = false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <iframe :src="modalOjo.url" class="flex-1 w-full border-0 bg-white"></iframe>
        </div>
    </div>

    {{-- Overlay imprimir --}}
    <div x-show="modalPrint.show" x-cloak class="modal-overlay" @keydown.escape.window="modalPrint.show = false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col" @click.outside="modalPrint.show = false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0 print:hidden">
                <h3 class="text-sm font-bold uppercase tracking-wide">Imprimir</h3>
                <div class="flex items-center gap-2">
                    <button type="button" @click="window.print()" class="px-3 py-1 rounded-lg bg-white/15 text-[10px] font-black uppercase">Imprimir</button>
                    <button type="button" @click="modalPrint.show = false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
                </div>
            </div>
            <div id="printArea" class="p-5 overflow-auto custom-scrollbar text-xs">
                <p class="font-black uppercase mb-1">Usuario: <span class="font-semibold normal-case" x-text="modalPrint.criterio || '—'"></span></p>
                <p class="font-black uppercase mb-4">Fecha: <span class="font-semibold normal-case" x-text="modalPrint.fecha || '—'"></span></p>
                <template x-for="(area, idx) in modalPrint.areas" :key="idx">
                    <div class="mb-6">
                        <p class="text-center font-black uppercase text-sm mb-2" x-text="area.de_area"></p>
                        <table class="w-full border border-slate-300 border-collapse mb-2">
                            <thead>
                                <tr class="bg-amber-200">
                                    <th class="border border-slate-300 px-2 py-1">Expediente</th>
                                    <th class="border border-slate-300 px-2 py-1">Folios</th>
                                    <th class="border border-slate-300 px-2 py-1">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr x-show="area.expedientes.length === 0">
                                    <td colspan="3" class="border border-slate-300 px-2 py-1 text-center">--No se encontron Expedientes--</td>
                                </tr>
                                <template x-for="(exp, i) in area.expedientes" :key="'e'+i">
                                    <tr>
                                        <td class="border border-slate-300 px-2 py-1 text-center" x-text="exp.expediente"></td>
                                        <td class="border border-slate-300 px-2 py-1 text-center" x-text="exp.folios"></td>
                                        <td class="border border-slate-300 px-2 py-1 text-center" x-text="exp.usuario"></td>
                                    </tr>
                                </template>
                                <tr class="bg-amber-200">
                                    <th class="border border-slate-300 px-2 py-1">Simple</th>
                                    <th class="border border-slate-300 px-2 py-1">Folios</th>
                                    <th class="border border-slate-300 px-2 py-1">Usuario</th>
                                </tr>
                                <tr x-show="area.simples.length === 0">
                                    <td colspan="3" class="border border-slate-300 px-2 py-1 text-center">--No se encontron simples--</td>
                                </tr>
                                <template x-for="(exp, i) in area.simples" :key="'s'+i">
                                    <tr>
                                        <td class="border border-slate-300 px-2 py-1 text-center" x-text="exp.expediente"></td>
                                        <td class="border border-slate-300 px-2 py-1 text-center" x-text="exp.folios"></td>
                                        <td class="border border-slate-300 px-2 py-1 text-center" x-text="exp.usuario"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
                <p x-show="modalPrint.areas.length === 0" class="text-center text-gray-400 py-8">No se encontraron resultados</p>
            </div>
        </div>
    </div>

    <script>
        function redirigirExpedientes() {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const gerenciasMeta = @json($gerencias);
            const routes = {
                buscar: @json(route('mod_gm.redirigir.buscar', [], false)),
                derivar: @json(route('mod_gm.redirigir.derivar', [], false)),
                anular: @json(route('mod_gm.redirigir.anular', [], false)),
                imprimir: @json(route('mod_gm.redirigir.imprimir', [], false)),
                subgerencias: @json(route('tramites.ingresar.subgerencias', ['gerencia' => '__GID__'], false)),
                buscaExpedi: @json(route('tramites.busca_expedi', [], false)),
            };

            return {
                loading: false,
                guardando: false,
                guardandoAnular: false,
                items: [],
                selectedIds: [],
                total: 0,
                pagina: 1,
                paginas: 1,
                toast: { show: false, message: '', type: 'success' },
                filtros: { palabra: '', buscar_por: 'p_dto_trmtes.nu_expe_todo', fecha: '' },
                subgerencias: [],
                modalRedirigir: { show: false, errors: [] },
                formRed: { gerencia: '0', subgerencia: '0', usar_sub: false, observaciones: 'Sin novedades para reenviar', fecha: '' },
                modalAnular: { show: false, errors: [], id: null, expediente: '', motivo: '' },
                modalOjo: { show: false, url: '' },
                modalPrint: { show: false, areas: [], criterio: '', fecha: '' },

                init() { this.fetchLista(); },

                showToast(message, type = 'success') {
                    this.toast = { show: true, message, type };
                    setTimeout(() => { this.toast.show = false; }, 4000);
                },
                payloadFiltros() {
                    return {
                        palabra: this.filtros.palabra,
                        buscar_por: this.filtros.buscar_por,
                        fecha: this.filtros.fecha,
                    };
                },
                buscarConValidacion() {
                    if (!this.filtros.palabra && !this.filtros.fecha) {
                        this.showToast('Debe especificar un criterio de busqueda', 'error');
                        return;
                    }
                    this.pagina = 1;
                    this.fetchLista();
                },
                verTodo() {
                    this.filtros.palabra = '';
                    this.filtros.fecha = '';
                    this.pagina = 1;
                    this.fetchLista();
                },
                paginasVisibles() {
                    const tam = 15;
                    const seccion = Math.floor((this.pagina - 1) / tam);
                    const inicio = seccion * tam + 1;
                    const final = Math.min(inicio + tam - 1, this.paginas);
                    const arr = [];
                    for (let i = inicio; i <= final; i++) arr.push(i);
                    return arr;
                },
                toggleId(id) {
                    const n = Number(id);
                    if (this.selectedIds.includes(n)) {
                        this.selectedIds = this.selectedIds.filter(x => x !== n);
                    } else {
                        this.selectedIds.push(n);
                    }
                },
                marcarTodos() {
                    this.selectedIds = this.items.map(i => Number(i.cdgo_dto_trmte));
                },
                async fetchLista() {
                    this.loading = true;
                    this.selectedIds = [];
                    try {
                        const res = await fetch(routes.buscar, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                ...this.payloadFiltros(),
                                page: this.pagina,
                                per_page: 15,
                            }),
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            this.items = data.data || [];
                            this.total = data.total || 0;
                            this.pagina = data.current_page || 1;
                            this.paginas = data.last_page || 1;
                        } else {
                            this.showToast(data.message || 'No se pudo cargar la bandeja.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al cargar expedientes.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                abrirOjo(item) {
                    this.modalOjo.url = routes.buscaExpedi + '?cdgo_tram=' + item.cdgo_dto_trmte + '&anex=1';
                    this.modalOjo.show = true;
                },
                abrirAnular(item) {
                    this.modalAnular = {
                        show: true,
                        errors: [],
                        id: item.cdgo_trmte_dcmnto,
                        expediente: item.nu_expe_todo,
                        motivo: '',
                    };
                },
                cerrarAnular() {
                    if (this.guardandoAnular) return;
                    this.modalAnular.show = false;
                },
                abrirRedirigir() {
                    if (this.selectedIds.length === 0) {
                        this.showToast('Debe seleccionar al menos un Documento', 'error');
                        return;
                    }
                    const now = new Date();
                    const pad = n => String(n).padStart(2, '0');
                    this.formRed = {
                        gerencia: '0',
                        subgerencia: '0',
                        usar_sub: false,
                        observaciones: 'Sin novedades para reenviar',
                        fecha: pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear() + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()),
                    };
                    this.subgerencias = [];
                    this.modalRedirigir = { show: true, errors: [] };
                },
                cerrarRedirigir() {
                    if (this.guardando) return;
                    this.modalRedirigir.show = false;
                },
                gerenciaInPart() {
                    const g = gerenciasMeta.find(x => String(x.cdgo_area) === String(this.formRed.gerencia));
                    return g ? String(g.in_part) : '0';
                },
                async onGerenciaChange() {
                    this.formRed.subgerencia = '0';
                    this.subgerencias = [];
                    this.formRed.usar_sub = false;
                    const gid = this.formRed.gerencia;
                    if (!gid || gid === '0') return;
                    try {
                        const res = await fetch(routes.subgerencias.replace('__GID__', gid), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const data = await res.json();
                        this.subgerencias = Array.isArray(data) ? data : [];
                        if (this.subgerencias.length > 0 && this.gerenciaInPart() === '1') {
                            this.formRed.usar_sub = true;
                        }
                    } catch (e) {
                        this.subgerencias = [];
                    }
                },
                async enviarRedirigir() {
                    if (this.formRed.gerencia === '0' || !this.formRed.gerencia) {
                        this.modalRedirigir.errors = ['Debe especificar la Gerencia'];
                        return;
                    }
                    if (this.guardando) return;
                    this.guardando = true;
                    this.modalRedirigir.errors = [];
                    try {
                        const res = await fetch(routes.derivar, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                ids: this.selectedIds,
                                cdgo_gerencia: this.formRed.gerencia,
                                cdgo_subgerencia: this.formRed.subgerencia,
                                usar_subgerencia: this.formRed.usar_sub ? 1 : 0,
                                observacion: this.formRed.observaciones,
                            }),
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.showToast(data.message || 'Los expedientes fueron reedirigidos');
                            this.modalRedirigir.show = false;
                            await this.fetchLista();
                        } else if (data.errors) {
                            this.modalRedirigir.errors = Object.values(data.errors).flat();
                        } else {
                            this.modalRedirigir.errors = [data.message || 'No se pudo redirigir.'];
                        }
                    } catch (e) {
                        this.modalRedirigir.errors = ['Error de red al redirigir.'];
                    } finally {
                        this.guardando = false;
                    }
                },
                async enviarAnular() {
                    const motivo = (this.modalAnular.motivo || '').trim();
                    if (motivo === '') {
                        this.modalAnular.errors = ['Debe especificar el motivo'];
                        return;
                    }
                    if (!confirm('Esta seguro de anular el expediente ' + this.modalAnular.expediente + '?')) {
                        return;
                    }
                    if (this.guardandoAnular) return;
                    this.guardandoAnular = true;
                    this.modalAnular.errors = [];
                    try {
                        const res = await fetch(routes.anular, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                id: this.modalAnular.id,
                                motivo: this.modalAnular.motivo,
                                expediente: this.modalAnular.expediente,
                            }),
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.showToast(data.message || 'El trámite fue anulado');
                            this.modalAnular.show = false;
                            await this.fetchLista();
                        } else if (data.errors) {
                            this.modalAnular.errors = Object.values(data.errors).flat();
                        } else {
                            this.modalAnular.errors = [data.message || 'No se pudo anular.'];
                        }
                    } catch (e) {
                        this.modalAnular.errors = ['Error de red al anular.'];
                    } finally {
                        this.guardandoAnular = false;
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
                            body: JSON.stringify(this.payloadFiltros()),
                        });
                        const data = await res.json();
                        this.modalPrint = {
                            show: true,
                            areas: data.areas || [],
                            criterio: data.criterio || this.filtros.palabra,
                            fecha: data.fecha || this.filtros.fecha,
                        };
                    } catch (e) {
                        this.showToast('No se pudo generar la impresión.', 'error');
                    }
                },
            };
        }
    </script>
</body>
</html>
