<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Ingresar Expedientes' }} — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@php
    $frontendConfig = [
        'routes' => [
            'store' => route('tramites.ingresar.store', [], false),
            'buscarUsuario' => route('tramites.ingresar.buscar_usuario', [], false),
            'buscarJuridico' => route('tramites.ingresar.buscar_juridico', [], false),
            'sugerirJuridicos' => route('tramites.ingresar.sugerir_juridicos', [], false),
            'subgerencias' => route('tramites.ingresar.subgerencias', ['gerencia' => '__GID__'], false),
            'tramites' => route('tramites.ingresar.tramites', ['servicio' => '__SID__'], false),
            'requisitos' => route('tramites.ingresar.requisitos', ['tramite' => '__TID__'], false),
            'buscarTupa' => route('tramites.ingresar.buscar_tramites_tupa', [], false),
            'lista' => route('tramites.ingresar.lista', [], false),
            'contarFolios' => route('tramites.ingresar.contar_folios', [], false),
            'buscaExpedi' => route('tramites.busca_expedi', [], false),
            'hojaRuta' => route('mod_gm.segi_expedi.hoja_ruta', ['id' => '__HID__'], false),
            'bandeja' => route('tramites.bandeja', [], false),
        ],
        'tiposDocumento' => $tiposDocumento,
        'catalogoValido' => (bool) ($tipoExterno && $tipoSimple && $tipoObservado),
        'tipoExterno' => $tipoExterno ? (string) $tipoExterno->cdgo_tpo_trmte : null,
        'tipoSimple' => $tipoSimple ? (string) $tipoSimple->cdgo_tpo_trmte : null,
        'tipoObservado' => $tipoObservado ? (string) $tipoObservado->cdgo_tpo_trmte : null,
        'areaObservado' => (int) ($areaObservado->cdgo_area ?? 0),
        'etiquetaExterno' => $tipoExterno->de_tpo_trmte ?? 'Externo',
        'etiquetaSimple' => $tipoSimple->de_tpo_trmte ?? 'Simple',
        'tipoDocDefault' => (string) (optional($tiposDocumento->first())->cdgo_dcmntos_iden ?: '1'),
        'csrfToken' => csrf_token(),
    ];
@endphp

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.index', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body id="ingreso-expediente-app" data-config='@json($frontendConfig)' class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="ingresoExpediente()" x-init="init()">
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.index', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}


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
                        <div class="flex items-center gap-3">
                            <i class="fas fa-file-signature text-amber-400 text-base"></i>
                            <h2 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Ingresar expedientes' }}</h2>
                        </div>
                        <span class="text-[10px] font-black bg-white/15 px-2.5 py-1 rounded-full">{{ $fechaServidor }}</span>
                    </div>

                    {{-- Estado y acciones post-registro --}}
                    <div x-show="ultimoExpediente" x-cloak class="p-4 bg-emerald-50 border-b border-emerald-200">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-600 text-white text-xs">
                                        <i class="fas fa-check"></i>
                                    </span>
                                    <h3 class="text-sm font-black text-emerald-900 uppercase">Expediente registrado exitosamente</h3>
                                </div>
                                <p class="text-xs text-emerald-700 font-semibold mt-1">
                                    Número: <strong class="text-emerald-950 font-black text-sm" x-text="ultimoExpediente.numero"></strong> |
                                    Folios: <span x-text="ultimoExpediente.folios"></span>
                                </p>
                                <template x-if="voucherBloqueado">
                                    <p class="text-xs text-amber-800 font-bold mt-1.5 flex items-center gap-1.5">
                                        <i class="fas fa-exclamation-triangle text-amber-600"></i>
                                        <span>El comprobante no pudo abrirse automáticamente</span>
                                    </p>
                                </template>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="abrirComprobante(ultimoExpediente.voucher_url)"
                                        class="px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 shadow-sm">
                                    <i class="fas fa-print"></i>
                                    <span>Imprimir comprobante</span>
                                </button>
                                <button type="button" @click="abrirExpediente(ultimoExpediente.show_url)"
                                        class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 shadow-sm">
                                    <i class="fas fa-folder-open"></i>
                                    <span>Ver expediente</span>
                                </button>
                                <button type="button" @click="volverBandeja()"
                                        class="px-3 py-1.5 bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 shadow-sm">
                                    <i class="fas fa-inbox"></i>
                                    <span>Ir a bandeja externa</span>
                                </button>
                                <button type="button" @click="registrarOtro()"
                                        class="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-bold rounded-lg transition-colors flex items-center gap-1.5 shadow-sm">
                                    <i class="fas fa-plus"></i>
                                    <span>Registrar otro</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    @if(!$tipoExterno || !$tipoSimple || !$tipoObservado)
                        <div class="m-4 p-4 rounded-xl border border-amber-300 bg-amber-50 text-amber-900 flex items-start gap-3">
                            <i class="fas fa-exclamation-triangle text-amber-600 text-base mt-0.5"></i>
                            <div>
                                <h4 class="text-xs font-black uppercase tracking-wider text-amber-800">Catálogo de trámites incompleto</h4>
                                <p class="text-xs text-amber-700 mt-1">
                                    No se encontraron activos los tipos de trámite requeridos (E, S u O) en el catálogo general. El ingreso de expedientes se encuentra inhabilitado hasta configurar el catálogo.
                                </p>
                            </div>
                        </div>
                    @endif

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
                                    <input type="file" id="fle_adjunto" @change="onArchivo($event)" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
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
                                <button type="submit" :disabled="loading || !catalogoValido"
                                        class="self-center px-8 py-3 bg-slate-700 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-[11px] font-black uppercase tracking-widest rounded-xl shadow-md">
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
</body>
</html>

