<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Pago a proveedores - Tesorería' }} — SIGTRAM</title>
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
        .modal-overlay { position: fixed; inset: 0; z-index: 150; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgb(15 23 42 / 0.7); backdrop-filter: blur(8px); }
        .inp { width: 100%; background: #fff; border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.75rem; font-weight: 600; outline: none; }
        .inp:focus { box-shadow: 0 0 0 2px #64748b; }
        .inp[readonly], .inp:disabled { background: #f1f5f9; color: #64748b; }
        .lbl { display: block; font-size: 9px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: #9ca3af; margin-bottom: 0.25rem; }
    </style>
</head>
<body class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="tesoreriaPago()" x-init="init()">

    <div x-show="busy" x-cloak class="fixed inset-0 z-[180] bg-slate-900/40 flex items-center justify-center">
        <div class="bg-white rounded-full px-5 py-2 text-[10px] font-black uppercase tracking-widest shadow-xl">Procesando…</div>
    </div>
    <div x-show="toast.show" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]">
        <div class="backdrop-blur-md text-white px-6 py-2.5 rounded-full shadow-2xl flex items-center gap-3" :class="toast.type === 'error' ? 'bg-red-700/90' : 'bg-gray-900/90'">
            <i class="text-xs" :class="toast.type === 'error' ? 'fas fa-exclamation-circle text-red-200' : 'fas fa-check-circle text-emerald-400'"></i>
            <p class="text-[10px] font-black uppercase tracking-widest" x-text="toast.message"></p>
        </div>
    </div>

    <div class="p-3 sm:p-5 w-full max-w-[1600px] mx-auto">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col min-h-[85vh]">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Logística · SGL</p>
                    <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Pago a proveedores - Tesorería' }}</h1>
                </div>
                <span class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full"><span x-text="total"></span> requerimientos</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[36%_1fr] flex-1 min-h-0">
                <section class="border-b lg:border-b-0 lg:border-r border-gray-100 flex flex-col min-h-[420px]">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/70">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Lista de requerimientos</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="lbl">Buscar</label>
                                <select x-model="filtros.campo" class="inp">
                                    <option value="nu_tram_todo">Nº Trámite</option>
                                    <option value="de_area">Área</option>
                                    <option value="de_pgo_prvdres">Asunto</option>
                                </select>
                            </div>
                            <div>
                                <label class="lbl">Criterio</label>
                                <input type="text" x-model="filtros.criterio" maxlength="80" @keydown.enter.prevent="pagina=1; fetchLista()" class="inp">
                            </div>
                            <div>
                                <label class="lbl">Fecha</label>
                                <input type="date" x-model="filtros.fecha" class="inp">
                            </div>
                            <div class="flex items-end">
                                <button type="button" @click="pagina=1; fetchLista()" class="w-full px-3 py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                                    <i class="fas fa-search mr-1"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse text-[11px]">
                            <thead>
                                <tr class="text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-200 bg-slate-100 sticky top-0">
                                    <th class="px-3 py-2 w-8">#</th>
                                    <th class="px-3 py-2">N° Trám.</th>
                                    <th class="px-3 py-2">Área</th>
                                    <th class="px-3 py-2 text-center">Fecha</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in lista" :key="item.id">
                                    <tr class="border-b border-gray-100 cursor-pointer hover:bg-slate-50"
                                        :class="item.id === form.id ? 'bg-slate-100' : ''"
                                        @click="abrir(item.id)">
                                        <td class="px-3 py-2" x-text="item.n"></td>
                                        <td class="px-3 py-2 font-semibold" x-text="item.titulo"></td>
                                        <td class="px-3 py-2" :title="item.area_nombre" x-text="item.area"></td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap" x-text="item.fecha"></td>
                                        <td class="px-3 py-2 text-center" title="Autorizar para subir ANEXOS">
                                            <i class="fas fa-eye" :class="item.estado === 7 ? 'text-red-500' : 'text-slate-400'"></i>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="!loading && lista.length === 0">
                                    <td colspan="5" class="px-3 py-10 text-center text-gray-400">No se encontraron resultados.</td>
                                </tr>
                                <tr x-show="loading">
                                    <td colspan="5" class="px-3 py-10 text-center text-gray-400">Cargando…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-[11px]">
                        <div class="flex items-center gap-1">
                            <button type="button" class="px-2 py-1 rounded border border-gray-200" :disabled="pagina<=1" @click="pagina--; fetchLista()">Anterior</button>
                            <template x-for="p in paginasVisibles()" :key="'p'+p">
                                <button type="button" class="px-2 py-1 rounded border"
                                        :class="p===pagina ? 'bg-slate-800 text-white border-slate-800' : 'border-gray-200'"
                                        @click="pagina=p; fetchLista()" x-text="p"></button>
                            </template>
                            <button type="button" class="px-2 py-1 rounded border border-gray-200" :disabled="pagina>=paginas" @click="pagina++; fetchLista()">Siguiente</button>
                        </div>
                        <p>Total: <b x-text="total"></b></p>
                    </div>
                </section>

                <section class="flex flex-col min-h-0 overflow-auto custom-scrollbar p-4 space-y-4">
                    <fieldset class="border border-gray-200 rounded-xl p-3">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Ver requerimiento</legend>
                        <div class="text-[11px] font-semibold text-slate-700 space-y-1 mb-3" x-show="form.id">
                            <p x-text="'Documento : ' + (form.documento || '')"></p>
                            <p x-text="'Asunto : ' + (form.asunto || '')"></p>
                            <p x-text="'Área : ' + (form.area || '')"></p>
                        </div>
                        <p class="text-[11px] text-gray-400 mb-3" x-show="!form.id">Seleccione un requerimiento de la lista.</p>

                        <fieldset class="border border-gray-100 rounded-xl p-2">
                            <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-500">Lista del requerimiento</legend>
                            <div class="overflow-auto custom-scrollbar">
                                <table class="w-full text-[11px]">
                                    <thead>
                                        <tr class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-slate-100">
                                            <th class="px-2 py-1.5 text-center">N°</th>
                                            <th class="px-2 py-1.5 text-center">Cant.</th>
                                            <th class="px-2 py-1.5 text-left">Descripción del requerimiento</th>
                                            <th class="px-2 py-1.5">Pagos</th>
                                            <th class="px-2 py-1.5">Monto</th>
                                            <th class="px-2 py-1.5">SIGA</th>
                                            <th class="px-2 py-1.5">TDR</th>
                                            <th class="px-2 py-1.5">ANE</th>
                                            <th class="px-2 py-1.5">CON</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="it in form.items" :key="it.id">
                                            <tr class="border-b border-gray-100">
                                                <td class="px-2 py-1.5 text-center" x-text="it.n"></td>
                                                <td class="px-2 py-1.5 text-center" x-text="it.cantidad"></td>
                                                <td class="px-2 py-1.5" x-text="it.descripcion"></td>
                                                <td class="px-2 py-1.5 text-center" x-text="it.pagos"></td>
                                                <td class="px-2 py-1.5 text-right" x-text="it.monto ?? ''"></td>
                                                <td class="px-2 py-1.5 text-center" x-text="it.siga"></td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <a x-show="it.url_tdr" :href="urlArchivo(it.url_tdr)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                                                    <span x-show="!it.url_tdr">-</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <a x-show="it.url_ane" :href="urlArchivo(it.url_ane)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                                                    <span x-show="!it.url_ane">-</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button type="button" x-show="it.puede_ver_con" :title="it.pendiente_pago ? 'Esperando registro de pago' : 'Solicitar Conformidad'" @click="abrirItem(it.id)" class="hover:text-slate-800" :class="it.pendiente_pago ? 'text-red-500' : 'text-slate-500'">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="form.id && form.items.length === 0">
                                            <td colspan="9" class="px-2 py-6 text-center text-gray-400">Sin ítems.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </fieldset>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_terminar">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Concluir proceso de pago</legend>
                        <div class="text-center">
                            <button type="button" @click="terminar()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">Terminar Proceso</button>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.cdgo_dtos_ntrnos > 0">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Seguimiento del trámite</legend>
                        <iframe :src="urlSeguimiento()" class="w-full h-[300px] border-0 rounded-lg bg-white"></iframe>
                    </fieldset>
                </section>
            </div>
        </div>
    </div>

    <div x-show="itemModal.show" x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Conformidad del servicio</h3>
                <button type="button" @click="itemModal.show=false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <div class="flex-1 overflow-auto custom-scrollbar p-4 space-y-3 text-[11px]" x-show="itemModal.data">
                <p class="font-semibold" x-text="'Servicio : ' + (itemModal.data?.servicio || '')"></p>
                <p>N° Siga : <span x-text="itemModal.data?.siga"></span></p>
                <p>N° O/S : <span x-text="itemModal.data?.os"></span>
                    <a x-show="itemModal.data?.url_os" :href="urlArchivo(itemModal.data?.url_os)" target="_blank" class="text-slate-600 ml-1"><i class="fas fa-paperclip"></i></a>
                </p>
                <p>N° Rubro : <span x-text="itemModal.data?.rubro"></span></p>
                <p>N° SIAF : <span x-text="itemModal.data?.siaf"></span></p>
                <p>Nombre : <span x-text="itemModal.data?.nombre"></span></p>

                <div x-show="(itemModal.data?.memos || []).length" class="overflow-auto">
                    <table class="w-full text-[11px]">
                        <thead>
                            <tr class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-slate-100">
                                <th class="px-2 py-1.5">N°</th>
                                <th class="px-2 py-1.5 text-left">Documento de Contabilidad</th>
                                <th class="px-2 py-1.5">Monto</th>
                                <th class="px-2 py-1.5">Conformidad</th>
                                <th class="px-2 py-1.5">Con</th>
                                <th class="px-2 py-1.5">Pag</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="m in (itemModal.data?.memos || [])" :key="m.id">
                                <tr class="border-b border-gray-100">
                                    <td class="px-2 py-1.5 text-center" x-text="m.n"></td>
                                    <td class="px-2 py-1.5" x-text="m.documento"></td>
                                    <td class="px-2 py-1.5 text-right" x-text="m.monto"></td>
                                    <td class="px-2 py-1.5 text-center" x-text="m.fecha"></td>
                                    <td class="px-2 py-1.5 text-center">
                                        <a x-show="m.url" :href="urlArchivo(m.url)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                                        <span x-show="!m.url">-</span>
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        <button type="button" x-show="m.puede_pagar" title="Registrar comprobante" @click="abrirPago(m.id)" class="text-emerald-600 hover:text-emerald-800">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </button>
                                        <span x-show="!m.puede_pagar">-</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div x-show="pagoModal.show" x-cloak class="modal-overlay" @keydown.escape.window="pagoModal.show=false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col" @click.outside="pagoModal.show=false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Comprobantes de pago</h3>
                <button type="button" @click="pagoModal.show=false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <div class="flex-1 overflow-auto custom-scrollbar p-4 space-y-3" x-show="pagoModal.data">
                <p class="text-[11px] font-semibold" x-text="'Trámite : ' + (pagoModal.data?.tramite || '')"></p>
                <p class="text-[10px] text-amber-700 font-bold uppercase" x-show="pagoModal.data?.ya_pagado">Este memorandum ya tiene comprobante.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="lbl">N° Comprobante</label>
                        <input type="text" class="inp" x-model="pagoModal.data.nu_cmprbnte_pgo">
                    </div>
                    <div>
                        <label class="lbl">Fecha</label>
                        <input type="date" class="inp" x-model="pagoModal.data.fe_cmprbnte_pgo">
                    </div>
                    <div>
                        <label class="lbl">Banco</label>
                        <select class="inp" x-model="pagoModal.data.cdgo_bncos">
                            <option value="0">-- SELECCIONE --</option>
                            <template x-for="b in (pagoModal.data?.catalogos?.bancos || [])" :key="b.id">
                                <option :value="b.id" x-text="b.nombre"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">N° SIAF</label>
                        <input type="text" class="inp" x-model="pagoModal.data.nu_siaf">
                    </div>
                    <div>
                        <label class="lbl">Tipo</label>
                        <input type="text" class="inp" list="lista-tipo-comp" x-model="pagoModal.data.nu_tpo_cmprbnte_pgo">
                        <datalist id="lista-tipo-comp">
                            <template x-for="t in (pagoModal.data?.catalogos?.tipos || [])" :key="t">
                                <option :value="t"></option>
                            </template>
                        </datalist>
                    </div>
                    <div>
                        <label class="lbl">N°</label>
                        <input type="text" class="inp" x-model="pagoModal.data.nu_co">
                    </div>
                    <div class="md:col-span-2">
                        <label class="lbl">Beneficiario</label>
                        <input type="text" class="inp" list="lista-benef" x-model="pagoModal.data.no_bnfcrio">
                        <datalist id="lista-benef">
                            <template x-for="t in (pagoModal.data?.catalogos?.beneficiarios || [])" :key="t">
                                <option :value="t"></option>
                            </template>
                        </datalist>
                    </div>
                    <div>
                        <label class="lbl">Rubro</label>
                        <input type="text" class="inp" x-model="pagoModal.data.no_rb">
                    </div>
                    <div>
                        <label class="lbl">TR</label>
                        <input type="text" class="inp" x-model="pagoModal.data.no_tr">
                    </div>
                    <div>
                        <label class="lbl">Importe</label>
                        <input type="number" step="0.01" class="inp" x-model="pagoModal.data.nu_imprte">
                    </div>
                    <div>
                        <label class="lbl">Orden</label>
                        <input type="text" class="inp" list="lista-orden" x-model="pagoModal.data.no_orden">
                        <datalist id="lista-orden">
                            <template x-for="t in (pagoModal.data?.catalogos?.ordenes || [])" :key="t">
                                <option :value="t"></option>
                            </template>
                        </datalist>
                    </div>
                    <div class="md:col-span-2">
                        <label class="lbl">Detalle</label>
                        <input type="text" class="inp" list="lista-dtlle" x-model="pagoModal.data.no_dtlle">
                        <datalist id="lista-dtlle">
                            <template x-for="t in (pagoModal.data?.catalogos?.detalles || [])" :key="t">
                                <option :value="t"></option>
                            </template>
                        </datalist>
                    </div>
                </div>
                <div class="text-center pt-2">
                    <button type="button" @click="guardarPago()" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">Generar Comprobante</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function tesoreriaPago() {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const routes = @json($routes);
            const openId = {{ (int) ($openPgoId ?? 0) }};

            return {
                loading: false,
                busy: false,
                lista: [],
                total: 0,
                pagina: 1,
                paginas: 1,
                toast: { show: false, message: '', type: 'success' },
                filtros: { campo: 'nu_tram_todo', criterio: '', fecha: '' },
                form: {},
                itemModal: { show: false, data: null },
                pagoModal: { show: false, data: null },

                init() {
                    this.resetForm();
                    this.fetchLista().then(() => {
                        if (openId > 0) this.abrir(openId);
                    });
                },

                showToast(message, type = 'success') {
                    this.toast = { show: true, message, type };
                    setTimeout(() => { this.toast.show = false; }, 3500);
                },

                paginasVisibles() {
                    const tam = 12;
                    const seccion = Math.floor((this.pagina - 1) / tam);
                    const inicio = seccion * tam + 1;
                    const final = Math.min(inicio + tam - 1, Math.max(this.paginas, 1));
                    const arr = [];
                    for (let i = inicio; i <= final; i++) arr.push(i);
                    return arr;
                },

                headersJson() {
                    return {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    };
                },

                urlArchivo(nom) {
                    if (!nom) return '#';
                    return routes.archivo + '?nom_archivo=' + encodeURIComponent(nom);
                },

                urlSeguimiento() {
                    return routes.seguimiento + '?nu_expe=' + (this.form.cdgo_dtos_ntrnos || 0);
                },

                resetForm() {
                    this.form = {
                        id: 0, asunto: '', area: '', estado: 0, cdgo_dtos_ntrnos: 0,
                        documento: '', puede_terminar: false, items: [],
                    };
                    return this.form;
                },

                async fetchLista() {
                    this.loading = true;
                    try {
                        const q = new URLSearchParams({ ...this.filtros, pagina: this.pagina });
                        const res = await fetch(routes.listar + '?' + q.toString(), { headers: this.headersJson() });
                        const data = await res.json();
                        if (data.success) {
                            this.lista = data.data.items || [];
                            this.total = data.data.total || 0;
                            this.pagina = data.data.pagina || 1;
                            this.paginas = data.data.paginas || 1;
                        } else {
                            this.showToast(data.error || 'No se pudo cargar la lista.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al cargar la lista.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async abrir(id) {
                    try {
                        const res = await fetch(routes.detalle, {
                            method: 'POST',
                            headers: this.headersJson(),
                            body: JSON.stringify({ id }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.form = Object.assign(this.resetForm(), data.data || {});
                        } else {
                            this.showToast(data.error || 'No se pudo abrir el requerimiento.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al abrir el requerimiento.', 'error');
                    }
                },

                async abrirItem(id) {
                    const res = await fetch(routes.itemDetalle, {
                        method: 'POST',
                        headers: this.headersJson(),
                        body: JSON.stringify({ item_id: id }),
                    });
                    const data = await res.json();
                    if (!data.success) return this.showToast(data.error || 'No se pudo abrir el ítem.', 'error');
                    this.itemModal = { show: true, data: data.data };
                },

                async abrirPago(memoId) {
                    const res = await fetch(routes.formComprobante, {
                        method: 'POST',
                        headers: this.headersJson(),
                        body: JSON.stringify({ memo_id: memoId }),
                    });
                    const data = await res.json();
                    if (!data.success) return this.showToast(data.error || 'No se pudo abrir el comprobante.', 'error');
                    this.pagoModal = { show: true, data: data.data };
                },

                async guardarPago() {
                    if (!this.pagoModal.data) return;
                    if (!confirm('¿Generar el comprobante de pago?')) return;
                    this.busy = true;
                    try {
                        const res = await fetch(routes.guardarComprobante, {
                            method: 'POST',
                            headers: this.headersJson(),
                            body: JSON.stringify(this.pagoModal.data),
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showToast(data.message);
                            this.pagoModal.show = false;
                            this.itemModal.show = false;
                            this.resetForm();
                            await this.fetchLista();
                            if (data.data?.id) await this.abrir(data.data.id);
                        } else this.showToast(data.error || 'No se pudo generar el comprobante.', 'error');
                    } finally {
                        this.busy = false;
                    }
                },

                async terminar() {
                    if (!confirm('¿Terminar el proceso de pago?')) return;
                    this.busy = true;
                    try {
                        const res = await fetch(routes.terminar, {
                            method: 'POST',
                            headers: this.headersJson(),
                            body: JSON.stringify({ id: this.form.id }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showToast(data.message);
                            await this.abrir(this.form.id);
                            await this.fetchLista();
                        } else this.showToast(data.error || 'No se pudo terminar el proceso.', 'error');
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
    </script>
</body>
</html>
