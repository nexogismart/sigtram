/**
 * Servicios de red centralizados para Trámite Externo (RF-05).
 */

import { httpClient } from '../shared/http-client.js';

export async function apiFetchConteos(routes) {
    const url = routes.conteos;
    return await httpClient.get(url);
}

export async function apiFetchItems(routes, params, signal) {
    const url = routes.tabla;
    return await httpClient.post(url, params, { signal });
}

export async function apiFetchHistorial(routes, id) {
    const url = routes.historial;
    return await httpClient.post(url, { id });
}

export async function apiFetchDetalle(routes, id) {
    const url = routes.detalle;
    return await httpClient.post(url, { id });
}

export async function apiFetchParticipantesU(routes, { movimientoId, page = 1, perPage = 10, search = '' }, signal) {
    const id = parseInt(movimientoId, 10);
    if (!id || id <= 0) return null;
    const base = routes.participantes;
    if (!base) return null;
    const url = new URL(base, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
    url.searchParams.set('movimiento_id', String(id));
    url.searchParams.set('page', String(page));
    url.searchParams.set('per_page', String(perPage));
    if (search) {
        url.searchParams.set('search', search);
    }
    return await httpClient.get(url.toString(), { signal });
}

export async function apiFetchSemilla(routes, docTipo) {
    const url = routes.semilla;
    return await httpClient.post(url, { doc_tipo: docTipo });
}

export async function apiFetchSolicitudVideo(routes, id) {
    const url = routes.solicitudVideo;
    return await httpClient.post(url, { id });
}

export async function apiSubmitRecepcionar(routes, payload, options = {}) {
    const url = routes.recepcionar;
    return await httpClient.post(url, payload, options);
}

export async function apiSubmitDerivar(routes, formData, options = {}) {
    const url = routes.derivar;
    return await httpClient.post(url, formData, options);
}

export async function apiSubmitAsignar(routes, formData, options = {}) {
    const url = routes.asignar;
    return await httpClient.post(url, formData, options);
}

export async function apiSubmitEnviar(routes, formData, options = {}) {
    const url = routes.enviar;
    return await httpClient.post(url, formData, options);
}

export async function apiSubmitFinalizar(routes, formData, options = {}) {
    const url = routes.finalizar;
    return await httpClient.post(url, formData, options);
}

export async function apiSubmitDevolver(routes, payload, options = {}) {
    const url = routes.devolver;
    return await httpClient.post(url, payload, options);
}

export async function apiSubmitArchivar(routes, payload, options = {}) {
    const url = routes.archivar;
    return await httpClient.post(url, payload, options);
}

export async function apiSubmitPrestar(routes, payload, options = {}) {
    const url = routes.prestar;
    return await httpClient.post(url, payload, options);
}

export async function apiSubmitRetornarPrestamo(routes, payload, options = {}) {
    const url = routes.retornarPrestamo;
    return await httpClient.post(url, payload, options);
}

export async function apiSubmitRedirigir(routes, payload, options = {}) {
    const url = routes.redirigir;
    return await httpClient.post(url, payload, options);
}

export async function apiFetchAuditoriaDescargas(routes, { movimientoId, page = 1, perPage = 10 }, signal) {
    const base = routes.auditoriaDescargas || '/mod_gm/mod_tram_exte/segi_expedi/auditoria-descargas';
    const url = new URL(base, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
    url.searchParams.set('movimiento_id', String(movimientoId));
    url.searchParams.set('page', String(page));
    url.searchParams.set('per_page', String(perPage));
    return await httpClient.get(url.toString(), { signal });
}

