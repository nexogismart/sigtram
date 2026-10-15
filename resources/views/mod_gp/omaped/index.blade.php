<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OMAPED - Usuarios OMAPED</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="puede-eliminar" content="{{ $puedeEliminar ? '1' : '0' }}">
    <link rel="stylesheet" href="{{ asset('mod_gp/omaped/css/app.css') }}?v=1">
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gp.omaped.index'])
</head>
<body data-sigtram-screen="mod_gp.omaped.index" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gp.omaped.index'])
    <main class="app">
        <header class="hdr">
            <div class="hdr__banner">
                <img src="{{ $routes['banner'] }}" alt="Banner OMAPED">
            </div>
            <div class="hdr__top">
                <div class="hdr__logo-wrap">
                    <img class="hdr__logo" src="{{ asset('images/nexosigtram-documental-logo.png') }}" width="280" height="72" alt="Nexo SIGTRAM">
                </div>
                <div class="hdr__right">
                    <table class="hdr__meta" role="presentation">
                        <tbody>
                        <tr>
                            <td class="hdr__meta-lbl">Area</td>
                            <td class="hdr__meta-val">: {{ $usNomArea }}</td>
                        </tr>
                        <tr>
                            <td class="hdr__meta-lbl">Cargo</td>
                            <td class="hdr__meta-val">: {{ $usNomCargo }}</td>
                        </tr>
                        <tr>
                            <td class="hdr__meta-lbl">Nombre</td>
                            <td class="hdr__meta-val">: {{ $usNombre }}</td>
                        </tr>
                        </tbody>
                    </table>
                    <a class="hdr__text-btn" id="btnInicio" href="{{ $routes['inicio'] }}" target="_top">Inicio</a>
                </div>
            </div>
        </header>

        <div class="app__title-row">
            <p class="app__eyebrow">Gerencia de Prevención - OMAPED</p>
            <h1 id="pageTitle">Lista de Usuarios</h1>
        </div>

        <section class="card" id="cardNuevoPaciente" hidden>
            <div class="table-meta">
                <h2 class="card__title">Registro de nuevo usuario</h2>
                <button type="button" class="btn btn--ghost" id="btnVolverLista" hidden>Volver a lista</button>
            </div>
            <form id="nuevoPacienteForm" autocomplete="off">
                <div class="form-group">
                    <h3 class="panel-bloque__title">Datos personales</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label for="regNumDoc">N° Documento</label>
                            <input id="regNumDoc" name="regNumDoc" type="text" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" autocomplete="off" required>
                        </div>
                        <div class="field">
                            <label for="regNombres">Nombres</label>
                            <input id="regNombres" name="regNombres" type="text" required>
                        </div>
                        <div class="field">
                            <label for="regApePaterno">Apellido Paterno</label>
                            <input id="regApePaterno" name="regApePaterno" type="text" required>
                        </div>
                        <div class="field">
                            <label for="regApeMaterno">Apellido Materno</label>
                            <input id="regApeMaterno" name="regApeMaterno" type="text" required>
                        </div>
                        <div class="field">
                            <label for="regCelular">Celular</label>
                            <input id="regCelular" name="regCelular" type="text" maxlength="9" inputmode="numeric" pattern="[0-9]{0,9}">
                        </div>
                        <div class="field">
                            <label for="regGenero">Sexo / Genero</label>
                            <select id="regGenero" name="regGenero" required>
                                <option value="">-- Seleccione --</option>
                                <option value="FEMENINO">FEMENINO</option>
                                <option value="MASCULINO">MASCULINO</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="regDireccionPersona">Direccion</label>
                            <input id="regDireccionPersona" name="regDireccionPersona" type="text">
                        </div>
                    </div>
                    <div class="field field--full">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="regActualizarDatosPersona" name="regActualizarDatosPersona" style="width: auto; cursor: pointer;">
                            <span>Actualizar datos de persona</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <h3 class="panel-bloque__title">Datos adicionales</h3>
                    <div style="display: grid; grid-template-columns: 220px 1fr; gap: 16px; align-items: start;">
                        <div class="field">
                            <label for="regFoto">Foto del Paciente</label>
                            <div id="fotoPreview" style="width: 200px; height: 200px; border: 2px dashed #ccc; border-radius: 8px; overflow: hidden; background: #f5f5f5; position: relative; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                <input type="file" id="regFoto" name="regFoto" accept="image/*" style="display: none;">
                                <img id="fotoPreviewImg" src="" alt="Vista previa" style="width: 100%; height: 100%; object-fit: cover; display: none; position: absolute; top: 0; left: 0;">
                                <span id="fotoPreviewText" style="color: #666; font-size: 14px; text-align: center; padding: 8px;">Subir Foto</span>
                                <button type="button" id="btnEliminarFoto" style="position: absolute; top: 4px; right: 4px; background: rgba(255,0,0,0.7); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 14px; z-index: 10; display: none;">×</button>
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="regSector">Sector <span style="color: red;">*</span></label>
                                <select id="regSector" name="regSector" required></select>
                            </div>
                            <div class="field">
                                <label for="regZona">Zona <span style="color: red;">*</span></label>
                                <select id="regZona" name="regZona" required>
                                    <option value="">-- Seleccione --</option>
                                    <option value="NORTE">NORTE</option>
                                    <option value="CENTRO">CENTRO</option>
                                    <option value="SUR">SUR</option>
                                    <option value="OTRO DISTRITO">OTRO DISTRITO</option>
                                </select>
                            </div>
                            <div class="field" id="fieldOtroDistrito" hidden>
                                <label for="regOtroDistrito">Otro Distrito</label>
                                <select id="regOtroDistrito" name="regOtroDistrito">
                                    <option value="">-- Seleccione --</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="regFechaNac">Fecha Nacimiento</label>
                                <input id="regFechaNac" name="regFechaNac" type="date">
                            </div>
                            <div class="field  field--full">
                                <label for="regUbicacion">Ubicación</label>
                                <input id="regUbicacion" name="regUbicacion" type="text">
                            </div>
                            <div class="field">
                                <label for="regLatitud">Latitud</label>
                                <input id="regLatitud" name="regLatitud" type="text" placeholder="Ej: -12.046374" inputmode="decimal">
                            </div>
                            <div class="field">
                                <label for="regLongitud">Longitud</label>
                                <input id="regLongitud" name="regLongitud" type="text" placeholder="Ej: -77.042793" inputmode="decimal">
                            </div>
                            <div class="field">
                                <label>&nbsp;</label>
                                <button type="button" class="btn btn--ghost" id="btnObtenerCoordenadas">Obtener coordenadas</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <h3 class="panel-bloque__title">Discapacidad Omaped</h3>
                    <div class="form-grid">
                        <div class="field field--full">
                            <label>Tipo de discapacidad</label>
                            <div class="checkbox-grid">
                                <label><input type="checkbox" name="regTiposDiscapacidad[]" value="MOTORA">MOTORA</label>
                                <label><input type="checkbox" name="regTiposDiscapacidad[]" value="SENSORIAL">SENSORIAL</label>
                                <label><input type="checkbox" name="regTiposDiscapacidad[]" value="INTELECTUAL">INTELECTUAL</label>
                                <label><input type="checkbox" name="regTiposDiscapacidad[]" value="MENTAL">MENTAL</label>
                            </div>
                        </div>
                        <div class="field">
                            <label for="regDescripcionDiscapacidad">Descripcion de Diagnostico</label>
                            <input id="regDescripcionDiscapacidad" name="regDescripcionDiscapacidad" type="text" placeholder="Detalle de la discapacidad">
                        </div>
                        <div class="field">
                            <label for="regCodigoConadis">Codigo Carnet Conadis</label>
                            <input id="regCodigoConadis" name="regCodigoConadis" type="text">
                        </div>
                        <div class="field">
                            <label for="regGradoDiscapacidad">Grado de discapacidad</label>
                            <select id="regGradoDiscapacidad" name="regGradoDiscapacidad" required>
                                <option value="">-- Seleccione --</option>
                                <option value="LEVE">LEVE</option>
                                <option value="MODERADO">MODERADO</option>
                                <option value="SEVERO">SEVERO</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="regSeguroSalud">Seguro de salud</label>
                            <input id="regSeguroSalud" name="regSeguroSalud" type="text">
                        </div>
                        <div class="field">
                            <label for="regCarnetConadisArchivo" id="regCarnetConadisArchivoLabel">Carnet Conadis (archivo)</label>
                            <input id="regCarnetConadisArchivo" name="regCarnetConadisArchivo" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                        <div class="field">
                            <label for="regCertificadoDiscapacidadArchivo" id="regCertificadoDiscapacidadArchivoLabel">Certificado de discapacidad (archivo)</label>
                            <input id="regCertificadoDiscapacidadArchivo" name="regCertificadoDiscapacidadArchivo" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <h3 class="panel-bloque__title">Datos de Familiar Cuidador</h3>
                    <div class="parientes-toolbar">
                        <button type="button" class="btn btn--ghost" id="btnAbrirParienteModal">+ Agregar Fam. Cuidador</button>
                        <p id="parientesCountLabel">0 Fam. Cuidador(s) agregado(s)</p>
                    </div>
                    <div class="table-wrap">
                        <table class="table" id="parientesRegistroTable">
                            <thead>
                                <tr>
                                    <th>Nombres</th>
                                    <th>Apellido Paterno</th>
                                    <th>Apellido Materno</th>
                                    <th>N° Documento</th>
                                    <th>Parentesco</th>
                                    <th>Celular</th>
                                    <th>Direccion</th>
                                    <th>Observaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="parientesTbody">
                                <tr class="empty-row">
                                    <td colspan="9">Aun no se agregaron parientes.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" id="btnCancelarNuevoPaciente">Cancelar</button>
                    <button type="submit" class="btn btn--primary">Guardar usuario</button>
                </div>
            </form>
        </section>

        <div id="vistaLista">
        <section class="card">
            <h2 class="card__title">Filtros</h2>
            <form class="filters" id="filtersForm" autocomplete="off">
                <div class="field">
                    <label for="tipoFiltro">Tipo de filtro</label>
                    <select id="tipoFiltro" name="tipoFiltro">
                        <option value="dni" selected>DNI</option>
                        <option value="nombres">Nombres</option>
                        <option value="direccion">Dirección</option>
                        <option value="zona">Zona</option>
                        <option value="discapacidad">Discapacidad</option>
                        <option value="fecha">Fecha de registro</option>
                    </select>
                </div>
                <div class="field" id="fieldFiltroTexto">
                    <label for="q" id="labelFiltroTexto"></label>
                    <input
                        id="q"
                        name="q"
                        type="text"
                    >
                </div>
                <div class="field" id="fieldZonaFiltro" hidden>
                    <label for="zona">Zona</label>
                    <select id="zona" name="zona">
                        <option value="">-- TODAS --</option>
                        <option value="NORTE">NORTE</option>
                        <option value="CENTRO">CENTRO</option>
                        <option value="SUR">SUR</option>
                        <option value="OTRO DISTRITO">OTRO DISTRITO</option>
                    </select>
                </div>
                <div class="field" id="fieldFechaInicioFiltro" hidden>
                    <label for="fechaInicio">Fecha Inicio</label>
                    <input id="fechaInicio" name="fechaInicio" type="date">
                </div>
                <div class="field" id="fieldFechaFinFiltro" hidden>
                    <label for="fechaFin">Fecha Fin</label>
                    <input id="fechaFin" name="fechaFin" type="date">
                </div>
                <div class="filters__actions">
                    <button class="btn btn--ghost" type="reset">Limpiar</button>
                    <button class="btn btn--excel" type="button" id="btnExportarExcel">
                        Exportar a Excel
                    </button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="table-meta">
                <h2 class="card__title">Usuarios OMAPED</h2>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <p id="countLabel">0 resultados</p>
                    <button type="button" class="btn btn--primary btn--sm" id="btnNuevoPaciente">+ Nuevo</button>
                </div>
            </div>
            <div class="table-wrap">
                <div id="tablaLoading" class="tabla-loading" aria-hidden="true">
                    <div class="tabla-loading__box">
                        <span class="tabla-loading__spinner" aria-hidden="true"></span>
                        <span class="tabla-loading__text">Cargando usuarios…</span>
                    </div>
                </div>
                <table class="table" id="pacientesTable">
                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Nombres</th>
                            <th>Apellido Paterno</th>
                            <th>Apellido Materno</th>
                            <th>Discapacidad</th>
                            <th>Zona</th>
                            <th class="col-diagnostico">Diagnostico</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="pacientesTbody"></tbody>
                </table>
            </div>
            <div id="paginacionContainer"></div>
        </section>

        <section class="card" id="panelPaciente" hidden>
            <div class="table-meta">
                <h2 class="card__title" id="panelTitulo">Historico del paciente</h2>
            </div>

            <div id="panelDatosAdicionales" hidden>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <section id="panelFotoPaciente" class="card card--subtle">
                        <h4 class="panel-bloque__title">Datos del Usuario</h4>
                        <div style="display: flex; gap: 24px; align-items: flex-start;">
                            <div id="fotoPacienteContainer" style="flex-shrink: 0;">
                                <div id="fotoPacienteMarco" style="width: 200px; height: 200px; border: 2px dashed #ccc; border-radius: 8px; overflow: hidden; background: #f5f5f5; position: relative; display: flex; align-items: center; justify-content: center;">
                                    <img id="fotoPacienteImg" src="" alt="Foto del paciente" style="width: 100%; height: 100%; object-fit: cover; display: none; position: absolute; top: 0; left: 0;">
                                    <span id="fotoPacienteTexto" style="color: #666; font-size: 14px; text-align: center; padding: 8px; display: block;">Sin foto</span>
                                </div>
                            </div>
                            <div id="datosPersonalesPaciente" style="flex: 1;">
                                <h4 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600;" id="nombreCompletoPaciente"></h4>
                                <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: 14px;">
                                    <strong>DNI:</strong> <span id="dniPaciente"></span>
                                    <strong>Género:</strong> <span id="generoPaciente"></span>
                                    <strong>Celular:</strong> <span id="celularPaciente"></span>
                                    <strong>Dirección:</strong> <span id="direccionPaciente"></span>
                                    <strong>Edad:</strong> <span id="edadPaciente">-</span>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section id="panelDatosAdicionalesDetalle" class="card card--subtle">
                        <h4 class="panel-bloque__title">Datos Adicionales</h4>
                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: 14px;">
                            <strong>Sector:</strong> <span id="detalleSector">-</span>
                            <strong>Zona:</strong> <span id="detalleZona">-</span>
                            <strong>Fecha Nacimiento:</strong> <span id="detalleFechaNac">-</span>
                            <strong>Ubicación:</strong> <span id="detalleUbicacion">-</span>
                            <strong>Latitud:</strong> <span id="detalleLatitud">-</span>
                            <strong>Longitud:</strong> <span id="detalleLongitud">-</span>
                        </div>
                    </section>
                </div>
                <div style="display: grid; grid-template-columns: minmax(220px, 0.8fr) minmax(0, 2.2fr); gap: 16px;">
                    <section id="panelDiagnosticoOmaped" class="card card--subtle">
                        <h4 class="panel-bloque__title">Discapacidad</h4>
                        <div class="diagnostico-detalle" id="diagnosticoDetalle"></div>

                        <!-- Bloque QR del usuario OMAPED -->
                        <div id="qrPacienteWrapper" style="margin-top: 16px; border-top: 1px solid #eee; padding-top: 12px; display: none;">
                            <h5 style="font-size: 14px; font-weight: 600; margin-bottom: 8px;">QR del Usuario</h5>
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                                <img id="qrPacienteImg"
                                     src=""
                                     alt="QR del usuario OMAPED"
                                     style="max-width: 160px; height: auto; border: 1px solid #ddd; padding: 6px; background: #fff; border-radius: 4px;">
                                <button type="button"
                                        id="btnDescargarQrPaciente"
                                        class="btn btn--primary btn--sm"
                                        style="min-width: 150px;">
                                    Descargar QR
                                </button>
                            </div>
                        </div>
                    </section>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <section id="panelFamiliar" class="card card--subtle" hidden>
                            <div class="historico-toolbar">
                                <h4 class="panel-bloque__title">Familiar Cuidador</h4>
                                <button type="button" class="btn btn--ghost" id="btnAbrirParienteModalDetalle">+ Agregar Fam. Cuidador</button>
                            </div>
                            <div class="table-wrap">
                                <table class="table" id="parientesDetalleTable">
                                    <thead>
                                        <tr>
                                            <th>N° Documento</th>
                                            <th>Nombres</th>
                                            <th>Apellido Paterno</th>
                                            <th>Apellido Materno</th>
                                            <th>Parentesco</th>
                                            <th>Celular</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="parientesDetalleTbody"></tbody>
                                </table>
                            </div>
                        </section>
                        <section id="panelDiagnosticos" class="card card--subtle" hidden>
                            <div class="historico-toolbar">
                                <h4 class="panel-bloque__title">Registro de Atenciones</h4>
                                <button type="button" class="btn btn--ghost" id="btnAbrirHistoricoModal">+ Agregar atencion</button>
                            </div>
                            <div class="table-wrap">
                                <table class="table" id="historicoTable">
                                    <thead>
                                        <tr>
                                            <th>Fecha de atencion</th>
                                            <th class="col-servicio-historico">Servicio</th>
                                            <th>Sede</th>
                                            <th>Observaciones</th>
                                            <th>Evidencia</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="diagnosticosTbody"></tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </section>
        </div>
    </main>

    <div class="modal" id="parienteModal" hidden>
        <div class="modal__backdrop" data-close-pariente-modal="1"></div>
        <div class="modal__content modal__content--sm" role="dialog" aria-modal="true" aria-labelledby="parienteModalTitle">
            <div class="modal__header">
                <h3 id="parienteModalTitle">Agregar Familiar Cuidador</h3>
                <button type="button" class="btn-icon btn-icon--close" data-close-pariente-modal="1" aria-label="Cerrar">x</button>
            </div>
            <form id="parienteForm" autocomplete="off">
                <div class="form-grid">
                    <div class="field">
                        <label for="parienteDocumento">N° Documento</label>
                        <input id="parienteDocumento" name="parienteDocumento" type="text" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" autocomplete="off" required>
                    </div>
                    <div class="field">
                        <label for="parienteNombres">Nombres</label>
                        <input id="parienteNombres" name="parienteNombres" type="text" required>
                    </div>
                    <div class="field">
                        <label for="parienteApePaterno">Apellido Paterno</label>
                        <input id="parienteApePaterno" name="parienteApePaterno" type="text" required>
                    </div>
                    <div class="field">
                        <label for="parienteApeMaterno">Apellido Materno</label>
                        <input id="parienteApeMaterno" name="parienteApeMaterno" type="text" required>
                    </div>
                    <div class="field">
                        <label for="parienteParentesco">Tipo Parentesco</label>
                        <select id="parienteParentesco" name="parienteParentesco"></select>
                    </div>
                    <div class="field">
                        <label for="parienteTelefono">Celular</label>
                        <input id="parienteTelefono" name="parienteTelefono" type="text" maxlength="9" inputmode="numeric" pattern="[0-9]{0,9}">
                    </div>
                    <div class="field">
                        <label for="parienteDireccion">Direccion</label>
                        <input id="parienteDireccion" name="parienteDireccion" type="text">
                    </div>
                    <div class="field field--full">
                        <label for="parienteObservaciones">Observaciones</label>
                        <input id="parienteObservaciones" name="parienteObservaciones" type="text">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-pariente-modal="1">Cancelar</button>
                    <button type="submit" class="btn btn--primary">Agregar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="diagnosticoModal" hidden>
        <div class="modal__backdrop" data-close-modal="1"></div>
        <div class="modal__content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal__header">
                <h3 id="modalTitle">Historico de diagnostico</h3>
                <button type="button" class="btn-icon btn-icon--close" data-close-modal="1" aria-label="Cerrar">x</button>
            </div>
            <div class="readonly-grid" id="diagnosticoReadonlyGrid"></div>
        </div>
    </div>

    <div class="modal" id="parienteDetalleModal" hidden>
        <div class="modal__backdrop" data-close-pariente-detalle-modal="1"></div>
        <div class="modal__content modal__content--sm" role="dialog" aria-modal="true" aria-labelledby="parienteDetalleTitle">
            <div class="modal__header">
                <h3 id="parienteDetalleTitle">Detalle de pariente</h3>
                <button type="button" class="btn-icon btn-icon--close" data-close-pariente-detalle-modal="1" aria-label="Cerrar">x</button>
            </div>
            <div class="readonly-grid" id="parienteDetalleReadonlyGrid"></div>
        </div>
    </div>

    <div class="modal" id="historicoModal" hidden>
        <div class="modal__backdrop" data-close-historico-modal="1"></div>
        <div class="modal__content modal__content--lg" role="dialog" aria-modal="true" aria-labelledby="historicoModalTitle">
            <div class="modal__header">
                <h3 id="historicoModalTitle">Registro de atenciones</h3>
                <button type="button" class="btn-icon btn-icon--close" data-close-historico-modal="1" aria-label="Cerrar">x</button>
            </div>
            <form id="historicoForm" autocomplete="off">
                <div class="form-group">
                    <h3 class="panel-bloque__title">Historial de atenciones</h3>
                    <div class="form-grid">
                        <div class="field field--full">
                            <label>Servicio (multiple seleccion)</label>
                            <div class="checkbox-grid" id="serviciosCheckboxGrid">
                                <!-- Los servicios se cargarán dinámicamente desde la base de datos -->
                            </div>
                        </div>
                        <div class="field field--full" id="fieldServicioOtro" hidden>
                            <label for="aServicioOtro">Otro Servicio</label>
                            <input id="aServicioOtro" type="text" placeholder="Especificar otro servicio">
                        </div>
                        <div class="field">
                            <label for="aSedeAtencion">Sede de atencion</label>
                            <select id="aSedeAtencion">
                                <option value="PALACIO DE LA CULTURA">PALACIO DE LA CULTURA</option>
                                <option value="MICAELA BASTIDAS">MICAELA BASTIDAS</option>
                                <option value="LADERAS DE CHILLON">LADERAS DE CHILLON</option>
                                <option value="ENSENADA">ENSENADA</option>
                                <option value="ATENCION DOMICILIARIA">ATENCION DOMICILIARIA</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="aFechaAtencion">Fecha de atencion</label>
                            <input id="aFechaAtencion" type="date" required>
                        </div>
                        <div class="field field--full">
                            <label for="aObservaciones">Observaciones</label>
                            <input id="aObservaciones" type="text">
                        </div>
                        <div class="field field--full">
                            <label for="aEvidenciaAtencion">Evidencia de atencion</label>
                            <input id="aEvidenciaAtencion" type="file" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-historico-modal="1">Cancelar</button>
                    <button type="submit" class="btn btn--primary">Registrar atencion</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="diagnosticoOmapedModal" hidden>
        <div class="modal__backdrop" data-close-diagnostico-omaped-modal="1"></div>
        <div class="modal__content modal__content--sm" role="dialog" aria-modal="true" aria-labelledby="diagnosticoOmapedTitle">
            <div class="modal__header">
                <h3 id="diagnosticoOmapedTitle">Editar Discapacidad</h3>
                <button type="button" class="btn-icon btn-icon--close" data-close-diagnostico-omaped-modal="1" aria-label="Cerrar">x</button>
            </div>
            <form id="diagnosticoOmapedForm" autocomplete="off">
                <div class="form-grid">
                    <div class="field"><label for="doIdDiscapacidad">Id Discapacidad</label><input id="doIdDiscapacidad" type="text"></div>
                    <div class="field"><label for="doIdPaciente">Id Paciente</label><input id="doIdPaciente" type="text"></div>
                    <div class="field"><label for="doDescripcion">Descripcion</label><input id="doDescripcion" type="text" required></div>
                    <div class="field"><label for="doCodigoConadis">Codigo Carnet Conadis</label><input id="doCodigoConadis" type="text"></div>
                    <div class="field"><label for="doGradoDiscapacidad">Grado de Discapacidad</label><select id="doGradoDiscapacidad" required><option value="">-- Seleccione --</option><option value="LEVE">LEVE</option><option value="MODERADO">MODERADO</option><option value="SEVERO">SEVERO</option></select></div>
                    <div class="field"><label for="doSeguroSalud">Seguro de Salud</label><input id="doSeguroSalud" type="text"></div>
                    <div class="field"><label for="doArchivoCarnetConadis">Archivo Carnet de Conadis</label><input id="doArchivoCarnetConadis" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
                    <div class="field"><label for="doArchivoCertificadoDiscapacidad">Archivo Certificado de Discapacidad</label><input id="doArchivoCertificadoDiscapacidad" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--ghost" data-close-diagnostico-omaped-modal="1">Cancelar</button>
                    <button type="submit" class="btn btn--primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>
    <style>
        .table-wrap { position: relative; min-height: 140px; }
        .tabla-loading { display: none; position: absolute; inset: 0; background: rgba(255,255,255,0.75); backdrop-filter: blur(1px); align-items: center; justify-content: center; z-index: 10; }
        .tabla-loading.is-visible { display: flex !important; }
        .tabla-loading__box { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 10px; background: #fff; border: 1px solid #e5e7eb; box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
        .tabla-loading__spinner { width: 18px; height: 18px; border: 3px solid #e5e7eb; border-top-color: #dc2626; border-radius: 50%; display: inline-block; animation: spin 0.8s linear infinite; }
        .tabla-loading__text { font-weight: 600; color: #374151; font-size: 13px; }
        @keyframes spin { from { transform: rotate(0deg);} to { transform: rotate(360deg);} }
    </style>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
        <script>
        window.DS_ROUTES = @json($routes);
        window.OMAPED_ROUTES = window.DS_ROUTES;
    </script>
    <script src="{{ asset('mod_gp/omaped/js/toast.js') }}?v=1"></script>
    <script src="{{ asset('mod_gp/omaped/js/app.js') }}?v=1"></script>
</body>
</html>
