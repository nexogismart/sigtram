/**
 * Gestión de bandejas, cabeceras y paginación para Trámite Interno (RF-04).
 */

import { state } from './state.js';
import { apiConsultarTabla, apiCargarConteos } from './api.js';
import { renderFilasSeguras } from './renderers.js';
import { createSafeElement, clearChildren } from '../shared/safe-dom.js';

export function activarNav(btn) {
    document.querySelectorAll('.nav-btn').forEach((b) => {
        b.classList.remove('bg-slate-800', 'text-white');
        b.classList.add('text-gray-600', 'hover:bg-slate-50');
        b.removeAttribute('aria-current');
        const badge = b.querySelector('.badge-conteo');
        if (badge) {
            badge.classList.remove('bg-white/20', 'text-white');
            badge.classList.add('bg-slate-100', 'text-slate-700');
        }
    });
    if (btn) {
        btn.classList.add('bg-slate-800', 'text-white');
        btn.classList.remove('text-gray-600', 'hover:bg-slate-50');
        btn.setAttribute('aria-current', 'page');
        const badge = btn.querySelector('.badge-conteo');
        if (badge) {
            badge.classList.remove('bg-slate-100', 'text-slate-700');
            badge.classList.add('bg-white/20', 'text-white');
        }
    }
}

export function irBandeja(clave, btn, onLimpiarDetalle) {
    state.activeBandeja = clave;
    activarNav(btn);

    const filtroBandeja = document.getElementById('filtro-bandeja');
    if (filtroBandeja) filtroBandeja.value = clave;

    const filtroReporte = document.getElementById('filtro-reporte');
    if (filtroReporte) filtroReporte.value = 'todos';

    const tituloBandeja = document.getElementById('titulo-bandeja');
    if (tituloBandeja) {
        tituloBandeja.textContent = `Bandeja Trámite Interno — ${state.getTitulo(clave)}`;
    }

    renderAcciones(clave);
    renderThead(clave);

    if (typeof onLimpiarDetalle === 'function') {
        onLimpiarDetalle();
    }

    consultar(1);
}

export function renderAcciones(bandeja) {
    const barra = document.getElementById('barra-acciones');
    if (!barra) return;
    clearChildren(barra);

    const fragment = document.createDocumentFragment();

    const separator = () => createSafeElement('span', {
        className: 'text-gray-300',
        textContent: '|',
    });

    if (bandeja === 'entrada') {
        const btnRecep = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 text-white hover:bg-slate-700 text-xs font-bold transition-all shadow-sm',
            attributes: { type: 'button', id: 'btn-recepcionar' },
            dataset: { action: 'recepcionar' },
            children: [
                createSafeElement('i', { className: 'fas fa-check-double text-emerald-400' }),
                document.createTextNode(' Recepcionar'),
            ],
        });

        const btnImp = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'imprimir' },
            children: [
                createSafeElement('i', { className: 'fas fa-print' }),
                document.createTextNode(' Imprimir'),
            ],
        });

        const lblFalt = createSafeElement('label', {
            className: 'inline-flex items-center gap-2 text-red-800 cursor-pointer select-none text-xs font-bold',
            children: [
                createSafeElement('input', {
                    className: 'rounded border-gray-300 text-slate-600 focus:ring-slate-500',
                    attributes: { type: 'checkbox', id: 'chk-faltantes' },
                    dataset: { action: 'toggle-faltantes' },
                }),
                document.createTextNode('Mostrar solo faltantes'),
            ],
        });

        fragment.appendChild(btnRecep);
        fragment.appendChild(separator());
        fragment.appendChild(btnImp);
        fragment.appendChild(separator());
        fragment.appendChild(lblFalt);
    } else if (bandeja === 'recibidos') {
        const btnDer = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-blue-700 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'derivar' },
            children: [
                createSafeElement('i', { className: 'fas fa-share text-blue-600' }),
                document.createTextNode(' Derivar'),
            ],
        });

        const btnAsig = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-emerald-700 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'asignar' },
            children: [
                createSafeElement('i', { className: 'fas fa-user-check text-emerald-600' }),
                document.createTextNode(' Asignar'),
            ],
        });

        const btnFin = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-rose-700 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'finalizar' },
            children: [
                createSafeElement('i', { className: 'fas fa-flag-checkered text-rose-600' }),
                document.createTextNode(' Finalizar'),
            ],
        });

        const btnImp = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'imprimir' },
            children: [
                createSafeElement('i', { className: 'fas fa-print' }),
                document.createTextNode(' Imprimir'),
            ],
        });

        fragment.appendChild(btnDer);
        fragment.appendChild(separator());
        fragment.appendChild(btnAsig);
        fragment.appendChild(separator());
        fragment.appendChild(btnFin);
        fragment.appendChild(separator());
        fragment.appendChild(btnImp);
    } else if (bandeja === 'salida') {
        const btnDev = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-amber-700 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'devolver' },
            children: [
                createSafeElement('i', { className: 'fas fa-rotate-left text-amber-600' }),
                document.createTextNode(' Devolver'),
            ],
        });

        const btnImp = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'imprimir' },
            children: [
                createSafeElement('i', { className: 'fas fa-print' }),
                document.createTextNode(' Imprimir'),
            ],
        });

        fragment.appendChild(btnDev);
        fragment.appendChild(separator());
        fragment.appendChild(btnImp);
    } else if (bandeja === 'enviados') {
        const btnImp = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'imprimir' },
            children: [
                createSafeElement('i', { className: 'fas fa-print' }),
                document.createTextNode(' Imprimir'),
            ],
        });

        const btnReit = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-indigo-700 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'reiterar' },
            children: [
                createSafeElement('i', { className: 'fas fa-redo text-indigo-600' }),
                document.createTextNode(' Reiterativo'),
            ],
        });

        const btnDesc = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-teal-700 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'descargar-bandeja' },
            children: [
                createSafeElement('i', { className: 'fas fa-download text-teal-600' }),
                document.createTextNode(' Descargar'),
            ],
        });

        fragment.appendChild(btnImp);
        fragment.appendChild(separator());
        fragment.appendChild(btnReit);
        fragment.appendChild(separator());
        fragment.appendChild(btnDesc);
    } else {
        const btnImp = createSafeElement('button', {
            className: 'inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-900 text-xs font-bold transition-colors',
            attributes: { type: 'button' },
            dataset: { action: 'imprimir' },
            children: [
                createSafeElement('i', { className: 'fas fa-print' }),
                document.createTextNode(' Imprimir'),
            ],
        });
        fragment.appendChild(btnImp);
    }

    barra.appendChild(fragment);
}

export function toggleFaltantes() {
    const chk = document.getElementById('chk-faltantes');
    const on = chk ? chk.checked : false;

    const fBand = document.getElementById('filtro-bandeja');
    if (fBand) fBand.value = 'entrada';

    const fRep = document.getElementById('filtro-reporte');
    if (fRep) fRep.value = on ? 'sin_finalizar' : 'todos';

    const tit = document.getElementById('titulo-bandeja');
    if (tit) {
        tit.textContent = on
            ? 'Bandeja Trámite Interno — Entrada (solo faltantes)'
            : 'Bandeja Trámite Interno — Entrada';
    }

    consultar(1);
}

export function renderThead(bandeja) {
    const row = document.getElementById('thead-row');
    if (!row) return;
    clearChildren(row);

    const makeTh = (txt, cls = '', title = '') => createSafeElement('th', {
        className: `px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 ${cls}`,
        textContent: txt,
        attributes: title ? { title } : {},
    });

    const thList = [];
    thList.push(makeTh('', 'w-10 shrink-0'));

    if (bandeja === 'entrada') {
        thList.push(makeTh('Trámite', 'celda-tram'));
        thList.push(makeTh('F. Entrada', 'text-center whitespace-nowrap celda-fecha'));
        thList.push(makeTh('Trámite Refer.', 'celda-ref'));
        thList.push(makeTh('Observaciones', 'celda-observaciones'));
        thList.push(makeTh('A', 'px-2 text-center w-10 shrink-0', 'Adjuntos'));
        thList.push(makeTh('D', 'px-2 text-center w-10 shrink-0', 'Detalle'));
    } else if (bandeja === 'enviados') {
        thList.push(makeTh('Trámite', 'celda-tram'));
        thList.push(makeTh('Área'));
        thList.push(makeTh('F. Envío', 'text-center whitespace-nowrap celda-fecha'));
        thList.push(makeTh('Observaciones', 'celda-observaciones'));
        thList.push(makeTh('Usua', 'px-2 text-center'));
        thList.push(makeTh('H', 'px-2 text-center w-10 shrink-0', 'Hoja de Ruta'));
        thList.push(makeTh('A', 'px-2 text-center w-10 shrink-0', 'Adjuntos'));
        thList.push(makeTh('D', 'px-2 text-center w-10 shrink-0', 'Detalle / Reemplazar Firmado'));
    } else if (bandeja === 'todos') {
        thList.push(makeTh('Trámite', 'celda-tram'));
        thList.push(makeTh('Envia'));
        thList.push(makeTh('F. Entrada', 'text-center whitespace-nowrap celda-fecha'));
        thList.push(makeTh('Observaciones', 'celda-observaciones'));
        thList.push(makeTh('Estado', 'whitespace-nowrap'));
        thList.push(makeTh('Cargo'));
        thList.push(makeTh('A', 'px-2 text-center w-10 shrink-0', 'Adjuntos'));
        thList.push(makeTh('D', 'px-2 text-center w-10 shrink-0', 'Detalle'));
    } else if (bandeja === 'sin_finalizar' || bandeja === 'contabilidad') {
        thList.push(makeTh('Trámite', 'celda-tram'));
        thList.push(makeTh('Área Envia'));
        thList.push(makeTh('F. Entrada', 'text-center whitespace-nowrap celda-fecha'));
        thList.push(makeTh('Observaciones', 'celda-observaciones'));
        if (bandeja === 'sin_finalizar') {
            thList.push(makeTh('Estado', 'whitespace-nowrap'));
        }
        thList.push(makeTh('H', 'px-2 text-center w-10 shrink-0', 'Hoja de Ruta'));
        thList.push(makeTh('A', 'px-2 text-center w-10 shrink-0', 'Adjuntos'));
        thList.push(makeTh('D', 'px-2 text-center w-10 shrink-0', 'Voucher'));
    } else {
        // recibidos / salida / finalizados
        thList.push(makeTh('Trámite', 'celda-tram'));
        thList.push(makeTh('F. Registro', 'text-center whitespace-nowrap celda-fecha'));
        thList.push(makeTh('Observaciones', 'celda-observaciones'));
        thList.push(makeTh('H', 'px-2 text-center w-10 shrink-0', 'Hoja de Ruta'));
        thList.push(makeTh('A', 'px-2 text-center w-10 shrink-0', 'Adjuntos'));
        thList.push(makeTh('D', 'px-2 text-center w-10 shrink-0', 'Voucher'));
    }

    thList.forEach((th) => row.appendChild(th));
}

export function marcarTodos() {
    const checks = document.querySelectorAll('.chk-tramite');
    const allOn = Array.from(checks).every((c) => c.checked);
    checks.forEach((c) => { c.checked = !allOn; });
    const btn = document.getElementById('btn-marcar');
    if (btn) btn.textContent = allOn ? 'Todos' : 'Ninguno';
}

export function seleccionados() {
    return Array.from(document.querySelectorAll('.chk-tramite:checked')).map((c) => c.value);
}

export function cambiarTamanoPagina(nuevoTamano) {
    state.paginacionBandeja.per_page = parseInt(nuevoTamano, 10) || 25;
    consultar(1);
}

export function irPagina(numPagina) {
    if (numPagina < 1 || numPagina > state.paginacionBandeja.last_page) return;
    consultar(numPagina);
}

export function actualizarControlesPaginacion(meta) {
    if (!meta) return;
    state.paginacionBandeja = { ...state.paginacionBandeja, ...meta };

    const txtInfo = document.getElementById('pag-rango-info');
    if (txtInfo) {
        if (meta.total > 0 && meta.from !== null) {
            txtInfo.textContent = `Mostrando ${meta.from}–${meta.to} de ${meta.total} trámites`;
        } else {
            txtInfo.textContent = '0 registros encontrados';
        }
    }

    const indPag = document.getElementById('pag-indicador-actual');
    if (indPag) indPag.textContent = `${meta.current_page} / ${meta.last_page}`;

    const btnPrim = document.getElementById('pag-btn-primera');
    const btnAnt = document.getElementById('pag-btn-anterior');
    const btnSig = document.getElementById('pag-btn-siguiente');
    const btnUlt = document.getElementById('pag-btn-ultima');

    if (btnPrim) btnPrim.disabled = meta.current_page <= 1;
    if (btnAnt) btnAnt.disabled = meta.current_page <= 1;
    if (btnSig) btnSig.disabled = meta.current_page >= meta.last_page;
    if (btnUlt) btnUlt.disabled = meta.current_page >= meta.last_page;
}

export async function consultar(page = 1) {
    if (state.consultaAbortController) {
        state.consultaAbortController.abort();
    }
    state.consultaAbortController = new AbortController();

    const loader = document.getElementById('loader');
    const tbody = document.getElementById('tbody-principal');
    const empty = document.getElementById('empty-state');
    const errorState = document.getElementById('error-state');
    const errorMsg = document.getElementById('error-state-mensaje');

    if (loader) {
        loader.classList.remove('hidden');
        loader.classList.add('flex');
    }
    if (tbody) clearChildren(tbody);
    if (empty) empty.classList.add('hidden');
    if (errorState) errorState.classList.add('hidden');

    const chkFalt = document.getElementById('chk-faltantes');
    const params = {
        criterio: document.getElementById('filtro-criterio')?.value || '',
        descripcion: document.getElementById('filtro-descripcion')?.value || '',
        bandeja: document.getElementById('filtro-bandeja')?.value || state.activeBandeja,
        reporte: document.getElementById('filtro-reporte')?.value || 'todos',
        faltantes: chkFalt && chkFalt.checked ? 1 : 0,
        desde: document.getElementById('filtro-desde')?.value || '',
        hasta: document.getElementById('filtro-hasta')?.value || '',
        page,
        per_page: state.paginacionBandeja.per_page,
    };

    try {
        const res = await apiConsultarTabla(params, state.consultaAbortController.signal);
        if (res.isAborted) return;

        if (!res.ok) {
            throw new Error(res.message || 'Error al cargar la bandeja');
        }

        const json = res.data;
        state.datosActuales = (json.status === 'success' && json.data) ? json.data : [];
        const meta = json.meta || {
            current_page: page,
            per_page: params.per_page,
            total: state.datosActuales.length,
            last_page: 1,
            from: 1,
            to: state.datosActuales.length,
        };

        actualizarControlesPaginacion(meta);

        const contReg = document.getElementById('contador-registros');
        if (contReg) {
            contReg.textContent = `${meta.total || state.datosActuales.length} registros`;
        }

        const badgeActivo = document.getElementById(`badge-conteo-${params.bandeja}`);
        if (badgeActivo && typeof meta.total === 'number') {
            badgeActivo.textContent = meta.total;
        }

        if (!state.datosActuales.length) {
            if (empty) empty.classList.remove('hidden');
        } else if (tbody) {
            renderFilasSeguras(params.bandeja, state.datosActuales, tbody);
        }
    } catch (e) {
        if (e.name !== 'AbortError') {
            console.error(e);
            if (errorState) {
                errorState.classList.remove('hidden');
                if (errorMsg) errorMsg.textContent = e.message || 'Error al cargar la bandeja';
            } else if (empty) {
                empty.classList.remove('hidden');
            }
            const contReg = document.getElementById('contador-registros');
            if (contReg) contReg.textContent = '0 registros';
        }
    } finally {
        if (loader) {
            loader.classList.add('hidden');
            loader.classList.remove('flex');
        }
    }
}

export async function cargarConteosBandejas() {
    try {
        const conteos = await apiCargarConteos();
        if (conteos) {
            for (const [bandeja, total] of Object.entries(conteos)) {
                const el = document.getElementById(`badge-conteo-${bandeja}`);
                if (el) el.textContent = total ?? 0;
            }
        }
    } catch (e) {
        console.warn('No se pudieron cargar los conteos de bandejas', e);
    }
}
