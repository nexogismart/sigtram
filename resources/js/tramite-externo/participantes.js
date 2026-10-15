/**
 * Métodos de Modal U y participantes para Trámite Externo (SPEC 10 / CEF-10 / CEF-11 / RF-05).
 */

import { apiFetchParticipantesU } from './api.js';

export const participantesMixin = {
    openModalUsuarios(hist, triggerEl) {
        if (!hist) return;
        const rawId = hist.movimiento_id || hist.cdgo_trmte_dcmnto;
        const movId = parseInt(rawId, 10);
        if (!movId || movId <= 0) return;
        this.selectedMovimientoU = hist;
        this.uOrigenEjecutor = null;
        this.uTriggerEl = triggerEl || null;
        this.uSearch = '';
        this.uPage = 1;
        this.uData = [];
        this.uError = false;
        this.uErrorMsg = '';
        this.uPagination = { current_page: 1, last_page: 1, total: 0, per_page: 10 };
        this.modalUsuarios = true;
        this.fetchParticipantesU(1);
    },

    closeModalUsuarios() {
        this.modalUsuarios = false;
        if (this.uAbortController) {
            this.uAbortController.abort();
            this.uAbortController = null;
        }
        if (this.uTriggerEl && typeof this.uTriggerEl.focus === 'function') {
            this.$nextTick(() => {
                this.uTriggerEl.focus();
            });
        }
    },

    changeUPage(delta) {
        const target = (this.uPagination.current_page || 1) + delta;
        if (target >= 1 && target <= (this.uPagination.last_page || 1)) {
            this.fetchParticipantesU(target);
        }
    },

    async fetchParticipantesU(page = 1) {
        if (!this.selectedMovimientoU) return;
        const rawId = this.selectedMovimientoU.movimiento_id || this.selectedMovimientoU.cdgo_trmte_dcmnto;
        const movId = parseInt(rawId, 10);
        if (!movId || movId <= 0) return;

        this.uPage = page;
        const search = (this.uSearch || '').trim();
        const cacheKey = `${movId}_${page}_${search}`;

        if (this.uCache.has(cacheKey)) {
            const cached = this.uCache.get(cacheKey);
            this.uData = cached.data || [];
            this.uPagination = cached.pagination || { current_page: page, last_page: 1, total: this.uData.length, per_page: 10 };
            if (cached.origen_ejecutor) {
                this.uOrigenEjecutor = cached.origen_ejecutor;
            }
            this.uError = false;
            this.uErrorMsg = '';
            return;
        }

        if (this.uAbortController) {
            this.uAbortController.abort();
        }
        this.uAbortController = new AbortController();
        this.uLoading = true;
        this.uError = false;
        this.uErrorMsg = '';

        try {
            const res = await apiFetchParticipantesU(
                this.routes,
                { movimientoId: movId, page, perPage: 10, search },
                this.uAbortController.signal
            );

            if (res.isAborted) return;

            this.uLoading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.uData = res.data.data || [];
                this.uPagination = res.data.pagination || { current_page: page, last_page: 1, total: this.uData.length, per_page: 10 };
                if (res.data.origen_ejecutor) {
                    this.uOrigenEjecutor = res.data.origen_ejecutor;
                }
                this.uCache.set(cacheKey, {
                    data: this.uData,
                    pagination: this.uPagination,
                    origen_ejecutor: this.uOrigenEjecutor,
                });
                this.uError = false;
            } else {
                this.uData = [];
                this.uPagination = { current_page: 1, last_page: 1, total: 0, per_page: 10 };
                this.uOrigenEjecutor = null;
                this.uError = true;
                this.uErrorMsg = res.data?.message || res.message || 'No se pudieron obtener participantes';
            }
        } catch (err) {
            if (err && err.name === 'AbortError') return;
            this.uLoading = false;
            this.uData = [];
            this.uPagination = { current_page: 1, last_page: 1, total: 0, per_page: 10 };
            this.uOrigenEjecutor = null;
            this.uError = true;
            this.uErrorMsg = 'Error de conexión al cargar participantes';
            console.error('Error al obtener participantes:', err);
        }
    },
};
