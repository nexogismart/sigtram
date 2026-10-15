/**
 * Métodos de histórico, detalle y visualización de comprobantes para Trámite Externo (RF-05).
 */

import { apiFetchHistorial, apiFetchDetalle, apiFetchAuditoriaDescargas } from './api.js';

export const historicoMixin = {
    async showDetail(item) {
        if (!item) return;
        this.selectedExpediente = item;
        this.history = [];
        this.historyLoading = true;
        try {
            const res = await apiFetchHistorial(this.routes, item.cdgo_dto_trmte);
            this.historyLoading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.history = res.data.data || [];
            } else {
                this.triggerToast(`Error: ${res.message || 'No se pudo cargar el historial'}`);
            }
        } catch (err) {
            this.historyLoading = false;
            console.error(err);
            this.triggerToast('Error de conexión al cargar historial');
        }
    },

    getTituloHistorico() {
        if (!this.selectedExpediente) return '—';
        const num = String(this.selectedExpediente.nu_expe_todo || '').trim();
        const orig = String(this.selectedExpediente.orig_doc || '').trim().toUpperCase();

        if (orig === 'S' || num.startsWith('S-') || num.startsWith('S')) {
            return `DOCUMENTO SIMPLE ${num}`;
        }
        return `EXPEDIENTE ${num}`;
    },

    async showDetalle(item) {
        this.detalleData = null;
        this.modalDetalle = true;
        try {
            const res = await apiFetchDetalle(this.routes, item.cdgo_dto_trmte);
            if (res.ok && res.data && res.data.status === 'success') {
                this.detalleData = res.data.data;
            } else {
                this.triggerToast(`Error: ${res.message || 'No se pudo obtener el detalle'}`);
                this.modalDetalle = false;
            }
        } catch (err) {
            console.error(err);
            this.triggerToast('Error de conexión al obtener detalles');
            this.modalDetalle = false;
        }
    },



    abrirModalAuditoria(hist, triggerEl) {
        if (!hist) return;
        this.selectedMovimientoAudit = hist;
        this.auditTriggerEl = triggerEl || null;
        this.auditData = [];
        this.auditPagination = { current_page: 1, last_page: 1, total: 0, per_page: 10 };
        this.auditError = false;
        this.auditErrorMsg = '';
        this.modalAuditoria = true;
        this.fetchAuditoriaDescargas(1);
    },

    cerrarModalAuditoria() {
        this.modalAuditoria = false;
        if (this.auditAbortController) {
            this.auditAbortController.abort();
            this.auditAbortController = null;
        }
        if (this.auditTriggerEl && typeof this.auditTriggerEl.focus === 'function') {
            this.$nextTick(() => {
                this.auditTriggerEl.focus();
            });
        }
    },

    changeAuditPage(delta) {
        const target = (this.auditPagination.current_page || 1) + delta;
        if (target >= 1 && target <= (this.auditPagination.last_page || 1)) {
            this.fetchAuditoriaDescargas(target);
        }
    },

    async fetchAuditoriaDescargas(page = 1) {
        if (!this.selectedMovimientoAudit) return;
        const movId = this.selectedMovimientoAudit.cdgo_trmte_dcmnto;
        if (!movId) return;

        if (this.auditAbortController) {
            this.auditAbortController.abort();
        }
        this.auditAbortController = new AbortController();
        this.auditLoading = true;
        this.auditError = false;
        this.auditErrorMsg = '';

        try {
            const res = await apiFetchAuditoriaDescargas(
                this.routes,
                { movimientoId: movId, page, perPage: 10 },
                this.auditAbortController.signal
            );

            if (res.isAborted) return;

            this.auditLoading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.auditData = res.data.data || [];
                this.auditPagination = res.data.pagination || { current_page: page, last_page: 1, total: this.auditData.length, per_page: 10 };
                this.auditError = false;
                this.auditErrorMsg = '';
            } else {
                this.auditData = [];
                this.auditPagination = { current_page: 1, last_page: 1, total: 0, per_page: 10 };
                this.auditError = true;
                this.auditErrorMsg = res.data?.message || res.message || 'No se pudieron cargar los registros de auditoría.';
            }
        } catch (err) {
            if (err && err.name === 'AbortError') return;
            this.auditLoading = false;
            this.auditData = [];
            this.auditPagination = { current_page: 1, last_page: 1, total: 0, per_page: 10 };
            this.auditError = true;
            this.auditErrorMsg = 'Error de conexión al cargar registros de auditoría.';
        }
    },

    openAdjunto(url) {
        if (!url) return;
        const esRutaNueva = String(url).includes('/');
        const qs = esRutaNueva
            ? `path=${encodeURIComponent(url)}`
            : `nom_archivo=${encodeURIComponent(url)}`;
        window.open(`/mod_gm/mod_tram_exte/descargar?${qs}`, '_blank');
    },

    descargarConsolidado(expedienteId) {
        if (!expedienteId) return;
        window.open(`/mod_gm/mod_tram_exte/expedientes/${expedienteId}/descargar-consolidado`, '_blank');
    },
};

