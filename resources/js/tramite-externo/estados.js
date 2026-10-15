/**
 * Resolución visual de estados semánticos para Trámite Externo (CEFX / RF-05 / RA-08).
 * Fuente única de verdad pura compartida con Trámite Interno.
 */

import { getEstadoBadgeClass as getSharedEstadoBadgeClass } from '../shared/estado-badge.js';

export function getEstadoBadgeClass(estadoId, estadoNombre) {
    return getSharedEstadoBadgeClass(estadoId, estadoNombre);
}

export function triggerToast(mensaje) {
    // Helper hook de notificación toast
}
