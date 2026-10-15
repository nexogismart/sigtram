/**
 * Gestión de histórico, trazabilidad y panel de detalle en Trámite Interno (RF-03 / RF-04).
 * Renderizado de 9 columnas canónicas, auditoría A, botón U y descarga en lote (ZIP).
 */

import { state } from './state.js';
import { apiCargarHistorial } from './api.js';
import { createSafeElement, clearChildren } from '../shared/safe-dom.js';
import { getEstadoBadgeClass } from '../shared/estado-badge.js';

export function toggleMaximizarHistorico() {
    const card = document.getElementById('card-detalle-historico');
    const icon = document.getElementById('icon-maximizar');
    if (!card || !icon) return;

    state.historicoMaximizado = !state.historicoMaximizado;

    if (state.historicoMaximizado) {
        card.classList.add('fixed', 'inset-2', 'z-[260]', 'shadow-2xl', 'bg-white');
        card.classList.remove('flex-1');
        icon.classList.remove('fa-expand');
        icon.classList.add('fa-compress');
    } else {
        card.classList.remove('fixed', 'inset-2', 'z-[260]', 'shadow-2xl', 'bg-white');
        card.classList.add('flex-1');
        icon.classList.add('fa-expand');
        icon.classList.remove('fa-compress');
    }
}

export function limpiarDetalle() {
    if (state.historicoMaximizado) {
        toggleMaximizarHistorico();
    }
    const card = document.getElementById('card-detalle-historico');
    if (card) {
        card.classList.add('hidden');
    }
    const btnOcultar = document.getElementById('btn-ocultar-historico-footer');
    if (btnOcultar) {
        btnOcultar.classList.add('hidden');
    }
    const badgeTitulo = document.getElementById('detalle-badge-titulo');
    if (badgeTitulo) badgeTitulo.textContent = '—';

    // Desmarcar filas activas en la tabla principal
    document.querySelectorAll('tr[data-action="ver-detalle"]').forEach((r) => {
        r.classList.remove('bg-amber-100/70', 'font-semibold');
    });

    state.tramiteSeleccionadoId = null;
    state.movimientosHistoricoActuales = [];
    state.resetParticipantesCache();

    const panel = document.getElementById('panel-detalle');
    if (panel) {
        clearChildren(panel);
        panel.appendChild(createSafeElement('p', {
            className: 'text-xs text-gray-400 italic text-center py-10',
            textContent: 'Seleccione un trámite de la lista para ver su detalle / historial.',
        }));
    }
}

/**
 * Carga y renderiza el historial de un trámite seleccionado de forma 100% segura.
 * Requiere un objeto explícito { documento_id } o { movimiento_id } (o ambos).
 * @param {{ documento_id?: number|string, movimiento_id?: number|string }} payload
 * @param {string} [numero]
 */
export async function verDetalle(payload, numero) {
    if (!payload || typeof payload !== 'object' || (!payload.documento_id && !payload.movimiento_id)) {
        throw new TypeError('verDetalle requiere un objeto explícito con documento_id o movimiento_id');
    }
    const docId = payload.documento_id ? Number(payload.documento_id) : null;
    const movId = payload.movimiento_id ? Number(payload.movimiento_id) : null;
    state.tramiteSeleccionadoId = docId || movId;

    const card = document.getElementById('card-detalle-historico');
    if (card) {
        card.classList.remove('hidden');
    }
    const btnOcultar = document.getElementById('btn-ocultar-historico-footer');
    if (btnOcultar) {
        btnOcultar.classList.remove('hidden');
    }

    const panel = document.getElementById('panel-detalle');
    const badgeEl = document.getElementById('detalle-badge-titulo');
    if (badgeEl) {
        badgeEl.textContent = `CARGANDO ${numero || docId || movId || ''}...`;
    }

    if (panel) {
        clearChildren(panel);
        const pLoading = createSafeElement('p', {
            className: 'text-center text-xs text-slate-600 py-8',
            children: [
                createSafeElement('i', { className: 'fas fa-spinner fa-spin mr-2' }),
                document.createTextNode('Cargando historial...'),
            ],
        });
        panel.appendChild(pLoading);
    }

    try {
        const res = await apiCargarHistorial(payload);
        if (!res.ok) {
            throw new Error(res.message || 'Error al obtener historial');
        }

        const json = res.data;
        const items = (json && json.status === 'success' && json.data) ? json.data : [];
        state.movimientosHistoricoActuales = items;
        const tramite = (json && json.tramite) || {};
        
        let histTitle = (json && json.titulo) ? json.titulo : '';
        if (!histTitle) {
            const tpo = (tramite.de_tpo_trmte || tramite.dc_tpo_trmte || '').trim();
            const num = (tramite.nu_tram_todo || numero || docId || movId || '');
            const area = String(tramite.dc_area || '').replace(/\./g, '').trim();
            histTitle = `${tpo} ${num}${area ? '-' + area : ''}`.trim();
        }
        histTitle = histTitle.replace(/-+$/, '');

        if (badgeEl) {
            badgeEl.textContent = histTitle || (`EXPEDIENTE ${numero || docId || movId || ''}`);
        }

        if (!panel) return;
        clearChildren(panel);

        if (!items.length) {
            panel.appendChild(createSafeElement('p', {
                className: 'text-center text-xs text-gray-400 py-10 italic',
                textContent: 'Sin movimientos históricos registrados para este documento.',
            }));
            return;
        }

        // Construir tabla histórica homologada con Trámite Externo
        const wrapTable = createSafeElement('div', {
            className: 'overflow-x-auto min-w-full',
        });
        const table = createSafeElement('table', {
            className: 'w-full text-left border-collapse text-xs',
        });

        // Thead
        const thead = createSafeElement('thead', {
            className: 'sticky top-0 bg-slate-50 z-20 shadow-xs',
        });
        const trHead = createSafeElement('tr', {
            className: 'text-[9px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-200 bg-slate-50',
        });
        const headers = [
            { text: 'Entrada', align: 'text-left' },
            { text: 'T.E.', align: 'text-center' },
            { text: 'Área Salida', align: 'text-left' },
            { text: 'Estado', align: 'text-left' },
            { text: 'Área Envío', align: 'text-left' },
            { text: 'Observaciones', align: 'text-left' },
            { text: 'U', align: 'text-center', title: 'Usuarios Origen y Destino' },
            { text: 'A', align: 'text-center', title: 'Adjuntos' },
            { text: 'D', align: 'text-center', title: 'Detalle del Movimiento (Voucher)' },
        ];
        headers.forEach((h) => {
            trHead.appendChild(createSafeElement('th', {
                className: `px-4 py-3 ${h.align} uppercase tracking-widest text-[9px] bg-slate-50`,
                textContent: h.text,
                attributes: h.title ? { title: h.title } : {},
            }));
        });
        thead.appendChild(trHead);
        table.appendChild(thead);

        // Tbody
        const tbody = createSafeElement('tbody');

        items.forEach((it, idx) => {
            const tr = createSafeElement('tr', {
                className: 'border-b border-gray-100 text-xs hover:bg-slate-50/80 transition-colors',
            });

            // 1. Entrada
            tr.appendChild(createSafeElement('td', {
                className: 'px-4 py-3 text-gray-500 whitespace-nowrap',
                textContent: it.fe_rcbdo_fmt || '-',
            }));

            // 2. T.E.
            tr.appendChild(createSafeElement('td', {
                className: 'px-4 py-3 text-center font-bold text-slate-700 whitespace-nowrap',
                textContent: it.tiempo_estimado || '-',
            }));

            // 3. Área Salida
            tr.appendChild(createSafeElement('td', {
                className: 'px-4 py-3 text-gray-600 font-medium',
                textContent: it.dc_area || '—',
                attributes: { title: it.de_area || '' },
            }));

            // 4. Estado
            const tdEstado = createSafeElement('td', {
                className: 'px-4 py-3',
            });
            const badgeClasses = getEstadoBadgeClass(it.cdgo_estdo_trmte, it.dc_estdo_trmte);
            tdEstado.appendChild(createSafeElement('span', {
                className: `px-2 py-0.5 rounded text-[9px] font-bold ${badgeClasses}`,
                textContent: it.dc_estdo_trmte || '-',
            }));
            tr.appendChild(tdEstado);

            // 5. Área Envío
            tr.appendChild(createSafeElement('td', {
                className: 'px-4 py-3 font-bold text-gray-800',
                textContent: it.dc_area_1 || '—',
                attributes: { title: it.de_area_1 || '' },
            }));

            // 6. Observaciones
            tr.appendChild(createSafeElement('td', {
                className: 'px-4 py-3 text-gray-600 max-w-xs truncate',
                textContent: it.de_obser_ntrnos || '—',
                attributes: { title: it.de_obser_ntrnos || '' },
            }));

            // 7. Columna U
            const tdU = createSafeElement('td', {
                className: 'px-4 py-3 text-center',
                dataset: { stopPropagation: '1' },
            });
            const totalU = it.usuarios_resumen?.total ?? (it.usuarios_destino ? it.usuarios_destino.length : 0);
            const btnU = createSafeElement('button', {
                className: 'px-2 py-0.5 rounded-md border border-slate-200 bg-white hover:bg-amber-50 hover:border-amber-300 text-slate-700 font-bold text-[11px] inline-flex items-center gap-1 transition-all shadow-2xs mx-auto',
                attributes: {
                    type: 'button',
                    'aria-haspopup': 'dialog',
                    'aria-expanded': 'false',
                    'aria-label': `Ver ${totalU} participantes del movimiento`,
                    title: `Ver ${totalU} participantes del movimiento`,
                },
                dataset: {
                    actionHist: 'zoom-usuarios',
                    idx,
                    movimientoId: String(it.cdgo_trmtes_ntrnos || it.movimiento_id || ''),
                },
                children: [
                    createSafeElement('span', { className: 'text-xs', textContent: '👥' }),
                    createSafeElement('span', { textContent: 'U' }),
                    createSafeElement('span', {
                        className: 'px-1 py-0.2 rounded-full bg-amber-100 text-amber-900 text-[10px] font-extrabold',
                        textContent: totalU,
                    }),
                ],
            });
            tdU.appendChild(btnU);
            tr.appendChild(tdU);

            // 8. Columna A (Adjunto + Auditoría de Descargas)
            const tdA = createSafeElement('td', {
                className: 'px-4 py-3 text-center',
                dataset: { stopPropagation: '1' },
            });
            if (it.tiene_adjunto) {
                const wrapA = createSafeElement('div', {
                    className: 'flex items-center justify-center gap-1.5',
                });
                const btnAdj = createSafeElement('button', {
                    className: 'p-1 text-slate-700 hover:text-slate-900 transition-colors',
                    attributes: {
                        type: 'button',
                        title: 'Descargar documento',
                    },
                    dataset: {
                        actionHist: 'ver-adjunto',
                        tipo: 'movimiento',
                        movimientoId: String(it.cdgo_trmtes_ntrnos || it.movimiento_id || ''),
                    },
                    children: [
                        createSafeElement('i', { className: 'fas fa-file-pdf text-rose-500 text-sm' }),
                    ],
                });
                wrapA.appendChild(btnAdj);

                if (it.tiene_descargas) {
                    const btnAudit = createSafeElement('button', {
                        className: 'text-gray-400 hover:text-slate-600 text-[10px] p-1 rounded hover:bg-slate-100 transition-colors focus:ring-1 focus:ring-slate-400',
                        attributes: {
                            type: 'button',
                            title: `Ver trazabilidad de descargas (${it.descargas_total || 1} registros)`,
                            'aria-label': `Ver trazabilidad de descargas (${it.descargas_total || 1} registros)`,
                            'aria-haspopup': 'dialog',
                        },
                        dataset: {
                            actionHist: 'auditoria-descargas',
                            movimientoId: String(it.cdgo_trmtes_ntrnos || it.movimiento_id || ''),
                            idx: String(idx),
                        },
                        children: [
                            createSafeElement('i', { className: 'fas fa-circle-info' }),
                        ],
                    });
                    wrapA.appendChild(btnAudit);
                }
                tdA.appendChild(wrapA);
            } else {
                tdA.appendChild(createSafeElement('span', {
                    className: 'text-gray-300 text-xs',
                    textContent: '—',
                }));
            }
            tr.appendChild(tdA);

            // 9. Columna D (Voucher del movimiento)
            const tdD = createSafeElement('td', {
                className: 'px-4 py-3 text-center',
                dataset: { stopPropagation: '1' },
            });
            const movIdExacto = String(it.cdgo_trmtes_ntrnos || it.movimiento_id || '');
            const btnD = createSafeElement('button', {
                className: 'text-slate-600 hover:text-slate-900 transition-transform active:scale-95 p-1 rounded hover:bg-slate-100',
                attributes: {
                    type: 'button',
                    title: 'Ver Detalle del Movimiento (Voucher)',
                    'aria-label': 'Ver Detalle del Movimiento (Voucher)',
                },
                dataset: {
                    actionHist: 'voucher',
                    documentoId: movIdExacto,
                },
                children: [
                    createSafeElement('i', { className: 'fas fa-eye text-slate-600 text-xs' }),
                ],
            });
            tdD.appendChild(btnD);
            tr.appendChild(tdD);

            tbody.appendChild(tr);
        });

        // Fila Inferior: Descargar Todos => ZIP
        const trZip = createSafeElement('tr', {
            className: 'bg-gray-50 border-t-2 border-gray-200',
        });
        const tdZipText = createSafeElement('td', {
            className: 'text-right font-extrabold text-slate-800 py-2.5 pr-3 text-xs tracking-wide',
            textContent: 'Descargar Todos =>',
            attributes: { colspan: 8 },
        });
        const tdZipBtn = createSafeElement('td', {
            className: 'text-center py-2.5',
            dataset: { stopPropagation: '1' },
        });
        const btnZip = createSafeElement('button', {
            className: 'inline-flex items-center justify-center p-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-800 transition-colors shadow-sm',
            attributes: {
                type: 'button',
                title: 'Descargar ZIP con todos los documentos adjuntos',
            },
            dataset: {
                actionHist: 'descargar-zip',
                documentoId: String(docId || ''),
            },
            children: [
                createSafeElement('i', { className: 'fas fa-file-zipper text-sm' }),
            ],
        });
        tdZipBtn.appendChild(btnZip);

        trZip.appendChild(tdZipText);
        trZip.appendChild(tdZipBtn);
        tbody.appendChild(trZip);

        table.appendChild(tbody);
        wrapTable.appendChild(table);
        panel.appendChild(wrapTable);
    } catch (e) {
        console.error(e);
        if (panel) {
            clearChildren(panel);
            panel.appendChild(createSafeElement('p', {
                className: 'text-center text-xs text-red-500 py-8',
                textContent: 'Error al cargar el detalle histórico.',
            }));
        }
    }
}

export const auditContext = {
    movimientoId: null,
    triggerEl: null,
    page: 1,
    lastPage: 1,
    perPage: 10,
    total: 0,
    item: null,
};

export async function abrirModalAuditoriaDescargas(movimientoId, idx, triggerEl = null) {
    const it = state.movimientosHistoricoActuales[idx] || {};
    auditContext.movimientoId = movimientoId || it.cdgo_trmtes_ntrnos || it.movimiento_id;
    auditContext.triggerEl = triggerEl || document.activeElement;
    auditContext.page = 1;
    auditContext.lastPage = 1;
    auditContext.total = 0;
    auditContext.item = it;

    const modal = document.getElementById('modal-auditoria-descargas');
    if (!modal) return;

    const subEl = document.getElementById('modal-audit-subtitulo-int');
    if (subEl) {
        subEl.textContent = `Movimiento N° ${auditContext.movimientoId}${it.de_urls_agrdo ? ' • ' + it.de_urls_agrdo : ''}`;
    }

    // Limpieza estricta de estado inicial del movimiento anterior
    const empty = document.getElementById('modal-audit-int-empty');
    const errorBox = document.getElementById('modal-audit-int-error');
    const lista = document.getElementById('modal-audit-int-lista');
    const pagInfo = document.getElementById('modal-audit-int-pag-info');
    const btnPrev = document.getElementById('btn-audit-int-prev');
    const btnNext = document.getElementById('btn-audit-int-next');

    if (empty) empty.classList.add('hidden');
    if (errorBox) errorBox.classList.add('hidden');
    if (lista) clearChildren(lista);
    if (pagInfo) pagInfo.textContent = 'Página 1 de 1 (0 registros)';
    if (btnPrev) btnPrev.disabled = true;
    if (btnNext) btnNext.disabled = true;

    modal.classList.remove('hidden');
    cargarAuditoriaDescargasPaginada(1);
}

export function cerrarModalAuditoriaDescargas() {
    const modal = document.getElementById('modal-auditoria-descargas');
    if (modal) modal.classList.add('hidden');
    if (auditContext.triggerEl && typeof auditContext.triggerEl.focus === 'function') {
        auditContext.triggerEl.focus();
    }
}

export async function cargarAuditoriaDescargasPaginada(page = 1) {
    if (!auditContext.movimientoId) return;
    auditContext.page = page;

    const loader = document.getElementById('modal-audit-int-loader');
    const empty = document.getElementById('modal-audit-int-empty');
    const errorBox = document.getElementById('modal-audit-int-error');
    const lista = document.getElementById('modal-audit-int-lista');
    const pagInfo = document.getElementById('modal-audit-int-pag-info');
    const btnPrev = document.getElementById('btn-audit-int-prev');
    const btnNext = document.getElementById('btn-audit-int-next');

    if (loader) loader.classList.remove('hidden');
    if (empty) empty.classList.add('hidden');
    if (errorBox) errorBox.classList.add('hidden');
    if (lista) clearChildren(lista);

    try {
        const baseUrl = window.frontendConfig?.routes?.auditoriaDescargas || '/mod_gene/mod_tram_inter/correo_interno/auditoria_descargas';
        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.set('movimiento_id', String(auditContext.movimientoId));
        url.searchParams.set('page', String(page));
        url.searchParams.set('per_page', '10');

        const resp = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!resp.ok) {
            throw new Error('Error HTTP ' + resp.status);
        }

        const res = await resp.json();
        if (res.status !== 'success') {
            throw new Error(res.message || 'Error al obtener auditoría');
        }

        const data = res.data || [];
        const pag = res.pagination || { current_page: page, last_page: 1, total: data.length, per_page: 10 };
        auditContext.lastPage = pag.last_page || 1;
        auditContext.total = pag.total ?? data.length;

        if (pagInfo) {
            pagInfo.textContent = `Página ${pag.current_page || 1} de ${auditContext.lastPage} (${auditContext.total} registros)`;
        }
        if (btnPrev) btnPrev.disabled = (pag.current_page || 1) <= 1;
        if (btnNext) btnNext.disabled = (pag.current_page || 1) >= auditContext.lastPage;

        if (!data.length) {
            if (empty) empty.classList.remove('hidden');
            return;
        }

        if (lista) {
            const frag = document.createDocumentFragment();
            data.forEach((d, i) => {
                const itemIdx = ((pag.current_page - 1) * (pag.per_page || 10)) + i + 1;
                const card = createSafeElement('div', {
                    className: 'p-3.5 rounded-xl border border-slate-200 bg-white shadow-2xs hover:border-blue-200 transition-colors',
                });

                const top = createSafeElement('div', {
                    className: 'flex items-start justify-between gap-2 border-b border-slate-100 pb-2 mb-2',
                });
                const userBox = createSafeElement('div');
                userBox.appendChild(createSafeElement('h4', {
                    className: 'font-extrabold text-slate-900 text-xs sm:text-sm leading-snug',
                    textContent: d.nombre_usuario || 'Usuario no disponible',
                }));

                const loginRow = createSafeElement('div', {
                    className: 'flex items-center gap-1.5 mt-0.5',
                });
                loginRow.appendChild(createSafeElement('span', {
                    className: 'text-[10px] text-slate-400 font-bold uppercase tracking-wider',
                    textContent: 'Usuario:',
                }));
                loginRow.appendChild(createSafeElement('span', {
                    className: 'font-mono text-xs font-bold text-slate-700 bg-slate-100 px-1.5 py-0.2 rounded',
                    textContent: d.login || '—',
                }));
                if (d.login_id) {
                    loginRow.appendChild(createSafeElement('span', {
                        className: 'text-[10px] text-slate-400 font-mono',
                        textContent: `(ID: ${d.login_id})`,
                    }));
                }
                userBox.appendChild(loginRow);

                const badgeNum = createSafeElement('span', {
                    className: 'text-[10px] font-black bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full shrink-0',
                    textContent: `#${itemIdx}`,
                });
                top.appendChild(userBox);
                top.appendChild(badgeNum);
                card.appendChild(top);

                const detailsGrid = createSafeElement('div', {
                    className: 'grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs',
                });
                const fBox = createSafeElement('div', { className: 'flex items-center gap-1.5 text-slate-600' });
                fBox.appendChild(createSafeElement('i', { className: 'fas fa-calendar-days text-[11px] text-slate-400' }));
                fBox.appendChild(createSafeElement('span', { className: 'text-[10px] text-slate-400 font-bold uppercase tracking-wider', textContent: 'Fecha:' }));
                fBox.appendChild(createSafeElement('span', { className: 'font-semibold text-slate-800', textContent: d.fecha || d.fe_dscrga || '—' }));

                const ipBox = createSafeElement('div', { className: 'flex items-center gap-1.5 text-slate-600' });
                ipBox.appendChild(createSafeElement('i', { className: 'fas fa-network-wired text-[11px] text-slate-400' }));
                ipBox.appendChild(createSafeElement('span', { className: 'text-[10px] text-slate-400 font-bold uppercase tracking-wider', textContent: 'IP:' }));
                ipBox.appendChild(createSafeElement('span', { className: 'font-mono text-slate-700 font-semibold', textContent: d.ip || d.ip_dscrga || '—' }));

                detailsGrid.appendChild(fBox);
                detailsGrid.appendChild(ipBox);
                card.appendChild(detailsGrid);

                frag.appendChild(card);
            });
            lista.appendChild(frag);
        }
    } catch (e) {
        console.error('[AuditoriaDescargas] Error:', e);
        auditContext.page = 1;
        auditContext.lastPage = 1;
        auditContext.total = 0;
        if (pagInfo) {
            pagInfo.textContent = 'Página 1 de 1 (0 registros)';
        }
        if (btnPrev) btnPrev.disabled = true;
        if (btnNext) btnNext.disabled = true;
        if (lista) clearChildren(lista);
        if (empty) empty.classList.add('hidden');
        if (errorBox) {
            errorBox.classList.remove('hidden');
            const msg = document.getElementById('modal-audit-int-error-msg');
            if (msg) msg.textContent = e.message || 'Error al cargar los registros de auditoría.';
        }
    } finally {
        if (loader) loader.classList.add('hidden');
    }
}
