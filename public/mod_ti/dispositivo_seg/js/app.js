/* ── Estado global ── */
var dsState = { page: 1, perPage: 10, q: "", tipo: "", zona: "", estado: "", fechaDesde: "", fechaHasta: "", sectorId: "", subSectorId: "" };

/* ── Elementos del DOM (cache) ── */
var dsEls = {};

function dsGetEls() {
    dsEls = {
        tbody:       document.getElementById("dispositivosTbody"),
        form:        document.getElementById("filtrosForm"),
        q:           document.getElementById("q"),
        tipo:        document.getElementById("filtroTipo"),
        zona:        document.getElementById("filtroZona"),
        sector:      document.getElementById("filtroSector"),
        subSector:   document.getElementById("filtroSubSector"),
        estado:      document.getElementById("filtroEstado"),
        fechaDesde:  document.getElementById("filtroFechaDesde"),
        fechaHasta:  document.getElementById("filtroFechaHasta"),
        btnAgregar:  document.getElementById("btnAgregar"),
        btnExportar: document.getElementById("btnExportar"),
        btnGraficos:  document.getElementById("btnGraficos"),
        btnMapa:      document.getElementById("btnMapa"),
        btnCerrar:   document.getElementById("btnCerrarModal"),
        btnCancelar: document.getElementById("btnCancelarModal"),
        overlay:     document.getElementById("modalOverlay"),
        formDispo:   document.getElementById("formDispositivo"),
        btnGuardar:  document.getElementById("btnGuardarModal"),
        btnUbi:      document.getElementById("btnUbicacion"),
        latitud:     document.getElementById("formLatitud"),
        longitud:    document.getElementById("formLongitud")
    };
}

/* ── Obtener filtros actuales del formulario ── */
function dsSyncFiltros() {
    dsState.q = dsNormalizarTexto(dsEls.q.value);
    dsState.tipo = dsEls.tipo.value;
    dsState.zona = dsEls.zona.value;
    dsState.sectorId = dsEls.sector && dsEls.sector.value ? dsEls.sector.value : "";
    dsState.subSectorId = dsEls.subSector && dsEls.subSector.value ? dsEls.subSector.value : "";
    dsState.estado = dsEls.estado.value;
    dsState.fechaDesde = dsEls.fechaDesde.value;
    dsState.fechaHasta = dsEls.fechaHasta.value;
}

function dsGetFiltrosParams() {
    return {
        page: dsState.page,
        per_page: dsState.perPage,
        q: dsState.q,
        tipo_dispositivo: dsState.tipo,
        zona: dsState.zona,
        estado: dsState.estado,
        sector_id: dsState.sectorId,
        sub_sector_id: dsState.subSectorId,
        fecha_desde: dsState.fechaDesde,
        fecha_hasta: dsState.fechaHasta
    };
}

/* ── Cargar listado ── */
function dsCargarListado() {
    dsEls.tbody.innerHTML = '<tr class="empty-row"><td colspan="14">Cargando registros...</td></tr>';
    dsApiListar(dsGetFiltrosParams()).then(function (data) {
        dsRenderTabla(data.items || []);
        dsRenderCount(data.pagination || {});
        dsRenderPager(data.pagination || {}, function (p) {
            dsState.page = p;
            dsCargarListado();
        });
        dsBindAcciones();
        var editarId = dsGetEditarId();
        if (editarId) {
            dsEditarDispositivo(editarId);
            dsLimpiarEditarUrl();
        }
    }).catch(function (err) {
        dsEls.tbody.innerHTML = '<tr class="empty-row"><td colspan="14">' + dsEscapeHtml(err.message) + '</td></tr>';
    });
}

function dsGetEditarId() {
    var params = new URLSearchParams(window.location.search);
    var id = params.get("editar");
    return id && id.trim() !== "" ? id.trim() : null;
}

function dsLimpiarEditarUrl() {
    if (typeof window.history.replaceState !== "function") return;
    var params = new URLSearchParams(window.location.search);
    params.delete("editar");
    var qs = params.toString();
    var url = window.location.pathname + (qs ? "?" + qs : "") + window.location.hash;
    window.history.replaceState({}, "", url);
}

/* ── Acciones de tabla ── */
function dsBindAcciones() {
    document.querySelectorAll(".btn--edit").forEach(function (b) {
        b.addEventListener("click", function () { dsEditarDispositivo(b.dataset.id); });
    });
    document.querySelectorAll(".btn--delete").forEach(function (b) {
        b.addEventListener("click", function () { dsEliminarDispositivo(b.dataset.id); });
    });
}

function dsEditarDispositivo(id) {
    dsApiSectores().then(function (data) {
        dsLlenarSelectSectores(data.sectores || []);
        return dsApiDetalle(id);
    }).then(function (item) {
        var sectorId = (item && item.sector_id) ? String(item.sector_id).trim() : "";
        if (sectorId) {
            return dsApiSubSectores(sectorId).then(function (subs) {
                dsLlenarSelectSubSectores(Array.isArray(subs) ? subs : []);
                dsAbrirModal("Editar Dispositivo", item);
            });
        }
        dsLlenarSelectSubSectores([]);
        dsAbrirModal("Editar Dispositivo", item);
    }).catch(function (e) { alert(e.message); });
}

function dsEliminarDispositivo(id) {
    if (!confirm("¿Esta seguro de eliminar este dispositivo?")) return;
    dsApiEliminar(id).then(function () { dsCargarListado(); })
        .catch(function (e) { alert(e.message); });
}

/* ── Guardar formulario ── */
function dsGuardarFormulario() {
    var datos = dsObtenerDatosForm();
    dsEls.btnGuardar.disabled = true;
    dsEls.btnGuardar.textContent = "Guardando...";
    var promesa = datos.id ? dsApiActualizar(datos) : dsApiGuardar(datos);
    promesa.then(function () {
        dsCerrarModal();
        dsCargarListado();
    }).catch(function (e) {
        alert(e.message);
    }).finally(function () {
        dsEls.btnGuardar.disabled = false;
        dsEls.btnGuardar.textContent = "Guardar";
    });
}

/* ── Geolocalización ── */
function dsResetBtnUbi() {
    dsEls.btnUbi.disabled = false;
    dsEls.btnUbi.textContent = "Obtener Ubicacion";
}

function dsObtenerUbicacion() {
    if (!navigator.geolocation) {
        alert("La geolocalizacion no esta disponible en este navegador.");
        return;
    }
    dsEls.btnUbi.disabled = true;
    dsEls.btnUbi.textContent = "Obteniendo...";
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            dsEls.latitud.value = pos.coords.latitude;
            dsEls.longitud.value = pos.coords.longitude;
            dsActualizarUrlMapa();
            dsResetBtnUbi();
        },
        function (err) {
            var msgs = { 1: "Permiso denegado.", 2: "Ubicacion no disponible.", 3: "Tiempo agotado." };
            alert(msgs[err.code] || "No se pudo obtener la ubicacion.");
            dsResetBtnUbi();
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

/* ── Exportar Excel ── */
function dsExportarExcel() {
    var params = new URLSearchParams({
        q: dsState.q,
        tipo_dispositivo: dsState.tipo,
        zona: dsState.zona,
        estado: dsState.estado,
        sector_id: dsState.sectorId,
        sub_sector_id: dsState.subSectorId,
        fecha_desde: dsState.fechaDesde,
        fecha_hasta: dsState.fechaHasta
    }).toString();
    var base = (window.DS_ROUTES && DS_ROUTES.exportar) ? DS_ROUTES.exportar : "exportar";
    window.open(base + "?" + params, "_blank");
}

/* ── Init ── */
function dsInit() {
    dsGetEls();

    // Filtros: submit
    dsEls.form.addEventListener("submit", function (e) {
        e.preventDefault();
        dsSyncFiltros();
        dsState.page = 1;
        dsCargarListado();
    });

    // Filtros: reset
    dsEls.form.addEventListener("reset", function () {
        setTimeout(function () {
            dsState = { page: 1, perPage: 10, q: "", tipo: "", zona: "", estado: "", fechaDesde: "", fechaHasta: "", sectorId: "", subSectorId: "" };
            if (dsEls.subSector) dsLlenarSelectFiltroSubSectores([]);
            dsCargarListado();
        }, 0);
    });

    // Filtros select: auto-filtrar al cambiar
    [dsEls.tipo, dsEls.zona, dsEls.estado].forEach(function (el) {
        if (!el) return;
        el.addEventListener("change", function () {
            dsSyncFiltros();
            dsState.page = 1;
            dsCargarListado();
        });
    });

    // Cargar sectores en filtros al inicio
    dsApiSectores().then(function (data) {
        dsLlenarSelectFiltroSectores(data.sectores || []);
    }).catch(function () {});

    // Al cambiar Sector en filtros, cargar sub_sectores
    if (dsEls.sector) {
        dsEls.sector.addEventListener("change", function () {
            var sectorId = this.value.trim();
            dsLlenarSelectFiltroSubSectores([]);
            if (!sectorId) {
                dsSyncFiltros();
                dsState.page = 1;
                dsCargarListado();
                return;
            }
            dsApiSubSectores(sectorId).then(function (data) {
                dsLlenarSelectFiltroSubSectores(Array.isArray(data) ? data : []);
                dsSyncFiltros();
                dsState.page = 1;
                dsCargarListado();
            }).catch(function () { dsCargarListado(); });
        });
    }
    if (dsEls.subSector) {
        dsEls.subSector.addEventListener("change", function () {
            dsSyncFiltros();
            dsState.page = 1;
            dsCargarListado();
        });
    }

    // Exportar
    dsEls.btnExportar.addEventListener("click", function () {
        dsSyncFiltros();
        dsExportarExcel();
    });

    // Gráficos / Mapa (nueva pestaña con filtros actuales)
    if (dsEls.btnGraficos) {
        dsEls.btnGraficos.addEventListener("click", function () {
            dsSyncFiltros();
            var p = dsGetFiltrosParams();
            var params = new URLSearchParams({
                q: p.q,
                tipo_dispositivo: p.tipo_dispositivo,
                zona: p.zona,
                estado: p.estado,
                sector_id: p.sector_id,
                sub_sector_id: p.sub_sector_id,
                fecha_desde: p.fecha_desde,
                fecha_hasta: p.fecha_hasta
            }).toString();
            var base = (window.DS_ROUTES && DS_ROUTES.graficos) ? DS_ROUTES.graficos : "graficos";
            window.open(base + "?" + params, "_blank");
        });
    }

    if (dsEls.btnMapa) {
        dsEls.btnMapa.addEventListener("click", function () {
            dsSyncFiltros();
            var p = dsGetFiltrosParams();
            var params = new URLSearchParams({
                q: p.q,
                tipo_dispositivo: p.tipo_dispositivo,
                zona: p.zona,
                estado: p.estado,
                sector_id: p.sector_id,
                sub_sector_id: p.sub_sector_id,
                fecha_desde: p.fecha_desde,
                fecha_hasta: p.fecha_hasta
            }).toString();
            var base = (window.DS_ROUTES && DS_ROUTES.mapa) ? DS_ROUTES.mapa : "mapa";
            window.open(base + "?" + params, "_blank");
        });
    }

    // Agregar
    dsEls.btnAgregar.addEventListener("click", function () {
        dsApiSectores().then(function (data) {
            dsLlenarSelectSectores(data.sectores || []);
            dsLlenarSelectSubSectores([]);
            dsAbrirModal("Agregar Dispositivo", {});
        }).catch(function (e) { alert(e.message); });
    });

    // Al cambiar Sector en el modal, cargar solo los sub_sectores de ese sector
    var formSector = document.getElementById("formSector");
    if (formSector) {
        formSector.addEventListener("change", function () {
            var sectorId = this.value.trim();
            dsLlenarSelectSubSectores([]);
            if (!sectorId) return;
            dsApiSubSectores(sectorId).then(function (data) {
                dsLlenarSelectSubSectores(Array.isArray(data) ? data : []);
            }).catch(function () { });
        });
    }

    // Modal cerrar
    dsEls.btnCerrar.addEventListener("click", dsCerrarModal);
    dsEls.btnCancelar.addEventListener("click", dsCerrarModal);

    // Form submit
    dsEls.formDispo.addEventListener("submit", function (e) { e.preventDefault(); dsGuardarFormulario(); });

    // Auto-URL
    dsEls.latitud.addEventListener("input", dsActualizarUrlMapa);
    dsEls.longitud.addEventListener("input", dsActualizarUrlMapa);

    // GPS
    dsEls.btnUbi.addEventListener("click", dsObtenerUbicacion);

    // Cargar datos iniciales
    dsCargarListado();
}

document.addEventListener("DOMContentLoaded", dsInit);
