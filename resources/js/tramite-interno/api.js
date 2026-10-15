/**
 * Servicios API de Trámite Interno (RF-04).
 * Centraliza las peticiones de red utilizando el cliente HTTP compartido.
 */

import { httpClient } from '../shared/http-client.js';
import { state } from './state.js';

export async function apiCargarConteos() {
    const url = state.getRoute('conteos');
    if (!url) return null;
    const res = await httpClient.get(url);
    if (res.ok && res.data && res.data.status === 'success') {
        return res.data.data;
    }
    return null;
}

export async function apiConsultarTabla(params, signal) {
    const url = state.getRoute('tabla');
    return await httpClient.post(url, params, { signal });
}

export async function apiCargarHistorial(payload) {
    if (!payload || typeof payload !== 'object' || (!payload.documento_id && !payload.movimiento_id)) {
        throw new TypeError('apiCargarHistorial requiere un objeto explícito { documento_id } o { movimiento_id }');
    }
    const url = state.getRoute('historial');
    return await httpClient.post(url, payload);
}

export async function apiRecepcionar(payload) {
    const url = state.getRoute('recepcionar');
    return await httpClient.post(url, payload);
}

export async function apiAsignar(formData) {
    const url = state.getRoute('asignar');
    return await httpClient.post(url, formData);
}

export async function apiDerivar(formData) {
    const url = state.getRoute('derivar');
    return await httpClient.post(url, formData);
}

export async function apiFinalizar(formData) {
    const url = state.getRoute('finalizar');
    return await httpClient.post(url, formData);
}

export async function apiDevolver(formData) {
    const url = state.getRoute('devolver');
    return await httpClient.post(url, formData);
}

export async function apiReiterar(formData) {
    const url = state.getRoute('reiterar');
    return await httpClient.post(url, formData);
}

export async function apiCargarDatosTransaccionales() {
    if (state.datosTransaccionalesCache) {
        return state.datosTransaccionalesCache;
    }
    const url = state.getRoute('datosTransaccionales');
    if (!url) return null;
    const res = await httpClient.get(url);
    if (res.ok && res.data && res.data.status === 'success') {
        state.datosTransaccionalesCache = res.data.data;
        return state.datosTransaccionalesCache;
    }
    return null;
}

export async function apiObtenerCorrelativoInterno(tipo) {
    const base = state.getRoute('correlativoInterno');
    if (!base) return null;
    const url = `${base}?cdgo_tpo_trmte=${encodeURIComponent(tipo)}`;
    const res = await httpClient.get(url);
    if (res.ok && res.data && res.data.status === 'success') {
        return res.data.data;
    }
    return null;
}

export async function apiCargarParticipantes(movimientoId, page, perPage, search, signal) {
    const id = parseInt(movimientoId, 10);
    if (!id || id <= 0) return null;
    const base = state.getRoute('participantes');
    if (!base) return null;
    const url = `${base}?movimiento_id=${encodeURIComponent(id)}&page=${page}&per_page=${perPage}&search=${encodeURIComponent(search || '')}`;
    return await httpClient.get(url, { signal });
}

export async function apiCargarDetalleDoc(id) {
    const base = state.getRoute('detalleDocumento');
    return await httpClient.get(`${base}${id}`);
}

export async function apiCargarVoucher(idMov) {
    const base = state.getRoute('voucher');
    return await httpClient.get(`${base}${idMov}`);
}

export async function apiReemplazarFirmado(formData) {
    const url = state.getRoute('reemplazarFirmado');
    return await httpClient.post(url, formData);
}

export async function apiCargarComprobantesPago() {
    const url = state.getRoute('comprobantesPago');
    if (!url) return null;
    return await httpClient.get(url);
}
