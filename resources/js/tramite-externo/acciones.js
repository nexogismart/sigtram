/**
 * Flujos transaccionales y modales operativos para Trámite Externo (RF-05).
 * Recepción, Derivación, Asignación, Envío, Finalización, Devolución, Archivo, Préstamo, Redirección.
 */

import {
    apiFetchSemilla,
    apiFetchSolicitudVideo,
    apiSubmitRecepcionar,
    apiSubmitDerivar,
    apiSubmitAsignar,
    apiSubmitEnviar,
    apiSubmitFinalizar,
    apiSubmitDevolver,
    apiSubmitArchivar,
    apiSubmitPrestar,
    apiSubmitRetornarPrestamo,
    apiSubmitRedirigir,
} from './api.js';

export const accionesMixin = {
    async fetchSemilla(modal) {
        let docTipo = '0';
        if (modal === 'derivar') docTipo = this.derivarDocTipo;
        if (modal === 'asignar') docTipo = this.asignarDocTipo;
        if (modal === 'enviar') docTipo = this.enviarDocTipo;
        if (modal === 'finalizar') docTipo = this.finalizarDocTipo;

        if (docTipo === '0') return;

        try {
            const res = await apiFetchSemilla(this.routes, docTipo);
            if (res.ok && res.data && res.data.status === 'success') {
                const sem = res.data.semilla;
                if (modal === 'derivar') this.derivarTramiteNro = sem;
                if (modal === 'asignar') this.asignarTramiteNro = sem;
                if (modal === 'enviar') this.enviarTramiteNro = sem;
                if (modal === 'finalizar') this.finalizarTramiteNro = sem;
            }
        } catch (e) {
            console.error('Error al obtener semilla:', e);
        }
    },

    getGerencias() {
        return (this.areas || []).filter((a) => a.level === 0);
    },

    getSubgerencias(gerId) {
        if (!gerId || gerId === '0') return [];
        return (this.areas || []).filter((a) => a.cdgo_area_prim == gerId);
    },

    async showVideoRequests(item) {
        this.videoRequests = [];
        this.modalVideoRequests = true;
        try {
            const res = await apiFetchSolicitudVideo(this.routes, item.cdgo_dto_trmte);
            if (res.ok && res.data && res.data.status === 'success') {
                this.videoRequests = res.data.data || [];
            } else {
                this.triggerToast(`Error: ${res.message || 'No se pudieron obtener solicitudes de video'}`);
            }
        } catch (e) {
            console.error(e);
            this.triggerToast('Error al consultar solicitudes de video');
        }
    },

    async submitRecepcionar() {
        this.loading = true;
        try {
            const res = await apiSubmitRecepcionar(this.routes, {
                movimiento_ids: this.selectedIds,
                fecha: this.recepcionFecha,
                observaciones: this.recepcionObs,
            });
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalRecepcionar = false;
                this.triggerToast(res.data.message || 'Trámites recepcionados');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al recepcionar'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    async submitDerivar(formEl) {
        this.loading = true;
        const fd = new FormData(formEl);
        this.selectedIds.forEach((id) => {
            fd.append('movimiento_ids[]', id);
        });
        fd.append('fecha', this.derivarFecha);
        fd.append('observaciones', this.derivarObs);
        fd.append('gerencia', this.derivarGerencia);
        fd.append('subgerencia', this.derivarSubgerencia);
        fd.append('enviar_con_tramite', this.derivarConTramite ? '1' : '0');
        if (this.derivarConTramite) {
            fd.append('doc_tipo', this.derivarDocTipo);
            fd.append('tramite_nro', this.derivarTramiteNro);
            fd.append('folios', this.derivarFolios);
        }

        try {
            const res = await apiSubmitDerivar(this.routes, fd);
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalDerivar = false;
                this.triggerToast(res.data.message || 'Trámites derivados');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al derivar'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    async submitAsignar(formEl) {
        this.loading = true;
        const fd = new FormData(formEl);
        this.selectedIds.forEach((id) => {
            fd.append('movimiento_ids[]', id);
        });
        fd.append('fecha', this.asignarFecha);
        fd.append('observaciones', this.asignarObs);
        fd.append('cargo', this.asignarCargo);
        fd.append('enviar_con_tramite', this.asignarConTramite ? '1' : '0');
        if (this.asignarConTramite) {
            fd.append('doc_tipo', this.asignarDocTipo);
            fd.append('tramite_nro', this.asignarTramiteNro);
            fd.append('folios', this.asignarFolios);
        }

        try {
            const res = await apiSubmitAsignar(this.routes, fd);
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalAsignar = false;
                this.triggerToast(res.data.message || 'Trámites asignados');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al asignar'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    async submitEnviar(formEl) {
        this.loading = true;
        const fd = new FormData(formEl);
        this.selectedIds.forEach((id) => {
            fd.append('movimiento_ids[]', id);
        });
        fd.append('fecha', this.enviarFecha);
        fd.append('observaciones', this.enviarObs);
        this.enviarAreas.forEach((a) => fd.append('areas[]', a));
        fd.append('enviar_con_tramite', this.enviarConTramite ? '1' : '0');
        if (this.enviarConTramite) {
            fd.append('doc_tipo', this.enviarDocTipo);
            fd.append('tramite_nro', this.enviarTramiteNro);
            fd.append('folios', this.enviarFolios);
        }

        try {
            const res = await apiSubmitEnviar(this.routes, fd);
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalEnviar = false;
                this.triggerToast(res.data.message || 'Trámites enviados');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al enviar'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    async submitFinalizar(formEl) {
        this.loading = true;
        const fd = new FormData(formEl);
        this.selectedIds.forEach((id) => {
            fd.append('movimiento_ids[]', id);
        });
        fd.append('fecha', this.finalizarFecha);
        fd.append('observaciones', this.finalizarObs);
        fd.append('enviar_con_tramite', this.finalizarConTramite ? '1' : '0');
        if (this.finalizarConTramite) {
            fd.append('doc_tipo', this.finalizarDocTipo);
            fd.append('tramite_nro', this.finalizarTramiteNro);
            fd.append('folios', this.finalizarFolios);
        }
        fd.append('enviar_correo', this.finalizarEnviarCorreo);

        try {
            const res = await apiSubmitFinalizar(this.routes, fd);
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalFinalizar = false;
                this.triggerToast(res.data.message || 'Trámites finalizados');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al finalizar'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    abrirModalPrestar() {
        if (this.selectedIds.length !== 1) {
            this.triggerToast('Seleccione un solo expediente para prestar.');
            return;
        }
        this.prestarPrestatario = '';
        this.prestarFinalidad = '';
        this.modalPrestar = true;
    },

    abrirModalRetorno() {
        if (this.selectedIds.length !== 1) {
            this.triggerToast('Seleccione un solo expediente para retornar.');
            return;
        }
        this.retornoObs = 'Devolución confirmada de expediente';
        this.modalRetornarPrestamo = true;
    },

    abrirModalRedirigir() {
        if (this.selectedIds.length !== 1) {
            this.triggerToast('Seleccione un solo expediente para redirigir.');
            return;
        }
        this.redirigirCargoId = '0';
        this.redirigirMotivo = '';
        this.modalRedirigir = true;
    },

    async submitDevolver() {
        if (!this.devolverMotivo || this.devolverMotivo.trim().length < 5) {
            this.triggerToast('El motivo de devolución es obligatorio (mínimo 5 caracteres).');
            return;
        }
        this.loading = true;
        try {
            const res = await apiSubmitDevolver(this.routes, {
                movimiento_ids: this.selectedIds,
                motivo: this.devolverMotivo,
                fecha: this.devolverFecha,
            });
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalDevolver = false;
                this.devolverMotivo = '';
                this.triggerToast(res.data.message || 'Trámites devueltos');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al devolver'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    async submitArchivar() {
        if (!this.archivarUbicacion || this.archivarUbicacion.trim().length < 2) {
            this.triggerToast('La ubicación física es obligatoria.');
            return;
        }
        this.loading = true;
        try {
            const res = await apiSubmitArchivar(this.routes, {
                movimiento_ids: this.selectedIds,
                ubicacion: this.archivarUbicacion,
                observaciones: this.archivarObs,
            });
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalArchivar = false;
                this.archivarUbicacion = '';
                this.archivarObs = '';
                this.triggerToast(res.data.message || 'Trámites archivados');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al archivar'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    async submitPrestar() {
        if (!this.prestarPrestatario || !this.prestarFinalidad) {
            this.triggerToast('El prestatario y la finalidad son obligatorios.');
            return;
        }
        this.loading = true;
        try {
            const movId = this.selectedIds[0];
            const res = await apiSubmitPrestar(this.routes, {
                movimiento_id: movId,
                prestatario: this.prestarPrestatario,
                finalidad: this.prestarFinalidad,
                fecha_salida: this.prestarSalida,
                fecha_retorno: this.prestarRetorno || null,
            });
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalPrestar = false;
                this.triggerToast(res.data.message || 'Préstamo registrado');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al prestar'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    async submitRetornarPrestamo() {
        this.loading = true;
        try {
            const movId = this.selectedIds[0];
            const res = await apiSubmitRetornarPrestamo(this.routes, {
                movimiento_id: movId,
                observaciones: this.retornoObs,
            });
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalRetornarPrestamo = false;
                this.triggerToast(res.data.message || 'Retorno de préstamo registrado');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al retornar préstamo'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    async submitRedirigir() {
        if (this.redirigirCargoId === '0' || !this.redirigirMotivo) {
            this.triggerToast('Seleccione un cargo de destino e ingrese el motivo.');
            return;
        }
        this.loading = true;
        try {
            const movId = this.selectedIds[0];
            const res = await apiSubmitRedirigir(this.routes, {
                movimiento_id: movId,
                nuevo_cargo_id: this.redirigirCargoId,
                motivo: this.redirigirMotivo,
            });
            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.modalRedirigir = false;
                this.triggerToast(res.data.message || 'Expediente redirigido');
                this.fetchItems();
                this.fetchConteos();
            } else {
                this.triggerToast(`Error: ${res.message || 'Error al redirigir'}`);
            }
        } catch (err) {
            this.loading = false;
            this.triggerToast('Error de conexión');
        }
    },

    openHojaRuta(item) {
        if (!item) return;

        const id = Number(item.documento_id || item.cdgo_dto_trmte);

        if (!Number.isInteger(id) || id <= 0) {
            this.triggerToast?.('No se pudo identificar el expediente.');
            return;
        }

        const base =
            this.routes?.hojaRuta ||
            '/mod_gm/mod_tram_exte/segi_expedi/hoja-ruta/';

        const url = base.endsWith('/')
            ? `${base}${id}`
            : `${base}/${id}`;

        window.open(url, '_blank');
    },

    imprimirBandeja() {
        const baseUrl = this.routes?.imprimirBandeja || '/mod_gm/mod_tram_exte/segi_expedi/imprimir-bandeja';
        const params = new URLSearchParams({
            bandeja: this.activeBandeja,
            criterio: this.criterio,
            descripcion: this.descripcion,
            desde: this.desde,
            hasta: this.hasta,
        });
        window.open(`${baseUrl}?${params.toString()}`, '_blank');
    },

    exportarExcel() {
        if (!this.puedeExportarExcel) {
            return;
        }

        const baseUrl = this.routes?.exportarExcel;
        if (!baseUrl) {
            console.error('Ruta de exportación a Excel no disponible en la configuración del cliente.');
            return;
        }

        this.exportandoExcel = true;

        const params = new URLSearchParams();
        if (this.activeBandeja) params.append('bandeja', this.activeBandeja);
        if (this.criterio) params.append('criterio', this.criterio);
        if (this.descripcion) params.append('descripcion', this.descripcion);
        if (this.desde) params.append('desde', this.desde);
        if (this.hasta) params.append('hasta', this.hasta);

        const queryString = params.toString();
        const url = queryString ? `${baseUrl}?${queryString}` : baseUrl;

        window.open(url, '_blank');

        setTimeout(() => {
            this.exportandoExcel = false;
        }, 1500);
    },
};
