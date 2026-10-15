/**
 * Renderizadores de DOM seguro para Trámite Interno (RF-03).
 * Sustituye completamente innerHTML por DocumentFragment, createSafeElement y textContent.
 * Asigna eventos mediante data-action y delegación segura.
 */

import { createSafeElement, clearChildren } from '../shared/safe-dom.js';
import { getEstadoBadgeClass } from '../shared/estado-badge.js';

export function pad8(n) {
    if (n === null || n === undefined || n === '') {
        return '-';
    }
    if (n === 0 || n === '0') {
        return '00000000';
    }
    const str = String(n).trim();
    if (str === '') {
        return '-';
    }
    // Si contiene guión tipo correlativo-año ('23-2026')
    if (str.includes('-')) {
        const partes = str.split('-');
        if (partes.length === 2 && partes[0].trim() !== '' && !isNaN(Number(partes[0]))) {
            return partes[0].trim().padStart(8, '0') + '-' + partes[1].trim();
        }
    }
    if (!isNaN(Number(str))) {
        return str.padStart(8, '0');
    }
    return '-';
}

export function fmtFecha(f) {
    if (!f) return '-';
    try {
        const d = new Date(f);
        if (isNaN(d.getTime())) return String(f);
        return d.toLocaleString('es-PE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return String(f);
    }
}

export function textoTramite(item) {
    if (!item) return '-';
    const tipo = (item.dc_tpo_trmte || item.de_tpo_trmte || '').trim();
    const rawNum = item.nu_tram_todo ?? item.nu_tram ?? null;
    const num = pad8(rawNum);
    const area = String(item.dc_area || item.de_area || '').replace(/\./g, '').trim();

    if (num === '-' || !num) {
        if (tipo && area) return `${tipo} - ${area}`;
        if (tipo) return tipo;
        return '-';
    }

    const parteTipo = tipo ? tipo + ' ' : '';
    const parteArea = area ? '-' + area : '';
    return (parteTipo + num + parteArea).trim();
}

export function textoReferencia(item) {
    if (!item) return '';
    if (item.dc_tpo_trmte_ref && item.nu_tram_todo_ref) {
        const area = String(item.dc_area_ref || item.de_area_ref || '').replace(/\./g, '').trim();
        const num = pad8(item.nu_tram_todo_ref);
        return (item.dc_tpo_trmte_ref + ' ' + num + (area ? '-' + area : '')).trim();
    }
    if (item.nu_tram_todo_ref) {
        const num = pad8(item.nu_tram_todo_ref);
        return num !== '-' ? num : '';
    }
    if (item.nu_tram_todo_rfncia && item.nu_tram_todo_rfncia.trim() !== '') {
        return item.nu_tram_todo_rfncia.trim();
    }
    return '';
}

export function titleRef(item) {
    if (!item) return '';
    if (item.de_tpo_trmte_ref || item.dc_tpo_trmte_ref) {
        const tipo = item.de_tpo_trmte_ref || item.dc_tpo_trmte_ref || '';
        const num = item.nu_tram_todo_ref || '';
        const area = item.de_area_ref || item.dc_area_ref || '';
        return (tipo + ' ' + num + ' ' + area).trim();
    }
    if (item.nu_tram_todo_rfncia) return item.nu_tram_todo_rfncia;
    return '';
}

export function titleTram(item) {
    if (!item) return '';
    const tipo = item.de_tpo_trmte || item.dc_tpo_trmte || '';
    const num = item.nu_tram_todo || '';
    const area = item.de_area || item.dc_area || '';
    return (tipo + ' ' + num + ' ' + area).trim();
}

/**
 * Crea una celda de checkbox de selección para una fila.
 * @param {object} item
 * @returns {HTMLTableCellElement}
 */
function createCheckboxCell(item) {
    const td = createSafeElement('td', {
        className: 'px-3 py-2',
        dataset: { stopPropagation: '1' },
    });
    const chk = createSafeElement('input', {
        className: 'chk-tramite rounded border-gray-300 text-slate-600',
        attributes: {
            type: 'checkbox',
            value: item.cdgo_dtos_ntrnos,
        },
        dataset: {
            tipo: item.cdgo_tpo_trmte || '',
            tramite: item.nu_tram_todo || '',
        },
    });
    td.appendChild(chk);
    return td;
}

/**
 * Crea una celda con botón de ver adjunto seguro mediante ID canónico.
 * @param {boolean|string} hasAdjunto
 * @param {string|number} canonicalId
 * @param {string} [tipo='documento']
 * @returns {HTMLTableCellElement}
 */
function createAdjuntoCell(hasAdjunto, canonicalId, tipo = 'documento') {
    const td = createSafeElement('td', {
        className: 'px-2 py-2 text-center',
        dataset: { stopPropagation: '1' },
    });
    const tieneAdj = Boolean(hasAdjunto && hasAdjunto !== '' && hasAdjunto !== '-');
    if (tieneAdj && canonicalId) {
        const btn = createSafeElement('button', {
            className: 'text-slate-600 hover:text-slate-800',
            attributes: {
                type: 'button',
                title: 'Adjunto',
            },
            dataset: {
                action: 'ver-adjunto',
                tipo: tipo,
                documentoId: String(canonicalId),
            },
            children: [
                createSafeElement('i', { className: 'fas fa-paperclip' }),
            ],
        });
        td.appendChild(btn);
    }
    return td;
}

/**
 * Crea una celda con botón de detalle/voucher.
 * @param {string|number} id
 * @param {boolean} [urgente=false]
 * @param {string} [title='Ver Detalle del Documento Interno (Voucher)']
 * @returns {HTMLTableCellElement}
 */
function createDetalleDocCell(id, urgente = false, title = 'Ver Detalle del Documento Interno (Voucher)') {
    const td = createSafeElement('td', {
        className: 'px-2 py-2 text-center',
        dataset: { stopPropagation: '1' },
    });
    const btn = createSafeElement('button', {
        className: `${urgente ? 'text-red-600' : 'text-slate-600'} hover:opacity-80 transition-transform active:scale-95`,
        attributes: {
            type: 'button',
            title,
        },
        dataset: {
            action: 'detalle-doc',
            documentoId: String(id),
        },
        children: [
            createSafeElement('i', { className: 'fas fa-eye' }),
        ],
    });
    td.appendChild(btn);
    return td;
}

/**
 * Crea una celda con botón de hoja de ruta.
 * @param {string|number} id
 * @returns {HTMLTableCellElement}
 */
function createHojaRutaCell(id) {
    const td = createSafeElement('td', {
        className: 'px-2 py-2 text-center',
        dataset: { stopPropagation: '1' },
    });
    const btn = createSafeElement('button', {
        className: 'text-amber-800 hover:text-amber-950 transition-transform active:scale-95',
        attributes: {
            type: 'button',
            title: 'Imprimir Hoja de Ruta',
        },
        dataset: {
            action: 'hoja-ruta',
            documentoId: String(id),
        },
        children: [
            createSafeElement('i', { className: 'fas fa-file-invoice text-xs' }),
        ],
    });
    td.appendChild(btn);
    return td;
}

export function createTramiteCell(tram, title) {
    const td = createSafeElement('td', {
        className: 'px-3 py-2 celda-tram text-[12px] font-bold text-slate-800',
        attributes: {
            title: title || tram || '—',
        },
    });
    const span = createSafeElement('span', {
        className: 'celda-tram-texto',
        textContent: tram || '—',
    });
    td.appendChild(span);
    return td;
}

export function createReferenciaCell(ref, title) {
    const td = createSafeElement('td', {
        className: 'px-3 py-2 celda-ref text-[12px] font-bold text-amber-950',
        attributes: {
            title: title || ref || '',
        },
    });
    const span = createSafeElement('span', {
        className: 'celda-ref-texto',
        textContent: ref || '',
    });
    td.appendChild(span);
    return td;
}

export function createFechaCell(fecha) {
    return createSafeElement('td', {
        className: 'px-3 py-2 text-center text-[11px] font-semibold text-gray-700 whitespace-nowrap celda-fecha',
        textContent: fecha || '—',
    });
}

export function createObservacionesCell(observacion) {
    const rawText = (observacion || '').trim();
    const td = createSafeElement('td', {
        className: 'px-3 py-2 text-[11px] text-gray-600 celda-observaciones',
        attributes: {
            title: rawText || '—',
        },
    });

    const span = createSafeElement('span', {
        className: 'celda-observaciones-texto',
        textContent: rawText || '—',
    });

    td.appendChild(span);
    return td;
}

/**
 * Renderiza todas las filas de la tabla principal para la bandeja seleccionada
 * de forma 100% segura usando createElement y DocumentFragment.
 * @param {string} bandeja
 * @param {object[]} items
 * @param {HTMLTableSectionElement} tbody
 */
export function renderFilasSeguras(bandeja, items, tbody) {
    clearChildren(tbody);

    if (!items || !items.length) {
        return;
    }

    const fragment = document.createDocumentFragment();

    items.forEach((item, i) => {
        const alt = i % 2 === 1 ? 'fila-alt' : 'bg-white';
        const urgente = String(item.in_prrdad_trmte) === '0' || String(item.in_prrdad_trmte) === '2';
        const numTramite = item.nu_tram_todo || '';
        const idTramite = item.cdgo_dtos_ntrnos || item.documento_id || '';
        const idMovimiento = item.cdgo_trmtes_ntrnos || item.movimiento_id || '';

        const tr = createSafeElement('tr', {
            className: `${alt} fila-hover cursor-pointer border-b border-gray-100 transition-colors`,
            dataset: {
                action: 'ver-detalle',
                documentoId: String(idTramite),
                movimientoId: String(idMovimiento),
                numero: String(numTramite),
            },
        });

        // 1. Checkbox
        tr.appendChild(createCheckboxCell(item));

        if (bandeja === 'entrada') {
            const ref = textoReferencia(item);
            const tram = textoTramite(item);
            const fecha = fmtFecha(item.fe_ingrso_ref || item.fe_ingrso);
            const adj = item.de_urls_adjnto_ref || item.de_urls_adjnto || '';

            if (ref) {
                tr.appendChild(createTramiteCell(tram, titleTram(item)));
                tr.appendChild(createFechaCell(fecha));
                tr.appendChild(createReferenciaCell(ref, titleRef(item)));
            } else {
                tr.appendChild(createTramiteCell(tram, titleTram(item)));
                tr.appendChild(createFechaCell(fecha));
                tr.appendChild(createSafeElement('td', {
                    className: 'px-3 py-2 text-[12px]',
                    textContent: '',
                }));
            }

            tr.appendChild(createObservacionesCell(item.de_obser));
            tr.appendChild(createAdjuntoCell(adj, idTramite, 'documento'));
            tr.appendChild(createDetalleDocCell(idTramite, urgente));
        } else if (bandeja === 'enviados') {
            tr.appendChild(createTramiteCell(textoTramite(item), titleTram(item)));
            tr.appendChild(createSafeElement('td', {
                className: 'px-3 py-2 text-[11px]',
                textContent: item.dc_area || item.de_area || '-',
            }));
            tr.appendChild(createFechaCell(fmtFecha(item.fe_ingrso)));
            tr.appendChild(createObservacionesCell(item.de_obser));
            tr.appendChild(createSafeElement('td', {
                className: 'px-2 py-2 text-center text-[10px] font-bold',
                textContent: (item.no_crto || '').split(' ')[0] || '-',
            }));

            tr.appendChild(createHojaRutaCell(idTramite));
            tr.appendChild(createAdjuntoCell(item.de_urls_adjnto, idTramite, 'documento'));
            tr.appendChild(createDetalleDocCell(idTramite, false, 'Ver Detalle del Documento Interno (Voucher) / Reemplazar Firmado'));
        } else if (bandeja === 'todos') {
            tr.appendChild(createTramiteCell(textoTramite(item), titleTram(item)));
            tr.appendChild(createSafeElement('td', {
                className: 'px-3 py-2 text-[11px]',
                textContent: item.dc_area || item.de_area || '-',
            }));
            tr.appendChild(createFechaCell(fmtFecha(item.fe_rcbdo || item.fe_ingrso)));
            tr.appendChild(createObservacionesCell(item.de_obser));
            const tdEstadoTodos = createSafeElement('td', {
                className: 'px-3 py-2 text-center whitespace-nowrap',
            });
            const clsTodos = getEstadoBadgeClass(item.cdgo_estdo_trmte, item.dc_estdo_trmte);
            tdEstadoTodos.appendChild(createSafeElement('span', {
                className: `inline-block px-2 py-0.5 rounded-full font-bold uppercase text-[9px] ${clsTodos}`,
                textContent: item.dc_estdo_trmte || '-',
            }));
            tr.appendChild(tdEstadoTodos);
            tr.appendChild(createSafeElement('td', {
                className: 'px-3 py-2 text-[11px] leading-snug font-medium text-slate-700 max-w-[220px]',
                textContent: item.de_crgo || '-',
                attributes: { title: item.de_crgo || '' },
            }));
            tr.appendChild(createAdjuntoCell(item.de_urls_adjnto, idTramite, 'documento'));
            tr.appendChild(createDetalleDocCell(idTramite, false));
        } else if (bandeja === 'sin_finalizar' || bandeja === 'contabilidad') {
            tr.appendChild(createTramiteCell(textoTramite(item), titleTram(item)));
            tr.appendChild(createSafeElement('td', {
                className: 'px-3 py-2 text-[11px]',
                textContent: item.dc_area || item.de_area || '-',
            }));
            tr.appendChild(createFechaCell(fmtFecha(item.fe_ingrso || item.fe_rcbdo)));
            tr.appendChild(createObservacionesCell(item.de_obser));
            if (bandeja === 'sin_finalizar') {
                const tdEstadoSinFin = createSafeElement('td', {
                    className: 'px-3 py-2 text-center whitespace-nowrap',
                });
                const clsSinFin = getEstadoBadgeClass(item.cdgo_estdo_trmte, item.dc_estdo_trmte);
                tdEstadoSinFin.appendChild(createSafeElement('span', {
                    className: `inline-block px-2 py-0.5 rounded-full font-bold uppercase text-[9px] ${clsSinFin}`,
                    textContent: item.dc_estdo_trmte || '-',
                }));
                tr.appendChild(tdEstadoSinFin);
            }
            tr.appendChild(createHojaRutaCell(idTramite));
            tr.appendChild(createAdjuntoCell(item.de_urls_adjnto, idTramite, 'documento'));
            tr.appendChild(createDetalleDocCell(idTramite, false));
        } else {
            // recibidos / salida / finalizados
            tr.appendChild(createTramiteCell(textoTramite(item), titleTram(item)));
            tr.appendChild(createFechaCell(fmtFecha(item.fe_ingrso || item.fe_rcbdo)));
            tr.appendChild(createObservacionesCell(item.de_obser));
            tr.appendChild(createHojaRutaCell(idTramite));
            tr.appendChild(createAdjuntoCell(item.de_urls_adjnto, idTramite, 'documento'));
            tr.appendChild(createDetalleDocCell(idTramite, urgente));
        }

        fragment.appendChild(tr);
    });

    tbody.appendChild(fragment);
}
