<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buscar requerimiento de pago</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_a/sgl/pago_proveedores/css/app.css') }}?v=2">
</head>
<body>
    <main class="app">
        <div class="app__title-row">
            <h1>Buscar requerimiento de pago</h1>
        </div>

        <div class="layout-2col">
            <section class="card">
                <h2 class="card__title">Lista de requerimientos</h2>
                <form class="filters" id="frmFiltros" autocomplete="off" onsubmit="return false;">
                    <div class="field">
                        <label for="selArea">Área</label>
                        <select id="selArea" name="area_id">
                            <option value="0">-- Todas las áreas --</option>
                            @foreach($areas as $area)
                                <option value="{{ $area['id'] }}">{{ $area['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="selCampo">Buscar</label>
                        <select id="selCampo" name="campo">
                            <option value="nu_tram_todo">Nº Trámite</option>
                            <option value="de_pgo_prvdres">Asunto</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="txtCriterio">Criterio</label>
                        <input id="txtCriterio" name="criterio" type="text" maxlength="80" placeholder="Escriba para buscar">
                    </div>
                    <div class="field">
                        <label for="txtFecha">Fecha</label>
                        <input id="txtFecha" name="fecha" type="date">
                    </div>
                    <div class="filters__actions">
                        <button class="btn btn--primary" type="button" id="btnBuscar">Buscar</button>
                    </div>
                </form>

                <div class="table-meta">
                    <p id="lblResultados">0 resultados</p>
                </div>

                <div id="listaRequerimientos">
                    <p class="table-empty">Realice una búsqueda para ver resultados.</p>
                </div>

                <div id="paginacionContainer"></div>
            </section>

            <section class="card" id="panelDetalle">
                <h2 class="card__title">Ver pago a proveedor</h2>

                <div id="detalleVacio">
                    <p>Seleccione un requerimiento de la lista para ver su detalle.</p>
                </div>

                <div id="detalleContenido" hidden>
                    <div class="field-view">
                        <strong>Documento:</strong> <span id="detDocumento"></span>
                    </div>
                    <div class="field-view">
                        <strong>Asunto:</strong> <span id="detAsunto"></span>
                    </div>
                    <div class="field-view">
                        <strong>Área:</strong> <span id="detArea"></span>
                    </div>

                    <fieldset class="fieldset-block">
                        <legend>Seguimiento del pago</legend>
                        <div id="conteSeguimientoPago">
                            <p class="table-empty">Sin información de seguimiento.</p>
                        </div>
                    </fieldset>

                    <fieldset class="fieldset-block">
                        <legend>Lista del requerimiento</legend>
                        <div class="table-wrap">
                            <table class="table" id="tablaItems">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Cant.</th>
                                        <th>Descripción del requerimiento</th>
                                        <th>Pagos</th>
                                        <th>Monto</th>
                                        <th>SIGA</th>
                                        <th>O/S</th>
                                        <th>TDR</th>
                                        <th>ANE</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaItemsBody"></tbody>
                            </table>
                        </div>
                    </fieldset>

                    <fieldset class="fieldset-block" id="bloqueSeguimientoTramite" hidden>
                        <legend>Seguimiento del trámite</legend>
                        <div id="conteSeguimientoTramite"></div>
                    </fieldset>
                </div>
            </section>
        </div>
    </main>

    <script>
        window.SGL_ROUTES = @json($routes);
        window.SGL_OPEN_PGO_ID = {{ (int) ($openPgoId ?? 0) }};
    </script>
    <script src="{{ asset('mod_a/sgl/pago_proveedores/js/buscar_requerimiento_pago.js') }}?v=3"></script>
</body>
</html>
