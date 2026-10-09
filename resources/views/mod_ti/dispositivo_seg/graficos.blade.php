<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estadísticas - Dispositivos de Seguridad</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_ti/dispositivo_seg/css/app.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('mod_ti/dispositivo_seg/css/graficos.css') }}?v=1">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous"></script>
</head>
<body>
    <main class="app">
        <header class="app__header">
            <div>
                <p class="app__eyebrow">Tecnologías de la Información</p>
                <h1>Estadísticas de Dispositivos de Seguridad</h1>
            </div>
            <div class="app__header-actions">
                <a href="{{ $routes['index'] }}" class="btn btn--ghost">Volver al listado</a>
            </div>
        </header>

        <section class="card card--filtros">
            <h2 class="card__title">Filtros para el reporte</h2>
            <form id="filtrosForm" method="get" autocomplete="off"
                  data-sector-id="{{ $filtros['sector_id_raw'] }}"
                  data-sub-sector-id="{{ $filtros['sub_sector_id_raw'] }}">
                <div class="filters-row">
                    <div class="field field--search">
                        <label for="q">Búsqueda</label>
                        <input id="q" name="q" type="text" placeholder="Tipo, código, dirección, marca, modelo..." value="{{ $filtros['q'] }}">
                    </div>
                    <div class="field">
                        <label for="filtroTipo">Tipo dispositivo</label>
                        <select id="filtroTipo" name="tipo_dispositivo">
                            <option value="">Todos</option>
                            <option value="CAMARA" @selected($filtros['tipo'] === 'CAMARA')>CÁMARA</option>
                            <option value="ALARMA" @selected($filtros['tipo'] === 'ALARMA')>ALARMA</option>
                            <option value="MUFA" @selected($filtros['tipo'] === 'MUFA')>MUFA</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="filtroZona">Zona</label>
                        <select id="filtroZona" name="zona">
                            <option value="">Todas</option>
                            <option value="NORTE" @selected($filtros['zona'] === 'NORTE')>NORTE</option>
                            <option value="CENTRO" @selected($filtros['zona'] === 'CENTRO')>CENTRO</option>
                            <option value="SUR" @selected($filtros['zona'] === 'SUR')>SUR</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="filtroSector">Sector</label>
                        <select id="filtroSector" name="sector_id">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="filtroSubSector">Sub Sector</label>
                        <select id="filtroSubSector" name="sub_sector_id">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="filtroEstado">Estado</label>
                        <select id="filtroEstado" name="estado">
                            <option value="">Todos</option>
                            <option value="OPERATIVO" @selected($filtros['estado'] === 'OPERATIVO')>OPERATIVO</option>
                            <option value="INOPERATIVO" @selected($filtros['estado'] === 'INOPERATIVO')>INOPERATIVO</option>
                            <option value="INTERMITENTE" @selected($filtros['estado'] === 'INTERMITENTE')>INTERMITENTE</option>
                            <option value="MANTENIMIENTO" @selected($filtros['estado'] === 'MANTENIMIENTO')>MANTENIMIENTO</option>
                        </select>
                    </div>
                </div>
                <div class="filters-row">
                    <div class="field">
                        <label for="filtroFechaDesde">Desde</label>
                        <input id="filtroFechaDesde" name="fecha_desde" type="date" value="{{ $filtros['fecha_desde'] }}">
                    </div>
                    <div class="field">
                        <label for="filtroFechaHasta">Hasta</label>
                        <input id="filtroFechaHasta" name="fecha_hasta" type="date" value="{{ $filtros['fecha_hasta'] }}">
                    </div>
                    <div class="filters-actions filters-actions--inline">
                        <button type="submit" class="btn btn--primary">Aplicar filtros</button>
                        <a href="{{ $routes['graficos'] }}" class="btn btn--ghost">Limpiar</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="card card--resumen">
            <p id="resumenLabel" class="resumen-total">Cargando...</p>
        </section>

        <section class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-card__title">Por estado operativo</h3>
                <div class="chart-wrap"><canvas id="chartEstado"></canvas></div>
            </div>
            <div class="chart-card">
                <h3 class="chart-card__title">Por tipo de dispositivo</h3>
                <div class="chart-wrap"><canvas id="chartTipoDispositivo"></canvas></div>
            </div>
            <div class="chart-card">
                <h3 class="chart-card__title">Por zona</h3>
                <div class="chart-wrap"><canvas id="chartZona"></canvas></div>
            </div>
            <div class="chart-card">
                <h3 class="chart-card__title">Por tipo (Fija / PTZ)</h3>
                <div class="chart-wrap"><canvas id="chartTipo"></canvas></div>
            </div>
            <div class="chart-card chart-card--wide">
                <h3 class="chart-card__title">Por marca (top 15)</h3>
                <div class="chart-wrap"><canvas id="chartMarca"></canvas></div>
            </div>
            <div class="chart-card chart-card--wide">
                <h3 class="chart-card__title">Por modelo (top 15)</h3>
                <div class="chart-wrap"><canvas id="chartModelo"></canvas></div>
            </div>
            <div class="chart-card chart-card--wide">
                <h3 class="chart-card__title">Cámaras por sector y subsector</h3>
                <p class="chart-card__desc">Haz clic en una cámara para ver su detalle.</p>
                <div id="reporteSectorSubsector" class="reporte-sector-subsector"></div>
            </div>
            <div class="chart-card chart-card--wide chart-card--tall">
                <h3 class="chart-card__title">Mapa de dispositivos</h3>
                <div class="mapa-descargas">
                    <p class="mapa-descargas__texto">Con los filtros aplicados puede descargar: <strong>Excel</strong> (datos para análisis) y <strong>Mapa (PNG)</strong> (vista actual para informes o presentaciones).</p>
                    <div class="mapa-descargas__btns">
                        <button type="button" class="btn btn--excel" id="btnDescargarExcel">Descargar Excel</button>
                        <button type="button" class="btn btn--graficos" id="btnDescargarMapa">Descargar mapa (PNG)</button>
                    </div>
                </div>
                <div id="mapaExportArea" class="mapa-export-area">
                    <div id="resumenEstadosContainer" class="resumen-estados"></div>
                    <div id="mapaDispositivos" class="mapa-dispositivos"></div>
                </div>
                <p id="mapaSinUbicacion" class="mapa-sin-ubicacion"></p>
            </div>
        </section>
    </main>

    <div id="modalDetalleOverlay" class="modal-detalle-overlay">
        <div class="modal-detalle">
            <div class="modal-detalle__header">
                <h3 id="modalDetalleTitulo" class="modal-detalle__titulo">Detalle del dispositivo</h3>
                <button type="button" class="modal-detalle__cerrar" id="modalDetalleCerrar" aria-label="Cerrar">&times;</button>
            </div>
            <div id="modalDetalleCuerpo" class="modal-detalle__cuerpo"></div>
        </div>
    </div>

    <script>
        window.DS_ROUTES = @json($routes);
    </script>
    <script src="{{ asset('mod_ti/dispositivo_seg/js/api.js') }}?v=1"></script>
    <script src="{{ asset('mod_ti/dispositivo_seg/js/graficos.js') }}?v=1"></script>
</body>
</html>
