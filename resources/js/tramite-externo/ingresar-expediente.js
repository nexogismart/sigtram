/**
 * Componente Alpine.js para Ingreso de Expedientes (Trámite Externo).
 * Extraído de resources/views/mod_gm/mod_tram_exte/index.blade.php.
 */

import { readRouteConfig } from '../shared/route-config.js';

/**
 * Crea la definición del componente Alpine `ingresoExpediente`.
 * @param {object} [overrideConfig]
 * @returns {object}
 */
export function createIngresoExpedienteComponent(overrideConfig = {}) {
    const domConfig = (typeof document !== 'undefined')
        ? readRouteConfig('#ingreso-expediente-app', 'data-config')
        : {};
    const config = { ...domConfig, ...overrideConfig };

    const csrf = (typeof document !== 'undefined')
        ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || config.csrfToken || '')
        : (config.csrfToken || '');

    const routes = {
        store: config.routes?.store || '',
        buscarUsuario: config.routes?.buscarUsuario || '',
        buscarJuridico: config.routes?.buscarJuridico || '',
        sugerirJuridicos: config.routes?.sugerirJuridicos || '',
        subgerencias: config.routes?.subgerencias || '',
        tramites: config.routes?.tramites || '',
        requisitos: config.routes?.requisitos || '',
        buscarTupa: config.routes?.buscarTupa || '',
        lista: config.routes?.lista || '',
        contarFolios: config.routes?.contarFolios || '',
        buscaExpedi: config.routes?.buscaExpedi || '',
        hojaRuta: config.routes?.hojaRuta || '',
        bandeja: config.routes?.bandeja || '',
    };

    return {
        ultimoExpediente: null,
        voucherBloqueado: false,
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
        tiposDocumento: config.tiposDocumento || [],
        catalogoValido: Boolean(config.catalogoValido),
        tipoExterno: config.tipoExterno || null,
        tipoSimple: config.tipoSimple || null,
        tipoObservado: config.tipoObservado || null,
        areaObservado: parseInt(config.areaObservado || 0, 10),
        etiquetaExterno: config.etiquetaExterno || 'Externo',
        etiquetaSimple: config.etiquetaSimple || 'Simple',
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
            cdgo_tpo_trmte: config.tipoExterno || null,
            cdgo_tpo_observado: config.tipoObservado || null,
            cdgo_area_observado: parseInt(config.areaObservado || 0, 10),
            tipo_persona: 'natural',
            tipo_doc: String(config.tipoDocDefault || '1'),
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
                if (res.ok && data.success && data.folios > 0) {
                    this.form.folios = data.folios;
                    this.showToast('Folios determinados automáticamente: ' + data.folios, 'success');
                } else {
                    this.showToast(data.message || 'No se pudo determinar automáticamente el número de páginas. Verifique el PDF o comuníquese con soporte', 'error');
                }
            } catch (e) {
                this.showToast('No se pudo determinar automáticamente el número de páginas. Verifique el PDF o comuníquese con soporte', 'error');
            }
        },

        isDirty() {
            return Boolean(
                this.form.num_doc ||
                this.form.nombres ||
                this.form.ape_paterno ||
                this.form.ape_materno ||
                this.form.telefono ||
                this.form.correo ||
                this.form.direccion ||
                this.form.ruc ||
                this.form.razon_social ||
                this.form.observaciones ||
                this.form.gerencia ||
                this.form.servicio ||
                this.form.tipo_tram_tupa ||
                this.archivo ||
                (this.form.folios && parseInt(this.form.folios, 10) > 0)
            );
        },

        volverBandeja() {
            if (this.isDirty()) {
                if (!confirm('Tiene cambios sin guardar en el formulario. ¿Está seguro de que desea salir y volver a la bandeja?')) {
                    return;
                }
            }
            if (window.parent && window.parent !== window && typeof window.parent.activarModuloPorUrl === 'function') {
                const ok = window.parent.activarModuloPorUrl(routes.bandeja);
                if (ok) return;
            }
            window.location.href = routes.bandeja;
        },

        abrirComprobante(url) {
            if (!url) return;
            const popup = window.open(url, '_blank', 'scrollbars=yes,width=450,height=600');
            if (!popup || popup.closed || typeof popup.closed === 'undefined') {
                this.voucherBloqueado = true;
            } else {
                this.voucherBloqueado = false;
            }
        },

        abrirExpediente(url) {
            if (!url) return;
            window.open(url, '_blank');
        },

        registrarOtro() {
            this.ultimoExpediente = null;
            this.voucherBloqueado = false;
            this.resetFormulario();
        },

        resetFormulario() {
            this.form = {
                tipo_tramite: 'externo',
                cdgo_tpo_trmte: this.tipoExterno,
                cdgo_tpo_observado: this.tipoObservado,
                cdgo_area_observado: this.areaObservado,
                tipo_persona: 'natural',
                tipo_doc: String(this.tiposDocumento?.[0]?.cdgo_dcmntos_iden || '1'),
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
            };
            this.archivo = null;
            const fileInput = document.getElementById('fle_adjunto');
            if (fileInput) fileInput.value = '';
            this.listaSubgerencias = [];
            this.listaTramites = [];
            this.listaRequisitos = [];
            this.busquedaDirectaTerm = '';
            this.resultadosTupa = [];
            this.mostrarResultados = false;
            this.sugerenciasJuri = [];
            this.errors = {};
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
            if (!this.catalogoValido || !this.tipoExterno || !this.tipoSimple || !this.tipoObservado) {
                this.showToast('Configuración incompleta: el catálogo no tiene configurados los tipos E, S u O.', 'error');
                return;
            }
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
                    this.ultimoExpediente = {
                        id: data.expediente_id,
                        numero: data.numero_expediente || '',
                        folios: data.folios || this.form.folios,
                        voucher_url: data.voucher_url,
                        show_url: data.show_url,
                        inbox_url: data.inbox_url || routes.bandeja,
                    };
                    this.voucherBloqueado = false;
                    if (data.voucher_url) {
                        this.abrirComprobante(data.voucher_url);
                    }
                    this.resetFormulario();
                    this.lista.pagina = 1;
                    this.fetchLista();
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
