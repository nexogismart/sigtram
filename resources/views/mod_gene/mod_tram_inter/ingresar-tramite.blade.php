<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingresar Documentos Internos — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: "Figtree", "Segoe UI", Tahoma, sans-serif; }
        [x-cloak] { display: none !important; }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        :focus-visible { outline: 2px solid #334155; outline-offset: 2px; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gene.mod_tram_inter.ingresar-tramite'])
</head>
<body data-sigtram-screen="mod_gene.mod_tram_inter.ingresar-tramite" class="sigtram-module bg-[#f8fafc] text-gray-800 min-h-screen" x-data="ingresarTramiteForm()" x-cloak>
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gene.mod_tram_inter.ingresar-tramite'])

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
               onclick="event.preventDefault(); navigateToBuscarTramite(this.href);"
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

<script>
function ingresarTramiteForm() {
    return {
        submitting: false,
        uploadProgress: 0,
        loadingSemilla: false,
        areaDropdownOpen: false,
        areaSearchQuery: '',
        firmaDigitalRequerida: false,
        showCargoSelector: false,
        generandoBorrador: false,
        flashOk: false,
        cargoFirma: {{ $inFrmaDgtalCargo ? '1' : '0' }},
        minDate: @json($minDate),
        maxDate: @json($maxDate),
        myAreaId: {{ (int) $areaId }},
        tiposDocumento: @json($tiposDocumento),
        allAreas: @json($destinoAreas),
        selectedAreas: [],
        areaCargos: [],
        files: { 1: null, 2: null, 3: null, 4: null },
        toast: { show: false, message: '', type: 'success' },
        formData: {
            sel_cdgo_tpo_trmte: '0',
            txt_nu_smlla_intrno: '',
            txt_fe_rcbdo: @json($defaultDate),
            txt_de_obser: '',
            rdn_in_prrdad_trmte: '1',
            chx_in_envo_fsco: false,
            sel_crgos: ''
        },

        get filteredAreas() {
            const selected = this.selectedAreas.map(a => a.cdgo_area);
            const q = this.areaSearchQuery.trim().toLowerCase();
            return this.allAreas.filter(a => {
                if (selected.includes(a.cdgo_area)) return false;
                if (!q) return true;
                return String(a.de_area).toLowerCase().includes(q) || String(a.dc_area || '').toLowerCase().includes(q);
            });
        },

        selectArea(area) {
            this.selectedAreas.push(area);
            this.areaSearchQuery = '';
            this.areaDropdownOpen = false;
            this.evaluarSelectorCargos();
        },
        deselectArea(area) {
            this.selectedAreas = this.selectedAreas.filter(a => a.cdgo_area !== area.cdgo_area);
            this.evaluarSelectorCargos();
        },
        evaluarSelectorCargos() {
            if (this.selectedAreas.length > 0 && parseInt(this.selectedAreas[0].cdgo_area) === this.myAreaId) {
                this.showCargoSelector = true;
                this.fetchCargosPropiaArea();
            } else {
                this.showCargoSelector = false;
                this.formData.sel_crgos = '';
                this.areaCargos = [];
            }
        },
        async fetchCargosPropiaArea() {
            try {
                const res = await fetch(`/mod_gene/mod_tram_inter/ingresar_tramite/ajax/cargos?cdgo_area=${this.myAreaId}`);
                const json = await res.json();
                if (json.success) this.areaCargos = json.cargos || [];
            } catch (e) { console.error(e); }
        },

        async onTipoDocumentoChange() {
            const tipoId = parseInt(this.formData.sel_cdgo_tpo_trmte);
            this.clearFile(1); this.clearFile(2); this.clearFile(3); this.clearFile(4);
            if (!tipoId) {
                this.formData.txt_nu_smlla_intrno = '';
                this.firmaDigitalRequerida = false;
                return;
            }
            this.loadingSemilla = true;
            try {
                const res = await fetch(`/mod_gene/mod_tram_inter/ingresar_tramite/ajax/semilla?cdgo_tpo_trmte=${tipoId}`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                if (json.success) {
                    this.formData.txt_nu_smlla_intrno = json.nu_smlla_intrno;
                    this.firmaDigitalRequerida = parseInt(json.in_frma_dgtal_trmte) === 1 && this.cargoFirma === 1;
                } else {
                    this.showToast(json.message || 'No se pudo obtener el número', 'error');
                    this.formData.sel_cdgo_tpo_trmte = '0';
                    this.formData.txt_nu_smlla_intrno = '';
                }
            } catch (e) {
                this.showToast('No se pudo establecer conexión con el servidor.', 'error');
                this.formData.sel_cdgo_tpo_trmte = '0';
            } finally {
                this.loadingSemilla = false;
            }
        },

        validarFecha() {
            const inputDate = new Date(this.formData.txt_fe_rcbdo);
            const minD = new Date(this.minDate);
            const maxD = new Date(this.maxDate);
            if (inputDate < minD) {
                this.showToast('La fecha del documento no puede ser mayor a 4 días de antigüedad.', 'error');
                this.formData.txt_fe_rcbdo = this.maxDate;
            } else if (inputDate > maxD) {
                this.showToast('No está permitido registrar documentos con fechas futuras.', 'error');
                this.formData.txt_fe_rcbdo = this.maxDate;
            }
        },

        handleFileSelect(e, idx) {
            this.setFile(e.target.files[0], idx);
        },
        setFile(file, idx) {
            if (!file) return;
            if (file.size > 20 * 1024 * 1024) {
                this.showToast('El archivo no debe superar 20MB.', 'error');
                return;
            }
            if (this.formData.sel_cdgo_tpo_trmte === '63') {
                if (idx === 1 && file.type !== 'application/pdf') {
                    this.showToast('El primer adjunto debe ser PDF y el segundo adjunto debe ser un Word', 'error');
                    return;
                }
                if (idx === 2 && !/\.(doc|docx)$/i.test(file.name)) {
                    this.showToast('El primer adjunto debe ser PDF y el segundo adjunto debe ser un Word', 'error');
                    return;
                }
            } else if (idx === 1) {
                if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) {
                    this.showToast('El trámite principal debe adjuntarse en formato PDF.', 'error');
                    return;
                }
                if (this.firmaDigitalRequerida && !file.name.toUpperCase().endsWith('[R].PDF')) {
                    this.showToast('El primer adjunto debe ser PDF y ser firmado digitalmente', 'error');
                    return;
                }
            }
            this.files[idx] = file;
        },
        clearFile(idx) {
            this.files[idx] = null;
            const id = this.formData.sel_cdgo_tpo_trmte === '63' ? `fle_adjunto${idx}_63` : `fle_adjunto${idx}`;
            const el = document.getElementById(id);
            if (el) el.value = '';
        },

        showToast(msg, type = 'success') {
            this.toast = { show: true, message: msg, type };
            setTimeout(() => { this.toast.show = false; }, 5000);
        },

        // Validaciones = validarinter_2 del antiguo
        validarAntesEnviar() {
            if (this.formData.sel_cdgo_tpo_trmte === '0') {
                this.showToast('Debe seleccionar tipo de Documento', 'error');
                return false;
            }
            if (!String(this.formData.txt_nu_smlla_intrno || '').trim()) {
                this.showToast('Debe especificar el Numero del Documento', 'error');
                return false;
            }
            if (!this.selectedAreas.length) {
                this.showToast('Debe especificar una Gerencia a Enviar', 'error');
                return false;
            }
            if (this.formData.sel_cdgo_tpo_trmte === '63') {
                if (!this.files[1] || !this.files[2]) {
                    this.showToast('Debe adjuntar el digital del documento', 'error');
                    return false;
                }
            } else if (!this.files[1]) {
                this.showToast('Debe adjuntar el digital del documento', 'error');
                return false;
            }
            if (!String(this.formData.txt_de_obser || '').trim()) {
                this.showToast('Debe especificar las observaciones', 'error');
                return false;
            }
            return confirm('¿Está seguro de guardar los datos?');
        },

        async descargarBorrador() {
            if (this.formData.sel_cdgo_tpo_trmte === '0' || !this.selectedAreas.length || !this.formData.txt_de_obser.trim()) {
                this.showToast('Complete tipo, asunto y destinos antes de generar el borrador.', 'error');
                return;
            }
            this.generandoBorrador = true;
            try {
                const fd = new FormData();
                fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                fd.append('sel_cdgo_tpo_trmte', this.formData.sel_cdgo_tpo_trmte);
                fd.append('txt_nu_smlla_intrno', this.formData.txt_nu_smlla_intrno);
                fd.append('txt_fe_rcbdo', this.formData.txt_fe_rcbdo);
                fd.append('txt_de_obser', this.formData.txt_de_obser);
                if (this.formData.sel_crgos) fd.append('sel_crgos', this.formData.sel_crgos);
                this.selectedAreas.forEach(a => fd.append('sel_areas[]', a.cdgo_area));

                const response = await fetch(@json(route('mod_tram_inter.ingresar_tramite.generar_borrador')), {
                    method: 'POST',
                    body: fd,
                    headers: { Accept: 'application/json, application/vnd.openxmlformats-officedocument.wordprocessingml.document' }
                });
                if (!response.ok) {
                    const err = await response.json().catch(() => null);
                    throw new Error(err?.message || 'Error al generar el borrador.');
                }
                const blob = await response.blob();
                let filename = response.headers.get('x-suggested-filename');
                filename = filename ? decodeURIComponent(filename) : 'Borrador.docx';
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = filename; a.click();
                URL.revokeObjectURL(url);
                this.showToast('Borrador generado correctamente.', 'success');
            } catch (e) {
                this.showToast(e.message || 'No se pudo generar el borrador.', 'error');
            } finally {
                this.generandoBorrador = false;
            }
        },

        async enviarFormulario() {
            if (this.submitting) return;
            if (!this.validarAntesEnviar()) return;

            this.submitting = true;
            this.uploadProgress = 0;
            this.flashOk = false;

            try {
                const fd = new FormData();
                fd.append('sel_cdgo_tpo_trmte', this.formData.sel_cdgo_tpo_trmte);
                fd.append('txt_nu_smlla_intrno', this.formData.txt_nu_smlla_intrno);
                fd.append('txt_fe_rcbdo', this.formData.txt_fe_rcbdo);
                fd.append('txt_de_obser', this.formData.txt_de_obser);
                fd.append('rdn_in_prrdad_trmte', this.formData.rdn_in_prrdad_trmte);
                if (this.formData.chx_in_envo_fsco) fd.append('chx_in_envo_fsco', '1');
                if (this.formData.sel_crgos) fd.append('sel_crgos', this.formData.sel_crgos);
                this.selectedAreas.forEach(a => fd.append('sel_areas[]', a.cdgo_area));
                if (this.files[1]) fd.append('fle_adjunto1', this.files[1]);
                if (this.files[2]) fd.append('fle_adjunto2', this.files[2]);
                if (this.files[3]) fd.append('fle_adjunto3', this.files[3]);
                if (this.files[4]) fd.append('fle_adjunto4', this.files[4]);

                const token = document.querySelector('meta[name="csrf-token"]').content;
                const response = await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '/mod_gene/mod_tram_inter/ingresar_tramite/store');
                    xhr.setRequestHeader('X-CSRF-TOKEN', token);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.upload.addEventListener('progress', (ev) => {
                        if (ev.lengthComputable) this.uploadProgress = Math.round((ev.loaded * 100) / ev.total);
                    });
                    xhr.onload = () => {
                        let data = {};
                        try { data = JSON.parse(xhr.responseText); } catch (e) {}
                        resolve({ status: xhr.status, ok: xhr.status >= 200 && xhr.status < 300, data });
                    };
                    xhr.onerror = () => reject(new Error('Error de red'));
                    xhr.send(fd);
                });

                if (response.status === 422) {
                    const first = response.data?.errors ? Object.values(response.data.errors)[0][0] : 'Datos inválidos';
                    this.showToast(first, 'error');
                    return;
                }
                if (!response.ok || !response.data?.success) {
                    this.showToast(response.data?.message || 'Error al guardar el trámite', 'error');
                    return;
                }

                this.uploadProgress = 100;
                this.flashOk = true;
                this.showToast(response.data.message || 'TRÁMITE ENVIADO', 'success');

                // Como el antiguo: queda en la misma pantalla, limpia formulario
                setTimeout(() => this.resetFormulario(), 1500);
            } catch (e) {
                this.showToast('No se pudo establecer contacto con el servidor.', 'error');
            } finally {
                this.submitting = false;
                this.uploadProgress = 0;
            }
        },

        resetFormulario() {
            this.formData = {
                sel_cdgo_tpo_trmte: '0',
                txt_nu_smlla_intrno: '',
                txt_fe_rcbdo: this.maxDate,
                txt_de_obser: '',
                rdn_in_prrdad_trmte: '1',
                chx_in_envo_fsco: false,
                sel_crgos: ''
            };
            this.selectedAreas = [];
            this.areaCargos = [];
            this.showCargoSelector = false;
            this.firmaDigitalRequerida = false;
            this.clearFile(1); this.clearFile(2); this.clearFile(3); this.clearFile(4);
        }
    };
}

function navigateToBuscarTramite(url) {
    if (window.parent && typeof window.parent.loadContentInPanel === 'function') {
        window.parent.loadContentInPanel(url);
    } else {
        window.location.href = url;
    }
}
</script>
</body>
</html>
