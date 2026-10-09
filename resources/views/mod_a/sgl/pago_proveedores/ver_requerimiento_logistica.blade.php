<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Pago a proveedores - Logística' }} — SIGTRAM</title>
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
<body class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="logisticaPago()" x-init="init()">

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
                    <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Pago a proveedores - Logística' }}</h1>
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
                            <div class="col-span-2">
                                <label class="lbl">Etapa</label>
                                <select x-model="filtros.etapa" class="inp">
                                    <option value="0">— Seleccionar —</option>
                                    <option value="9">Autorizar Presupuesto</option>
                                    <option value="11">Subir O/S</option>
                                    <option value="5">Conformidad</option>
                                </select>
                            </div>
                            <div class="col-span-2">
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
                                    <tr class="border-b border-gray-100 cursor-pointer hover:bg-slate-50" :class="item.id === form.id ? 'bg-slate-100' : ''" @click="abrir(item.id)">
                                        <td class="px-3 py-2" x-text="item.n"></td>
                                        <td class="px-3 py-2 font-semibold" x-text="item.titulo"></td>
                                        <td class="px-3 py-2" :title="item.area_nombre" x-text="item.area"></td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap" x-text="item.fecha"></td>
                                        <td class="px-3 py-2 text-center" :title="item.titulo_icono">
                                            <i class="fas fa-eye"
                                               :class="{
                                                   'text-amber-500': item.icono === 'amarillo',
                                                   'text-red-600': item.icono === 'rojo',
                                                   'text-emerald-600': item.icono === 'verde',
                                                   'text-sky-600': item.icono === 'azul',
                                                   'text-slate-400': item.icono === 'ver'
                                               }"></i>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="!loading && lista.length === 0"><td colspan="5" class="px-3 py-10 text-center text-gray-400">No se encontraron resultados.</td></tr>
                                <tr x-show="loading"><td colspan="5" class="px-3 py-10 text-center text-gray-400">Cargando…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-[11px]">
                        <div class="flex items-center gap-1">
                            <button type="button" class="px-2 py-1 rounded border border-gray-200" :disabled="pagina<=1" @click="pagina--; fetchLista()">Anterior</button>
                            <template x-for="p in paginasVisibles()" :key="'p'+p">
                                <button type="button" class="px-2 py-1 rounded border" :class="p===pagina ? 'bg-slate-800 text-white border-slate-800' : 'border-gray-200'" @click="pagina=p; fetchLista()" x-text="p"></button>
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
                                            <th class="px-2 py-1.5">N°</th>
                                            <th class="px-2 py-1.5">Cant.</th>
                                            <th class="px-2 py-1.5 text-left">Descripción</th>
                                            <th class="px-2 py-1.5">Pagos</th>
                                            <th class="px-2 py-1.5">Monto</th>
                                            <th class="px-2 py-1.5">CCNM</th>
                                            <th class="px-2 py-1.5">O/S</th>
                                            <th class="px-2 py-1.5">TDR</th>
                                            <th class="px-2 py-1.5">ANE</th>
                                            <th class="px-2 py-1.5">CON</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="it in form.items" :key="it.id">
                                            <tr class="border-b border-gray-100" :class="it.in_esta === 2 ? 'bg-red-100/70' : ''">
                                                <td class="px-2 py-1.5 text-center" x-text="it.n"></td>
                                                <td class="px-2 py-1.5 text-center" x-text="it.cantidad"></td>
                                                <td class="px-2 py-1.5" x-text="it.descripcion"></td>
                                                <td class="px-2 py-1.5 text-center" x-text="it.pagos"></td>
                                                <td class="px-2 py-1.5 text-right" x-text="it.monto ?? ''"></td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button type="button" class="underline decoration-dotted" @click="editarCampo(it, 'ccp')" x-text="it.ccp || '0000'"></button>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button type="button" class="underline decoration-dotted" @click="editarCampo(it, 'os')" x-text="it.os || '0000'"></button>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <a x-show="it.url_tdr" :href="urlArchivo(it.url_tdr)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                                                    <span x-show="!it.url_tdr">-</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <a x-show="it.url_ane" :href="urlArchivo(it.url_ane)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                                                    <span x-show="!it.url_ane">-</span>
                                                </td>
                                                <td class="px-2 py-1.5 text-center">
                                                    <button type="button" :title="it.titulo_icono" @click="abrirItem(it.id)">
                                                        <i class="fas fa-eye"
                                                           :class="{
                                                               'text-amber-500': it.icono === 'amarillo',
                                                               'text-red-600': it.icono === 'rojo',
                                                               'text-emerald-600': it.icono === 'verde',
                                                               'text-slate-500': it.icono === 'ver'
                                                           }"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="form.id && form.items.length === 0"><td colspan="10" class="px-2 py-6 text-center text-gray-400">Sin ítems.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </fieldset>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_subir_ccmn">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Subir CCMN</legend>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="flex-1 min-w-[200px]"><label class="lbl">ZIP de PDF</label><input type="file" accept=".zip,application/zip" @change="zipCcmn = $event.target.files[0]" class="inp"></div>
                            <button type="button" @click="subirZip(1)" class="btn bg-slate-700 text-white">Subir CCMN</button>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_subir_os">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Subir orden</legend>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="flex-1 min-w-[200px]"><label class="lbl">ZIP de PDF</label><input type="file" accept=".zip,application/zip" @change="zipOs = $event.target.files[0]" class="inp"></div>
                            <button type="button" @click="subirZip(2)" class="btn bg-slate-700 text-white">Subir ORDEN</button>
                        </div>
                    </fieldset>

                    <div class="text-center" x-show="form.puede_publicar">
                        <button type="button" @click="publicar()" class="btn bg-slate-800 text-white">Guardar y publicar</button>
                    </div>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_enviar_finanzas">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Detalle de envío a Finanzas</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="lbl">Documento</label>
                                <select x-model="finanzas.tipo_id" @change="cargarSemilla('finanzas')" class="inp">
                                    <option value="">-- Seleccione --</option>
                                    @foreach(($catalogos['tiposFinanzas'] ?? []) as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="lbl">N° Trámite</label><input type="text" x-model="finanzas.semilla" class="inp"></div>
                            <div class="flex items-end"><button type="button" @click="verBorrador('finanzas')" class="w-full btn bg-slate-600 text-white">Generar borrador</button></div>
                            <div class="md:col-span-2"><label class="lbl">Adjuntar PDF</label><input type="file" accept="application/pdf" @change="finanzas.adjunto = $event.target.files[0]" class="inp"></div>
                        </div>
                        <div class="flex justify-center gap-2 mt-3">
                            <button type="button" @click="abrirDevolver('finanzas')" class="btn bg-red-50 text-red-700 border border-red-200">Devolver</button>
                            <button type="button" @click="enviarFinanzas()" class="btn bg-slate-800 text-white">Enviar Finanzas</button>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_devolver_area">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Devolver área inicial</legend>
                        <div class="text-center">
                            <button type="button" @click="abrirDevolver('area')" class="btn bg-red-50 text-red-700 border border-red-200">Devolver al área</button>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_enviar_presupuesto">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Enviar a Presupuesto</legend>
                        <div class="text-center">
                            <button type="button" @click="enviarPresupuesto()" class="btn bg-slate-800 text-white">Enviar a Presupuesto</button>
                        </div>
                    </fieldset>

                    <fieldset class="border border-gray-200 rounded-xl p-3" x-show="form.puede_informe_tecnico">
                        <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-600">Generar informe técnico</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="lbl">Documento</label>
                                <select x-model="informe.tipo_id" @change="cargarSemilla('informe')" class="inp">
                                    <option value="">-- Seleccione --</option>
                                    @foreach(($catalogos['tiposInforme'] ?? []) as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="lbl">N° Trámite</label><input type="text" x-model="informe.semilla" class="inp"></div>
                            <div class="flex items-end"><button type="button" @click="verBorrador('informe')" class="w-full btn bg-slate-600 text-white">Generar borrador</button></div>
                            <div class="md:col-span-2"><label class="lbl">Adjuntar PDF</label><input type="file" accept="application/pdf" @change="informe.adjunto = $event.target.files[0]" class="inp"></div>
                        </div>
                        <div class="text-center mt-3">
                            <button type="button" @click="enviarInforme()" class="btn bg-slate-800 text-white">Enviar informe técnico</button>
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
                <h3 class="text-sm font-bold uppercase tracking-wide">Datos del requerimiento</h3>
                <button type="button" @click="itemModal.show=false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <div class="flex-1 overflow-auto custom-scrollbar p-4 space-y-3 text-[11px]" x-show="itemModal.data">
                <p class="font-semibold" x-text="'Servicio : ' + (itemModal.data?.servicio || '')"></p>
                <p>N° Siga : <span x-text="itemModal.data?.siga"></span>
                    <a x-show="itemModal.data?.url_siga" :href="urlArchivo(itemModal.data?.url_siga)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                    <span class="ml-4">TDR : <a x-show="itemModal.data?.url_tdr || itemModal.data?.url_pgo" :href="urlArchivo(itemModal.data?.url_tdr || itemModal.data?.url_pgo)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a></span>
                </p>

                <template x-if="itemModal.data?.modo === 'habilitacion'">
                    <div class="space-y-2">
                        <p>N° RUC : <span x-text="itemModal.data.ruc"></span> · N° CCI : <span x-text="itemModal.data.cci"></span></p>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="lbl">Monto</label><input type="number" step="0.01" class="inp" x-model="itemModal.data.monto"></div>
                            <div><label class="lbl">Días pago</label><p class="inp bg-slate-50" x-text="itemModal.data.dias_pago"></p></div>
                            <div><label class="lbl">N° CCMN</label><input type="text" class="inp" x-model="itemModal.data.ccp"></div>
                            <div><label class="lbl">N° Esp. gasto</label><input type="text" class="inp" x-model="itemModal.data.esp_gasto"></div>
                        </div>
                        <div>
                            <label class="lbl">CCMN (PDF)</label>
                            <input type="file" accept="application/pdf" @change="itemModal.ccpFile = $event.target.files[0]" class="inp">
                            <a x-show="itemModal.data?.url_ccp" :href="urlArchivo(itemModal.data.url_ccp)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i> Ver CCMN</a>
                        </div>
                        <div class="text-center"><button type="button" @click="guardarHabilitacion()" class="btn bg-slate-700 text-white">Almacenar datos</button></div>
                    </div>
                </template>

                <template x-if="itemModal.data?.modo === 'os'">
                    <div class="space-y-2">
                        <p>N° CCMN : <span x-text="itemModal.data.ccp"></span>
                            <a x-show="itemModal.data?.url_ccp" :href="urlArchivo(itemModal.data.url_ccp)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="lbl">Fecha O/S</label><input type="date" class="inp" x-model="itemModal.data.fecha_os"></div>
                            <div><label class="lbl">N° O/S</label><input type="text" class="inp" x-model="itemModal.data.os"></div>
                            <div class="col-span-2"><label class="lbl">Archivo O/S</label><input type="file" accept="application/pdf" @change="itemModal.osFile = $event.target.files[0]" class="inp"></div>
                            <div><label class="lbl">N° Rubro</label><input type="text" class="inp" x-model="itemModal.data.rubro"></div>
                            <div><label class="lbl">N° SIAF</label><input type="text" class="inp" x-model="itemModal.data.siaf"></div>
                            <div class="col-span-2">
                                <label class="lbl">Nombre</label>
                                <div class="flex gap-2">
                                    <input type="text" class="inp" list="listaProv" x-model="itemModal.data.nombre" @blur="buscarProveedor()">
                                    <button type="button" x-show="!itemModal.data.usuario_id" @click="proveedorModal.show=true" class="btn bg-slate-100 border border-gray-200" title="Nuevo proveedor"><i class="fas fa-user-plus"></i></button>
                                    <span x-show="itemModal.data.usuario_id" class="text-emerald-600 self-center"><i class="fas fa-check"></i></span>
                                </div>
                            </div>
                            <div><label class="lbl">N° RUC</label><input type="text" maxlength="11" class="inp" x-model="itemModal.data.ruc"></div>
                            <div><label class="lbl">N° CCI</label><input type="text" maxlength="20" class="inp" x-model="itemModal.data.cci"></div>
                            <div><label class="lbl">Monto</label><input type="number" step="0.01" class="inp" x-model="itemModal.data.monto"></div>
                        </div>
                        <div class="text-center"><button type="button" @click="guardarOs()" class="btn bg-slate-700 text-white">Generar cronograma de pagos</button></div>
                    </div>
                </template>

                <template x-if="itemModal.data?.modo === 'informe' || itemModal.data?.modo === 'ver'">
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                        <p x-show="itemModal.data?.os">N° O/S : <span x-text="itemModal.data.os"></span>
                            <a x-show="itemModal.data?.url_os" :href="urlArchivo(itemModal.data.url_os)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                        </p>
                        <p x-show="itemModal.data?.fecha_os">Fecha O/S : <span x-text="itemModal.data.fecha_os"></span></p>
                        <p>N° CCMN : <span x-text="itemModal.data?.ccp"></span>
                            <a x-show="itemModal.data?.url_ccp" :href="urlArchivo(itemModal.data.url_ccp)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                        </p>
                        <p>N° CCP SIAF : <span x-text="itemModal.data?.ccp_nta"></span></p>
                        <p>N° Rubro : <span x-text="itemModal.data?.rubro"></span></p>
                        <p>N° SIAF : <span x-text="itemModal.data?.siaf"></span></p>
                        <p>N° Meta : <span x-text="itemModal.data?.meta"></span></p>
                        <p>N° Esp. gasto : <span x-text="itemModal.data?.esp_gasto"></span></p>
                        <p class="col-span-2">Nombre : <span x-text="itemModal.data?.nombre"></span></p>
                        <p>N° RUC : <span x-text="itemModal.data?.ruc"></span></p>
                        <p>N° CCI : <span x-text="itemModal.data?.cci"></span></p>
                        <p>Monto : <span x-text="itemModal.data?.monto"></span> / <span x-text="itemModal.data?.pagos"></span> pagos</p>
                        <p>Días pago : <span x-text="itemModal.data?.dias_pago"></span></p>
                    </div>
                </template>

                <div x-show="(itemModal.data?.memos || []).length" class="overflow-auto">
                    <table class="w-full text-[11px]">
                        <thead>
                            <tr class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-slate-100">
                                <th class="px-2 py-1.5">N°</th>
                                <th class="px-2 py-1.5 text-left">Documento</th>
                                <th class="px-2 py-1.5">Monto</th>
                                <th class="px-2 py-1.5">Días</th>
                                <th class="px-2 py-1.5">Conformidad</th>
                                <th class="px-2 py-1.5">Con</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="m in (itemModal.data?.memos || [])" :key="m.id">
                                <tr class="border-b border-gray-100">
                                    <td class="px-2 py-1.5 text-center" x-text="m.n"></td>
                                    <td class="px-2 py-1.5" x-text="m.documento"></td>
                                    <td class="px-2 py-1.5 text-right">
                                        <button type="button" class="underline decoration-dotted" @click="editarMontoMemo(m)" x-text="m.monto"></button>
                                    </td>
                                    <td class="px-2 py-1.5 text-right" x-text="m.dias"></td>
                                    <td class="px-2 py-1.5 text-center" x-text="m.fecha"></td>
                                    <td class="px-2 py-1.5 text-center">
                                        <a x-show="m.url" :href="urlArchivo(m.url)" target="_blank" class="text-slate-600"><i class="fas fa-paperclip"></i></a>
                                        <span x-show="!m.url">-</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="text-center mt-3" x-show="itemModal.data?.puede_anular_migra">
                        <button type="button" @click="anularMigra()" class="btn bg-red-50 text-red-700 border border-red-200">Anular migra</button>
                    </div>
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
                <div><label class="lbl">Motivo de devolución</label><textarea x-model="devolverModal.motivo" rows="3" class="inp"></textarea></div>
                <div class="text-center"><button type="button" @click="devolver()" class="btn bg-red-600 text-white">Devolver requerimiento</button></div>
            </div>
        </div>
    </div>

    <div x-show="proveedorModal.show" x-cloak class="modal-overlay" @keydown.escape.window="proveedorModal.show=false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-lg overflow-hidden" @click.outside="proveedorModal.show=false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wide">Nuevo proveedor</h3>
                <button type="button" @click="proveedorModal.show=false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <div class="p-4 space-y-2">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="lbl">Tipo doc.</label>
                        <select x-model="proveedorModal.tipo_doc" class="inp">
                            @foreach(($catalogos['tiposDoc'] ?? []) as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="lbl">Número</label><input type="text" class="inp" x-model="proveedorModal.num_doc"></div>
                    <div><label class="lbl">Apellido paterno</label><input type="text" class="inp" x-model="proveedorModal.ap_pate"></div>
                    <div><label class="lbl">Apellido materno</label><input type="text" class="inp" x-model="proveedorModal.ap_mate"></div>
                    <div class="col-span-2"><label class="lbl">Nombres</label><input type="text" class="inp" x-model="proveedorModal.nombres"></div>
                    <div class="col-span-2"><label class="lbl">Dirección</label><input type="text" class="inp" x-model="proveedorModal.direccion"></div>
                </div>
                <div class="text-center"><button type="button" @click="guardarProveedor()" class="btn bg-slate-800 text-white">Guardar</button></div>
            </div>
        </div>
    </div>

    <datalist id="listaProv">
        @foreach(($catalogos['proveedores'] ?? []) as $prov)
            <option value="{{ $prov }}"></option>
        @endforeach
    </datalist>

    <script>
        function logisticaPago() {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const routes = @json($routes);
            const openId = {{ (int) ($openPgoId ?? 0) }};
            return {
                loading: false, busy: false, lista: [], total: 0, pagina: 1, paginas: 1,
                toast: { show: false, message: '', type: 'success' },
                filtros: { campo: 'nu_tram_todo', criterio: '', etapa: '0' },
                form: {}, zipCcmn: null, zipOs: null,
                finanzas: { tipo_id: '', semilla: '', adjunto: null },
                informe: { tipo_id: '', semilla: '', adjunto: null },
                itemModal: { show: false, data: null, ccpFile: null, osFile: null },
                devolverModal: { show: false, motivo: '', etapa: '' },
                proveedorModal: { show: false, tipo_doc: '1', num_doc: '', ap_pate: '', ap_mate: '', nombres: '', direccion: '' },

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
                        puede_enviar_finanzas: false, puede_devolver_finanzas: false, puede_devolver_area: false,
                        puede_enviar_presupuesto: false, puede_informe_tecnico: false, puede_publicar: false,
                        puede_subir_ccmn: false, puede_subir_os: false };
                    this.finanzas = { tipo_id: '', semilla: '', adjunto: null };
                    this.informe = { tipo_id: '', semilla: '', adjunto: null };
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
                        else this.showToast(data.error || 'No se pudo abrir el requerimiento.', 'error');
                    } catch (e) { this.showToast('Error al abrir el requerimiento.', 'error'); }
                },
                async abrirItem(id) {
                    const data = await this.postJson(routes.itemDetalle, { item_id: id });
                    if (!data.success) return this.showToast(data.error || 'No se pudo abrir el ítem.', 'error');
                    this.itemModal = { show: true, data: data.data, ccpFile: null, osFile: null };
                },
                async editarCampo(it, campo) {
                    const actual = campo === 'ccp' ? (it.ccp || '') : (it.os || '');
                    const valor = prompt(campo === 'ccp' ? 'N° CCMN' : 'N° O/S', actual);
                    if (valor === null) return;
                    const data = await this.postJson(routes.actualizarCampo, { item_id: it.id, campo, valor });
                    if (data.success) { this.showToast(data.message); await this.abrir(this.form.id); }
                    else this.showToast(data.error || 'No se pudo actualizar.', 'error');
                },
                async editarMontoMemo(m) {
                    const valor = prompt('Monto', m.monto);
                    if (valor === null) return;
                    const data = await this.postJson(routes.actualizarMontoMemo, { memo_id: m.id, monto: valor });
                    if (data.success) { this.showToast(data.message); await this.abrirItem(this.itemModal.data.id); }
                    else this.showToast(data.error || 'No se pudo actualizar.', 'error');
                },
                async guardarHabilitacion() {
                    const fd = new FormData();
                    fd.append('item_id', this.itemModal.data.id);
                    fd.append('monto', this.itemModal.data.monto || '');
                    fd.append('ccp', this.itemModal.data.ccp || '');
                    fd.append('esp_gasto', this.itemModal.data.esp_gasto || '');
                    if (this.itemModal.ccpFile) fd.append('ccp_file', this.itemModal.ccpFile);
                    await this.postForm(routes.guardarHabilitacion, fd, true);
                },
                async buscarProveedor() {
                    if (!this.itemModal.data?.nombre) return;
                    const data = await this.postJson(routes.buscarProveedor, { nombre: this.itemModal.data.nombre });
                    if (data.success) this.itemModal.data.usuario_id = data.data.id || 0;
                },
                async guardarOs() {
                    const fd = new FormData();
                    const d = this.itemModal.data;
                    fd.append('item_id', d.id);
                    fd.append('usuario_id', d.usuario_id || 0);
                    fd.append('nombre', d.nombre || '');
                    fd.append('os', d.os || '');
                    fd.append('fecha_os', d.fecha_os || '');
                    fd.append('ruc', d.ruc || '');
                    fd.append('cci', d.cci || '');
                    fd.append('monto', d.monto || '');
                    fd.append('siaf', d.siaf || '');
                    fd.append('rubro', d.rubro || '');
                    if (this.itemModal.osFile) fd.append('os_file', this.itemModal.osFile);
                    await this.postForm(routes.guardarOs, fd, true);
                },
                async anularMigra() {
                    if (!confirm('¿Anular el proceso de pagos de este ítem?')) return;
                    const data = await this.postJson(routes.anularMigra, { item_id: this.itemModal.data.id });
                    if (data.success) { this.showToast(data.message); this.itemModal.show = false; await this.abrir(data.data.id); }
                    else this.showToast(data.error || 'No se pudo anular.', 'error');
                },
                async guardarProveedor() {
                    const data = await this.postJson(routes.guardarProveedor, this.proveedorModal);
                    if (data.success) {
                        this.showToast(data.message);
                        this.itemModal.data.usuario_id = data.data.id;
                        this.itemModal.data.nombre = data.data.nombre;
                        this.proveedorModal.show = false;
                    } else this.showToast(data.error || 'No se pudo guardar.', 'error');
                },
                async subirZip(tipo) {
                    const file = tipo === 1 ? this.zipCcmn : this.zipOs;
                    if (!file) return this.showToast('Adjunte el archivo ZIP.', 'error');
                    const fd = new FormData();
                    fd.append('id', this.form.id);
                    fd.append('tipo', tipo);
                    fd.append('zip', file);
                    await this.postForm(routes.subirZip, fd, false);
                },
                async publicar() {
                    if (!confirm('¿Guardar y publicar el requerimiento?')) return;
                    const data = await this.postJson(routes.publicar, { id: this.form.id });
                    if (data.success) { this.showToast(data.message); await this.fetchLista(); await this.abrir(this.form.id); }
                    else this.showToast(data.error || 'No se pudo publicar.', 'error');
                },
                async cargarSemilla(tipo) {
                    const id = tipo === 'informe' ? this.informe.tipo_id : this.finanzas.tipo_id;
                    if (!id) return;
                    const data = await this.postJson(routes.semilla, { tipo_id: id });
                    if (data.success) {
                        if (tipo === 'informe') this.informe.semilla = data.data.nu_smlla_intrno;
                        else this.finanzas.semilla = data.data.nu_smlla_intrno;
                    }
                },
                async verBorrador(tipo) {
                    const box = tipo === 'informe' ? this.informe : this.finanzas;
                    if (!box.tipo_id) return this.showToast('Seleccione el documento.', 'error');
                    this.busy = true;
                    try {
                        const res = await fetch(routes.verBorrador, {
                            method: 'POST', headers: this.headersJson(),
                            body: JSON.stringify({
                                tipo_id: box.tipo_id, semilla: box.semilla,
                                asunto: tipo === 'informe' ? 'TRAMITE DE PAGO' : 'SOLICITO DEVENGAR',
                                dest_area: tipo === 'informe' ? 22 : 99,
                                plantilla: tipo === 'informe' ? 'informe_tecnico_indagacion_mercado.docx' : 'memorandum_proceso_pago_finanzas.docx',
                            }),
                        });
                        const ctype = res.headers.get('content-type') || '';
                        if (ctype.includes('application/json')) {
                            const data = await res.json();
                            return this.showToast(data.message || data.error || 'No se pudo generar.', 'error');
                        }
                        const blob = await res.blob();
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url; a.download = 'borrador.docx'; a.click();
                        URL.revokeObjectURL(url);
                    } catch (e) { this.showToast('Error al generar el borrador.', 'error'); }
                    finally { this.busy = false; }
                },
                async enviarFinanzas() {
                    if (!this.finanzas.tipo_id || !this.finanzas.semilla || !this.finanzas.adjunto) return this.showToast('Complete documento, número y PDF.', 'error');
                    if (!confirm('¿Enviar a Finanzas?')) return;
                    const fd = new FormData();
                    fd.append('id', this.form.id); fd.append('tipo_id', this.finanzas.tipo_id);
                    fd.append('semilla', this.finanzas.semilla); fd.append('adjunto', this.finanzas.adjunto);
                    await this.postForm(routes.enviarFinanzas, fd, false);
                },
                async enviarInforme() {
                    if (!this.informe.tipo_id || !this.informe.semilla || !this.informe.adjunto) return this.showToast('Complete documento, número y PDF.', 'error');
                    if (!confirm('¿Enviar el informe técnico?')) return;
                    const fd = new FormData();
                    fd.append('id', this.form.id); fd.append('tipo_id', this.informe.tipo_id);
                    fd.append('semilla', this.informe.semilla); fd.append('adjunto', this.informe.adjunto);
                    await this.postForm(routes.enviarInforme, fd, false);
                },
                async enviarPresupuesto() {
                    if (!confirm('¿Enviar a Presupuesto?')) return;
                    const data = await this.postJson(routes.enviarPresupuesto, { id: this.form.id });
                    if (data.success) { this.showToast(data.message); this.resetForm(); await this.fetchLista(); }
                    else this.showToast(data.error || 'No se pudo enviar.', 'error');
                },
                abrirDevolver(etapa) {
                    this.devolverModal = { show: true, etapa, motivo: 'SE DEVUELVE EL REQUERIMIENTO POR ' };
                },
                async devolver() {
                    if (!this.devolverModal.motivo.trim()) return this.showToast('Ingrese el motivo de devolución.', 'error');
                    if (!confirm('¿Devolver el requerimiento?')) return;
                    this.busy = true;
                    try {
                        const data = await this.postJson(routes.devolver, { id: this.form.id, motivo: this.devolverModal.motivo, etapa: this.devolverModal.etapa });
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
