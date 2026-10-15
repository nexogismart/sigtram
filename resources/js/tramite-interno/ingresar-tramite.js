/**
 * Componente Alpine.js para Ingresar Documentos Internos (Trámite Interno).
 * Extraído de ingresar-tramite.blade.php.
 */

import { readRouteConfig } from '../shared/route-config.js';

/**
 * Crea la definición del componente Alpine `ingresarTramiteForm`.
 * @param {object} [overrideConfig]
 * @returns {object}
 */
export function createIngresarTramiteComponent(overrideConfig = {}) {
    const domConfig = (typeof document !== 'undefined')
        ? readRouteConfig('#ingresar-tramite-app', 'data-config')
        : {};
    const config = { ...domConfig, ...overrideConfig };

    const csrf = (typeof document !== 'undefined')
        ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || config.csrfToken || '')
        : (config.csrfToken || '');

    const routes = {
        semilla: config.routes?.semilla || '/mod_gene/mod_tram_inter/ingresar_tramite/ajax/semilla',
        cargos: config.routes?.cargos || '/mod_gene/mod_tram_inter/ingresar_tramite/ajax/cargos',
        borrador: config.routes?.borrador || config.borradorUrl || '/mod_gene/mod_tram_inter/ingresar_tramite/generar-borrador',
        store: config.routes?.store || config.storeUrl || '/mod_gene/mod_tram_inter/ingresar_tramite/store',
    };

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
        cargoFirma: Number(config.cargoFirma || 0),
        minDate: config.minDate || '',
        maxDate: config.maxDate || '',
        myAreaId: parseInt(config.myAreaId || 0, 10),
        tiposDocumento: config.tiposDocumento || [],
        allAreas: config.allAreas || [],
        selectedAreas: [],
        areaCargos: [],
        files: { 1: null, 2: null, 3: null, 4: null },
        toast: { show: false, message: '', type: 'success' },
        formData: {
            sel_cdgo_tpo_trmte: '0',
            txt_nu_smlla_intrno: '',
            txt_fe_rcbdo: config.defaultDate || '',
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
            if (this.selectedAreas.length > 0 && parseInt(this.selectedAreas[0].cdgo_area, 10) === this.myAreaId) {
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
                const res = await fetch(`${routes.cargos}?cdgo_area=${this.myAreaId}`);
                const json = await res.json();
                if (json.success) this.areaCargos = json.cargos || [];
            } catch (e) { console.error(e); }
        },

        async onTipoDocumentoChange() {
            const tipoId = parseInt(this.formData.sel_cdgo_tpo_trmte, 10);
            this.clearFile(1); this.clearFile(2); this.clearFile(3); this.clearFile(4);
            if (!tipoId) {
                this.formData.txt_nu_smlla_intrno = '';
                this.firmaDigitalRequerida = false;
                return;
            }
            this.loadingSemilla = true;
            try {
                const res = await fetch(`${routes.semilla}?cdgo_tpo_trmte=${tipoId}`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                if (json.success) {
                    this.formData.txt_nu_smlla_intrno = json.nu_smlla_intrno;
                    this.firmaDigitalRequerida = parseInt(json.in_frma_dgtal_trmte, 10) === 1 && this.cargoFirma === 1;
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
                fd.append('_token', csrf);
                fd.append('sel_cdgo_tpo_trmte', this.formData.sel_cdgo_tpo_trmte);
                fd.append('txt_nu_smlla_intrno', this.formData.txt_nu_smlla_intrno);
                fd.append('txt_fe_rcbdo', this.formData.txt_fe_rcbdo);
                fd.append('txt_de_obser', this.formData.txt_de_obser);
                if (this.formData.sel_crgos) fd.append('sel_crgos', this.formData.sel_crgos);
                this.selectedAreas.forEach(a => fd.append('sel_areas[]', a.cdgo_area));

                const response = await fetch(routes.borrador, {
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

                const response = await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', routes.store);
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
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
        },

        irABuscarTramite(url) {
            if (window.parent && typeof window.parent.loadContentInPanel === 'function') {
                window.parent.loadContentInPanel(url);
            } else {
                window.location.href = url;
            }
        }
    };
}
