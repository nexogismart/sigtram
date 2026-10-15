/**
 * Módulo de Inicialización y Delegación de Eventos para Trámite Interno (RF-04).
 * Bootstrap limpio, unificado e idempotente con cero listeners duplicados y cero código inline.
 */

import { state } from './state.js';
import { readRouteConfig } from '../shared/route-config.js';
import {
    irBandeja,
    renderAcciones,
    renderThead,
    toggleFaltantes,
    marcarTodos,
    cambiarTamanoPagina,
    irPagina,
    consultar,
    cargarConteosBandejas,
} from './bandejas.js';
import {
    verDetalle,
    limpiarDetalle,
    toggleMaximizarHistorico,
    abrirModalAuditoriaDescargas,
    cerrarModalAuditoriaDescargas,
    cargarAuditoriaDescargasPaginada,
    auditContext,
} from './historico.js';
import {
    abrirModalUsuarios,
    cerrarModalUsuarios,
    cambiarPaginaU,
    cargarParticipantesULazy,
} from './participantes.js';
import {
    accionRecepcionar,
    cerrarModalRecepcionar,
    submitRecepcionar,
    accionAsignar,
    cerrarModalAsignar,
    enviarAsignar,
    accionDerivar,
    cerrarModalDerivar,
    enviarDerivar,
    filtrarAreasDerivar,
    toggleAreaDerivar,
    accionFinalizar,
    cerrarModalFinalizar,
    enviarFinalizar,
    accionDevolver,
    cerrarModalDevolver,
    enviarDevolver,
    accionReiterar,
    cerrarModalReiterar,
    enviarReiterar,
    accionImprimir,
    imprimirHojaRuta,
    imprimirHojaRutaActual,
    accionDescargarBandeja,
    descargarExpediente,
    verAdjunto,
    abrirModalDetalleDoc,
    cerrarModalDetalleDoc,
    enviarReemplazoFirmado,
    abrirModalVoucher,
    cerrarModalVoucher,
    abrirModalComprobantesPago,
    cerrarModalComprobantesPago,
    cargarComprobantesPago,
    filtrarComprobantesLocal,
    clickVideo,
    cerrarVideo,
    iniciarReproduccionManual,
    toggleCrearDoc,
    cambioTipoDoc,
    mostrarAviso,
    cerrarAviso,
} from './acciones.js';

let debounceSearchTimer = null;
let debounceCompTimer = null;

export function initTramiteInterno(rootEl) {
    if (!rootEl || rootEl.dataset.initialized === 'true') {
        return;
    }
    rootEl.dataset.initialized = 'true';

    // 1. Cargar configuración JSON desde data-config
    const config = readRouteConfig(rootEl, 'data-config');
    state.init(config);

    // 2. Refresco general tras operaciones exitosas
    const refrescarTodo = () => {
        consultar();
        limpiarDetalle();
        cargarConteosBandejas();
    };

    // 3. Delegación de eventos Click
    rootEl.addEventListener('click', (e) => {
        // A) Botones con data-action
        const btnAction = e.target.closest('[data-action]');
        if (btnAction) {
            const action = btnAction.dataset.action;
            const stop = btnAction.dataset.stopPropagation === '1';
            if (stop) e.stopPropagation();

            switch (action) {
                case 'recepcionar':
                    accionRecepcionar();
                    break;
                case 'cerrar-recepcionar':
                    cerrarModalRecepcionar();
                    break;
                case 'submit-recep':
                    submitRecepcionar(refrescarTodo);
                    break;
                case 'asignar':
                    accionAsignar();
                    break;
                case 'cerrar-asignar':
                    cerrarModalAsignar();
                    break;
                case 'derivar':
                    accionDerivar();
                    break;
                case 'cerrar-derivar':
                    cerrarModalDerivar();
                    break;
                case 'finalizar':
                    accionFinalizar();
                    break;
                case 'cerrar-finalizar':
                    cerrarModalFinalizar();
                    break;
                case 'devolver':
                    accionDevolver();
                    break;
                case 'cerrar-devolver':
                    cerrarModalDevolver();
                    break;
                case 'reiterar':
                    accionReiterar();
                    break;
                case 'cerrar-reiterar':
                    cerrarModalReiterar();
                    break;
                case 'imprimir':
                    accionImprimir();
                    break;
                case 'hoja-ruta':
                    imprimirHojaRuta(btnAction.dataset.documentoId);
                    break;
                case 'hoja-ruta-actual':
                    imprimirHojaRutaActual();
                    break;
                case 'descargar-bandeja':
                    accionDescargarBandeja();
                    break;
                case 'ver-adjunto':
                    verAdjunto(btnAction.dataset.documentoId || btnAction.dataset.movimientoId, btnAction.dataset.tipo || 'documento');
                    break;
                case 'detalle-doc':
                    abrirModalDetalleDoc(btnAction.dataset.documentoId);
                    break;
                case 'cerrar-detalle-doc':
                    cerrarModalDetalleDoc();
                    break;
                case 'voucher':
                    abrirModalVoucher(btnAction.dataset.documentoId);
                    break;
                case 'cerrar-voucher':
                    cerrarModalVoucher();
                    break;
                case 'cerrar-usuarios':
                    cerrarModalUsuarios();
                    break;
                case 'abrir-comprobantes':
                    abrirModalComprobantesPago();
                    break;
                case 'cerrar-comprobantes':
                    cerrarModalComprobantesPago();
                    break;
                case 'recargar-comprobantes':
                    cargarComprobantesPago();
                    break;
                case 'cerrar-video':
                    cerrarVideo();
                    break;
                case 'play-video-overlay':
                    iniciarReproduccionManual();
                    break;
                case 'cerrar-aviso':
                    cerrarAviso();
                    break;
                case 'marcar-todos':
                    marcarTodos();
                    break;
                case 'consultar':
                    consultar(1);
                    break;
                case 'limpiar-filtros':
                    limpiarFiltros();
                    break;
                case 'maximizar-historico':
                    toggleMaximizarHistorico();
                    break;
                case 'cerrar-historico':
                    limpiarDetalle();
                    break;
                case 'pag-primera':
                    irPagina(1);
                    break;
                case 'pag-anterior':
                    irPagina(state.paginacionBandeja.current_page - 1);
                    break;
                case 'pag-siguiente':
                    irPagina(state.paginacionBandeja.current_page + 1);
                    break;
                case 'pag-ultima':
                    irPagina(state.paginacionBandeja.last_page);
                    break;
                case 'pag-u-prev':
                    cambiarPaginaU(-1);
                    break;
                case 'pag-u-next':
                    cambiarPaginaU(1);
                    break;
                case 'toggle-faltantes':
                    toggleFaltantes();
                    break;
                case 'video-tutorial':
                    clickVideo(
                        btnAction.dataset.videoUrl,
                        btnAction.dataset.videoTitulo,
                        btnAction.dataset.videoExiste === '1'
                    );
                    break;
                case 'ver-detalle': {
                    const trFila = btnAction.closest('tr[data-action="ver-detalle"]');
                    if (trFila && !e.target.closest('input[type="checkbox"]') && !e.target.closest('button') && !e.target.closest('a')) {
                        document.querySelectorAll('tr[data-action="ver-detalle"]').forEach((r) => {
                            r.classList.remove('bg-amber-100/70', 'font-semibold');
                        });
                        trFila.classList.add('bg-amber-100/70', 'font-semibold');
                        const docId = trFila.dataset.documentoId ? Number(trFila.dataset.documentoId) : null;
                        const movId = trFila.dataset.movimientoId ? Number(trFila.dataset.movimientoId) : null;
                        verDetalle({
                            documento_id: docId,
                            movimiento_id: movId,
                        }, trFila.dataset.numero);
                    }
                    break;
                }
                case 'reintentar-u':
                    cargarParticipantesULazy();
                    break;
                case 'cerrar-auditoria-int':
                    cerrarModalAuditoriaDescargas();
                    break;
                default:
                    break;
            }
            return;
        }

        // B) Botones de cambio de bandeja en el menú lateral
        const btnBandeja = e.target.closest('[data-bandeja]');
        if (btnBandeja) {
            const clave = btnBandeja.dataset.bandeja;
            irBandeja(clave, btnBandeja, limpiarDetalle);
            return;
        }

        // C) Botones con data-action-hist (del panel de histórico)
        const btnHist = e.target.closest('[data-action-hist]');
        if (btnHist) {
            e.stopPropagation();
            const accion = btnHist.dataset.actionHist;
            if (accion === 'zoom-usuarios') {
                abrirModalUsuarios(Number(btnHist.dataset.idx), btnHist);
            } else if (accion === 'voucher') {
                abrirModalVoucher(btnHist.dataset.documentoId);
            } else if (accion === 'ver-adjunto') {
                verAdjunto(btnHist.dataset.movimientoId, btnHist.dataset.tipo || 'movimiento');
            } else if (accion === 'descargar-zip') {
                descargarExpediente(btnHist.dataset.documentoId);
            } else if (accion === 'auditoria-descargas') {
                abrirModalAuditoriaDescargas(btnHist.dataset.movimientoId, Number(btnHist.dataset.idx), btnHist);
            }
            return;
        }

        // D) Botones de video tutorial
        const btnVideo = e.target.closest('.btn-video-tutorial');
        if (btnVideo) {
            clickVideo(
                btnVideo.dataset.videoUrl,
                btnVideo.dataset.videoTitulo,
                btnVideo.dataset.videoExiste === '1'
            );
            return;
        }

        // E) Clic en fila para ver detalle / historial
        const trFila = e.target.closest('tr[data-action="ver-detalle"]');
        if (trFila && !e.target.closest('input[type="checkbox"]') && !e.target.closest('button') && !e.target.closest('a')) {
            document.querySelectorAll('tr[data-action="ver-detalle"]').forEach((r) => {
                r.classList.remove('bg-amber-100/70', 'font-semibold');
            });
            trFila.classList.add('bg-amber-100/70', 'font-semibold');
            const docId = trFila.dataset.documentoId ? Number(trFila.dataset.documentoId) : null;
            const movId = trFila.dataset.movimientoId ? Number(trFila.dataset.movimientoId) : null;
            verDetalle({
                documento_id: docId,
                movimiento_id: movId,
            }, trFila.dataset.numero);
        }
    });

    // 4. Delegación de eventos Change / Input
    rootEl.addEventListener('change', (e) => {
        const target = e.target;
        if (!target) return;

        if (target.id === 'chk-faltantes' || target.dataset.action === 'toggle-faltantes') {
            toggleFaltantes();
        } else if (target.id === 'pag-tamano') {
            cambiarTamanoPagina(target.value);
        } else if (target.id === 'asignar-chk-crear-doc') {
            toggleCrearDoc('asignar');
        } else if (target.id === 'asignar-tipo-doc') {
            cambioTipoDoc('asignar');
        } else if (target.id === 'derivar-chk-crear-doc') {
            toggleCrearDoc('derivar');
        } else if (target.id === 'derivar-tipo-doc') {
            cambioTipoDoc('derivar');
        } else if (target.dataset.action === 'toggle-area-derivar') {
            toggleAreaDerivar(target.dataset.areaId);
        } else if (target.id === 'filtro-bandeja' || target.id === 'filtro-reporte' || target.id === 'filtro-criterio') {
            consultar(1);
        }
    });

    rootEl.addEventListener('input', (e) => {
        const target = e.target;
        if (!target) return;

        if (target.id === 'derivar-buscar-area') {
            filtrarAreasDerivar(target.value);
        } else if (target.id === 'zoom-buscar-usuario') {
            clearTimeout(debounceSearchTimer);
            debounceSearchTimer = setTimeout(() => {
                state.uModalContext.search = target.value.trim();
                state.uModalContext.page = 1;
                cargarParticipantesULazy();
            }, 300);
        } else if (target.id === 'filtro-comprobantes') {
            clearTimeout(debounceCompTimer);
            debounceCompTimer = setTimeout(() => {
                filtrarComprobantesLocal(target.value);
            }, 250);
        }
    });

    // Enter en campo de búsqueda de filtros
    const inputDesc = document.getElementById('filtro-descripcion');
    if (inputDesc) {
        inputDesc.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                consultar(1);
            }
        });
    }

    // 5. Formularios de envío modal
    const formAsignar = document.getElementById('form-asignar');
    if (formAsignar) {
        formAsignar.addEventListener('submit', (e) => enviarAsignar(e, refrescarTodo));
    }

    const formDerivar = document.getElementById('form-derivar');
    if (formDerivar) {
        formDerivar.addEventListener('submit', (e) => enviarDerivar(e, refrescarTodo));
    }

    const formFinalizar = document.getElementById('form-finalizar');
    if (formFinalizar) {
        formFinalizar.addEventListener('submit', (e) => enviarFinalizar(e, refrescarTodo));
    }

    const formDevolver = document.getElementById('form-devolver');
    if (formDevolver) {
        formDevolver.addEventListener('submit', (e) => enviarDevolver(e, refrescarTodo));
    }

    const formReiterar = document.getElementById('form-reiterar');
    if (formReiterar) {
        formReiterar.addEventListener('submit', (e) => enviarReiterar(e, refrescarTodo));
    }

    const formReemplazar = document.getElementById('form-reemplazar-firmado');
    if (formReemplazar) {
        formReemplazar.addEventListener('submit', (e) => enviarReemplazoFirmado(e, () => consultar()));
    }

    // 6. Accesibilidad con tecla Escape y Barra Espaciadora en Video
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cerrarVideo();
            cerrarModalUsuarios();
            cerrarModalRecepcionar();
            cerrarModalAsignar();
            cerrarModalDerivar();
            cerrarModalFinalizar();
            cerrarModalDevolver();
            cerrarModalReiterar();
            cerrarModalDetalleDoc();
            cerrarModalVoucher();
            cerrarModalComprobantesPago();
            cerrarModalAuditoriaDescargas();
            cerrarAviso();
        }

        if (e.code === 'Space') {
            const modalVid = document.getElementById('modal-video');
            if (modalVid && !modalVid.classList.contains('hidden')) {
                const tag = (e.target.tagName || '').toLowerCase();
                if (tag !== 'input' && tag !== 'textarea') {
                    e.preventDefault();
                    const player = document.getElementById('modal-video-player');
                    if (player) {
                        if (player.paused) player.play();
                        else player.pause();
                    }
                }
            }
        }
    });

    // 7. Listeners para modal de auditoría de descargas (paginación y retry)
    const btnAuditPrev = document.getElementById('btn-audit-int-prev');
    if (btnAuditPrev) {
        btnAuditPrev.addEventListener('click', () => {
            if (auditContext.page > 1) cargarAuditoriaDescargasPaginada(auditContext.page - 1);
        });
    }
    const btnAuditNext = document.getElementById('btn-audit-int-next');
    if (btnAuditNext) {
        btnAuditNext.addEventListener('click', () => {
            if (auditContext.page < auditContext.lastPage) cargarAuditoriaDescargasPaginada(auditContext.page + 1);
        });
    }
    const btnAuditRetry = document.getElementById('btn-retry-audit-int');
    if (btnAuditRetry) {
        btnAuditRetry.addEventListener('click', () => {
            cargarAuditoriaDescargasPaginada(auditContext.page);
        });
    }

    // 8. Carga inicial
    const initialNav = rootEl.querySelector('[data-bandeja="entrada"]');
    irBandeja('entrada', initialNav, limpiarDetalle);
    cargarConteosBandejas();
}

function limpiarFiltros() {
    const fDesc = document.getElementById('filtro-descripcion');
    if (fDesc) fDesc.value = '';
    const fCrit = document.getElementById('filtro-criterio');
    if (fCrit) fCrit.value = 'tramite';
    const fDesde = document.getElementById('filtro-desde');
    if (fDesde) fDesde.value = '';
    const fHasta = document.getElementById('filtro-hasta');
    if (fHasta) fHasta.value = '';
    const chkFalt = document.getElementById('chk-faltantes');
    if (chkFalt) chkFalt.checked = false;
    limpiarDetalle();
    consultar(1);
}
