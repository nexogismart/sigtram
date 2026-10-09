<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Pago a proveedores - Presupuesto' }} — SIGTRAM</title>
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
        .btn { padding: 0.5rem 1rem; font-size: 10px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; border-radius: 0.5rem; }
    </style>
</head>
<body class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="presupuestoPago()" x-init="init()">

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
                    <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Pago a proveedores - Presupuesto' }}</h1>
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
                                        <td class="px-3 py-2 text-center">
                                            <i class="fas fa-eye text-amber-400"></i>
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
                                            <th class="px-2 py-1.5 text-left">Descripción</th>
                                            <th class="px-2 py-1.5">Pagos</th>
                                            <th class="px-2 py-1.5">Monto</th>
                                            <th class="px-2 py-1.5">SIGA</th>
                                            <th class="px-2 py-1.5">SIAF</th>
                                            <th class="px-2 py-1.5">CCNM</th>
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
                                                <td class="px-2 py-1.5 text-center cursor-pointer font-semibold" title="Doble clic para editar SIAF" @dblclick="editarSiaf(it)" x-text="it.ccp_nta || '0000'"></td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <a x-show="it.url_ccp" :href="urlArchivo(it.url_ccp)" target="_blank" class="text-slate-600 font-semibold" x-text="it.ccp"></a>
                                                    <span x-show="!it.url_ccp">-</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button type="button" :title="it.titulo_icono || 'Habilitación presupuestal'" @click="abrirItem(it.id)">
                                                        <i class="fas fa-eye" :class="it.icono === 'amarillo' ? 'text-amber-400' : 'text-slate-400'"></i>
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

                    <div class="text-center" x-show="form.puede_publicar">
                        <button type="button" @click="publicar()" class="btn bg-slate-800 text-white">Guardar y publicar</button>
                    </div>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_enviar">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Generar memorandum</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="lbl">Documento</label>
                                <select x-model="envio.tipo_id" @change="cargarSemilla()" class="inp">
                                    <option value="">-- Seleccione --</option>
                                    @foreach(($catalogos['tiposMemo'] ?? []) as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="lbl">N° Trámite</label><input type="text" x-model="envio.semilla" class="inp"></div>
                            <div class="flex items-end"><button type="button" @click="verBorrador()" class="w-full btn bg-slate-600 text-white">Generar borrador</button></div>
                            <div class="md:col-span-2"><label class="lbl">Adjuntar PDF</label><input type="file" accept="application/pdf" @change="envio.adjunto = $event.target.files[0]" class="inp"></div>
                        </div>
                        <div class="text-center mt-3">
                            <button type="button" @click="enviarAbastecimiento()" class="btn bg-slate-800 text-white">Enviar Abastecimiento</button>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_devolver">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Devolver solicitud de CCP</legend>
                        <div class="text-center">
                            <button type="button" @click="abrirDevolver()" class="btn bg-red-50 text-red-700 border border-red-200">Devolver a logística</button>
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
                <h3 class="text-sm font-bold uppercase tracking-wide">Habilitación presupuestal</h3>
                <button type="button" @click="itemModal.show=false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <div class="flex-1 overflow-auto custom-scrollbar p-4 space-y-3 text-[11px]" x-show="itemModal.data">
                <fieldset class="border border-gray-200 rounded-xl p-3">
                    <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-500">Datos del proveedor</legend>
                    <p class="font-semibold mb-2" x-text="'Servicio : ' + (itemModal.data?.servicio || '')"></p>
                    <div class="grid grid-cols-2 gap-3">
                        <p>N° Siga : <span x-text="itemModal.data?.siga"></span>
                            <a x-show="itemModal.data?.url_siga" :href="urlArchivo(itemModal.data?.url_siga)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                        </p>
                        <p>TDR :
                            <a x-show="itemModal.data?.url_tdr" :href="urlArchivo(itemModal.data?.url_tdr)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                        </p>
                        <p>Monto : <span x-text="itemModal.data?.monto"></span> / <span x-text="itemModal.data?.pagos"></span> Pagos</p>
                        <p>Días pago : <span x-text="itemModal.data?.dias_pago"></span></p>
                        <div><label class="lbl">N° Meta</label><input type="text" class="inp" x-model="itemModal.data.meta" :readonly="itemModal.data?.readonly"></div>
                        <div><label class="lbl">N° Rubro</label><input type="text" class="inp" x-model="itemModal.data.rubro" :readonly="itemModal.data?.readonly"></div>
                        <div><label class="lbl">N° SIAF</label><input type="text" class="inp" x-model="itemModal.data.ccp_nta" :readonly="itemModal.data?.readonly"></div>
                        <div><label class="lbl">N° Esp. Gasto</label><input type="text" class="inp" x-model="itemModal.data.esp_gasto" :readonly="itemModal.data?.readonly"></div>
                        <div><label class="lbl">N° CCMN</label><input type="text" class="inp" x-model="itemModal.data.ccp"></div>
                        <div class="col-span-2">
                            <label class="lbl">SIAF (PDF)</label>
                            <div class="flex items-center gap-2">
                                <input type="file" accept="application/pdf" class="inp" @change="itemModal.siafFile = $event.target.files[0]">
                                <a x-show="itemModal.data?.url_ccp_nta" :href="urlArchivo(itemModal.data?.url_ccp_nta)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                            </div>
                        </div>
                    </div>
                </fieldset>
                <div class="text-center flex justify-center gap-2">
                    <button type="button" @click="itemModal.show=false; abrir(form.id)" class="btn bg-slate-100 text-slate-700">Cerrar y actualizar</button>
                    <button type="button" @click="guardarHabilitacion()" class="btn bg-slate-800 text-white">Guardar habilitación</button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="devolverModal.show" x-cloak class="modal-overlay" @keydown.escape.window="devolverModal.show=false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden" @click.outside="devolverModal.show=false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wide">Devolver</h3>
                <button type="button" @click="devolverModal.show=false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <div class="p-4 space-y-3">
                <p class="text-[11px] font-semibold" x-text="'Documento : ' + (form.documento || '')"></p>
                <div>
                    <label class="lbl">Motivo de devolución</label>
                    <textarea class="inp min-h-[90px]" x-model="devolverModal.motivo"></textarea>
                </div>
                <div class="text-center">
                    <button type="button" @click="devolver()" class="btn bg-red-700 text-white">Devolver requerimiento</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function presupuestoPago() {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const routes = @json($routes);
            const openId = {{ (int) ($openPgoId ?? 0) }};
            return {
                loading: false, busy: false, lista: [], total: 0, pagina: 1, paginas: 1,
                toast: { show: false, message: '', type: 'success' },
                filtros: { campo: 'nu_tram_todo', criterio: '', fecha: '' },
                form: {}, envio: { tipo_id: '', semilla: '', adjunto: null },
                itemModal: { show: false, data: null, siafFile: null },
                devolverModal: { show: false, motivo: '' },

                init() {
                    this.resetForm();
                    this.fetchLista().then(() => { if (openId > 0) this.abrir(openId); });
                },
                showToast(message, type = 'success') {
                    this.toast = { show: true, message, type };
                    setTimeout(() => { this.toast.show = false; }, 3500);
                },
                paginasVisibles() {
                    const tam = 12, seccion = Math.floor((this.pagina - 1) / tam), inicio = seccion * tam + 1;
                    const final = Math.min(inicio + tam - 1, Math.max(this.paginas, 1)), arr = [];
                    for (let i = inicio; i <= final; i++) arr.push(i);
                    return arr;
                },
                headersJson() { return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }; },
                headersForm() { return { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }; },
                urlArchivo(nom) { return nom ? routes.archivo + '?nom_archivo=' + encodeURIComponent(nom) : '#'; },
                urlSeguimiento() { return routes.seguimiento + '?nu_expe=' + (this.form.cdgo_dtos_ntrnos || 0); },
                resetForm() {
                    this.form = { id: 0, asunto: '', area: '', estado: 0, cdgo_dtos_ntrnos: 0, documento: '', items: [],
                        puede_enviar: false, puede_devolver: false, puede_publicar: false };
                    this.envio = { tipo_id: '', semilla: '', adjunto: null };
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
                        } else this.showToast(data.error || 'No se pudo cargar la lista.', 'error');
                    } catch (e) { this.showToast('Error al cargar la lista.', 'error'); }
                    finally { this.loading = false; }
                },
                async abrir(id) {
                    try {
                        const res = await fetch(routes.detalle, { method: 'POST', headers: this.headersJson(), body: JSON.stringify({ id }) });
                        const data = await res.json();
                        if (data.success) this.form = Object.assign(this.resetForm(), data.data || {});
                        else this.showToast(data.error || 'No se pudo cargar el requerimiento.', 'error');
                    } catch (e) { this.showToast('Error al abrir el requerimiento.', 'error'); }
                },
                async abrirItem(id) {
                    const data = await this.postJson(routes.itemDetalle, { item_id: id });
                    if (!data.success) return this.showToast(data.error || 'No se pudo abrir el ítem.', 'error');
                    this.itemModal = { show: true, data: data.data, siafFile: null };
                },
                async editarSiaf(it) {
                    const valor = prompt('N° SIAF', it.ccp_nta || '');
                    if (valor === null) return;
                    const data = await this.postJson(routes.actualizarSiaf, { item_id: it.id, valor });
                    if (data.success) { this.showToast(data.message); await this.abrir(this.form.id); }
                    else this.showToast(data.error || 'No se pudo actualizar.', 'error');
                },
                async guardarHabilitacion() {
                    const fd = new FormData();
                    const d = this.itemModal.data;
                    fd.append('item_id', d.id);
                    fd.append('ccp', d.ccp || '');
                    fd.append('ccp_nta', d.ccp_nta || '');
                    fd.append('meta', d.meta || '');
                    fd.append('rubro', d.rubro || '');
                    fd.append('esp_gasto', d.esp_gasto || '');
                    if (this.itemModal.siafFile) fd.append('siaf_file', this.itemModal.siafFile);
                    await this.postForm(routes.guardarHabilitacion, fd, true);
                },
                async publicar() {
                    if (!confirm('¿Guardar y publicar el requerimiento?')) return;
                    const data = await this.postJson(routes.publicar, { id: this.form.id });
                    if (data.success) { this.showToast(data.message); await this.fetchLista(); await this.abrir(this.form.id); }
                    else this.showToast(data.error || 'No se pudo publicar.', 'error');
                },
                async cargarSemilla() {
                    if (!this.envio.tipo_id) return;
                    const data = await this.postJson(routes.semilla, { tipo_id: this.envio.tipo_id });
                    if (data.success) this.envio.semilla = data.data.nu_smlla_intrno;
                },
                async verBorrador() {
                    if (!this.envio.tipo_id) return this.showToast('Seleccione el documento.', 'error');
                    this.busy = true;
                    try {
                        const res = await fetch(routes.verBorrador, {
                            method: 'POST', headers: this.headersJson(),
                            body: JSON.stringify({
                                tipo_id: this.envio.tipo_id, semilla: this.envio.semilla,
                                asunto: 'TRAMITE DE PAGO', dest_area: 101,
                                plantilla: 'memorandum_aprueba_habilitacion.docx',
                            }),
                        });
                        if (!res.ok) {
                            const err = await res.json().catch(() => ({}));
                            return this.showToast(err.message || 'No se pudo generar el borrador.', 'error');
                        }
                        const blob = await res.blob();
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url; a.download = 'borrador.docx'; a.click();
                        URL.revokeObjectURL(url);
                    } finally { this.busy = false; }
                },
                async enviarAbastecimiento() {
                    if (!this.envio.tipo_id || !this.envio.semilla) return this.showToast('Complete documento y N° de trámite.', 'error');
                    if (!this.envio.adjunto) return this.showToast('Adjunte el PDF.', 'error');
                    if (!confirm('¿Enviar a Abastecimiento?')) return;
                    const fd = new FormData();
                    fd.append('id', this.form.id);
                    fd.append('tipo_id', this.envio.tipo_id);
                    fd.append('semilla', this.envio.semilla);
                    fd.append('adjunto', this.envio.adjunto);
                    await this.postForm(routes.enviarAbastecimiento, fd, false);
                },
                abrirDevolver() {
                    this.devolverModal = { show: true, motivo: 'SE DEVUELVE EL REQUERIMIENTO POR ' };
                },
                async devolver() {
                    if (!this.devolverModal.motivo.trim()) return this.showToast('Ingrese el motivo de devolución.', 'error');
                    if (!confirm('¿Devolver el requerimiento a Logística?')) return;
                    this.busy = true;
                    try {
                        const data = await this.postJson(routes.devolver, { id: this.form.id, motivo: this.devolverModal.motivo });
                        if (data.success) { this.showToast(data.message); this.devolverModal.show = false; this.resetForm(); await this.fetchLista(); }
                        else this.showToast(data.error || 'No se pudo devolver.', 'error');
                    } finally { this.busy = false; }
                },
                async postJson(url, body) {
                    const res = await fetch(url, { method: 'POST', headers: this.headersJson(), body: JSON.stringify(body) });
                    return res.json();
                },
                async postForm(url, fd, recargarItem) {
                    this.busy = true;
                    try {
                        const res = await fetch(url, { method: 'POST', headers: this.headersForm(), body: fd });
                        const data = await res.json();
                        if (data.success) {
                            this.showToast(data.message || 'Listo.');
                            if (recargarItem) this.itemModal.show = false;
                            const id = data.data?.id || this.form.id;
                            await this.fetchLista();
                            if (id) await this.abrir(id);
                        } else this.showToast(data.error || 'No se pudo completar la operación.', 'error');
                    } catch (e) { this.showToast('Error de comunicación.', 'error'); }
                    finally { this.busy = false; }
                },
            };
        }
    </script>
</body>
</html>
