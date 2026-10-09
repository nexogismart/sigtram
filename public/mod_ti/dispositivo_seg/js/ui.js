/* ── UI helpers ── */
function dsEscapeHtml(v) {
    return String(v || "").replace(/&/g,"&amp;").replace(/</g,"&lt;")
        .replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
}

function dsNormalizarTexto(v) { return String(v || "").trim(); }

/* ── Acceso rapido a elementos del modal (cache interno) ── */
var dsForm = null;
function dsFormEls() {
    if (!dsForm) {
        dsForm = {
            id:       document.getElementById("formId"),
            tipo:     document.getElementById("formTipo"),
            zona:     document.getElementById("formZona"),
            sector:   document.getElementById("formSector"),
            subSector: document.getElementById("formSubSector"),
            kmz:      document.getElementById("formCodigoKmz"),
            nuevoCod: document.getElementById("formNuevoCodigo"),
            dir:      document.getElementById("formDireccion"),
            lat:      document.getElementById("formLatitud"),
            lng:      document.getElementById("formLongitud"),
            url:      document.getElementById("formUrl"),
            ip:       document.getElementById("formIp"),
            marca:    document.getElementById("formMarca"),
            modelo:   document.getElementById("formModelo"),
            tipoEq:   document.getElementById("formTipoEquipo"),
            resolucion: document.getElementById("formResolucion"),
            megapixeles: document.getElementById("formMegapixeles"),
            zoom:     document.getElementById("formZoom"),
            tecnologia: document.getElementById("formTecnologia"),
            anioInst: document.getElementById("formAnioInstalacion"),
            conexion: document.getElementById("formConexion"),
            estado:   document.getElementById("formEstado"),
            vcs:      document.getElementById("formVcs"),
            obs:      document.getElementById("formObservaciones"),
            numOc:    document.getElementById("formNumeroOc"),
            fechaCont: document.getElementById("formFechaContrato"),
            proveedor: document.getElementById("formProveedor"),
            usuarioVisor: document.getElementById("formUsuarioVisor"),
            passwordVisor: document.getElementById("formPasswordVisor"),
            titulo:   document.getElementById("modalTitulo"),
            overlay:  document.getElementById("modalOverlay"),
            form:     document.getElementById("formDispositivo")
        };
    }
    return dsForm;
}

/* ── Tabla ── */
function dsRenderTabla(items) {
    var tbody = document.getElementById("dispositivosTbody");
    if (!items || !items.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="14">No se encontraron registros.</td></tr>';
        return;
    }
    var h = "";
    for (var i = 0; i < items.length; i++) {
        var it = items[i];
        var id = dsEscapeHtml(it.id);
        var nro = dsEscapeHtml(it.item || "-");
        var url = (it.url || "").trim();
        var mapaCell;
        if (url && /^https?:\/\//i.test(url)) {
            mapaCell = '<a href="' + dsEscapeHtml(url) + '" target="_blank" rel="noopener noreferrer" class="btn btn--small btn--map" title="Ver ubicacion en Google Maps">&#x1F4CD;</a>';
        } else if (url) {
            mapaCell = '<span class="text-muted" title="URL no valida">-</span>';
        } else {
            mapaCell = '<span class="text-muted">-</span>';
        }
        h += "<tr>"
            + "<td>" + nro + "</td>"
            + "<td>" + dsEscapeHtml(it.tipo_dispositivo || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.zona || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.sector || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.sub_sector || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.codigo_kmz || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.direccion || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.ip || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.marca || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.modelo || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.estado || "-") + "</td>"
            + "<td>" + dsEscapeHtml(it.ultima_conexion || "-") + "</td>"
            + "<td>" + mapaCell + "</td>"
            + '<td class="acciones-cell">'
            + '<button type="button" class="btn btn--small btn--edit" data-id="' + id + '" title="Editar">&#9998;</button>'
            + '<button type="button" class="btn btn--small btn--delete" data-id="' + id + '" title="Eliminar">&#128465;</button>'
            + "</td></tr>";
    }
    tbody.innerHTML = h;
}

function dsRenderCount(pag) {
    document.getElementById("countLabel").textContent = (pag.total || 0) + " registros";
}

function dsRenderPager(pag, onPageChange) {
    var pager = document.getElementById("pager");
    var p = pag.page || 1, tp = pag.total_pages || 1, t = pag.total || 0;
    pager.innerHTML = '<span>Total: ' + dsEscapeHtml(t) + '</span>'
        + '<button type="button" data-page="' + (p - 1) + '"' + (p <= 1 ? " disabled" : "") + '>Anterior</button>'
        + '<span>Pagina ' + dsEscapeHtml(p) + ' de ' + dsEscapeHtml(tp) + '</span>'
        + '<button type="button" data-page="' + (p + 1) + '"' + (p >= tp ? " disabled" : "") + '>Siguiente</button>';
    pager.querySelectorAll("button[data-page]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var np = Number(btn.getAttribute("data-page"));
            if (!isNaN(np) && np >= 1 && np <= tp) onPageChange(np);
        });
    });
}

/* ── Modal ── */
function dsAbrirModal(titulo, d) {
    var f = dsFormEls();
    d = d || {};
    f.titulo.textContent = titulo;
    f.id.value = d.id || "";
    f.tipo.value = d.tipo_dispositivo || "";
    f.zona.value = d.zona || "";
    f.sector.value = d.sector_id || "";
    f.subSector.value = d.sub_sector_id || "";
    f.kmz.value = d.codigo_kmz || "";
    f.nuevoCod.value = d.nuevo_codigo || "";
    f.dir.value = d.direccion || "";

    f.lat.value = d.latitud || "";
    f.lng.value = d.longitud || "";
    f.url.value = d.url || "";

    f.ip.value = d.ip || "";
    f.marca.value = d.marca || "";
    f.modelo.value = d.modelo || "";
    f.tipoEq.value = d.tipo || "";
    f.resolucion.value = d.resolucion || "";
    f.megapixeles.value = d.megapixeles || "";
    f.zoom.value = d.zoom || "";
    f.tecnologia.value = d.tecnologia || "";
    f.anioInst.value = d.anio_instalacion || "";
    f.conexion.value = d.conexion || "";
    f.estado.value = d.estado || "";
    f.vcs.value = d.vcs || "";
    f.obs.value = d.observaciones || "";

    f.numOc.value = d.numero_oc_contrato || "";
    f.fechaCont.value = d.fecha_contrato || "";
    f.proveedor.value = d.proveedor || "";
    if (f.usuarioVisor) f.usuarioVisor.value = d.usuario_visor || "";
    if (f.passwordVisor) f.passwordVisor.value = d.password_visor || "";

    f.overlay.classList.add("active");
}

function dsCerrarModal() {
    var f = dsFormEls();
    f.overlay.classList.remove("active");
    f.form.reset();
    f.id.value = "";
    f.url.value = "";
    f.lat.value = "";
    f.lng.value = "";
    if (f.passwordVisor) f.passwordVisor.value = "";
}

function dsObtenerDatosForm() {
    var f = dsFormEls();
    return {
        id: f.id.value.trim(),
        tipo_dispositivo: f.tipo.value.trim(),
        zona: f.zona.value.trim(),
        sector_id: f.sector.value.trim(),
        sub_sector_id: f.subSector.value.trim(),
        codigo_kmz: f.kmz.value.trim(),
        nuevo_codigo: f.nuevoCod.value.trim(),
        direccion: f.dir.value.trim(),
        latitud: f.lat.value.trim(),
        longitud: f.lng.value.trim(),
        url: f.url.value.trim(),
        ip: f.ip.value.trim(),
        marca: f.marca.value.trim(),
        modelo: f.modelo.value.trim(),
        tipo: f.tipoEq.value.trim(),
        resolucion: f.resolucion.value.trim(),
        megapixeles: f.megapixeles.value.trim(),
        zoom: f.zoom.value.trim(),
        tecnologia: f.tecnologia.value.trim(),
        anio_instalacion: f.anioInst.value.trim(),
        conexion: f.conexion.value.trim(),
        estado: f.estado.value.trim(),
        vcs: f.vcs.value.trim(),
        observaciones: f.obs.value.trim(),
        numero_oc_contrato: f.numOc.value.trim(),
        fecha_contrato: f.fechaCont.value.trim(),
        proveedor: f.proveedor.value.trim(),
        usuario_visor: f.usuarioVisor ? f.usuarioVisor.value.trim() : "",
        password_visor: f.passwordVisor ? f.passwordVisor.value.trim() : ""
    };
}

function dsActualizarUrlMapa() {
    var f = dsFormEls();
    var lat = f.lat.value.trim();
    var lng = f.lng.value.trim();
    f.url.value = (lat && lng) ? "https://www.google.com/maps?q=" + lat + "," + lng : "";
}

function dsLlenarSelectSectores(opciones) {
    var f = dsFormEls();
    var sel = f.sector;
    if (!sel) return;
    var valorActual = sel.value;
    sel.innerHTML = '<option value="">-- Seleccionar --</option>';
    (opciones || []).forEach(function (o) {
        var opt = document.createElement("option");
        opt.value = o.id;
        opt.textContent = o.nombre || "";
        sel.appendChild(opt);
    });
    if (valorActual) sel.value = valorActual;
}

function dsLlenarSelectSubSectores(opciones) {
    var f = dsFormEls();
    var sel = f.subSector;
    if (!sel) return;
    var valorActual = sel.value;
    sel.innerHTML = '<option value="">-- Seleccionar --</option>';
    (opciones || []).forEach(function (o) {
        var opt = document.createElement("option");
        opt.value = o.id;
        opt.textContent = o.nombre || "";
        sel.appendChild(opt);
    });
    if (valorActual) sel.value = valorActual;
}

function dsLlenarSelectFiltroSectores(opciones) {
    var sel = document.getElementById("filtroSector");
    if (!sel) return;
    var valorActual = sel.value;
    sel.innerHTML = '<option value="">Todos</option>';
    (opciones || []).forEach(function (o) {
        var opt = document.createElement("option");
        opt.value = o.id;
        opt.textContent = o.nombre || "";
        sel.appendChild(opt);
    });
    if (valorActual) sel.value = valorActual;
}

function dsLlenarSelectFiltroSubSectores(opciones) {
    var sel = document.getElementById("filtroSubSector");
    if (!sel) return;
    var valorActual = sel.value;
    sel.innerHTML = '<option value="">Todos</option>';
    (opciones || []).forEach(function (o) {
        var opt = document.createElement("option");
        opt.value = o.id;
        opt.textContent = o.nombre || "";
        sel.appendChild(opt);
    });
    if (valorActual) sel.value = valorActual;
}
