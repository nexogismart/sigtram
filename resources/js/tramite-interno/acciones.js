/**
 * Acciones transaccionales y modales de Trámite Interno (RF-04).
 * Recepcionar, Asignar, Derivar, Finalizar, Devolver, Reiterar, Imprimir, Voucher, Videos, etc.
 */

import { state } from './state.js';
import {
    apiRecepcionar,
    apiAsignar,
    apiDerivar,
    apiFinalizar,
    apiDevolver,
    apiReiterar,
    apiCargarDatosTransaccionales,
    apiObtenerCorrelativoInterno,
    apiCargarDetalleDoc,
    apiCargarVoucher,
    apiReemplazarFirmado,
    apiCargarComprobantesPago,
} from './api.js';
import { seleccionados } from './bandejas.js';
import { createSafeElement, clearChildren } from '../shared/safe-dom.js';

export function ahoraISO() {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - offset).toISOString().slice(0, 16);
}

export function fechaHoraActualLocal() {
    return ahoraISO();
}

export function mostrarAviso(titulo, mensaje) {
    const tit = document.getElementById('aviso-titulo');
    if (tit) tit.textContent = titulo;
    const msg = document.getElementById('aviso-mensaje');
    if (msg) msg.textContent = mensaje;
    const modal = document.getElementById('modal-aviso');
    if (modal) modal.classList.remove('hidden');
}

export function cerrarAviso() {
    const modal = document.getElementById('modal-aviso');
    if (modal) modal.classList.add('hidden');
}

// -------------------------------------------------------------
// RECEPCIONAR
// -------------------------------------------------------------
export function accionRecepcionar() {
    const ids = seleccionados();
    if (!ids.length) {
        mostrarAviso('Atención', 'Debe seleccionar al menos un trámite para recepcionar.');
        return;
    }
    const cantEl = document.getElementById('recep-cant');
    if (cantEl) cantEl.textContent = ids.length;
    const fecEl = document.getElementById('recep-fecha');
    if (fecEl) fecEl.value = ahoraISO();
    const obsEl = document.getElementById('recep-obs');
    if (obsEl) obsEl.value = 'SIN NOVEDADES PARA RECEPCIONAR';

    const modal = document.getElementById('modal-recepcionar');
    if (modal) modal.classList.remove('hidden');
}

export function cerrarModalRecepcionar() {
    const modal = document.getElementById('modal-recepcionar');
    if (modal) modal.classList.add('hidden');
}

export async function submitRecepcionar(onSuccess) {
    const ids = seleccionados();
    if (!ids.length) {
        mostrarAviso('Atención', 'Debe seleccionar al menos un trámite.');
        cerrarModalRecepcionar();
        return;
    }

    const fecha = document.getElementById('recep-fecha')?.value || '';
    const observaciones = document.getElementById('recep-obs')?.value || '';
    const btn = document.getElementById('btn-submit-recep');

    if (btn) {
        btn.disabled = true;
        btn.textContent = ' Recepcionando...';
    }

    try {
        const res = await apiRecepcionar({ ids, fecha, observaciones });
        if (res.ok && res.data && res.data.status === 'success') {
            mostrarAviso('Éxito', res.data.message || 'Trámite(s) recepcionado(s) correctamente.');
            cerrarModalRecepcionar();
            if (typeof onSuccess === 'function') onSuccess();
        } else {
            mostrarAviso('Error', res.message || 'Error al recepcionar trámite(s).');
        }
    } catch (err) {
        console.error(err);
        mostrarAviso('Error de Conexión', 'No se pudo comunicar con el servidor para la recepción.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Confirmar Recepción';
        }
    }
}

// -------------------------------------------------------------
// ASIGNAR
// -------------------------------------------------------------
export async function accionAsignar() {
    const ids = seleccionados();
    if (!ids.length) {
        mostrarAviso('Atención', 'Debe seleccionar al menos un trámite para asignar.');
        return;
    }
    if (ids.length > 200) {
        mostrarAviso('Límite de seguridad', 'Por seguridad, procese hasta un máximo de 200 expedientes por lote.');
        return;
    }

    const sub = document.getElementById('asignar-subtitulo');
    if (sub) sub.textContent = `${ids.length} expediente(s) seleccionado(s)`;
    const fec = document.getElementById('asignar-fecha');
    if (fec) fec.value = fechaHoraActualLocal();
    const obs = document.getElementById('asignar-observaciones');
    if (obs) obs.value = 'Sin novedades para asignar';

    const chkCrear = document.getElementById('asignar-chk-crear-doc');
    if (chkCrear) chkCrear.checked = false;
    const secDoc = document.getElementById('asignar-subseccion-doc');
    if (secDoc) secDoc.classList.add('hidden');

    const dt = await apiCargarDatosTransaccionales();
    if (dt) {
        const selCargo = document.getElementById('asignar-cargo');
        if (selCargo) {
            clearChildren(selCargo);
            selCargo.appendChild(createSafeElement('option', { value: '', textContent: '-- Seleccione Colaborador / Cargo --' }));
            (dt.cargosArea || []).forEach((c) => {
                selCargo.appendChild(createSafeElement('option', { value: c.cdgo_crgo, textContent: c.texto }));
            });
        }

        const selTipo = document.getElementById('asignar-tipo-doc');
        if (selTipo) {
            clearChildren(selTipo);
            selTipo.appendChild(createSafeElement('option', { value: '', textContent: '-- Seleccione Tipo --' }));
            (dt.tiposDocumento || []).forEach((t) => {
                selTipo.appendChild(createSafeElement('option', { value: t.cdgo_tpo_trmte, textContent: t.de_tpo_trmte }));
            });
        }
    }

    const modal = document.getElementById('modal-asignar');
    if (modal) modal.classList.remove('hidden');
}

export function cerrarModalAsignar() {
    const modal = document.getElementById('modal-asignar');
    if (modal) modal.classList.add('hidden');
}

export async function enviarAsignar(e, onSuccess) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    const ids = seleccionados();
    const cargo = document.getElementById('asignar-cargo')?.value;
    if (!cargo) {
        mostrarAviso('Atención', 'Debe seleccionar el colaborador o cargo destino.');
        return;
    }

    const btn = document.getElementById('btn-submit-asignar');
    if (btn) {
        btn.disabled = true;
        btn.textContent = ' Asignando...';
    }

    const formData = new FormData();
    ids.forEach((id) => formData.append('ids[]', id));
    formData.append('cdgo_crgo_en', cargo);
    formData.append('fecha', document.getElementById('asignar-fecha')?.value || '');
    formData.append('observaciones', document.getElementById('asignar-observaciones')?.value || '');

    const crearDoc = document.getElementById('asignar-chk-crear-doc')?.checked;
    formData.append('crear_tramite_interno', crearDoc ? 1 : 0);
    if (crearDoc) {
        formData.append('cdgo_tpo_trmte', document.getElementById('asignar-tipo-doc')?.value || '');
        formData.append('txt_nu_smlla_intrno', document.getElementById('asignar-nu-smlla')?.value || '');
        formData.append('in_envo_fsco', document.getElementById('asignar-chk-fisico')?.checked ? 1 : 0);
        const f1 = document.getElementById('asignar-pdf-firmado')?.files?.[0];
        if (f1) formData.append('fle_adjunto1', f1);
    }

    const f2 = document.getElementById('asignar-adjunto2')?.files?.[0];
    if (f2) formData.append('fle_adjunto2', f2);
    const f3 = document.getElementById('asignar-adjunto3')?.files?.[0];
    if (f3) formData.append('fle_adjunto3', f3);
    const f4 = document.getElementById('asignar-adjunto4')?.files?.[0];
    if (f4) formData.append('fle_adjunto4', f4);

    try {
        const res = await apiAsignar(formData);
        if (res.ok && res.data && res.data.status === 'success') {
            cerrarModalAsignar();
            mostrarAviso('Asignación Exitosa', res.data.message || 'Expedientes asignados correctamente.');
            if (typeof onSuccess === 'function') onSuccess();
        } else {
            mostrarAviso('Error', res.message || 'No se pudo realizar la asignación.');
        }
    } catch (err) {
        console.error(err);
        mostrarAviso('Error de Servidor', 'Ocurrió un error al procesar la asignación.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Asignar Trámite(s)';
        }
    }
}

// -------------------------------------------------------------
// DERIVAR
// -------------------------------------------------------------
export async function accionDerivar() {
    const ids = seleccionados();
    if (!ids.length) {
        mostrarAviso('Atención', 'Debe seleccionar al menos un trámite para derivar.');
        return;
    }
    if (ids.length > 200) {
        mostrarAviso('Límite de seguridad', 'Por seguridad, procese hasta un máximo de 200 expedientes por lote.');
        return;
    }

    const sub = document.getElementById('derivar-subtitulo');
    if (sub) sub.textContent = `${ids.length} expediente(s) seleccionado(s)`;
    const fec = document.getElementById('derivar-fecha');
    if (fec) fec.value = fechaHoraActualLocal();
    const obs = document.getElementById('derivar-observaciones');
    if (obs) obs.value = 'Sin novedades para enviar';

    const chkCrear = document.getElementById('derivar-chk-crear-doc');
    if (chkCrear) chkCrear.checked = false;
    const secDoc = document.getElementById('derivar-subseccion-doc');
    if (secDoc) secDoc.classList.add('hidden');

    state.areasDerivarSeleccionadas.clear();

    const dt = await apiCargarDatosTransaccionales();
    if (dt) {
        renderListaAreasDerivar(dt.areasDestino || []);
        const selTipo = document.getElementById('derivar-tipo-doc');
        if (selTipo) {
            clearChildren(selTipo);
            selTipo.appendChild(createSafeElement('option', { value: '', textContent: '-- Seleccione Tipo --' }));
            (dt.tiposDocumento || []).forEach((t) => {
                selTipo.appendChild(createSafeElement('option', { value: t.cdgo_tpo_trmte, textContent: t.de_tpo_trmte }));
            });
        }
    }

    actualizarContadorAreasDerivar();
    const modal = document.getElementById('modal-derivar');
    if (modal) modal.classList.remove('hidden');
}

export function renderListaAreasDerivar(areas, filtro = '') {
    const cont = document.getElementById('derivar-lista-areas');
    if (!cont) return;
    clearChildren(cont);

    const f = filtro.toLowerCase().trim();
    const filtradas = (areas || []).filter((a) => {
        if (!f) return true;
        return (a.de_area && a.de_area.toLowerCase().includes(f)) ||
               (a.dc_area && a.dc_area.toLowerCase().includes(f));
    });

    if (!filtradas.length) {
        cont.appendChild(createSafeElement('p', {
            className: 'text-xs text-gray-400 italic p-3 text-center',
            textContent: 'No se encontraron áreas coincidentes.',
        }));
        return;
    }

    const fragment = document.createDocumentFragment();

    filtradas.forEach((a) => {
        const strId = String(a.cdgo_area);
        const lbl = createSafeElement('label', {
            className: 'flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded-lg text-xs',
        });
        const chk = createSafeElement('input', {
            className: 'rounded border-gray-300 text-blue-600 focus:ring-blue-500',
            attributes: {
                type: 'checkbox',
                value: strId,
            },
            dataset: { action: 'toggle-area-derivar', id: strId },
            checked: state.areasDerivarSeleccionadas.has(strId),
        });

        lbl.appendChild(chk);
        lbl.appendChild(createSafeElement('span', {
            className: 'font-bold text-slate-800',
            textContent: a.dc_area || a.de_area || '',
        }));
        lbl.appendChild(createSafeElement('span', {
            className: 'text-[10px] text-gray-400 truncate',
            textContent: a.de_area || '',
        }));

        fragment.appendChild(lbl);
    });

    cont.appendChild(fragment);
}

export function filtrarAreasDerivar(val) {
    if (state.datosTransaccionalesCache && state.datosTransaccionalesCache.areasDestino) {
        renderListaAreasDerivar(state.datosTransaccionalesCache.areasDestino, val);
    }
}

export function toggleAreaDerivar(id) {
    const strId = String(id);
    if (state.areasDerivarSeleccionadas.has(strId)) {
        state.areasDerivarSeleccionadas.delete(strId);
    } else {
        state.areasDerivarSeleccionadas.add(strId);
    }
    actualizarContadorAreasDerivar();
}

export function actualizarContadorAreasDerivar() {
    const badge = document.getElementById('derivar-contador-areas');
    if (badge) {
        badge.textContent = `${state.areasDerivarSeleccionadas.size} seleccionada(s)`;
    }
}

export function cerrarModalDerivar() {
    const modal = document.getElementById('modal-derivar');
    if (modal) modal.classList.add('hidden');
}

export async function enviarDerivar(e, onSuccess) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    const ids = seleccionados();
    if (!state.areasDerivarSeleccionadas.size) {
        mostrarAviso('Atención', 'Debe seleccionar al menos un área de destino.');
        return;
    }

    const btn = document.getElementById('btn-submit-derivar');
    if (btn) {
        btn.disabled = true;
        btn.textContent = ' Derivando...';
    }

    const formData = new FormData();
    ids.forEach((id) => formData.append('ids[]', id));
    Array.from(state.areasDerivarSeleccionadas).forEach((aId) => formData.append('sel_areas[]', aId));
    formData.append('fecha', document.getElementById('derivar-fecha')?.value || '');
    formData.append('observaciones', document.getElementById('derivar-observaciones')?.value || '');

    const crearDoc = document.getElementById('derivar-chk-crear-doc')?.checked;
    formData.append('crear_tramite_interno', crearDoc ? 1 : 0);
    if (crearDoc) {
        formData.append('cdgo_tpo_trmte', document.getElementById('derivar-tipo-doc')?.value || '');
        formData.append('txt_nu_smlla_intrno', document.getElementById('derivar-nu-smlla')?.value || '');
        formData.append('in_envo_fsco', document.getElementById('derivar-chk-fisico')?.checked ? 1 : 0);
        const f1 = document.getElementById('derivar-pdf-firmado')?.files?.[0];
        if (f1) formData.append('fle_adjunto1', f1);
    }

    const f2 = document.getElementById('derivar-adjunto2')?.files?.[0];
    if (f2) formData.append('fle_adjunto2', f2);
    const f3 = document.getElementById('derivar-adjunto3')?.files?.[0];
    if (f3) formData.append('fle_adjunto3', f3);
    const f4 = document.getElementById('derivar-adjunto4')?.files?.[0];
    if (f4) formData.append('fle_adjunto4', f4);

    try {
        const res = await apiDerivar(formData);
        if (res.ok && res.data && res.data.status === 'success') {
            cerrarModalDerivar();
            mostrarAviso('Derivación Exitosa', res.data.message || 'Expedientes derivados correctamente.');
            if (typeof onSuccess === 'function') onSuccess();
        } else {
            mostrarAviso('Error', res.message || 'No se pudo derivar los expedientes.');
        }
    } catch (err) {
        console.error(err);
        mostrarAviso('Error de Servidor', 'Ocurrió un error al procesar la derivación.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Derivar Trámite(s)';
        }
    }
}

// -------------------------------------------------------------
// FINALIZAR
// -------------------------------------------------------------
export function accionFinalizar() {
    const ids = seleccionados();
    if (!ids.length) {
        mostrarAviso('Atención', 'Debe seleccionar al menos un trámite para finalizar.');
        return;
    }

    const sub = document.getElementById('finalizar-subtitulo');
    if (sub) sub.textContent = `${ids.length} expediente(s) seleccionado(s)`;
    const fec = document.getElementById('finalizar-fecha');
    if (fec) fec.value = fechaHoraActualLocal();
    const obs = document.getElementById('finalizar-observaciones');
    if (obs) obs.value = 'Sin novedades para finalizar';
    const adj = document.getElementById('finalizar-adjunto');
    if (adj) adj.value = '';

    const modal = document.getElementById('modal-finalizar');
    if (modal) modal.classList.remove('hidden');
}

export function cerrarModalFinalizar() {
    const modal = document.getElementById('modal-finalizar');
    if (modal) modal.classList.add('hidden');
}

export async function enviarFinalizar(e, onSuccess) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    const ids = seleccionados();
    const btn = document.getElementById('btn-submit-finalizar');
    if (btn) {
        btn.disabled = true;
        btn.textContent = ' Finalizando...';
    }

    const formData = new FormData();
    ids.forEach((id) => formData.append('ids[]', id));
    formData.append('fecha', document.getElementById('finalizar-fecha')?.value || '');
    formData.append('observaciones', document.getElementById('finalizar-observaciones')?.value || '');
    const f = document.getElementById('finalizar-adjunto')?.files?.[0];
    if (f) formData.append('fle_adjunto1', f);

    try {
        const res = await apiFinalizar(formData);
        if (res.ok && res.data && res.data.status === 'success') {
            cerrarModalFinalizar();
            mostrarAviso('Finalización Exitosa', res.data.message || 'Expedientes finalizados.');
            if (typeof onSuccess === 'function') onSuccess();
        } else {
            mostrarAviso('Error', res.message || 'No se pudo finalizar.');
        }
    } catch (err) {
        console.error(err);
        mostrarAviso('Error', 'Ocurrió un error al finalizar.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Finalizar Trámite(s)';
        }
    }
}

// -------------------------------------------------------------
// DEVOLVER
// -------------------------------------------------------------
export function accionDevolver() {
    const ids = seleccionados();
    if (!ids.length) {
        mostrarAviso('Atención', 'Debe seleccionar al menos un trámite para devolver.');
        return;
    }

    const sub = document.getElementById('devolver-subtitulo');
    if (sub) sub.textContent = `${ids.length} expediente(s) seleccionado(s)`;
    const fec = document.getElementById('devolver-fecha');
    if (fec) fec.value = fechaHoraActualLocal();
    const obs = document.getElementById('devolver-observaciones');
    if (obs) obs.value = 'Sin novedades para devolver';

    const modal = document.getElementById('modal-devolver');
    if (modal) modal.classList.remove('hidden');
}

export function cerrarModalDevolver() {
    const modal = document.getElementById('modal-devolver');
    if (modal) modal.classList.add('hidden');
}

export async function enviarDevolver(e, onSuccess) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    const ids = seleccionados();
    const btn = document.getElementById('btn-submit-devolver');
    if (btn) {
        btn.disabled = true;
        btn.textContent = ' Devolviendo...';
    }

    const formData = new FormData();
    ids.forEach((id) => formData.append('ids[]', id));
    formData.append('fecha', document.getElementById('devolver-fecha')?.value || '');
    formData.append('observaciones', document.getElementById('devolver-observaciones')?.value || '');

    try {
        const res = await apiDevolver(formData);
        if (res.ok && res.data && res.data.status === 'success') {
            cerrarModalDevolver();
            mostrarAviso('Devolución Exitosa', res.data.message || 'Expedientes devueltos con éxito.');
            if (typeof onSuccess === 'function') onSuccess();
        } else {
            mostrarAviso('Error', res.message || 'No se pudo devolver.');
        }
    } catch (err) {
        console.error(err);
        mostrarAviso('Error', 'Ocurrió un error al devolver.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Devolver Trámite(s)';
        }
    }
}

// -------------------------------------------------------------
// REITERAR
// -------------------------------------------------------------
export function accionReiterar() {
    const ids = seleccionados();
    if (!ids.length) {
        mostrarAviso('Atención', 'Debe seleccionar al menos un trámite para reiterar.');
        return;
    }

    const sub = document.getElementById('reiterar-subtitulo');
    if (sub) sub.textContent = `${ids.length} expediente(s) seleccionado(s)`;
    const fec = document.getElementById('reiterar-fecha');
    if (fec) fec.value = fechaHoraActualLocal();
    const obs = document.getElementById('reiterar-observaciones');
    if (obs) obs.value = 'Sin novedades para reiterar';
    const adj = document.getElementById('reiterar-adjunto');
    if (adj) adj.value = '';

    const modal = document.getElementById('modal-reiterar');
    if (modal) modal.classList.remove('hidden');
}

export function cerrarModalReiterar() {
    const modal = document.getElementById('modal-reiterar');
    if (modal) modal.classList.add('hidden');
}

export async function enviarReiterar(e, onSuccess) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    const ids = seleccionados();
    const btn = document.getElementById('btn-submit-reiterar');
    if (btn) {
        btn.disabled = true;
        btn.textContent = ' Reiterando...';
    }

    const formData = new FormData();
    ids.forEach((id) => formData.append('ids[]', id));
    formData.append('fecha', document.getElementById('reiterar-fecha')?.value || '');
    formData.append('observaciones', document.getElementById('reiterar-observaciones')?.value || '');
    const f = document.getElementById('reiterar-adjunto')?.files?.[0];
    if (f) formData.append('fle_adjunto1', f);

    try {
        const res = await apiReiterar(formData);
        if (res.ok && res.data && res.data.status === 'success') {
            cerrarModalReiterar();
            mostrarAviso('Reiteración Exitosa', res.data.message || 'Expediente(s) reiterado(s) con éxito.');
            if (typeof onSuccess === 'function') onSuccess();
        } else {
            mostrarAviso('Error', res.message || 'No se pudo reiterar.');
        }
    } catch (err) {
        console.error(err);
        mostrarAviso('Error', 'Ocurrió un error al reiterar.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Reiterar Trámite(s)';
        }
    }
}

// -------------------------------------------------------------
// HELPERS MODALES: CREAR DOC Y CORRELATIVO
// -------------------------------------------------------------
export function toggleCrearDoc(prefijo) {
    const chk = document.getElementById(`${prefijo}-chk-crear-doc`);
    const sub = document.getElementById(`${prefijo}-subseccion-doc`);
    if (chk && sub) {
        if (chk.checked) {
            sub.classList.remove('hidden');
        } else {
            sub.classList.add('hidden');
        }
    }
}

export async function cambioTipoDoc(prefijo) {
    const sel = document.getElementById(`${prefijo}-tipo-doc`);
    const val = sel ? sel.value : null;
    if (!val) return;

    try {
        const data = await apiObtenerCorrelativoInterno(val);
        if (data && data.nu_smlla_intrno) {
            const inputSmlla = document.getElementById(`${prefijo}-nu-smlla`);
            if (inputSmlla) inputSmlla.value = data.nu_smlla_intrno;
        }
    } catch (e) {
        console.error('Error obteniendo correlativo:', e);
    }
}

// -------------------------------------------------------------
// IMPRESIÓN, ADJUNTOS Y DESCARGAS
// -------------------------------------------------------------
export function accionImprimir() {
    const ids = seleccionados();
    if (ids.length === 1) {
        window.open(state.getRoute('hojaRuta') + ids[0], '_blank');
        return;
    }
    const chkFalt = document.getElementById('chk-faltantes');
    const params = new URLSearchParams({
        bandeja: document.getElementById('filtro-bandeja')?.value || state.activeBandeja,
        criterio: document.getElementById('filtro-criterio')?.value || '',
        descripcion: document.getElementById('filtro-descripcion')?.value || '',
        desde: document.getElementById('filtro-desde')?.value || '',
        hasta: document.getElementById('filtro-hasta')?.value || '',
        reporte: document.getElementById('filtro-reporte')?.value || 'todos',
        faltantes: chkFalt && chkFalt.checked ? '1' : '0',
    });
    window.open(state.getRoute('imprimirBandeja') + '?' + params.toString(), '_blank');
}

export function imprimirHojaRuta(id) {
    if (id) window.open(state.getRoute('hojaRuta') + id, '_blank');
}

export function imprimirHojaRutaActual() {
    if (state.tramiteSeleccionadoId) {
        window.open(state.getRoute('hojaRuta') + state.tramiteSeleccionadoId, '_blank');
    } else {
        const ids = seleccionados();
        if (ids.length) {
            window.open(state.getRoute('hojaRuta') + ids[0], '_blank');
        } else {
            mostrarAviso('Atención', 'Seleccione un trámite para emitir su Hoja de Ruta.');
        }
    }
}

export function accionDescargarBandeja() {
    const ids = seleccionados();
    if (!ids.length) {
        mostrarAviso('Descarga en Lote', 'Debe seleccionar al menos un trámite para descargar sus documentos adjuntos.');
        return;
    }
    const params = ids.map((id) => 'ids[]=' + encodeURIComponent(id)).join('&');
    window.open(state.getRoute('descargarLote') + '?' + params, '_blank');
}

export function descargarExpediente(id) {
    if (!id) return;
    window.open(state.getRoute('descargarCompleto') + id, '_blank');
}

export function verAdjunto(idOrParams, tipo = 'documento') {
    let id = null;
    let tipoRecurso = tipo;

    if (idOrParams && typeof idOrParams === 'object') {
        id = idOrParams.id || idOrParams.documento_id || idOrParams.movimiento_id;
        tipoRecurso = idOrParams.tipo || tipo;
    } else {
        id = idOrParams;
    }

    if (!id || String(id).trim() === '' || isNaN(Number(id))) {
        return;
    }

    const cleanId = encodeURIComponent(String(id).trim());
    const qs = (tipoRecurso === 'movimiento')
        ? 'movimiento_id=' + cleanId
        : 'id=' + cleanId;

    window.open('/mod_gene/mod_tram_inter/descargar?' + qs, '_blank');
}

// -------------------------------------------------------------
// DETALLE DOCUMENTO / REEMPLAZAR FIRMADO
// -------------------------------------------------------------
export async function abrirModalDetalleDoc(id) {
    if (!id || String(id).trim() === '' || isNaN(Number(id)) || Number(id) <= 0) {
        mostrarAviso('Detalle del Documento', 'Identificador de trámite no válido.');
        return;
    }

    try {
        const res = await apiCargarDetalleDoc(id);
        if (res.ok && res.data && res.data.status === 'success' && res.data.data) {
            const d = res.data.data;
            const docNum = document.getElementById('doc-numero');
            if (docNum) docNum.textContent = d.numero_tramite;
            const docFec = document.getElementById('doc-fecha');
            if (docFec) docFec.textContent = d.fecha;
            const docHor = document.getElementById('doc-hora');
            if (docHor) docHor.textContent = d.hora;
            const docArea = document.getElementById('doc-area-inicio');
            if (docArea) docArea.textContent = d.area_inicio;
            const docDet = document.getElementById('doc-detalles');
            if (docDet) docDet.textContent = d.detalles;

            const adjBox = document.getElementById('doc-adjunto-container');
            const adjEnlace = document.getElementById('doc-adjunto-enlace');
            const accAdj = document.getElementById('doc-acciones-adjunto');

            if (d.adjunto) {
                if (adjBox) adjBox.classList.remove('hidden');
                if (adjEnlace) {
                    clearChildren(adjEnlace);
                    const btnVer = createSafeElement('button', {
                        className: 'inline-flex items-center gap-1.5 text-blue-600 hover:underline font-bold',
                        attributes: { type: 'button' },
                        dataset: { action: 'ver-adjunto', tipo: 'documento', id: String(id) },
                        children: [
                            createSafeElement('i', { className: 'fas fa-paperclip' }),
                            document.createTextNode(' Click Ver Adjunto'),
                        ],
                    });
                    adjEnlace.appendChild(btnVer);
                }
                if (accAdj) {
                    clearChildren(accAdj);
                    const btnVer2 = createSafeElement('button', {
                        className: 'px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-xs transition-colors',
                        attributes: { type: 'button' },
                        dataset: { action: 'ver-adjunto', tipo: 'documento', id: String(id) },
                        children: [
                            createSafeElement('i', { className: 'fas fa-file-arrow-down mr-1' }),
                            document.createTextNode(' Ver Adjunto'),
                        ],
                    });
                    accAdj.appendChild(btnVer2);
                }
            } else {
                if (adjBox) adjBox.classList.add('hidden');
                if (accAdj) clearChildren(accAdj);
            }

            const reemplazoBox = document.getElementById('doc-reemplazar-container');
            const reempId = document.getElementById('reemplazo-doc-id');
            if (reempId) reempId.value = id;
            if (d.puede_reemplazar && reemplazoBox) {
                reemplazoBox.classList.remove('hidden');
            } else if (reemplazoBox) {
                reemplazoBox.classList.add('hidden');
            }

            const modal = document.getElementById('modal-detalle-doc');
            if (modal) modal.classList.remove('hidden');
        } else {
            const status = res.status || (res.data && res.data.status);
            const msg = (res.data && res.data.message) || res.message || '';
            if (status === 401) {
                mostrarAviso('Sesión Expirada', msg || 'Su sesión ha expirado o no es válida. Inicie sesión nuevamente.');
            } else if (status === 403) {
                mostrarAviso('Acceso Denegado', msg || 'No cuenta con permisos para consultar el detalle de este documento.');
            } else if (status === 404) {
                mostrarAviso('Detalle del Documento', msg || 'No se encontraron datos para este trámite.');
            } else if (status === 422 || status === 400) {
                mostrarAviso('Datos Inválidos', msg || 'El identificador del documento proporcionado no es válido.');
            } else if (status >= 500) {
                mostrarAviso('Error del Servidor', msg || 'Ocurrió un error inesperado al consultar el detalle del documento.');
            } else {
                mostrarAviso('Detalle del Documento', msg || 'No se encontraron datos para este trámite.');
            }
        }
    } catch (e) {
        console.error(e);
        mostrarAviso('Error de Red', 'No se pudo comunicar con el servidor para cargar el detalle del documento.');
    }
}

export function cerrarModalDetalleDoc() {
    const m = document.getElementById('modal-detalle-doc');
    if (m) m.classList.add('hidden');
}

export async function enviarReemplazoFirmado(e, onSuccess) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    const id = document.getElementById('reemplazo-doc-id')?.value;
    const file = document.getElementById('archivo-firmado')?.files?.[0];
    if (!id || !file) {
        mostrarAviso('Atención', 'Debe seleccionar el archivo PDF firmado.');
        return;
    }

    const btn = document.getElementById('btn-submit-reemplazo');
    if (btn) {
        btn.disabled = true;
        btn.textContent = ' Subiendo...';
    }

    const formData = new FormData();
    formData.append('cdgo_dtos_ntrnos', id);
    formData.append('pdf_firmado', file);

    try {
        const res = await apiReemplazarFirmado(formData);
        if (res.ok && res.data && res.data.status === 'success') {
            mostrarAviso('Éxito', res.data.message || 'Documento firmado reemplazado exitosamente.');
            cerrarModalDetalleDoc();
            if (typeof onSuccess === 'function') onSuccess();
        } else {
            mostrarAviso('Error', res.message || 'No se pudo reemplazar el archivo firmado.');
        }
    } catch (err) {
        console.error(err);
        mostrarAviso('Error', 'Ocurrió un error al subir el documento firmado.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Confirmar Reemplazo';
        }
    }
}

// -------------------------------------------------------------
// VOUCHER / TICKET
// -------------------------------------------------------------
export async function abrirModalVoucher(idMov) {
    try {
        const res = await apiCargarVoucher(idMov);
        if (res.ok && res.data && res.data.status === 'success' && res.data.data) {
            const d = res.data.data;
            const vNum = document.getElementById('vouch-numero');
            if (vNum) vNum.textContent = d.numero_tramite;
            const vFec = document.getElementById('vouch-fecha');
            if (vFec) vFec.textContent = d.fecha;
            const vHor = document.getElementById('vouch-hora');
            if (vHor) vHor.textContent = d.hora;
            const vDet = document.getElementById('vouch-detalles');
            if (vDet) vDet.textContent = d.detalles || 'Sin observaciones registradas.';

            const adjEl = document.getElementById('vouch-adjunto');
            if (adjEl) {
                clearChildren(adjEl);
                if (d.adjuntado) {
                    const btnAdj = createSafeElement('button', {
                        className: 'text-slate-800 hover:underline font-bold inline-flex items-center gap-1.5',
                        attributes: { type: 'button' },
                        dataset: { action: 'ver-adjunto', tipo: 'movimiento', id: String(idMov) },
                        children: [
                            createSafeElement('i', { className: 'fas fa-paperclip' }),
                            document.createTextNode(' Click Ver Adjunto'),
                        ],
                    });
                    adjEl.appendChild(btnAdj);
                } else {
                    adjEl.textContent = 'No Tiene Adjunto';
                }
            }

            const modal = document.getElementById('modal-voucher');
            if (modal) modal.classList.remove('hidden');
        } else {
            mostrarAviso('Comprobante', 'No se encontraron datos para este movimiento.');
        }
    } catch (e) {
        console.error(e);
        mostrarAviso('Error', 'No se pudo consultar el voucher.');
    }
}

export function cerrarModalVoucher() {
    const modal = document.getElementById('modal-voucher');
    if (modal) modal.classList.add('hidden');
}

// -------------------------------------------------------------
// VIDEOS TUTORIALES
// -------------------------------------------------------------
export function clickVideo(url, titulo, existe) {
    if (existe && url) {
        abrirVideo(url, titulo);
    } else {
        mostrarAviso(
            'Video Institucional',
            `El video tutorial institucional "${titulo}" se encuentra en proceso de carga o actualización en el servidor.`
        );
    }
}

export function abrirVideo(url, titulo) {
    const tit = document.getElementById('modal-video-titulo');
    if (tit) tit.textContent = `${titulo} — Tutorial Institucional`;
    const player = document.getElementById('modal-video-player');
    const overlay = document.getElementById('video-overlay-play');
    if (player) {
        player.src = url;
        player.currentTime = 0;
    }
    const modal = document.getElementById('modal-video');
    if (modal) modal.classList.remove('hidden');

    if (player) {
        const playPromise = player.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                if (overlay) overlay.classList.add('hidden');
            }).catch(() => {
                if (overlay) overlay.classList.remove('hidden');
            });
        }
        player.onplay = () => { if (overlay) overlay.classList.add('hidden'); };
        player.onpause = () => {
            if (player.currentTime < (player.duration || 1)) {
                if (overlay) overlay.classList.remove('hidden');
            }
        };
    }
}

export function iniciarReproduccionManual() {
    const player = document.getElementById('modal-video-player');
    const overlay = document.getElementById('video-overlay-play');
    if (overlay) overlay.classList.add('hidden');
    if (player) player.play().catch(() => {});
}

export function cerrarVideo() {
    const player = document.getElementById('modal-video-player');
    const overlay = document.getElementById('video-overlay-play');
    if (player) {
        player.pause();
        player.removeAttribute('src');
        player.load();
    }
    if (overlay) overlay.classList.remove('hidden');
    const modal = document.getElementById('modal-video');
    if (modal) modal.classList.add('hidden');
}

// -------------------------------------------------------------
// COMPROBANTES DE PAGO
// -------------------------------------------------------------
export function abrirModalComprobantesPago() {
    const modal = document.getElementById('modal-comprobantes-pago');
    if (modal) modal.classList.remove('hidden');
    cargarComprobantesPago();
}

export function cerrarModalComprobantesPago() {
    const modal = document.getElementById('modal-comprobantes-pago');
    if (modal) modal.classList.add('hidden');
}

export async function cargarComprobantesPago() {
    const loader = document.getElementById('loader-comprobantes');
    const tbody = document.getElementById('tbody-comprobantes');
    if (loader) loader.classList.remove('hidden');
    if (tbody) clearChildren(tbody);

    try {
        const res = await apiCargarComprobantesPago();
        if (res && res.ok && res.data && res.data.status === 'success') {
            state.comprobantesCache = res.data.data || [];
            renderizarComprobantes(state.comprobantesCache);
        } else {
            if (tbody) {
                tbody.appendChild(createSafeElement('tr', {
                    children: [
                        createSafeElement('td', {
                            className: 'px-4 py-6 text-center text-gray-400 italic text-xs',
                            textContent: 'No se encontraron comprobantes disponibles.',
                            attributes: { colspan: 6 },
                        }),
                    ],
                }));
            }
        }
    } catch (e) {
        console.error(e);
        if (tbody) {
            tbody.appendChild(createSafeElement('tr', {
                children: [
                    createSafeElement('td', {
                        className: 'px-4 py-6 text-center text-rose-500 text-xs',
                        textContent: 'Error al consultar comprobantes de pago.',
                        attributes: { colspan: 6 },
                    }),
                ],
            }));
        }
    } finally {
        if (loader) loader.classList.add('hidden');
    }
}

export function filtrarComprobantesLocal(query) {
    const q = (query || '').toLowerCase().trim();
    if (!q) {
        renderizarComprobantes(state.comprobantesCache);
        return;
    }
    const filtrados = (state.comprobantesCache || []).filter((c) => {
        return (c.nu_cmprbnte_pgo && String(c.nu_cmprbnte_pgo).toLowerCase().includes(q)) ||
               (c.no_bnfcrio && String(c.no_bnfcrio).toLowerCase().includes(q)) ||
               (c.nu_tram_todo && String(c.nu_tram_todo).toLowerCase().includes(q));
    });
    renderizarComprobantes(filtrados);
}

function renderizarComprobantes(items) {
    const tbody = document.getElementById('tbody-comprobantes');
    if (!tbody) return;
    clearChildren(tbody);

    if (!items || !items.length) {
        tbody.appendChild(createSafeElement('tr', {
            children: [
                createSafeElement('td', {
                    className: 'px-4 py-6 text-center text-gray-400 italic text-xs',
                    textContent: 'No se encontraron comprobantes coincidentes.',
                    attributes: { colspan: 6 },
                }),
            ],
        }));
        return;
    }

    const fragment = document.createDocumentFragment();

    items.forEach((c) => {
        const tr = createSafeElement('tr', {
            className: 'hover:bg-slate-50 transition-colors text-xs border-b border-gray-100',
            children: [
                createSafeElement('td', { className: 'px-3 py-2 font-bold text-slate-800', textContent: c.nu_cmprbnte_pgo || '-' }),
                createSafeElement('td', { className: 'px-3 py-2 text-center text-slate-600', textContent: c.fe_cmprbnte_pgo || '-' }),
                createSafeElement('td', { className: 'px-3 py-2 text-right font-bold text-emerald-700', textContent: `S/ ${Number(c.nu_imprte || 0).toFixed(2)}` }),
                createSafeElement('td', { className: 'px-3 py-2 text-slate-700', textContent: c.no_bnfcrio || '-' }),
                createSafeElement('td', { className: 'px-3 py-2 font-medium text-slate-700', textContent: c.nu_tram_todo || '-' }),
                createSafeElement('td', { className: 'px-3 py-2 text-slate-500', textContent: c.de_area || '-' }),
            ],
        });
        fragment.appendChild(tr);
    });

    tbody.appendChild(fragment);
}
