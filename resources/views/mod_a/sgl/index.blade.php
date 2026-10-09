<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Fichas de selección SE@CE' }} — SIGTRAM</title>
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
        .inp {
            width: 100%; background: #fff; border: 1px solid #d1d5db; border-radius: 0.5rem;
            padding: 0.5rem 0.75rem; font-size: 0.75rem; font-weight: 600; outline: none;
        }
        .inp:focus { box-shadow: 0 0 0 2px #64748b; }
        .lbl { display: block; font-size: 9px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: #9ca3af; margin-bottom: 0.25rem; }
    </style>
</head>
<body class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="fichaSeleccion()" x-init="init()">

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
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Logística · SGL</p>
                    <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Fichas de selección SE@CE' }}</h1>
                </div>
                <span class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full"><span x-text="total"></span> fichas</span>
            </div>

            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/70">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Consultas</p>
                <div class="flex flex-wrap items-end gap-2">
                    <div class="w-full sm:w-52">
                        <label class="lbl">Opción</label>
                        <select x-model="filtros.campo" class="inp">
                            <option value="">--Seleccione--</option>
                            <option value="nomenclatura">Nomenclatura</option>
                            <option value="contratacion">Contratación</option>
                            <option value="descripcion">Descripción de objeto</option>
                            <option value="valor">Valor</option>
                            <option value="seace">SEACE</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[180px]">
                        <label class="lbl">Palabra</label>
                        <input type="text" x-model="filtros.palabra" maxlength="80" @keydown.enter.prevent="pagina=1; fetchLista()" class="inp">
                    </div>
                    <div>
                        <label class="lbl">Desde</label>
                        <input type="date" x-model="filtros.desde" class="inp">
                    </div>
                    <div>
                        <label class="lbl">Hasta</label>
                        <input type="date" x-model="filtros.hasta" class="inp">
                    </div>
                    <div class="w-20">
                        <label class="lbl">Registros</label>
                        <input type="number" min="1" max="100" x-model="filtros.porPagina" class="inp">
                    </div>
                    <button type="button" @click="pagina=1; fetchLista()" class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <i class="fas fa-search mr-1"></i> Consultar
                    </button>
                    <button type="button" @click="abrirFicha(0)" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <i class="fas fa-plus mr-1"></i> Nuevo
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-[11px]">
                    <thead>
                        <tr class="text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-200 bg-slate-100 sticky top-0">
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Nomenclatura</th>
                            <th class="px-3 py-2">Contratación</th>
                            <th class="px-3 py-2">Descripción de objeto</th>
                            <th class="px-3 py-2 text-right">Valor</th>
                            <th class="px-3 py-2 text-center">Moneda</th>
                            <th class="px-3 py-2">SEACE</th>
                            <th class="px-3 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in items" :key="item.id">
                            <tr class="border-b border-gray-100 hover:bg-slate-50">
                                <td class="px-3 py-2 whitespace-nowrap" x-text="item.fecha"></td>
                                <td class="px-3 py-2 font-semibold" x-text="item.nomenclatura"></td>
                                <td class="px-3 py-2" x-text="item.contratacion"></td>
                                <td class="px-3 py-2" :title="item.descripcion" x-text="item.descripcion_corta"></td>
                                <td class="px-3 py-2 text-right font-semibold" x-text="item.valor"></td>
                                <td class="px-3 py-2 text-center" x-text="item.moneda"></td>
                                <td class="px-3 py-2" x-text="item.seace"></td>
                                <td class="px-3 py-2 text-center">
                                    <button type="button" class="text-slate-600 hover:text-slate-900" title="Ver / editar" @click="abrirFicha(item.id)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && items.length === 0">
                            <td colspan="8" class="px-3 py-10 text-center text-gray-400">No hay fichas para los filtros indicados.</td>
                        </tr>
                        <tr x-show="loading">
                            <td colspan="8" class="px-3 py-10 text-center text-gray-400">Cargando…</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-[11px]">
                <div class="flex items-center gap-1">
                    <button type="button" class="px-2 py-1 rounded border border-gray-200" :disabled="pagina<=1" @click="pagina--; fetchLista()">Anterior</button>
                    <template x-for="p in paginasVisibles()" :key="p">
                        <button type="button" class="px-2 py-1 rounded border"
                                :class="p===pagina ? 'bg-slate-800 text-white border-slate-800' : 'border-gray-200'"
                                @click="pagina=p; fetchLista()" x-text="p"></button>
                    </template>
                    <button type="button" class="px-2 py-1 rounded border border-gray-200" :disabled="pagina>=paginas" @click="pagina++; fetchLista()">Siguiente</button>
                </div>
                <p>Total: <b x-text="total"></b></p>
            </div>
        </div>
    </div>

    <div x-show="modal.show" x-cloak class="modal-overlay" @keydown.escape.window="cerrarFicha()">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-6xl h-[90vh] overflow-hidden flex flex-col" @click.outside="cerrarFicha()">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Mantenimiento fichas de selección SE@CE</h3>
                <button type="button" @click="cerrarFicha()" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <div class="flex-1 overflow-auto custom-scrollbar p-4 space-y-4">
                <fieldset class="border border-gray-200 rounded-xl p-3">
                    <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">I. Información general</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">Nomenclatura</label>
                            <input type="text" x-model="form.nomenclatura" class="inp">
                        </div>
                        <div>
                            <label class="lbl">Fecha</label>
                            <input type="datetime-local" x-model="form.fecha" class="inp">
                        </div>
                        <div>
                            <label class="lbl">Tipo de selección</label>
                            <select x-model="form.tipo_seleccion" class="inp">
                                <option value="0">-- Seleccione --</option>
                                @foreach(($catalogos['tiposSeleccion'] ?? []) as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="lbl">Versión SEACE</label>
                            <select x-model="form.version_seace" class="inp">
                                <option value="0">-- Seleccione --</option>
                                @foreach(($catalogos['versionesSeace'] ?? []) as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="lbl">Identificador</label>
                            <input type="text" x-model="form.identificador" class="inp">
                        </div>
                        <div>
                            <label class="lbl">N° convocatoria</label>
                            <input type="text" x-model="form.convocatoria" class="inp">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="border border-gray-200 rounded-xl p-3">
                    <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">II. Procedimiento</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="lbl">Objeto</label>
                            <select x-model="form.tipo_objeto" class="inp">
                                <option value="0">-- Seleccione --</option>
                                @foreach(($catalogos['tiposObjeto'] ?? []) as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="lbl">Valor referencial</label>
                            <input type="text" x-model="form.valor" class="inp">
                        </div>
                        <div class="md:col-span-2">
                            <label class="lbl">Descripción del objeto</label>
                            <input type="text" x-model="form.descripcion" class="inp">
                        </div>
                        <div>
                            <label class="lbl">Publicación</label>
                            <input type="datetime-local" x-model="form.publicacion" class="inp">
                        </div>
                    </div>
                </fieldset>

                <div class="flex justify-center gap-2">
                    <button type="button" @click="cerrarFicha()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-[10px] font-black uppercase tracking-widest rounded-lg">Cancelar y regresar</button>
                    <button type="button" @click="guardarFicha()" :disabled="saving" class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <span x-text="saving ? 'Guardando…' : 'Almacenar datos'"></span>
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-3" x-show="form.id > 0">
                    <fieldset class="lg:col-span-3 border border-gray-200 rounded-xl p-3">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">III. Cronograma</legend>
                        <p class="text-[10px] text-gray-400 mb-2">Doble clic en Inicio o Fin para editar la fecha.</p>
                        <div class="overflow-auto custom-scrollbar max-h-64">
                            <table class="w-full text-[11px]">
                                <thead>
                                    <tr class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-slate-100">
                                        <th class="px-2 py-1.5 text-left">Etapa</th>
                                        <th class="px-2 py-1.5">Inicio</th>
                                        <th class="px-2 py-1.5">Fin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="eta in cronograma" :key="eta.id">
                                        <tr class="border-b border-gray-100">
                                            <td class="px-2 py-1.5">
                                                <span x-text="eta.etapa"></span>
                                                <template x-if="eta.lugar"><div class="font-bold text-[10px]" x-text="'(' + eta.lugar + ')'"></div></template>
                                            </td>
                                            <td class="px-2 py-1.5 text-center cursor-pointer" @dblclick="editarFecha(eta, 'fe_incio')">
                                                <span x-show="editKey !== eta.id + '-fe_incio'" x-text="eta.inicio"></span>
                                                <input x-show="editKey === eta.id + '-fe_incio'" x-cloak type="date" class="inp"
                                                       x-model="editValor" @blur="guardarFecha(eta, 'fe_incio')" @keydown.enter.prevent="guardarFecha(eta, 'fe_incio')">
                                            </td>
                                            <td class="px-2 py-1.5 text-center cursor-pointer" @dblclick="editarFecha(eta, 'fe_fin')">
                                                <span x-show="editKey !== eta.id + '-fe_fin'" x-text="eta.fin"></span>
                                                <input x-show="editKey === eta.id + '-fe_fin'" x-cloak type="date" class="inp"
                                                       x-model="editValor" @blur="guardarFecha(eta, 'fe_fin')" @keydown.enter.prevent="guardarFecha(eta, 'fe_fin')">
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="cronograma.length === 0">
                                        <td colspan="3" class="px-2 py-6 text-center text-gray-400">Sin cronograma. Guarde la ficha para generarlo.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </fieldset>

                    <div class="lg:col-span-2 space-y-3">
                        <fieldset class="border border-gray-200 rounded-xl p-3">
                            <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">IV. Expedientes de participantes</legend>
                            <div class="overflow-auto custom-scrollbar max-h-36">
                                <table class="w-full text-[11px]">
                                    <thead>
                                        <tr class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-slate-100">
                                            <th class="px-2 py-1.5 text-left">Expediente</th>
                                            <th class="px-2 py-1.5">Fecha y hora</th>
                                            <th class="w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="ex in participantes" :key="'p'+ex.id">
                                            <tr class="border-b border-gray-100">
                                                <td class="px-2 py-1.5 text-slate-700 font-semibold cursor-pointer underline decoration-dashed" @click="abrirHistorico(ex)" x-text="ex.expediente"></td>
                                                <td class="px-2 py-1.5 whitespace-nowrap" x-text="ex.fecha"></td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button type="button" @click="abrirOjo(ex.id)" class="text-slate-600"><i class="fas fa-eye"></i></button>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="participantes.length === 0">
                                            <td colspan="3" class="px-2 py-4 text-center text-gray-400">Sin expedientes</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </fieldset>

                        <fieldset class="border border-gray-200 rounded-xl p-3">
                            <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">IV. Expedientes de apelaciones</legend>
                            <div class="overflow-auto custom-scrollbar max-h-36">
                                <table class="w-full text-[11px]">
                                    <thead>
                                        <tr class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-slate-100">
                                            <th class="px-2 py-1.5 text-left">Expediente</th>
                                            <th class="px-2 py-1.5">Fecha y hora</th>
                                            <th class="w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="ex in apelaciones" :key="'a'+ex.id">
                                            <tr class="border-b border-gray-100">
                                                <td class="px-2 py-1.5 text-slate-700 font-semibold cursor-pointer underline decoration-dashed" @click="abrirHistorico(ex)" x-text="ex.expediente"></td>
                                                <td class="px-2 py-1.5 whitespace-nowrap" x-text="ex.fecha"></td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button type="button" @click="abrirOjo(ex.id)" class="text-slate-600"><i class="fas fa-eye"></i></button>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="apelaciones.length === 0">
                                            <td colspan="3" class="px-2 py-4 text-center text-gray-400">Sin expedientes</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="modalOjo.show" x-cloak class="modal-overlay" @keydown.escape.window="modalOjo.show = false" style="z-index:160">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-[520px] h-[80vh] overflow-hidden flex flex-col" @click.outside="modalOjo.show = false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Expediente</h3>
                <button type="button" @click="modalOjo.show = false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <iframe :src="modalOjo.url" class="flex-1 w-full border-0 bg-white"></iframe>
        </div>
    </div>

    <div x-show="modalHist.show" x-cloak class="modal-overlay" @keydown.escape.window="modalHist.show = false" style="z-index:160">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-[980px] h-[80vh] overflow-hidden flex flex-col" @click.outside="modalHist.show = false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide" x-text="modalHist.titulo"></h3>
                <button type="button" @click="modalHist.show = false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <iframe :src="modalHist.url" class="flex-1 w-full border-0 bg-white"></iframe>
        </div>
    </div>

    <script>
        function fichaSeleccion() {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const routes = @json($routes);

            return {
                loading: false,
                saving: false,
                items: [],
                total: 0,
                pagina: 1,
                paginas: 1,
                toast: { show: false, message: '', type: 'success' },
                filtros: { campo: '', palabra: '', desde: '', hasta: '', porPagina: 10 },
                modal: { show: false },
                modalOjo: { show: false, url: '' },
                modalHist: { show: false, url: '', titulo: 'Histórico' },
                form: {},
                cronograma: [],
                participantes: [],
                apelaciones: [],
                editKey: '',
                editValor: '',

                init() {
                    this.resetForm();
                    this.fetchLista();
                },

                showToast(message, type = 'success') {
                    this.toast = { show: true, message, type };
                    setTimeout(() => { this.toast.show = false; }, 3500);
                },

                paginasVisibles() {
                    const tam = 10;
                    const seccion = Math.floor((this.pagina - 1) / tam);
                    const inicio = seccion * tam + 1;
                    const final = Math.min(inicio + tam - 1, this.paginas);
                    const arr = [];
                    for (let i = inicio; i <= final; i++) arr.push(i);
                    return arr;
                },

                headers() {
                    return {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    };
                },

                async fetchLista() {
                    this.loading = true;
                    try {
                        const res = await fetch(routes.buscar, {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({ ...this.filtros, pagina: this.pagina }),
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
                        this.showToast('Error al cargar las fichas.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                resetForm() {
                    this.form = {
                        id: 0,
                        nomenclatura: '',
                        fecha: '',
                        tipo_seleccion: 0,
                        version_seace: 0,
                        identificador: '',
                        convocatoria: '',
                        tipo_objeto: 0,
                        valor: '',
                        descripcion: '',
                        publicacion: '',
                    };
                    this.cronograma = [];
                    this.participantes = [];
                    this.apelaciones = [];
                    this.editKey = '';
                },

                async abrirFicha(id) {
                    this.resetForm();
                    this.modal.show = true;
                    if (!id) return;
                    try {
                        const res = await fetch(routes.detalle, {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({ id }),
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            this.form = Object.assign(this.form, data.ficha || {});
                            this.cronograma = data.cronograma || [];
                            this.participantes = data.participantes || [];
                            this.apelaciones = data.apelaciones || [];
                        } else {
                            this.showToast(data.message || 'No se pudo abrir la ficha.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al abrir la ficha.', 'error');
                    }
                },

                cerrarFicha() {
                    this.modal.show = false;
                    this.resetForm();
                },

                validar() {
                    if (!this.form.nomenclatura) return 'Debe ingresar la nomenclatura.';
                    if (!this.form.fecha) return 'Debe ingresar la fecha de la selección.';
                    if (!this.form.tipo_seleccion || this.form.tipo_seleccion === '0' || this.form.tipo_seleccion === 0) return 'Debe seleccionar el tipo de selección.';
                    if (!this.form.version_seace || this.form.version_seace === '0' || this.form.version_seace === 0) return 'Debe seleccionar la versión del SE@CE.';
                    if (!this.form.identificador) return 'Debe ingresar el identificador de la convocatoria.';
                    if (!this.form.tipo_objeto || this.form.tipo_objeto === '0' || this.form.tipo_objeto === 0) return 'Debe ingresar el tipo de objeto.';
                    if (!this.form.descripcion) return 'Debe ingresar la descripción del objeto.';
                    if (!this.form.publicacion) return 'Debe ingresar la fecha de la publicación.';
                    return '';
                },

                async guardarFicha() {
                    const error = this.validar();
                    if (error) {
                        this.showToast(error, 'error');
                        return;
                    }
                    if (!confirm('¿Desea guardar la ficha de selección?')) return;
                    this.saving = true;
                    try {
                        const res = await fetch(routes.guardar, {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify(this.form),
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            this.showToast(data.message || 'Ingresado correctamente.');
                            await this.abrirFicha(data.id);
                            this.fetchLista();
                        } else {
                            this.showToast(data.message || 'No se pudo guardar.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al guardar la ficha.', 'error');
                    } finally {
                        this.saving = false;
                    }
                },

                editarFecha(eta, campo) {
                    this.editKey = eta.id + '-' + campo;
                    const raw = campo === 'fe_incio' ? eta.inicio : eta.fin;
                    const parts = (raw || '').split('/');
                    this.editValor = parts.length === 3 ? (parts[2] + '-' + parts[1] + '-' + parts[0]) : '';
                    this.$nextTick(() => {});
                },

                async guardarFecha(eta, campo) {
                    if (this.editKey !== eta.id + '-' + campo) return;
                    const fecha = this.editValor;
                    this.editKey = '';
                    if (!fecha) return;
                    try {
                        const res = await fetch(routes.cronograma, {
                            method: 'POST',
                            headers: this.headers(),
                            body: JSON.stringify({ id: eta.id, campo, fecha }),
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            if (campo === 'fe_incio') eta.inicio = data.fecha;
                            else eta.fin = data.fecha;
                        } else {
                            this.showToast(data.message || 'No se pudo actualizar la fecha.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al actualizar la fecha.', 'error');
                    }
                },

                abrirOjo(id) {
                    this.modalOjo.url = routes.buscaExpediDeta + '?cdgo_tram=' + id;
                    this.modalOjo.show = true;
                },

                abrirHistorico(ex) {
                    this.modalHist.titulo = 'Histórico del expediente N° ' + (ex.expediente || '');
                    this.modalHist.url = routes.historico.replace('__HID__', ex.id);
                    this.modalHist.show = true;
                },
            };
        }
    </script>
</body>
</html>
