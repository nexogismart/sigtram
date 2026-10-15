/**
 * Estado inicial del componente de Trámite Externo (RF-05).
 */

export function createInitialState(config = {}) {
    const fechaHoraHoy = () => {
        const now = new Date();
        const offset = now.getTimezoneOffset() * 60000;
        return new Date(now.getTime() - offset).toISOString().slice(0, 16);
    };

    return {
        // Configuración y rutas del servidor
        routes: config.routes || {},
        puedeExportarExcel: Boolean(config.puedeExportarExcel),
        exportandoExcel: false,

        // Estado general y vista activa
        loading: false,
        activeBandeja: config.bandejaInicial || 'entrada',
        selectedIds: [],
        selectedExpediente: null,
        showToast: false,
        toastMessage: '',
        currentPage: 1,
        lastPage: 1,
        page: 1,
        perPage: 25,
        total: 0,

        // Filtros
        criterio: 'expe_todo',
        descripcion: '',
        desde: '',
        hasta: '',
        detalleData: null,

        // Catálogos
        areas: config.areasList || [],
        cargos: config.cargosList || [],
        tiposDocumento: config.tiposDocumento || [],

        // Modales
        modalRecepcionar: false,
        modalDerivar: false,
        modalAsignar: false,
        modalEnviar: false,
        modalFinalizar: false,
        modalVideoRequests: false,
        modalDetalle: false,
        videoRequests: [],

        modalDevolver: false,
        devolverMotivo: '',
        devolverFecha: fechaHoraHoy(),

        modalArchivar: false,
        archivarUbicacion: '',
        archivarObs: '',

        modalPrestar: false,
        prestarPrestatario: '',
        prestarFinalidad: '',
        prestarSalida: fechaHoraHoy(),
        prestarRetorno: '',

        modalRetornarPrestamo: false,
        retornoObs: 'Devolución confirmada de expediente',

        modalRedirigir: false,
        redirigirCargoId: '0',
        redirigirMotivo: '',



        // Modal Auditoría de descargas (Trazabilidad)
        modalAuditoria: false,
        auditLoading: false,
        auditData: [],
        auditPagination: { current_page: 1, last_page: 1, total: 0, per_page: 10 },
        auditError: false,
        auditErrorMsg: '',
        selectedMovimientoAudit: null,
        auditTriggerEl: null,
        auditAbortController: null,

        // Campos de formularios transaccionales
        recepcionObs: 'SIN NOVEDADES PARA RECEPCIONAR',
        recepcionFecha: fechaHoraHoy(),

        derivarGerencia: '0',
        derivarSubgerencia: '0',
        derivarFecha: fechaHoraHoy(),
        derivarConTramite: false,
        derivarDocTipo: '',
        derivarTramiteNro: '',
        derivarObs: 'SIN NOVEDADES PARA ENVIAR',
        derivarFolios: 1,

        asignarCargo: '0',
        asignarFecha: fechaHoraHoy(),
        asignarConTramite: false,
        asignarDocTipo: '',
        asignarTramiteNro: '',
        asignarObs: 'SIN NOVEDADES PARA ASIGNAR',
        asignarFolios: 1,

        enviarAreas: [],
        enviarFecha: fechaHoraHoy(),
        enviarConTramite: false,
        enviarDocTipo: '',
        enviarTramiteNro: '',
        enviarObs: 'SIN NOVEDADES PARA ENVIAR',
        enviarFolios: 1,

        finalizarFecha: fechaHoraHoy(),
        finalizarObs: 'TRÁMITE FINALIZADO',
        finalizarEnviarCorreo: '1',
        finalizarConTramite: false,
        finalizarDocTipo: '',
        finalizarTramiteNro: '',
        finalizarFolios: 1,

        // Colecciones de datos
        items: [],
        history: [],
        historyLoading: false,
        tableError: null,
        tableAbortController: null,

        // Conteos globales (CEF-09A)
        conteos: {},
        conteosLoading: false,
        conteosError: false,

        // Modal U y Participantes (CEF-10 / CEF-11)
        modalUsuarios: false,
        selectedMovimientoU: null,
        uOrigenEjecutor: null,
        uTriggerEl: null,
        uData: [],
        uPagination: { current_page: 1, last_page: 1, total: 0, per_page: 10 },
        uPage: 1,
        uSearch: '',
        uLoading: false,
        uError: false,
        uErrorMsg: '',
        uCache: new Map(),
        uAbortController: null,
    };
}
