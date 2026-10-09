<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mapa de Dispositivos - Dispositivos de Seguridad</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_ti/dispositivo_seg/css/app.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('mod_ti/dispositivo_seg/css/mapa.css') }}?v=1">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
</head>
<body class="pagina-mapa">
    <header class="mapa-header">
        <div class="mapa-header__titulo">
            <p class="mapa-header__eyebrow">Tecnologías de la Información</p>
            <h1>Mapa de Dispositivos de Seguridad</h1>
        </div>
        <div class="mapa-header__actions">
            <a href="{{ $routes['index'] }}" class="btn btn--ghost">Volver al listado</a>
            <button type="button" class="btn btn--secondary" id="btnReporteVisores" style="background: #1e1e1e; border: 1px solid #444; color: #fff;">ESTADO VISORES</button>
            <button type="button" class="btn btn--ping" id="btnPingGeneral">PING GENERAL</button>
        </div>
    </header>

    <div class="mapa-leyenda" id="mapaLeyenda">
        <span class="mapa-leyenda__titulo">Estado:</span>
        <span class="mapa-leyenda__total">Total: <strong id="leyendaTotal">0</strong></span>
        <span class="mapa-leyenda__item mapa-leyenda__item--operativo">Operativo (<strong id="leyendaOperativo">0</strong>)</span>
        <span class="mapa-leyenda__item mapa-leyenda__item--inoperativo">Inoperativo (<strong id="leyendaInoperativo">0</strong>)</span>
        <span class="mapa-leyenda__item mapa-leyenda__item--intermitente">Intermitente (<strong id="leyendaIntermitente">0</strong>)</span>
        <span class="mapa-leyenda__item mapa-leyenda__item--mantenimiento">Mantenimiento (<strong id="leyendaMantenimiento">0</strong>)</span>
    </div>

    <div class="mapa-contenedor">
        <div id="panelCapas" class="mapa-panel-capas mapa-panel-capas--dropdown" aria-hidden="true">
            <h3 class="mapa-panel-capas__titulo">Capas del mapa</h3>
            <div class="mapa-panel-capas__opciones">
                <div class="mapa-panel-capas__item-desplegable" data-seccion="camaras">
                    <button type="button" class="mapa-panel-capas__encabezado" id="btnDesplegableCamaras" aria-expanded="true" aria-controls="panelCapasCamarasSub">
                        <label class="mapa-panel-capas__label mapa-panel-capas__label--encabezado">
                            <input type="checkbox" id="capaCamaras" class="mapa-panel-capas__check" checked>
                            <span>Cámaras</span>
                        </label>
                        <span class="mapa-panel-capas__flecha" aria-hidden="true"></span>
                    </button>

                    <div id="panelCapasCamarasSub" class="mapa-panel-capas__sub mapa-panel-capas__sub--desplegable">
                        <div id="filtroCamarasEstadoWrap" class="mapa-panel-capas__filtro-estado">
                            <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                                <input type="checkbox" class="mapa-panel-capas__check capa-estado-camara" data-estado="OPERATIVO" checked>
                                <span>Operativas</span>
                            </label>
                            <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                                <input type="checkbox" class="mapa-panel-capas__check capa-estado-camara" data-estado="INOPERATIVO" checked>
                                <span>Inoperativas</span>
                            </label>
                            <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                                <input type="checkbox" class="mapa-panel-capas__check capa-estado-camara" data-estado="INTERMITENTE" checked>
                                <span>Intermitentes</span>
                            </label>
                        </div>

                        <div id="filtroCamarasTipoWrap" class="mapa-panel-capas__filtro-tipo">
                            <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                                <input type="checkbox" class="mapa-panel-capas__check capa-tipo-camara" data-tipo-camara="PTZ" checked>
                                <span>PTZ</span>
                            </label>
                            <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                                <input type="checkbox" class="mapa-panel-capas__check capa-tipo-camara" data-tipo-camara="FIJA" checked>
                                <span>Fijas</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mapa-panel-capas__item-desplegable" data-seccion="alarmas">
                    <button type="button" class="mapa-panel-capas__encabezado" id="btnDesplegableAlarmas" aria-expanded="true" aria-controls="panelCapasAlarmasSub">
                        <label class="mapa-panel-capas__label mapa-panel-capas__label--encabezado">
                            <input type="checkbox" id="capaAlarmas" class="mapa-panel-capas__check" checked>
                            <span>Alarmas</span>
                        </label>
                        <span class="mapa-panel-capas__flecha" aria-hidden="true"></span>
                    </button>

                    <div id="panelCapasAlarmasSub" class="mapa-panel-capas__sub mapa-panel-capas__sub--desplegable">
                        <div id="filtroAlarmasEstadoWrap" class="mapa-panel-capas__filtro-estado">
                            <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                                <input type="checkbox" class="mapa-panel-capas__check capa-estado-alarma" data-estado="OPERATIVO" checked>
                                <span>Operativas</span>
                            </label>
                            <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                                <input type="checkbox" class="mapa-panel-capas__check capa-estado-alarma" data-estado="INOPERATIVO" checked>
                                <span>Inoperativas</span>
                            </label>
                            <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                                <input type="checkbox" class="mapa-panel-capas__check capa-estado-alarma" data-estado="INTERMITENTE" checked>
                                <span>Intermitentes</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mapa-panel-capas__item-desplegable" data-seccion="zonas">
                    <button type="button" class="mapa-panel-capas__encabezado" id="btnDesplegableZonas" aria-expanded="false" aria-controls="panelCapasZonasSub">
                        <label class="mapa-panel-capas__label mapa-panel-capas__label--encabezado">
                            <input type="checkbox" id="capaZonas" class="mapa-panel-capas__check">
                            <span>Zonas</span>
                        </label>
                        <span class="mapa-panel-capas__flecha" aria-hidden="true"></span>
                    </button>
                    <div id="panelCapasZonasSub" class="mapa-panel-capas__sub mapa-panel-capas__sub--desplegable" hidden>
                        <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                            <input type="checkbox" id="capaZonaNorte" class="mapa-panel-capas__check capa-zona" data-zona="NORTE">
                            <span>Zona Norte</span>
                        </label>
                        <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                            <input type="checkbox" id="capaZonaCentro" class="mapa-panel-capas__check capa-zona" data-zona="CENTRO">
                            <span>Zona Centro</span>
                        </label>
                        <label class="mapa-panel-capas__label mapa-panel-capas__label--sub">
                            <input type="checkbox" id="capaZonaSur" class="mapa-panel-capas__check capa-zona" data-zona="SUR">
                            <span>Zona Sur</span>
                        </label>
                    </div>
                </div>
                <div class="mapa-panel-capas__item-desplegable" data-seccion="sectores">
                    <button type="button" class="mapa-panel-capas__encabezado" id="btnDesplegableSectores" aria-expanded="false" aria-controls="panelCapasSectoresSub">
                        <label class="mapa-panel-capas__label mapa-panel-capas__label--encabezado">
                            <input type="checkbox" id="capaSectores" class="mapa-panel-capas__check">
                            <span>Sectores</span>
                        </label>
                        <span class="mapa-panel-capas__flecha" aria-hidden="true"></span>
                    </button>
                    <div id="panelCapasSectoresSub" class="mapa-panel-capas__sub mapa-panel-capas__sub--desplegable" hidden>
                        <!-- Sectores se llenan por JS al cargar el GeoJSON -->
                    </div>
                </div>
                <div class="mapa-panel-capas__item-desplegable" data-seccion="subsectores">
                    <button type="button" class="mapa-panel-capas__encabezado" id="btnDesplegableSubSectores" aria-expanded="false" aria-controls="panelCapasSubSectoresSub">
                        <label class="mapa-panel-capas__label mapa-panel-capas__label--encabezado">
                            <input type="checkbox" id="capaSubSectores" class="mapa-panel-capas__check">
                            <span>SubSectores</span>
                        </label>
                        <span class="mapa-panel-capas__flecha" aria-hidden="true"></span>
                    </button>
                    <div id="panelCapasSubSectoresSub" class="mapa-panel-capas__sub mapa-panel-capas__sub--desplegable" hidden>
                        <!-- SubSectores se llenan por JS al cargar el GeoJSON -->
                    </div>
                </div>
            </div>
        </div>
        <button type="button" class="mapa-hamburger" id="btnMapaCapas" aria-label="Abrir capas del mapa" title="Capas del mapa">
            <span class="mapa-hamburger__bar"></span>
            <span class="mapa-hamburger__bar"></span>
            <span class="mapa-hamburger__bar"></span>
        </button>
        <div id="mapaDispositivos" class="mapa-full"></div>
    </div>

    <aside id="panelDetalle" class="panel-detalle">
        <div class="panel-detalle__header">
            <h2 id="panelDetalleTitulo" class="panel-detalle__titulo">Detalle del dispositivo</h2>
            <button type="button" class="panel-detalle__cerrar" id="panelDetalleCerrar" aria-label="Cerrar">&times;</button>
        </div>
        <div id="panelDetalleCuerpo" class="panel-detalle__cuerpo">
            <p class="panel-detalle__cargando">Haz clic en una cámara del mapa para ver su detalle.</p>
        </div>
        <div id="panelDetalleAcciones" class="panel-detalle__acciones" style="display:none;">
            <button type="button" class="btn btn--primary" id="btnEditarDispositivo">Editar</button>
            <button type="button" class="btn btn--ping" id="btnPingDispositivo">PING</button>
        </div>
    </aside>

    <!-- Modal visor cámara (pantalla completa) -->
    <div class="modal-camara-overlay" id="modalCamaraOverlay" aria-hidden="true">
        <div class="modal-camara" role="dialog" aria-modal="true" aria-labelledby="modalCamaraTitulo">
            <div class="modal-camara__header">
                <h2 id="modalCamaraTitulo" class="modal-camara__title">Cámara</h2>
                <button type="button" class="modal-camara__close" id="btnCerrarModalCamara" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-camara__body">
                <div class="modal-camara__frame">
                    <img id="modalCamaraImg" class="modal-camara__img" alt="Video en vivo" />
                    <video id="modalCamaraVideo" class="modal-camara__img" style="display:none;" playsinline muted autoplay></video>
                </div>
                <div id="modalCamaraMsg" class="modal-camara__msg" style="display:none;"></div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Dispositivo (misma ventana emergente que en index) -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <div class="modal__header">
                <h2 id="modalTitulo">Editar Dispositivo</h2>
                <button type="button" class="modal__close" id="btnCerrarModal">&times;</button>
            </div>
            <form id="formDispositivo" method="post" autocomplete="off">
                <input type="hidden" id="formId" name="id">
                <div class="modal__body">
                    <h3 class="section-title">Informacion General</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label for="formTipo">Tipo Dispositivo</label>
                            <select id="formTipo" name="tipo_dispositivo">
                                <option value="">-- Seleccionar --</option>
                                <option value="CAMARA">CAMARA</option>
                                <option value="ALARMA">ALARMA</option>
                                <option value="MUFA">MUFA</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="formZona">Zona</label>
                            <select id="formZona" name="zona">
                                <option value="">-- Seleccionar --</option>
                                <option value="NORTE">NORTE</option>
                                <option value="CENTRO">CENTRO</option>
                                <option value="SUR">SUR</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="formSector">Sector</label>
                            <select id="formSector" name="sector_id">
                                <option value="">-- Seleccionar --</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="formSubSector">Sub Sector</label>
                            <select id="formSubSector" name="sub_sector_id">
                                <option value="">-- Seleccionar --</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="formCodigoKmz">Codigo KMZ</label>
                            <input id="formCodigoKmz" name="codigo_kmz" type="text" placeholder="Ej: CF-01">
                        </div>
                        <div class="field">
                            <label for="formNuevoCodigo">Nuevo Codigo (opcional)</label>
                            <input id="formNuevoCodigo" name="nuevo_codigo" type="text" placeholder="Ej: C-140">
                        </div>
                        <div class="field field--full">
                            <label for="formDireccion">Direccion</label>
                            <input id="formDireccion" name="direccion" type="text" placeholder="Direccion del dispositivo">
                        </div>
                    </div>
                    <div class="section-header">
                        <h3 class="section-title">Ubicacion</h3>
                        <button type="button" class="btn btn--primary btn--geo" id="btnUbicacion">Obtener Ubicacion</button>
                    </div>
                    <div class="form-grid">
                        <div class="field">
                            <label for="formLatitud">Latitud</label>
                            <input id="formLatitud" name="latitud" type="text" placeholder="Ej: -11.829839">
                        </div>
                        <div class="field">
                            <label for="formLongitud">Longitud</label>
                            <input id="formLongitud" name="longitud" type="text" placeholder="Ej: -77.116283">
                        </div>
                        <div class="field field--full">
                            <label for="formUrl">URL Mapa</label>
                            <input id="formUrl" name="url" type="text" placeholder="Se genera automaticamente" readonly>
                        </div>
                    </div>
                    <h3 class="section-title">Datos Tecnicos</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label for="formIp">IP</label>
                            <input id="formIp" name="ip" type="text" placeholder="Ej: 172.16.161.17">
                        </div>
                        <div class="field">
                            <label for="formMarca">Marca</label>
                            <input id="formMarca" name="marca" type="text" placeholder="Ej: DAHUA">
                        </div>
                        <div class="field">
                            <label for="formModelo">Modelo</label>
                            <input id="formModelo" name="modelo" type="text" placeholder="Ej: DH-IPC-HFW2841T-ZAS">
                        </div>
                        <div class="field">
                            <label for="formTipoEquipo">Tipo</label>
                            <input id="formTipoEquipo" name="tipo" type="text" placeholder="Ej: PTZ">
                        </div>
                        <div class="field">
                            <label for="formResolucion">Resolucion</label>
                            <input id="formResolucion" name="resolucion" type="text" placeholder="Ej: 1920 x 1080">
                        </div>
                        <div class="field">
                            <label for="formMegapixeles">Megapixeles</label>
                            <input id="formMegapixeles" name="megapixeles" type="text" placeholder="Ej: 2">
                        </div>
                        <div class="field">
                            <label for="formZoom">Zoom</label>
                            <input id="formZoom" name="zoom" type="text" placeholder="Ej: 45x">
                        </div>
                        <div class="field">
                            <label for="formTecnologia">Tecnologia</label>
                            <input id="formTecnologia" name="tecnologia" type="text" placeholder="Ej: IP">
                        </div>
                        <div class="field">
                            <label for="formAnioInstalacion">Año Instalacion</label>
                            <input id="formAnioInstalacion" name="anio_instalacion" type="text" placeholder="Ej: 2025">
                        </div>
                        <div class="field">
                            <label for="formConexion">Conexion</label>
                            <input id="formConexion" name="conexion" type="text" placeholder="Ej: Fibra Optica">
                        </div>
                        <div class="field">
                            <label for="formEstado">Estado</label>
                            <select id="formEstado" name="estado">
                                <option value="">-- Seleccionar --</option>
                                <option value="OPERATIVO">OPERATIVO</option>
                                <option value="INOPERATIVO">INOPERATIVO</option>
                                <option value="INTERMITENTE">INTERMITENTE</option>
                                <option value="MANTENIMIENTO">MANTENIMIENTO</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="formVcs">VCS</label>
                            <input id="formVcs" name="vcs" type="text" readonly>
                        </div>
                        <div class="field field--full">
                            <label for="formObservaciones">Observaciones</label>
                            <input id="formObservaciones" name="observaciones" type="text" readonly>
                        </div>
                    </div>
                    <h3 class="section-title">Contrato</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label for="formNumeroOc">Nro OC/Contrato</label>
                            <input id="formNumeroOc" name="numero_oc_contrato" type="text" placeholder="Ej: CONTRATO_0027-2018">
                        </div>
                        <div class="field">
                            <label for="formFechaContrato">Fecha Contrato</label>
                            <input id="formFechaContrato" name="fecha_contrato" type="date">
                        </div>
                        <div class="field">
                            <label for="formProveedor">Proveedor</label>
                            <input id="formProveedor" name="proveedor" type="text" placeholder="Ej: AMERICANET TELECOM">
                        </div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--ghost" id="btnCancelarModal">Cancelar</button>
                    <button type="submit" class="btn btn--primary" id="btnGuardarModal">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reporte Visores -->
    <div class="modal-overlay" id="modalReporteVisores" aria-hidden="true">
        <div class="modal" style="max-width: 800px; width: 95%;">
            <div class="modal__header">
                <h2>Estado de Botones "Ver Cámara"</h2>
                <button type="button" class="modal__close" id="btnCerrarModalReporte">&times;</button>
            </div>
            <div class="modal__body" style="max-height: 60vh; overflow-y: auto;">
                <p>Este reporte muestra qué dispositivos en el mapa tienen disponible el botón <strong>VER CÁMARA</strong> y cuáles no, junto a la razón exacta.</p>
                <h3 style="color: #4caf50; margin-top: 15px;">&#128994; Dispositivos CON botón 'Ver Cámara'</h3>
                <p style="font-size: 13px; color: #666;">Cumplen con ambas condiciones: el campo tipo_dispositivo es exactamente CAMARA y tienen una dirección IP configurada.</p>
                <div class="table-wrap" style="margin-top: 10px; margin-bottom: 20px;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>FID</th>
                                <th>Código</th>
                                <th>Dirección</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyReporteConBoton"></tbody>
                    </table>
                </div>

                <h3 style="color: #f44336;">&#128308; Dispositivos SIN botón 'Ver Cámara'</h3>
                <p style="font-size: 13px; color: #666;">No muestran el botón. Para solucionarlo, debes editar el dispositivo y asegurarte de clasificarlo como CAMARA y asignarle su respectiva IP.</p>
                <div class="table-wrap" style="margin-top: 10px;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>FID</th>
                                <th>Código</th>
                                <th>IP</th>
                                <th>Razón</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyReporteSinBoton"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost" id="btnAceptarModalReporte">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
        window.DS_ROUTES = @json($routes);
    </script>
    <script src="{{ asset('mod_ti/dispositivo_seg/js/api.js') }}?v=1"></script>
    <script src="{{ asset('mod_ti/dispositivo_seg/js/ui.js') }}?v=1"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"></script>
    <script src="{{ asset('mod_ti/dispositivo_seg/js/mapa.js') }}?v=1"></script>
</body>
</html>
