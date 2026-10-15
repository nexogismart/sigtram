<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte de Publicidad Estatal 2026 - ANEXO 2</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('mod_ti/reporte_publicidad_2026/css/app.css') }}?v=2">
    @include('components.sigtram.module-head', ['screenKey' => 'mod_ti.reporte_publicidad_2026.index'])
</head>
<body data-sigtram-screen="mod_ti.reporte_publicidad_2026.index" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_ti.reporte_publicidad_2026.index'])
<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Reporte de Publicidad Estatal 2026 - ANEXO 2</h1>
            <button id="btnNuevoReporte" type="button" class="btn btn-primary rounded-pill shadow-sm px-4 py-2 d-flex align-items-center justify-content-center" style="min-width: 160px;">
                <i class="fas fa-plus me-2"></i> <span>Registrar Nuevo</span>
            </button>
        </div>

        <div class="card-body">
            <div class="table-responsive table-scroll">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="50">Código</th>
                            <th width="100">Medio</th>
                            <th width="250">Ubicación</th>
                            <th width="90">Fecha Inicio</th>
                            <th width="90">Fecha Fin</th>
                            <th class="text-center" width="150">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaReportes">
                        <tr>
                            <td colspan="6" class="text-center">Cargando datos...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav aria-label="Paginación de reportes">
                <ul class="pagination justify-content-center mt-3" id="paginacion"></ul>
            </nav>
        </div>
    </div>
</div>

<div id="reporteModal" class="overlay" hidden>
    <div class="modal-custom">
        <div class="modal-header">
            <h2 id="modalTitle">Registrar Nuevo Reporte</h2>
            <button type="button" class="modal-close" id="btnCloseModal">&times;</button>
        </div>
        <div id="modalContent"></div>
    </div>
</div>

<script>
    window.REPORTE_PUBLICIDAD_2026 = {
        routes: @json($routes),
        csrfToken: @json(csrf_token())
    };
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('mod_ti/reporte_publicidad_2026/js/app.js') }}?v=1"></script>
</body>
</html>
