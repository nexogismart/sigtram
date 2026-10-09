/* Buscar requerimiento de pago (solo consulta) */
(function () {
    "use strict";

    var ROUTES = window.SGL_ROUTES || {};
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

    var selArea = document.getElementById("selArea");
    var selCampo = document.getElementById("selCampo");
    var txtCriterio = document.getElementById("txtCriterio");
    var txtFecha = document.getElementById("txtFecha");
    var btnBuscar = document.getElementById("btnBuscar");
    var listaRequerimientos = document.getElementById("listaRequerimientos");
    var lblResultados = document.getElementById("lblResultados");
    var paginacionContainer = document.getElementById("paginacionContainer");

    var detalleVacio = document.getElementById("detalleVacio");
    var detalleContenido = document.getElementById("detalleContenido");
    var detDocumento = document.getElementById("detDocumento");
    var detAsunto = document.getElementById("detAsunto");
    var detArea = document.getElementById("detArea");
    var conteSeguimientoPago = document.getElementById("conteSeguimientoPago");
    var tablaItemsBody = document.getElementById("tablaItemsBody");
    var bloqueSeguimientoTramite = document.getElementById("bloqueSeguimientoTramite");
    var conteSeguimientoTramite = document.getElementById("conteSeguimientoTramite");

    var paginaActual = 1;
    var porPagina = 25;
    var idSeleccionado = null;

    function escapeHtml(value) {
        var div = document.createElement("div");
        div.textContent = value === null || value === undefined ? "" : String(value);
        return div.innerHTML;
    }

    async function apiGet(url, params) {
        var qs = new URLSearchParams(params || {});
        var full = url + (url.indexOf("?") >= 0 ? "&" : "?") + qs.toString();
        var response = await fetch(full, {
            method: "GET",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
        });

        return response.json();
    }

    function renderPaginacion(pagination) {
        if (!paginacionContainer) return;
        paginacionContainer.innerHTML = "";
        if (!pagination || !pagination.total || pagination.total_pages < 1) return;

        var totalPages = pagination.total_pages || 1;
        var page = pagination.page || 1;
        var ventana = 12;
        var seccion = Math.floor((page - 1) / ventana);
        var inicio = seccion * ventana + 1;
        var final = Math.min(inicio + ventana - 1, totalPages);

        var wrap = document.createElement("div");
        wrap.className = "pagination";

        function addBtn(texto, targetPage, disabled, activo) {
            var btn = document.createElement("button");
            btn.type = "button";
            btn.className = activo ? "btn btn--primary btn--sm" : "btn btn--ghost btn--sm";
            btn.textContent = texto;
            btn.disabled = !!disabled;
            if (!disabled && !activo) {
                btn.addEventListener("click", function () {
                    paginaActual = targetPage;
                    cargarLista();
                });
            }
            wrap.appendChild(btn);
        }

        addBtn("Anterior", page - 1, page <= 1, false);
        for (var i = inicio; i <= final; i++) {
            addBtn(String(i), i, false, i === page);
        }
        addBtn("Siguiente", page + 1, page >= totalPages, false);

        var lbl = document.createElement("span");
        lbl.className = "pagination__label";
        lbl.textContent = "Total: " + pagination.total;
        wrap.appendChild(lbl);

        paginacionContainer.appendChild(wrap);
    }

    function renderLista(items, pagination) {
        if (!items || items.length === 0) {
            listaRequerimientos.innerHTML = '<p class="table-empty">No se encontraron resultados.</p>';
            return;
        }

        var offset = ((pagination && pagination.page ? pagination.page : 1) - 1) * (pagination && pagination.per_page ? pagination.per_page : porPagina);

        var table = document.createElement("table");
        table.className = "table";
        table.innerHTML =
            "<thead><tr><th>#</th><th>N° Trám.</th><th>Área</th><th>Items</th><th>Fecha</th></tr></thead>";

        var tbody = document.createElement("tbody");
        items.forEach(function (item, index) {
            var tr = document.createElement("tr");
            tr.className = "table-row--clickable";
            if (String(item.id) === String(idSeleccionado)) {
                tr.classList.add("table-row--active");
            }
            tr.innerHTML =
                "<td>" + (offset + index + 1) + "</td>" +
                "<td>" + escapeHtml(item.documento) + "</td>" +
                '<td title="' + escapeHtml(item.de_area) + '">' + escapeHtml(item.dc_area) + "</td>" +
                '<td style="text-align:center;">' + escapeHtml(item.cantidad_items) + "</td>" +
                '<td style="text-align:center;">' + escapeHtml(item.fecha) + "</td>";
            tr.addEventListener("click", function () {
                idSeleccionado = item.id;
                cargarDetalle(item.id);
                Array.prototype.forEach.call(tbody.querySelectorAll("tr"), function (row) {
                    row.classList.remove("table-row--active");
                });
                tr.classList.add("table-row--active");
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        listaRequerimientos.innerHTML = "";
        listaRequerimientos.appendChild(table);
    }

    async function cargarLista() {
        listaRequerimientos.innerHTML = '<p class="table-empty">Cargando…</p>';
        try {
            var resultado = await apiGet(ROUTES.listar, {
                area_id: selArea ? selArea.value : 0,
                campo: selCampo ? selCampo.value : "nu_tram_todo",
                criterio: txtCriterio ? txtCriterio.value.trim() : "",
                fecha: txtFecha ? txtFecha.value : "",
                pagina: paginaActual,
                porPagina: porPagina,
            });

            if (!resultado || !resultado.success) {
                listaRequerimientos.innerHTML = '<p class="table-empty">' + escapeHtml((resultado && resultado.error) || "No se pudo cargar la lista.") + "</p>";
                lblResultados.textContent = "0 resultados";
                renderPaginacion(null);
                return;
            }

            var data = resultado.data || {};
            renderLista(data.items || [], data.pagination);
            lblResultados.textContent = (data.pagination ? data.pagination.total : 0) + " resultados";
            renderPaginacion(data.pagination);
        } catch (e) {
            listaRequerimientos.innerHTML = '<p class="table-empty">Error de conexión al cargar la lista.</p>';
        }
    }

    function renderSeguimientoPago(pagos) {
        if (!pagos || pagos.length === 0) {
            conteSeguimientoPago.innerHTML = '<p class="table-empty">Sin información de seguimiento.</p>';
            return;
        }

        var html = "";
        pagos.forEach(function (pago) {
            html += '<fieldset class="fieldset-block fieldset-block--nested">';
            html += "<legend>Pago " + pago.pago + " de " + pago.total_pagos + "</legend>";
            if (!pago.filas || pago.filas.length === 0) {
                html += '<p class="table-empty">Sin movimientos registrados.</p>';
            } else {
                html += '<div class="table-wrap"><table class="table">';
                html += "<thead><tr><th>Área origen</th><th>Abastecimiento</th><th>Contabilidad</th><th>Tesorería</th></tr></thead><tbody>";
                pago.filas.forEach(function (fila) {
                    html += "<tr>" +
                        "<td>" + escapeHtml(fila.origen) + "</td>" +
                        "<td>" + escapeHtml(fila.abastecimiento) + "</td>" +
                        "<td>" + escapeHtml(fila.contabilidad) + "</td>" +
                        "<td>" + escapeHtml(fila.tesoreria) + "</td>" +
                        "</tr>";
                });
                html += "</tbody></table></div>";
            }
            html += "</fieldset>";
        });

        conteSeguimientoPago.innerHTML = html;
    }

    function renderItems(items) {
        tablaItemsBody.innerHTML = "";
        if (!items || items.length === 0) {
            tablaItemsBody.innerHTML = '<tr><td colspan="9" class="table-empty">Sin items registrados.</td></tr>';
            return;
        }

        items.forEach(function (item) {
            var tr = document.createElement("tr");
            var tdr = item.url_tdr
                ? '<a href="' + ROUTES.archivo + "?archivo=" + encodeURIComponent(item.url_tdr) + '" target="_blank" rel="noopener">Ver</a>'
                : "-";
            var ane = item.url_ane
                ? '<a href="' + ROUTES.archivo + "?archivo=" + encodeURIComponent(item.url_ane) + '" target="_blank" rel="noopener">Ver</a>'
                : "-";
            tr.innerHTML =
                "<td>" + escapeHtml(item.n) + "</td>" +
                '<td style="text-align:center;">' + escapeHtml(item.cantidad) + "</td>" +
                "<td>" + escapeHtml(item.descripcion) + "</td>" +
                '<td style="text-align:center;">' + escapeHtml(item.pagos) + "</td>" +
                '<td style="text-align:right;">' + escapeHtml(item.monto) + "</td>" +
                '<td style="text-align:center;">' + escapeHtml(item.siga) + "</td>" +
                '<td style="text-align:center;">' + escapeHtml(item.os) + "</td>" +
                '<td style="text-align:center;">' + tdr + "</td>" +
                '<td style="text-align:center;">' + ane + "</td>";
            tablaItemsBody.appendChild(tr);
        });
    }

    function renderSeguimientoTramite(cdgoDtosNtrnos) {
        if (!bloqueSeguimientoTramite || !conteSeguimientoTramite) return;

        if (!cdgoDtosNtrnos || !ROUTES.seguimiento_tramite) {
            bloqueSeguimientoTramite.hidden = true;
            conteSeguimientoTramite.innerHTML = "";
            return;
        }

        bloqueSeguimientoTramite.hidden = false;
        var src = ROUTES.seguimiento_tramite + "?nu_expe=" + encodeURIComponent(cdgoDtosNtrnos);
        conteSeguimientoTramite.innerHTML =
            '<iframe src="' + src + '" width="100%" height="300" style="border:none;" frameborder="0" scrolling="auto" title="Seguimiento del trámite"></iframe>';
    }

    async function cargarDetalle(id) {
        detalleVacio.hidden = true;
        detalleContenido.hidden = false;
        detDocumento.textContent = "Cargando…";
        detAsunto.textContent = "";
        detArea.textContent = "";
        conteSeguimientoPago.innerHTML = "";
        tablaItemsBody.innerHTML = "";
        if (bloqueSeguimientoTramite) bloqueSeguimientoTramite.hidden = true;
        if (conteSeguimientoTramite) conteSeguimientoTramite.innerHTML = "";

        try {
            var resultado = await apiGet(ROUTES.detalle, { id: id });
            if (!resultado || !resultado.success) {
                detDocumento.textContent = (resultado && resultado.error) || "No se pudo cargar el detalle.";
                return;
            }

            var data = resultado.data || {};
            detDocumento.textContent = data.documento || "-";
            detAsunto.textContent = data.asunto || "-";
            detArea.textContent = data.de_area || "-";
            renderSeguimientoPago(data.seguimiento || []);
            renderItems(data.items || []);
            renderSeguimientoTramite(data.cdgo_dtos_ntrnos);
        } catch (e) {
            detDocumento.textContent = "Error de conexión al cargar el detalle.";
        }
    }

    if (btnBuscar) {
        btnBuscar.addEventListener("click", function () {
            paginaActual = 1;
            cargarLista();
        });
    }

    if (txtCriterio) {
        txtCriterio.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                paginaActual = 1;
                cargarLista();
            }
        });
    }

    cargarLista();

    var openId = parseInt(window.SGL_OPEN_PGO_ID || 0, 10);
    if (openId > 0) {
        cargarDetalle(openId);
    }
})();
