<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Pago a proveedores' }} — SIGTRAM</title>
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
        .inp[readonly], .inp:disabled { background: #f1f5f9; color: #64748b; }
        .lbl { display: block; font-size: 9px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: #9ca3af; margin-bottom: 0.25rem; }
    </style>
</head>
<body class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="pagoProveedores()" x-init="init()">

    <div x-show="busy" x-cloak class="fixed inset-0 z-[180] bg-slate-900/40 flex items-center justify-center">
        <div class="bg-white rounded-full px-5 py-2 text-[10px] font-black uppercase tracking-widest shadow-xl">Procesando…</div>
    </div>

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
                    <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Pago a proveedores' }}</h1>
                </div>
                <button type="button" @click="nuevo()" class="text-[10px] font-black bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-full">
                    <i class="fas fa-plus mr-1"></i> Nuevo
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[36%_1fr] flex-1 min-h-0">
                <section class="border-b lg:border-b-0 lg:border-r border-gray-100 flex flex-col min-h-[420px]">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/70">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Lista de pagos</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="lbl">Buscar</label>
                                <select x-model="filtros.campo" class="inp">
                                    <option value="nu_tram_todo">Nº Trámite</option>
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
                                    <th class="px-3 py-2 text-center">Fecha</th>
                                    <th class="px-3 py-2 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in lista" :key="item.id">
                                    <tr class="border-b border-gray-100 cursor-pointer"
                                        :class="{
                                            'bg-red-100/70': item.estado === 2,
                                            'bg-slate-100': item.id === form.id,
                                            'hover:bg-slate-50': item.id !== form.id && item.estado !== 2
                                        }"
                                        @click="abrir(item.id)">
                                        <td class="px-3 py-2" x-text="item.n"></td>
                                        <td class="px-3 py-2 font-semibold" x-text="item.titulo"></td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap" x-text="item.fecha"></td>
                                        <td class="px-3 py-2 text-center">
                                            <span :title="item.titulo_icono">
                                                <i class="fas"
                                                   :class="{
                                                       'fa-eye text-emerald-600': item.icono === 'anexo',
                                                       'fa-eye text-red-600': item.icono === 'alerta',
                                                       'fa-eye text-slate-400': item.icono === 'ver' || item.icono === 'finalizado'
                                                   }"></i>
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="!loading && lista.length === 0">
                                    <td colspan="4" class="px-3 py-10 text-center text-gray-400">No se encontraron resultados.</td>
                                </tr>
                                <tr x-show="loading">
                                    <td colspan="4" class="px-3 py-10 text-center text-gray-400">Cargando…</td>
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
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">
                            Datos del requerimiento (<span x-text="form.id || 0"></span>)
                        </legend>
                        <p class="text-[11px] font-semibold text-slate-700 mb-2" x-show="form.documento" x-text="'Documento : ' + form.documento"></p>
                        <div class="mb-3">
                            <label class="lbl">Asunto</label>
                            <textarea x-model="form.asunto" rows="3" class="inp" :readonly="form.readonly" required></textarea>
                        </div>
                        <div class="flex flex-wrap items-end gap-4 mb-3">
                            <div class="w-28">
                                <label class="lbl">Cant. ítems</label>
                                <input type="number" min="1" max="200" class="inp" :value="form.nu_items" :readonly="form.readonly"
                                       @change="ajustarItems($event.target.value)">
                            </div>
                            <label class="flex items-center gap-2 text-[11px] font-semibold" title="Desactivar si el requerimiento no será para pago de locadores">
                                <input type="checkbox" x-model="form.locadores" :disabled="form.readonly" class="rounded border-gray-300">
                                Pago locadores?
                                <i class="fas fa-circle-question text-slate-400"></i>
                            </label>
                        </div>

                        <fieldset class="border border-gray-100 rounded-xl p-2">
                            <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-500">Lista del requerimiento</legend>
                            <div class="overflow-auto custom-scrollbar">
                                <table class="w-full text-[11px]">
                                    <thead>
                                        <tr class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-slate-100">
                                            <th class="px-2 py-1.5 text-center">N°</th>
                                            <th class="px-2 py-1.5 text-center">Cant.</th>
                                            <th class="px-2 py-1.5 text-left">Descripción</th>
                                            <th class="px-2 py-1.5" x-text="form.editable ? 'N° Pagos' : 'Pagos'"></th>
                                            <th class="px-2 py-1.5" x-show="!form.editable">Monto</th>
                                            <th class="px-2 py-1.5">SIGA</th>
                                            <th class="px-2 py-1.5" x-show="!form.editable">O/S</th>
                                            <th class="px-2 py-1.5">TDR</th>
                                            <th class="px-2 py-1.5" x-show="!form.editable">ANE</th>
                                            <th class="px-2 py-1.5 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(it, idx) in form.items" :key="'it'+idx+(it.id||0)">
                                            <tr class="border-b border-gray-100" :class="it.in_esta === 2 ? 'bg-red-100/70' : ''">
                                                <td class="px-2 py-1.5 text-center" x-text="idx + 1"></td>
                                                <td class="px-2 py-1.5 w-16">
                                                    <template x-if="form.editable"><input type="number" min="1" class="inp" x-model="it.cantidad"></template>
                                                    <span x-show="!form.editable" x-text="it.cantidad"></span>
                                                </td>
                                                <td class="px-2 py-1.5">
                                                    <template x-if="form.editable">
                                                        <input type="text" class="inp" list="listaDesc" x-model="it.descripcion">
                                                    </template>
                                                    <span x-show="!form.editable" x-text="it.descripcion"></span>
                                                </td>
                                                <td class="px-2 py-1.5 w-20">
                                                    <template x-if="form.editable"><input type="number" min="1" class="inp" x-model="it.pagos"></template>
                                                    <span x-show="!form.editable" x-text="it.pagos"></span>
                                                </td>
                                                <td class="px-2 py-1.5 text-right" x-show="!form.editable" x-text="it.monto ?? ''"></td>
                                                <td class="px-2 py-1.5 w-24">
                                                    <template x-if="form.editable"><input type="text" class="inp" x-model="it.siga"></template>
                                                    <span x-show="!form.editable" x-text="it.siga"></span>
                                                </td>
                                                <td class="px-2 py-1.5" x-show="!form.editable" x-text="it.os || '-'"></td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <a x-show="it.url_tdr" :href="urlArchivo(it.url_tdr)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                                                    <span x-show="!it.url_tdr">-</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center" x-show="!form.editable">
                                                    <a x-show="it.url_ane" :href="urlArchivo(it.url_ane)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                                                    <span x-show="!it.url_ane">-</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button type="button" x-show="it.id" :title="it.titulo_icono" @click="abrirItem(it.id)" class="text-slate-600 hover:text-slate-900">
                                                        <i class="fas fa-eye"
                                                           :class="{
                                                               'text-emerald-600': it.icono === 'anexo',
                                                               'text-red-600': it.icono === 'alerta'
                                                           }"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="form.items.length === 0">
                                            <td colspan="10" class="px-2 py-6 text-center text-gray-400">Ingrese la cantidad de ítems.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <datalist id="listaDesc">
                                @foreach(($catalogos['sugerencias'] ?? []) as $sug)
                                    <option value="{{ $sug }}"></option>
                                @endforeach
                            </datalist>
                            <div class="flex justify-center gap-2 mt-3">
                                <button type="button" x-show="form.puede_anular" @click="anular()" class="px-4 py-2 bg-red-50 text-red-700 border border-red-200 text-[10px] font-black uppercase tracking-widest rounded-lg">Anular</button>
                                <button type="button" x-show="!form.readonly" @click="guardar()" :disabled="saving" class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                                    <span x-text="saving ? 'Guardando…' : 'Guardar'"></span>
                                </button>
                                <button type="button" x-show="form.puede_finalizar" @click="finalizar()" class="px-4 py-2 bg-red-50 text-red-700 border border-red-200 text-[10px] font-black uppercase tracking-widest rounded-lg">Finalizar requerimiento</button>
                            </div>
                        </fieldset>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_generar_tramite">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Generar requerimiento</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="lbl">Documento</label>
                                <select x-model="tramite.tipo_id" @change="cargarSemilla('tramite')" class="inp">
                                    <option value="">-- Seleccione --</option>
                                    @foreach(($catalogos['tiposRequerimiento'] ?? []) as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">N° Trámite</label>
                                <input type="text" x-model="tramite.semilla" class="inp">
                            </div>
                            <div>
                                <label class="lbl">Adjuntar</label>
                                <input type="file" accept="application/pdf" @change="tramite.adjunto = $event.target.files[0]" class="inp">
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <button type="button" @click="enviarTramite()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">Generar y enviar</button>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_generar_memo">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Generar trámite de conformidad</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="lbl">Documento</label>
                                <select x-model="memo.tipo_id" @change="cargarSemilla('memo')" class="inp">
                                    <option value="">-- Seleccione --</option>
                                    @foreach(($catalogos['tiposMemo'] ?? []) as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lbl">N° Trámite</label>
                                <input type="text" x-model="memo.semilla" class="inp">
                            </div>
                            <div>
                                <label class="lbl">Adjuntar</label>
                                <input type="file" accept="application/pdf" @change="memo.adjunto = $event.target.files[0]" class="inp">
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <button type="button" @click="enviarMemo()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">Generar y enviar</button>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.cdgo_dtos_ntrnos > 0">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Seguimiento del trámite</legend>
                        <iframe x-show="form.cdgo_dtos_ntrnos > 0" :src="urlSeguimiento()" class="w-full h-[300px] border-0 rounded-lg bg-white"></iframe>
                    </fieldset>
                </section>
            </div>
        </div>
    </div>

    <div x-show="itemModal.show" x-cloak class="modal-overlay">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Documentación del servicio</h3>
                <button type="button" @click="itemModal.show=false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <div class="flex-1 overflow-auto custom-scrollbar p-4 space-y-3" x-show="itemModal.data">
                <p class="text-[11px] font-semibold" x-text="'Servicio : ' + (itemModal.data?.servicio || '')"></p>
                <p class="text-[11px]">
                    N° Siga : <span x-text="itemModal.data?.siga"></span>
                    <a x-show="itemModal.data?.url_siga" :href="urlArchivo(itemModal.data?.url_siga)" target="_blank" class="ml-1 text-slate-600"><i class="fas fa-paperclip"></i></a>
                    <span class="ml-4">TDR :
                        <a x-show="itemModal.data?.url_tdr" :href="urlArchivo(itemModal.data?.url_tdr)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                    </span>
                </p>

                <template x-if="itemModal.data?.modo === 'tdr'">
                    <div class="space-y-2">
                        <div>
                            <label class="lbl">SIGA (PDF firmado)</label>
                            <input type="file" accept="application/pdf" @change="itemModal.siga = $event.target.files[0]" class="inp">
                        </div>
                        <div>
                            <label class="lbl">TDR (PDF firmado)</label>
                            <input type="file" accept="application/pdf" @change="itemModal.tdr = $event.target.files[0]" class="inp">
                        </div>
                        <div class="flex items-center justify-center gap-2 pt-2">
                            <button type="button" @click="itemModal.show=false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-100 transition-colors">Cancelar</button>
                            <button type="button" @click="guardarTdr()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-colors">Guardar SIGA/TDR</button>
                        </div>
                    </div>
                </template>

                <template x-if="itemModal.data?.modo === 'anexos'">
                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="lbl">N° RUC</label>
                                <input type="text" maxlength="11" class="inp" x-model="itemModal.data.ruc">
                            </div>
                            <div>
                                <label class="lbl">N° CCI</label>
                                <input type="text" maxlength="20" class="inp" x-model="itemModal.data.cci">
                            </div>
                        </div>
                        <div>
                            <label class="lbl">Monto</label>
                            <input type="number" step="0.01" class="inp" x-model="itemModal.data.monto">
                        </div>
                        <div>
                            <label class="lbl">Anexos (un solo PDF)</label>
                            <input type="file" accept="application/pdf" @change="itemModal.anexos = $event.target.files[0]" class="inp">
                            <a x-show="itemModal.data?.url_ane" :href="urlArchivo(itemModal.data?.url_ane)" target="_blank" class="text-[11px] text-slate-600"><i class="fas fa-paperclip"></i> Ver anexos</a>
                        </div>
                        <div>
                            <label class="lbl">SERVIR</label>
                            <input type="file" accept="application/pdf" @change="itemModal.servir = $event.target.files[0]" class="inp">
                            <a x-show="itemModal.data?.url_servir" :href="urlArchivo(itemModal.data?.url_servir)" target="_blank" class="text-[11px] text-slate-600"><i class="fas fa-paperclip"></i> Ver SERVIR</a>
                        </div>
                        <div class="flex items-center justify-center gap-2 pt-2">
                            <button type="button" @click="itemModal.show=false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-100 transition-colors">Cancelar</button>
                            <button type="button" @click="guardarAnexos()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-colors">Guardar anexos</button>
                        </div>
                    </div>
                </template>

                <template x-if="itemModal.data?.nombre">
                    <div class="text-[11px] space-y-1">
                        <p>Fecha O/S : <span x-text="itemModal.data.fecha_os"></span></p>
                        <p>N° O/S : <span x-text="itemModal.data.os"></span>
                            <a x-show="itemModal.data?.url_os" :href="urlArchivo(itemModal.data?.url_os)" target="_blank" class="ml-1 text-slate-600"><i class="fas fa-paperclip"></i></a>
                        </p>
                        <p>N° Rubro : <span x-text="itemModal.data.rubro"></span></p>
                        <p>N° SIAF : <span x-text="itemModal.data.siaf"></span></p>
                        <p>Nombre : <span x-text="itemModal.data.nombre"></span></p>
                        <p>N° RUC : <span x-text="itemModal.data.ruc"></span> · N° CCI : <span x-text="itemModal.data.cci"></span></p>
                    </div>
                </template>

                <div x-show="(itemModal.data?.memos || []).length" class="overflow-auto">
                    <table class="w-full text-[11px]">
                        <thead>
                            <tr class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-slate-100">
                                <th class="px-2 py-1.5">N°</th>
                                <th class="px-2 py-1.5 text-left">Doc. de conformidad</th>
                                <th class="px-2 py-1.5">Monto</th>
                                <th class="px-2 py-1.5">Días</th>
                                <th class="px-2 py-1.5">N° RXH</th>
                                <th class="px-2 py-1.5">4ta</th>
                                <th class="px-2 py-1.5">C</th>
                                <th class="px-2 py-1.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(m, i) in (itemModal.data?.memos || [])" :key="m.id">
                                <tr class="border-b border-gray-100">
                                    <td class="px-2 py-1.5 text-center" x-text="i+1"></td>
                                    <td class="px-2 py-1.5" x-text="m.documento"></td>
                                    <td class="px-2 py-1.5 text-right" x-text="m.monto"></td>
                                    <td class="px-2 py-1.5 text-right" x-text="m.dias"></td>
                                    <template x-if="m.editable">
                                        <td class="px-2 py-1.5"><input type="text" class="inp" x-model="m.recibo"></td>
                                    </template>
                                    <template x-if="m.editable">
                                        <td class="px-2 py-1.5 text-center"><input type="checkbox" x-model="m.suspension"></td>
                                    </template>
                                    <template x-if="m.editable">
                                        <td class="px-2 py-1.5"><input type="file" accept="application/pdf" @change="m.archivo = $event.target.files[0]" class="inp"></td>
                                    </template>
                                    <td class="px-2 py-1.5 text-center" x-show="!m.editable" colspan="3" x-text="m.fecha"></td>
                                    <td class="px-2 py-1.5 text-center">
                                        <a x-show="m.url" :href="urlArchivo(m.url)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="flex justify-center gap-2 mt-3">
                        <button type="button" @click="itemModal.show=false" class="px-4 py-2 border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-100 transition-colors">Cerrar</button>
                        <button type="button" x-show="itemModal.data?.puede_guardar_conformidad" @click="bajaProveedor()" class="px-4 py-2 bg-red-50 text-red-700 border border-red-200 text-[10px] font-black uppercase tracking-widest rounded-lg">Baja proveedor</button>
                        <button type="button" x-show="itemModal.data?.puede_guardar_conformidad" @click="guardarConformidad()" class="px-4 py-2 bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm">Guardar conformidad</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function pagoProveedores() {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const routes = @json($routes);
            const openId = {{ (int) ($openPgoId ?? 0) }};

            return {
                loading: false,
                saving: false,
                busy: false,
                lista: [],
                total: 0,
                pagina: 1,
                paginas: 1,
                toast: { show: false, message: '', type: 'success' },
                filtros: { campo: 'nu_tram_todo', criterio: '', fecha: '' },
                form: {},
                tramite: { tipo_id: '', semilla: '', adjunto: null },
                memo: { tipo_id: '', semilla: '', adjunto: null },
                itemModal: { show: false, data: null, tdr: null, siga: null, anexos: null, servir: null },

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

                headersForm() {
                    return {
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
                        id: 0, asunto: '', locadores: true, estado: 1, cdgo_dtos_ntrnos: 0,
                        documento: '', editable: true, readonly: false,
                        puede_anular: false, puede_finalizar: false,
                        puede_generar_tramite: false, puede_generar_memo: false,
                        items: [], nu_items: 0,
                    };
                    this.tramite = { tipo_id: '', semilla: '', adjunto: null };
                    this.memo = { tipo_id: '', semilla: '', adjunto: null };
                },

                itemVacio() {
                    return { id: 0, cantidad: 1, descripcion: '', pagos: '', siga: '', in_esta: 1 };
                },

                ajustarItems(n) {
                    n = Math.max(1, Math.min(200, parseInt(n, 10) || 1));
                    this.form.nu_items = n;
                    while (this.form.items.length < n) this.form.items.push(this.itemVacio());
                    if (this.form.items.length > n) this.form.items = this.form.items.slice(0, n);
                },

                nuevo() {
                    this.resetForm();
                    this.ajustarItems(1);
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
                            this.form = Object.assign(this.resetKeep(), data.data || {});
                            if (this.form.editable && this.form.nu_items === 0) this.ajustarItems(1);
                        } else {
                            this.showToast(data.error || 'No se pudo abrir el requerimiento.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al abrir el requerimiento.', 'error');
                    }
                },

                resetKeep() {
                    this.tramite = { tipo_id: '', semilla: '', adjunto: null };
                    this.memo = { tipo_id: '', semilla: '', adjunto: null };
                    return this.form;
                },

                async guardar() {
                    if (!this.form.asunto) return this.showToast('Ingrese el asunto.', 'error');
                    if (!this.form.items.length) return this.showToast('Ingrese al menos un ítem.', 'error');
                    if (!confirm('¿Desea guardar el requerimiento?')) return;
                    this.saving = true;
                    try {
                        const res = await fetch(routes.guardar, {
                            method: 'POST',
                            headers: this.headersJson(),
                            body: JSON.stringify({
                                id: this.form.id,
                                asunto: this.form.asunto,
                                locadores: this.form.locadores,
                                items: this.form.items,
                            }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showToast(data.message || 'Guardado.');
                            await this.fetchLista();
                            await this.abrir(data.data.id);
                        } else {
                            this.showToast(data.error || 'No se pudo guardar.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al guardar.', 'error');
                    } finally {
                        this.saving = false;
                    }
                },

                async anular() {
                    if (!confirm('¿Está seguro de ANULAR el pre-requerimiento?')) return;
                    const data = await this.postJson(routes.anular, { id: this.form.id });
                    if (data.success) {
                        this.showToast(data.message);
                        this.resetForm();
                        this.fetchLista();
                    } else this.showToast(data.error || 'No se pudo anular.', 'error');
                },

                async finalizar() {
                    if (!confirm('¿Está seguro de FINALIZAR el requerimiento?')) return;
                    const data = await this.postJson(routes.finalizar, { id: this.form.id });
                    if (data.success) {
                        this.showToast(data.message);
                        await this.fetchLista();
                        await this.abrir(this.form.id);
                    } else this.showToast(data.error || 'No se pudo finalizar.', 'error');
                },

                async cargarSemilla(tipo) {
                    const id = tipo === 'memo' ? this.memo.tipo_id : this.tramite.tipo_id;
                    if (!id) return;
                    const data = await this.postJson(routes.semilla, { tipo_id: id });
                    if (data.success) {
                        if (tipo === 'memo') this.memo.semilla = data.data.nu_smlla_intrno;
                        else this.tramite.semilla = data.data.nu_smlla_intrno;
                    }
                },

                async enviarTramite() {
                    if (!this.tramite.tipo_id || !this.tramite.semilla || !this.tramite.adjunto) {
                        return this.showToast('Complete documento, número y adjunto PDF.', 'error');
                    }
                    if (!confirm('¿Generar y enviar el requerimiento?')) return;
                    const fd = new FormData();
                    fd.append('id', this.form.id);
                    fd.append('tipo_id', this.tramite.tipo_id);
                    fd.append('semilla', this.tramite.semilla);
                    fd.append('adjunto', this.tramite.adjunto);
                    await this.postForm(routes.generarTramite, fd);
                },

                async enviarMemo() {
                    if (!this.memo.tipo_id || !this.memo.semilla || !this.memo.adjunto) {
                        return this.showToast('Complete documento, número y adjunto PDF.', 'error');
                    }
                    if (!confirm('¿Generar y enviar el trámite de conformidad?')) return;
                    const fd = new FormData();
                    fd.append('id', this.form.id);
                    fd.append('tipo_id', this.memo.tipo_id);
                    fd.append('semilla', this.memo.semilla);
                    fd.append('adjunto', this.memo.adjunto);
                    await this.postForm(routes.generarMemo, fd);
                },

                async abrirItem(id) {
                    const data = await this.postJson(routes.itemDetalle, { item_id: id });
                    if (!data.success) return this.showToast(data.error || 'No se pudo abrir el ítem.', 'error');
                    this.itemModal = { show: true, data: data.data, tdr: null, siga: null, anexos: null, servir: null };
                },

                async guardarTdr() {
                    if (!this.itemModal.tdr || !this.itemModal.siga) return this.showToast('Adjunte TDR y SIGA.', 'error');
                    const fd = new FormData();
                    fd.append('item_id', this.itemModal.data.id);
                    fd.append('tdr', this.itemModal.tdr);
                    fd.append('siga', this.itemModal.siga);
                    await this.postForm(routes.guardarTdr, fd, true);
                },

                async guardarAnexos() {
                    const fd = new FormData();
                    fd.append('item_id', this.itemModal.data.id);
                    fd.append('ruc', this.itemModal.data.ruc || '');
                    fd.append('cci', this.itemModal.data.cci || '');
                    fd.append('monto', this.itemModal.data.monto || '');
                    if (this.itemModal.anexos) fd.append('anexos', this.itemModal.anexos);
                    if (this.itemModal.servir) fd.append('servir', this.itemModal.servir);
                    await this.postForm(routes.guardarAnexos, fd, true);
                },

                async guardarConformidad() {
                    const fd = new FormData();
                    fd.append('item_id', this.itemModal.data.id);
                    (this.itemModal.data.memos || []).forEach((m, i) => {
                        fd.append('pagos[' + i + '][id]', m.id);
                        fd.append('pagos[' + i + '][recibo]', m.recibo || '');
                        if (m.suspension) fd.append('pagos[' + i + '][suspension]', '1');
                        if (m.archivo) fd.append('pagos[' + i + '][archivo]', m.archivo);
                    });
                    await this.postForm(routes.guardarConformidad, fd, true);
                },

                async bajaProveedor() {
                    if (!confirm('¿Dar de baja a este proveedor?')) return;
                    const data = await this.postJson(routes.bajaItem, { item_id: this.itemModal.data.id });
                    if (data.success) {
                        this.showToast(data.message);
                        this.itemModal.show = false;
                        await this.fetchLista();
                        await this.abrir(data.data.id);
                    } else this.showToast(data.error || 'No se pudo dar de baja.', 'error');
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
                            const id = data.data?.id || this.form.id;
                            if (recargarItem) {
                                this.itemModal.show = false;
                            }
                            await this.fetchLista();
                            if (id) await this.abrir(id);
                        } else {
                            this.showToast(data.error || 'No se pudo completar la operación.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error de comunicación.', 'error');
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
    </script>
</body>
</html>
