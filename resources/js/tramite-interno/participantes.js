/**
 * Modal y visor de participantes U en Trámite Interno (SPEC 10 / RF-04).
 * Renderizado seguro de tarjetas de participantes, paginación y búsqueda reactiva.
 */

import { state } from './state.js';
import { apiCargarParticipantes } from './api.js';
import { createSafeElement, clearChildren } from '../shared/safe-dom.js';

export function abrirModalUsuarios(idx, triggerEl = null) {
    const it = state.movimientosHistoricoActuales[idx];
    if (!it) return;

    const rawId = it.cdgo_trmtes_ntrnos || it.movimiento_id;
    const movId = parseInt(rawId, 10);
    if (!movId || movId <= 0) return;

    state.uModalContext.triggerEl = triggerEl || document.activeElement;
    state.uModalContext.item = it;
    state.uModalContext.movimientoId = movId;
    state.uModalContext.page = 1;
    state.uModalContext.lastPage = 1;
    state.uModalContext.total = 0;
    state.uModalContext.search = '';

    const modalTitulo = document.getElementById('zoom-movimiento-titulo');
    if (modalTitulo) modalTitulo.textContent = `Participantes — Movimiento #${idx + 1}`;

    const modalSubtitulo = document.getElementById('zoom-movimiento-subtitulo');
    if (modalSubtitulo) {
        modalSubtitulo.textContent = `${it.dc_estdo_trmte || 'MOVIMIENTO'} • Entrada: ${it.fe_rcbdo_fmt || '-'}`;
    }

    // Remitente / Actor Origen
    const actor = it.actor || {};
    const elOrigenNombre = document.getElementById('zoom-origen-nombre');
    if (elOrigenNombre) elOrigenNombre.textContent = actor.nombre || it.usuario_origen || 'No registrado';

    const elOrigenLogin = document.getElementById('zoom-origen-login');
    if (elOrigenLogin) elOrigenLogin.textContent = actor.login || (it.cdgo_lgin ? String(it.cdgo_lgin) : '-');

    const elOrigenCargo = document.getElementById('zoom-origen-cargo');
    if (elOrigenCargo) elOrigenCargo.textContent = actor.cargo || it.de_crgo_sa || 'Cargo no registrado en el movimiento';

    const elOrigenArea = document.getElementById('zoom-origen-area');
    if (elOrigenArea) elOrigenArea.textContent = actor.area || it.de_area || it.dc_area || 'Área no registrada';

    const elOrigenFecha = document.getElementById('zoom-origen-fecha');
    if (elOrigenFecha) elOrigenFecha.textContent = `${it.fe_rcbdo_fmt || it.fecha || '-'} — ${it.dc_estdo_trmte || 'REGISTRADO'}`;

    // Perfil independiente (Spec 10 / Regla Tarea 9)
    const perfilBox = document.getElementById('zoom-box-perfil');
    const perfilTxt = document.getElementById('zoom-origen-perfil');
    const perfilValor = actor.perfil || it.perfil_origen || null;
    if (perfilBox && perfilTxt) {
        if (perfilValor && perfilValor.trim() !== '') {
            perfilBox.classList.remove('hidden');
            perfilTxt.textContent = perfilValor;
        } else {
            perfilBox.classList.add('hidden');
        }
    }

    // Recepcionado por si existe
    const recepBox = document.getElementById('zoom-box-recepcionado');
    const recepTxt = document.getElementById('zoom-recepcionado-texto');
    if (recepBox && recepTxt) {
        if (it.recepcionado_por && (it.recepcionado_por.nombre || typeof it.recepcionado_por === 'string')) {
            recepBox.classList.remove('hidden');
            recepTxt.textContent = typeof it.recepcionado_por === 'string'
                ? it.recepcionado_por
                : `${it.recepcionado_por.nombre || ''} (${it.recepcionado_por.fecha || ''})`;
        } else {
            recepBox.classList.add('hidden');
        }
    }

    const inputSearch = document.getElementById('zoom-buscar-usuario');
    if (inputSearch) inputSearch.value = '';

    const cantEl = document.getElementById('zoom-destino-cant');
    if (cantEl) {
        cantEl.textContent = 'Cargando...';
        cantEl.className = 'text-[9px] font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full';
    }

    const pagInfo = document.getElementById('zoom-paginacion-info');
    if (pagInfo) pagInfo.textContent = 'Página 1 de 1';

    const btnPrev = document.getElementById('zoom-btn-prev');
    const btnNext = document.getElementById('zoom-btn-next');
    if (btnPrev) btnPrev.disabled = true;
    if (btnNext) btnNext.disabled = true;

    const listaEl = document.getElementById('zoom-destino-lista');
    if (listaEl) clearChildren(listaEl);

    const errorContainer = document.getElementById('zoom-error-container');
    if (errorContainer) errorContainer.classList.add('hidden');

    const modalU = document.getElementById('modal-usuarios-zoom');
    if (modalU) modalU.classList.remove('hidden');
    if (inputSearch) inputSearch.focus();

    cargarParticipantesULazy();
}

export function cerrarModalUsuarios() {
    if (state.uModalContext.abortController) {
        state.uModalContext.abortController.abort();
        state.uModalContext.abortController = null;
    }
    const modal = document.getElementById('modal-usuarios-zoom');
    if (modal) modal.classList.add('hidden');

    if (state.uModalContext.triggerEl && typeof state.uModalContext.triggerEl.focus === 'function') {
        state.uModalContext.triggerEl.focus();
    }
}

export async function cargarParticipantesULazy() {
    const { movimientoId, page, perPage, search } = state.uModalContext;
    const idVal = parseInt(movimientoId, 10);
    if (!idVal || idVal <= 0) return;

    const cacheKey = `${movimientoId}_${page}_${search}`;
    if (state.uModalContext.cache.has(cacheKey)) {
        renderizarListaU(state.uModalContext.cache.get(cacheKey));
        return;
    }

    if (state.uModalContext.abortController) {
        state.uModalContext.abortController.abort();
    }
    state.uModalContext.abortController = new AbortController();

    const loader = document.getElementById('zoom-loader');
    if (loader) loader.classList.remove('hidden');

    const errorContainer = document.getElementById('zoom-error-container');
    if (errorContainer) errorContainer.classList.add('hidden');

    try {
        const res = await apiCargarParticipantes(
            movimientoId,
            page,
            perPage,
            search,
            state.uModalContext.abortController.signal
        );

        if (res.isAborted) return;

        if (res.ok && res.data && res.data.status === 'success') {
            state.uModalContext.cache.set(cacheKey, res.data);
            renderizarListaU(res.data);
        } else {
            renderizarErrorU(res.data?.message || res.message || 'Error al obtener participantes');
        }
    } catch (err) {
        if (err.name !== 'AbortError') {
            console.error(err);
            renderizarErrorU('Error de conexión al cargar participantes');
        }
    } finally {
        if (loader) loader.classList.add('hidden');
    }
}

export function cambiarPaginaU(delta) {
    const nueva = state.uModalContext.page + delta;
    if (nueva >= 1 && nueva <= state.uModalContext.lastPage) {
        state.uModalContext.page = nueva;
        cargarParticipantesULazy();
    }
}

export function renderizarListaU(resp) {
    const listaEl = document.getElementById('zoom-destino-lista');
    const cantEl = document.getElementById('zoom-destino-cant');
    const pagInfo = document.getElementById('zoom-paginacion-info');
    const btnPrev = document.getElementById('zoom-btn-prev');
    const btnNext = document.getElementById('zoom-btn-next');
    const errorContainer = document.getElementById('zoom-error-container');
    if (errorContainer) errorContainer.classList.add('hidden');

    const data = resp.data || [];
    const pagination = resp.pagination || { current_page: 1, last_page: 1, total: data.length, per_page: 10 };
    state.uModalContext.lastPage = pagination.last_page || 1;
    state.uModalContext.total = pagination.total ?? data.length;

    if (!data.length) {
        if (cantEl) {
            cantEl.textContent = '0 asignados';
            cantEl.className = 'text-[9px] font-black bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full';
        }
    } else {
        if (cantEl) {
            cantEl.textContent = `${state.uModalContext.total} actualmente asignados`;
            cantEl.className = 'text-[9px] font-black bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full';
        }
    }

    if (pagInfo) pagInfo.textContent = `Página ${pagination.current_page || 1} de ${state.uModalContext.lastPage}`;
    if (btnPrev) btnPrev.disabled = (pagination.current_page || 1) <= 1;
    if (btnNext) btnNext.disabled = (pagination.current_page || 1) >= state.uModalContext.lastPage;

    if (!listaEl) return;
    clearChildren(listaEl);

    if (!data.length) {
        const emptyDiv = createSafeElement('div', {
            className: 'text-center py-8 text-slate-400',
            children: [
                createSafeElement('i', { className: 'fas fa-users-slash text-2xl mb-2 text-slate-300' }),
                createSafeElement('p', {
                    className: 'text-xs font-medium',
                    textContent: 'No existen usuarios activos asignados actualmente a este cargo.',
                }),
            ],
        });
        listaEl.appendChild(emptyDiv);
        return;
    }

    const fragment = document.createDocumentFragment();

    data.forEach((u, i) => {
        const itemIdx = ((pagination.current_page - 1) * (pagination.per_page || 10)) + i + 1;
        const numStr = String(itemIdx).padStart(2, '0');

        const card = createSafeElement('div', {
            className: 'p-2.5 rounded-xl border border-slate-100 bg-slate-50/70 hover:bg-emerald-50/40 transition-colors flex items-start gap-2.5',
        });

        const badgeNum = createSafeElement('div', {
            className: 'w-6 h-6 rounded-md bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-[10px] shrink-0 mt-0.5',
            textContent: numStr,
        });

        const infoDiv = createSafeElement('div', { className: 'min-w-0 flex-1' });
        const rowTop = createSafeElement('div', { className: 'flex items-center justify-between gap-1' });

        rowTop.appendChild(createSafeElement('h5', {
            className: 'font-bold text-slate-900 text-xs leading-snug truncate',
            textContent: u.nombre || u.no_crto || '-',
        }));

        if (u.login) {
            const loginBox = createSafeElement('div', {
                className: 'flex items-center gap-1 shrink-0',
            });
            loginBox.appendChild(createSafeElement('span', {
                className: 'font-mono text-[9px] bg-slate-200 text-slate-700 px-1 py-0.2 rounded font-bold',
                textContent: u.login,
            }));
            if (u.login_id) {
                loginBox.appendChild(createSafeElement('span', {
                    className: 'text-[9px] text-slate-400 font-mono',
                    textContent: `(ID: ${u.login_id})`,
                }));
            }
            rowTop.appendChild(loginBox);
        }

        infoDiv.appendChild(rowTop);
        infoDiv.appendChild(createSafeElement('p', {
            className: 'text-[10px] text-slate-500 font-medium leading-tight mt-0.5',
            textContent: `${u.cargo || u.de_crgo || 'Sin cargo'} · ${u.area || u.de_area || 'Sin área'}`,
        }));

        card.appendChild(badgeNum);
        card.appendChild(infoDiv);
        fragment.appendChild(card);
    });

    listaEl.appendChild(fragment);
}

export function renderizarErrorU(mensaje) {
    state.uModalContext.page = 1;
    state.uModalContext.lastPage = 1;
    state.uModalContext.total = 0;

    const cantEl = document.getElementById('zoom-destino-cant');
    if (cantEl) {
        cantEl.textContent = 'No disponible';
        cantEl.className = 'text-[9px] font-bold bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full';
    }

    const pagInfo = document.getElementById('zoom-paginacion-info');
    if (pagInfo) pagInfo.textContent = 'Página 1 de 1';

    const btnPrev = document.getElementById('zoom-btn-prev');
    const btnNext = document.getElementById('zoom-btn-next');
    if (btnPrev) btnPrev.disabled = true;
    if (btnNext) btnNext.disabled = true;

    const listaEl = document.getElementById('zoom-destino-lista');
    if (listaEl) clearChildren(listaEl);

    const errorContainer = document.getElementById('zoom-error-container');
    const errorMsg = document.getElementById('zoom-error-mensaje');
    if (errorContainer) {
        errorContainer.classList.remove('hidden');
        if (errorMsg) errorMsg.textContent = mensaje || 'Error al obtener participantes';
    } else if (listaEl) {
        listaEl.appendChild(createSafeElement('div', {
            className: 'text-center py-6 text-rose-500',
            children: [
                createSafeElement('i', { className: 'fas fa-triangle-exclamation text-lg mb-1' }),
                createSafeElement('p', { className: 'text-xs font-semibold', textContent: mensaje }),
                createSafeElement('button', {
                    className: 'mt-3 px-3 py-1 bg-rose-100 hover:bg-rose-200 text-rose-700 font-bold rounded-lg text-xs transition-colors',
                    textContent: 'Reintentar',
                    attributes: { type: 'button' },
                    dataset: { action: 'reintentar-u' },
                }),
            ],
        }));
    }
}
