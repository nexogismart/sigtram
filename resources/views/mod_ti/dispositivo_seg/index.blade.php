<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dispositivos de Seguridad</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_ti/dispositivo_seg/css/app.css') }}?v=1">
</head>
<body>
    <main class="app">
        <header class="app__header">
            <div>
                <p class="app__eyebrow">Tecnologias de la Informacion</p>
                <h1>Mantenimiento de Dispositivos de Seguridad</h1>
            </div>
            <div class="app__header-actions">
                <a href="{{ $routes['inicio'] }}" class="btn btn--ghost" target="_top">Inicio</a>
                <button type="button" class="btn btn--primary" id="btnAgregar">+ Agregar</button>
            </div>
        </header>

        <section class="card">
            <h2 class="card__title">Filtros</h2>
            <form id="filtrosForm" autocomplete="off">
                <div class="filters-row">
                    <div class="field field--search">
                        <label for="q">Busqueda</label>
                        <input id="q" name="q" type="text" placeholder="Buscar por tipo, codigo, direccion, marca, modelo...">
                    </div>
                    <div class="field">
                        <label for="filtroTipo">Tipo</label>
                        <select id="filtroTipo" name="tipo_dispositivo">
                            <option value="">Todos</option>
                            <option value="CAMARA">CAMARA</option>
                            <option value="ALARMA">ALARMA</option>
                            <option value="MUFA">MUFA</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="filtroZona">Zona</label>
                        <select id="filtroZona" name="zona">
                            <option value="">Todas</option>
                            <option value="NORTE">NORTE</option>
                            <option value="CENTRO">CENTRO</option>
                            <option value="SUR">SUR</option>
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
                            <option value="OPERATIVO">OPERATIVO</option>
                            <option value="INOPERATIVO">INOPERATIVO</option>
                            <option value="INTERMITENTE">INTERMITENTE</option>
                            <option value="MANTENIMIENTO">MANTENIMIENTO</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="filtroFechaDesde">Desde</label>
                        <input id="filtroFechaDesde" name="fecha_desde" type="date">
                    </div>
                    <div class="field">
                        <label for="filtroFechaHasta">Hasta</label>
                        <input id="filtroFechaHasta" name="fecha_hasta" type="date">
                    </div>
                </div>
                <div class="filters-actions">
                    <button type="submit" class="btn btn--primary">Buscar</button>
                    <button type="reset" class="btn btn--ghost">Limpiar</button>
                    <button type="button" class="btn btn--excel" id="btnExportar">Excel</button>
                    <button type="button" class="btn btn--graficos" id="btnGraficos">Gráficos</button>
                    <button type="button" class="btn btn--mapa" id="btnMapa">Mapa</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="table-meta">
                <h2 class="card__title">Listado</h2>
                <p id="countLabel">0 registros</p>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Tipo Dispositivo</th>
                            <th>Zona</th>
                            <th>Sector</th>
                            <th>Sub Sector</th>
                            <th>Codigo</th>
                            <th>Direccion</th>
                            <th>IP</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Estado</th>
                            <th>Ultima Conexion</th>
                            <th>Mapa</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="dispositivosTbody">
                        <tr class="empty-row">
                            <td colspan="14">Cargando registros...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pager" id="pager"></div>
        </section>
    </main>

    <!-- Modal -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <div class="modal__header">
                <h2 id="modalTitulo">Agregar Dispositivo</h2>
                <button type="button" class="modal__close" id="btnCerrarModal">&times;</button>
            </div>
            <form id="formDispositivo" method="post" autocomplete="off">
                @csrf
                <input type="hidden" id="formId" name="id">
                <div class="modal__body">
                    <h3 class="section-title">Informacion General</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label for="formTipo">Tipo Dispositivo</label>
                            <select id="formTipo" name="tipo_dispositivo" required title="Por favor, seleccione el tipo de dispositivo">
                                <option value="">-- Seleccionar --</option>
                                <option value="CAMARA">CAMARA</option>
                                <option value="ALARMA">ALARMA</option>
                                <option value="MUFA">MUFA</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="formZona">Zona</label>
                            <select id="formZona" name="zona" required title="Por favor, seleccione la zona">
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
                            <label for="formUsuarioVisor">Usuario Visor <small style="font-weight:normal;opacity:.7">(acceso stream)</small></label>
                            <input id="formUsuarioVisor" name="usuario_visor" type="text" placeholder="Ej: admin" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="formPasswordVisor">Password Visor</label>
                            <input id="formPasswordVisor" name="password_visor" type="password" placeholder="Contraseña de la cámara" autocomplete="new-password">
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

                    <div style="display: none;">
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
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn--ghost" id="btnCancelarModal">Cancelar</button>
                    <button type="submit" class="btn btn--primary" id="btnGuardarModal">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.DS_ROUTES = @json($routes);
    </script>
    <script src="{{ asset('mod_ti/dispositivo_seg/js/api.js') }}?v=1"></script>
    <script src="{{ asset('mod_ti/dispositivo_seg/js/ui.js') }}?v=1"></script>
    <script src="{{ asset('mod_ti/dispositivo_seg/js/app.js') }}?v=1"></script>
</body>
</html>
