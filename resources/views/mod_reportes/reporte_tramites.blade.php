<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reporte de Trámites — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: "Figtree", "Segoe UI", Tahoma, sans-serif; }
        h1, .num { font-family: "Outfit", "Figtree", sans-serif; }
        [x-cloak] { display: none !important; }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        :focus-visible { outline: 2px solid #334155; outline-offset: 2px; }
    </style>
</head>
<body class="bg-[#f8fafc] text-gray-800 min-h-screen"
      x-data="reporteTramitesApp()"
      x-init="init()">

<div class="p-3 sm:p-5 w-full max-w-[1600px] mx-auto space-y-4">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Reporte de <span class="text-slate-600">Trámites</span></h1>
            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400 mt-1">Consulta OCI / expedientes externos</p>
        </div>
        <span class="text-[10px] font-black bg-slate-800 text-white px-3 py-1.5 rounded-full" x-text="pagination.total + ' registros'"></span>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-slate-800 text-white px-5 py-3">
            <h2 class="text-sm font-bold uppercase tracking-wide">Búsqueda</h2>
        </div>

        <div class="px-4 py-4 bg-gray-50/70 border-b border-gray-100 space-y-3">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">
                <div class="lg:col-span-5">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Área(s)</label>
                    <select x-model="filtros.areas" multiple
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none min-h-[88px]">
                        @foreach ($areas as $a)
                            <option value="{{ $a->cdgo_area }}">{{ $a->de_area }}</option>
                        @endforeach
                    </select>
                    <p class="text-[9px] text-gray-400 mt-1 font-semibold">Ctrl/Cmd + clic para varias áreas</p>
                </div>
                <div class="lg:col-span-4">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Trámite</label>
                    <select x-model="filtros.tramite"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                        <option value="0">-- Todos los trámites --</option>
                        @foreach ($tramites as $t)
                            <option value="{{ $t->cdgo_docmnto_trmte }}">{{ $t->de_docmnto_trmte }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-3">
                    <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer select-none mt-6">
                        <input type="checkbox" x-model="filtros.silencio" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                        Silencio administrativo
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Estado</label>
                    <div class="flex items-center gap-2">
                        <select x-model="filtros.estado"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                            <option value="0">-- Seleccione estado --</option>
                            <option value="1">Ingresado</option>
                            <option value="2">Recepcionado</option>
                            <option value="3">Finalizado</option>
                            <option value="29">Anulado</option>
                        </select>
                        <label class="inline-flex items-center gap-1 text-[10px] font-black text-red-700 whitespace-nowrap" title="Negar estado">
                            <input type="checkbox" x-model="filtros.not_estado" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500"> !
                        </label>
                    </div>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Tipo expediente</label>
                    <div class="flex flex-wrap gap-3 text-xs font-bold text-slate-700 pt-1">
                        <label class="inline-flex items-center gap-1.5"><input type="radio" value="1" x-model="filtros.tipo_expe" class="text-slate-700 focus:ring-slate-500"> E</label>
                        <label class="inline-flex items-center gap-1.5"><input type="radio" value="2" x-model="filtros.tipo_expe" class="text-slate-700 focus:ring-slate-500"> DS</label>
                        <label class="inline-flex items-center gap-1.5"><input type="radio" value="3" x-model="filtros.tipo_expe" class="text-slate-700 focus:ring-slate-500"> O</label>
                        <label class="inline-flex items-center gap-1.5"><input type="radio" value="4" x-model="filtros.tipo_expe" class="text-slate-700 focus:ring-slate-500"> R</label>
                        <label class="inline-flex items-center gap-1.5"><input type="radio" value="5" x-model="filtros.tipo_expe" class="text-slate-700 focus:ring-slate-500"> Todos</label>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Desde</label>
                    <input type="date" x-model="filtros.desde" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Hasta</label>
                    <input type="date" x-model="filtros.hasta" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Buscar por</label>
                    <select x-model="filtros.criterio" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                        <option value="detalle">Detalle</option>
                        <option value="expediente">Expediente</option>
                        <option value="documento">Nº de identificación</option>
                        <option value="nombre">Nombre / Razón social</option>
                        <option value="usuario">Usuario de plataforma</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Criterio</label>
                    <input type="text" x-model="filtros.descripcion" @keydown.enter.prevent="consultar(1)"
                           placeholder="Texto a buscar..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Nº registros</label>
                    <input type="number" min="1" max="200" x-model="filtros.per_page"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                </div>
                <div class="md:col-span-3">
                    <button type="button" @click="consultar(1)"
                            class="w-full py-2.5 bg-slate-600 hover:bg-slate-700 text-white text-[11px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-all">
                        <i class="fas fa-search mr-1"></i> Consultar
                    </button>
                </div>
            </div>
        </div>

        <div class="relative overflow-auto custom-scroll max-h-[520px]">
            <div x-show="loading" x-cloak class="absolute inset-0 bg-white/70 z-10 flex items-center justify-center">
                <div class="flex flex-col items-center gap-2">
                    <div class="w-8 h-8 border-4 border-slate-600 border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-700">Consultando...</span>
                </div>
            </div>

            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-slate-900 text-slate-100 sticky top-0 z-[5]">
                    <tr class="text-[9px] font-black uppercase tracking-widest">
                        <th class="px-3 py-3 text-center w-10">N°</th>
                        <th class="px-3 py-3">Expediente</th>
                        <th class="px-3 py-3 text-center" title="E=Expediente | S=Doc.Simple">T</th>
                        <th class="px-3 py-3 text-center">Folio</th>
                        <th class="px-3 py-3" title="Área que ingresó">Area.Ing</th>
                        <th class="px-3 py-3">Fec.Ing</th>
                        <th class="px-3 py-3">Fec.Act</th>
                        <th class="px-3 py-3">Nombre / Razón social</th>
                        <th class="px-3 py-3">Trámite</th>
                        <th class="px-3 py-3">Area.Act</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="px-3 py-3">Detalle</th>
                        <th class="px-3 py-3 text-center">V</th>
                        <th class="px-3 py-3 text-center">U</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in items" :key="row.cdgo_dto_trmte + '-' + row.n">
                        <tr class="border-b border-gray-100 hover:bg-slate-50 text-xs">
                            <td class="px-3 py-2.5 text-center font-bold text-slate-500" x-text="row.n"></td>
                            <td class="px-3 py-2.5 font-bold text-slate-800 text-right" :title="row.cdgo_dto_trmte" x-text="row.nu_expe_todo"></td>
                            <td class="px-3 py-2.5 text-center font-black" x-text="row.tpo"></td>
                            <td class="px-3 py-2.5 text-center" x-text="row.nu_foli_trmte"></td>
                            <td class="px-3 py-2.5 font-semibold" :title="row.de_area1" x-text="row.dc_area1"></td>
                            <td class="px-3 py-2.5" :title="row.fe_ingr_full" x-text="row.fe_ingr"></td>
                            <td class="px-3 py-2.5" :title="row.fe_act_full" x-text="row.fe_act"></td>
                            <td class="px-3 py-2.5 font-semibold max-w-[160px] truncate" x-text="row.no_crto"></td>
                            <td class="px-3 py-2.5 max-w-[180px] truncate" :title="row.de_docmnto_trmte" x-text="(row.de_docmnto_trmte || '').substring(0, 28) + ((row.de_docmnto_trmte || '').length > 28 ? '…' : '')"></td>
                            <td class="px-3 py-2.5 font-semibold" :title="row.de_area" x-text="row.dc_area"></td>
                            <td class="px-3 py-2.5">
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-700" x-text="row.de_estdo_trmte"></span>
                            </td>
                            <td class="px-3 py-2.5 max-w-[220px] truncate" :title="row.de_obse_trmte" x-text="row.de_obse_trmte"></td>
                            <td class="px-3 py-2.5 text-center">
                                <button type="button" @click="verExpediente(row)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg border border-gray-200 bg-gray-50 hover:bg-slate-200 text-slate-600" title="Ver expediente">
                                    <i class="fas fa-eye text-[10px]"></i>
                                </button>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="cursor-help inline-flex w-7 h-7 items-center justify-center rounded-lg bg-slate-100 text-slate-600" :title="row.usuario_tip">
                                    <i class="fas fa-user text-[10px]"></i>
                                </span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div x-show="!loading && items.length === 0" class="py-16 text-center text-gray-400">
                <i class="fas fa-inbox text-3xl mb-3 text-gray-200"></i>
                <p class="text-xs font-bold uppercase tracking-widest">No se encontraron resultados</p>
            </div>
        </div>

        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 flex flex-wrap items-center justify-between gap-3 text-[11px] text-gray-500">
            <span class="font-bold text-slate-600">Total: <span class="num" x-text="pagination.total"></span></span>
            <div class="flex items-center gap-2 font-bold">
                <button type="button" @click="consultar(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                        class="px-2.5 py-1 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40">Anterior</button>
                <span>Página <span class="text-slate-800 px-1" x-text="pagination.current_page"></span> / <span x-text="pagination.last_page"></span></span>
                <button type="button" @click="consultar(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
                        class="px-2.5 py-1 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40">Siguiente</button>
            </div>
        </div>
    </div>
</div>

<script>
function reporteTramitesApp() {
    return {
        loading: false,
        items: [],
        pagination: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        filtros: {
            areas: [],
            tramite: '0',
            silencio: false,
            estado: '0',
            not_estado: false,
            tipo_expe: '1',
            criterio: 'detalle',
            descripcion: '',
            desde: '',
            hasta: '',
            per_page: 15,
        },
        urls: {
            buscar: @json($urlBuscar),
            buscaExpedi: @json($urlBuscaExpedi),
        },
        csrf: document.querySelector('meta[name="csrf-token"]').content,

        init() {
            this.consultar(1);
        },

        async consultar(page = 1) {
            if (page < 1) return;
            this.loading = true;
            try {
                const body = {
                    areas: (this.filtros.areas || []).map(Number),
                    tramite: Number(this.filtros.tramite || 0),
                    silencio: !!this.filtros.silencio,
                    estado: Number(this.filtros.estado || 0),
                    not_estado: !!this.filtros.not_estado,
                    tipo_expe: Number(this.filtros.tipo_expe || 5),
                    criterio: this.filtros.criterio,
                    descripcion: this.filtros.descripcion,
                    desde: this.filtros.desde || null,
                    hasta: this.filtros.hasta || null,
                    page: page,
                    per_page: Number(this.filtros.per_page || 15),
                };

                const res = await fetch(this.urls.buscar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify(body),
                });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Error al consultar');
                this.items = json.data || [];
                this.pagination = json.pagination || this.pagination;
            } catch (e) {
                console.error(e);
                this.items = [];
                alert(e.message || 'No se pudo consultar el reporte');
            } finally {
                this.loading = false;
            }
        },

        verExpediente(row) {
            const q = new URLSearchParams({
                cdgo_tram: row.cdgo_dto_trmte,
                anex: row.anexo,
                mostrar: btoa('0'),
            });
            window.open(this.urls.buscaExpedi + '?' + q.toString(), 'Reporte_OCI', 'width=470,height=560,scrollbars=yes');
        },
    };
}
</script>
</body>
</html>
