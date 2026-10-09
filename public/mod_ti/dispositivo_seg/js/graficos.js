/* Gráficos - datos desde estadisticas.php, Chart.js */
(function () {
    var CSRF = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute("content") : "";

    function paramsFromForm() {
        var f = document.getElementById("filtrosForm");
        if (!f) return {};
        var o = {};
        ["q", "tipo_dispositivo", "zona", "estado", "sector_id", "sub_sector_id", "fecha_desde", "fecha_hasta"].forEach(function (k) {
            var el = f.elements[k];
            if (el && el.value) o[k] = el.value;
        });
        return o;
    }

    function fetchEstadisticas() {
        var qs = new URLSearchParams(paramsFromForm()).toString();
        var url = (window.DS_ROUTES && DS_ROUTES.estadisticas ? DS_ROUTES.estadisticas : "estadisticas") + "?" + qs + "&_=" + Date.now();
        return fetch(url, { credentials: "same-origin", headers: { "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" }, cache: "no-store" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.error || "Error al cargar estadísticas.");
                return data.data;
            });
    }

    var coloresCorporativos = ["#1565c0", "#2e7d32", "#c0392b", "#6a1b9a", "#00838f", "#ef6c00", "#5d4037", "#455a64", "#37474f", "#263238"];
    var colorPorEstadoGrafico = { OPERATIVO: "#2e7d32", INTERMITENTE: "#FBC02D", INOPERATIVO: "#c0392b", MANTENIMIENTO: "#5d4037", "Sin dato": "#78909c" };
    var opcionesComunes = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: "bottom", labels: { font: { size: 11 }, padding: 12 } }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 }, maxRotation: 45 } },
            y: { beginAtZero: true, grid: { color: "#f0f0f0" }, ticks: { font: { size: 11 }, stepSize: 1 } }
        }
    };

    /* Gráfico de dona: leyenda con cantidad, % y horas (desde ultima_conexion). */
    var opcionesDona = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "bottom",
                labels: {
                    font: { size: 11 },
                    padding: 12,
                    generateLabels: function (chart) {
                        var ds = chart.data.datasets[0];
                        var total = ds.data.reduce(function (a, b) { return a + b; }, 0);
                        var horas = chart.data.horas_totales || [];
                        return (chart.data.labels || []).map(function (label, i) {
                            var v = ds.data[i];
                            var pct = total > 0 ? ((v / total) * 100).toFixed(1) : 0;
                            var txt = label + " (" + v + " - " + pct + "%)";
                            if (horas[i] != null && horas[i] !== "") txt += " - " + formatHoras(horas[i]) + " h";
                            return { text: txt, fillStyle: ds.backgroundColor[i], index: i };
                        });
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function (ctx) {
                        var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                        var pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                        var line = ctx.label + ": " + ctx.raw + " (" + pct + "%)";
                        var horas = ctx.chart.data.horas_totales;
                        if (horas && horas[ctx.dataIndex] != null && horas[ctx.dataIndex] !== "") line += " - " + formatHoras(horas[ctx.dataIndex]) + " h";
                        return line;
                    }
                }
            }
        }
    };

    function formatHoras(h) {
        if (h == null || h === "") return "0";
        var n = Number(h);
        if (isNaN(n)) return "0";
        return n >= 1000 ? n.toFixed(0) : (n % 1 === 0 ? n.toString() : n.toFixed(1));
    }

    /* Barras horizontales (solo se mantiene por si se quiere usar en otro gráfico). */
    var opcionesBarraHorizontal = {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function (ctx) {
                        var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                        var pct = total > 0 ? ((ctx.raw / total) * 100).toFixed(1) : 0;
                        return ctx.label + ": " + ctx.raw + " (" + pct + "%)";
                    }
                }
            }
        },
        scales: {
            x: { beginAtZero: true, ticks: { font: { size: 11 }, stepSize: 1 }, grid: { color: "#f0f0f0" } },
            y: { grid: { display: false }, ticks: { font: { size: 11 } } }
        },
        barPercentage: 0.7,
        categoryPercentage: 0.8
    };

    function graficoBarra(canvasId, datos, titulo) {
        var ctx = document.getElementById(canvasId);
        if (!ctx || !datos || !datos.length) return;
        var labels = datos.map(function (d) { return d.label; });
        var values = datos.map(function (d) { return d.total; });
        var horas = datos.map(function (d) { return d.horas_totales != null ? d.horas_totales : ""; });
        var bg = labels.map(function (_, i) { return coloresCorporativos[i % coloresCorporativos.length]; });
        var opts = JSON.parse(JSON.stringify(opcionesComunes));
        if (horas.some(function (h) { return h !== "" && h != null; })) {
            opts.plugins.tooltip = {
                callbacks: {
                    label: function (ctx) {
                        var line = ctx.label + ": " + ctx.raw;
                        if (horas[ctx.dataIndex] != null && horas[ctx.dataIndex] !== "") line += " - " + formatHoras(horas[ctx.dataIndex]) + " h";
                        return line;
                    }
                }
            };
        }
        new Chart(ctx, {
            type: "bar",
            data: { labels: labels, datasets: [{ label: "Cantidad", data: values, backgroundColor: bg }] },
            options: opts
        });
    }

    function graficoBarraHorizontal(canvasId, datos) {
        var ctx = document.getElementById(canvasId);
        if (!ctx || !datos || !datos.length) return;
        var labels = datos.map(function (d) { return d.label; });
        var values = datos.map(function (d) { return d.total; });
        var bg = labels.map(function (_, i) { return coloresCorporativos[i % coloresCorporativos.length]; });
        new Chart(ctx, {
            type: "bar",
            data: { labels: labels, datasets: [{ label: "Cantidad", data: values, backgroundColor: bg, minBarLength: 12 }] },
            options: opcionesBarraHorizontal
        });
    }

    function graficoQueso(canvasId, datos) {
        var ctx = document.getElementById(canvasId);
        if (!ctx || !datos || !datos.length) return;
        var labels = datos.map(function (d) { return d.label; });
        var values = datos.map(function (d) { return d.total; });
        var horas = datos.map(function (d) { return d.horas_totales != null ? d.horas_totales : ""; });
        var bg = canvasId === "chartEstado"
            ? labels.map(function (label) { return colorPorEstadoGrafico[label] || coloresCorporativos[0]; })
            : labels.map(function (_, i) { return coloresCorporativos[i % coloresCorporativos.length]; });
        new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: labels,
                datasets: [{ data: values, backgroundColor: bg, borderWidth: 2, borderColor: "#fff" }],
                horas_totales: horas
            },
            options: opcionesDona
        });
    }

    function actualizarResumen(total) {
        var el = document.getElementById("resumenLabel");
        if (el) el.textContent = "Total de dispositivos en el reporte: " + (total || 0);
    }

    function escapeHtml(s) { return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }

    function iconUrlPorTipo(tipo, tipo_dispositivo) {
        var td = String(tipo_dispositivo || "").toUpperCase();
        var t = String(tipo || "").toUpperCase();
        if (td === "ALARMA" || t === "ALARMA") return ((window.DS_ROUTES && DS_ROUTES.asset_image) ? DS_ROUTES.asset_image : "image") + "/ALARMA.png";
        return (t.indexOf("PTZ") !== -1) ? (((window.DS_ROUTES && DS_ROUTES.asset_image) ? DS_ROUTES.asset_image : "image") + "/camara_ptz.jpg") : (((window.DS_ROUTES && DS_ROUTES.asset_image) ? DS_ROUTES.asset_image : "image") + "/camara_fija.png");
    }

    function renderReporteSectorSubsector(porSectorSubsector) {
        var el = document.getElementById("reporteSectorSubsector");
        if (!el) return;
        if (!porSectorSubsector || !porSectorSubsector.length) {
            el.innerHTML = '<p class="reporte-sector-subsector--empty">No hay dispositivos con sector y subsector asignados en el reporte.<br><small>Asigna sector y subsector a los dispositivos para verlos aquí.</small></p>';
            return;
        }
        var gruposPorSector = {};
        for (var i = 0; i < porSectorSubsector.length; i++) {
            var g = porSectorSubsector[i];
            var sec = escapeHtml(g.sector || "Sin sector");
            var sub = escapeHtml(g.sub_sector || "Sin subsector");
            var camaras = Array.isArray(g.camaras) ? g.camaras : [];
            if (!gruposPorSector[sec]) gruposPorSector[sec] = [];
            gruposPorSector[sec].push({ sub_sector: sub, total: g.total, camaras: camaras });
        }
        var html = "";
        var sectores = Object.keys(gruposPorSector).sort();
        for (var s = 0; s < sectores.length; s++) {
            var sectorNombre = sectores[s];
            var subs = gruposPorSector[sectorNombre];
            html += '<div class="reporte-sector-subsector__grupo">';
            html += '<div class="reporte-sector-subsector__sector">' + sectorNombre + '</div>';
            for (var u = 0; u < subs.length; u++) {
                var subItem = subs[u];
                html += '<div class="reporte-sector-subsector__sub">';
                html += '<div class="reporte-sector-subsector__sub-titulo">' + subItem.sub_sector + '<span class="reporte-sector-subsector__sub-badge">' + subItem.total + '</span></div>';
                html += '<div class="reporte-sector-subsector__camaras">';
                for (var c = 0; c < subItem.camaras.length; c++) {
                    var cam = subItem.camaras[c];
                    var id = (cam && cam.id) ? cam.id : "";
                    var lbl = (cam && cam.label != null) ? cam.label : (typeof cam === "string" ? cam : "");
                    var tipo = (cam && cam.tipo != null) ? cam.tipo : "";
                    var tipo_disp = (cam && cam.tipo_dispositivo != null) ? cam.tipo_dispositivo : "";
                    var iconSrc = iconUrlPorTipo(tipo, tipo_disp);
                    html += '<span class="reporte-sector-subsector__camara" data-id="' + escapeHtml(String(id)) + '" title="Ver detalle"><img src="' + escapeHtml(iconSrc) + '" alt="" class="reporte-sector-subsector__camara-icon">' + escapeHtml(lbl) + '</span>';
                }
                html += '</div></div>';
            }
            html += '</div>';
        }
        el.innerHTML = html;
        el.querySelectorAll(".reporte-sector-subsector__camara[data-id]").forEach(function (btn) {
            var id = btn.getAttribute("data-id");
            if (id) btn.addEventListener("click", function () { abrirModalDetalle(id); });
        });
    }

    function abrirModalDetalle(id) {
        if (!id) return;
        var overlay = document.getElementById("modalDetalleOverlay");
        var titulo = document.getElementById("modalDetalleTitulo");
        var cuerpo = document.getElementById("modalDetalleCuerpo");
        if (!overlay || !cuerpo) return;
        titulo.textContent = "Cargando...";
        cuerpo.innerHTML = '<p style="text-align:center;color:var(--muted);">Obteniendo detalle del dispositivo...</p>';
        overlay.classList.add("active");
        if (typeof dsApiDetalle !== "function") {
            cuerpo.innerHTML = '<p style="color:#c0392b;">Error: no se puede cargar el detalle.</p>';
            return;
        }
        dsApiDetalle(id).then(function (item) {
            titulo.textContent = item.nuevo_codigo || item.codigo_kmz || "Dispositivo " + id;
            cuerpo.innerHTML = renderDetalleHTML(item);
        }).catch(function (err) {
            titulo.textContent = "Error";
            cuerpo.innerHTML = '<p style="color:#c0392b;">' + escapeHtml(err.message || "No se pudo cargar el detalle.") + '</p>';
        });
    }

    function renderDetalleHTML(item) {
        if (!item) return "";
        var iconUrl = iconUrlPorTipo(item.tipo || "", item.tipo_dispositivo || "");
        var rows = [
            { label: "Tipo dispositivo", val: item.tipo_dispositivo },
            { label: "Zona", val: item.zona },
            { label: "Sector", val: item.sector },
            { label: "Sub sector", val: item.sub_sector },
            { label: "Código KMZ", val: item.codigo_kmz },
            { label: "Nombre", val: item.nuevo_codigo },
            { label: "Dirección", val: item.direccion },
            { label: "IP", val: item.ip },
            { label: "Marca", val: item.marca },
            { label: "Modelo", val: item.modelo },
            { label: "Tipo (Fija/PTZ)", val: item.tipo },
            { label: "Estado", val: item.estado },
            { label: "Última conexión", val: item.ultima_conexion },
            { label: "Ubicación", val: item.url ? '<a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">Ver en mapa</a>' : "-" }
        ];
        var h = '';
        if (iconUrl) h += '<div style="margin-bottom:16px;"><img src="' + escapeHtml(iconUrl) + '" alt="" class="modal-detalle__camara-icon"></div>';
        for (var i = 0; i < rows.length; i++) {
            var v = rows[i].val;
            if (v == null || v === "") v = "-";
            h += '<div class="modal-detalle__row"><span class="modal-detalle__label">' + escapeHtml(rows[i].label) + '</span><span class="modal-detalle__value">' + (typeof v === "string" && v.indexOf("<a ") === 0 ? v : escapeHtml(String(v))) + '</span></div>';
        }
        return h;
    }

    function cerrarModalDetalle() {
        var overlay = document.getElementById("modalDetalleOverlay");
        if (overlay) overlay.classList.remove("active");
    }

    function dibujarTodo(data) {
        actualizarResumen(data.total);
        graficoQueso("chartEstado", data.por_estado);
        graficoQueso("chartTipoDispositivo", data.por_tipo_dispositivo);
        graficoQueso("chartZona", data.por_zona);
        graficoQueso("chartTipo", data.por_tipo);
        graficoBarra("chartMarca", data.por_marca);
        graficoBarra("chartModelo", data.por_modelo);
        renderReporteSectorSubsector(data.por_sector_subsector || []);
        renderResumenEstados(data.por_estado || []);
        renderMapa(data.por_camara || [], data.por_estado || []);
    }

    function renderResumenEstados(porEstado) {
        var el = document.getElementById("resumenEstadosContainer");
        if (!el) return;
        if (!porEstado || !porEstado.length) {
            el.innerHTML = "";
            return;
        }
        var labels = { OPERATIVO: "Operativas", INOPERATIVO: "Inoperativas", INTERMITENTE: "Intermitentes", MANTENIMIENTO: "En mantenimiento", "Sin dato": "Sin dato" };
        var clases = { OPERATIVO: "operativo", INOPERATIVO: "inoperativo", INTERMITENTE: "intermitente", MANTENIMIENTO: "mantenimiento", "Sin dato": "sindato" };
        var html = '<div class="resumen-estados__strip">';
        for (var i = 0; i < porEstado.length; i++) {
            var it = porEstado[i];
            var nombre = labels[it.label] || it.label;
            var c = clases[it.label] || "sindato";
            html += '<span class="resumen-estados__item resumen-estados__item--' + c + '">' + escapeHtml(nombre) + ': <strong>' + it.total + '</strong></span>';
        }
        html += "</div>";
        el.innerHTML = html;
    }

    var colorEstadoMapa = { OPERATIVO: "#2e7d32", INOPERATIVO: "#c0392b", INTERMITENTE: "#FBC02D", MANTENIMIENTO: "#5d4037" };
    var iconUrlPorTipo = function (tipo, tipo_dispositivo) {
        var td = String(tipo_dispositivo || "").toUpperCase();
        var t = String(tipo || "").toUpperCase();
        if (td === "ALARMA" || t === "ALARMA") return ((window.DS_ROUTES && DS_ROUTES.asset_image) ? DS_ROUTES.asset_image : "image") + "/ALARMA.png";
        return (t.indexOf("PTZ") !== -1) ? (((window.DS_ROUTES && DS_ROUTES.asset_image) ? DS_ROUTES.asset_image : "image") + "/camara_ptz.jpg") : (((window.DS_ROUTES && DS_ROUTES.asset_image) ? DS_ROUTES.asset_image : "image") + "/camara_fija.png");
    };
    var mapaLeaflet = null;
    function renderMapa(porCamara, porEstado) {
        var cont = document.getElementById("mapaDispositivos");
        var sinEl = document.getElementById("mapaSinUbicacion");
        if (!cont) return;
        if (mapaLeaflet) { mapaLeaflet.remove(); mapaLeaflet = null; }
        if (!porCamara || !porCamara.length) {
            cont.innerHTML = '<p class="chart-camara-empty">No hay dispositivos para mostrar.</p>';
            if (sinEl) sinEl.textContent = "";
            return;
        }
        var conCoords = [];
        var sinCoords = [];
        for (var j = 0; j < porCamara.length; j++) {
            var d = porCamara[j];
            var lat = d.latitud != null && !isNaN(Number(d.latitud));
            var lon = d.longitud != null && !isNaN(Number(d.longitud));
            if (lat && lon) conCoords.push(d); else sinCoords.push(d);
        }
        if (sinEl) sinEl.textContent = sinCoords.length > 0 ? sinCoords.length + " dispositivo(s) sin ubicación en el mapa." : "";
        if (conCoords.length === 0) {
            cont.innerHTML = '<p class="chart-camara-empty">Ningún dispositivo tiene coordenadas para mostrar en el mapa.</p>';
            return;
        }
        cont.innerHTML = "";
        var map = L.map("mapaDispositivos").setView([-12.0, -77.0], 11);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "&copy; OpenStreetMap" }).addTo(map);
        var bounds = [];
        for (var k = 0; k < conCoords.length; k++) {
            var item = conCoords[k];
            var lat = Number(item.latitud);
            var lon = Number(item.longitud);
            var color = colorEstadoMapa[item.estado] || "#78909c";
            var iconUrl = iconUrlPorTipo(item.tipo, item.tipo_dispositivo);
            var codigo = escapeHtml(item.label);
            var popup = escapeHtml(item.label) + "<br><strong>" + escapeHtml(item.estado || "Sin dato") + "</strong><br>" + (item.tipo ? "Tipo: " + escapeHtml(item.tipo) + "<br>" : "") + formatHoras(item.horas_totales) + " h desde última conexión";
            var iconHtml = '<div class="map-marker-contenedor"><div class="map-marker-wrap" style="border-color:' + color + '"><img src="' + escapeHtml(iconUrl) + '" alt="" class="map-marker-img"></div><span class="map-marker-codigo">' + codigo + '</span></div>';
            var icon = L.divIcon({
                html: iconHtml,
                className: "map-marker-custom",
                iconSize: [56, 48],
                iconAnchor: [28, 32]
            });
            var marker = L.marker([lat, lon], { icon: icon }).addTo(map);
            marker.bindPopup(popup);
            bounds.push([lat, lon]);
        }
        if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
        mapaLeaflet = map;
    }

    document.addEventListener("DOMContentLoaded", function () {
        var form = document.getElementById("filtrosForm");
        var sectorId = form && form.getAttribute("data-sector-id") ? form.getAttribute("data-sector-id") : "";
        var subSectorId = form && form.getAttribute("data-sub-sector-id") ? form.getAttribute("data-sub-sector-id") : "";

        function llenarFiltroSectores(opciones) {
            var sel = document.getElementById("filtroSector");
            if (!sel) return;
            sel.innerHTML = '<option value="">Todos</option>';
            (opciones || []).forEach(function (o) {
                var opt = document.createElement("option");
                opt.value = o.id;
                opt.textContent = o.nombre || "";
                sel.appendChild(opt);
            });
            if (sectorId) sel.value = sectorId;
        }
        function llenarFiltroSubSectores(opciones) {
            var sel = document.getElementById("filtroSubSector");
            if (!sel) return;
            sel.innerHTML = '<option value="">Todos</option>';
            (opciones || []).forEach(function (o) {
                var opt = document.createElement("option");
                opt.value = o.id;
                opt.textContent = o.nombre || "";
                sel.appendChild(opt);
            });
            if (subSectorId) sel.value = subSectorId;
        }

        function cargarYMostrar() {
            llenarFiltroSectores([]);
            llenarFiltroSubSectores([]);
            if (typeof dsApiSectores !== "function") {
                fetchEstadisticas().then(dibujarTodo).catch(onErr);
                return;
            }
            dsApiSectores().then(function (data) {
                llenarFiltroSectores(data.sectores || []);
                if (sectorId) {
                    return (typeof dsApiSubSectores === "function" ? dsApiSubSectores(sectorId) : Promise.resolve([])).then(function (subs) {
                        llenarFiltroSubSectores(Array.isArray(subs) ? subs : []);
                        fetchEstadisticas().then(dibujarTodo).catch(onErr);
                    });
                }
                fetchEstadisticas().then(dibujarTodo).catch(onErr);
            }).catch(function () {
                fetchEstadisticas().then(dibujarTodo).catch(onErr);
            });
        }
        function onErr(err) {
            var el = document.getElementById("resumenLabel");
            if (el) el.textContent = "Error: " + (err.message || "No se pudieron cargar las estadísticas.");
        }

        var filtroSector = document.getElementById("filtroSector");
        var filtroSubSector = document.getElementById("filtroSubSector");
        if (filtroSector && typeof dsApiSubSectores === "function") {
            filtroSector.addEventListener("change", function () {
                var sid = this.value.trim();
                llenarFiltroSubSectores([]);
                if (!sid) {
                    fetchEstadisticas().then(dibujarTodo).catch(onErr);
                    return;
                }
                dsApiSubSectores(sid).then(function (subs) {
                    llenarFiltroSubSectores(Array.isArray(subs) ? subs : []);
                    fetchEstadisticas().then(dibujarTodo).catch(onErr);
                });
            });
        }
        if (filtroSubSector) {
            filtroSubSector.addEventListener("change", function () {
                fetchEstadisticas().then(dibujarTodo).catch(onErr);
            });
        }

        cargarYMostrar();

        var modalCerrar = document.getElementById("modalDetalleCerrar");
        var modalOverlay = document.getElementById("modalDetalleOverlay");
        if (modalCerrar) modalCerrar.addEventListener("click", cerrarModalDetalle);
        if (modalOverlay) modalOverlay.addEventListener("click", function (e) {
            if (e.target === modalOverlay) cerrarModalDetalle();
        });

        var btnExcel = document.getElementById("btnDescargarExcel");
        if (btnExcel) btnExcel.addEventListener("click", function () {
            var params = paramsFromForm();
            var qs = new URLSearchParams(params).toString();
            window.open(((window.DS_ROUTES && DS_ROUTES.exportar) ? DS_ROUTES.exportar : "exportar") + "?" + qs, "_blank");
        });

        var btnMapa = document.getElementById("btnDescargarMapa");
        if (btnMapa) btnMapa.addEventListener("click", function () {
            var area = document.getElementById("mapaExportArea");
            if (!area || typeof html2canvas === "undefined") {
                alert("No se puede generar la imagen. Use Imprimir (Ctrl+P) y \"Guardar como PDF\" para exportar.");
                return;
            }
            var btn = this;
            btn.disabled = true;
            btn.textContent = "Generando...";
            html2canvas(area, { useCORS: true, allowTaint: true, scale: 2, backgroundColor: "#fff" }).then(function (canvas) {
                var link = document.createElement("a");
                link.download = "mapa_dispositivos_" + new Date().toISOString().slice(0, 10) + ".png";
                link.href = canvas.toDataURL("image/png");
                link.click();
            }).catch(function () {
                alert("No se pudo generar la imagen. Use Imprimir (Ctrl+P) y \"Guardar como PDF\".");
            }).finally(function () {
                btn.disabled = false;
                btn.textContent = "Descargar mapa (PNG)";
            });
        });
    });
})();
