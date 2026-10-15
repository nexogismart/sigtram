<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Notificar Plataforma' }} — SIGTRAM</title>
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
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.notificar_pltfrma'])
</head>
<body data-sigtram-screen="mod_gm.mod_tram_exte.notificar_pltfrma" class="sigtram-module bg-[#f8fafc] text-gray-800 min-h-screen" x-data="notificarPlataforma()" x-init="init()">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.notificar_pltfrma'])

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
                    <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Notificar Plataforma' }}</h1>
                </div>
                <span class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full"><span x-text="total"></span> registros</span>
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
                        <input type="text" x-model="filtros.criterio" @keydown.enter.prevent="pagina=1; fetchLista()"
                               class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <div class="w-full sm:w-64">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Buscar por</label>
                        <select x-model="filtros.campo" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                            <option value="p_dto_trmtes.nu_expe_todo">Nº de Expediente</option>
                            <option value="p_usrios.no_crto">Nombre o Razón Social</option>
                            <option value="a_tpo_trmtes.de_tpo_trmte">Tipo de documento</option>
                        </select>
                    </div>
                    <button type="button" @click="pagina=1; fetchLista()"
                            class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                    <button type="button" @click="verTodo()"
                            class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-lg">
                        Ver todo
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
                    <button type="button" @click="pagina=1; fetchLista()"
                            class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-auto custom-scrollbar relative min-h-[320px]">
                <div x-show="loading" x-cloak class="absolute inset-0 bg-white/70 z-10 flex items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-slate-600"></i>
                </div>
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-200 bg-slate-50 sticky top-0">
                            <th class="px-3 py-2">Nº Expe</th>
                            <th class="px-2 py-2 text-center">Sil</th>
                            <th class="px-3 py-2">Tipo Doc</th>
                            <th class="px-3 py-2">Oficina</th>
                            <th class="px-2 py-2 text-center">Fo</th>
                            <th class="px-3 py-2 text-center">Estado</th>
                            <th class="px-2 py-2 text-center">V</th>
                            <th class="px-2 py-2 text-center">Not</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in items" :key="item.cdgo_trmte_dcmnto">
                            <tr class="border-b border-gray-100 hover:bg-slate-50 cursor-pointer text-[11px]"
                                @click="abrirHojaRuta(item.cdgo_dto_trmte)">
                                <td class="px-3 py-2 font-bold text-slate-800 whitespace-nowrap" x-text="item.nu_expe_fmt || item.nu_expe_todo"></td>
                                <td class="px-2 py-2 text-center" x-text="item.silencio"></td>
                                <td class="px-3 py-2 text-gray-600" x-text="item.tipo_corto || item.de_tpo_trmte" :title="item.de_tpo_trmte"></td>
                                <td class="px-3 py-2 text-gray-600" x-text="item.dc_area" :title="item.de_area"></td>
                                <td class="px-2 py-2 text-center" x-text="item.nu_foli_trmte"></td>
                                <td class="px-3 py-2 text-center text-[10px] font-bold" x-text="item.dc_estdo_trmte"></td>
                                <td class="px-2 py-2 text-center" @click.stop>
                                    <button type="button" @click="abrirBusca(item)" class="text-slate-600 hover:text-slate-900" title="Ver expediente">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                                <td class="px-2 py-2 text-center" @click.stop>
                                    <button type="button" x-show="Number(item.cdgo_estdo_trmte) === 3" @click="abrirNotificar(item)"
                                            class="text-slate-700 hover:text-slate-900" title="Notificar expediente">
                                        <i class="fas fa-bell"></i>
                                    </button>
                                    <span x-show="Number(item.cdgo_estdo_trmte) === 26" class="text-emerald-600" title="Notificado">
                                        <i class="fas fa-check-circle"></i>
                                    </span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && items.length === 0">
                            <td colspan="8" class="px-3 py-10 text-center text-[11px] font-bold text-gray-400">No se encontraron resultados</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-4 py-2 flex items-center justify-between text-[10px] font-bold text-gray-500">
                <span>Total: <span x-text="total"></span></span>
                <div class="flex items-center gap-2">
                    <button type="button" @click="pagina--; fetchLista()" :disabled="pagina <= 1" class="disabled:opacity-30"><i class="fas fa-chevron-left"></i></button>
                    <span x-text="pagina + ' / ' + paginas"></span>
                    <button type="button" @click="pagina++; fetchLista()" :disabled="pagina >= paginas" class="disabled:opacity-30"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="modal.show" x-cloak class="modal-overlay" @keydown.escape.window="cerrarModal()">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden" @click.outside="cerrarModal()">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wide">Notificar expediente</h3>
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Mas datos</p>
                </div>
                <button type="button" @click="cerrarModal()" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <form @submit.prevent="guardarNotificacion()" class="p-5 space-y-4">
                <div x-show="modal.errors.length" class="bg-red-50 border border-red-200 rounded-xl p-3 text-xs font-semibold text-red-700">
                    <template x-for="err in modal.errors" :key="err"><p x-text="err"></p></template>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Fecha</label>
                    <input type="datetime-local" x-model="form.fecha"
                           class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Adjuntar</label>
                    <input type="file" @change="onArchivo($event)"
                           class="w-full text-xs font-semibold file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-800 file:text-white file:text-[10px] file:font-black file:uppercase">
                    <p x-show="form.archivoNombre" class="mt-1 text-[10px] font-bold text-slate-600" x-text="form.archivoNombre"></p>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Observaciones</label>
                    <textarea x-model="form.observaciones" rows="5"
                              class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" @click="cerrarModal()" :disabled="guardando"
                            class="px-4 py-2 border border-gray-300 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-600">Cancelar</button>
                    <button type="submit" :disabled="guardando"
                            class="px-5 py-2 bg-slate-700 hover:bg-slate-800 disabled:opacity-50 text-white rounded-xl text-[10px] font-black uppercase tracking-widest">
                        <span x-show="!guardando">Notificar</span>
                        <span x-show="guardando" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Procesando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function notificarPlataforma() {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const routes = {
                tabla: @json(route('tramites.notificar.tabla', [], false)),
                notificar: @json(route('tramites.notificar.action', ['id' => '__ID__'], false)),
                buscaExpediDeta: @json(route('tramites.busca_expedi_deta', [], false)),
                hojaRuta: @json(route('mod_gm.segi_expedi.hoja_ruta', ['id' => '__HID__'], false)),
            };

            return {
                loading: false,
                guardando: false,
                items: [],
                total: 0,
                pagina: 1,
                paginas: 1,
                toast: { show: false, message: '', type: 'success' },
                filtros: { modo: 'generico', criterio: '', campo: 'p_dto_trmtes.nu_expe_todo', desde: '', hasta: '' },
                modal: { show: false, errors: [] },
                activo: {},
                form: { fecha: '', observaciones: '', archivo: null, archivoNombre: '' },

                init() { this.fetchLista(); },

                showToast(message, type = 'success') {
                    this.toast = { show: true, message, type };
                    setTimeout(() => { this.toast.show = false; }, 4000);
                },
                verTodo() {
                    this.filtros.criterio = '';
                    this.filtros.desde = '';
                    this.filtros.hasta = '';
                    this.pagina = 1;
                    this.fetchLista();
                },
                abrirHojaRuta(id) {
                    window.open(routes.hojaRuta.replace('__HID__', id), 'ventana1', 'scrollbars=auto,width=770,height=310');
                },
                abrirBusca(item) {
                    window.open(routes.buscaExpediDeta + '?cdgo_tram=' + item.cdgo_dto_trmte, 'ventana2', 'scrollbars=auto,width=470,height=410');
                },
                abrirNotificar(item) {
                    const now = new Date();
                    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                    this.activo = item;
                    this.form = {
                        fecha: local,
                        observaciones: 'SE LE ENTREGA EL EXPEDIENTE A LA SR(A):     CON DNI:',
                        archivo: null,
                        archivoNombre: '',
                    };
                    this.modal = { show: true, errors: [] };
                },
                cerrarModal() {
                    if (this.guardando) return;
                    this.modal.show = false;
                },
                onArchivo(event) {
                    const file = event.target.files[0] || null;
                    this.form.archivo = file;
                    this.form.archivoNombre = file ? file.name : '';
                },
                async fetchLista() {
                    this.loading = true;
                    try {
                        const res = await fetch(routes.tabla, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                modo: this.filtros.modo,
                                criterio: this.filtros.criterio,
                                campo: this.filtros.campo,
                                desde: this.filtros.desde,
                                hasta: this.filtros.hasta,
                                page: this.pagina,
                                per_page: 20,
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
                async guardarNotificacion() {
                    const obs = (this.form.observaciones || '').trim();
                    if (obs === '') {
                        this.modal.errors = ['Debe especificar las observaciones'];
                        return;
                    }
                    if (!confirm('Esta apunto de notificar este expediente desea continuar?')) {
                        return;
                    }
                    if (this.guardando) return;
                    this.guardando = true;
                    this.modal.errors = [];
                    const fd = new FormData();
                    fd.append('fecha', this.form.fecha);
                    fd.append('observaciones', this.form.observaciones);
                    if (this.form.archivo) fd.append('documento', this.form.archivo);
                    try {
                        const res = await fetch(routes.notificar.replace('__ID__', this.activo.cdgo_dto_trmte), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            body: fd,
                        });
                        const data = await res.json();
                        if (res.ok && data.status === 'success') {
                            this.showToast('Los expedientes fueron notificados');
                            this.modal.show = false;
                            await this.fetchLista();
                        } else if (data.errors) {
                            this.modal.errors = Object.values(data.errors).flat();
                        } else {
                            this.modal.errors = [data.message || 'No se pudo notificar.'];
                        }
                    } catch (e) {
                        this.modal.errors = ['Error de red al notificar.'];
                    } finally {
                        this.guardando = false;
                    }
                },
            };
        }
    </script>
</body>
</html>
