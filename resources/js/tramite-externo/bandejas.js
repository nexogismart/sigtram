/**
 * Métodos de bandejas, filtros, conteos y paginación para Trámite Externo (RF-05).
 */

import { apiFetchConteos, apiFetchItems } from './api.js';
import { escapeHtml } from '../shared/safe-dom.js';

export const bandejasMixin = {
    triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3000);
    },

    toggleSelectAll() {
        if (this.selectedIds.length === this.items.length) {
            this.selectedIds = [];
        } else {
            this.selectedIds = this.items.map((i) => i.movimiento_id || i.cdgo_trmte_dcmnto);
        }
    },

    escapeRegex(str) {
        return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    },

    /**
     * Resalta de forma segura coincidencias de texto escapando primero el texto no confiable
     * para evitar cualquier riesgo de DOM XSS vía x-html (RF-05).
     * @param {string} text
     * @returns {string}
     */
    highlightMatch(text) {
        if (!text) return '';
        const safeText = escapeHtml(text);
        if (!this.descripcion || !this.descripcion.trim()) return safeText;
        try {
            const escapedQuery = this.escapeRegex(this.descripcion.trim());
            const regex = new RegExp(`(${escapedQuery})`, 'gi');
            return safeText.replace(regex, '<mark class="bg-yellow-200 text-yellow-900 rounded px-1 font-semibold">$1</mark>');
        } catch {
            return safeText;
        }
    },

    firstPage() {
        if (this.page > 1) {
            this.page = 1;
            this.fetchItems();
        }
    },

    prevPage() {
        if (this.page > 1) {
            this.page--;
            this.fetchItems();
        }
    },

    nextPage() {
        if (this.page < this.lastPage) {
            this.page++;
            this.fetchItems();
        }
    },

    lastPageNav() {
        if (this.page < this.lastPage) {
            this.page = this.lastPage;
            this.fetchItems();
        }
    },

    getRangeInfo() {
        if (this.loading) {
            return 'Cargando registros...';
        }
        const total = Number(this.total) || 0;
        if (total === 0) {
            return '0 registros encontrados';
        }
        const perPage = Number(this.perPage) || 25;
        const from = (this.page - 1) * perPage + 1;
        const to = Math.min(this.page * perPage, total);
        return `Mostrando ${from}–${to} de ${total} expedientes`;
    },

    cleanFilters() {
        this.criterio = 'expe_todo';
        this.descripcion = '';
        this.desde = '';
        this.hasta = '';
        this.page = 1;
        this.fetchItems();
    },

    async fetchConteos() {
        this.conteosLoading = true;
        this.conteosError = false;
        try {
            const res = await apiFetchConteos(this.routes);
            this.conteosLoading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.conteos = res.data.data || {};
                this.conteosError = false;
            } else {
                this.conteosError = true;
            }
        } catch (err) {
            this.conteosLoading = false;
            this.conteosError = true;
            console.error('Error al cargar conteos de trámite externo:', err);
        }
    },

    async fetchItems() {
        if (this.tableAbortController) {
            this.tableAbortController.abort();
        }
        this.tableAbortController = new AbortController();
        this.loading = true;
        this.tableError = null;
        this.selectedIds = [];
        this.selectedExpediente = null;

        const params = {
            bandeja: this.activeBandeja,
            criterio: this.criterio,
            descripcion: this.descripcion,
            desde: this.desde,
            hasta: this.hasta,
            page: this.page,
            per_page: this.perPage || 25,
        };

        try {
            const res = await apiFetchItems(this.routes, params, this.tableAbortController.signal);
            if (res.isAborted) return;

            this.loading = false;
            if (res.ok && res.data && res.data.status === 'success') {
                this.items = res.data.data || [];
                this.tableError = null;
                const pag = res.data.pagination || res.data.meta;
                if (pag) {
                    this.page = Number(pag.current_page || pag.page || this.page || 1);
                    this.lastPage = Number(pag.last_page || this.lastPage || 1);
                    this.total = Number(pag.total !== undefined ? pag.total : (this.items ? this.items.length : 0));
                }
            } else {
                const msg = res.message || (res.data && res.data.message) || 'Error al obtener registros';
                this.tableError = msg;
                this.triggerToast(`Error: ${msg}`);
            }
        } catch (err) {
            if (err && err.name === 'AbortError') return;
            this.loading = false;
            this.tableError = 'Error de conexión con el servidor';
            console.error(err);
            this.triggerToast('Error de conexión con el servidor');
        }
    },

    irANuevoTramite() {
        const destino = '/mod_gm/mod_tram_exte/index';
        if (window.parent && window.parent !== window && typeof window.parent.activarModuloPorUrl === 'function') {
            const ok = window.parent.activarModuloPorUrl(destino);
            if (ok) return;
        }
        window.location.href = destino;
    },
};
