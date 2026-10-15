<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingresar Documentos Internos — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@php
    $frontendConfig = [
        'cargoFirma' => $inFrmaDgtalCargo ? 1 : 0,
        'minDate' => $minDate,
        'maxDate' => $maxDate,
        'myAreaId' => (int) $areaId,
        'tiposDocumento' => $tiposDocumento,
        'allAreas' => $destinoAreas,
        'defaultDate' => $defaultDate,
        'borradorUrl' => route('mod_tram_inter.ingresar_tramite.generar_borrador'),
        'storeUrl' => url('/mod_gene/mod_tram_inter/ingresar_tramite/store'),
        'csrfToken' => csrf_token(),
    ];
@endphp

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gene.mod_tram_inter.ingresar-tramite', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body id="ingresar-tramite-app" data-config='@json($frontendConfig)' class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="ingresarTramiteForm()" x-cloak>
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gene.mod_tram_inter.ingresar-tramite', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}


{{-- Toast --}}
<div x-show="toast.show" x-transition
     class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200] w-full max-w-md px-4">
    <div :class="toast.type === 'success' ? 'bg-slate-600' : 'bg-red-600'"
         class="text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-3 text-sm font-semibold">
        <i :class="toast.type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'"></i>
        <span class="flex-1" x-text="toast.message"></span>
        <button type="button" @click="toast.show = false" class="opacity-80 hover:opacity-100"><i class="fas fa-times"></i></button>
    </div>
</div>

<div class="p-3 sm:p-5 w-full max-w-[1200px] mx-auto space-y-4">

    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between gap-3">
            <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">Ingresar Documentos Internos</h1>
            <a href="{{ route('mod_tram_inter.buscar_tramite') }}"
               @click.prevent="irABuscarTramite($el.href)"
               class="text-[10px] font-black uppercase tracking-widest bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-lg">
                <i class="fas fa-search mr-1"></i> Buscar
            </a>
        </div>

        {{-- Flash éxito como el antiguo --}}
        <div x-show="flashOk" x-cloak class="bg-slate-50 border-b border-slate-100 px-5 py-3 text-center text-sm font-black uppercase tracking-widest text-slate-700">
            TRÁMITE ENVIADO
        </div>

        <form @submit.prevent="enviarFormulario()" class="p-4 sm:p-5">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- ===== Campos Generales (fieldset izquierdo del antiguo) ===== --}}
                <fieldset class="border border-gray-200 rounded-xl p-4 bg-gray-50/50">
                    <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-700">Campos Generales</legend>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Tipo Documento</label>
                            <select id="sel_cdgo_tpo_trmte" x-model="formData.sel_cdgo_tpo_trmte" @change="onTipoDocumentoChange()"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                                <option value="0">-- Seleccione --</option>
                                <template x-for="tipo in tiposDocumento" :key="tipo.cdgo_tpo_trmte">
                                    <option :value="String(tipo.cdgo_tpo_trmte)" x-text="tipo.de_tpo_trmte"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">N° Documento</label>
                            <div class="relative flex items-center gap-2">
                                <input type="text"
                                       id="txt_nu_smlla_intrno"
                                       name="txt_nu_smlla_intrno"
                                       x-model="formData.txt_nu_smlla_intrno"
                                       :readonly="false"
                                       :disabled="false"
                                       autocomplete="off"
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 pr-10 text-sm font-bold bg-white text-gray-800 focus:ring-2 focus:ring-slate-500 outline-none cursor-text"
                                       placeholder="Ej: 10-2026"
                                       title="Campo editable: puede cambiar el correlativo sugerido">
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-500" aria-hidden="true">
                                    <i x-show="loadingSemilla" class="fas fa-spinner fa-spin"></i>
                                    <i x-show="!loadingSemilla" class="fas fa-pen text-[10px] opacity-60"></i>
                                </span>
                            </div>
                            <p class="text-[9px] text-slate-600 mt-1 font-semibold">Editable: el sistema sugiere el correlativo, pero puedes escribir otro número (ej. 25-2026).</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Fecha Documento</label>
                            <input type="datetime-local" id="txt_fe_rcbdo" x-model="formData.txt_fe_rcbdo"
                                   :min="minDate" :max="maxDate" @change="validarFecha()"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Marque las Áreas</label>
                            <div class="relative" @click.outside="areaDropdownOpen = false">
                                <input type="text" x-model="areaSearchQuery" @focus="areaDropdownOpen = true"
                                       placeholder="Buscar área / gerencia..."
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                                <div x-show="areaDropdownOpen && filteredAreas.length" x-cloak
                                     class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-auto custom-scroll">
                                    <template x-for="area in filteredAreas" :key="area.cdgo_area">
                                        <button type="button" @click="selectArea(area)"
                                                class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-slate-50 flex justify-between gap-2">
                                            <span x-text="area.de_area"></span>
                                            <span class="text-gray-400" x-text="area.dc_area"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mt-2" x-show="selectedAreas.length">
                                <template x-for="(area, idx) in selectedAreas" :key="area.cdgo_area">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-[10px] font-bold border"
                                          :class="idx === 0 ? 'bg-slate-100 text-slate-800 border-slate-300' : 'bg-amber-50 text-amber-800 border-amber-200'">
                                        <span x-text="area.de_area"></span>
                                        <span class="text-[9px] font-semibold px-1 py-0.5 rounded"
                                              :class="idx === 0 ? 'bg-slate-200 text-slate-700' : 'bg-amber-200 text-amber-900'"
                                              x-text="idx === 0 ? 'Principal' : 'Copia (c.c.)'"></span>
                                        <button type="button" @click="deselectArea(area)" class="hover:text-red-600"><i class="fas fa-times"></i></button>
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Asunto</label>
                            <textarea id="txt_de_obser" x-model="formData.txt_de_obser" rows="2"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none resize-none"
                                      placeholder="Asunto / observaciones..."></textarea>
                        </div>

                        <div x-show="showCargoSelector" x-cloak>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Seleccione el Cargo</label>
                            <select id="sel_crgos" x-model="formData.sel_crgos"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                                <option value="">-- Cargo principal del área --</option>
                                <template x-for="cargo in areaCargos" :key="cargo.cdgo_crgo">
                                    <option :value="String(cargo.cdgo_crgo)" x-text="cargo.de_crgo"></option>
                                </template>
                            </select>
                        </div>

                        <div class="pt-1 text-center">
                            <button type="button" @click="descargarBorrador()"
                                    :disabled="generandoBorrador"
                                    class="px-4 py-2 bg-slate-600 hover:bg-slate-700 disabled:opacity-50 text-white text-[11px] font-black uppercase tracking-widest rounded-lg">
                                <i class="fas fa-file-word mr-1" :class="generandoBorrador && 'fa-spin'"></i>
                                <span x-text="generandoBorrador ? 'Generando...' : 'Generar Borrador'"></span>
                            </button>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Prioridad</label>
                            <div class="flex items-center gap-6 text-sm font-semibold">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="rdn_in_prrdad_trmte" value="1" x-model="formData.rdn_in_prrdad_trmte" class="text-slate-600 focus:ring-slate-500">
                                    Normal
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer text-red-700">
                                    <input type="radio" name="rdn_in_prrdad_trmte" value="2" x-model="formData.rdn_in_prrdad_trmte" class="text-slate-600 focus:ring-slate-500">
                                    Urgente
                                </label>
                            </div>
                        </div>
                    </div>
                </fieldset>

                {{-- ===== Adjuntar y enviar (fieldset derecho del antiguo) ===== --}}
                <fieldset class="border border-gray-200 rounded-xl p-4 bg-gray-50/50">
                    <legend class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-700">Adjuntar y enviar</legend>

                    <div x-show="firmaDigitalRequerida && formData.sel_cdgo_tpo_trmte !== '63'" x-cloak
                         class="mb-3 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-[11px] text-amber-800 font-semibold">
                        El PDF principal debe estar firmado digitalmente y terminar en <span class="font-mono">[R].pdf</span>.
                    </div>

                    {{-- Tipo 63 --}}
                    <div x-show="formData.sel_cdgo_tpo_trmte === '63'" x-cloak class="space-y-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">PDF Firmado *</label>
                            <input type="file" id="fle_adjunto1_63" accept=".pdf" @change="handleFileSelect($event, 1)"
                                   class="w-full text-xs border border-gray-300 rounded-lg bg-white file:mr-3 file:py-2 file:px-3 file:border-0 file:bg-slate-50 file:text-slate-700 file:font-bold file:text-[10px] file:uppercase">
                            <p class="text-[10px] text-gray-400 mt-1" x-show="files[1]" x-text="files[1]?.name"></p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 mb-1">Word sin firma *</label>
                            <input type="file" id="fle_adjunto2_63" accept=".doc,.docx" @change="handleFileSelect($event, 2)"
                                   class="w-full text-xs border border-gray-300 rounded-lg bg-white file:mr-3 file:py-2 file:px-3 file:border-0 file:bg-slate-50 file:text-slate-700 file:font-bold file:text-[10px] file:uppercase">
                            <p class="text-[10px] text-gray-400 mt-1" x-show="files[2]" x-text="files[2]?.name"></p>
                        </div>
                    </div>

                    {{-- Normal --}}
                    <div x-show="formData.sel_cdgo_tpo_trmte !== '63'" class="space-y-3">
                        <div class="border border-gray-200 rounded-lg p-3 bg-white">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Principal</p>
                            <div class="mb-3">
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Trámite PDF Firmado *</label>
                                <input type="file" id="fle_adjunto1" accept=".pdf,application/pdf" @change="handleFileSelect($event, 1)"
                                       class="w-full text-xs border border-gray-300 rounded-lg bg-white file:mr-3 file:py-2 file:px-3 file:border-0 file:bg-slate-50 file:text-slate-700 file:font-bold file:text-[10px] file:uppercase">
                                <p class="text-[10px] text-gray-400 mt-1" x-show="files[1]" x-text="files[1]?.name"></p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                                <input type="checkbox" value="1" x-model="formData.chx_in_envo_fsco" class="rounded border-gray-300 text-slate-600 focus:ring-slate-500">
                                ¿Enviado en físico?
                            </label>
                        </div>

                        <div class="border border-gray-200 rounded-lg p-3 bg-white space-y-3">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Más Archivos</p>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Adjuntar Digital 1</label>
                                <input type="file" id="fle_adjunto2" @change="handleFileSelect($event, 2)"
                                       class="w-full text-xs border border-gray-300 rounded-lg bg-white file:mr-3 file:py-2 file:px-3 file:border-0 file:bg-gray-100 file:text-gray-700 file:font-bold file:text-[10px] file:uppercase">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Adjuntar Digital 2</label>
                                <input type="file" id="fle_adjunto3" @change="handleFileSelect($event, 3)"
                                       class="w-full text-xs border border-gray-300 rounded-lg bg-white file:mr-3 file:py-2 file:px-3 file:border-0 file:bg-gray-100 file:text-gray-700 file:font-bold file:text-[10px] file:uppercase">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Adjuntar Digital 3</label>
                                <input type="file" id="fle_adjunto4" @change="handleFileSelect($event, 4)"
                                       class="w-full text-xs border border-gray-300 rounded-lg bg-white file:mr-3 file:py-2 file:px-3 file:border-0 file:bg-gray-100 file:text-gray-700 file:font-bold file:text-[10px] file:uppercase">
                            </div>
                        </div>
                    </div>

                    <div class="pt-5 text-center">
                        <button type="submit" :disabled="submitting"
                                class="px-6 py-2.5 bg-slate-600 hover:bg-slate-700 disabled:opacity-50 text-white text-[11px] font-black uppercase tracking-widest rounded-lg shadow-sm">
                            <span x-show="!submitting"><i class="fas fa-paper-plane mr-1"></i> Enviar Trámite</span>
                            <span x-show="submitting" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Enviando...</span>
                        </button>
                        <div x-show="submitting && uploadProgress > 0" x-cloak class="mt-3">
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-2 bg-slate-500 transition-all" :style="`width:${uploadProgress}%`"></div>
                            </div>
                            <p class="text-[10px] font-bold text-slate-700 mt-1" x-text="uploadProgress + '%'"></p>
                        </div>
                    </div>
                </fieldset>
            </div>
        </form>
    </div>
</div>
</body>
</html>
