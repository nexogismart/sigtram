/**
 * Estado encapsulado de Trámite Interno (RF-04).
 */

class TramiteInternoState {
    constructor() {
        this.config = {
            csrf: '',
            areaId: 0,
            cargoId: 0,
            esAreaContabilidad: false,
            routes: {},
            titulos: {},
        };

        this.activeBandeja = 'entrada';
        this.datosActuales = [];
        this.tramiteSeleccionadoId = null;
        this.movimientosHistoricoActuales = [];
        this.historicoMaximizado = false;
        this.areasDerivarSeleccionadas = new Set();
        this.datosTransaccionalesCache = null;
        this.comprobantesCache = [];

        this.paginacionBandeja = {
            current_page: 1,
            per_page: 25,
            total: 0,
            last_page: 1,
            from: null,
            to: null,
        };

        this.uModalContext = {
            movimientoId: null,
            item: null,
            triggerEl: null,
            page: 1,
            perPage: 10,
            search: '',
            lastPage: 1,
            total: 0,
            abortController: null,
            cache: new Map(),
        };

        this.consultaAbortController = null;
    }

    init(config = {}) {
        this.config = { ...this.config, ...config };
    }

    getRoute(key) {
        return this.config.routes ? this.config.routes[key] || '' : '';
    }

    getTitulo(bandeja) {
        return (this.config.titulos && this.config.titulos[bandeja]) || bandeja;
    }

    getCsrf() {
        return this.config.csrf || '';
    }

    resetParticipantesCache() {
        this.uModalContext.cache.clear();
    }
}

export const state = new TramiteInternoState();
