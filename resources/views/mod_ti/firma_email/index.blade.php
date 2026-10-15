<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generador de Firmas de Correo</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_ti/firma_email/css/app.css') }}?v=1">
    @include('components.sigtram.module-head', ['screenKey' => 'mod_ti.firma_email.index'])
</head>
<body data-sigtram-screen="mod_ti.firma_email.index" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_ti.firma_email.index'])
    <div id="firma-app">
        <div class="card">
            <h1>Generador de Firmas de Correo</h1>
            <p class="text-muted">Crea firmas institucionales oficiales de forma individual o masiva.</p>

            <div class="tabs">
                <button type="button" class="tab-btn active" onclick="switchTab('individual')">Generación Individual</button>
                <button type="button" class="tab-btn" onclick="switchTab('masivo')">Generación Masiva (CSV)</button>
            </div>

            <div id="individual" class="tab-content active">
                <div class="search-section">
                    <div class="form-group">
                        <label for="search_dni" style="margin-bottom: 8px; display: block;">Ingrese DNI para buscar trabajador</label>
                        <div class="search-controls">
                            <input type="text" id="search_dni" maxlength="8" inputmode="numeric" autocomplete="off"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   onkeypress="if(event.key === 'Enter') buscarPorDni()">
                            <button type="button" class="btn btn-primary" onclick="buscarPorDni()">Buscar</button>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group col-1-3">
                        <label for="titulo">Grado (Lic, Ing, etc.)</label>
                        <input type="text" id="titulo" style="text-transform: capitalize;" oninput="updatePreview()" onblur="this.value = toTitleCase(this.value); updatePreview();">
                    </div>
                    <div class="form-group col-2-3">
                        <label for="nombre">Nombre Completo</label>
                        <input type="text" id="nombre" style="text-transform: capitalize;" oninput="updatePreview()" onblur="this.value = toTitleCase(this.value); updatePreview();">
                    </div>
                    <div class="form-group">
                        <label for="cargo">Cargo / Oficina</label>
                        <input type="text" id="cargo" style="text-transform: capitalize;" oninput="updatePreview()" onblur="this.value = toTitleCase(this.value); updatePreview();">
                    </div>
                    <div class="form-group">
                        <label for="correo">Correo Institucional</label>
                        <input type="email" id="correo" oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label for="celular">Celular de contacto</label>
                        <input type="text" id="celular" oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label for="anexo">Anexo (Opcional)</label>
                        <input type="text" id="anexo" oninput="updatePreview()">
                    </div>
                </div>

                <div class="action-bar">
                    <button type="button" class="btn btn-primary" onclick="guardarFirma()">Descargar firma</button>
                </div>
            </div>

            <div id="masivo" class="tab-content">
                <div class="card" style="border: 1px solid var(--border); box-shadow: none;">
                    <h3>Instrucciones</h3>
                    <ol>
                        <li>Descarga la plantilla CSV haciendo clic en el botón de abajo.</li>
                        <li>Llena los datos respetando las columnas.</li>
                        <li>Sube el archivo procesado para generar todas las firmas en un archivo ZIP.</li>
                    </ol>
                    <a href="{{ $routes['plantilla'] }}" class="btn btn-secondary">Descargar plantilla CSV</a>
                </div>

                <div class="massive-dropzone" onclick="document.getElementById('csv_file').click()">
                    <p><strong>Haz clic aquí o arrastra tu archivo CSV corregido</strong></p>
                    <input type="file" id="csv_file" style="display: none;" accept=".csv" onchange="procesarMasivo(this)">
                </div>

                <div id="massive-results" style="display: none; margin-top: 20px;">
                    <h3>Resultados del Procesamiento</h3>
                    <div id="zip-download-container" style="margin-bottom: 15px;"></div>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th>Mensaje</th>
                            </tr>
                        </thead>
                        <tbody id="results-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="preview-container">
            <h3>Previsualización Real</h3>
            <canvas id="firma-canvas" width="484" height="153"></canvas>
            <p class="text-muted" style="font-size: 0.8rem;">* La vista previa utiliza Canvas. El resultado final será un archivo PNG de alta calidad.</p>
        </div>
    </div>

    <script>
        window.FIRMA_EMAIL = {
            routes: @json($routes),
            assetBase: @json($assetBase),
            csrfToken: @json(csrf_token())
        };
    </script>
    <script src="{{ asset('mod_ti/firma_email/js/api.js') }}?v=1"></script>
    <script src="{{ asset('mod_ti/firma_email/js/ui.js') }}?v=1"></script>
    <script src="{{ asset('mod_ti/firma_email/js/app.js') }}?v=1"></script>
</body>
</html>
