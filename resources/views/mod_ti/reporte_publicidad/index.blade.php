<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema de Gestión de Reportes</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('mod_ti/reporte_publicidad/css/app.css') }}?v=2">
    <style>
        .error-message { font-size: 12px; color: red; margin-top: 5px; display: block; }
    </style>
</head>
<body>
<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header">
            <h1 class="h3">Sistema de Gestión de Reportes</h1>
        </div>

        <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                <button id="btnNuevoReporte" type="button" class="btn btn-primary rounded-pill shadow-sm px-4 py-2 d-flex align-items-center justify-content-center" style="min-width: 160px;">
                    <i class="fas fa-plus me-2"></i> <span>Nuevo Reporte</span>
                </button>
            </div>

            <div class="table-responsive table-scroll">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="30"></th>
                            <th>Código</th>
                            <th>Medio</th>
                            <th>Ubicación</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaReportes">
                        <tr>
                            <td colspan="7" class="text-center">Cargando datos...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="reporteModal" class="overlay" hidden>
    <div class="modal-custom">
        <div class="modal-header">
            <h2 id="modalTitle">Nuevo Reporte</h2>
            <button type="button" class="modal-close" id="btnCloseModal">&times;</button>
        </div>

        <form id="reporteForm">
            <input type="hidden" id="cdgo_reporte" name="cdgo_reporte">
            <input type="hidden" id="modalMode" name="modalMode" value="nuevo">

            <h4 class="mb-3" style="text-align: center;">Información del Reporte</h4>

            <div class="row">
                <div class="col-md-6">
                    <label for="mdio_empleado" class="form-label">Método Empleado</label>
                    <input type="text" class="form-control" id="mdio_empleado" name="mdio_empleado" required>
                </div>

                <div class="col-md-6">
                    <label for="ubicacion" class="form-label">Ubicación</label>
                    <input type="text" class="form-control" id="ubicacion" name="ubicacion" required>
                </div>

                <div class="col-md-3">
                    <label for="fech_ini" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fech_ini" name="fech_ini" required>
                </div>

                <div class="col-md-3">
                    <label for="fech_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fech_fin" name="fech_fin" required>
                </div>

                <div class="col-md-6">
                    <label for="enlace" class="form-label">Enlace</label>
                    <input type="text" class="form-control" id="enlace" name="enlace">
                </div>

                <div class="col-md-12">
                    <label for="fundamento" class="form-label">Fundamento</label>
                    <textarea class="form-control" id="fundamento" name="fundamento" rows="3"></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label">Imágenes Relacionadas (máximo 6)</label>
                    <input type="file" class="form-control" id="imagenesReporte" name="imagenes[]" multiple accept="image/*">
                    <small class="text-muted">Formatos permitidos: JPG, JPEG, PNG. Máximo 6 imágenes.</small>
                </div>
            </div>

            <hr>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4>Cuadros Relacionados</h4>
                    <button type="button" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm d-flex align-items-center justify-content-center" id="btnAddCuadro" style="min-width: 140px;">
                        <i class="fas fa-plus me-2"></i> <span>Agregar Cuadro</span>
                    </button>
                </div>
                <div id="cuadrosContainer"></div>
            </div>

            <div class="d-flex justify-content-end gap-3 mt-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4 shadow-sm" id="btnCancelModal" style="min-width: 120px;">
                    <span>Cancelar</span>
                </button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" style="min-width: 120px;">
                    <span>Guardar</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.REPORTE_PUBLICIDAD = {
        routes: @json($routes),
        csrfToken: @json(csrf_token())
    };
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('mod_ti/reporte_publicidad/js/app.js') }}?v=1"></script>
</body>
</html>
