<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Tickets</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_ti/helpdesk/tickets/css/app.css') }}?v=1">
</head>
<body>
    <div id="closingScreen">
        <div class="stars" id="starsContainer"></div>
        <div class="closing-header">
            <div class="closing-title">🏆 RESULTADOS DEL DÍA</div>
            <div class="closing-subtitle">OFICINA DE TECNOLOGÍAS DE INFORMACIÓN</div>
            <div class="closing-date" id="closingDate"></div>
        </div>
        <div id="closingPodium"></div>
    </div>

    <div class="container">
        <div class="header">
            <h1>🎫 SISTEMA DE GESTIÓN DE TICKETS</h1>
            <div class="current-time" id="currentTime"></div>
        </div>

        <div class="main-content">
            <div class="tickets-board">
                <div class="requerimiento-section">
                    <div class="status-column status-requerimiento">
                        <div class="status-header">
                            📝 REQUERIMIENTO
                            <span class="count-badge" id="count-1">0</span>
                        </div>
                        <div class="tickets-container" id="tickets-1"></div>
                    </div>
                </div>
            </div>

            <div class="staff-section">
                <div class="staff-title">👥 ESTADO DEL PERSONAL</div>
                <div class="staff-grid" id="staffGrid"></div>
            </div>
        </div>
    </div>

    <script>
        window.TICKETS_PANTALLA = {
            routes: @json($routes),
            audioAlerta: @json($audioAlerta)
        };
    </script>
    <script src="{{ asset('mod_ti/helpdesk/tickets/js/app.js') }}?v=1"></script>
</body>
</html>
