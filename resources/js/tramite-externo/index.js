/**
 * Componente Alpine modularizado para Trámite Externo (RF-05).
 * Sustituye el God component de 900 líneas del Blade por una composición modular.
 */

import { readRouteConfig } from '../shared/route-config.js';
import { createInitialState } from './state.js';
import { getEstadoBadgeClass } from './estados.js';
import { bandejasMixin } from './bandejas.js';
import { historicoMixin } from './historico.js';
import { participantesMixin } from './participantes.js';
import { accionesMixin } from './acciones.js';

/**
 * Crea la definición del componente Alpine `correoExternoApp`.
 * @param {object} [overrideConfig]
 * @returns {object}
 */
export function createCorreoExternoComponent(overrideConfig) {
    const domConfig = readRouteConfig('#tramite-externo-app', 'data-config');
    const finalConfig = { ...domConfig, ...(overrideConfig || {}) };
    const baseState = createInitialState(finalConfig);

    return {
        ...baseState,
        ...bandejasMixin,
        ...historicoMixin,
        ...participantesMixin,
        ...accionesMixin,

        getEstadoBadgeClass,

        get rangeInfo() {
            return this.getRangeInfo();
        },

        init() {
            this.fetchConteos();
            this.fetchItems();
            this.$watch('activeBandeja', () => {
                this.page = 1;
                this.fetchItems();
            });
        },
    };
}
