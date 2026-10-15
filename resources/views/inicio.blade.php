<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Municipal - Puente Piedra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-primary: #111;
            --color-secondary: #1f1f1f;
            --color-accent: #c62828;
            --color-white: #ffffff;
            --color-text: #333;
            --color-text-light: #666;
            --color-border: #e0e0e0;
            --color-hover: #f5f5f5;
            --sidebar-width-open: 300px;
            --sidebar-width-closed: 75px;
            --navbar-height: 70px;
            --transition-speed: 0.3s;
        }

        html, body {
            height: 100%;
            font-family: 'Poppins', 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: var(--color-text);
        }

        /* ==================== NAVBAR SUPERIOR ==================== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--navbar-height);
            background-color: var(--color-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            z-index: 1000;
        }

        .navbar-left {
            display: flex;
            align-items: center;
            width: var(--sidebar-width-open);
            justify-content: center;
        }

        .navbar-logo-wrapper {
            display: flex;
            align-items: center;
        }

        .navbar-logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--color-accent), #ff5252);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-white);
            font-size: 24px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(198, 40, 40, 0.3);
        }

        .navbar-title {
            color: var(--color-white);
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .navbar-center {
            flex: 1;
            display: flex;
            justify-content: center;
            gap: 50px;
            margin-left: 30px;
        }

        .navbar-info {
            display: flex;
            flex-direction: column;
            color: var(--color-white);
            font-size: 12px;
        }

        .navbar-info strong {
            font-weight: 600;
            color: var(--color-accent);
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .navbar-btn {
            background: transparent;
            border: 2px solid var(--color-white);
            color: var(--color-white);
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all var(--transition-speed) ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar-btn:hover {
            background-color: var(--color-white);
            color: var(--color-primary);
            transform: translateY(-2px);
        }

        .navbar-btn.logout {
            border-color: var(--color-accent);
        }

        .navbar-btn.logout:hover {
            background-color: var(--color-accent);
            color: var(--color-white);
        }

        .navbar-hamburger {
            display: none;
            background: transparent;
            border: none;
            color: var(--color-white);
            font-size: 24px;
            cursor: pointer;
            padding: 0 10px;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            transition: all var(--transition-speed) ease;
        }

        .navbar-hamburger:hover {
            background-color: rgba(198, 40, 40, 0.2);
            border-radius: 6px;
        }

        /* ==================== SIDEBAR ==================== */
        .sidebar {
            position: fixed;
            top: var(--navbar-height);
            left: 0;
            width: var(--sidebar-width-open);
            height: calc(100vh - var(--navbar-height));
            background-color: var(--color-secondary);
            overflow-x: hidden;
            overflow-y: auto;
            transition: width var(--transition-speed) ease;
            z-index: 900;
            padding: 0 0 28px 0;
        }

        .sidebar.collapsed {
            width: var(--sidebar-width-closed);
            padding-top: 12px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: var(--navbar-height);
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 850;
        }

        .sidebar-overlay.active {
            display: block;
        }

        .sidebar-toggle {
            position: fixed;
            left: 20px;
            top: calc(var(--navbar-height) + 20px);
            width: 45px;
            height: 45px;
            background-color: var(--color-secondary);
            border: none;
            color: var(--color-white);
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-speed) ease;
            z-index: 901;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .sidebar-toggle:hover {
            background-color: var(--color-accent);
            transform: scale(1.05);
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 12px;
        }

        .menu-item {
            margin-bottom: 4px;
        }

        .menu-link {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 12px;
            color: #b0b0b0;
            text-decoration: none;
            border-radius: 8px;
            transition: all var(--transition-speed) ease;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            position: relative;
        }

        .menu-link:hover {
            background-color: rgba(198, 40, 40, 0.1);
            color: var(--color-accent);
        }

        .menu-link.active {
            background-color: rgba(198, 40, 40, 0.2);
            color: var(--color-accent);
            border-left: 3px solid var(--color-accent);
            padding-left: 12px;
        }

        .menu-link i {
            width: 22px;
            flex-shrink: 0;
            text-align: center;
            font-size: 16px;
            line-height: 1.4;
        }

        .menu-text {
            flex: 1;
            min-width: 0;
            line-height: 1.35;
            white-space: normal;
            overflow: visible;
            word-break: break-word;
        }

        .sidebar.collapsed .menu-text {
            display: none;
        }

        .menu-arrow {
            margin-left: auto;
            margin-top: 3px;
            flex-shrink: 0;
            transition: transform var(--transition-speed) ease;
            font-size: 12px;
        }

        .menu-arrow.open {
            transform: rotate(180deg);
        }

        .sidebar.collapsed .menu-arrow {
            display: none;
        }

        .submenu {
            list-style: none;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.25s ease;
        }

        .submenu.open {
            max-height: 4000px;
        }

        .submenu-item {
            list-style: none;
        }

        .submenu-link {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 10px 8px 42px;
            color: #999;
            text-decoration: none;
            border-radius: 6px;
            transition: all var(--transition-speed) ease;
            cursor: pointer;
            font-size: 12px;
            font-weight: 400;
            line-height: 1.35;
            white-space: normal;
            word-break: break-word;
        }

        .submenu-link:hover {
            background-color: rgba(198, 40, 40, 0.08);
            color: var(--color-accent);
            padding-left: 55px;
        }

        .submenu-link.active {
            color: var(--color-accent);
            background-color: rgba(198, 40, 40, 0.1);
        }

        .sidebar.collapsed .submenu-link {
            display: none;
        }

        /* ==================== CONTENIDO PRINCIPAL ==================== */
        .main-content {
            margin-top: var(--navbar-height);
            margin-left: var(--sidebar-width-open);
            padding: 30px;
            transition: margin-left var(--transition-speed) ease;
            min-height: calc(100vh - var(--navbar-height));
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: var(--sidebar-width-closed);
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }

        .header-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--color-primary);
        }

        .header-subtitle {
            font-size: 14px;
            color: var(--color-text-light);
            margin-top: 5px;
        }

        .header-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-box {
            background: var(--color-white);
            border: 2px solid var(--color-border);
            padding: 10px 15px;
            border-radius: 8px;
            width: 280px;
            font-size: 14px;
            transition: all var(--transition-speed) ease;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(198, 40, 40, 0.1);
        }

        .filter-btn {
            background: var(--color-white);
            border: 2px solid var(--color-border);
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all var(--transition-speed) ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .filter-btn:hover {
            border-color: var(--color-accent);
            color: var(--color-accent);
        }

        /* ==================== TARJETAS ==================== */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .card {
            background: var(--color-white);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all var(--transition-speed) ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-accent), #ff5252);
            transform: scaleX(0);
            transition: transform var(--transition-speed) ease;
        }

        .card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
            transform: translateY(-5px);
            border-color: rgba(198, 40, 40, 0.1);
        }

        .card:hover::before {
            transform: scaleX(1);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(198, 40, 40, 0.1), rgba(255, 82, 82, 0.1));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--color-accent);
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 14px;
            color: var(--color-text-light);
            margin-bottom: 8px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .card-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 15px;
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 15px;
            border-top: 1px solid var(--color-border);
            font-size: 12px;
            color: var(--color-text-light);
        }

        .card-badge {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
        }

        .card-badge.warning {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
        }

        /* ==================== TABLA ==================== */
        .table-section {
            background: var(--color-white);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 25px;
            border-bottom: 2px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-primary);
        }

        .table-controls {
            display: flex;
            gap: 10px;
        }

        .table-btn {
            background: transparent;
            border: 1px solid var(--color-border);
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            font-size: 13px;
        }

        .table-btn:hover {
            border-color: var(--color-accent);
            color: var(--color-accent);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #f8f9fa;
        }

        th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--color-border);
        }

        td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--color-border);
            font-size: 13px;
        }

        tbody tr {
            transition: all var(--transition-speed) ease;
        }

        tbody tr:hover {
            background-color: #fafafa;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: rgba(76, 175, 80, 0.15);
            color: #2e7d32;
        }

        .badge-warning {
            background: rgba(255, 152, 0, 0.15);
            color: #f57c00;
        }

        .badge-danger {
            background: rgba(198, 40, 40, 0.15);
            color: #b71c1c;
        }

        .badge-info {
            background: rgba(33, 150, 243, 0.15);
            color: #1565c0;
        }

        .action-btn {
            background: transparent;
            border: 1px solid var(--color-border);
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all var(--transition-speed) ease;
            margin-right: 5px;
        }

        .action-btn:hover {
            border-color: var(--color-accent);
            color: var(--color-accent);
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 1024px) {
            .navbar {
                padding: 0 15px;
            }

            .navbar-left {
                width: auto;
                justify-content: flex-start;
            }

            .navbar-center {
                display: none !important;
            }

            .navbar-right {
                gap: 10px;
            }

            .navbar-hamburger {
                display: flex;
            }

            .navbar-btn {
                padding: 6px 12px;
                font-size: 12px;
                gap: 5px;
            }

            .sidebar {
                position: fixed;
                left: calc(-1 * var(--sidebar-width-open));
                width: var(--sidebar-width-open);
                box-shadow: 2px 0 15px rgba(0, 0, 0, 0.2);
                transition: left var(--transition-speed) ease;
            }

            .sidebar.mobile-open {
                left: 0;
            }

            .sidebar.collapsed {
                left: -75px;
            }

            .sidebar-toggle {
                display: none;
            }

            .sidebar-overlay {
                display: none;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .header-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .header-right {
                width: 100%;
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .cards-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .card-value {
                font-size: 24px;
            }

            .header-title {
                font-size: 22px;
            }

            .navbar-title {
                font-size: 13px;
            }

            .navbar-logo {
                width: 45px;
                height: 45px;
                font-size: 20px;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px 8px;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                padding: 0 10px;
            }

            .navbar-left {
                gap: 8px;
                margin-left: 0;
            }

            .navbar-title {
                font-size: 11px;
            }

            .navbar-logo {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .navbar-hamburger {
                width: 45px;
                font-size: 20px;
            }

            .navbar-btn {
                padding: 5px 10px;
                font-size: 10px;
            }

            .header-title {
                font-size: 18px;
            }

            .card-value {
                font-size: 18px;
            }

            .table-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .table-controls {
                flex-direction: column;
                width: 100%;
            }

            .table-btn {
                width: 100%;
                font-size: 11px;
            }

            .main-content {
                padding: 15px;
            }

            .card-title {
                font-size: 12px;
            }
        }

        @media (max-width: 360px) {
            .navbar {
                padding: 0 8px;
            }

            .navbar-title {
                display: none;
            }

            .navbar-logo {
                width: 38px;
                height: 38px;
            }

            .navbar-btn {
                display: none;
            }

            .navbar-right {
                gap: 5px;
            }

            .main-content {
                padding: 12px;
            }

            th, td {
                padding: 8px 6px;
                font-size: 11
                font-size: 20px;
            }

            .table-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px 12px;
            }
        }

        /* ==================== SCROLLBAR ==================== */
        .sidebar::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.04);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(198, 40, 40, 0.55);
            border-radius: 4px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(198, 40, 40, 0.8);
        }

        /* Contenido embebido en el panel principal (Full-Bleed sin márgenes ni scroll exterior) */
        body.has-module {
            overflow: hidden !important;
        }

        .main-content.module-active {
            padding: 0 !important;
            height: calc(100vh - var(--navbar-height)) !important;
            min-height: calc(100vh - var(--navbar-height)) !important;
            overflow: hidden !important;
        }

        .content-frame {
            display: none;
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 0;
            box-shadow: none;
            background: var(--color-white);
        }

        .content-frame.visible {
            display: block;
            width: 100%;
            height: 100%;
        }



        .dashboard-home.hidden {
            display: none;
        }

        /* ==================== BUSCADOR SIDEBAR ==================== */
        /* Sidebar Search */
        .sidebar-search-box {
            position: sticky;
            top: 0;
            z-index: 20;
            background-color: var(--color-secondary);
            padding: 12px 14px 10px 14px;
            margin-bottom: 6px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .sidebar.collapsed .sidebar-search-box {
            display: none;
        }

        .sidebar-search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            padding: 0 10px;
            transition: all 0.2s ease;
        }

        .sidebar-search-input-wrapper:focus-within {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--color-accent);
            box-shadow: 0 0 0 2px rgba(198, 40, 40, 0.25);
        }

        .sidebar-search-icon {
            color: #888;
            font-size: 12px;
            margin-right: 8px;
        }

        #sidebarSearchInput {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: #fff;
            font-size: 12px;
            padding: 8px 0;
            font-family: inherit;
        }

        #sidebarSearchInput::placeholder {
            color: #777;
            font-size: 11.5px;
        }

        .sidebar-search-clear {
            background: transparent;
            border: none;
            color: #888;
            cursor: pointer;
            padding: 4px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-search-clear:hover {
            color: #fff;
        }

        .sidebar-no-results {
            padding: 16px 14px;
            text-align: center;
            color: #777;
            font-size: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .sidebar-no-results i {
            font-size: 18px;
            color: #555;
        }

        @media (max-width: 1024px) {
            .navbar-search-wrapper {
                display: none;
            }
        }
    </style>
</head>
<body>
    
    <nav class="navbar">
        <div class="navbar-left">
            <div class="navbar-logo-wrapper">
                <img src="{{ asset('images/imagen2.png') }}" alt="Logo" style="width: 50px; height: 50px;">
            </div>
        </div>

        <div class="navbar-center">
            <div class="navbar-info">
                <span>ÁREA</span>
                <strong>{{ $area }}</strong>
            </div>
            <div class="navbar-info">
                <span>CARGO</span>
                <strong>{{ $cargo }}</strong>
            </div>
            <div class="navbar-info">
                <span>USUARIO</span>
                <strong>{{ $usuario }}</strong>
            </div>
        </div>

        <div class="navbar-right">

            <button class="navbar-btn" id="navHomeBtn">
                <i class="fas fa-home"></i>
                Inicio
            </button>
            <button class="navbar-btn logout" id="navLogoutBtn" type="button">
                <i class="fas fa-sign-out-alt"></i>
                Salir
            </button>
            <button class="navbar-hamburger" id="navbarHamburger" title="Menú">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <form id="logoutForm" method="POST" action="{{ route('logout') }}" style="display: none;">
        @csrf
    </form>

    <button class="sidebar-toggle" id="sidebarToggle" title="Colapsar menú" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <!-- Buscador del Menú Lateral -->
        <div class="sidebar-search-box">
            <div class="sidebar-search-input-wrapper">
                <i class="fas fa-search sidebar-search-icon"></i>
                <input type="text" id="sidebarSearchInput" placeholder="Buscar módulo..." autocomplete="off">
                <button type="button" id="sidebarSearchClear" class="sidebar-search-clear" style="display: none;" title="Limpiar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div id="sidebarNoResults" class="sidebar-no-results" style="display: none;">
            <i class="fas fa-search-minus"></i>
            <span>No se encontraron módulos</span>
        </div>

        <ul class="sidebar-menu" id="sidebarMenuList">
            <li class="menu-item">
                <a class="menu-link active" href="{{ route('inicio') }}" data-page="inicio" data-home-link="1">
                    <i class="fas fa-home"></i>
                    <span class="menu-text">Inicio</span>
                </a>
            </li>

            @foreach ($menuItems as $menuItem)
                <li class="menu-item">
                    @if (!empty($menuItem['children']))
                        <a class="menu-link" href="#" onclick="toggleSubmenu(event)">
                            <i class="fas fa-folder"></i>
                            <span class="menu-text">{{ $menuItem['label'] }}</span>
                            <i class="fas fa-chevron-down menu-arrow"></i>
                        </a>
                        <ul class="submenu">
                            @foreach ($menuItem['children'] as $subItem)
                                <li class="submenu-item">
                                    <a class="submenu-link" href="{{ $subItem['url'] }}" @if($subItem['popup']) target="_blank" rel="noopener" @endif>
                                        
                                        <span>{{ $subItem['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <a class="menu-link" href="{{ $menuItem['url'] }}" @if($menuItem['popup']) target="_blank" rel="noopener" @endif>
                            <i class="fas fa-link"></i>
                            <span class="menu-text">{{ $menuItem['label'] }}</span>
                        </a>
                    @endif
                </li>
            @endforeach

            <li style="margin: 20px 0; border-top: 1px solid rgba(255,255,255,0.1);"></li>

            <li class="menu-item">
                <a class="menu-link" href="#" onclick="logout(event)">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Salir</span>
                </a>
            </li>
        </ul>
    </aside>

    
    <main class="main-content">
        <div id="dashboardHome" class="dashboard-home">
        <!-- Header -->
        <div class="header-section">
            <div>
                <h1 class="header-title">Bienvenido al Sistema Municipal</h1>
                <p class="header-subtitle">Panel de control y gestión de trámites municipales</p>
            </div>
            <div class="header-right">
                <input type="text" class="search-box" placeholder="Buscar...">
                <button class="filter-btn">
                    <i class="fas fa-filter"></i>
                    Filtrar
                </button>
            </div>
        </div>

        <!-- Tarjetas de Estadísticas -->
        <div class="cards-container">
            <!-- Total Trámites -->
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="card-title">Total Trámites</div>
                <div class="card-value">1,234</div>
                <div class="card-footer">
                    <span>Este mes</span>
                    <span class="card-badge">
                        <i class="fas fa-arrow-up"></i> 12%
                    </span>
                </div>
            </div>

            <!-- Usuarios Activos -->
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-title">Usuarios Activos</div>
                <div class="card-value">245</div>
                <div class="card-footer">
                    <span>Conectados</span>
                    <span class="card-badge">
                        <i class="fas fa-check-circle"></i> Activo
                    </span>
                </div>
            </div>

            <!-- Proveedores -->
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="card-title">Proveedores</div>
                <div class="card-value">89</div>
                <div class="card-footer">
                    <span>Registrados</span>
                    <span class="card-badge warning">
                        <i class="fas fa-exclamation-circle"></i> 5 Nuevos
                    </span>
                </div>
            </div>

            <!-- Reportes Pendientes -->
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-title">Reportes Pendientes</div>
                <div class="card-value">18</div>
                <div class="card-footer">
                    <span>Urgentes</span>
                    <span class="card-badge warning">
                        <i class="fas fa-clock"></i> Pendiente
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabla de Últimos Movimientos -->
        <div class="table-section">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-history" style="margin-right: 10px; color: var(--color-accent);"></i>
                    Últimos Movimientos
                </div>
                <div class="table-controls">
                    <button class="table-btn">
                        <i class="fas fa-download"></i>
                        Exportar
                    </button>
                    <button class="table-btn">
                        <i class="fas fa-print"></i>
                        Imprimir
                    </button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>#TRM-001</td>
                        <td>Licencia de funcionamiento</td>
                        <td>Juan Pérez</td>
                        <td>Solicitud</td>
                        <td><span class="badge badge-success">Aprobado</span></td>
                        <td>25/04/2026</td>
                        <td>
                            <button class="action-btn" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>#TRM-002</td>
                        <td>Autorización comercial</td>
                        <td>María García</td>
                        <td>Autorización</td>
                        <td><span class="badge badge-warning">En Revisión</span></td>
                        <td>24/04/2026</td>
                        <td>
                            <button class="action-btn" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>#TRM-003</td>
                        <td>Permiso de construcción</td>
                        <td>Carlos López</td>
                        <td>Permiso</td>
                        <td><span class="badge badge-danger">Rechazado</span></td>
                        <td>23/04/2026</td>
                        <td>
                            <button class="action-btn" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>#TRM-004</td>
                        <td>Documento de identificación</td>
                        <td>Rosa Martínez</td>
                        <td>Documento</td>
                        <td><span class="badge badge-info">Pendiente</span></td>
                        <td>22/04/2026</td>
                        <td>
                            <button class="action-btn" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>#TRM-005</td>
                        <td>Certificado municipal</td>
                        <td>Pedro Rodríguez</td>
                        <td>Certificado</td>
                        <td><span class="badge badge-success">Aprobado</span></td>
                        <td>21/04/2026</td>
                        <td>
                            <button class="action-btn" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>

        <iframe id="contentFrame" class="content-frame" name="contentFrame" title="Contenido del módulo"></iframe>
    </main>

    
    <script>
        // Elementos del DOM
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const menuLinks = document.querySelectorAll('.menu-link');
        const submenuLinks = document.querySelectorAll('.submenu-link');
        const dashboardHome = document.getElementById('dashboardHome');
        const contentFrame = document.getElementById('contentFrame');
        const navLogoutBtn = document.querySelector('.navbar-btn.logout');
        const navHomeBtn = document.querySelector('.navbar-btn:not(.logout)');

        function showDashboardHome() {
            dashboardHome.classList.remove('hidden');
            contentFrame.classList.remove('visible');

            contentFrame.src = 'about:blank';
            const mc = document.querySelector('.main-content');
            if (mc) mc.classList.remove('module-active');
            document.body.classList.remove('has-module');
        }

        function loadContentInPanel(url) {
            if (!url || url === '#' || url === 'about:blank') {
                return;
            }
            dashboardHome.classList.add('hidden');
            contentFrame.classList.add('visible');
            const mc = document.querySelector('.main-content');
            if (mc) mc.classList.add('module-active');
            document.body.classList.add('has-module');
            contentFrame.src = url;
        }



        function setActiveMenu(anchor) {
            menuLinks.forEach(l => l.classList.remove('active'));
            submenuLinks.forEach(l => l.classList.remove('active'));
            if (anchor) {
                anchor.classList.add('active');
                const parentSubmenu = anchor.closest('.submenu');
                if (parentSubmenu) {
                    parentSubmenu.style.maxHeight = '';
                    parentSubmenu.classList.add('open');
                    const parentMenuLink = parentSubmenu.previousElementSibling;
                    if (parentMenuLink) {
                        const arrow = parentMenuLink.querySelector('.menu-arrow');
                        if (arrow) arrow.classList.add('open');
                    }
                }
            }
        }

        window.activarModuloPorUrl = function(targetUrl) {
            if (!targetUrl) return false;
            const stripExt = (p) => (p || '').replace(/\.(php|blade\.php|html)$/i, '');
            const normalize = (u) => stripExt((u || '').replace(/^https?:\/\/[^\/]+/, '').split('?')[0].replace(/\/+$/, ''));
            const targetNorm = normalize(targetUrl);

            for (const link of submenuLinks) {
                const linkHref = link.getAttribute('href') || link.href || '';
                const linkNorm = normalize(linkHref);
                if (linkNorm && (linkNorm === targetNorm || linkNorm.endsWith(targetNorm) || targetNorm.endsWith(linkNorm))) {
                    loadContentInPanel(link.href || linkHref);
                    setActiveMenu(link);
                    return true;
                }
            }
            for (const link of menuLinks) {
                const linkHref = link.getAttribute('href') || link.href || '';
                const linkNorm = normalize(linkHref);
                if (linkNorm && (linkNorm === targetNorm || linkNorm.endsWith(targetNorm) || targetNorm.endsWith(linkNorm))) {
                    loadContentInPanel(link.href || linkHref);
                    setActiveMenu(link);
                    return true;
                }
            }
            // Fallback directo si la ruta no tiene un link exacto en el menú
            try {
                loadContentInPanel(targetUrl);
                return true;
            } catch (e) {
                return false;
            }
        };

        window.addEventListener('message', function(event) {
            if (event.data && event.data.action === 'activar_modulo' && event.data.url) {
                window.activarModuloPorUrl(event.data.url);
            }
        });

        // Toggle Sidebar
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            
            // En móvil, también toggle la clase mobile-open
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
            }
        });

        // Toggle Submenu
        function toggleSubmenu(event) {
            event.preventDefault();
            
            const menuLink = event.currentTarget;
            const submenu = menuLink.nextElementSibling;
            const arrow = menuLink.querySelector('.menu-arrow');
            
            if (submenu && submenu.classList.contains('submenu')) {
                submenu.style.maxHeight = '';
                const isOpen = submenu.classList.toggle('open');
                if (arrow) {
                    arrow.classList.toggle('open', isOpen);
                }
            }
        }

        // Enlaces del menú lateral: módulos en el panel derecho (iframe), no página completa
        menuLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.querySelector('.menu-arrow')) {
                    return;
                }
                if (this.getAttribute('target') === '_blank') {
                    return;
                }
                const href = this.getAttribute('href');
                if (!href || href === '#') {
                    e.preventDefault();
                    return;
                }
                if (this.dataset.homeLink === '1') {
                    e.preventDefault();
                    showDashboardHome();
                    setActiveMenu(this);
                    return;
                }
                e.preventDefault();
                loadContentInPanel(href);
                setActiveMenu(this);
            });
        });

        submenuLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('target') === '_blank') {
                    return;
                }
                e.preventDefault();
                loadContentInPanel(this.href);
                setActiveMenu(this);
            });
        });

        const logoutForm = document.getElementById('logoutForm');
        const seleccionarCargoUrl = @json(route('seleccionar.cargo'));

        // Logout
        function logout(event) {
            event.preventDefault();
            if (confirm('¿Está seguro de que desea salir?')) {
                logoutForm.submit();
            }
        }

        // Logout desde navbar
        navLogoutBtn.addEventListener('click', function() {
            logout(new Event('click'));
        });

        // Home desde navbar
        navHomeBtn.addEventListener('click', function() {
            window.location.href = seleccionarCargoUrl;
        });

        // Responsive - Cerrar sidebar en móvil al hacer click en un item
        menuLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768 && !this.querySelector('.menu-arrow')) {
                    sidebar.classList.remove('mobile-open');
                }
            });
        });

        // Manejo de resize para responsive
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
            }
        });

        // Animación de carga
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar animación de fade-in a las tarjetas
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.animation = `fadeIn 0.5s ease forwards ${index * 0.1}s`;
            });

            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `;
            document.head.appendChild(style);
        });

        // Cerrar sidebar al hacer click fuera en móvil
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && !navbarHamburger.contains(e.target)) {
                    sidebar.classList.remove('mobile-open');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                }
            }
        });

        // Búsqueda (ejemplo básico)
        const searchBox = document.querySelector('.search-box');
        searchBox.addEventListener('keyup', function() {
            console.log('Buscando:', this.value);
            // Aquí iría la lógica de búsqueda
        });

        // Filtro (ejemplo)
        const filterBtn = document.querySelector('.filter-btn');
        filterBtn.addEventListener('click', function() {
            alert('Abrir panel de filtros');
            // Aquí iría la lógica de filtros
        });

        // Tabla - Exportar
        document.querySelectorAll('.table-btn')[0].addEventListener('click', function() {
            alert('Exportando datos...');
            // Aquí iría la lógica de exportación
        });

        // Tabla - Imprimir
        document.querySelectorAll('.table-btn')[1].addEventListener('click', function() {
            window.print();
        });

        // ========== CONTROL RESPONSIVO ==========
        const navbarHamburger = document.getElementById('navbarHamburger');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const logoutBtn = document.querySelector('.navbar-btn.logout');
        let isMobile = window.innerWidth <= 1024;

        // Actualizar estado mobile en resize
        window.addEventListener('resize', () => {
            isMobile = window.innerWidth <= 1024;
            if (window.innerWidth > 1024) {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
            }
        });

        // Hamburguesa navbar (mobile)
        navbarHamburger.addEventListener('click', (e) => {
            e.preventDefault();
            if (isMobile) {
                sidebar.classList.toggle('mobile-open');
                sidebarOverlay.classList.toggle('active');
            }
        });

        // Overlay - Cerrar menú
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        });

        // Cerrar menú al clickear items (mobile)
        const mobileMenuLinks = document.querySelectorAll('.menu-link, .submenu-link');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                if (isMobile && !link.hasAttribute('onclick')) {
                    setTimeout(() => {
                        sidebar.classList.remove('mobile-open');
                        sidebarOverlay.classList.remove('active');
                    }, 300);
                }
            });
        });

        // Logout desde navbar (ya registrado arriba)

        // ========== INTERCEPTOR GLOBAL DE FETCH (PROXY 401) ==========
        // Protege todas las peticiones asíncronas del sistema contra caducidad de sesión
        const { fetch: originalFetch } = window;
        window.fetch = async (...args) => {
            const response = await originalFetch(...args);
            
            // Si Laravel detecta que la sesión expiró (401 Unauthorized)
            if (response.status === 401) {
                if (typeof AppNotif !== 'undefined' && AppNotif.error) {
                    AppNotif.error("Su sesión ha expirado por inactividad. Redirigiendo...");
                } else {
                    alert("Su sesión ha expirado por inactividad. Redirigiendo...");
                }
                
                setTimeout(() => {
                    window.location.href = '/login';
                }, 2000);
                
                // Retornamos una Promesa pendiente para evitar que la vista intente procesar el error
                return new Promise(() => {}); 
            }
            
            return response;
        };

        // ==================== BUSCADOR DEL MENÚ LATERAL ====================
        const sidebarSearchInput = document.getElementById('sidebarSearchInput');
        const sidebarSearchClear = document.getElementById('sidebarSearchClear');
        const sidebarNoResults = document.getElementById('sidebarNoResults');
        const sidebarMenuList = document.getElementById('sidebarMenuList');

        const menuItemsElements = sidebarMenuList ? sidebarMenuList.querySelectorAll(':scope > .menu-item') : [];

        function normalizeSearchText(text) {
            return (text || '')
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
        }

        function filterMenu(query) {
            const cleanQuery = normalizeSearchText(query);
            const isSearching = cleanQuery.length > 0;

            if (sidebarSearchClear) sidebarSearchClear.style.display = isSearching ? 'flex' : 'none';

            if (!isSearching) {
                menuItemsElements.forEach(item => {
                    item.style.display = '';
                    const submenu = item.querySelector('.submenu');
                    const arrow = item.querySelector('.menu-arrow');
                    if (submenu) {
                        submenu.classList.remove('open');
                        submenu.style.maxHeight = '';
                    }
                    if (arrow) arrow.classList.remove('open');
                    item.querySelectorAll('.submenu-item').forEach(sub => sub.style.display = '');
                });
                if (sidebarNoResults) sidebarNoResults.style.display = 'none';
                return;
            }

            let totalMatches = 0;

            menuItemsElements.forEach(item => {
                const isHome = item.dataset.homeLink === '1' || item.querySelector('[data-home-link="1"]');
                const isExit = item.querySelector('.fa-sign-out-alt');
                if (isExit) {
                    item.style.display = 'none';
                    return;
                }

                const mainLink = item.querySelector(':scope > .menu-link');
                const mainText = mainLink && mainLink.querySelector('.menu-text') ? mainLink.querySelector('.menu-text').textContent.trim() : '';
                const mainNorm = normalizeSearchText(mainText);
                const subItems = item.querySelectorAll('.submenu-item');

                if (subItems.length > 0) {
                    let hasMatchingChild = false;
                    const submenu = item.querySelector('.submenu');
                    const arrow = item.querySelector('.menu-arrow');

                    subItems.forEach(sub => {
                        const subLink = sub.querySelector('.submenu-link');
                        const subText = subLink ? subLink.textContent.trim() : '';
                        const subNorm = normalizeSearchText(subText);

                        const matches = subNorm.includes(cleanQuery) || mainNorm.includes(cleanQuery);
                        if (matches) {
                            sub.style.display = '';
                            hasMatchingChild = true;
                            totalMatches++;
                        } else {
                            sub.style.display = 'none';
                        }
                    });

                    if (hasMatchingChild) {
                        item.style.display = '';
                        if (submenu) {
                            submenu.style.maxHeight = '';
                            submenu.classList.add('open');
                        }
                        if (arrow) arrow.classList.add('open');
                    } else {
                        item.style.display = 'none';
                    }
                } else {
                    if (mainNorm.includes(cleanQuery)) {
                        item.style.display = '';
                        totalMatches++;
                    } else {
                        item.style.display = 'none';
                    }
                }
            });

            if (sidebarNoResults) {
                sidebarNoResults.style.display = totalMatches === 0 ? 'flex' : 'none';
            }
        }

        if (sidebarSearchInput) {
            sidebarSearchInput.addEventListener('input', function(e) {
                const val = e.target.value;
                if (val.trim().length > 0) {
                    const sidebarEl = document.getElementById('sidebar');
                    if (sidebarEl) sidebarEl.scrollTop = 0;
                }
                filterMenu(val);
            });
            sidebarSearchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    filterMenu('');
                }
            });
        }
        if (sidebarSearchClear) {
            sidebarSearchClear.addEventListener('click', function() {
                if (sidebarSearchInput) {
                    sidebarSearchInput.value = '';
                    sidebarSearchInput.focus();
                }
                filterMenu('');
            });
        }

        document.addEventListener('keydown', function(e) {
            if ((e.key === '/' || (e.ctrlKey && e.key.toLowerCase() === 'k')) && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                if (sidebarSearchInput) {
                    e.preventDefault();
                    sidebarSearchInput.focus();
                    sidebarSearchInput.select();
                }
            }
        });
    </script>
</body>
</html>