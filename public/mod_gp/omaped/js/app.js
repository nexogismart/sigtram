/* OMAPED Laravel routes */
function omapedUrl(key, qs) {
  var base = (window.OMAPED_ROUTES && OMAPED_ROUTES[key]) ? OMAPED_ROUTES[key] : key;
  if (!qs) return base;
  return base + (base.indexOf('?') >= 0 ? '&' : '?') + qs;
}
const tipoFiltroSelect = document.getElementById("tipoFiltro");
const qInput = document.getElementById("q");
const zonaSelect = document.getElementById("zona");
const fechaInicioInput = document.getElementById("fechaInicio");
const fechaFinInput = document.getElementById("fechaFin");
const fieldFiltroTexto = document.getElementById("fieldFiltroTexto");
const fieldZonaFiltro = document.getElementById("fieldZonaFiltro");
const fieldFechaInicioFiltro = document.getElementById("fieldFechaInicioFiltro");
const fieldFechaFinFiltro = document.getElementById("fieldFechaFinFiltro");
const labelFiltroTexto = document.getElementById("labelFiltroTexto");
const tbody = document.getElementById("pacientesTbody");
const countLabel = document.getElementById("countLabel");
const pageTitle = document.getElementById("pageTitle");
const vistaLista = document.getElementById("vistaLista");
const filtersForm = document.getElementById("filtersForm");
const btnNuevoPaciente = document.getElementById("btnNuevoPaciente");
const btnVolverLista = document.getElementById("btnVolverLista");
const btnInicio = document.getElementById("btnInicio");
const cardNuevoPaciente = document.getElementById("cardNuevoPaciente");
const nuevoPacienteForm = document.getElementById("nuevoPacienteForm");

// Variables de paginación
let paginaActual = 1;
const elementosPorPagina = 5;

/**
 * Función de utilidad para limitar la frecuencia de ejecución de una función.
 * @param {Function} func - La función a ejecutar.
 * @param {number} wait - Tiempo de espera en milisegundos.
 * @returns {Function}
 */
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

/**
 * Sincroniza la visibilidad de los botones según el estado de la vista
 * - "Inicio": visible solo cuando está en la vista de lista
 * - "Volver a lista": visible solo cuando está en el formulario
 * - "Nuevo Usuario OMAPED": visible solo cuando está en la vista de lista
 */
function sincronizarBotonesHeader() {
    if (!cardNuevoPaciente) return;
    const estaEnFormulario = !cardNuevoPaciente.hidden;
    
    const toggleHidden = (element, ocultar) => {
        if (!element) return;
        if (ocultar) {
            element.setAttribute("hidden", "");
        } else {
            element.removeAttribute("hidden");
        }
    };
    
    toggleHidden(btnInicio, estaEnFormulario);
    toggleHidden(btnVolverLista, !estaEnFormulario);
    toggleHidden(btnNuevoPaciente, estaEnFormulario);
}

// Observar cambios en el atributo hidden del formulario para sincronizar automáticamente los botones
if (cardNuevoPaciente) {
    sincronizarBotonesHeader();
    const observer = new MutationObserver(sincronizarBotonesHeader);
    observer.observe(cardNuevoPaciente, {
        attributes: true,
        attributeFilter: ['hidden']
    });
}
const btnSubmitNuevoPaciente = document.querySelector("#nuevoPacienteForm button[type='submit']");
const btnCancelarNuevoPaciente = document.getElementById("btnCancelarNuevoPaciente");
const btnAbrirParienteModal = document.getElementById("btnAbrirParienteModal");
const btnAbrirParienteModalDetalle = document.getElementById("btnAbrirParienteModalDetalle");
const parientesTbody = document.getElementById("parientesTbody");
const parientesCountLabel = document.getElementById("parientesCountLabel");
const regSector = document.getElementById("regSector");
const regZona = document.getElementById("regZona");
const regGenero = document.getElementById("regGenero");
const regNumDocInput = document.getElementById("regNumDoc");
const regCelularInput = document.getElementById("regCelular");
const toastContainer = document.getElementById("toastContainer");
const fieldOtroDistrito = document.getElementById("fieldOtroDistrito");
const regOtroDistrito = document.getElementById("regOtroDistrito");
const regActualizarDatosPersona = document.getElementById("regActualizarDatosPersona");
const regFoto = document.getElementById("regFoto");
const fotoPreview = document.getElementById("fotoPreview");
const fotoPreviewImg = document.getElementById("fotoPreviewImg");
const fotoPreviewText = document.getElementById("fotoPreviewText");
const btnEliminarFoto = document.getElementById("btnEliminarFoto");
const parienteModal = document.getElementById("parienteModal");
const parienteModalTitle = document.getElementById("parienteModalTitle");
const parienteForm = document.getElementById("parienteForm");
const parienteParentesco = document.getElementById("parienteParentesco");
const parienteDocumentoInput = document.getElementById("parienteDocumento");
const parienteTelefonoInput = document.getElementById("parienteTelefono");
const btnSubmitPariente = document.querySelector("#parienteForm button[type='submit']");
const panelPaciente = document.getElementById("panelPaciente");
const panelDatosAdicionales = document.getElementById("panelDatosAdicionales");
const panelTitulo = document.getElementById("panelTitulo");
const panelFamiliar = document.getElementById("panelFamiliar");
const panelDiagnosticos = document.getElementById("panelDiagnosticos");
const btnAbrirHistoricoModal = document.getElementById("btnAbrirHistoricoModal");
const parientesDetalleTbody = document.getElementById("parientesDetalleTbody");
const diagnosticosTbody = document.getElementById("diagnosticosTbody");
const diagnosticoDetalle = document.getElementById("diagnosticoDetalle");
const diagnosticoModal = document.getElementById("diagnosticoModal");
const diagnosticoReadonlyGrid = document.getElementById("diagnosticoReadonlyGrid");
const parienteDetalleModal = document.getElementById("parienteDetalleModal");
const parienteDetalleReadonlyGrid = document.getElementById("parienteDetalleReadonlyGrid");
const historicoModal = document.getElementById("historicoModal");
const historicoForm = document.getElementById("historicoForm");
const historicoModalTitle = document.getElementById("historicoModalTitle");
const btnSubmitHistorico = document.querySelector("#historicoForm button[type='submit']");
const diagnosticoOmapedModal = document.getElementById("diagnosticoOmapedModal");
const diagnosticoOmapedForm = document.getElementById("diagnosticoOmapedForm");
const diagnosticoOmapedTitle = document.getElementById("diagnosticoOmapedTitle");
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
const puedeEliminarUsuario = document.querySelector('meta[name="puede-eliminar"]')?.getAttribute("content") === "1";

let pacientes = [];
/** Total de registros que cumplen el filtro actual (servidor); la tabla muestra una página. */
let totalPacientesLista = 0;
let pacienteSeleccionado = null;
let parientesRegistro = [];
let modoPacienteEdicion = false;
let idPacienteEnEdicion = 0;
let idAtencionEnEdicion = 0;
let contextoPariente = "registro";
let modoPariente = "agregar";
let parienteEditIdx = -1;
let fotoActual = null; // Almacena la foto actual (nombre del archivo o null)
let sectoresReferencia = [];
let parentescosReferencia = [];
let sedesReferencia = [];
let distritosReferencia = [];
let serviciosReferencia = [];
let ultimoDniBuscado = "";
let busquedaDocumentoEnCurso = false;

// ==================== UTILIDADES Y VALIDACIONES ====================

/**
 * Escapa caracteres HTML para prevenir XSS
 * @param {*} value Valor a escapar
 * @returns {string} Valor escapado
 */
function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Iconos SVG para acciones
const ICON_TOGGLE_OFF = `<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
  <path fill="currentColor" d="M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10 10-4.49 10-10S17.51 2 12 2zm5 11H7v-2h10v2z"/>
</svg>`;
const ICON_TOGGLE_ON = `<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
  <path fill="currentColor" d="M12 2C6.49 2 2 6.49 2 12s4.49 10 10 10 10-4.49 10-10S17.51 2 12 2zm-1 14-4-4 1.41-1.41L11 13.17l5.59-5.59L18 9l-7 7z"/>
</svg>`;

// Ícono reutilizable de "descargar" para archivos (Carnet/Certificado/Evidencia).
const ICON_DOWNLOAD = `<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
  <path fill="currentColor" d="M5 20h14v-2H5v2z"/>
  <path fill="currentColor" d="M12 16l-6-6 1.41-1.41L11 13.17V3h2v10.17l3.59-3.58L18 10l-6 6z"/>
</svg>`;

/**
 * Objeto centralizado de validaciones
 */
const Validaciones = {
    /**
     * Valida y normaliza un DNI (solo números, 8 dígitos)
     * @param {string} dni DNI a validar
     * @returns {string|null} DNI normalizado o null si es inválido
     */
    dni: (dni) => {
        if (!dni) return null;
        const normalizado = normalizarSoloDigitos(dni, 8);
        return normalizado.length === 8 ? normalizado : null;
    },
    
    /**
     * Valida y normaliza un celular (solo números, 9 dígitos)
     * @param {string} celular Celular a validar
     * @returns {string|null} Celular normalizado o null si es inválido
     */
    celular: (celular) => {
        if (!celular) return null;
        const normalizado = normalizarSoloDigitos(celular, 9);
        return normalizado.length === 9 ? normalizado : null;
    },
    
    /**
     * Valida un género (MASCULINO o FEMENINO)
     * @param {string} genero Género a validar
     * @returns {string|null} Género validado o null si es inválido
     */
    genero: (genero) => {
        if (!genero) return null;
        const generoUpper = genero.toUpperCase().trim();
        return ['MASCULINO', 'FEMENINO'].includes(generoUpper) ? generoUpper : null;
    },
    
    /**
     * Valida coordenadas geográficas (latitud y longitud)
     * @param {string|number} latitud Latitud a validar
     * @param {string|number} longitud Longitud a validar
     * @returns {Object|null} Objeto con latitud y longitud validados o null si son inválidas
     */
    coordenadas: (latitud, longitud) => {
        if (!latitud || !longitud) return null;
        const lat = parseFloat(latitud);
        const long = parseFloat(longitud);
        
        if (isNaN(lat) || isNaN(long)) return null;
        
        // Validar rangos: latitud -90 a 90, longitud -180 a 180
        if (lat < -90 || lat > 90 || long < -180 || long > 180) {
            return null;
        }
        
        return { latitud: String(lat), longitud: String(long) };
    },
    
    /**
     * Valida una fecha en formato YYYY-MM-DD
     * @param {string} fecha Fecha a validar
     * @returns {string|null} Fecha validada o null si es inválida
     */
    fecha: (fecha) => {
        if (!fecha || typeof fecha !== 'string') return null;
        const fechaTrim = fecha.trim();
        if (fechaTrim === '') return null;
        
        // Extraer solo la parte de fecha si viene con hora
        const match = fechaTrim.match(/^(\d{4}-\d{2}-\d{2})/);
        if (!match) return null;
        
        const fechaParte = match[1];
        
        // Validar formato básico
        if (!/^\d{4}-\d{2}-\d{2}$/.test(fechaParte)) return null;
        
        // Validar que sea una fecha válida
        const partes = fechaParte.split('-');
        if (partes.length !== 3) return null;
        
        const anio = parseInt(partes[0], 10);
        const mes = parseInt(partes[1], 10);
        const dia = parseInt(partes[2], 10);
        
        // Validación básica de rangos
        if (anio < 1900 || anio > 2100 || mes < 1 || mes > 12 || dia < 1 || dia > 31) {
            return null;
        }
        
        // Validar fecha real usando Date
        const fechaObj = new Date(anio, mes - 1, dia);
        if (fechaObj.getFullYear() !== anio || 
            fechaObj.getMonth() !== mes - 1 || 
            fechaObj.getDate() !== dia) {
            return null;
        }
        
        return fechaParte;
    },
    
    /**
     * Valida que se haya seleccionado al menos un tipo de discapacidad
     * @param {Object} discapacidadDetalle Objeto con flags de tipos de discapacidad
     * @returns {boolean} true si es válido, false si no
     */
    tiposDiscapacidad: (discapacidadDetalle) => {
        if (!discapacidadDetalle || typeof discapacidadDetalle !== 'object') return false;
        return !!(discapacidadDetalle.motora || 
                 discapacidadDetalle.sensorial || 
                 discapacidadDetalle.intelectual || 
                 discapacidadDetalle.mental);
    },
    
    /**
     * Valida un grado de discapacidad
     * @param {string} grado Grado a validar
     * @returns {string|null} Grado validado o null si es inválido
     */
    gradoDiscapacidad: (grado) => {
        if (!grado) return null;
        const gradoUpper = grado.toUpperCase().trim();
        return ['LEVE', 'MODERADO', 'SEVERO'].includes(gradoUpper) ? gradoUpper : null;
    }
};

function obtenerNombreCompletoPersona(persona) {
    const partes = [persona?.nombres || "", persona?.apePaterno || "", persona?.apeMaterno || ""]
        .map((value) => String(value).trim())
        .filter(Boolean);
    return partes.join(" ") || "-";
}

function obtenerFamiliaresPaciente(paciente) {
    return Array.isArray(paciente?.familiares) && paciente.familiares.length
        ? paciente.familiares
        : (paciente?.familiar ? [paciente.familiar] : []);
}

/**
 * Valida que un pariente no tenga el mismo DNI que el paciente principal
 * y que no esté duplicado en la lista de parientes
 * @param {Object} pariente - Objeto con los datos del pariente
 * @param {number} indiceExcluir - Índice del pariente a excluir de la validación (para edición)
 * @returns {Object} { valido: boolean, mensaje: string }
 */
function validarPariente(pariente, indiceExcluir = -1) {
    // Validar DNI usando función centralizada
    const dniPariente = Validaciones.dni(pariente.numeroDocumento);
    
    if (!dniPariente) {
        return { valido: false, mensaje: "El DNI del pariente debe tener 8 dígitos" };
    }

    // Validar que no sea el mismo DNI del paciente principal
    let dniPacientePrincipal = null;
    if (contextoPariente === "registro") {
        const regNumDocInput = document.getElementById("regNumDoc");
        dniPacientePrincipal = Validaciones.dni(regNumDocInput?.value || "");
    } else if (contextoPariente === "detalle" && pacienteSeleccionado) {
        dniPacientePrincipal = Validaciones.dni(pacienteSeleccionado.dni || "");
    }

    if (dniPariente && dniPacientePrincipal && dniPariente === dniPacientePrincipal) {
        return { valido: false, mensaje: "Pariente no puede ser el mismo paciente" };
    }

    // Validar duplicados en la lista de parientes usando función centralizada
    if (contextoPariente === "registro") {
        const existeDuplicado = parientesRegistro.some((item, index) => {
            if (indiceExcluir >= 0 && index === indiceExcluir) return false;
            const dniExistente = Validaciones.dni(item.numeroDocumento);
            return dniExistente && dniExistente === dniPariente;
        });
        if (existeDuplicado) {
            return { valido: false, mensaje: "Ya existe un pariente con este DNI en la lista" };
        }
    } else if (contextoPariente === "detalle" && pacienteSeleccionado) {
        const familiares = obtenerFamiliaresPaciente(pacienteSeleccionado);
        const existeDuplicado = familiares.some((item, index) => {
            if (indiceExcluir >= 0 && index === indiceExcluir) return false;
            const dniExistente = Validaciones.dni(item.numeroDocumento);
            return dniExistente && dniExistente === dniPariente;
        });
        if (existeDuplicado) {
            return { valido: false, mensaje: "Ya existe un pariente con este DNI" };
        }
    }

    return { valido: true, mensaje: "" };
}

function obtenerHistoricoPaciente(paciente) {
    return Array.isArray(paciente?.historico) ? paciente.historico : [];
}

function obtenerDiscapacidadDetallePaciente(paciente) {
    return paciente?.discapacidadDetalle || {};
}

function obtenerTiposDesdeFlags(origen) {
    if (!origen) return [];
    const tipos = [];
    if (origen.motora === true) tipos.push("MOTORA");
    if (origen.sensorial === true) tipos.push("SENSORIAL");
    if (origen.intelectual === true) tipos.push("INTELECTUAL");
    if (origen.mental === true) tipos.push("MENTAL");
    return tipos;
}

function obtenerTextoTiposDiscapacidadPaciente(paciente) {
    const tipos = obtenerTiposDesdeFlags(obtenerDiscapacidadDetallePaciente(paciente));
    return tipos.length ? tipos.join(", ") : "-";
}

function obtenerDescripcionDiagnosticoPaciente(paciente) {
    const detalle = obtenerDiscapacidadDetallePaciente(paciente);
    return detalle.descripcionDiagnostico || detalle.descripcion || "-";
}

function marcarTiposDiscapacidadSeleccionados(tipos) {
    const tiposSet = new Set((Array.isArray(tipos) ? tipos : []).map((item) => String(item).trim().toUpperCase()));
    document.querySelectorAll("input[name='regTiposDiscapacidad[]']").forEach((checkbox) => {
        checkbox.checked = tiposSet.has(String(checkbox.value).trim().toUpperCase());
    });
}

function renderReadonlyGrid(container, fields) {
    let html = "";
    fields.forEach((field) => {
        html += "<div class='readonly-field'>";
        html += `<span class='readonly-field__label'>${escapeHtml(field.label)}</span>`;
        html += `<span class='readonly-field__value'>${escapeHtml(field.value || "-")}</span>`;
        html += "</div>";
    });
    container.innerHTML = html;
}

function renderParientesTabla() {
    if (!parientesRegistro.length) {
        parientesTbody.innerHTML = '<tr class="empty-row"><td colspan="9">Aun no se agregaron parientes.</td></tr>';
        parientesCountLabel.textContent = "0 pariente(s) agregado(s)";
        return;
    }

    let html = "";
    parientesRegistro.forEach((item, index) => {
        html += "<tr>";
        html += `<td>${escapeHtml(item.nombres || "-")}</td>`;
        html += `<td>${escapeHtml(item.apePaterno || "-")}</td>`;
        html += `<td>${escapeHtml(item.apeMaterno || "-")}</td>`;
        html += `<td>${escapeHtml(item.numeroDocumento || "-")}</td>`;
        html += `<td>${escapeHtml(item.parentesco || "-")}</td>`;
        html += `<td>${escapeHtml(item.telefono || "-")}</td>`;
        html += `<td>${escapeHtml(item.direccion || "-")}</td>`;
        html += `<td>${escapeHtml(item.observaciones || "-")}</td>`;
        html += "<td>";
        html += `<button type="button" class="btn-icon btn-icon--secondary" data-action="editar-pariente-registro" data-pariente-idx="${index}" title="Editar pariente"><span class="btn-icon__glyph">&#9998;</span></button>`;
        if (puedeEliminarUsuario) {
            html += `<button type="button" class="btn-icon btn-icon--danger" data-action="eliminar-pariente-registro" data-pariente-idx="${index}" title="Eliminar pariente"><span class="btn-icon__glyph">&#128465;</span></button>`;
        }
        html += "</td></tr>";
    });

    parientesTbody.innerHTML = html;
    parientesCountLabel.textContent = `${parientesRegistro.length} pariente(s) agregado(s)`;
}

function renderParientesDetalle(paciente) {
    const familiares = obtenerFamiliaresPaciente(paciente);
    if (!familiares.length) {
        parientesDetalleTbody.innerHTML = '<tr class="empty-row"><td colspan="7">No hay familiar cuidador registrados.</td></tr>';
        return;
    }

    let html = "";
    familiares.forEach((item, index) => {
        const estaActivo = item && (item.estado === true || item.estado === "t" || item.estado === 1 || item.estado === "1");
        html += `<tr${estaActivo ? "" : ' class="is-inactivo"'}>`;
        html += `<td>${escapeHtml(item.numeroDocumento || "-")}</td>`;
        html += `<td>${escapeHtml(item.nombres || "-")}</td>`;
        html += `<td>${escapeHtml(item.apePaterno || "-")}</td>`;
        html += `<td>${escapeHtml(item.apeMaterno || "-")}</td>`;
        html += `<td>${escapeHtml(item.parentesco || "-")}</td>`;
        html += `<td>${escapeHtml(item.telefono || "-")}</td>`;
        html += "<td>";
        html += `<button type="button" class="btn-icon btn-icon--secondary" data-action="editar-pariente" data-pariente-idx="${index}" title="Editar pariente"><span class="btn-icon__glyph">&#9998;</span></button>`;
        html += `<button type="button" class="btn-icon btn-icon--ghost" data-action="ver-pariente-detalle" data-pariente-idx="${index}" title="Ver detalle"><span class="btn-icon__glyph">&#128196;</span></button>`;
        if (puedeEliminarUsuario) {
            const titulo = estaActivo ? "Desactivar pariente" : "Activar pariente";
            const icon = estaActivo ? ICON_TOGGLE_OFF : ICON_TOGGLE_ON;
            const clase = estaActivo ? "btn-icon--danger" : "btn-icon--success";
            html += `<button type="button" class="btn-icon ${clase}" data-action="toggle-pariente-estado" data-pariente-idx="${index}" title="${titulo}" aria-label="${titulo}"><span class="btn-icon__glyph">${icon}</span></button>`;
        }
        html += "</td></tr>";
    });
    parientesDetalleTbody.innerHTML = html;
}

function esArchivoDisponible(valor) {
    const texto = String(valor || "").trim();
    return texto && texto !== "-" ? texto : "";
}

function construirUrlArchivoPrivado(tipo, nombreArchivo) {
    if (!tipo || !nombreArchivo) return null;
    const nombre = String(nombreArchivo).trim();
    if (!nombre || nombre === "-") return null;
    return omapedUrl("archivo_privado", "tipo=" + encodeURIComponent(tipo) + "&archivo=" + encodeURIComponent(nombre));
}

function aplicarImagenPrivada(imgElement, textoElement, tipo, nombreArchivo, textoFallback) {
    if (!imgElement) return false;
    const ruta = construirUrlArchivoPrivado(tipo, nombreArchivo);
    if (!ruta) {
        imgElement.style.display = "none";
        if (textoElement) {
            textoElement.style.display = "block";
            if (textoFallback) textoElement.textContent = textoFallback;
        }
        return false;
    }

    imgElement.src = ruta;
    imgElement.onerror = () => {
        imgElement.style.display = "none";
        if (textoElement) {
            textoElement.style.display = "block";
            if (textoFallback) textoElement.textContent = textoFallback;
        }
    };
    imgElement.style.display = "block";
    if (textoElement) {
        textoElement.style.display = "none";
    }
    return true;
}

function obtenerRutaArchivoDiscapacidad(nombreArchivo, tipo) {
    if (!nombreArchivo || String(nombreArchivo).trim() === "" || nombreArchivo === "-") return null;
    const nombre = String(nombreArchivo).trim();
    if (tipo === "carnet" || nombre.toLowerCase().startsWith("carnt_")) {
        return construirUrlArchivoPrivado("carnet", nombre);
    } else if (tipo === "certificado" || nombre.toLowerCase().startsWith("cert_")) {
        return construirUrlArchivoPrivado("certificado", nombre);
    }
    return null;
}

function construirCamposDiscapacidad(item, paciente, tiposDiscapacidadTexto) {
    const archivoCarnet = esArchivoDisponible(item.archivoCarnetConadis || item.carnetConadis);
    const archivoCertificado = esArchivoDisponible(item.archivoCertificadoDiscapacidad || item.certificadoDiscapacidad);
    
    return [
        { label: "Descripcion Diagnostico", value: item.descripcionDiagnostico || item.descripcion || obtenerDescripcionDiagnosticoPaciente(paciente) || "-" },
        { label: "Tipo de Discapacidad", value: tiposDiscapacidadTexto || "-" },
        { label: "Codigo Carnet Conadis", value: item.codigoConadis || "-" },
        { label: "Grado de Discapacidad", value: item.gradoDiscapacidad || "-" },
        { label: "Seguro de Salud", value: item.seguroSalud || "-" },
        { label: "Carnet Conadis (archivo)", value: archivoCarnet || "-", esArchivo: true, archivo: archivoCarnet || null, rutaArchivo: obtenerRutaArchivoDiscapacidad(archivoCarnet, "carnet") },
        { label: "Certificado de Discapacidad (archivo)", value: archivoCertificado || "-", esArchivo: true, archivo: archivoCertificado || null, rutaArchivo: obtenerRutaArchivoDiscapacidad(archivoCertificado, "certificado") }
    ];
}

function renderDiagnosticoDetalle(registros, paciente) {
    const lista = Array.isArray(registros) && registros.length ? registros : [{}];
    const base = obtenerDiscapacidadDetallePaciente(paciente) || lista[0] || {};
    const tiposDiscapacidad = obtenerTiposDesdeFlags(base);
    const tiposDiscapacidadTexto = tiposDiscapacidad.length ? `[${tiposDiscapacidad.join(", ")}]` : "-";

    let html = "<div class='diagnostico-detalle__grupo'>";
    construirCamposDiscapacidad(base, paciente, tiposDiscapacidadTexto).forEach((field) => {
        const classExtra = field.label === "Descripcion Diagnostico" ? " diagnostico-detalle__item--stack" : "";
        html += `<div class='diagnostico-detalle__item${classExtra}'>`;
        html += `<span class='diagnostico-detalle__label'>${escapeHtml(field.label)}:</span>`;
        if (field.esArchivo && field.archivo && field.rutaArchivo && String(field.archivo).trim() !== "") {
            html += "<span class='diagnostico-detalle__value'>";
            html += `<a href="${escapeHtml(field.rutaArchivo)}" download="${escapeHtml(field.archivo)}" class="btn-icon btn-icon--primary" title="Descargar ${escapeHtml(field.archivo)}" target="_blank" rel="noopener"><span class="btn-icon__glyph">${ICON_DOWNLOAD}</span></a>`;
            html += "</span>";
        } else {
            html += `<span class='diagnostico-detalle__value'>${escapeHtml(field.value || "-")}</span>`;
        }
        html += "</div>";
    });
    html += "</div>";
    diagnosticoDetalle.innerHTML = html;
}

function renderHistoricoTabla(paciente) {
    const historico = obtenerHistoricoPaciente(paciente);
    if (!historico.length) {
        diagnosticosTbody.innerHTML = '<tr class="empty-row"><td colspan="6">No hay historico registrado.</td></tr>';
        return;
    }

    let html = "";
    historico.forEach((item) => {
        const serviciosTexto = String(item.serviciosAtencion || "-")
            .replace(/\s*\|\s*/g, ", ")
            .replace(/\s*,\s*/g, ", ")
            .trim() || "-";
        const evidencia = item.evidenciaArchivos || "-";
        const idAtencion = item.idAtencion || item.id_atncn || "";

        html += "<tr>";
        html += `<td>${escapeHtml(item.fechaAtencion || "-")}</td>`;
        html += `<td class="col-servicio-historico"><span class="cell-ellipsis-historico" title="${escapeHtml(serviciosTexto)}">${escapeHtml(serviciosTexto)}</span></td>`;
        html += `<td>${escapeHtml(item.idSedeAtencion || "-")}</td>`;
        html += `<td>${escapeHtml(item.observacionesAtencion || "-")}</td>`;
        html += "<td>";
        if (evidencia !== "-") {
            html += `<button type="button" class="btn-icon btn-icon--primary" data-action="descargar-evidencia" data-evidencia="${escapeHtml(evidencia)}" title="Descargar evidencia"><span class="btn-icon__glyph">${ICON_DOWNLOAD}</span></button>`;
        } else {
            html += "-";
        }
        html += "</td>";
        html += "<td>";
        html += `<button type="button" class="btn-icon btn-icon--secondary" data-action="editar-historico" data-id-atencion="${escapeHtml(String(idAtencion))}" title="Editar historico"><span class="btn-icon__glyph">&#9998;</span></button>`;
        html += "</td></tr>";
    });
    diagnosticosTbody.innerHTML = html;
}


function renderTabla(data) {
    const total = totalPacientesLista;
    if (!total && (!data || !data.length)) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="8">No se encontraron usuarios OMAPED</td></tr>';
        countLabel.textContent = "0 resultados";
        renderPaginacion(0);
        return;
    }

    const totalPaginas = Math.max(1, Math.ceil(total / elementosPorPagina));
    if (paginaActual > totalPaginas) {
        paginaActual = totalPaginas;
    }

    const datosPagina = Array.isArray(data) ? data : [];
    const mostrarBotonEliminar = puedeEliminarUsuario;

    let html = "";
    datosPagina.forEach((item) => {
        html += "<tr>";
        html += `<td>${escapeHtml(item.dni || "-")}</td>`;
        html += `<td>${escapeHtml(item.nombres || "-")}</td>`;
        html += `<td>${escapeHtml(item.apePaterno || "-")}</td>`;
        html += `<td>${escapeHtml(item.apeMaterno || "-")}</td>`;
        html += `<td>${escapeHtml(obtenerTextoTiposDiscapacidadPaciente(item))}</td>`;
        html += `<td>${escapeHtml(item.zona || "-")}</td>`;
        html += `<td class="col-diagnostico"><span class="cell-ellipsis" title="${escapeHtml(obtenerDescripcionDiagnosticoPaciente(item))}">${escapeHtml(obtenerDescripcionDiagnosticoPaciente(item))}</span></td>`;
        html += "<td>";
        html += `<button type="button" class="btn-icon btn-icon--primary" data-action="ver-diagnosticos" data-id-paciente="${item.idPaciente}" title="Ver diagnosticos"><span class="btn-icon__glyph">&#128193;</span></button>`;
        html += `<button type="button" class="btn-icon btn-icon--secondary" data-action="editar-paciente" data-id-paciente="${item.idPaciente}" title="Editar usuario"><span class="btn-icon__glyph">&#9998;</span></button>`;
        if (mostrarBotonEliminar) {
            html += `<button type="button" class="btn-icon btn-icon--danger" data-action="eliminar-paciente" data-id-paciente="${item.idPaciente}" title="Eliminar usuario"><span class="btn-icon__glyph">&#128465;</span></button>`;
        }
        html += "</td></tr>";
    });

    tbody.innerHTML = html;
    countLabel.textContent = `${total} resultado(s) - Página ${paginaActual} de ${totalPaginas}`;
    
    // Renderizar controles de paginación
    renderPaginacion(totalPaginas);
}

function renderPaginacion(totalPaginas) {
    const paginacionContainer = document.getElementById("paginacionContainer");
    if (!paginacionContainer) return;
    
    if (totalPaginas <= 1) {
        paginacionContainer.innerHTML = "";
        return;
    }
    
    let html = '<div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 16px;">';
    
    // Botón Anterior
    html += `<button type="button" class="btn btn--ghost btn--sm" id="btnPaginaAnterior" ${paginaActual === 1 ? 'disabled' : ''}>Anterior</button>`;
    
    // Números de página
    html += '<div style="display: flex; gap: 4px;">';
    for (let i = 1; i <= totalPaginas; i++) {
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 1 && i <= paginaActual + 1)) {
            html += `<button type="button" class="btn btn--sm ${i === paginaActual ? 'btn--primary' : 'btn--ghost'}" data-pagina="${i}">${i}</button>`;
        } else if (i === paginaActual - 2 || i === paginaActual + 2) {
            html += '<span style="padding: 0 4px;">...</span>';
        }
    }
    html += '</div>';
    
    // Botón Siguiente
    html += `<button type="button" class="btn btn--ghost btn--sm" id="btnPaginaSiguiente" ${paginaActual === totalPaginas ? 'disabled' : ''}>Siguiente</button>`;
    
    html += '</div>';
    paginacionContainer.innerHTML = html;
    
    document.getElementById("btnPaginaAnterior")?.addEventListener("click", async () => {
        if (paginaActual > 1) {
            await cargarPacientes(paginaActual - 1);
        }
    });
    
    document.getElementById("btnPaginaSiguiente")?.addEventListener("click", async () => {
        if (paginaActual < totalPaginas) {
            await cargarPacientes(paginaActual + 1);
        }
    });
    
    document.querySelectorAll('[data-pagina]').forEach(btn => {
        btn.addEventListener("click", async () => {
            const dest = parseInt(btn.getAttribute("data-pagina"), 10);
            if (!Number.isNaN(dest) && dest >= 1) {
                await cargarPacientes(dest);
            }
        });
    });
}

function animarTransicionVista(element) {
    element.classList.remove("view-fade-in");
    void element.offsetWidth;
    element.classList.add("view-fade-in");
}

// Funciones auxiliares para manejar campo documento
function habilitarCampoDocumentoPariente() {
    if (!parienteDocumentoInput) return;
    parienteDocumentoInput.disabled = false;
    parienteDocumentoInput.readOnly = false;
    parienteDocumentoInput.removeAttribute('readonly');
    parienteDocumentoInput.removeAttribute('disabled');
    parienteDocumentoInput.value = '';
}

function deshabilitarCampoDocumentoPariente() {
    if (!parienteDocumentoInput) return;
    parienteDocumentoInput.disabled = true;
    parienteDocumentoInput.readOnly = true;
}

function abrirModalPariente() {
    contextoPariente = "registro";
    modoPariente = "agregar";
    parienteEditIdx = -1;
    parienteModalTitle.textContent = "Agregar familiar cuidador";
    btnSubmitPariente.textContent = "Agregar";
    parienteForm.reset();
    habilitarCampoDocumentoPariente();
    parienteModal.hidden = false;
    setTimeout(() => parienteDocumentoInput?.focus(), 100);
}

function abrirModalParienteDetalle() {
    if (!pacienteSeleccionado) return;
    contextoPariente = "detalle";
    modoPariente = "agregar";
    parienteEditIdx = -1;
    parienteModalTitle.textContent = `Agregar familiar cuidador a ${obtenerNombreCompletoPersona(pacienteSeleccionado)}`;
    btnSubmitPariente.textContent = "Agregar";
    parienteForm.reset();
    habilitarCampoDocumentoPariente();
    parienteModal.hidden = false;
    setTimeout(() => parienteDocumentoInput?.focus(), 100);
}

function llenarFormularioPariente(pariente) {
    document.getElementById("parienteDocumento").value = pariente.numeroDocumento || "";
    document.getElementById("parienteNombres").value = pariente.nombres || "";
    document.getElementById("parienteApePaterno").value = pariente.apePaterno || "";
    document.getElementById("parienteApeMaterno").value = pariente.apeMaterno || "";
    document.getElementById("parienteTelefono").value = pariente.telefono || "";
    document.getElementById("parienteDireccion").value = pariente.direccion || "";
    document.getElementById("parienteObservaciones").value = pariente.observaciones || "";
    const parentescoItem = parentescosReferencia.find((item) => String(item.id) === String(pariente.idParentesco) || item.nombre === pariente.parentesco);
    if (parentescoItem) parienteParentesco.value = String(parentescoItem.id);
}

function abrirModalEditarParienteRegistro(pariente, idx) {
    if (!pariente) return;
    contextoPariente = "registro";
    modoPariente = "editar";
    parienteEditIdx = idx;
    parienteModalTitle.textContent = "Editar familiar cuidador";
    btnSubmitPariente.textContent = "Guardar cambios";
    llenarFormularioPariente(pariente);
    deshabilitarCampoDocumentoPariente();
    parienteModal.hidden = false;
}

function abrirModalEditarPariente(pariente, idx) {
    if (!pariente) return;
    contextoPariente = "detalle";
    modoPariente = "editar";
    parienteEditIdx = idx;
    parienteModalTitle.textContent = `Editar pariente de ${obtenerNombreCompletoPersona(pacienteSeleccionado)}`;
    btnSubmitPariente.textContent = "Guardar cambios";
    llenarFormularioPariente(pariente);
    deshabilitarCampoDocumentoPariente();
    parienteModal.hidden = false;
}

function cerrarModalPariente() {
    parienteForm.reset();
    modoPariente = "agregar";
    parienteEditIdx = -1;
    ultimoDniParienteBuscado = "";
    btnSubmitPariente.textContent = "Agregar";
    habilitarCampoDocumentoPariente();
    parienteModal.hidden = true;
}

function abrirModalParienteDetalleReadonly(pariente) {
    renderReadonlyGrid(parienteDetalleReadonlyGrid, [
        { label: "N° Documento", value: pariente.numeroDocumento || "-" },
        { label: "Nombres", value: pariente.nombres || "-" },
        { label: "Apellido Paterno", value: pariente.apePaterno || "-" },
        { label: "Apellido Materno", value: pariente.apeMaterno || "-" },
        { label: "Parentesco", value: pariente.parentesco || "-" },
        { label: "Celular", value: pariente.telefono || "-" },
        { label: "Direccion", value: pariente.direccion || "-" },
        { label: "Observaciones", value: pariente.observaciones || "-" }
    ]);
    parienteDetalleModal.hidden = false;
}

function cerrarModalParienteDetalle() {
    parienteDetalleModal.hidden = true;
}

function abrirModalHistorico() {
    if (!pacienteSeleccionado) return;
    idAtencionEnEdicion = 0;
    historicoModalTitle.textContent = "Registro de atenciones";
    btnSubmitHistorico.textContent = "Registrar atencion";
    historicoForm.reset();
    const fieldServicioOtro = document.getElementById("fieldServicioOtro");
    if (fieldServicioOtro) {
        fieldServicioOtro.setAttribute("hidden", "");
        fieldServicioOtro.style.display = "none";
    }
    const fechaAtencionInput = document.getElementById("aFechaAtencion");
    if (fechaAtencionInput) {
        const hoy = new Date();
        const año = hoy.getFullYear();
        const mes = String(hoy.getMonth() + 1).padStart(2, '0');
        const dia = String(hoy.getDate()).padStart(2, '0');
        fechaAtencionInput.value = `${año}-${mes}-${dia}`;
    }
    historicoModal.hidden = false;
}

function abrirModalEditarHistorico(idAtencion) {
    if (!pacienteSeleccionado || !idAtencion) return;
    idAtencionEnEdicion = idAtencion;
    historicoModalTitle.textContent = "Editar atencion";
    btnSubmitHistorico.textContent = "Actualizar atencion";
    
    const historico = obtenerHistoricoPaciente(pacienteSeleccionado);
    const atencion = historico.find(item => (item.idAtencion || item.id_atncn) === idAtencion);
    
    if (!atencion) {
        mostrarToast("error", "No se pudo encontrar la atención");
        return;
    }
    
    document.getElementById("aFechaAtencion").value = atencion.fechaAtencion || "";
    document.getElementById("aSedeAtencion").value = atencion.idSedeAtencion || "";
    document.getElementById("aObservaciones").value = atencion.observacionesAtencion || "";
    
    const checkboxes = document.querySelectorAll("input[name='aServicios[]']");
    checkboxes.forEach(cb => cb.checked = false);
    
    const serviciosIds = String(atencion.serviciosIds || "").trim();
    if (serviciosIds) {
        const idsArray = serviciosIds.split(',').filter(id => id.trim() !== "").map(id => parseInt(id.trim()));
        idsArray.forEach(idServ => {
            const checkbox = Array.from(checkboxes).find(cb => parseInt(cb.value) === idServ);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }
    
    const serviciosTexto = String(atencion.serviciosAtencion || "").trim();
    if (serviciosTexto && serviciosTexto !== "-") {
        const serviciosArray = serviciosTexto.split(/\s*\|\s*/).filter(s => s.trim() !== "");
        serviciosArray.forEach(servicioTexto => {
            const partes = servicioTexto.split(/\s*:\s*/);
            const nombreServicio = partes[0].trim();
            const observServicio = partes.length > 1 ? partes.slice(1).join(": ").trim() : "";
            
            if (nombreServicio === "OTRO" && observServicio) {
                const fieldServicioOtro = document.getElementById("fieldServicioOtro");
                const inputServicioOtro = document.getElementById("aServicioOtro");
                if (fieldServicioOtro && inputServicioOtro) {
                    fieldServicioOtro.removeAttribute("hidden");
                    fieldServicioOtro.style.display = "";
                    inputServicioOtro.value = observServicio;
                }
            }
        });
    }
    
    manejarCambioServicios();
    historicoModal.hidden = false;
}

function cerrarModalHistorico() {
    historicoForm.reset();
    idAtencionEnEdicion = 0;
    btnSubmitHistorico.textContent = "Registrar atencion";
    historicoModal.hidden = true;
}

function abrirModalEditarDiagnosticoActual() {
    if (!pacienteSeleccionado) return;
    const actual = obtenerDiscapacidadDetallePaciente(pacienteSeleccionado);
    document.getElementById("doIdPaciente").value = pacienteSeleccionado.dni || "";
    document.getElementById("doDescripcion").value = actual.descripcionDiagnostico || "";
    document.getElementById("doCodigoConadis").value = actual.codigoConadis || "";
    // Solo establecer grado de discapacidad si es válido, de lo contrario dejar vacío (SELECCIONE)
    const gradoDiscapacidadActual = actual.gradoDiscapacidad || "";
    if (gradoDiscapacidadActual === "LEVE" || gradoDiscapacidadActual === "MODERADO" || gradoDiscapacidadActual === "SEVERO") {
        document.getElementById("doGradoDiscapacidad").value = gradoDiscapacidadActual;
    } else {
        document.getElementById("doGradoDiscapacidad").value = "";
    }
    document.getElementById("doIdDiscapacidad").value = actual.idDiscapacidad || "";
    document.getElementById("doSeguroSalud").value = actual.seguroSalud || "";
    const doCarnetInput = document.getElementById("doArchivoCarnetConadis");
    const doCertInput = document.getElementById("doArchivoCertificadoDiscapacidad");
    if (doCarnetInput && doCarnetInput.type === "file") {
        doCarnetInput.value = "";
    }
    if (doCertInput && doCertInput.type === "file") {
        doCertInput.value = "";
    }
    diagnosticoOmapedTitle.textContent = "Editar Discapacidad";
    diagnosticoOmapedModal.hidden = false;
}

function cerrarModalDiagnosticoOmaped() {
    diagnosticoOmapedForm.reset();
    diagnosticoOmapedModal.hidden = true;
}


function cerrarModalDiagnostico() {
    diagnosticoModal.hidden = true;
}

function manejarCambioZonaRegistro() {
    const campoOtroDistrito = document.getElementById("fieldOtroDistrito");
    const selectZona = document.getElementById("regZona");
    const selectOtroDistrito = document.getElementById("regOtroDistrito");
    
    if (campoOtroDistrito && selectZona) {
        const valorZona = String(selectZona.value || "").trim();
        const mostrarOtroDistrito = valorZona === "OTRO DISTRITO";
        
        if (mostrarOtroDistrito) {
            campoOtroDistrito.removeAttribute("hidden");
            campoOtroDistrito.style.display = "";
            // Cargar distritos en el combo si aún no están cargados
            if (selectOtroDistrito && selectOtroDistrito.options.length <= 1 && distritosReferencia.length > 0) {
                distritosReferencia.forEach((distrito) => {
                    selectOtroDistrito.innerHTML += `<option value="${distrito.id}">${escapeHtml(distrito.nombre)}</option>`;
                });
            }
        } else {
            campoOtroDistrito.setAttribute("hidden", "");
            campoOtroDistrito.style.display = "none";
            if (selectOtroDistrito) {
                selectOtroDistrito.value = "";
            }
        }
    }
}

function abrirFormularioPaciente() {
    modoPacienteEdicion = false;
    idPacienteEnEdicion = 0;
    ultimoDniBuscado = "";
    vistaLista.hidden = true;
    panelPaciente.hidden = true;
    
    const carnetLabel = document.getElementById("regCarnetConadisArchivoLabel");
    const certLabel = document.getElementById("regCertificadoDiscapacidadArchivoLabel");
    if (carnetLabel) carnetLabel.textContent = "Carnet Conadis (archivo)";
    if (certLabel) certLabel.textContent = "Certificado de discapacidad (archivo)";
    panelDatosAdicionales.setAttribute("hidden", "");
    cardNuevoPaciente.hidden = false;
    if (regZona) {
        regZona.value = "";
        manejarCambioZonaRegistro();
    }
    pageTitle.textContent = "Registro de Usuario";
    if (btnSubmitNuevoPaciente) btnSubmitNuevoPaciente.textContent = "Guardar usuario";
    renderParientesTabla();
    marcarTiposDiscapacidadSeleccionados([]);
    if (regActualizarDatosPersona) {
        regActualizarDatosPersona.checked = false;
    }
    // Limpiar foto al abrir formulario nuevo
    fotoActual = null;
    if (fotoPreviewImg) {
        fotoPreviewImg.style.display = "none";
        fotoPreviewImg.src = "";
    }
    if (fotoPreviewText) {
        fotoPreviewText.style.display = "block";
        fotoPreviewText.textContent = "Subir Foto";
    }
    if (btnEliminarFoto) {
        btnEliminarFoto.style.display = "none";
    }
    if (regFoto) {
        regFoto.value = "";
    }
    // Habilitar campo DNI para nuevo paciente
    const regNumDocEl = document.getElementById("regNumDoc");
    if (regNumDocEl) {
        regNumDocEl.disabled = false;
        regNumDocEl.readOnly = false;
    }
    animarTransicionVista(cardNuevoPaciente);
    
    if (regNumDocEl && !regNumDocEl.hasAttribute("data-listener-registrado")) {
        regNumDocEl.setAttribute("data-listener-registrado", "1");
        regNumDocEl.addEventListener("input", (event) => {
            let valor = regNumDocEl.value.replace(/\D/g, '').substring(0, 8);
            if (regNumDocEl.value !== valor) {
                const cursorPos = regNumDocEl.selectionStart;
                regNumDocEl.value = valor;
                regNumDocEl.setSelectionRange(Math.min(cursorPos, valor.length), Math.min(cursorPos, valor.length));
            }
            if (valor.length === 8) {
                buscarDocumentoAutomatico();
            }
        });
    }
}

function cerrarFormularioPaciente() {
    nuevoPacienteForm.reset();
    // Limpiar foto
    fotoActual = null;
    if (fotoPreviewImg) {
        fotoPreviewImg.style.display = "none";
        fotoPreviewImg.src = "";
    }
    if (fotoPreviewText) {
        fotoPreviewText.style.display = "block";
        fotoPreviewText.textContent = "Subir Foto";
    }
    if (btnEliminarFoto) {
        btnEliminarFoto.style.display = "none";
    }
    if (regFoto) {
        regFoto.value = "";
    }
    ultimoDniBuscado = "";
    parientesRegistro = [];
    modoPacienteEdicion = false;
    idPacienteEnEdicion = 0;
    
    const carnetLabel = document.getElementById("regCarnetConadisArchivoLabel");
    const certLabel = document.getElementById("regCertificadoDiscapacidadArchivoLabel");
    if (carnetLabel) carnetLabel.textContent = "Carnet Conadis (archivo)";
    if (certLabel) certLabel.textContent = "Certificado de discapacidad (archivo)";
    
    renderParientesTabla();
    marcarTiposDiscapacidadSeleccionados([]);
    cardNuevoPaciente.hidden = true;
    vistaLista.hidden = false;
    pageTitle.textContent = "Lista de Usuarios";
    if (btnSubmitNuevoPaciente) btnSubmitNuevoPaciente.textContent = "Guardar usuario";
    panelDatosAdicionales.setAttribute("hidden", "");
    animarTransicionVista(vistaLista);
}


function mostrarVistaPreviaFoto(file) {
    if (!file || !file.type.startsWith('image/')) {
        mostrarToast("error", "Por favor selecciona una imagen válida");
        return;
    }
    
    const reader = new FileReader();
    reader.onload = (e) => {
        fotoPreviewImg.src = e.target.result;
        fotoPreviewImg.style.display = "block";
        if (fotoPreviewText) {
            fotoPreviewText.style.display = "none";
        }
        if (btnEliminarFoto) {
            btnEliminarFoto.style.display = "block";
        }
        fotoActual = null; // Se enviará el archivo nuevo
    };
    reader.readAsDataURL(file);
}

function mostrarFotoPaciente(fotoNombre) {
    const panelFotoPaciente = document.getElementById("panelFotoPaciente");
    const fotoPacienteImg = document.getElementById("fotoPacienteImg");
    const fotoPacienteTexto = document.getElementById("fotoPacienteTexto");
    const nombreCompletoPaciente = document.getElementById("nombreCompletoPaciente");
    const dniPaciente = document.getElementById("dniPaciente");
    const generoPaciente = document.getElementById("generoPaciente");
    const celularPaciente = document.getElementById("celularPaciente");
    const direccionPaciente = document.getElementById("direccionPaciente");
    const edadPaciente = document.getElementById("edadPaciente");
    
    if (!panelFotoPaciente || !pacienteSeleccionado) return;
    
    // Siempre mostrar el panel
    panelFotoPaciente.removeAttribute("hidden");
    
    if (!(fotoNombre && fotoNombre.trim() !== '' && aplicarImagenPrivada(fotoPacienteImg, fotoPacienteTexto, "foto", fotoNombre))) {
        fotoPacienteImg.style.display = "none";
        if (fotoPacienteTexto) {
            fotoPacienteTexto.style.display = "block";
        }
    }
    
    // Llenar datos personales
    if (nombreCompletoPaciente) {
        nombreCompletoPaciente.textContent = obtenerNombreCompletoPersona(pacienteSeleccionado);
    }
    if (dniPaciente) {
        dniPaciente.textContent = pacienteSeleccionado.dni || "-";
    }
    if (generoPaciente) {
        generoPaciente.textContent = pacienteSeleccionado.genero || "-";
    }
    if (celularPaciente) {
        celularPaciente.textContent = pacienteSeleccionado.telefono || "-";
    }
    if (direccionPaciente) {
        direccionPaciente.textContent = pacienteSeleccionado.direccion || "-";
    }
    
    // Calcular y mostrar edad a partir de la fecha de nacimiento
    if (edadPaciente) {
        let edad = "-";
        if (pacienteSeleccionado.fechaNacimiento && pacienteSeleccionado.fechaNacimiento.trim() !== '') {
            const fechaNac = new Date(pacienteSeleccionado.fechaNacimiento);
            if (!isNaN(fechaNac.getTime())) {
                const hoy = new Date();
                let edadCalculada = hoy.getFullYear() - fechaNac.getFullYear();
                const mesActual = hoy.getMonth();
                const diaActual = hoy.getDate();
                const mesNac = fechaNac.getMonth();
                const diaNac = fechaNac.getDate();
                
                // Ajustar si aún no ha cumplido años este año
                if (mesActual < mesNac || (mesActual === mesNac && diaActual < diaNac)) {
                    edadCalculada--;
                }
                
                edad = edadCalculada + " años";
            }
        }
        edadPaciente.textContent = edad;
    }
}

function mostrarDatosAdicionalesDetalle(paciente) {
    // Obtener nombre del sector
    let nombreSector = "-";
    if (paciente.idSector && sectoresReferencia && sectoresReferencia.length > 0) {
        const sector = sectoresReferencia.find(s => s.id === Number(paciente.idSector));
        if (sector) {
            nombreSector = sector.nombre;
        }
    }
    
    // Formatear fecha de nacimiento
    let fechaNacFormateada = "-";
    if (paciente.fechaNacimiento && paciente.fechaNacimiento.trim() !== '') {
        const fecha = new Date(paciente.fechaNacimiento);
        if (!isNaN(fecha.getTime())) {
            fechaNacFormateada = fecha.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }
    }
    
    // Llenar campos
    const detalleSector = document.getElementById("detalleSector");
    const detalleZona = document.getElementById("detalleZona");
    const detalleFechaNac = document.getElementById("detalleFechaNac");
    const detalleUbicacion = document.getElementById("detalleUbicacion");
    const detalleLatitud = document.getElementById("detalleLatitud");
    const detalleLongitud = document.getElementById("detalleLongitud");
    
    if (detalleSector) detalleSector.textContent = nombreSector;
    if (detalleZona) detalleZona.textContent = paciente.zona || "-";
    if (detalleFechaNac) detalleFechaNac.textContent = fechaNacFormateada;
    
    // Mostrar ubicación como enlace si es una URL válida
    if (detalleUbicacion) {
        const ubicacion = paciente.ubicacion || "";
        if (ubicacion.trim() !== "" && (ubicacion.startsWith("http://") || ubicacion.startsWith("https://") || ubicacion.startsWith("www."))) {
            // Es una URL válida, crear enlace
            const url = ubicacion.startsWith("www.") ? "https://" + ubicacion : ubicacion;
            detalleUbicacion.innerHTML = `<a href="${url}" target="_blank" rel="noopener noreferrer" style="color: #0066cc; text-decoration: underline;">${ubicacion}</a>`;
        } else {
            // No es una URL o está vacía
            detalleUbicacion.textContent = ubicacion || "-";
        }
    }
    
    if (detalleLatitud) detalleLatitud.textContent = paciente.latitud || "-";
    if (detalleLongitud) detalleLongitud.textContent = paciente.longitud || "-";
}

function mostrarDiagnosticos(paciente) {
    pacienteSeleccionado = paciente;
    panelPaciente.hidden = false;
    panelTitulo.textContent = `Usuario ${obtenerNombreCompletoPersona(paciente)}`;
    panelDatosAdicionales.removeAttribute("hidden");
    
    // Mostrar foto del paciente (siempre, con marco vacío si no hay foto)
    mostrarFotoPaciente(paciente.foto || '');
    
    // Mostrar datos adicionales
    mostrarDatosAdicionalesDetalle(paciente);
    
    panelDiagnosticos.removeAttribute("hidden");
    panelFamiliar.removeAttribute("hidden");
    renderParientesDetalle(paciente);
    renderDiagnosticoDetalle(obtenerHistoricoPaciente(paciente), paciente);
    renderHistoricoTabla(paciente);

    // === QR del paciente ===
    const qrWrapper = document.getElementById("qrPacienteWrapper");
    const qrImg = document.getElementById("qrPacienteImg");
    const btnDescQr = document.getElementById("btnDescargarQrPaciente");

    // Debug: verificar qué datos tenemos
    console.log("Paciente datos:", paciente);
    console.log("tokenQr:", paciente.tokenQr);

    if (qrWrapper && qrImg && btnDescQr) {
        // Siempre mostrar el wrapper (para que el usuario vea que existe el campo)
        qrWrapper.style.display = "block";
        
        if (paciente.tokenQr && paciente.tokenQr.trim() !== '') {
            // Preferir URL entregada por backend (evita errores de ruta)
            const urlQrPng = paciente.urlQrPng
                ? (/^https?:\/\//i.test(String(paciente.urlQrPng))
                    ? String(paciente.urlQrPng)
                    : `${window.location.origin}/${String(paciente.urlQrPng).replace(/^\/+/, '')}`)
                : (paciente.tokenQr
                    ? omapedUrl("qr_png", "id_paciente=" + encodeURIComponent(paciente.idPaciente))
                    : "");
            
            console.log("URL QR construida:", urlQrPng);
            
            qrImg.src = urlQrPng;
            qrImg.style.display = "block";
            btnDescQr.style.display = "block";
            
            qrImg.onerror = () => {
                // Si la imagen no carga, mostrar mensaje pero mantener visible
                console.error("No se pudo cargar la imagen QR:", urlQrPng);
                qrImg.alt = "QR no disponible";
                qrWrapper.style.display = "block";
            };
            qrImg.onload = () => {
                // Si la imagen carga correctamente
                console.log("Imagen QR cargada correctamente");
                qrWrapper.style.display = "block";
            };

            btnDescQr.onclick = async () => {
                try {
                    if (!paciente.idPaciente) {
                        console.error("No hay idPaciente:", paciente);
                        mostrarToast("error", "No se pudo identificar al paciente.");
                        return;
                    }
                    
                    // Descargar desde endpoint usando fetch para mejor control
                    const urlDownload = omapedUrl("qr_png", "id_paciente=" + encodeURIComponent(paciente.idPaciente));
                    console.log("Descargando QR desde:", urlDownload);
                    
                    const response = await fetch(urlDownload);
                    console.log("Respuesta del servidor:", response.status, response.statusText);
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error("Error al descargar QR:", response.status, errorText);
                        mostrarToast("error", "No se pudo descargar el QR. " + (errorText || "Error desconocido"));
                        return;
                    }
                    
                    // Verificar que sea una imagen
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('image')) {
                        const errorText = await response.text();
                        console.error("El servidor no devolvió una imagen:", contentType, errorText);
                        mostrarToast("error", "El servidor no devolvió una imagen válida.");
                        return;
                    }
                    
                    // Obtener el blob de la imagen
                    const blob = await response.blob();
                    console.log("Blob recibido, tamaño:", blob.size, "bytes");
                    
                    if (blob.size === 0) {
                        mostrarToast("error", "El archivo QR está vacío.");
                        return;
                    }
                    
                    // Crear URL temporal y descargar
                    const urlBlob = window.URL.createObjectURL(blob);
                    const a = document.createElement("a");
                    a.href = urlBlob;
                    a.download = `qr_omaped_${paciente.dni || "sin_dni"}.jpg`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    
                    // Limpiar URL temporal después de un momento
                    setTimeout(() => window.URL.revokeObjectURL(urlBlob), 100);
                    
                    console.log("Descarga iniciada correctamente");
                } catch (error) {
                    console.error("Error al descargar QR:", error);
                    mostrarToast("error", "Error al descargar el QR: " + error.message);
                }
            };
        } else {
            console.warn("No hay tokenQr para este paciente. tokenQr:", paciente.tokenQr);
            // No romper el HTML del bloque QR (no usar innerHTML). Solo ocultar imagen/botón.
            qrImg.style.display = "none";
            btnDescQr.style.display = "none";
        }
    } else {
        console.error("Elementos QR no encontrados:", {
            qrWrapper: !!qrWrapper,
            qrImg: !!qrImg,
            btnDescQr: !!btnDescQr
        });
    }
}

function construirFlagsTipoDiscapacidad(tiposSeleccionados) {
    const tipos = new Set((Array.isArray(tiposSeleccionados) ? tiposSeleccionados : []).map((item) => String(item).trim().toUpperCase()));
    return {
        motora: tipos.has("MOTORA"),
        sensorial: tipos.has("SENSORIAL"),
        intelectual: tipos.has("INTELECTUAL"),
        mental: tipos.has("MENTAL")
    };
}

function obtenerTiposDiscapacidadSeleccionados() {
    return Array.from(document.querySelectorAll("input[name='regTiposDiscapacidad[]']:checked"))
        .map((item) => item.value.trim())
        .filter(Boolean);
}

function construirPayloadPaciente() {
    const tipos = obtenerTiposDiscapacidadSeleccionados();
    const flags = construirFlagsTipoDiscapacidad(tipos);
    return {
        idPaciente: modoPacienteEdicion ? idPacienteEnEdicion : undefined,
        dni: document.getElementById("regNumDoc").value.trim(),
        nombres: document.getElementById("regNombres").value.trim().toUpperCase(),
        apePaterno: document.getElementById("regApePaterno").value.trim().toUpperCase(),
        apeMaterno: document.getElementById("regApeMaterno").value.trim().toUpperCase(),
        telefono: document.getElementById("regCelular").value.trim(),
        genero: regGenero.value,
        fechaNacimiento: document.getElementById("regFechaNac") ? document.getElementById("regFechaNac").value : "",
        actualizarDatosPersona: regActualizarDatosPersona ? regActualizarDatosPersona.checked : false,
        idSector: regSector.value && regSector.value !== "" ? Number(regSector.value) : (sectoresReferencia && sectoresReferencia.length > 0 ? sectoresReferencia[0].id : null),
        zona: regZona.value,
        distritoOtro: regZona.value === "OTRO DISTRITO" && regOtroDistrito ? Number(regOtroDistrito.value || 0) : null,
        direccion: document.getElementById("regDireccionPersona") ? document.getElementById("regDireccionPersona").value.trim().toUpperCase() : "",
        ubicacion: document.getElementById("regUbicacion") ? document.getElementById("regUbicacion").value.trim() : "",
        latitud: document.getElementById("regLatitud") ? document.getElementById("regLatitud").value.trim() : "",
        longitud: document.getElementById("regLongitud") ? document.getElementById("regLongitud").value.trim() : "",
        discapacidadDetalle: (() => {
            const detalleActual = modoPacienteEdicion && idPacienteEnEdicion > 0 
                ? obtenerDiscapacidadDetallePaciente(pacienteSeleccionado) 
                : {};
            return {
                idDiscapacidad: detalleActual.idDiscapacidad || undefined,
                descripcionDiagnostico: document.getElementById("regDescripcionDiscapacidad").value.trim().toUpperCase(),
                codigoConadis: document.getElementById("regCodigoConadis").value.trim().toUpperCase(),
                gradoDiscapacidad: document.getElementById("regGradoDiscapacidad").value,
                seguroSalud: document.getElementById("regSeguroSalud").value.trim().toUpperCase(),
                archivoCarnetConadis: (() => {
                    const fileInput = document.getElementById("regCarnetConadisArchivo");
                    if (fileInput && fileInput.files && fileInput.files[0]) {
                        return fileInput.files[0].name;
                    }
                    return detalleActual.archivoCarnetConadis || "";
                })(),
                archivoCertificadoDiscapacidad: (() => {
                    const fileInput = document.getElementById("regCertificadoDiscapacidadArchivo");
                    if (fileInput && fileInput.files && fileInput.files[0]) {
                        return fileInput.files[0].name;
                    }
                    return detalleActual.archivoCertificadoDiscapacidad || "";
                })(),
                ...flags
            };
        })(),
        familiares: parientesRegistro.map((item) => ({ ...item })),
        foto: fotoActual || ""
    };
}

function llenarFormularioPaciente(paciente) {
    const regNumDocInput = document.getElementById("regNumDoc");
    regNumDocInput.value = paciente.dni || "";
    // Deshabilitar campo DNI en modo edición
    if (modoPacienteEdicion) {
        regNumDocInput.disabled = true;
        regNumDocInput.readOnly = true;
    } else {
        regNumDocInput.disabled = false;
        regNumDocInput.readOnly = false;
    }
    document.getElementById("regNombres").value = (paciente.nombres || "").toUpperCase();
    document.getElementById("regApePaterno").value = (paciente.apePaterno || "").toUpperCase();
    document.getElementById("regApeMaterno").value = (paciente.apeMaterno || "").toUpperCase();
    document.getElementById("regCelular").value = paciente.telefono || "";
    // Solo establecer género si es válido, de lo contrario dejar vacío (SELECCIONE)
    const generoPaciente = paciente.genero || "";
    if (generoPaciente === "FEMENINO" || generoPaciente === "MASCULINO") {
        regGenero.value = generoPaciente;
    } else {
        regGenero.value = "";
    }
    if (document.getElementById("regFechaNac")) {
        document.getElementById("regFechaNac").value = paciente.fechaNacimiento || "";
    }
    regZona.value = paciente.zona || "";
    manejarCambioZonaRegistro();
    if (regOtroDistrito && paciente.distritoOtro && paciente.distritoOtro > 0) {
        regOtroDistrito.value = String(paciente.distritoOtro);
    } else if (regOtroDistrito) {
        regOtroDistrito.value = "";
    }
    regSector.value = String(paciente.idSector || sectoresReferencia[0]?.id || "");
    if (document.getElementById("regDireccionPersona")) {
        document.getElementById("regDireccionPersona").value = (paciente.direccion || "").toUpperCase();
    }
    if (document.getElementById("regUbicacion")) {
        document.getElementById("regUbicacion").value = paciente.ubicacion || ""; // Mantener URL sin convertir
    }
    if (document.getElementById("regLatitud")) {
        document.getElementById("regLatitud").value = paciente.latitud || "";
    }
    if (document.getElementById("regLongitud")) {
        document.getElementById("regLongitud").value = paciente.longitud || "";
    }
    
    // Cargar foto si existe
    fotoActual = paciente.foto || null;
    if (fotoActual && fotoActual.trim() !== '' && aplicarImagenPrivada(fotoPreviewImg, fotoPreviewText, "foto", fotoActual, "Actualizar Foto")) {
        if (btnEliminarFoto) {
            btnEliminarFoto.style.display = "block";
        }
    } else {
        fotoPreviewImg.style.display = "none";
        if (fotoPreviewText) {
            fotoPreviewText.style.display = "block";
            fotoPreviewText.textContent = "Actualizar Foto";
        }
        if (btnEliminarFoto) {
            btnEliminarFoto.style.display = "none";
        }
        fotoActual = null;
    }

    const detalleDiscapacidad = obtenerDiscapacidadDetallePaciente(paciente);
    document.getElementById("regDescripcionDiscapacidad").value = (detalleDiscapacidad.descripcionDiagnostico || "").toUpperCase();
    document.getElementById("regCodigoConadis").value = (detalleDiscapacidad.codigoConadis || "").toUpperCase();
    // Solo establecer grado de discapacidad si es válido, de lo contrario dejar vacío (SELECCIONE)
    const gradoDiscapacidad = detalleDiscapacidad.gradoDiscapacidad || "";
    if (gradoDiscapacidad === "LEVE" || gradoDiscapacidad === "MODERADO" || gradoDiscapacidad === "SEVERO") {
        document.getElementById("regGradoDiscapacidad").value = gradoDiscapacidad;
    } else {
        document.getElementById("regGradoDiscapacidad").value = "";
    }
    document.getElementById("regSeguroSalud").value = (detalleDiscapacidad.seguroSalud || "").toUpperCase();
    
    const carnetInput = document.getElementById("regCarnetConadisArchivo");
    const certInput = document.getElementById("regCertificadoDiscapacidadArchivo");
    const carnetLabel = document.getElementById("regCarnetConadisArchivoLabel");
    const certLabel = document.getElementById("regCertificadoDiscapacidadArchivoLabel");
    
    if (carnetInput && carnetInput.type === "file") {
        carnetInput.value = "";
    }
    if (certInput && certInput.type === "file") {
        certInput.value = "";
    }
    
    const archivoCarnet = detalleDiscapacidad.archivoCarnetConadis || "";
    const archivoCert = detalleDiscapacidad.archivoCertificadoDiscapacidad || "";
    
    if (modoPacienteEdicion) {
        if (carnetLabel) {
            carnetLabel.textContent = archivoCarnet && String(archivoCarnet).trim() !== "" ? "Cambiar Carnet Conadis" : "Subir Carnet Conadis";
        }
        if (certLabel) {
            certLabel.textContent = archivoCert && String(archivoCert).trim() !== "" ? "Cambiar Certificado de discapacidad" : "Subir Certificado de discapacidad";
        }
    } else {
        if (carnetLabel) {
            carnetLabel.textContent = "Carnet Conadis (archivo)";
        }
        if (certLabel) {
            certLabel.textContent = "Certificado de discapacidad (archivo)";
        }
    }

    parientesRegistro = obtenerFamiliaresPaciente(paciente).map((item) => ({ ...item }));
    renderParientesTabla();
    marcarTiposDiscapacidadSeleccionados(obtenerTiposDesdeFlags(detalleDiscapacidad));
}

function cargarCatalogosRegistro() {
    regSector.innerHTML = '<option value="">-- Seleccione --</option>';
    sectoresReferencia.forEach((item) => {
        regSector.innerHTML += `<option value="${item.id}">${escapeHtml(item.nombre)}</option>`;
    });

    parienteParentesco.innerHTML = '<option value="">-- Seleccione --</option>';
    parentescosReferencia.forEach((item) => {
        parienteParentesco.innerHTML += `<option value="${item.id}">${escapeHtml(item.nombre)}</option>`;
    });

    const sedeSelect = document.getElementById("aSedeAtencion");
    if (sedeSelect) {
        sedeSelect.innerHTML = '<option value="">-- Seleccione --</option>';
        sedesReferencia.forEach((item) => {
            sedeSelect.innerHTML += `<option value="${escapeHtml(item.nombre)}">${escapeHtml(item.nombre)}</option>`;
        });
    }

    const regOtroDistrito = document.getElementById("regOtroDistrito");
    if (regOtroDistrito) {
        regOtroDistrito.innerHTML = '<option value="">-- Seleccione --</option>';
        distritosReferencia.forEach((distrito) => {
            regOtroDistrito.innerHTML += `<option value="${distrito.id}">${escapeHtml(distrito.nombre)}</option>`;
        });
    }
}

function cargarServiciosCheckboxes() {
    const serviciosGrid = document.getElementById("serviciosCheckboxGrid");
    if (!serviciosGrid) return;
    
    serviciosGrid.innerHTML = "";
    serviciosReferencia.forEach((servicio) => {
        const label = document.createElement("label");
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.name = "aServicios[]";
        checkbox.value = servicio.id;
        checkbox.dataset.nombre = servicio.nombre;
        checkbox.addEventListener("change", manejarCambioServicios);
        label.appendChild(checkbox);
        label.appendChild(document.createTextNode(" " + servicio.nombre));
        serviciosGrid.appendChild(label);
    });
}

function manejarCambioServicios() {
    const fieldServicioOtro = document.getElementById("fieldServicioOtro");
    const inputServicioOtro = document.getElementById("aServicioOtro");
    const checkboxOtro = Array.from(document.querySelectorAll("input[name='aServicios[]']")).find(cb => {
        const nombre = cb.dataset.nombre || cb.value;
        return nombre === "OTRO" || nombre.toUpperCase() === "OTRO";
    });
    
    if (fieldServicioOtro && checkboxOtro) {
        const mostrarOtro = checkboxOtro.checked;
        if (mostrarOtro) {
            fieldServicioOtro.removeAttribute("hidden");
            fieldServicioOtro.style.display = "";
        } else {
            fieldServicioOtro.setAttribute("hidden", "");
            fieldServicioOtro.style.display = "none";
            if (inputServicioOtro) {
                inputServicioOtro.value = "";
            }
        }
    }
}

function setCargandoTabla() {
    tbody.innerHTML = '<tr class="empty-row"><td colspan="8">Cargando usuarios OMAPED...</td></tr>';
}

function setTablaLoadingVisible(visible) {
    const overlay = document.getElementById("tablaLoading");
    if (!overlay) return;
    if (visible) {
        overlay.classList.add("is-visible");
        overlay.setAttribute("aria-hidden", "false");
    } else {
        overlay.classList.remove("is-visible");
        overlay.setAttribute("aria-hidden", "true");
    }
}

function nextPaint() {
    return new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
}

let tablaLoadingShownAt = 0;
let tablaLoadingHideTimer = null;
function tablaLoadingShowMin() {
    if (tablaLoadingHideTimer) {
        clearTimeout(tablaLoadingHideTimer);
        tablaLoadingHideTimer = null;
    }
    tablaLoadingShownAt = Date.now();
    setTablaLoadingVisible(true);
}
function tablaLoadingHideMin() {
    const minMs = 350;
    const elapsed = Date.now() - (tablaLoadingShownAt || 0);
    const wait = Math.max(0, minMs - elapsed);
    if (tablaLoadingHideTimer) clearTimeout(tablaLoadingHideTimer);
    tablaLoadingHideTimer = setTimeout(() => {
        setTablaLoadingVisible(false);
        tablaLoadingHideTimer = null;
    }, wait);
}

function normalizarSoloDigitos(valor, maxLen = 8) {
    return String(valor || "")
        .replace(/\D+/g, "")
        .slice(0, maxLen);
}

function limpiarCamposDatosPersonales() {
    document.getElementById("regNombres").value = "";
    document.getElementById("regApePaterno").value = "";
    document.getElementById("regApeMaterno").value = "";
    document.getElementById("regCelular").value = "";
    if (document.getElementById("regDireccionPersona")) {
        document.getElementById("regDireccionPersona").value = "";
    }
    if (regGenero) {
        regGenero.value = "";
    }
}

function aplicarDatosPersonaPorDocumento(persona) {
    if (!persona) return;
    if (!String(document.getElementById("regNombres").value || "").trim()) {
        document.getElementById("regNombres").value = persona.nombres || "";
    }
    if (!String(document.getElementById("regApePaterno").value || "").trim()) {
        document.getElementById("regApePaterno").value = persona.apePaterno || "";
    }
    if (!String(document.getElementById("regApeMaterno").value || "").trim()) {
        document.getElementById("regApeMaterno").value = persona.apeMaterno || "";
    }
    if (!String(document.getElementById("regCelular").value || "").trim()) {
        document.getElementById("regCelular").value = persona.telefono || "";
    }
    if (document.getElementById("regDireccionPersona") && !String(document.getElementById("regDireccionPersona").value || "").trim()) {
        document.getElementById("regDireccionPersona").value = persona.direccion || "";
    }
    const generoValor = String(persona.genero || "").trim();
    if (generoValor === "MASCULINO") {
        regGenero.value = "MASCULINO";
    } else if (generoValor === "FEMENINO") {
        regGenero.value = "FEMENINO";
    } else if (generoValor === "1") {
        regGenero.value = "MASCULINO";
    } else if (generoValor === "2") {
        regGenero.value = "FEMENINO";
    } else {
        regGenero.value = "";
    }
}

function limpiarCamposDatosPariente() {
    document.getElementById("parienteNombres").value = "";
    document.getElementById("parienteApePaterno").value = "";
    document.getElementById("parienteApeMaterno").value = "";
    document.getElementById("parienteTelefono").value = "";
    document.getElementById("parienteDireccion").value = "";
}

function aplicarDatosParientePorDocumento(persona) {
    if (!persona) return;
    if (!String(document.getElementById("parienteNombres").value || "").trim()) {
        document.getElementById("parienteNombres").value = persona.nombres || "";
    }
    if (!String(document.getElementById("parienteApePaterno").value || "").trim()) {
        document.getElementById("parienteApePaterno").value = persona.apePaterno || "";
    }
    if (!String(document.getElementById("parienteApeMaterno").value || "").trim()) {
        document.getElementById("parienteApeMaterno").value = persona.apeMaterno || "";
    }
    if (!String(document.getElementById("parienteTelefono").value || "").trim()) {
        document.getElementById("parienteTelefono").value = persona.telefono || "";
    }
    if (!String(document.getElementById("parienteDireccion").value || "").trim()) {
        document.getElementById("parienteDireccion").value = persona.direccion || "";
    }
}

let ultimoDniParienteBuscado = "";
let busquedaParienteDocumentoEnCurso = false;

async function buscarDocumentoParienteAutomatico() {
    if (!parienteDocumentoInput) {
        console.warn("parienteDocumentoInput no encontrado");
        return;
    }
    // Obtener solo números del valor actual
    const dni = parienteDocumentoInput.value.replace(/\D/g, '');
    
    if (dni.length === 0) {
        ultimoDniParienteBuscado = "";
        limpiarCamposDatosPariente();
        return;
    }

    if (dni.length !== 8) {
        ultimoDniParienteBuscado = "";
        return;
    }
    if (busquedaParienteDocumentoEnCurso || ultimoDniParienteBuscado === dni) {
        return;
    }

    busquedaParienteDocumentoEnCurso = true;
    try {
        const url = omapedUrl("buscar_documento", "dni=" + encodeURIComponent(dni));
        const data = await apiRequest(url);
        ultimoDniParienteBuscado = dni;
        if (data && data.found === true && data.persona) {
            aplicarDatosParientePorDocumento(data.persona);
            mostrarToast("success", "Persona encontrada");
        } else if (data && data.found === false) {
            mostrarToast("error", "Sin resultado, ingrese manualmente los datos para registrar a la persona");
        }
    } catch (error) {
        console.error("Error al buscar documento:", error);
        ultimoDniParienteBuscado = "";
        mostrarToast("error", "Error al buscar documento. Ingrese manualmente los datos.");
    } finally {
        busquedaParienteDocumentoEnCurso = false;
    }
}

async function buscarDocumentoAutomatico() {
    if (!regNumDocInput) {
        console.warn("regNumDocInput no encontrado");
        return;
    }
    const dni = regNumDocInput.value.replace(/\D/g, '');
    
    if (dni.length === 0) {
        ultimoDniBuscado = "";
        limpiarCamposDatosPersonales();
        return;
    }

    if (dni.length !== 8) {
        ultimoDniBuscado = "";
        return;
    }
    if (busquedaDocumentoEnCurso || ultimoDniBuscado === dni) {
        return;
    }

    busquedaDocumentoEnCurso = true;
    try {
        const url = omapedUrl("buscar_documento", "dni=" + encodeURIComponent(dni));
        const data = await apiRequest(url);
        ultimoDniBuscado = dni;
        if (data && data.found === true && data.persona) {
            aplicarDatosPersonaPorDocumento(data.persona);
            mostrarToast("success", "Persona encontrada");
        } else if (data && data.found === false) {
            mostrarToast("error", "Sin resultado, ingrese manualmente los datos para registrar a la persona");
        }
    } catch (error) {
        console.error("Error al buscar documento:", error);
        ultimoDniBuscado = "";
        mostrarToast("error", "Error al buscar documento. Ingrese manualmente los datos.");
    } finally {
        busquedaDocumentoEnCurso = false;
    }
}

async function apiRequest(url, options = {}) {
    const config = {
        method: options.method || "GET",
        headers: { Accept: "application/json" }
    };

    if (options.body) {
        config.headers["Content-Type"] = "application/json";
        config.body = JSON.stringify(options.body);
    }

    if (config.method !== "GET") {
        config.headers["X-CSRF-TOKEN"] = csrfToken;
    }

    const response = await fetch(url, config);
    const payload = await response.json();

    if (!response.ok || !payload.success) {
        throw new Error(payload.error || "Error inesperado.");
    }

    return payload.data;
}

async function apiRequestFormData(url, formData) {
    const response = await fetch(url, {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": csrfToken
        },
        body: formData
    });

    let payload;
    let responseText = '';
    try {
        responseText = await response.text();
        if (!responseText || responseText.trim() === '') {
            throw new Error("El servidor no devolvió una respuesta válida.");
        }
        payload = JSON.parse(responseText);
    } catch (e) {
        console.error("Error al parsear respuesta:", e);
        console.error("Respuesta del servidor:", responseText);
        throw new Error("Error al procesar respuesta del servidor: " + (e.message || "Respuesta inválida"));
    }

    if (!response.ok || !payload.success) {
        const errorMsg = payload?.error || payload?.message || "Error inesperado.";
        throw new Error(errorMsg);
    }

    return payload;
}

function obtenerPacientePorId(idPaciente) {
    return pacientes.find((item) => Number(item.idPaciente) === Number(idPaciente)) || null;
}

async function cargarCatalogos() {
    const data = await apiRequest(omapedUrl("catalogos"));
    sectoresReferencia = (data.sectores || []).map((item) => ({
        id: Number(item.id_sectr),
        nombre: item.nomb_sectr
    }));
    parentescosReferencia = (data.parentescos || []).map((item) => ({
        id: Number(item.id_tip_parntsc),
        nombre: item.nomb_parntsc
    }));
    sedesReferencia = (data.sedes || []).map((item) => ({
        id: Number(item.id_sede),
        nombre: item.nomb_sede
    }));
    distritosReferencia = (data.distritos || []).map((item) => ({
        id: Number(item.cdgo_dist || item.id_dist || item.id),
        nombre: item.de_dist || item.nombre || item.de_dist
    }));
    serviciosReferencia = (data.servicios || []).map((item) => ({
        id: Number(item.id_serv),
        nombre: item.nomb_serv
    }));
    cargarCatalogosRegistro();
    cargarServiciosCheckboxes();
}

/**
 * Parámetros GET de filtros (sin paginación ni exportar). Reutilizado por listado y Excel.
 */
function construirParamsListaPacientes() {
    const tipoFiltro = tipoFiltroSelect ? tipoFiltroSelect.value : "dni";
    const params = new URLSearchParams();
    params.append("tipoFiltro", tipoFiltro);

    const qValor = qInput ? qInput.value.trim() : "";
    const zonaValor = zonaSelect ? zonaSelect.value.trim() : "";

    switch (tipoFiltro) {
        case "dni":
        case "nombres":
        case "direccion":
        case "discapacidad":
            if (qValor !== "") {
                params.append("q", qValor);
            }
            break;
        case "zona":
            if (zonaValor !== "") {
                params.append("zona", zonaValor);
            }
            break;
        case "fecha":
            if (fechaInicioInput && fechaInicioInput.value) {
                params.append("fechaInicio", fechaInicioInput.value);
            }
            if (fechaFinInput && fechaFinInput.value) {
                params.append("fechaFin", fechaFinInput.value);
            }
            break;
        default:
            break;
    }
    return params;
}

/**
 * @param {number} [pagina] Página 1-based. Por defecto 1 al refrescar tras guardar, etc.
 */
async function cargarPacientes(pagina) {
    const p = typeof pagina === "number" && pagina >= 1 ? pagina : 1;
    try {
        tablaLoadingShowMin();
        await nextPaint();
        const params = construirParamsListaPacientes();
        params.append("pagina", String(p));
        params.append("porPagina", String(elementosPorPagina));

        const data = await apiRequest(omapedUrl("listar", params.toString()));
        pacientes = Array.isArray(data.items) ? data.items : [];
        totalPacientesLista = typeof data.total === "number" ? data.total : pacientes.length;
        paginaActual = typeof data.pagina === "number" && data.pagina >= 1 ? data.pagina : p;
        if (!pacientes.length && totalPacientesLista > 0 && p > 1) {
            await cargarPacientes(1);
            return;
        }
        renderTabla(pacientes);
    } finally {
        tablaLoadingHideMin();
    }
}

async function cargarDetallePaciente(idPaciente) {
    const data = await apiRequest(omapedUrl("detalle", "id_paciente=" + encodeURIComponent(idPaciente)));
    const idx = pacientes.findIndex((item) => Number(item.idPaciente) === Number(data.idPaciente));
    if (idx >= 0) pacientes[idx] = { ...pacientes[idx], ...data };
    else pacientes.unshift(data);
    return data;
}

async function aplicarFiltros() {
    paginaActual = 1;
    try {
        await cargarPacientes(1);
    } catch (error) {
        mostrarToast("error", error.message || "Error al cargar pacientes");
    }
}

function scrollAFamiliarCuidador() {
    // Llevar la vista al inicio de la sección donde está la tabla de familiares
    if (panelFamiliar && !panelFamiliar.hidden) {
        panelFamiliar.scrollIntoView({ behavior: "smooth", block: "start" });
        return;
    }
    const tabla = document.getElementById("parientesDetalleTable") || document.getElementById("parientesDetalleTbody");
    if (tabla) {
        tabla.scrollIntoView({ behavior: "smooth", block: "start" });
    }
}

async function persistirYRefrescarFamiliares(mensajeOk) {
    if (!pacienteSeleccionado) return;
    await persistirPacienteActual(false);
    const detalleActualizado = await cargarDetallePaciente(pacienteSeleccionado.idPaciente);
    pacienteSeleccionado = detalleActualizado;
    if (mensajeOk) mostrarToast("success", mensajeOk);
    renderParientesDetalle(pacienteSeleccionado);
    scrollAFamiliarCuidador();
}

// Por defecto no recarga la lista al persistir desde el panel.
async function persistirPacienteActual(refreshLista = false) {
    const familiaresFuente = Array.isArray(pacienteSeleccionado?.familiares)
        ? pacienteSeleccionado.familiares
        : obtenerFamiliaresPaciente(pacienteSeleccionado);
    const familiaresValidos = (Array.isArray(familiaresFuente) ? familiaresFuente : [])
        .filter((f) => Validaciones.dni(f?.numeroDocumento || "") !== null)
        .map((f) => ({
            ...f,
            numeroDocumento: Validaciones.dni(f?.numeroDocumento || ""),
            estado: !(f?.estado === false || f?.estado === 0 || f?.estado === "0" || f?.estado === "false" || f?.estado === "f")
        }));

    const payload = {
        idPaciente: pacienteSeleccionado.idPaciente,
        dni: pacienteSeleccionado.dni,
        nombres: pacienteSeleccionado.nombres || "",
        apePaterno: pacienteSeleccionado.apePaterno || "",
        apeMaterno: pacienteSeleccionado.apeMaterno || "",
        telefono: pacienteSeleccionado.telefono || "",
        idSector: pacienteSeleccionado.idSector || Number(regSector.value || 0),
        zona: pacienteSeleccionado.zona || "",
        direccion: pacienteSeleccionado.direccion || "",
        ubicacion: pacienteSeleccionado.ubicacion || "",
        latitud: pacienteSeleccionado.latitud || "",
        longitud: pacienteSeleccionado.longitud || "",
        discapacidadDetalle: pacienteSeleccionado.discapacidadDetalle || {},
        familiares: familiaresValidos,
        // Conserva la foto actual si no se seleccionó una nueva.
        foto: (fotoActual && String(fotoActual).trim() !== "") ? fotoActual : (pacienteSeleccionado.foto || "")
    };

    try {
        // Si hay una nueva foto seleccionada, enviar como multipart (JSON + archivo)
        if (regFoto && regFoto.files && regFoto.files[0]) {
            const formData = new FormData();
            formData.append("data", JSON.stringify(payload));
            formData.append("fotoPaciente", regFoto.files[0]);
            await apiRequestFormData(omapedUrl("actualizar"), formData);
        } else {
            await apiRequest(omapedUrl("actualizar"), { method: "POST", body: payload });
        }
        if (refreshLista) {
            await cargarPacientes(1);
        }
    } catch (error) {
        throw error;
    }
}

filtersForm.addEventListener("reset", () => setTimeout(() => {
    if (tipoFiltroSelect) {
        tipoFiltroSelect.value = "dni"; // Mantener DNI como valor por defecto
    }
    if (qInput) qInput.value = "";
    if (zonaSelect) zonaSelect.value = "";
    if (fechaInicioInput) fechaInicioInput.value = "";
    if (fechaFinInput) fechaFinInput.value = "";
    actualizarVisibilidadFiltros();
    aplicarFiltros();
}, 0));

if (qInput) {
    // Aplicar debounce de 300ms para evitar spam de peticiones al servidor
    qInput.addEventListener("input", debounce(aplicarFiltros, 300));
}
if (zonaSelect) {
zonaSelect.addEventListener("change", aplicarFiltros);
}
if (fechaInicioInput) {
    fechaInicioInput.addEventListener("change", aplicarFiltros);
}
if (fechaFinInput) {
    fechaFinInput.addEventListener("change", aplicarFiltros);
}
if (tipoFiltroSelect) {
    tipoFiltroSelect.addEventListener("change", () => {
        actualizarVisibilidadFiltros();
        aplicarFiltros();
    });
}

function actualizarVisibilidadFiltros() {
    const tipo = tipoFiltroSelect ? tipoFiltroSelect.value : "dni";

    if (!fieldFiltroTexto || !fieldZonaFiltro || !fieldFechaInicioFiltro || !fieldFechaFinFiltro) {
        return;
    }

    // Función auxiliar para mostrar/ocultar usando atributo hidden y style.display
    const mostrar = (elemento) => {
        if (elemento) {
            elemento.removeAttribute("hidden");
            elemento.style.display = "";
        }
    };
    const ocultar = (elemento) => {
        if (elemento) {
            elemento.setAttribute("hidden", "");
            elemento.style.display = "none";
        }
    };

    // Siempre ocultar todos primero, luego mostrar solo los necesarios
    ocultar(fieldZonaFiltro);
    ocultar(fieldFechaInicioFiltro);
    ocultar(fieldFechaFinFiltro);
    
    // Limpiar valores de campos ocultos
    if (zonaSelect) zonaSelect.value = "";
    if (fechaInicioInput) fechaInicioInput.value = "";
    if (fechaFinInput) fechaFinInput.value = "";

    // Mostrar/ocultar campo de texto según el tipo de filtro
    if (tipo === "dni" || tipo === "nombres" || tipo === "direccion" || tipo === "discapacidad") {
        mostrar(fieldFiltroTexto);
    } else if (tipo === "zona") {
        ocultar(fieldFiltroTexto);
        mostrar(fieldZonaFiltro);
        if (qInput) qInput.value = "";
    } else if (tipo === "fecha") {
        ocultar(fieldFiltroTexto);
        mostrar(fieldFechaInicioFiltro);
        mostrar(fieldFechaFinFiltro);
        if (qInput) qInput.value = "";
    }

    // Actualizar etiqueta y placeholder del campo de texto según el tipo
    if (labelFiltroTexto && qInput) {
        let texto = "DNI";
        let placeholder = "Buscar por DNI";
        switch (tipo) {
            case "dni":
                texto = "DNI";
                placeholder = "Buscar por DNI";
                qInput.type = "text";
                qInput.maxLength = 8;
                qInput.inputMode = "numeric";
                qInput.pattern = "[0-9]{8}";
                break;
            case "nombres":
                texto = "Nombres";
                placeholder = "Buscar por nombres y apellidos";
                qInput.type = "text";
                qInput.removeAttribute("maxLength");
                qInput.inputMode = "text";
                qInput.pattern = "";
                break;
            case "direccion":
                texto = "Dirección";
                placeholder = "Buscar por dirección";
                qInput.type = "text";
                qInput.removeAttribute("maxLength");
                qInput.inputMode = "text";
                qInput.pattern = "";
                break;
            case "discapacidad":
                texto = "Discapacidad (diagnóstico)";
                placeholder = "Buscar por diagnóstico";
                qInput.type = "text";
                qInput.removeAttribute("maxLength");
                qInput.inputMode = "text";
                qInput.pattern = "";
                break;
        }
        labelFiltroTexto.textContent = texto;
        qInput.placeholder = placeholder;
    }
}
if (regZona) {
    regZona.addEventListener("change", manejarCambioZonaRegistro);
}
if (regNumDocInput) {
    regNumDocInput.addEventListener("input", (event) => {
        let valor = regNumDocInput.value.replace(/\D/g, '').substring(0, 8);
        if (regNumDocInput.value !== valor) {
            const cursorPos = regNumDocInput.selectionStart;
            regNumDocInput.value = valor;
            regNumDocInput.setSelectionRange(Math.min(cursorPos, valor.length), Math.min(cursorPos, valor.length));
        }
        if (valor.length === 8) {
            buscarDocumentoAutomatico();
        }
    });
}

btnNuevoPaciente.addEventListener("click", abrirFormularioPaciente);
btnCancelarNuevoPaciente.addEventListener("click", cerrarFormularioPaciente);
btnVolverLista.addEventListener("click", cerrarFormularioPaciente);
btnAbrirParienteModal.addEventListener("click", abrirModalPariente);
btnAbrirParienteModalDetalle.addEventListener("click", abrirModalParienteDetalle);
btnAbrirHistoricoModal.addEventListener("click", abrirModalHistorico);

// Event listeners para foto del paciente
if (fotoPreview) {
    fotoPreview.addEventListener("click", () => {
        if (regFoto) {
            regFoto.click();
        }
    });
}

if (regFoto) {
    regFoto.addEventListener("change", (event) => {
        const file = event.target.files[0];
        if (file) {
            mostrarVistaPreviaFoto(file);
        }
    });
}

if (btnEliminarFoto) {
    btnEliminarFoto.addEventListener("click", (e) => {
        e.stopPropagation(); // Evitar que se active el click del marco
        fotoActual = null;
        if (fotoPreviewImg) {
            fotoPreviewImg.style.display = "none";
            fotoPreviewImg.src = "";
        }
        if (fotoPreviewText) {
            fotoPreviewText.style.display = "block";
            fotoPreviewText.textContent = modoPacienteEdicion ? "Actualizar Foto" : "Subir Foto";
        }
        if (btnEliminarFoto) {
            btnEliminarFoto.style.display = "none";
        }
        if (regFoto) {
            regFoto.value = "";
        }
    });
}

// Validar campo celular: solo números, máximo 9 dígitos
if (regCelularInput) {
    regCelularInput.addEventListener("input", (event) => {
        let valor = regCelularInput.value.replace(/\D/g, '').substring(0, 9);
        if (regCelularInput.value !== valor) {
            const cursorPos = regCelularInput.selectionStart;
            regCelularInput.value = valor;
            regCelularInput.setSelectionRange(Math.min(cursorPos, valor.length), Math.min(cursorPos, valor.length));
        }
    });
}

// Validar campo celular de pariente: solo números, máximo 9 dígitos
if (parienteTelefonoInput) {
    parienteTelefonoInput.addEventListener("input", (event) => {
        let valor = parienteTelefonoInput.value.replace(/\D/g, '').substring(0, 9);
        if (parienteTelefonoInput.value !== valor) {
            const cursorPos = parienteTelefonoInput.selectionStart;
            parienteTelefonoInput.value = valor;
            parienteTelefonoInput.setSelectionRange(Math.min(cursorPos, valor.length), Math.min(cursorPos, valor.length));
        }
    });
}

// Botón para obtener coordenadas
document.getElementById("btnObtenerCoordenadas")?.addEventListener("click", () => {
    const btn = document.getElementById("btnObtenerCoordenadas");
    const latInput = document.getElementById("regLatitud");
    const lngInput = document.getElementById("regLongitud");
    
    if (!navigator.geolocation) {
        mostrarToast("error", "La geolocalización no está disponible en su navegador");
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = "<span class='btn-icon__glyph'>&#8982;</span> Obteniendo...";
    
    navigator.geolocation.getCurrentPosition(
        (position) => {
            if (latInput) latInput.value = position.coords.latitude.toFixed(6);
            if (lngInput) lngInput.value = position.coords.longitude.toFixed(6);
            btn.disabled = false;
            btn.innerHTML = "Obtener coordenadas";
            mostrarToast("success", "Coordenadas obtenidas correctamente");
        },
        (error) => {
            btn.disabled = false;
            btn.innerHTML = "Obtener coordenadas";
            let mensaje = "No se pudieron obtener las coordenadas";
            if (error.code === error.PERMISSION_DENIED) {
                mensaje = "Permiso de geolocalización denegado. Puede ingresar las coordenadas manualmente.";
            } else if (error.code === error.POSITION_UNAVAILABLE) {
                mensaje = "Ubicación no disponible. Puede ingresar las coordenadas manualmente.";
            } else if (error.code === error.TIMEOUT) {
                mensaje = "Tiempo de espera agotado. Puede ingresar las coordenadas manualmente.";
            }
            mostrarToast("error", mensaje);
        }
    );
});

tbody.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;

    const idPaciente = Number(button.getAttribute("data-id-paciente"));
    const action = button.getAttribute("data-action");
    const paciente = obtenerPacientePorId(idPaciente);
    if (!paciente) return;

    try {
        if (action === "ver-diagnosticos") {
            const detalle = await cargarDetallePaciente(idPaciente);
            mostrarDiagnosticos(detalle);
        }

        if (action === "editar-paciente") {
            const detalle = await cargarDetallePaciente(idPaciente);
            pacienteSeleccionado = detalle;
            abrirFormularioPaciente();
            modoPacienteEdicion = true;
            idPacienteEnEdicion = detalle.idPaciente;
            pageTitle.textContent = "Editar Usuario OMAPED";
            if (btnSubmitNuevoPaciente) btnSubmitNuevoPaciente.textContent = "Guardar cambios";
            llenarFormularioPaciente(detalle);
        }

        if (action === "eliminar-paciente") {
            if (!puedeEliminarUsuario) {
                mostrarToast("error", "No tiene permisos para eliminar usuarios.");
                return;
            }
            
            if (!confirm(`¿Está seguro de que desea eliminar al usuario ${obtenerNombreCompletoPersona(paciente)}?`)) {
                return;
            }
            
            eliminarPaciente(idPaciente);
        }
    } catch (error) {
        mostrarToast("error", error.message || "Error al procesar acción");
    }
});

diagnosticosTbody.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;

    if (button.getAttribute("data-action") === "descargar-evidencia") {
        const evidencia = button.getAttribute("data-evidencia");
        if (!evidencia || evidencia === "-") return;
        const rutaArchivo = construirUrlArchivoPrivado("evidencia", evidencia);
        if (!rutaArchivo) return;
        const enlace = document.createElement("a");
        enlace.href = rutaArchivo;
        enlace.download = evidencia;
        enlace.target = "_blank";
        enlace.rel = "noopener";
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();
    } else if (button.getAttribute("data-action") === "editar-historico") {
        const idAtencion = button.getAttribute("data-id-atencion");
        if (idAtencion) {
            abrirModalEditarHistorico(parseInt(idAtencion));
        }
    }
});


// Event listener para tabla de parientes en registro
parientesTbody.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button) return;
    
    const idx = Number(button.getAttribute("data-pariente-idx"));
    if (idx < 0 || idx >= parientesRegistro.length) return;
    const pariente = parientesRegistro[idx];
    if (!pariente) return;

    if (button.getAttribute("data-action") === "editar-pariente-registro") {
        abrirModalEditarParienteRegistro(pariente, idx);
    }

    if (button.getAttribute("data-action") === "eliminar-pariente-registro") {
        if (!puedeEliminarUsuario) {
            mostrarToast("error", "No tiene permisos para eliminar parientes.");
            return;
        }
        if (confirm(`¿Está seguro de que desea eliminar al familiar cuidador ${obtenerNombreCompletoPersona(pariente)}?`)) {
            parientesRegistro.splice(idx, 1);
            renderParientesTabla();
            mostrarToast("success", "Pariente eliminado de la lista");
        }
    }
});

parientesDetalleTbody.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-action]");
    if (!button || !pacienteSeleccionado) return;
    const familiares = obtenerFamiliaresPaciente(pacienteSeleccionado);
    const idx = Number(button.getAttribute("data-pariente-idx"));
    const pariente = familiares[idx];
    if (!pariente) return;

    if (button.getAttribute("data-action") === "ver-pariente-detalle") {
        abrirModalParienteDetalleReadonly(pariente);
    }

    if (button.getAttribute("data-action") === "editar-pariente") {
        abrirModalEditarPariente(pariente, idx);
    }

    if (button.getAttribute("data-action") === "toggle-pariente-estado") {
        if (!puedeEliminarUsuario) {
            mostrarToast("error", "No tiene permisos para eliminar parientes.");
            return;
        }
        const estaActivo = pariente && (pariente.estado === true || pariente.estado === "t" || pariente.estado === 1 || pariente.estado === "1");
        const accionTxt = estaActivo ? "desactivar" : "activar";
        if (!confirm(`¿Está seguro de que desea ${accionTxt} al familiar cuidador ${obtenerNombreCompletoPersona(pariente)}?`)) return;
        
        try {
            const cdgoUsrios = Number(pariente.cdgoUsrios || 0);
            if (!cdgoUsrios) {
                mostrarToast("error", "No se pudo identificar al familiar (cdgoUsrios). Recargue el detalle.");
                return;
            }

            button.disabled = true;
            const prevEstado = pariente.estado;
            pariente.estado = !estaActivo;
            renderParientesDetalle(pacienteSeleccionado);

            const payload = { idPaciente: pacienteSeleccionado.idPaciente, cdgoUsrios, estado: !estaActivo };
            const respuesta = await apiRequest(omapedUrl("toggle_pariente"), { method: "POST", body: payload });
            if (!respuesta || respuesta.success === false) {
                // Revertir si falla
                pariente.estado = prevEstado;
                renderParientesDetalle(pacienteSeleccionado);
                throw new Error(respuesta?.error || "No se pudo actualizar el estado del pariente");
            }

            mostrarToast("success", estaActivo ? "Pariente desactivado correctamente" : "Pariente activado correctamente");
            scrollAFamiliarCuidador();
        } catch (error) {
            mostrarToast("error", error.message || "Error al actualizar estado del pariente");
        } finally {
            if (button) button.disabled = false;
        }
    }
});

parienteForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    // Construir objeto del pariente
    const parentescoId = parienteParentesco.value;
    
    // Validar que se haya seleccionado un parentesco
    if (!parentescoId || parentescoId === "" || parentescoId === "0") {
        mostrarToast("error", "Debe seleccionar un tipo de parentesco");
        return;
    }
    
    const parentesco = parentescosReferencia.find((item) => String(item.id) === String(parentescoId));
    if (!parentesco) {
        mostrarToast("error", "Tipo de parentesco no válido");
        return;
    }
    
    const nuevoPariente = {
        idParentesco: Number(parentescoId),
        parentesco: parentesco.nombre,
        nombres: document.getElementById("parienteNombres").value.trim().toUpperCase(),
        apePaterno: document.getElementById("parienteApePaterno").value.trim().toUpperCase(),
        apeMaterno: document.getElementById("parienteApeMaterno").value.trim().toUpperCase(),
        numeroDocumento: document.getElementById("parienteDocumento").value.trim(),
        telefono: document.getElementById("parienteTelefono").value.trim() || "-",
        direccion: document.getElementById("parienteDireccion").value.trim() || "-",
        observaciones: document.getElementById("parienteObservaciones").value.trim() || "-"
    };

    // Validar campos requeridos
    if (!nuevoPariente.nombres || !nuevoPariente.apePaterno || !nuevoPariente.apeMaterno || !nuevoPariente.numeroDocumento) {
        mostrarToast("error", "Completa todos los campos requeridos");
        return;
    }

    // Validar DNI (no duplicado y no igual al paciente principal)
    const indiceExcluir = modoPariente === "editar" ? parienteEditIdx : -1;
    const validacion = validarPariente(nuevoPariente, indiceExcluir);
    if (!validacion.valido) {
        mostrarToast("error", validacion.mensaje);
        return;
    }

    // Loading/disable para evitar doble clic mientras se guarda en servidor
    const btnParienteSubmit = btnSubmitPariente || parienteForm.querySelector("button[type='submit']");
    const iniciarLoadingPariente = () => {
        if (!btnParienteSubmit) return;
        if (btnParienteSubmit.dataset.omapedLoading === "1") return;
        btnParienteSubmit.dataset.omapedLoading = "1";
        btnParienteSubmit.dataset.prevLabel = btnParienteSubmit.textContent.trim();
        btnParienteSubmit.disabled = true;
        btnParienteSubmit.innerHTML = `<span class="btn-loading-spinner" aria-hidden="true"></span>${btnParienteSubmit.dataset.prevLabel}...`;
    };
    const finalizarLoadingPariente = () => {
        if (!btnParienteSubmit) return;
        if (btnParienteSubmit.dataset.omapedLoading !== "1") return;
        const prev = btnParienteSubmit.dataset.prevLabel || "Guardar";
        btnParienteSubmit.dataset.omapedLoading = "0";
        btnParienteSubmit.disabled = false;
        btnParienteSubmit.textContent = prev;
    };

    // Procesar según el contexto
    if (modoPariente === "editar" && contextoPariente === "registro") {
        // Editar pariente en registro
        if (parienteEditIdx >= 0 && parienteEditIdx < parientesRegistro.length) {
            parientesRegistro[parienteEditIdx] = nuevoPariente;
            renderParientesTabla();
            mostrarToast("success", "Pariente actualizado en la lista");
        }
    } else if (modoPariente === "editar" && contextoPariente === "detalle" && pacienteSeleccionado) {
        // Editar pariente existente en detalle
        if (!Array.isArray(pacienteSeleccionado.familiares)) {
            pacienteSeleccionado.familiares = obtenerFamiliaresPaciente(pacienteSeleccionado).map((item) => ({ ...item }));
        }
        if (parienteEditIdx >= 0 && parienteEditIdx < pacienteSeleccionado.familiares.length) {
            pacienteSeleccionado.familiares[parienteEditIdx] = nuevoPariente;
        }
        pacienteSeleccionado.familiar = { ...pacienteSeleccionado.familiares[0] };
        try {
            iniciarLoadingPariente();
            await persistirYRefrescarFamiliares("Pariente actualizado correctamente");
        } catch (error) {
            finalizarLoadingPariente();
            mostrarToast("error", error.message || "Error al actualizar pariente");
            return;
        }
        finalizarLoadingPariente();
    } else if (contextoPariente === "detalle" && pacienteSeleccionado) {
        // Agregar nuevo pariente en detalle
        if (!Array.isArray(pacienteSeleccionado.familiares)) {
            pacienteSeleccionado.familiares = obtenerFamiliaresPaciente(pacienteSeleccionado).map((item) => ({ ...item }));
        }
        // Mostrar el nuevo familiar arriba (UX)
        pacienteSeleccionado.familiares.unshift(nuevoPariente);
        pacienteSeleccionado.familiar = { ...pacienteSeleccionado.familiares[0] };
        try {
            iniciarLoadingPariente();
            await persistirYRefrescarFamiliares("Pariente agregado correctamente");
        } catch (error) {
            finalizarLoadingPariente();
            mostrarToast("error", error.message || "Error al agregar pariente");
            return;
        }
        finalizarLoadingPariente();
    } else {
        // Agregar pariente en registro
        parientesRegistro.push(nuevoPariente);
        renderParientesTabla();
    }

    cerrarModalPariente();
});

historicoForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!pacienteSeleccionado) return;

    const servicios = Array.from(document.querySelectorAll("input[name='aServicios[]']:checked")).map((item) => {
        return item.dataset.nombre || item.value;
    });
    if (!servicios.length) {
        mostrarToast("error", "Selecciona al menos un servicio");
        return;
    }
    
    let fechaAtencion = document.getElementById("aFechaAtencion").value;
    if (!fechaAtencion) {
        const hoy = new Date();
        const año = hoy.getFullYear();
        const mes = String(hoy.getMonth() + 1).padStart(2, '0');
        const dia = String(hoy.getDate()).padStart(2, '0');
        fechaAtencion = `${año}-${mes}-${dia}`;
    }

    const payload = {
        idPaciente: pacienteSeleccionado.idPaciente,
        fechaAtencion,
        sede: document.getElementById("aSedeAtencion").value,
        observaciones: document.getElementById("aObservaciones").value.trim().toUpperCase(),
        evidencia: "",
        servicios,
        servicioOtro: document.getElementById("aServicioOtro").value.trim().toUpperCase()
    };

    if (idAtencionEnEdicion > 0) {
        payload.idAtencion = idAtencionEnEdicion;
    }

    try {
        const formData = new FormData();
        formData.append("data", JSON.stringify(payload));
        
        const evidenciaInput = document.getElementById("aEvidenciaAtencion");
        if (evidenciaInput && evidenciaInput.files && evidenciaInput.files[0]) {
            formData.append("evidenciaAtencion", evidenciaInput.files[0]);
        }

        const respuesta = await apiRequestFormData(omapedUrl("historico_guardar"), formData);
        if (respuesta && respuesta.success) {
            mostrarToast("success", "Historial guardado correctamente");
            const detalle = await cargarDetallePaciente(pacienteSeleccionado.idPaciente);
            mostrarDiagnosticos(detalle);
            await cargarPacientes(1);
            cerrarModalHistorico();
        } else {
            mostrarToast("error", respuesta?.error || "Error al guardar historial");
        }
    } catch (error) {
        mostrarToast("error", error.message || "Error al guardar historial");
    }
});

diagnosticoOmapedForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!pacienteSeleccionado) return;

    const descripcion = document.getElementById("doDescripcion").value.trim().toUpperCase();
    if (!descripcion) return;

    const gradoDiscapacidad = document.getElementById("doGradoDiscapacidad").value.trim();
    if (!gradoDiscapacidad || (gradoDiscapacidad !== "LEVE" && gradoDiscapacidad !== "MODERADO" && gradoDiscapacidad !== "SEVERO")) {
        mostrarToast("error", "Selecciona un grado de discapacidad válido");
        return;
    }

    const discapacidadPayload = {
        ...obtenerDiscapacidadDetallePaciente(pacienteSeleccionado),
        idDiscapacidad: document.getElementById("doIdDiscapacidad").value.trim(),
        descripcionDiagnostico: descripcion,
        codigoConadis: document.getElementById("doCodigoConadis").value.trim().toUpperCase(),
        gradoDiscapacidad: gradoDiscapacidad,
        seguroSalud: document.getElementById("doSeguroSalud").value.trim().toUpperCase()
    };

    try {
        const formData = new FormData();
        const payload = {
            idPaciente: pacienteSeleccionado.idPaciente,
            dni: pacienteSeleccionado.dni,
            discapacidadDetalle: discapacidadPayload
        };
        formData.append("data", JSON.stringify(payload));
        
        const carnetFile = document.getElementById("doArchivoCarnetConadis");
        if (carnetFile && carnetFile.files && carnetFile.files[0]) {
            formData.append("archivoCarnetConadis", carnetFile.files[0]);
        }
        
        const certFile = document.getElementById("doArchivoCertificadoDiscapacidad");
        if (certFile && certFile.files && certFile.files[0]) {
            formData.append("archivoCertificadoDiscapacidad", certFile.files[0]);
        }

        await apiRequestFormData(omapedUrl("actualizar"), formData);
        mostrarToast("success", "Discapacidad actualizada correctamente");
        const detalle = await cargarDetallePaciente(pacienteSeleccionado.idPaciente);
        mostrarDiagnosticos(detalle);
        cerrarModalDiagnosticoOmaped();
    } catch (error) {
        mostrarToast("error", error.message || "Error al actualizar discapacidad");
    }
});

nuevoPacienteForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const payload = construirPayloadPaciente();
    
    // Validar DNI usando función centralizada
    const dniValidado = Validaciones.dni(payload.dni);
    if (!dniValidado) {
        mostrarToast("error", "El DNI debe tener 8 dígitos");
        return;
    }
    payload.dni = dniValidado;
    
    // Validar campos obligatorios
    if (!payload.nombres?.trim() || !payload.apePaterno?.trim() || !payload.apeMaterno?.trim()) {
        mostrarToast("error", "Completa nombres y apellidos del usuario");
        return;
    }
    
    // Validar género usando función centralizada
    const generoValidado = Validaciones.genero(payload.genero);
    if (!generoValidado) {
        mostrarToast("error", "Selecciona un género válido");
        return;
    }
    payload.genero = generoValidado;

    // Validar tipos de discapacidad usando función centralizada
    if (!Validaciones.tiposDiscapacidad(payload.discapacidadDetalle)) {
        mostrarToast("error", "Selecciona al menos un tipo de discapacidad");
        return;
    }
    
    // Validar grado de discapacidad usando función centralizada
    const gradoValidado = Validaciones.gradoDiscapacidad(payload.discapacidadDetalle.gradoDiscapacidad);
    if (!gradoValidado) {
        mostrarToast("error", "Selecciona un grado de discapacidad válido");
        return;
    }
    payload.discapacidadDetalle.gradoDiscapacidad = gradoValidado;
    
    // Validar coordenadas si están presentes (permitir valores vacíos)
    if (payload.latitud && payload.longitud && payload.latitud.trim() !== '' && payload.longitud.trim() !== '') {
        const coords = Validaciones.coordenadas(payload.latitud, payload.longitud);
        if (!coords) {
            mostrarToast("error", "Las coordenadas ingresadas no son válidas");
            return;
        }
        payload.latitud = coords.latitud;
        payload.longitud = coords.longitud;
    } else {
        // Si están vacías, establecer como null para que el backend las procese correctamente
        payload.latitud = (payload.latitud && payload.latitud.trim() !== '') ? payload.latitud : null;
        payload.longitud = (payload.longitud && payload.longitud.trim() !== '') ? payload.longitud : null;
    }


    try {
        if (modoPacienteEdicion && !payload.idPaciente) {
            mostrarToast("error", "Error: ID de paciente no encontrado");
            return;
        }
        
        const formData = new FormData();
        formData.append("data", JSON.stringify(payload));
        
        const carnetFile = document.getElementById("regCarnetConadisArchivo");
        if (carnetFile && carnetFile.files && carnetFile.files[0]) {
            formData.append("archivoCarnetConadis", carnetFile.files[0]);
        }
        
        const certFile = document.getElementById("regCertificadoDiscapacidadArchivo");
        if (certFile && certFile.files && certFile.files[0]) {
            formData.append("archivoCertificadoDiscapacidad", certFile.files[0]);
        }
        
        // Agregar foto del paciente si hay un archivo seleccionado
        if (regFoto && regFoto.files && regFoto.files[0]) {
            formData.append("fotoPaciente", regFoto.files[0]);
        }

        const url = modoPacienteEdicion ? omapedUrl("actualizar") : omapedUrl("guardar");
        const respuesta = await apiRequestFormData(url, formData);
        
        if (!respuesta || !respuesta.success) {
            mostrarToast("error", respuesta?.error || "Error al guardar usuario");
            return;
        }
        
        mostrarToast("success", modoPacienteEdicion ? "Usuario actualizado correctamente" : "Usuario registrado correctamente");
        await cargarPacientes(1);
        cerrarFormularioPaciente();
    } catch (error) {
        console.error("Error al guardar usuario:", error);
        mostrarToast("error", error.message || "Error al guardar usuario");
    }
});

diagnosticoModal.addEventListener("click", (event) => {
    if (event.target.matches("[data-close-modal='1']")) cerrarModalDiagnostico();
});

historicoModal.addEventListener("click", (event) => {
    if (event.target.matches("[data-close-historico-modal='1']")) cerrarModalHistorico();
});

diagnosticoOmapedModal.addEventListener("click", (event) => {
    if (event.target.matches("[data-close-diagnostico-omaped-modal='1']")) cerrarModalDiagnosticoOmaped();
});

parienteDetalleModal.addEventListener("click", (event) => {
    if (event.target.matches("[data-close-pariente-detalle-modal='1']")) cerrarModalParienteDetalle();
});

parienteModal.addEventListener("click", (event) => {
    if (event.target.matches("[data-close-pariente-modal='1']")) cerrarModalPariente();
});

if (parienteDocumentoInput) {
    parienteDocumentoInput.addEventListener("input", (event) => {
        let valor = parienteDocumentoInput.value.replace(/\D/g, '').substring(0, 8);
        if (parienteDocumentoInput.value !== valor) {
            const cursorPos = parienteDocumentoInput.selectionStart;
            parienteDocumentoInput.value = valor;
            parienteDocumentoInput.setSelectionRange(Math.min(cursorPos, valor.length), Math.min(cursorPos, valor.length));
        }
        if (valor.length === 8) {
            buscarDocumentoParienteAutomatico();
        } else if (valor.length === 0) {
            limpiarCamposDatosPariente();
            ultimoDniParienteBuscado = "";
        }
    });
}

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !diagnosticoModal.hidden) cerrarModalDiagnostico();
    if (event.key === "Escape" && !parienteModal.hidden) cerrarModalPariente();
    if (event.key === "Escape" && !historicoModal.hidden) cerrarModalHistorico();
    if (event.key === "Escape" && !diagnosticoOmapedModal.hidden) cerrarModalDiagnosticoOmaped();
    if (event.key === "Escape" && !parienteDetalleModal.hidden) cerrarModalParienteDetalle();
});

async function eliminarPaciente(idPaciente) {
    if (!idPaciente || idPaciente <= 0) {
        mostrarToast("error", "Error: ID de paciente no válido");
        return;
    }
    
    if (!puedeEliminarUsuario) {
        mostrarToast("error", "No tiene permisos para eliminar usuarios.");
        return;
    }
    
    try {
        const response = await fetch(omapedUrl("eliminar"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrfToken
            },
            body: JSON.stringify({
                idPaciente: idPaciente
            })
        });
        
        const respuesta = await response.json();
        
        if (respuesta && respuesta.success) {
            mostrarToast("success", "Usuario eliminado correctamente");
            await cargarPacientes(1);
            if (pacienteSeleccionado && Number(pacienteSeleccionado.idPaciente) === idPaciente) {
                panelPaciente.hidden = true;
            }
        } else {
            mostrarToast("error", respuesta?.error || "Error al eliminar usuario");
        }
    } catch (error) {
        mostrarToast("error", error.message || "Error al eliminar usuario");
    }
}

// Convertir automáticamente todo el texto del formulario a mayúsculas mientras se escribe
function configurarInputsMayusculas() {
    const form = document.getElementById("nuevoPacienteForm");
    if (!form) return;
    
    // Obtener todos los inputs de texto y textareas del formulario (excepto DNI, Celular, Latitud, Longitud, Ubicacion)
    const inputsTexto = form.querySelectorAll('input[type="text"]:not(#regNumDoc):not(#regCelular):not(#regLatitud):not(#regLongitud):not(#regUbicacion), textarea');
    
    inputsTexto.forEach(input => {
        // Convertir a mayúsculas mientras se escribe
        input.addEventListener('input', function(e) {
            const cursorPos = this.selectionStart;
            this.value = this.value.toUpperCase();
            // Restaurar posición del cursor
            this.setSelectionRange(cursorPos, cursorPos);
        });
        
        // Convertir a mayúsculas al pegar
        input.addEventListener('paste', function(e) {
            setTimeout(() => {
                const cursorPos = this.selectionStart;
                this.value = this.value.toUpperCase();
                this.setSelectionRange(cursorPos, cursorPos);
            }, 0);
        });
    });
}

/**
 * Calcula la edad desde una fecha de nacimiento
 * @param {string} fechaNacimiento Fecha en formato YYYY-MM-DD
 * @returns {string} Edad en años o '-' si es inválida
 */
function calcularEdad(fechaNacimiento) {
    if (!fechaNacimiento || fechaNacimiento.trim() === '') return '-';
    
    // Validar fecha usando función centralizada
    const fechaValidada = Validaciones.fecha(fechaNacimiento);
    if (!fechaValidada) return '-';
    
    const fechaNac = new Date(fechaValidada);
    if (isNaN(fechaNac.getTime())) return '-';
    
    const hoy = new Date();
    let edad = hoy.getFullYear() - fechaNac.getFullYear();
    const mesActual = hoy.getMonth();
    const diaActual = hoy.getDate();
    const mesNac = fechaNac.getMonth();
    const diaNac = fechaNac.getDate();
    
    if (mesActual < mesNac || (mesActual === mesNac && diaActual < diaNac)) {
        edad--;
    }
    
    return edad + " años";
}

// Función para exportar pacientes a Excel (mismos filtros que el listado; hasta 50000 filas en servidor)
async function exportarPacientesAExcel() {
    try {
        tablaLoadingShowMin();
        const params = construirParamsListaPacientes();
        params.append("exportar", "1");
        const data = await apiRequest(omapedUrl("listar", params.toString()));
        const pacientesActivos = Array.isArray(data.items) ? data.items.filter((p) => p.estado !== false) : [];
        if (!pacientesActivos.length) {
            mostrarToast("error", "No hay datos para exportar con los filtros actuales");
            return;
        }

        const datosExportacion = pacientesActivos.map((paciente) => {
            const detalleDiscapacidad = obtenerDiscapacidadDetallePaciente(paciente);
            
            // Obtener nombre del sector
            let nombreSector = '-';
            if (paciente.idSector && sectoresReferencia && sectoresReferencia.length > 0) {
                const sector = sectoresReferencia.find(s => s.id === Number(paciente.idSector));
                if (sector) nombreSector = sector.nombre;
            }
            
            // Obtener nombre del distrito
            let nombreDistrito = '-';
            if (paciente.distritoOtro && distritosReferencia && distritosReferencia.length > 0) {
                const distrito = distritosReferencia.find(d => d.id === Number(paciente.distritoOtro));
                if (distrito) nombreDistrito = distrito.nombre;
            }
            
            // Construir enlace de dirección usando latitud y longitud
            const latitud = paciente.latitud || '';
            const longitud = paciente.longitud || '';
            const enlaceDireccion = (latitud && longitud && latitud !== '-' && longitud !== '-') 
                ? `https://www.google.com/maps?q=${latitud},${longitud}` 
                : '-';
            
            // Convertir género a texto si viene como número
            let generoTexto = '-';
            if (paciente.genero) {
                const generoUpper = String(paciente.genero).toUpperCase().trim();
                if (generoUpper === 'M' || generoUpper === '1' || generoUpper === 'MASCULINO') {
                    generoTexto = 'MASCULINO';
                } else if (generoUpper === 'F' || generoUpper === '2' || generoUpper === 'FEMENINO') {
                    generoTexto = 'FEMENINO';
                } else {
                    generoTexto = generoUpper;
                }
            }
            
            // Formatear fecha de nacimiento
            let fechaNacFormateada = '-';
            if (paciente.fechaNacimiento && paciente.fechaNacimiento.trim() !== '') {
                const fechaNac = new Date(paciente.fechaNacimiento);
                if (!isNaN(fechaNac.getTime())) {
                    fechaNacFormateada = fechaNac.toLocaleDateString('es-PE', { 
                        day: '2-digit', 
                        month: '2-digit', 
                        year: 'numeric' 
                    });
                }
            }
            
            // Formatear fecha de registro
            let fechaRegistroFormateada = '-';
            if (paciente.fechaRegistro && paciente.fechaRegistro.trim() !== '') {
                const fechaReg = new Date(paciente.fechaRegistro);
                if (!isNaN(fechaReg.getTime())) {
                    fechaRegistroFormateada = fechaReg.toLocaleDateString('es-PE', { 
                        day: '2-digit', 
                        month: '2-digit', 
                        year: 'numeric' 
                    });
                }
            }
            
            return {
                'Nombre': paciente.nombres || '-',
                'Apellido Paterno': paciente.apePaterno || '-',
                'Apellido Materno': paciente.apeMaterno || '-',
                'DNI': paciente.dni || '-',
                'Edad': calcularEdad(paciente.fechaNacimiento),
                'Género': generoTexto,
                'Fecha de Nacimiento': fechaNacFormateada,
                'Celular': paciente.telefono || '-',
                'Sector': nombreSector,
                'Distrito': nombreDistrito,
                'Dirección': paciente.ubicacion || '-',
                'Coordenadas': enlaceDireccion,
                'Diagnóstico': detalleDiscapacidad.descripcionDiagnostico || detalleDiscapacidad.descripcion || '-',
                'Código CONADIS': detalleDiscapacidad.codigoConadis || '-',
                'Grado de Discapacidad': detalleDiscapacidad.gradoDiscapacidad || '-',
                'Tipo Motora': detalleDiscapacidad.motora ? 'Sí' : 'No',
                'Tipo Sensorial': detalleDiscapacidad.sensorial ? 'Sí' : 'No',
                'Tipo Intelectual': detalleDiscapacidad.intelectual ? 'Sí' : 'No',
                'Tipo Mental': detalleDiscapacidad.mental ? 'Sí' : 'No',
                'Seguro de Salud': detalleDiscapacidad.seguroSalud || '-',
                'Fecha de Registro': fechaRegistroFormateada
            };
        });

        // Crear libro de trabajo
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.json_to_sheet(datosExportacion);
        
        // Ajustar ancho de columnas
        const colWidths = [
            { wch: 15 }, // Nombre
            { wch: 18 }, // Apellido Paterno
            { wch: 18 }, // Apellido Materno
            { wch: 12 }, // DNI
            { wch: 10 }, // Edad
            { wch: 12 }, // Género
            { wch: 18 }, // Fecha de Nacimiento
            { wch: 12 }, // Celular
            { wch: 15 }, // Sector
            { wch: 20 }, // Distrito
            { wch: 30 }, // Dirección
            { wch: 50 }, // Coordenadas
            { wch: 30 }, // Diagnóstico
            { wch: 15 }, // Código CONADIS
            { wch: 20 }, // Grado de Discapacidad
            { wch: 12 }, // Tipo Motora
            { wch: 12 }, // Tipo Sensorial
            { wch: 12 }, // Tipo Intelectual
            { wch: 12 }, // Tipo Mental
            { wch: 15 }, // Seguro de Salud
            { wch: 18 }  // Fecha de Registro
        ];
        ws['!cols'] = colWidths;

        // Agregar hoja al libro
        XLSX.utils.book_append_sheet(wb, ws, 'Pacientes OMAPED');

        // Generar nombre de archivo con fecha
        const fecha = new Date();
        const fechaStr = fecha.toISOString().split('T')[0];
        const nombreArchivo = `Pacientes_OMAPED_${fechaStr}.xlsx`;

        // Descargar archivo
        XLSX.writeFile(wb, nombreArchivo);

        const totalSrv = typeof data.total === "number" ? data.total : pacientesActivos.length;
        const truncado = totalSrv > pacientesActivos.length;
        mostrarToast(
            "success",
            truncado
                ? `Se exportaron ${pacientesActivos.length} de ${totalSrv} paciente(s) (límite de exportación).`
                : `Se exportaron ${pacientesActivos.length} paciente(s) a Excel`
        );
    } catch (error) {
        console.error("Error al exportar a Excel:", error);
        mostrarToast("error", "Error al exportar a Excel: " + (error.message || "Error desconocido"));
    } finally {
        tablaLoadingHideMin();
    }
}

// Event listener para el botón de exportar
const btnExportarExcel = document.getElementById("btnExportarExcel");
if (btnExportarExcel) {
    btnExportarExcel.addEventListener("click", exportarPacientesAExcel);
}

(async function init() {
    try {
        setCargandoTabla();
        // Optimización: cargar la LISTA primero (prioridad UX).
        // Los catálogos se cargan en segundo plano y solo afectan formularios/modales.
        renderParientesTabla();
        marcarTiposDiscapacidadSeleccionados([]);
        
        // Inicializar visibilidad de filtros (DNI por defecto)
        actualizarVisibilidadFiltros();

        await cargarPacientes(1);
        cargarCatalogos().catch((e) => console.warn("No se pudieron cargar catálogos:", e));
        
        // Configurar inputs para convertir a mayúsculas
        configurarInputsMayusculas();
    } catch (error) {
        setTablaLoadingVisible(false);
        mostrarToast("error", error.message || "Error al inicializar");
    }
})();
