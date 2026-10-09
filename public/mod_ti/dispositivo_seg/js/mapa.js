/* Mapa full page - dispositivos con leyenda, detalle, Editar y PING */
(function () {
    var CSRF = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute("content") : "";
    var colorEstadoMapa = { OPERATIVO: "#2e7d32", INOPERATIVO: "#c0392b", INTERMITENTE: "#FBC02D", MANTENIMIENTO: "#5d4037" };
    var mapaLeaflet = null;
    var markersPorId = {};
    var dispositivoActualId = null;
    var dispositivoActualMarker = null;
    var visorStreamActivoId = null;
    var visorHlsInstance = null;
    var capaLayerZonas = null;
    var capaLayerSectores = null;
    var capaLayerSubSectores = null;
    var capaLayerCamaras = null;
    var capaLayersPorZona = {};
    var capaLayersPorSector = {};
    var capaLayersPorSubSector = {};
    var filtroEstadosCamaras = { OPERATIVO: true, INOPERATIVO: true, INTERMITENTE: true };
    var filtroTiposCamaras = { PTZ: true, FIJA: true };
    var filtroEstadosAlarmas = { OPERATIVO: true, INOPERATIVO: true, INTERMITENTE: true };
    var todosLosMarkersCamara = [];

    function paramsFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var o = {};
        ["q", "tipo_dispositivo", "zona", "estado", "sector_id", "sub_sector_id", "fecha_desde", "fecha_hasta"].forEach(function (k) {
            var v = params.get(k);
            if (v) o[k] = v;
        });
        return o;
    }

    function fetchEstadisticas() {
        var qs = new URLSearchParams(paramsFromUrl()).toString();
        var url = (window.DS_ROUTES && DS_ROUTES.estadisticas ? DS_ROUTES.estadisticas : "estadisticas") + "?" + qs + "&_=" + Date.now();
        return fetch(url, { credentials: "same-origin", headers: { "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" }, cache: "no-store" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.error || "Error al cargar datos.");
                return data.data;
            });
    }

    function escapeHtml(s) {
        return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function setLeyendaConteos(conteos) {
        var elTotal = document.getElementById("leyendaTotal");
        var elOp = document.getElementById("leyendaOperativo");
        var elInop = document.getElementById("leyendaInoperativo");
        var elInt = document.getElementById("leyendaIntermitente");
        var elMant = document.getElementById("leyendaMantenimiento");
        if (elTotal) elTotal.textContent = String(conteos.total || 0);
        if (elOp) elOp.textContent = String(conteos.OPERATIVO || 0);
        if (elInop) elInop.textContent = String(conteos.INOPERATIVO || 0);
        if (elInt) elInt.textContent = String(conteos.INTERMITENTE || 0);
        if (elMant) elMant.textContent = String(conteos.MANTENIMIENTO || 0);
    }

    function conteosDesdeFiltrosActuales() {
        var conteos = { total: 0, OPERATIVO: 0, INOPERATIVO: 0, INTERMITENTE: 0, MANTENIMIENTO: 0 };
        var chkCamaras = document.getElementById("capaCamaras");
        var chkAlarmas = document.getElementById("capaAlarmas");
        var mostrarCamaras = chkCamaras ? chkCamaras.checked : true;
        var mostrarAlarmas = chkAlarmas ? chkAlarmas.checked : true;

        if (!todosLosMarkersCamara || !todosLosMarkersCamara.length) return conteos;
        todosLosMarkersCamara.forEach(function (marker) {
            var item = marker._dispositivoItem;
            if (!item) return;
            var estado = item.estado;
            if (!estado) return;

            var tipoDisp = String(item.tipo_dispositivo || "").toUpperCase();
            var esAlarma = (tipoDisp === "ALARMA");

            if (esAlarma && !mostrarAlarmas) return;
            if (!esAlarma && !mostrarCamaras) return;

            var tipoCat = normalizarTipoCamara(marker._dispositivoTipo);
            
            if (esAlarma) {
                if (filtroEstadosAlarmas[estado] === true) {
                    conteos.total++;
                    if (conteos[estado] == null) conteos[estado] = 0;
                    conteos[estado]++;
                }
            } else {
                if (filtroEstadosCamaras[estado] === true && filtroTiposCamaras[tipoCat] === true) {
                    conteos.total++;
                    if (conteos[estado] == null) conteos[estado] = 0;
                    conteos[estado]++;
                }
            }
        });
        return conteos;
    }

    function refrescarLeyendaDesdeMapa() {
        setLeyendaConteos(conteosDesdeFiltrosActuales());
    }

    function iconUrlPorTipo(tipo, tipo_dispositivo) {
        var td = String(tipo_dispositivo || "").toUpperCase();
        var t = String(tipo || "").toUpperCase();
        if (td === "ALARMA" || t === "ALARMA") return ((window.DS_ROUTES && DS_ROUTES.asset_image) ? DS_ROUTES.asset_image : "image") + "/ALARMA.png";
        return (t.indexOf("PTZ") !== -1) ? (((window.DS_ROUTES && DS_ROUTES.asset_image) ? DS_ROUTES.asset_image : "image") + "/camara_ptz.jpg") : (((window.DS_ROUTES && DS_ROUTES.asset_image) ? DS_ROUTES.asset_image : "image") + "/camara_fija.png");
    }

    function normalizarTipoCamara(tipo) {
        var t = String(tipo || "").toUpperCase();
        if (t.indexOf("PTZ") !== -1) return "PTZ";
        // En la BD puede venir como "FIJA", "FIJAS" o cualquier valor equivalente.
        if (t.indexOf("FIJ") !== -1) return "FIJA";
        // Default: si no se detecta "PTZ", asumimos cámara fija.
        return "FIJA";
    }

    function crearIcono(item) {
        var color = colorEstadoMapa[item.estado] || "#78909c";
        var iconUrl = iconUrlPorTipo(item.tipo, item.tipo_dispositivo);
        var codigo = escapeHtml(item.label);
        var iconHtml = '<div class="map-marker-contenedor"><div class="map-marker-wrap" style="border-color:' + color + '"><img src="' + escapeHtml(iconUrl) + '" alt="" class="map-marker-img"></div><span class="map-marker-codigo">' + codigo + '</span></div>';
        return L.divIcon({
            html: iconHtml,
            className: "map-marker-custom",
            iconSize: [56, 48],
            iconAnchor: [28, 32]
        });
    }

    function actualizarIconoMarker(marker, estado) {
        if (!marker || !marker._dispositivoItem) return;
        var item = { label: marker._dispositivoLabel, tipo: marker._dispositivoTipo || "", tipo_dispositivo: marker._dispositivoItem.tipo_dispositivo || "", estado: estado };
        marker.setIcon(crearIcono(item));
        marker._dispositivoItem.estado = estado;
    }

    var coloresZonas = { 1: "#1565c0", 2: "#2e7d32", 3: "#c62828" };

    function estiloZona(feature) {
        var zonaId = feature.properties && feature.properties.zona_id;
        var color = coloresZonas[zonaId] || "#607d8b";
        return {
            fillColor: color,
            color: color,
            weight: 2,
            opacity: 0.9,
            fillOpacity: 0.15
        };
    }

    function normalizarClaveZona(zona) {
        var z = String(zona || "").toUpperCase();
        if (z.indexOf("NORTE") !== -1) return "NORTE";
        if (z.indexOf("CENTRO") !== -1) return "CENTRO";
        if (z.indexOf("SUR") !== -1) return "SUR";
        return z || "NORTE";
    }

    function crearEtiquetaMapa(texto, clase) {
        return L.divIcon({
            html: '<span class="' + (clase || "mapa-label-zona") + '">' + escapeHtml(texto) + "</span>",
            className: "mapa-label-icon",
            iconSize: [280, 60],
            iconAnchor: [140, 30]
        });
    }

    function cargarCapasZonas(map) {
        if (!map) return;
        capaLayersPorZona = {};
        var parentGroup = L.layerGroup();
        capaLayerZonas = parentGroup;
        fetch((window.DS_ROUTES && DS_ROUTES.zonas_geojson) ? DS_ROUTES.zonas_geojson : "zonas_geojson", { credentials: "same-origin", headers: { "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" } })
            .then(function (r) { return r.json(); })
            .then(function (geojson) {
                if (geojson && geojson.features && geojson.features.length) {
                    geojson.features.forEach(function (feature) {
                        var nombre = (feature.properties && feature.properties.zona) || "Zona";
                        var clave = normalizarClaveZona(nombre);
                        var grupo = L.layerGroup();
                        var geoLayer = L.geoJSON({ type: "FeatureCollection", features: [feature] }, {
                            style: estiloZona,
                            onEachFeature: function (f, layer) {
                                layer.bindTooltip(nombre, { permanent: false, direction: "center", className: "mapa-zona-tooltip" });
                            }
                        });
                        grupo.addLayer(geoLayer);
                        var firstLayer = geoLayer.getLayers()[0];
                        if (firstLayer && firstLayer.getBounds) {
                            var center = firstLayer.getBounds().getCenter();
                            var labelMarker = L.marker(center, {
                                icon: crearEtiquetaMapa(nombre, "mapa-label-zona"),
                                interactive: false
                            });
                            grupo.addLayer(labelMarker);
                        }
                        if (!capaLayersPorZona[clave]) capaLayersPorZona[clave] = L.layerGroup();
                        capaLayersPorZona[clave].addLayer(grupo);
                    });
                    aplicarVisibilidadZonas();
                    if (document.getElementById("capaZonas") && document.getElementById("capaZonas").checked) {
                        parentGroup.addTo(map);
                    }
                }
            })
            .catch(function () {});
    }

    function aplicarVisibilidadZonas() {
        if (!capaLayerZonas) return;
        capaLayerZonas.clearLayers();
        var chkZonas = document.getElementById("capaZonas");
        if (!chkZonas || !chkZonas.checked) return;
        ["NORTE", "CENTRO", "SUR"].forEach(function (clave) {
            var chk = document.querySelector('.capa-zona[data-zona="' + clave + '"]');
            if (chk && chk.checked && capaLayersPorZona[clave]) {
                capaLayerZonas.addLayer(capaLayersPorZona[clave]);
            }
        });
    }

    var coloresSectores = ["#1565c0", "#2e7d32", "#6a1b9a", "#00838f", "#ef6c00", "#c62828", "#00695c", "#37474f", "#5d4037", "#0277bd", "#558b2f", "#7b1fa2", "#d84315"];

    function estiloSector(feature) {
        var sectorId = feature.properties && feature.properties.sector_id;
        var idx = (sectorId && sectorId > 0) ? (sectorId - 1) % coloresSectores.length : 0;
        var color = coloresSectores[idx] || "#607d8b";
        return {
            fillColor: color,
            color: color,
            weight: 1.5,
            opacity: 0.85,
            fillOpacity: 0.12
        };
    }

    function cargarCapasSectores(map) {
        if (!map) return;
        capaLayersPorSector = {};
        var parentGroup = L.layerGroup();
        capaLayerSectores = parentGroup;
        fetch((window.DS_ROUTES && DS_ROUTES.sectores_geojson) ? DS_ROUTES.sectores_geojson : "sectores_geojson", { credentials: "same-origin", headers: { "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" } })
            .then(function (r) { return r.json(); })
            .then(function (geojson) {
                if (geojson && geojson.features && geojson.features.length) {
                    var contSectores = document.getElementById("panelCapasSectoresSub");
                    if (contSectores) contSectores.innerHTML = "";
                    geojson.features.forEach(function (feature) {
                        var sid = feature.properties && feature.properties.sector_id;
                        var desc = (feature.properties && feature.properties.sector_descripcion) || (feature.properties && feature.properties.sector) || "Sector " + sid;
                        var grupo = L.layerGroup();
                        var geoLayer = L.geoJSON({ type: "FeatureCollection", features: [feature] }, {
                            style: estiloSector,
                            onEachFeature: function (f, layer) {
                                layer.bindTooltip(desc, { permanent: false, direction: "center", className: "mapa-sector-tooltip" });
                            }
                        });
                        grupo.addLayer(geoLayer);
                        var firstLayer = geoLayer.getLayers()[0];
                        if (firstLayer && firstLayer.getBounds) {
                            var center = firstLayer.getBounds().getCenter();
                            var labelMarker = L.marker(center, {
                                icon: crearEtiquetaMapa(desc, "mapa-label-sector"),
                                interactive: false
                            });
                            grupo.addLayer(labelMarker);
                        }
                        capaLayersPorSector[sid] = { group: grupo, desc: desc };
                        if (contSectores) {
                            var label = document.createElement("label");
                            label.className = "mapa-panel-capas__label mapa-panel-capas__label--sub";
                            var chk = document.createElement("input");
                            chk.type = "checkbox";
                            chk.className = "mapa-panel-capas__check capa-sector";
                            chk.dataset.sectorId = String(sid);
                            chk.checked = false;
                            var span = document.createElement("span");
                            span.textContent = desc;
                            label.appendChild(chk);
                            label.appendChild(span);
                            contSectores.appendChild(label);
                            chk.addEventListener("change", aplicarVisibilidadSectores);
                        }
                    });
                    aplicarVisibilidadSectores();
                    if (document.getElementById("capaSectores") && document.getElementById("capaSectores").checked) {
                        parentGroup.addTo(map);
                    }
                }
            })
            .catch(function () {});
    }

    function aplicarVisibilidadSectores() {
        if (!capaLayerSectores) return;
        capaLayerSectores.clearLayers();
        var chkSectores = document.getElementById("capaSectores");
        if (!chkSectores || !chkSectores.checked) return;
        var sectoresChk = document.querySelectorAll(".capa-sector:checked");
        sectoresChk.forEach(function (chk) {
            var sid = chk.dataset.sectorId;
            if (capaLayersPorSector[sid]) {
                capaLayerSectores.addLayer(capaLayersPorSector[sid].group);
            }
        });
    }

    var coloresPorSubSectorId = {};
    function colorOscuroParaIndice(indice, total) {
        var h = total > 0 ? (indice * 360 / total) % 360 : 0;
        var s = 78;
        var l = 36;
        var c = (1 - Math.abs(2 * l / 100 - 1)) * s / 100;
        var x = c * (1 - Math.abs((h / 60) % 2 - 1));
        var m = l / 100 - c / 2;
        var r = 0, g = 0, b = 0;
        if (h < 60) { r = c; g = x; b = 0; } else if (h < 120) { r = x; g = c; b = 0; } else if (h < 180) { r = 0; g = c; b = x; } else if (h < 240) { r = 0; g = x; b = c; } else if (h < 300) { r = x; g = 0; b = c; } else { r = c; g = 0; b = x; }
        r = Math.min(255, Math.round((r + m) * 255));
        g = Math.min(255, Math.round((g + m) * 255));
        b = Math.min(255, Math.round((b + m) * 255));
        return "#" + ("0" + r.toString(16)).slice(-2) + ("0" + g.toString(16)).slice(-2) + ("0" + b.toString(16)).slice(-2);
    }
    function estiloSubSector(feature) {
        var sid = feature.properties && feature.properties.subsector_id;
        var color = coloresPorSubSectorId[sid] || "#8b4513";
        return {
            fillColor: color,
            color: color,
            weight: 3,
            opacity: 1,
            fillOpacity: 0.28
        };
    }

    function cargarCapasSubSectores(map) {
        if (!map) return;
        capaLayersPorSubSector = {};
        coloresPorSubSectorId = {};
        var parentGroup = L.layerGroup();
        capaLayerSubSectores = parentGroup;
        fetch((window.DS_ROUTES && DS_ROUTES.subsectores_geojson) ? DS_ROUTES.subsectores_geojson : "subsectores_geojson", { credentials: "same-origin", headers: { "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" } })
            .then(function (r) { return r.json(); })
            .then(function (geojson) {
                if (geojson && geojson.features && geojson.features.length) {
                    var features = geojson.features;
                    var idsUnicos = [];
                    var vistos = {};
                    features.forEach(function (f) {
                        var sid = f.properties && f.properties.subsector_id;
                        if (sid != null && sid !== "" && !vistos[sid]) {
                            vistos[sid] = true;
                            idsUnicos.push(sid);
                        }
                    });
                    var totalIds = idsUnicos.length;
                    idsUnicos.sort(function (a, b) { return Number(a) - Number(b); });
                    idsUnicos.forEach(function (sid, idx) {
                        coloresPorSubSectorId[sid] = colorOscuroParaIndice(idx, totalIds);
                    });
                    var contSub = document.getElementById("panelCapasSubSectoresSub");
                    if (contSub) contSub.innerHTML = "";
                    features.forEach(function (feature) {
                        var gid = feature.properties && feature.properties.gid;
                        var desc = (feature.properties && feature.properties.subsector_descripcion) || (feature.properties && feature.properties.subsector_den) || (feature.properties && feature.properties.subsector) || "SubSector " + gid;
                        var grupo = L.layerGroup();
                        var geoLayer = L.geoJSON({ type: "FeatureCollection", features: [feature] }, {
                            style: estiloSubSector,
                            onEachFeature: function (f, layer) {
                                layer.bindTooltip(desc, { permanent: false, direction: "center", className: "mapa-sector-tooltip" });
                            }
                        });
                        grupo.addLayer(geoLayer);
                        var firstLayer = geoLayer.getLayers()[0];
                        if (firstLayer && firstLayer.getBounds) {
                            var center = firstLayer.getBounds().getCenter();
                            var labelMarker = L.marker(center, {
                                icon: crearEtiquetaMapa(desc, "mapa-label-sector"),
                                interactive: false
                            });
                            grupo.addLayer(labelMarker);
                        }
                        capaLayersPorSubSector[gid] = { group: grupo, desc: desc };
                        if (contSub) {
                            var label = document.createElement("label");
                            label.className = "mapa-panel-capas__label mapa-panel-capas__label--sub";
                            var chk = document.createElement("input");
                            chk.type = "checkbox";
                            chk.className = "mapa-panel-capas__check capa-subsector";
                            chk.dataset.subsectorGid = String(gid);
                            chk.checked = false;
                            var span = document.createElement("span");
                            span.textContent = desc;
                            label.appendChild(chk);
                            label.appendChild(span);
                            contSub.appendChild(label);
                            chk.addEventListener("change", aplicarVisibilidadSubSectores);
                        }
                    });
                    aplicarVisibilidadSubSectores();
                    if (document.getElementById("capaSubSectores") && document.getElementById("capaSubSectores").checked) {
                        parentGroup.addTo(map);
                    }
                }
            })
            .catch(function () {});
    }

    function aplicarVisibilidadSubSectores() {
        if (!capaLayerSubSectores) return;
        capaLayerSubSectores.clearLayers();
        var chkSub = document.getElementById("capaSubSectores");
        if (!chkSub || !chkSub.checked) return;
        var chks = document.querySelectorAll(".capa-subsector:checked");
        chks.forEach(function (chk) {
            var gid = chk.dataset.subsectorGid;
            if (capaLayersPorSubSector[gid]) {
                capaLayerSubSectores.addLayer(capaLayersPorSubSector[gid].group);
            }
        });
    }

    function aplicarFiltroEstadoCamaras() {
        if (!capaLayerCamaras) return;
        capaLayerCamaras.clearLayers();
        
        var chkCamaras = document.getElementById("capaCamaras");
        var chkAlarmas = document.getElementById("capaAlarmas");
        var mostrarCamaras = chkCamaras ? chkCamaras.checked : true;
        var mostrarAlarmas = chkAlarmas ? chkAlarmas.checked : true;

        todosLosMarkersCamara.forEach(function (marker) {
            var item = marker._dispositivoItem;
            if (!item) return;
            var estado = item.estado;
            var tipoDisp = String(item.tipo_dispositivo || "").toUpperCase();
            var esAlarma = (tipoDisp === "ALARMA");

            if (esAlarma && !mostrarAlarmas) return;
            if (!esAlarma && !mostrarCamaras) return;

            var tipoCat = normalizarTipoCamara(marker._dispositivoTipo);
            if (esAlarma) {
                if (filtroEstadosAlarmas[estado] === true) {
                    capaLayerCamaras.addLayer(marker);
                }
            } else {
                if (filtroEstadosCamaras[estado] === true && filtroTiposCamaras[tipoCat] === true) {
                    capaLayerCamaras.addLayer(marker);
                }
            }
        });
        refrescarLeyendaDesdeMapa();
    }

    function renderMapa(porCamara) {
        var cont = document.getElementById("mapaDispositivos");
        if (!cont) return;
        if (mapaLeaflet) {
            mapaLeaflet.remove();
            mapaLeaflet = null;
        }
        markersPorId = {};

        if (!porCamara || !porCamara.length) {
            cont.innerHTML = '<p class="mapa-empty-msg">No hay dispositivos para mostrar en el mapa.</p>';
            return;
        }

        var conCoords = [];
        for (var j = 0; j < porCamara.length; j++) {
            var d = porCamara[j];
            var lat = d.latitud != null && !isNaN(Number(d.latitud));
            var lon = d.longitud != null && !isNaN(Number(d.longitud));
            if (lat && lon) conCoords.push(d);
        }

        if (conCoords.length === 0) {
            cont.innerHTML = '<p class="mapa-empty-msg">Ningún dispositivo tiene coordenadas para mostrar.</p>';
            return;
        }

        cont.innerHTML = "";
        var map = L.map("mapaDispositivos").setView([-12.0, -77.0], 11);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "&copy; OpenStreetMap" }).addTo(map);

        cargarCapasZonas(map);
        cargarCapasSectores(map);
        cargarCapasSubSectores(map);

        var layerCamaras = null;
        if (typeof L.markerClusterGroup === "function") {
            layerCamaras = L.markerClusterGroup({
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false,
                maxClusterRadius: 45
            });
        } else {
            // Fallback por si el plugin no cargó.
            layerCamaras = L.layerGroup();
        }
        capaLayerCamaras = layerCamaras;
        todosLosMarkersCamara = [];
        var bounds = [];

        for (var k = 0; k < conCoords.length; k++) {
            var item = conCoords[k];
            var lat = Number(item.latitud);
            var lon = Number(item.longitud);
            var icon = crearIcono(item);
            var marker = L.marker([lat, lon], { icon: icon });
            marker._dispositivoId = item.id;
            marker._dispositivoLabel = item.label;
            marker._dispositivoTipo = item.tipo || "";
            marker._dispositivoItem = item;
            todosLosMarkersCamara.push(marker);
            if (item.id != null) markersPorId[item.id] = marker;

            marker.on("click", function () {
                var id = this._dispositivoId;
                if (id) abrirDetalle(id);
            });
            bounds.push([lat, lon]);
        }

        aplicarFiltroEstadoCamaras();
        layerCamaras.addTo(map);
        if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
        mapaLeaflet = map;
    }

    function initPanelCapas() {
        var btn = document.getElementById("btnMapaCapas");
        var panel = document.getElementById("panelCapas");
        var chkZonas = document.getElementById("capaZonas");
        var chkSectores = document.getElementById("capaSectores");
        if (!btn || !panel) return;

        function togglePanel() {
            var abierto = panel.classList.toggle("mapa-panel-capas--abierto");
            panel.setAttribute("aria-hidden", abierto ? "false" : "true");
        }

        btn.addEventListener("click", function (e) {
            e.stopPropagation();
            togglePanel();
        });

        document.addEventListener("click", function (e) {
            if (panel.classList.contains("mapa-panel-capas--abierto") && !panel.contains(e.target) && e.target !== btn) {
                panel.classList.remove("mapa-panel-capas--abierto");
                panel.setAttribute("aria-hidden", "true");
            }
        });

        function desactivarSubopcionesZonas() {
            document.querySelectorAll(".capa-zona").forEach(function (chk) {
                chk.checked = false;
            });
        }

        function desactivarSubopcionesSectores() {
            document.querySelectorAll(".capa-sector").forEach(function (chk) {
                chk.checked = false;
            });
        }

        function activarSubopcionesZonas() {
            document.querySelectorAll(".capa-zona").forEach(function (chk) {
                chk.checked = true;
            });
        }

        function activarSubopcionesSectores() {
            document.querySelectorAll(".capa-sector").forEach(function (chk) {
                chk.checked = true;
            });
        }

        function desactivarSubopcionesSubSectores() {
            document.querySelectorAll(".capa-subsector").forEach(function (chk) {
                chk.checked = false;
            });
        }

        function activarSubopcionesSubSectores() {
            document.querySelectorAll(".capa-subsector").forEach(function (chk) {
                chk.checked = true;
            });
        }

        function aplicarCapaZonas() {
            if (!mapaLeaflet || !capaLayerZonas) return;
            if (chkZonas && chkZonas.checked) {
                activarSubopcionesZonas();
                aplicarVisibilidadZonas();
                capaLayerZonas.addTo(mapaLeaflet);
            } else {
                desactivarSubopcionesZonas();
                mapaLeaflet.removeLayer(capaLayerZonas);
            }
        }

        function aplicarCapaSectores() {
            if (!mapaLeaflet || !capaLayerSectores) return;
            if (chkSectores && chkSectores.checked) {
                activarSubopcionesSectores();
                aplicarVisibilidadSectores();
                capaLayerSectores.addTo(mapaLeaflet);
            } else {
                desactivarSubopcionesSectores();
                mapaLeaflet.removeLayer(capaLayerSectores);
            }
        }

        var chkSubSectores = document.getElementById("capaSubSectores");
        function aplicarCapaSubSectores() {
            if (!mapaLeaflet || !capaLayerSubSectores) return;
            if (chkSubSectores && chkSubSectores.checked) {
                activarSubopcionesSubSectores();
                aplicarVisibilidadSubSectores();
                capaLayerSubSectores.addTo(mapaLeaflet);
            } else {
                desactivarSubopcionesSubSectores();
                mapaLeaflet.removeLayer(capaLayerSubSectores);
            }
        }

        var chkCamaras = document.getElementById("capaCamaras");
        var chkAlarmas = document.getElementById("capaAlarmas");
        function aplicarCapaDispositivos() {
            if (!mapaLeaflet || !capaLayerCamaras) return;
            if (!mapaLeaflet.hasLayer(capaLayerCamaras)) {
                capaLayerCamaras.addTo(mapaLeaflet);
            }
            aplicarFiltroEstadoCamaras();
        }
        if (chkCamaras) chkCamaras.addEventListener("change", aplicarCapaDispositivos);
        if (chkAlarmas) chkAlarmas.addEventListener("change", aplicarCapaDispositivos);

        function recomputarFiltroEstadosCamaras() {
            filtroEstadosCamaras = {};
            var chks = document.querySelectorAll(".capa-estado-camara");
            var algunoMarcado = false;
            chks.forEach(function (chk) {
                var estado = chk.dataset.estado;
                if (chk.checked) {
                    filtroEstadosCamaras[estado] = true;
                    algunoMarcado = true;
                }
            });
            // Si el usuario desmarca todos, se interpreta como "mostrar todo"
            if (!algunoMarcado) {
                chks.forEach(function (chk) {
                    var estado = chk.dataset.estado;
                    filtroEstadosCamaras[estado] = true;
                });
            }
        }

        function recomputarFiltroTiposCamaras() {
            filtroTiposCamaras = {};
            var chks = document.querySelectorAll(".capa-tipo-camara");
            var algunoMarcado = false;
            chks.forEach(function (chk) {
                var tipo = chk.dataset.tipoCamara;
                if (chk.checked) {
                    filtroTiposCamaras[tipo] = true;
                    algunoMarcado = true;
                }
            });
            // Si el usuario desmarca todos, se interpreta como "mostrar todo"
            if (!algunoMarcado) {
                chks.forEach(function (chk) {
                    var tipo = chk.dataset.tipoCamara;
                    filtroTiposCamaras[tipo] = true;
                });
            }
        }

        document.querySelectorAll(".capa-estado-camara").forEach(function (chk) {
            chk.addEventListener("change", function () {
                recomputarFiltroEstadosCamaras();
                aplicarFiltroEstadoCamaras();
            });
        });

        document.querySelectorAll(".capa-tipo-camara").forEach(function (chk) {
            chk.addEventListener("change", function () {
                recomputarFiltroTiposCamaras();
                aplicarFiltroEstadoCamaras();
            });
        });

        function recomputarFiltroEstadosAlarmas() {
            filtroEstadosAlarmas = {};
            var chks = document.querySelectorAll(".capa-estado-alarma");
            var algunoMarcado = false;
            chks.forEach(function (chk) {
                var estado = chk.dataset.estado;
                if (chk.checked) {
                    filtroEstadosAlarmas[estado] = true;
                    algunoMarcado = true;
                }
            });
            if (!algunoMarcado) {
                chks.forEach(function (chk) {
                    var estado = chk.dataset.estado;
                    filtroEstadosAlarmas[estado] = true;
                });
            }
        }

        document.querySelectorAll(".capa-estado-alarma").forEach(function (chk) {
            chk.addEventListener("change", function () {
                recomputarFiltroEstadosAlarmas();
                aplicarFiltroEstadoCamaras();
            });
        });

        if (chkZonas) chkZonas.addEventListener("change", aplicarCapaZonas);
        if (chkSectores) chkSectores.addEventListener("change", aplicarCapaSectores);
        if (chkSubSectores) chkSubSectores.addEventListener("change", aplicarCapaSubSectores);
        document.querySelectorAll(".capa-zona").forEach(function (chk) {
            chk.addEventListener("change", aplicarVisibilidadZonas);
        });

        function toggleDesplegable(btnId, subId) {
            var btn = document.getElementById(btnId);
            var sub = document.getElementById(subId);
            if (!btn || !sub) return;
            var expanded = btn.getAttribute("aria-expanded") === "true";
            btn.setAttribute("aria-expanded", !expanded);
            sub.hidden = expanded;
        }

        var btnDesplegZonas = document.getElementById("btnDesplegableZonas");
        var btnDesplegCamaras = document.getElementById("btnDesplegableCamaras");
        var btnDesplegAlarmas = document.getElementById("btnDesplegableAlarmas");
        var btnDesplegSectores = document.getElementById("btnDesplegableSectores");
        if (btnDesplegCamaras) {
            btnDesplegCamaras.addEventListener("click", function (e) {
                if (e.target.closest("label") || (e.target.type && e.target.type === "checkbox")) return;
                e.preventDefault();
                toggleDesplegable("btnDesplegableCamaras", "panelCapasCamarasSub");
            });
        }
        if (btnDesplegAlarmas) {
            btnDesplegAlarmas.addEventListener("click", function (e) {
                if (e.target.closest("label") || (e.target.type && e.target.type === "checkbox")) return;
                e.preventDefault();
                toggleDesplegable("btnDesplegableAlarmas", "panelCapasAlarmasSub");
            });
        }
        if (btnDesplegZonas) {
            btnDesplegZonas.addEventListener("click", function (e) {
                if (e.target.closest("label") || (e.target.type && e.target.type === "checkbox")) return;
                e.preventDefault();
                toggleDesplegable("btnDesplegableZonas", "panelCapasZonasSub");
            });
        }
        if (btnDesplegSectores) {
            btnDesplegSectores.addEventListener("click", function (e) {
                if (e.target.closest("label") || (e.target.type && e.target.type === "checkbox")) return;
                e.preventDefault();
                toggleDesplegable("btnDesplegableSectores", "panelCapasSectoresSub");
            });
        }
        var btnDesplegSubSectores = document.getElementById("btnDesplegableSubSectores");
        if (btnDesplegSubSectores) {
            btnDesplegSubSectores.addEventListener("click", function (e) {
                if (e.target.closest("label") || (e.target.type && e.target.type === "checkbox")) return;
                e.preventDefault();
                toggleDesplegable("btnDesplegableSubSectores", "panelCapasSubSectoresSub");
            });
        }
    }

    function abrirDetalle(id) {
        dispositivoActualId = id;
        dispositivoActualMarker = markersPorId[id] || null;
        var panel = document.getElementById("panelDetalle");
        var titulo = document.getElementById("panelDetalleTitulo");
        var cuerpo = document.getElementById("panelDetalleCuerpo");
        var acciones = document.getElementById("panelDetalleAcciones");
        if (!panel || !cuerpo) return;

        panel.classList.add("activo");
        titulo.textContent = "Cargando...";
        cuerpo.innerHTML = '<p class="panel-detalle__cargando">Obteniendo detalle del dispositivo...</p>';
        acciones.style.display = "none";
        detenerStreamCamara();

        if (typeof dsApiDetalle !== "function") {
            cuerpo.innerHTML = '<p style="color:#c0392b;">No se puede cargar el detalle.</p>';
            return;
        }

        dsApiDetalle(id).then(function (item) {
            titulo.textContent = item.nuevo_codigo || item.codigo_kmz || "Dispositivo " + id;
            cuerpo.innerHTML = renderDetalleHTML(item);
            initVisorCamaraUI(item);
            acciones.style.display = "flex";
        }).catch(function (err) {
            titulo.textContent = "Error";
            cuerpo.innerHTML = '<p style="color:#c0392b;">' + escapeHtml(err.message || "No se pudo cargar el detalle.") + "</p>";
        });
    }

    function renderDetalleHTML(item) {
        if (!item) return "";
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
        var h = "";
        for (var i = 0; i < rows.length; i++) {
            var v = rows[i].val;
            if (v == null || v === "") v = "-";
            h += '<div class="panel-detalle__row"><span class="panel-detalle__label">' + escapeHtml(rows[i].label) + '</span><span class="panel-detalle__value">' + (typeof v === "string" && v.indexOf("<a ") === 0 ? v : escapeHtml(String(v))) + "</span></div>";
        }

        var esCamara = String(item.tipo_dispositivo || "").toUpperCase() === "CAMARA";
        var tieneIp = String(item.ip || "").trim() !== "";
        if (esCamara && tieneIp) {
            h += '<div class="panel-detalle__visor">'
                + '<button type="button" class="btn btn--primary btn--ver-camara" id="btnVerCamara" data-id="' + escapeHtml(item.id) + '">VER CAMARA</button>'
                + '<button type="button" class="btn btn--secondary btn--ver-logs" id="btnVerLogs" data-id="' + escapeHtml(item.id) + '" style="margin-left: 5px; background: #333; color: #fff;">VER LOGS</button>'
                + "</div>";
        }
        return h;
    }

    function detenerStreamCamara() {
        visorStreamActivoId = null;
        var overlay = document.getElementById("modalCamaraOverlay");
        var img = document.getElementById("modalCamaraImg");
        var video = document.getElementById("modalCamaraVideo");
        var msg = document.getElementById("modalCamaraMsg");
        if (visorHlsInstance && window.Hls) {
            try { visorHlsInstance.destroy(); } catch (e) { }
            visorHlsInstance = null;
        }
        if (video) {
            try { video.pause(); } catch (e) { }
            try { video.removeAttribute("src"); } catch (e) { }
            try { video.load(); } catch (e) { }
            video.style.display = "none";
        }
        if (img && img.parentNode) {
            // Forzar cancelación de la solicitud al proxy_stream.php:
            // establecer src a vacío ayuda a que el navegador cierre la conexión.
            try { img.src = ""; } catch (e) { }
            var nuevo = img.cloneNode(false);
            nuevo.removeAttribute("src");
            img.parentNode.replaceChild(nuevo, img);
        }
        if (msg) { msg.style.display = "none"; msg.textContent = ""; }
        if (overlay) {
            overlay.classList.remove("active");
            overlay.setAttribute("aria-hidden", "true");
        }
    }

    function asegurarVideoModalCamara() {
        var img = document.getElementById("modalCamaraImg");
        if (!img || !img.parentNode) return null;

        var video = document.getElementById("modalCamaraVideo");
        if (!video) {
            video = document.createElement("video");
            video.id = "modalCamaraVideo";
            video.className = "modal-camara__img";
            video.style.display = "none";
            video.setAttribute("playsinline", "");
            video.muted = true;
            video.autoplay = true;
            img.parentNode.appendChild(video);
        }
        return video;
    }

    function iniciarStreamCamara(id, titulo, esHikvision) {
        visorStreamActivoId = id;
        var overlay = document.getElementById("modalCamaraOverlay");
        var img = document.getElementById("modalCamaraImg");
        var video = asegurarVideoModalCamara();
        var msg = document.getElementById("modalCamaraMsg");
        var tit = document.getElementById("modalCamaraTitulo");
        if (!overlay || !img) return;

        if (tit) tit.textContent = titulo || "Cámara";
        if (msg) { msg.style.display = "none"; msg.textContent = ""; }

        overlay.classList.add("active");
        overlay.setAttribute("aria-hidden", "false");

        // Hikvision/Dahua: HLS real con hls.js. Se hace un pre-check para mostrar
        // el error real del servidor si la playlist no está disponible aún.
        if (esHikvision && video) {
            var hlsUrl = ((window.DS_ROUTES && DS_ROUTES.stream) ? DS_ROUTES.stream : "stream") + "?id=" + encodeURIComponent(id) + "&format=hls_m3u8&_=" + Date.now();
            video.style.display = "none";
            img.style.display = "none";

            if (msg) {
                msg.style.display = "block";
                msg.textContent = "Conectando con la cámara...";
                msg.style.color = "#90caf9";
            }

        // Lanzar HLS directamente con hls.js (sin pre-fetch que cause demora)
        function dsCargarHls(hlsUrl, intento) {
            var HlsLib = window.Hls;
            if (!HlsLib || !HlsLib.isSupported || !HlsLib.isSupported()) {
                // Navegador no soporta hls.js — fallback nativo
                video.style.display = "block";
                if (msg) msg.style.display = "none";
                try { video.src = hlsUrl; video.play(); } catch (e) {}
                return;
            }

            try { if (visorHlsInstance) visorHlsInstance.destroy(); } catch (e) {}
            visorHlsInstance = new HlsLib({
                enableWorker: true,
                lowLatencyMode: false,
                startFragPrefetch: true,
                backBufferLength: 5,
                maxBufferLength: 12,
                maxMaxBufferLength: 20,
                liveSyncDurationCount: 3,
                liveMaxLatencyDurationCount: 8,
                maxLiveSyncPlaybackRate: 1.1,
                fragLoadingMaxRetry: 6,
                fragLoadingRetryDelay: 1000,
                manifestLoadingMaxRetry: 4,
                manifestLoadingRetryDelay: 2000,
                levelLoadingMaxRetry: 4,
                levelLoadingRetryDelay: 1000,
                xhrSetup: function(xhr) { xhr.withCredentials = true; }
            });

            visorHlsInstance.on(HlsLib.Events.ERROR, function (event, data) {
                if (!data) return;
                if (data.fatal) {
                    if (data.type === HlsLib.ErrorTypes.NETWORK_ERROR) {
                        // Red: reintentar carga de manifiesto
                        if (intento < 3) {
                            if (msg) {
                                msg.style.display = "block";
                                msg.style.color = "#ffcc80";
                                msg.textContent = "Conectando con la cámara... (intento " + (intento + 1) + "/3)";
                            }
                            setTimeout(function () {
                                var hlsUrl2 = ((window.DS_ROUTES && DS_ROUTES.stream) ? DS_ROUTES.stream : "stream") + "?id=" + encodeURIComponent(id) + "&format=hls_m3u8&_=" + Date.now();
                                dsCargarHls(hlsUrl2, intento + 1);
                            }, 4000);
                        } else {
                            if (msg) {
                                msg.style.display = "block";
                                msg.style.color = "#ef9a9a";
                                msg.textContent = "No se pudo conectar con la cámara. Verifique que esté encendida y accesible.";
                            }
                        }
                    } else if (data.type === HlsLib.ErrorTypes.MEDIA_ERROR) {
                        // Error de media: recuperar automáticamente
                        try { visorHlsInstance.recoverMediaError(); } catch(e) {}
                    }
                }
            });

            visorHlsInstance.on(HlsLib.Events.MANIFEST_PARSED, function () {
                video.style.display = "block";
                if (msg) { msg.style.display = "none"; msg.textContent = ""; }
                try { video.play(); } catch (e) {}
            });

            visorHlsInstance.loadSource(hlsUrl);
            visorHlsInstance.attachMedia(video);
        }

        dsCargarHls(hlsUrl, 1);
        } else {
            if (video) video.style.display = "none";
            img.style.display = "block";
            var url = ((window.DS_ROUTES && DS_ROUTES.stream) ? DS_ROUTES.stream : "stream") + "?id=" + encodeURIComponent(id) + "&_=" + Date.now();
            img.src = url;
        }

        img.onerror = function () {
            if (msg) {
                msg.style.display = "block";
                msg.textContent = "No se pudo cargar el video en vivo (revise IP/credenciales o conectividad).";
            }
        };
    }

    function initVisorCamaraUI(item) {
        var btnVer = document.getElementById("btnVerCamara");
        if (!btnVer) return;
        var id = (item && item.id != null) ? String(item.id) : "";
        var titulo = (item && (item.nuevo_codigo || item.codigo_kmz)) ? String(item.nuevo_codigo || item.codigo_kmz) : "Cámara";
        var marca = item && item.marca ? String(item.marca).toUpperCase() : "";
        var modelo = item && item.modelo ? String(item.modelo).toUpperCase() : "";
        var tipoCam = item && item.tipo ? String(item.tipo).toUpperCase() : "";
        var esHikvision = (marca.indexOf("HIK") !== -1) || (modelo.indexOf("HIK") !== -1);
        var esHikvisionFija = esHikvision && (tipoCam.indexOf("FIJA") !== -1);
        var esDahuaFija = (marca.indexOf("DAHUA") !== -1) && (tipoCam.indexOf("FIJA") !== -1);
        var esDahuaPtz = (marca.indexOf("DAHUA") !== -1) && (tipoCam.indexOf("PTZ") !== -1);
        btnVer.addEventListener("click", function () {
            if (!id) return;
            if (visorStreamActivoId === id) {
                detenerStreamCamara();
                return;
            }
            // Si hay otro stream activo, cortarlo antes de iniciar el nuevo
            // para evitar múltiples conexiones MJPEG colgadas.
            if (visorStreamActivoId && visorStreamActivoId !== id) detenerStreamCamara();
            iniciarStreamCamara(id, titulo, esHikvisionFija || esDahuaFija || esDahuaPtz || esHikvision);
        });

        var btnLogs = document.getElementById("btnVerLogs");
        if (btnLogs) {
            btnLogs.addEventListener("click", function () {
                if (!id) return;
                window.open(((window.DS_ROUTES && DS_ROUTES.tail_log) ? DS_ROUTES.tail_log : 'tail_log') + '?id=' + id, 'Logs_Camara_' + id, 'width=900,height=700,scrollbars=yes');
            });
        }
    }

    function cerrarPanel() {
        var panel = document.getElementById("panelDetalle");
        if (panel) panel.classList.remove("activo");
        dispositivoActualId = null;
        dispositivoActualMarker = null;
        detenerStreamCamara();
    }

    function abrirModalEditar() {
        if (!dispositivoActualId || typeof dsApiDetalle !== "function" || typeof dsAbrirModal !== "function") return;
        var id = dispositivoActualId;
        dsApiDetalle(id).then(function (item) {
            return (typeof dsApiSectores === "function" ? dsApiSectores() : Promise.resolve({ sectores: [] }))
                .then(function (dataSectores) {
                    var sectores = dataSectores.sectores || dataSectores || [];
                    if (typeof dsLlenarSelectSectores === "function") dsLlenarSelectSectores(sectores);
                    var sectorId = (item.sector_id != null && item.sector_id !== "") ? String(item.sector_id).trim() : "";
                    if (!sectorId)
                        return item;
                    return (typeof dsApiSubSectores === "function" ? dsApiSubSectores(sectorId) : Promise.resolve([]))
                        .then(function (subs) {
                            if (typeof dsLlenarSelectSubSectores === "function") dsLlenarSelectSubSectores(Array.isArray(subs) ? subs : []);
                            return item;
                        });
                })
                .then(function (item) {
                    dsAbrirModal("Editar Dispositivo", item);
                });
        }).catch(function (err) {
            alert(err.message || "No se pudo cargar el dispositivo para editar.");
        });
    }

    function guardarModalEditar(e) {
        e.preventDefault();
        if (typeof dsObtenerDatosForm !== "function" || typeof dsApiActualizar !== "function") return;
        var datos = dsObtenerDatosForm();
        if (!datos.id) {
            alert("ID de dispositivo no válido.");
            return;
        }
        var btnGuardar = document.getElementById("btnGuardarModal");
        if (btnGuardar) {
            btnGuardar.disabled = true;
            btnGuardar.textContent = "Guardando...";
        }
        dsApiActualizar(datos).then(function () {
            if (typeof dsCerrarModal === "function") dsCerrarModal();
            if (dispositivoActualId) abrirDetalle(dispositivoActualId);
            if (dispositivoActualMarker && datos.estado) actualizarIconoMarker(dispositivoActualMarker, datos.estado);
        }).catch(function (err) {
            alert(err.message || "Error al guardar.");
        }).finally(function () {
            if (btnGuardar) {
                btnGuardar.disabled = false;
                btnGuardar.textContent = "Guardar";
            }
        });
    }

    function onSectorChangeModal() {
        var sel = document.getElementById("formSector");
        if (!sel || typeof dsApiSubSectores !== "function" || typeof dsLlenarSelectSubSectores !== "function") return;
        var sectorId = sel.value.trim();
        dsLlenarSelectSubSectores([]);
        if (!sectorId) return;
        dsApiSubSectores(sectorId).then(function (data) {
            dsLlenarSelectSubSectores(Array.isArray(data) ? data : []);
        }).catch(function () {});
    }

    function obtenerUbicacionModal() {
        var btn = document.getElementById("btnUbicacion");
        var latEl = document.getElementById("formLatitud");
        var lngEl = document.getElementById("formLongitud");
        if (!navigator.geolocation || !latEl || !lngEl) {
            alert("La geolocalización no está disponible.");
            return;
        }
        if (btn) { btn.disabled = true; btn.textContent = "Obteniendo..."; }
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                latEl.value = pos.coords.latitude;
                lngEl.value = pos.coords.longitude;
                if (typeof dsActualizarUrlMapa === "function") dsActualizarUrlMapa();
                if (btn) { btn.disabled = false; btn.textContent = "Obtener Ubicacion"; }
            },
            function (err) {
                var msgs = { 1: "Permiso denegado.", 2: "Ubicación no disponible.", 3: "Tiempo agotado." };
                alert(msgs[err.code] || "No se pudo obtener la ubicación.");
                if (btn) { btn.disabled = false; btn.textContent = "Obtener Ubicacion"; }
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    function ejecutarPing() {
        if (!dispositivoActualId) return;
        var btn = document.getElementById("btnPingDispositivo");
        if (!btn) return;
        btn.disabled = true;
        btn.textContent = "Comprobando...";

        fetch((window.DS_ROUTES && DS_ROUTES.ping) ? DS_ROUTES.ping : "ping", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" },
            body: JSON.stringify({ id: dispositivoActualId })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.estado) {
                    if (dispositivoActualMarker) actualizarIconoMarker(dispositivoActualMarker, data.estado);
                    aplicarFiltroEstadoCamaras();
                    var rows = document.querySelectorAll("#panelDetalleCuerpo .panel-detalle__row");
                    for (var i = 0; i < rows.length; i++) {
                        var lbl = rows[i].querySelector(".panel-detalle__label");
                        if (lbl && lbl.textContent.trim() === "Estado") {
                            var val = rows[i].querySelector(".panel-detalle__value");
                            if (val) val.textContent = data.estado;
                            break;
                        }
                    }
                    alert(data.mensaje || (data.ping_ok ? "Dispositivo operativo." : "Dispositivo no responde."));
                } else {
                    alert(data.error || "Error al ejecutar PING.");
                }
            })
            .catch(function () {
                alert("Error de conexión al ejecutar PING.");
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = "PING";
            });
    }

    function ejecutarPingGeneral() {
        var btn = document.getElementById("btnPingGeneral");
        if (!btn) return;
        var ids = todosLosMarkersCamara.map(function (m) { return m._dispositivoId; }).filter(function (id) { return id != null && id !== ""; });
        if (!ids.length) {
            alert("No hay cámaras para hacer PING.");
            return;
        }

        btn.disabled = true;
        var total = ids.length;
        var ok = 0, fail = 0, done = 0;
        var textoOriginal = btn.textContent;

        function setTexto() {
            btn.textContent = "PING (" + done + "/" + total + ") OK:" + ok + " FAIL:" + fail;
        }
        setTexto();

        var concurrencia = 5;
        var cola = ids.slice();
        function worker() {
            if (!cola.length) return Promise.resolve();
            var id = cola.shift();
            return fetch((window.DS_ROUTES && DS_ROUTES.ping) ? DS_ROUTES.ping : "ping", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" },
                body: JSON.stringify({ id: id })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.success && data.estado) {
                        ok += data.ping_ok ? 1 : 0;
                        fail += data.ping_ok ? 0 : 1;
                        var marker = markersPorId[id];
                        if (marker) actualizarIconoMarker(marker, data.estado);
                    } else {
                        fail++;
                    }
                })
                .catch(function () {
                    fail++;
                })
                .finally(function () {
                    done++;
                    aplicarFiltroEstadoCamaras();
                    setTexto();
                })
                .then(worker);
        }

        var workers = [];
        for (var i = 0; i < concurrencia; i++) workers.push(worker());
        Promise.all(workers).finally(function () {
            btn.disabled = false;
            btn.textContent = textoOriginal;
            alert("PING general finalizado. OK: " + ok + " / FAIL: " + fail);
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        var params = paramsFromUrl();
        fetchEstadisticas()
            .then(function (data) {
                renderMapa(data.por_camara || []);
                refrescarLeyendaDesdeMapa();
            })
            .catch(function (err) {
                var cont = document.getElementById("mapaDispositivos");
                if (cont) cont.innerHTML = '<p class="mapa-empty-msg" style="color:#c0392b;">' + escapeHtml(err.message) + "</p>";
            });

        initPanelCapas();

        document.getElementById("panelDetalleCerrar").addEventListener("click", cerrarPanel);
        document.getElementById("btnEditarDispositivo").addEventListener("click", abrirModalEditar);
        document.getElementById("btnPingDispositivo").addEventListener("click", ejecutarPing);
        var btnPingGeneral = document.getElementById("btnPingGeneral");
        if (btnPingGeneral) btnPingGeneral.addEventListener("click", ejecutarPingGeneral);

        // Modal cámara (pantalla completa)
        var overlayCam = document.getElementById("modalCamaraOverlay");
        var btnCerrarCam = document.getElementById("btnCerrarModalCamara");
        if (btnCerrarCam) btnCerrarCam.addEventListener("click", function () { detenerStreamCamara(); });
        if (overlayCam) {
            overlayCam.addEventListener("click", function (e) {
                // cerrar al hacer click fuera del cuadro
                if (e.target === overlayCam) detenerStreamCamara();
            });
        }
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                var ov = document.getElementById("modalCamaraOverlay");
                if (ov && ov.classList.contains("active")) detenerStreamCamara();
            }
        });

        var formDispo = document.getElementById("formDispositivo");
        if (formDispo) formDispo.addEventListener("submit", guardarModalEditar);
        var btnCerrarModal = document.getElementById("btnCerrarModal");
        var btnCancelarModal = document.getElementById("btnCancelarModal");
        if (btnCerrarModal) btnCerrarModal.addEventListener("click", function () { if (typeof dsCerrarModal === "function") dsCerrarModal(); });
        if (btnCancelarModal) btnCancelarModal.addEventListener("click", function () { if (typeof dsCerrarModal === "function") dsCerrarModal(); });
        var formSector = document.getElementById("formSector");
        if (formSector) formSector.addEventListener("change", onSectorChangeModal);
        var btnUbicacion = document.getElementById("btnUbicacion");
        if (btnUbicacion) btnUbicacion.addEventListener("click", obtenerUbicacionModal);
        var formLat = document.getElementById("formLatitud");
        var formLng = document.getElementById("formLongitud");
        if (formLat && typeof dsActualizarUrlMapa === "function") formLat.addEventListener("input", dsActualizarUrlMapa);
        if (formLng && typeof dsActualizarUrlMapa === "function") formLng.addEventListener("input", dsActualizarUrlMapa);

        // Modal Reporte Visores
        var btnReporte = document.getElementById("btnReporteVisores");
        var modalReporte = document.getElementById("modalReporteVisores");
        var btnCerrarReporte = document.getElementById("btnCerrarModalReporte");
        var btnAceptarReporte = document.getElementById("btnAceptarModalReporte");

        if (btnReporte && modalReporte) {
            function cerrarModalReporte() {
                modalReporte.classList.remove("active");
                modalReporte.setAttribute("aria-hidden", "true");
            }
            btnReporte.addEventListener("click", function() {
                modalReporte.classList.add("active");
                modalReporte.setAttribute("aria-hidden", "false");
                
                var tbCon = document.getElementById("tbodyReporteConBoton");
                var tbSin = document.getElementById("tbodyReporteSinBoton");
                tbCon.innerHTML = "<tr><td colspan='4'>Cargando...</td></tr>";
                tbSin.innerHTML = "<tr><td colspan='4'>Cargando...</td></tr>";

                dsApiFetch((window.DS_ROUTES && DS_ROUTES.reporte_visores) ? DS_ROUTES.reporte_visores : "reporte_visores").then(function(res) {
                    var htmlCon = "";
                    var htmlSin = "";
                    if (res && res.data) {
                        res.data.conBoton.forEach(function(r) {
                            var btnHtml = "<button class='btn btn--primary btn-play-reporte' data-id='"+r.id+"' data-titulo='"+escapeHtml(r.codigo || "")+"' data-marca='"+escapeHtml(r.marca || "")+"' data-modelo='"+escapeHtml(r.modelo || "")+"' data-tipo='"+escapeHtml(r.tipo || "")+"' style='font-size: 11px; padding: 4px 8px;'>VER CÁMARA</button>";
                            var btnLogs = "<button class='btn btn--secondary btn-logs-reporte' data-id='"+r.id+"' style='font-size: 11px; padding: 4px 8px; margin-left: 5px; background: #333; color: #fff;'>VER LOGS</button>";
                            htmlCon += "<tr><td>"+r.id+"</td><td>"+escapeHtml(r.codigo || "")+"</td><td>"+escapeHtml(r.direccion || "")+"</td><td>"+escapeHtml(r.ip || "")+"</td><td>"+btnHtml+btnLogs+"</td></tr>";
                        });
                        res.data.sinBoton.forEach(function(r) {
                            htmlSin += "<tr><td>"+r.id+"</td><td>"+escapeHtml(r.codigo || "")+"</td><td>"+(r.ip ? escapeHtml(r.ip) : '<i>(vacío)</i>')+"</td><td style='color:#f44336;'><strong>"+escapeHtml(r.razon || "")+"</strong></td></tr>";
                        });
                    }
                    if(!htmlCon) htmlCon = "<tr><td colspan='5'>No hay datos.</td></tr>";
                    if(!htmlSin) htmlSin = "<tr><td colspan='4'>No hay datos.</td></tr>";
                    tbCon.innerHTML = htmlCon;
                    tbSin.innerHTML = htmlSin;

                    var btns = tbCon.querySelectorAll('.btn-play-reporte');
                    for (var i = 0; i < btns.length; i++) {
                        btns[i].addEventListener('click', function(e) {
                            var b = e.target;
                            var id = b.getAttribute('data-id');
                            var titulo = b.getAttribute('data-titulo');
                            var marca = (b.getAttribute('data-marca') || '').toUpperCase();
                            var modelo = (b.getAttribute('data-modelo') || '').toUpperCase();
                            var esHikvision = (marca.indexOf("HIK") !== -1) || (modelo.indexOf("HIK") !== -1);
                            
                            if (typeof visorStreamActivoId !== 'undefined' && visorStreamActivoId === id) {
                                detenerStreamCamara();
                            } else {
                                // Cierra el modal para que el visor no quede por debajo
                                cerrarModalReporte();
                                iniciarStreamCamara(id, titulo, esHikvision);
                            }
                        });
                    }

                    var btnLogs = tbCon.querySelectorAll('.btn-logs-reporte');
                    for (var j = 0; j < btnLogs.length; j++) {
                        btnLogs[j].addEventListener('click', function(e) {
                            var id = e.target.getAttribute('data-id');
                            window.open(((window.DS_ROUTES && DS_ROUTES.tail_log) ? DS_ROUTES.tail_log : 'tail_log') + '?id=' + id, 'Logs_Camara_' + id, 'width=900,height=700,scrollbars=yes');
                        });
                    }
                }).catch(function(err) {
                    tbCon.innerHTML = "<tr><td colspan='4' style='color:red;'>Error al cargar.</td></tr>";
                    tbSin.innerHTML = "<tr><td colspan='4' style='color:red;'>Error al cargar.</td></tr>";
                });
            });

            if (btnCerrarReporte) btnCerrarReporte.addEventListener("click", cerrarModalReporte);
            if (btnAceptarReporte) btnAceptarReporte.addEventListener("click", cerrarModalReporte);
            modalReporte.addEventListener("click", function (e) {
                if (e.target === modalReporte) cerrarModalReporte();
            });
        }
    });
})();
