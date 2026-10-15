<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Ingresar Expedientes' }} — SIGTRAM</title>
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
        .nav-active { background-color: #1e293b; color: #fff; }
        .nav-idle { color: #4b5563; }
        .nav-idle:hover { background-color: #f8fafc; }
        .field-error { border-color: #f87171 !important; background-color: #fef2f2 !important; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.index'])
</head>
<body data-sigtram-screen="mod_gm.mod_tram_exte.index" class="sigtram-module bg-[#f8fafc] text-gray-800 min-h-screen" x-data="ingresoExpediente()" x-init="init()">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.index'])

    <div x-show="toast.show" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]">
        <div class="backdrop-blur-md text-white px-6 py-2.5 rounded-full shadow-2xl flex items-center gap-3"
             :class="toast.type === 'error' ? 'bg-red-700/90' : 'bg-gray-900/90'">
            <i class="text-xs" :class="toast.type === 'error' ? 'fas fa-exclamation-circle text-red-200' : 'fas fa-check-circle text-emerald-400'"></i>
            <p class="text-[10px] font-black uppercase tracking-widest" x-text="toast.message"></p>
        </div>
    </div>

    <div class="p-3 sm:p-5 w-full max-w-[1600px] mx-auto">
        <div class="grid grid-cols-1 xl:grid-cols-[380px_1fr] gap-4 min-h-[85vh]">

            {{-- Lista lateral (equivalente a lista_expedi.php) --}}
            <aside class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden xl:sticky xl:top-4 self-start flex flex-col max-h-[92vh]">
                <div class="bg-slate-800 text-white px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Trámite Externo</p>
                    <h1 class="text-sm font-bold mt-0.5">Expedientes ingresados</h1>
                </div>

                <div class="p-3 border-b border-gray-100 bg-gray-50/70 space-y-3">
                    <div class="inline-flex bg-gray-200/60 p-1 rounded-xl gap-1 w-full">
                        <button type="button" @click="lista.modo = 'generico'"
                                class="flex-1 py-1.5 text-[9px] font-black uppercase tracking-wider rounded-lg"
                                :class="lista.modo === 'generico' ? 'bg-slate-800 text-white' : 'text-gray-500'">
                            Buscar genéricos
                        </button>
                        <button type="button" @click="lista.modo = 'fechas'"
                                class="flex-1 py-1.5 text-[9px] font-black uppercase tracking-wider rounded-lg"
                                :class="lista.modo === 'fechas' ? 'bg-slate-800 text-white' : 'text-gray-500'">
                            Buscar por fechas
                        </button>
                    </div>

                    <div x-show="lista.modo === 'generico'" class="space-y-2">
                        <input type="text" x-model="lista.criterio" @keydown.enter.prevent="lista.pagina=1; fetchLista()"
                               placeholder="Palabra..."
                               class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                        <select x-model="lista.campo" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                            <option value="p_dto_trmtes.nu_expe_todo">Nº de Expediente</option>
                            <option value="p_usrios_1.nu_docu">Nº de Identificación</option>
                            <option value="p_usrios_1.no_crto">Nombre o Razón Social</option>
                            <option value="a_areas.de_area">Gerencia</option>
                            <option value="a_estdos_trmtes.dc_estdo_trmte">Estado</option>
                            <option value="p_lgin.de_lgin">Usuario de Plataforma</option>
                            <option value="p_dto_trmtes.de_obse_todo">Observaciones</option>
                        </select>
                        <input type="date" x-model="lista.fecha" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>

                    <div x-show="lista.modo === 'fechas'" x-cloak class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Desde</label>
                            <input type="date" x-model="lista.desde" class="w-full bg-white border border-gray-300 rounded-lg px-2 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Hasta</label>
                            <input type="date" x-model="lista.hasta" class="w-full bg-white border border-gray-300 rounded-lg px-2 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                        </div>
                    </div>

                    <button type="button" @click="lista.pagina=1; fetchLista()"
                            class="w-full py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                </div>

                <div class="flex-1 overflow-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-200 bg-slate-50 sticky top-0">
                                <th class="px-2 py-2">N° Trám</th>
                                <th class="px-1 py-2 text-center">Sil</th>
                                <th class="px-1 py-2 text-center">D/M</th>
                                <th class="px-2 py-2">Oficina</th>
                                <th class="px-1 py-2 text-center">Fo</th>
                                <th class="px-1 py-2">Est.</th>
                                <th class="px-1 py-2 text-center">V</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in lista.items" :key="item.cdgo_dto_trmte">
                                <tr class="border-b border-gray-100 hover:bg-slate-50 cursor-pointer text-[11px]"
                                    @click="abrirHojaRuta(item.cdgo_dto_trmte)">
                                    <td class="px-2 py-2 font-bold text-slate-800 whitespace-nowrap" x-text="item.nu_expe_fmt || item.nu_expe_todo" :title="item.solicitante"></td>
                                    <td class="px-1 py-2 text-center" x-text="item.silencio"></td>
                                    <td class="px-1 py-2 text-center text-gray-500" x-text="item.fecha_dm"></td>
                                    <td class="px-2 py-2 text-gray-600 truncate max-w-[90px]" :title="item.de_area" x-text="item.dc_area"></td>
                                    <td class="px-1 py-2 text-center" x-text="item.nu_foli_trmte"></td>
                                    <td class="px-1 py-2 text-[10px] font-bold text-slate-600" x-text="(item.dc_estdo_trmte || '').substring(0,5)" :title="item.dc_estdo_trmte"></td>
                                    <td class="px-1 py-2 text-center" @click.stop>
                                        <button type="button" @click="abrirBusca(item)" class="text-slate-600 hover:text-slate-900" title="Ver expediente">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="!lista.loading && lista.items.length === 0">
                                <td colspan="7" class="px-3 py-8 text-center text-[11px] font-bold text-gray-400">No se encontraron resultados</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-100 px-3 py-2 flex items-center justify-between text-[10px] font-bold text-gray-500">
                    <span>Total: <span x-text="lista.total"></span></span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="lista.pagina--; fetchLista()" :disabled="lista.pagina <= 1" class="disabled:opacity-30"><i class="fas fa-chevron-left"></i></button>
                        <span x-text="lista.pagina + ' / ' + lista.paginas"></span>
                        <button type="button" @click="lista.pagina++; fetchLista()" :disabled="lista.pagina >= lista.paginas" class="disabled:opacity-30"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </aside>

            {{-- Formulario --}}
            <section class="min-w-0">
                <form @submit.prevent="submitForm" enctype="multipart/form-data" class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between gap-3">
                        <h2 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Ingresar expedientes' }}</h2>
                        <span class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full">{{ $fechaServidor }}</span>
                    </div>

                    <div class="p-4 sm:p-6 space-y-5">

                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer border rounded-2xl p-4 text-center transition-all"
                                   :class="esExterno() ? 'border-slate-800 bg-slate-50 ring-2 ring-slate-800' : 'border-gray-200 hover:bg-gray-50'">
                                <input type="radio" class="sr-only" :value="tipoExterno" x-model="form.cdgo_tpo_trmte" @change="onTipoIngresoChange()">
                                <i class="fas fa-file-import text-2xl mb-2" :class="esExterno() ? 'text-slate-800' : 'text-gray-400'"></i>
                                <p class="text-[11px] font-black uppercase tracking-widest" x-text="etiquetaExterno"></p>
                                <p class="text-[9px] text-gray-400 font-bold mt-1">Trámites externos</p>
                            </label>
                            <label class="cursor-pointer border rounded-2xl p-4 text-center transition-all"
                                   :class="!esExterno() ? 'border-slate-800 bg-slate-50 ring-2 ring-slate-800' : 'border-gray-200 hover:bg-gray-50'">
                                <input type="radio" class="sr-only" :value="tipoSimple" x-model="form.cdgo_tpo_trmte" @change="onTipoIngresoChange()">
                                <i class="fas fa-file-alt text-2xl mb-2" :class="!esExterno() ? 'text-slate-800' : 'text-gray-400'"></i>
                                <p class="text-[11px] font-black uppercase tracking-widest" x-text="etiquetaSimple"></p>
                                <p class="text-[9px] text-gray-400 font-bold mt-1">Documentos simples</p>
                            </label>
                        </div>

                        <div class="inline-flex bg-gray-200/60 p-1 rounded-xl gap-1 w-full sm:w-auto">
                            <button type="button" @click="form.tipo_persona = 'natural'"
                                    class="py-1.5 px-4 text-[10px] font-black uppercase tracking-wider rounded-lg"
                                    :class="form.tipo_persona === 'natural' ? 'bg-slate-800 text-white' : 'text-gray-500'">
                                Persona Natural
                            </button>
                            <button type="button" @click="form.tipo_persona = 'juridica'"
                                    class="py-1.5 px-4 text-[10px] font-black uppercase tracking-wider rounded-lg"
                                    :class="form.tipo_persona === 'juridica' ? 'bg-slate-800 text-white' : 'text-gray-500'">
                                Persona Jurídica
                            </button>
                        </div>

                        {{-- Natural --}}
                        <div x-show="form.tipo_persona === 'natural'" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Tipo Doc.</label>
                                <select x-model="form.tipo_doc" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                                    @foreach($tiposDocumento as $doc)
                                        <option value="{{ $doc->cdgo_dcmntos_iden }}">{{ $doc->dc_dcmntos_iden }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Número</label>
                                <div class="relative">
                                    <input type="text" x-model="form.num_doc"
                                           @input="form.num_doc = form.num_doc.replace(/[^0-9]/g, '')"
                                           @blur="buscarPersona('natural')"
                                           @keydown.enter.prevent="buscarPersona('natural')"
                                           :maxlength="maxDoc()"
                                           :disabled="esSinDocumento()"
                                           class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 pr-8 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none disabled:bg-gray-100"
                                           :class="errors.num_doc ? 'field-error' : ''">
                                    <button type="button" @click="buscarPersona('natural')" class="absolute right-2 top-2 text-slate-500" :disabled="esSinDocumento()">
                                        <i class="fas" :class="loadingBusqueda ? 'fa-spinner fa-spin' : 'fa-search'"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Nombres</label>
                                <input type="text" x-model="form.nombres" maxlength="150"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none uppercase"
                                       :class="errors.nombres ? 'field-error' : ''">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Apellido paterno</label>
                                <input type="text" x-model="form.ape_paterno" maxlength="100"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none uppercase"
                                       :class="errors.ape_paterno ? 'field-error' : ''">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Apellido materno</label>
                                <input type="text" x-model="form.ape_materno" maxlength="100"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none uppercase"
                                       :class="errors.ape_materno ? 'field-error' : ''">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Teléfono</label>
                                <input type="text" x-model="form.telefono" maxlength="9"
                                       @input="form.telefono = form.telefono.replace(/[^0-9]/g, '')"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Correo</label>
                                <input type="email" x-model="form.correo" maxlength="254"
                                       @input="form.correo = form.correo.toLowerCase().replace(/\s/g,'')"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none lowercase">
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Domicilio fiscal</label>
                                <input type="text" x-model="form.direccion" maxlength="250"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none uppercase"
                                       :class="errors.direccion ? 'field-error' : ''">
                            </div>
                        </div>

                        {{-- Jurídica --}}
                        <div x-show="form.tipo_persona === 'juridica'" x-cloak class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div class="md:col-span-3 relative" @click.away="sugerenciasJuri = []">
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Razón social</label>
                                <div class="relative">
                                    <input type="text" x-model="form.razon_social" maxlength="150"
                                           @input.debounce.300ms="sugerirJuridicos()"
                                           @blur="buscarJuridico()"
                                           class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 pr-8 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none uppercase"
                                           :class="errors.razon_social ? 'field-error' : ''">
                                    <button type="button" @click="buscarJuridico()" class="absolute right-2 top-2 text-slate-500">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <div x-show="sugerenciasJuri.length" x-cloak class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-xl max-h-48 overflow-auto custom-scrollbar">
                                    <template x-for="s in sugerenciasJuri" :key="s.cdgo_usrios">
                                        <button type="button" @click="form.razon_social = s.rz_soci; sugerenciasJuri=[]; buscarJuridico();"
                                                class="w-full text-left px-3 py-2 text-xs font-bold hover:bg-slate-50" x-text="s.rz_soci"></button>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Nº RUC</label>
                                <input type="text" x-model="form.ruc" maxlength="11"
                                       @input="form.ruc = form.ruc.replace(/[^0-9]/g, '')"
                                       @blur="if(form.ruc.length===11) buscarPersona('juridica')"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none"
                                       :class="errors.ruc ? 'field-error' : ''">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Teléfono</label>
                                <input type="text" x-model="form.tel_jur" maxlength="9"
                                       @input="form.tel_jur = form.tel_jur.replace(/[^0-9]/g, '')"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Correo</label>
                                <input type="email" x-model="form.cor_jur" maxlength="254"
                                       @input="form.cor_jur = form.cor_jur.toLowerCase().replace(/\s/g,'')"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none lowercase">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Domicilio fiscal</label>
                                <input type="text" x-model="form.dir_jur" maxlength="255"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none uppercase"
                                       :class="errors.dir_jur ? 'field-error' : ''">
                            </div>
                        </div>

                        {{-- Destino --}}
                        <div class="border border-gray-200 rounded-xl overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-700">Destino del expediente</p>
                                <div class="inline-flex bg-gray-200/60 p-1 rounded-xl gap-1">
                                    <button type="button" @click="form.destino_modo = 'areas'"
                                            class="py-1.5 px-3 text-[9px] font-black uppercase tracking-wider rounded-lg"
                                            :class="form.destino_modo === 'areas' ? 'bg-slate-800 text-white' : 'text-gray-500'">Áreas</button>
                                    <button type="button" @click="form.destino_modo = 'servicios'"
                                            class="py-1.5 px-3 text-[9px] font-black uppercase tracking-wider rounded-lg"
                                            :class="form.destino_modo === 'servicios' ? 'bg-slate-800 text-white' : 'text-gray-500'">Servicios</button>
                                    <button type="button" @click="form.destino_modo = 'seleccion'"
                                            class="py-1.5 px-3 text-[9px] font-black uppercase tracking-wider rounded-lg"
                                            :class="form.destino_modo === 'seleccion' ? 'bg-slate-800 text-white' : 'text-gray-500'">Selección</button>
                                </div>
                            </div>
                            <div class="p-4">
                                <div x-show="form.destino_modo === 'areas'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Gerencia</label>
                                        <div class="flex gap-2">
                                            <input type="text" x-model="codigoGerencia" @blur="seleccionarGerenciaPorCodigo()" @keydown.enter.prevent="seleccionarGerenciaPorCodigo()"
                                                   class="w-14 bg-white border border-gray-300 rounded-lg px-2 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-slate-500 outline-none" placeholder="0">
                                            <select x-model="form.gerencia" @change="codigoGerencia = form.gerencia; cargarSubgerencias()"
                                                    class="flex-1 bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                                                <option value="">-- Seleccione --</option>
                                                @foreach($gerencias as $g)
                                                    <option value="{{ $g->cdgo_area }}">{{ $g->de_area }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Sub Gerencia</label>
                                        <select x-model="form.subgerencia" :disabled="!form.gerencia || loadingSubgerencias"
                                                class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none disabled:bg-gray-100">
                                            <option value="">-- Seleccione --</option>
                                            <template x-for="sub in listaSubgerencias" :key="sub.id">
                                                <option :value="sub.id" x-text="sub.nombre"></option>
                                            </template>
                                        </select>
                                    </div>
                                </div>

                                <div x-show="form.destino_modo === 'servicios'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Servicio</label>
                                        <select x-model="form.servicio" @change="cargarTramites()"
                                                class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                                            <option value="">-- Seleccione --</option>
                                            @foreach($servicios as $s)
                                                <option value="{{ $s->cdgo_srvcio_area }}">{{ $s->de_srvcio }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Tipo de trámite</label>
                                        <div class="flex gap-2 items-center">
                                            <select x-model="form.tipo_tram_tupa" @change="cargarRequisitos()" :disabled="!form.servicio || loadingTramites"
                                                    class="flex-1 bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none disabled:bg-gray-100">
                                                <option value="">-- Seleccione --</option>
                                                <template x-for="tramite in listaTramites" :key="tramite.id">
                                                    <option :value="tramite.id" x-text="tramite.nombre"></option>
                                                </template>
                                            </select>
                                            <span x-show="silencio.nu_tiem_sile" x-cloak class="text-[10px] font-black text-slate-600 whitespace-nowrap" x-text="(silencio.nu_tiem_sile || '') + ' días hábiles'"></span>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="form.destino_modo === 'seleccion'" x-cloak>
                                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Detalle el trámite</label>
                                    <div class="relative" @click.away="mostrarResultados = false">
                                        <input type="text" x-model="busquedaDirectaTerm"
                                               @input.debounce.250ms="buscarTramitesTupa()"
                                               @focus="if (busquedaDirectaTerm.length >= 3) mostrarResultados = true"
                                               class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none"
                                               placeholder="Escriba el nombre del trámite..." autocomplete="off">
                                        <div x-show="mostrarResultados" x-cloak class="absolute z-40 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-2xl max-h-64 overflow-y-auto custom-scrollbar">
                                            <template x-for="item in resultadosTupa" :key="item.id">
                                                <button type="button" @click="seleccionarTupa(item)" class="w-full text-left px-4 py-2.5 text-xs font-bold hover:bg-slate-50" x-text="item.nombre"></button>
                                            </template>
                                            <div x-show="resultadosTupa.length === 0 && !loadingBusquedaTupa && busquedaDirectaTerm.length >= 3" class="px-4 py-3 text-xs font-bold text-gray-400 text-center">Sin coincidencias</div>
                                        </div>
                                    </div>
                                    <p x-show="silencio.nu_tiem_sile" x-cloak class="mt-2 text-[10px] font-black text-slate-600" x-text="'Silencio administrativo: ' + silencio.nu_tiem_sile + ' días hábiles'"></p>
                                </div>
                            </div>
                        </div>

                        <div class="border border-dashed border-gray-300 rounded-xl p-3 min-h-[72px] bg-gray-50/50">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Requisitos</p>
                            <div x-show="listaRequisitos.length === 0 && !loadingRequisitos" class="text-xs font-bold text-gray-400">Sin requisitos</div>
                            <div x-show="loadingRequisitos" class="text-xs font-bold text-slate-500"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando...</div>
                            <div class="space-y-1 max-h-[220px] overflow-y-auto custom-scrollbar">
                                <template x-for="req in listaRequisitos" :key="req.id">
                                    <label class="flex items-start gap-2 p-1.5 rounded-lg hover:bg-white cursor-pointer"
                                           :style="'padding-left:' + (8 + (parseInt(req.in_ctgria||1)-1)*12) + 'px'">
                                        <input type="checkbox" :value="req.id" x-model="form.requisitos_marcados"
                                               x-show="String(req.in_ctgria) !== '4' && !(parseFloat(req.prcio_trmte||0) > 0 && String(req.in_ctgria) === '1')"
                                               class="mt-0.5 rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                                        <span class="text-xs font-semibold text-gray-700">
                                            <span x-text="req.descripcion"></span>
                                            <span x-show="parseFloat(req.prcio_trmte||0) > 0" class="ml-1 font-black text-slate-800" x-text="'S/. ' + Number(req.prcio_trmte).toFixed(2)"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <fieldset class="border border-gray-200 rounded-xl p-4">
                            <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-700">Adjuntar archivo digital</legend>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Adjuntar</label>
                                    <input type="file" @change="onArchivo($event)" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                           class="w-full text-xs font-semibold file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-800 file:text-white file:text-[10px] file:font-black file:uppercase">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Folio</label>
                                    <div class="flex items-center gap-2">
                                        <input type="number" min="0" x-model="form.folios" class="w-24 bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold text-center focus:ring-2 focus:ring-slate-500 outline-none">
                                        <span class="text-[10px] font-bold text-gray-400">Cantidad de folios adjuntados</span>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <label class="flex items-center gap-3 p-3 rounded-xl border border-orange-100 bg-orange-50/50 cursor-pointer">
                            <input type="checkbox" x-model="form.observado" class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                            <span>
                                <span class="block text-xs font-black uppercase tracking-widest text-orange-700">¿Trámite observado?</span>
                                <span class="block text-[10px] font-semibold text-orange-600/80">Al activar, el trámite se genera como documento observado y queda pendiente de ingreso.</span>
                            </span>
                        </label>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Detalles</label>
                            <div class="flex flex-col md:flex-row gap-3">
                                <textarea x-model="form.observaciones" rows="4" maxlength="1000"
                                          class="flex-1 bg-white border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold focus:ring-2 focus:ring-slate-500 outline-none uppercase"></textarea>
                                <button type="submit" :disabled="loading"
                                        class="self-center px-8 py-3 bg-slate-700 hover:bg-slate-800 disabled:opacity-50 text-white text-[11px] font-black uppercase tracking-widest rounded-xl shadow-md">
                                    <span x-show="!loading"><i class="fas fa-save mr-1"></i> Guardar</span>
                                    <span x-show="loading" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Registrando...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <script>
        function ingresoExpediente() {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const routes = {
                store: @json(route('tramites.ingresar.store', [], false)),
                buscarUsuario: @json(route('tramites.ingresar.buscar_usuario', [], false)),
                buscarJuridico: @json(route('tramites.ingresar.buscar_juridico', [], false)),
                sugerirJuridicos: @json(route('tramites.ingresar.sugerir_juridicos', [], false)),
                subgerencias: @json(route('tramites.ingresar.subgerencias', ['gerencia' => '__GID__'], false)),
                tramites: @json(route('tramites.ingresar.tramites', ['servicio' => '__SID__'], false)),
                requisitos: @json(route('tramites.ingresar.requisitos', ['tramite' => '__TID__'], false)),
                buscarTupa: @json(route('tramites.ingresar.buscar_tramites_tupa', [], false)),
                lista: @json(route('tramites.ingresar.lista', [], false)),
                contarFolios: @json(route('tramites.ingresar.contar_folios', [], false)),
                buscaExpedi: @json(route('tramites.busca_expedi', [], false)),
                hojaRuta: @json(route('mod_gm.segi_expedi.hoja_ruta', ['id' => '__HID__'], false)),
            };

            return {
                loading: false,
                loadingBusqueda: false,
                loadingSubgerencias: false,
                loadingTramites: false,
                loadingRequisitos: false,
                loadingBusquedaTupa: false,
                listaSubgerencias: [],
                listaTramites: [],
                listaRequisitos: [],
                busquedaDirectaTerm: '',
                resultadosTupa: [],
                mostrarResultados: false,
                sugerenciasJuri: [],
                codigoGerencia: '',
                archivo: null,
                silencio: {},
                errors: {},
                toast: { message: '', type: 'success', show: false },
                tiposDocumento: @json($tiposDocumento),
                tipoExterno: String(@json((int) ($tipoExterno->cdgo_tpo_trmte ?? 22))),
                tipoSimple: String(@json((int) ($tipoSimple->cdgo_tpo_trmte ?? 17))),
                tipoObservado: String(@json((int) ($tipoObservado->cdgo_tpo_trmte ?? 40))),
                areaObservado: @json((int) ($areaObservado->cdgo_area ?? 0)),
                etiquetaExterno: @json($tipoExterno->de_tpo_trmte ?? 'Externo'),
                etiquetaSimple: @json($tipoSimple->de_tpo_trmte ?? 'Simple'),
                lista: {
                    modo: 'generico',
                    criterio: '',
                    campo: 'p_dto_trmtes.nu_expe_todo',
                    fecha: '',
                    desde: '',
                    hasta: '',
                    pagina: 1,
                    paginas: 1,
                    total: 0,
                    items: [],
                    loading: false,
                },
                form: {
                    tipo_tramite: 'externo',
                    cdgo_tpo_trmte: String(@json((int) ($tipoExterno->cdgo_tpo_trmte ?? 22))),
                    cdgo_tpo_observado: String(@json((int) ($tipoObservado->cdgo_tpo_trmte ?? 40))),
                    cdgo_area_observado: @json((int) ($areaObservado->cdgo_area ?? 0)),
                    tipo_persona: 'natural',
                    tipo_doc: @json((string) (optional($tiposDocumento->first())->cdgo_dcmntos_iden ?: '1')),
                    num_doc: '',
                    nombres: '',
                    ape_paterno: '',
                    ape_materno: '',
                    telefono: '',
                    correo: '',
                    direccion: '',
                    ruc: '',
                    razon_social: '',
                    tel_jur: '',
                    cor_jur: '',
                    dir_jur: '',
                    destino_modo: 'servicios',
                    gerencia: '',
                    subgerencia: '',
                    servicio: '',
                    tipo_tram_tupa: '',
                    folios: 0,
                    observaciones: '',
                    observado: false,
                    requisitos_marcados: [],
                    exinatu: '',
                    exijuri: '',
                },

                init() {
                    this.fetchLista();
                    this.$watch('form.tipo_doc', () => {
                        this.form.num_doc = '';
                        this.errors.num_doc = null;
                    });
                    this.$watch('form.tipo_persona', () => { this.errors = {}; });
                },

                esExterno() {
                    return String(this.form.cdgo_tpo_trmte) === String(this.tipoExterno);
                },
                esSinDocumento() {
                    const t = (this.tiposDocumento || []).find(d => String(d.cdgo_dcmntos_iden) === String(this.form.tipo_doc));
                    const txt = String(t?.dc_dcmntos_iden || '').toUpperCase();
                    return String(this.form.tipo_doc) === '5' || txt.includes('SIN DOC');
                },
                maxDoc() {
                    const t = (this.tiposDocumento || []).find(d => String(d.cdgo_dcmntos_iden) === String(this.form.tipo_doc));
                    const txt = String(t?.dc_dcmntos_iden || '').toUpperCase();
                    if (txt.includes('DNI')) return 8;
                    if (txt.includes('CARNET') || txt.includes('CE') || txt.includes('EXTRANJ')) return 9;
                    return 20;
                },
                onTipoIngresoChange() {
                    this.form.tipo_tramite = this.esExterno() ? 'externo' : 'simple';
                    this.form.destino_modo = this.esExterno() ? 'servicios' : 'areas';
                },
                showToast(message, type = 'success') {
                    this.toast = { message, type, show: true };
                    setTimeout(() => { this.toast.show = false; }, 4000);
                },
                seleccionarGerenciaPorCodigo() {
                    if (!this.codigoGerencia) return;
                    this.form.gerencia = String(this.codigoGerencia);
                    this.cargarSubgerencias();
                },
                abrirHojaRuta(id) {
                    window.open(routes.hojaRuta.replace('__HID__', id), 'ventana', 'scrollbars=auto,width=800,height=600');
                },
                abrirBusca(item) {
                    const anex = (String(item.cdgo_estdo_trmte) === '3' || String(item.cdgo_estdo_trmte) === '26') ? '' : '&anex=1';
                    window.open(routes.buscaExpedi + '?cdgo_tram=' + item.cdgo_dto_trmte + anex, 'ventana', 'scrollbars=auto,width=520,height=620');
                },

                async fetchLista() {
                    this.lista.loading = true;
                    const params = new URLSearchParams({
                        modo: this.lista.modo,
                        criterio: this.lista.criterio,
                        campo: this.lista.campo,
                        fecha: this.lista.fecha,
                        desde: this.lista.desde,
                        hasta: this.lista.hasta,
                        pagina: this.lista.pagina,
                    });
                    try {
                        const res = await fetch(routes.lista + '?' + params.toString(), { headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
                        const data = await res.json();
                        this.lista.items = data.items || [];
                        this.lista.total = data.total || 0;
                        this.lista.pagina = data.pagina || 1;
                        this.lista.paginas = data.paginas || 1;
                    } catch (e) {
                        this.showToast('No se pudo cargar la lista de expedientes.', 'error');
                    } finally {
                        this.lista.loading = false;
                    }
                },

                async cargarSubgerencias() {
                    this.form.subgerencia = '';
                    this.listaSubgerencias = [];
                    if (!this.form.gerencia) return;
                    this.loadingSubgerencias = true;
                    try {
                        const res = await fetch(routes.subgerencias.replace('__GID__', this.form.gerencia));
                        this.listaSubgerencias = await res.json();
                    } finally {
                        this.loadingSubgerencias = false;
                    }
                },

                async cargarTramites() {
                    this.form.tipo_tram_tupa = '';
                    this.listaTramites = [];
                    this.listaRequisitos = [];
                    this.silencio = {};
                    if (!this.form.servicio) return;
                    this.loadingTramites = true;
                    try {
                        const res = await fetch(routes.tramites.replace('__SID__', this.form.servicio));
                        this.listaTramites = await res.json();
                    } finally {
                        this.loadingTramites = false;
                    }
                },

                async cargarRequisitos() {
                    this.listaRequisitos = [];
                    this.form.requisitos_marcados = [];
                    this.silencio = {};
                    if (!this.form.tipo_tram_tupa) return;
                    this.loadingRequisitos = true;
                    try {
                        const res = await fetch(routes.requisitos.replace('__TID__', this.form.tipo_tram_tupa));
                        const data = await res.json();
                        this.listaRequisitos = Array.isArray(data) ? data : (data.requisitos || []);
                        this.silencio = data.silencio || {};
                    } finally {
                        this.loadingRequisitos = false;
                    }
                },

                async buscarTramitesTupa() {
                    if (this.busquedaDirectaTerm.length < 3) {
                        this.resultadosTupa = [];
                        this.mostrarResultados = false;
                        return;
                    }
                    this.loadingBusquedaTupa = true;
                    this.mostrarResultados = true;
                    try {
                        const res = await fetch(routes.buscarTupa + '?term=' + encodeURIComponent(this.busquedaDirectaTerm));
                        this.resultadosTupa = await res.json();
                    } finally {
                        this.loadingBusquedaTupa = false;
                    }
                },

                seleccionarTupa(item) {
                    this.busquedaDirectaTerm = item.nombre;
                    this.form.tipo_tram_tupa = item.id;
                    this.silencio = { in_sile_admi: item.in_sile_admi, nu_tiem_sile: item.nu_tiem_sile };
                    this.mostrarResultados = false;
                    this.cargarRequisitos();
                },

                async sugerirJuridicos() {
                    if ((this.form.razon_social || '').length < 3) {
                        this.sugerenciasJuri = [];
                        return;
                    }
                    const res = await fetch(routes.sugerirJuridicos + '?term=' + encodeURIComponent(this.form.razon_social));
                    this.sugerenciasJuri = await res.json();
                },

                async buscarJuridico() {
                    if ((this.form.razon_social || '').trim().length < 3) return;
                    this.loadingBusqueda = true;
                    try {
                        const res = await fetch(routes.buscarJuridico + '?razon=' + encodeURIComponent(this.form.razon_social));
                        const result = await res.json();
                        if (result.success && result.data) {
                            this.aplicarJuridico(result.data);
                            this.showToast('Datos del remitente cargados.', 'success');
                        }
                    } finally {
                        this.loadingBusqueda = false;
                    }
                },

                aplicarJuridico(data) {
                    this.form.exijuri = data.id || '';
                    this.form.razon_social = data.razon_social || this.form.razon_social;
                    this.form.ruc = data.ruc || this.form.ruc;
                    this.form.tel_jur = data.tel_jur || '';
                    this.form.cor_jur = data.cor_jur || '';
                    this.form.dir_jur = data.dir_jur || '';
                },

                async buscarPersona(tipo) {
                    const documento = tipo === 'juridica' ? this.form.ruc : this.form.num_doc;
                    if (!documento || (tipo === 'natural' && this.esSinDocumento())) return;
                    this.loadingBusqueda = true;
                    try {
                        const res = await fetch(routes.buscarUsuario + '?tipo=' + tipo + '&documento=' + encodeURIComponent(documento));
                        const result = await res.json();
                        if (res.ok && result.success) {
                            const data = result.data;
                            if (tipo === 'juridica') {
                                this.aplicarJuridico(data);
                            } else {
                                this.form.exinatu = data.id || '';
                                this.form.nombres = data.nombres || '';
                                this.form.ape_paterno = data.ape_paterno || '';
                                this.form.ape_materno = data.ape_materno || '';
                                this.form.telefono = data.telefono || '';
                                this.form.correo = data.correo || '';
                                this.form.direccion = data.direccion || '';
                                if (data.tipo_doc) this.form.tipo_doc = String(data.tipo_doc);
                            }
                            this.errors = {};
                            this.showToast('Datos del remitente cargados correctamente.', 'success');
                        } else {
                            this.showToast(result.message || 'Persona no encontrada en la base de datos.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error al buscar el solicitante.', 'error');
                    } finally {
                        this.loadingBusqueda = false;
                    }
                },

                async onArchivo(event) {
                    this.archivo = event.target.files[0] || null;
                    if (!this.archivo) return;
                    if (this.archivo.size > 20 * 1024 * 1024) {
                        this.showToast('El archivo adjunto supera el límite de 20MB.', 'error');
                        event.target.value = '';
                        this.archivo = null;
                        return;
                    }
                    const fd = new FormData();
                    fd.append('fle_adjunto', this.archivo);
                    try {
                        const res = await fetch(routes.contarFolios, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            body: fd,
                        });
                        const data = await res.json();
                        if (data.success && data.folios > 0) {
                            this.form.folios = data.folios;
                        }
                    } catch (e) {}
                },

                validarCliente() {
                    this.errors = {};
                    const soloLetras = /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'\-]+$/;
                    const telOk = /^9\d{8}$/;
                    const dirOk = /^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\.\,\-\#\/°]+$/;

                    if (this.form.tipo_persona === 'natural') {
                        if (!this.esSinDocumento() && !this.form.num_doc) this.errors.num_doc = 'El número de documento es obligatorio.';
                        if (!this.esSinDocumento() && String(this.form.tipo_doc) === '1' && this.form.num_doc.length !== 8) {
                            this.errors.num_doc = 'El DNI debe tener exactamente 8 dígitos.';
                        }
                        if (!this.form.nombres) this.errors.nombres = 'El nombre es obligatorio.';
                        else if (!soloLetras.test(this.form.nombres)) this.errors.nombres = 'Solo se permiten letras, tildes, espacios, guiones y apóstrofes.';
                        if (!this.form.ape_paterno) this.errors.ape_paterno = 'El apellido paterno es obligatorio.';
                        else if (!soloLetras.test(this.form.ape_paterno)) this.errors.ape_paterno = 'Solo se permiten letras, tildes, espacios, guiones y apóstrofes.';
                        if (!this.form.ape_materno) this.errors.ape_materno = 'El apellido materno es obligatorio.';
                        else if (!soloLetras.test(this.form.ape_materno)) this.errors.ape_materno = 'Solo se permiten letras, tildes, espacios, guiones y apóstrofes.';
                        if (this.form.telefono && !telOk.test(this.form.telefono)) this.errors.telefono = 'Ingrese un teléfono de 9 dígitos que inicie con 9.';
                        if (!this.form.direccion) this.errors.direccion = 'La dirección es obligatoria.';
                        else if (this.form.direccion.trim().length < 5) this.errors.direccion = 'La dirección debe tener al menos 5 caracteres.';
                        else if (!dirOk.test(this.form.direccion)) this.errors.direccion = 'Solo se permiten letras, números, espacios y caracteres comunes (.,#/-°).';
                    } else {
                        if (!this.form.razon_social) this.errors.razon_social = 'La razón social es obligatoria.';
                        if (!this.form.ruc) this.errors.ruc = 'El RUC es obligatorio.';
                        else if (!/^(10|15|17|20)\d{9}$/.test(this.form.ruc)) this.errors.ruc = 'El RUC debe tener 11 dígitos y comenzar con 10, 15, 17 o 20.';
                        if (this.form.tel_jur && !telOk.test(this.form.tel_jur)) this.errors.tel_jur = 'Ingrese un teléfono de 9 dígitos que inicie con 9.';
                        if (!this.form.dir_jur) this.errors.dir_jur = 'El domicilio fiscal es obligatorio.';
                        else if (this.form.dir_jur.trim().length < 5) this.errors.dir_jur = 'El domicilio fiscal debe tener al menos 5 caracteres.';
                    }
                    if (!this.form.folios || parseInt(this.form.folios, 10) < 1) {
                        this.errors.folios = 'Debe especificar la cantidad de folios.';
                    }
                    if (this.form.destino_modo === 'areas' && !this.form.gerencia) {
                        this.showToast('Debe seleccionar una gerencia de destino.', 'error');
                        return false;
                    }
                    if (this.form.destino_modo === 'servicios' && !this.form.tipo_tram_tupa) {
                        this.showToast('Debe seleccionar el tipo de trámite.', 'error');
                        return false;
                    }
                    if (Object.keys(this.errors).length) {
                        this.showToast(Object.values(this.errors)[0], 'error');
                        return false;
                    }
                    return true;
                },

                async submitForm() {
                    this.form.tipo_tramite = this.esExterno() ? 'externo' : 'simple';
                    if (!this.validarCliente()) return;
                    this.loading = true;
                    try {
                        const fd = new FormData();
                        Object.keys(this.form).forEach((k) => {
                            if (k === 'requisitos_marcados') {
                                this.form.requisitos_marcados.forEach((id) => fd.append('requisitos_marcados[]', id));
                            } else if (k === 'observado') {
                                fd.append('observado', this.form.observado ? '1' : '0');
                            } else {
                                fd.append(k, this.form[k] ?? '');
                            }
                        });
                        if (this.archivo) fd.append('fle_adjunto', this.archivo);

                        const res = await fetch(routes.store, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            body: fd,
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.showToast(data.message || 'Expediente registrado.', 'success');
                            if (data.voucher_url) {
                                window.open(data.voucher_url, '_blank', 'scrollbars=yes,width=400,height=550');
                            }
                            setTimeout(() => window.location.reload(), 1200);
                        } else if (data.errors) {
                            Object.keys(data.errors).forEach((field) => { this.errors[field] = data.errors[field][0]; });
                            const first = Object.values(this.errors)[0] || 'Revise los campos marcados.';
                            this.showToast(first, 'error');
                        } else {
                            this.showToast(data.message || 'Error al registrar el expediente.', 'error');
                        }
                    } catch (e) {
                        this.showToast('Error de red al intentar registrar el expediente.', 'error');
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
</body>
</html>
