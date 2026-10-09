<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sigtram Intelligence | Advanced Report</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

    <script type="text/javascript" src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Modern Datepicker (Flatpickr) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

    <!-- Analytics: Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>

    <style>
        :root {
            --brand-primary: #334155;
            --brand-secondary: #0f172a;
            --brand-gradient: linear-gradient(135deg, #334155 0%, #0f172a 100%);
            --bg-main: #fcfcfd;
            --card-border: rgba(226, 232, 240, 0.6);
            --text-heading: #0f172a;
            --text-body: #475569;
            --text-muted: #94a3b8;
            --success: #475569;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0ea5e9;
            --glass: rgba(255, 255, 255, 0.7);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Figtree', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-body);
            padding: 2.5rem;
            min-height: 100vh;
            background-image: 
                radial-gradient(at 100% 0%, rgba(99, 102, 241, 0.05) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(168, 85, 247, 0.05) 0px, transparent 50%);
        }

        .wrapper {
            max-width: 100%;
            padding: 0 2rem;
            margin: 0 auto;
        }

        /* Top Navigation / Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2rem;
            animation: fadeIn 0.8s ease-out;
        }

        .title-section h1 {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 2.25rem;
            letter-spacing: -0.04em;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-heading);
        }

        .title-section h1 span {
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* View Transitions */
        .view-container {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .view-hidden {
            display: none !important;
            opacity: 0;
            transform: translateY(20px);
        }

        .view-active {
            display: block !important;
            opacity: 1;
            transform: translateY(0);
        }

        .kpi-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .kpi-row .kpi-card {
            flex: 0 1 220px;
        }

        /* Advanced Reports Module Styles */
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .reports-grid { grid-template-columns: 1fr; }
            .kpi-row { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 768px) {
            .reports-grid { grid-template-columns: 1fr; }
            .report-card.span-2 { grid-column: span 1; }
            .kpi-row { grid-template-columns: 1fr; }
        }

        .report-card {
            background: white;
            border-radius: 1.5rem;
            padding: 1.25rem;
            border: 1px solid var(--card-border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
        }

        .report-card.span-2 { grid-column: span 2; }
        .report-card.full { grid-column: 1 / -1; }

        .analytics-split {
            display: flex;
            gap: 2rem;
            height: 100%;
            align-items: center;
        }

        .chart-main {
            flex: 0 0 60%;
            position: relative;
            min-height: 280px;
        }

        .chart-side-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 1.25rem;
            border: 1px solid #f1f5f9;
        }

        .donut-center-label {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            pointer-events: none;
        }

        .donut-center-label .total-num {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--text-heading);
            font-family: 'Outfit';
            line-height: 1;
        }

        .donut-center-label .total-lbl {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .side-metric-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .side-metric-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .side-metric-label {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-heading);
        }

        .side-metric-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .side-metric-value {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--text-heading);
        }

        .side-progress-bg {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .side-progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 1s ease-out;
        }

        .trend-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 800;
        }

        .trend-pill.up { background: #e2e8f0; color: #15803d; }
        .trend-pill.down { background: #fee2e2; color: #b91c1c; }

        .kpi-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem;
            border: 1px solid var(--card-border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            cursor: default;
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.08);
            border-color: var(--brand-primary);
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .kpi-card:hover .kpi-icon {
            transform: scale(1.1);
        }

        .kpi-card.blue .kpi-icon { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .kpi-card.purple .kpi-icon { background: rgba(168, 85, 247, 0.1); color: #64748b; }
        .kpi-card.green .kpi-icon { background: rgba(71, 85, 105, 0.1); color: #475569; }
        .kpi-card.orange .kpi-icon { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

        .kpi-content { flex: 1; }
        .kpi-content h4 { 
            font-size: 0.65rem; 
            font-weight: 700; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            margin-bottom: 0.15rem; 
        }
        .kpi-content .stat-value { 
            font-size: 1.6rem; 
            font-weight: 800; 
            color: var(--text-heading); 
            font-family: 'Outfit'; 
            line-height: 1; 
        }
        .kpi-content p { 
            font-size: 0.65rem; 
            color: var(--text-muted); 
            font-weight: 600; 
            margin-top: 0.15rem; 
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .report-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .chart-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-chart-toggle {
            padding: 0.35rem 0.6rem;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            transition: all 0.2s;
        }

        .btn-chart-toggle.active {
            background: var(--brand-primary);
            color: white;
            border-color: var(--brand-primary);
        }

        .report-table-container {
            width: 100%;
            overflow-x: auto;
            border-radius: 1rem;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .report-table th {
            text-align: left;
            padding: 1rem;
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #f1f5f9;
        }

        .report-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-heading);
            font-weight: 600;
        }

        .report-table tr:last-child td { border-bottom: none; }
        .report-table tr:hover td { background: #fcfcfd; }

        .efficiency-pill {
            padding: 0.25rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 800;
        }

        .skeleton {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }

        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .btn-view-toggle {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 0.6rem 1.25rem;
            border-radius: 0.85rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-heading);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .btn-view-toggle:hover { background: white; border-color: var(--brand-primary); color: var(--brand-primary); transform: translateY(-2px); }
        .btn-view-toggle.active { background: var(--brand-primary); color: white; border-color: var(--brand-primary); }

        .btn-view-toggle svg { width: 16px; height: 16px; }

        /* ===== NUEVO FILTRO ===== */
        .nf-bar {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.05);
            padding: 0.35rem 0.5rem;
            margin: 0 auto 0.75rem auto;
            position: sticky;
            top: 1rem;
            z-index: 100;
            width: fit-content;
            max-width: 100%;
            gap: 0;
            overflow: visible;
        }

        .nf-sep {
            width: 1px;
            height: 28px;
            background: #e8edf4;
            margin: 0 0.5rem;
            flex-shrink: 0;
        }

        /* Búsqueda */
        .nf-search {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.75rem;
            border-radius: 0.85rem;
            transition: background 0.2s;
            min-width: 200px;
        }
        .nf-search svg { color: #94a3b8; flex-shrink: 0; }
        .nf-search input {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            outline: none !important;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-heading);
            width: 100%;
        }
        .nf-search input::placeholder { color: #b0bec5; font-weight: 500; }
        .nf-search:focus-within {
            background: #f4f6fb;
        }

        /* Fechas */
        .nf-dates {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.5rem;
            border-radius: 0.85rem;
            flex-shrink: 0;
        }
        .nf-date-input {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            outline: none !important;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-heading);
            width: 90px;
            text-align: center;
            cursor: pointer;
            padding: 0.35rem 0.4rem;
            border-radius: 0.6rem;
            transition: background 0.2s;
        }
        .nf-date-input:hover { background: #f1f5f9; }
        .nf-date-sep { font-size: 0.75rem; color: #cbd5e1; font-weight: 700; }

        /* Presets */
        .nf-presets-wrap { position: relative; }
        .nf-presets-btn {
            width: 30px; height: 30px;
            border-radius: 50%;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            color: #64748b;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .nf-presets-btn:hover { background: #334155; color: white; border-color: #334155; }
        .nf-presets-menu {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
            z-index: 200;
            min-width: 175px;
            padding: 0.35rem;
            overflow: hidden;
        }
        .nf-presets-menu button {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            width: 100%;
            padding: 0.6rem 0.85rem;
            border: none;
            background: transparent;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-heading);
            cursor: pointer;
            border-radius: 0.65rem;
            transition: background 0.15s;
            text-align: left;
        }
        .nf-presets-menu button:hover { background: #f4f6fb; color: #334155; }

        /* Chips de estado — fila centrada debajo de la barra */
        .nf-chips-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            flex-wrap: wrap;
            margin: 0.75rem auto 1.25rem auto;
            width: fit-content;
            background: #f1f5f9;
            padding: 0.3rem;
            border-radius: 1rem;
        }
        .nf-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            border: 1px solid transparent;
            background: transparent;
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .nf-chip:hover { background: #f1f5f9; color: var(--text-heading); }
        .nf-chip.active,
        .nf-chip:focus { background: #334155; color: white; border-color: #334155; outline: none; }
        .nf-chip.excelente.active { background: #475569; border-color: #475569; }
        .nf-chip.aldia.active     { background: #0ea5e9; border-color: #0ea5e9; }
        .nf-chip.revisar.active   { background: #f59e0b; border-color: #f59e0b; }
        .nf-chip.critico.active   { background: #ef4444; border-color: #ef4444; }
        .nf-chip.pendiente.active { background: #94a3b8; border-color: #94a3b8; }
        .nf-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* Botón sincronizar */
        .nf-sync-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1.1rem;
            border-radius: 0.9rem;
            background: var(--brand-gradient);
            color: white;
            border: none;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s;
            box-shadow: 0 4px 12px rgba(99,102,241,0.25);
            flex-shrink: 0;
            margin-left: auto;
        }
        .nf-sync-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99,102,241,0.35); }

        /* Chips Refinement */
        .status-navigation {
            display: flex;
            gap: 0.5rem;
            margin: 0 auto 1.5rem auto;
            padding: 0.25rem;
            background: #f1f5f9;
            width: fit-content;
            border-radius: 1rem;
        }

        .nav-chip {
            padding: 0.6rem 1.25rem;
            border-radius: 0.85rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid transparent;
        }

        .nav-chip:hover:not(.active) { color: var(--brand-primary); background: rgba(255,255,255,0.5); }
        .nav-chip.active {
            background: white;
            color: var(--text-heading);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-color: #e2e8f0;
        }

        .chip-dot { width: 6px; height: 6px; border-radius: 50%; }

        .flatpickr-calendar {
            box-shadow: 0 15px 35px rgba(0,0,0,0.15) !important;
            border: none !important;
            border-radius: 1rem !important;
            font-family: 'Outfit', sans-serif !important;
            padding: 0.5rem !important;
            width: 310px !important;
            font-size: 0.8rem !important;
        }

        .flatpickr-days,
        .flatpickr-days .dayContainer {
            width: 294px !important;
            min-width: 294px !important;
            max-width: 294px !important;
        }

        .flatpickr-day {
            max-width: 42px !important;
            width: 42px !important;
            height: 36px !important;
            line-height: 36px !important;
            border-radius: 0.5rem !important;
        }

        .flatpickr-month {
            height: 40px !important;
        }

        .flatpickr-current-month {
            font-size: 1rem !important;
            padding-top: 0 !important;
        }

        .flatpickr-day.selected {
            background: var(--brand-primary) !important;
            border-color: var(--brand-primary) !important;
            font-weight: bold;
        }
        
        .flatpickr-day:hover {
            background: rgba(99, 102, 241, 0.1) !important;
        }

        .date-range-group span {
            color: var(--text-muted);
            font-size: 1.2rem;
            font-weight: 300;
        }

        .btn-refresh {
            background: var(--brand-gradient);
            color: white;
            border: none;
            padding: 0.85rem 1.5rem;
            border-radius: 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        /* Summary Stats Container */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 1300px) {
            .summary-stats { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 768px) {
            .summary-stats { grid-template-columns: repeat(2, 1fr); }
        }

        .summary-card {
            background: white;
            padding: 1rem 1.25rem;
            border-radius: 1.25rem;
            border: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.3s;
        }

        .summary-card:hover { transform: translateY(-5px); }

        .icon-box {
            width: 54px;
            height: 54px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .summary-card.primary .icon-box { background: rgba(99, 102, 241, 0.1); color: var(--brand-primary); }
        .summary-card.info .icon-box { background: rgba(14, 165, 233, 0.1); color: var(--info); }
        .summary-card.warning .icon-box { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .summary-card.success .icon-box { background: rgba(71, 85, 105, 0.1); color: var(--success); }
        .summary-card.total .icon-box { background: rgba(15, 23, 42, 0.07); color: var(--text-heading); }

        .stat-info h4 { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
        .stat-info .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--text-heading); font-family: 'Outfit'; }

        /* Main Data Display: Premium List Cards */
        .header-section {
            padding: 0.75rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            backdrop-filter: blur(10px);
        }

        .report-section-header h2 { font-size: 1.25rem; font-weight: 700; color: var(--text-heading); }



        .data-cards-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .data-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid var(--card-border);
            padding: 1rem 1.5rem;
            display: grid;
            grid-template-columns: 1.2fr 1.1fr 1.4fr 0.7fr;
            align-items: center;
            gap: 1.25rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01);
        }

        .data-card:hover {
            border-color: var(--brand-primary);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.03);
            transform: scale(1.005);
        }

        /* Card Section: Cargo Info */
        .cargo-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .cargo-tag {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--brand-primary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            background: rgba(99, 102, 241, 0.08);
            padding: 0.25rem 0.6rem;
            border-radius: 0.5rem;
            display: inline-block;
            width: fit-content;
        }

        .cargo-name {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text-heading);
            font-family: 'Outfit';
            line-height: 1.2;
        }

        /* Card Section: Team */
        .team-section {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .team-label { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }

        .team-list {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .extra-team {
            display: none;
            flex-direction: column;
            gap: 0.4rem;
            margin-top: 0.4rem;
            padding-top: 0.4rem;
            border-top: 1px dashed #f1f5f9;
            animation: slideDown 0.3s ease-out;
        }

        .btn-toggle-team {
            background: #f8fafc;
            border: 1px solid #eef2f6;
            padding: 0.2rem 0.6rem;
            border-radius: 0.5rem;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--brand-primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.5rem;
            transition: all 0.2s;
        }

        .btn-toggle-team:hover {
            background: var(--brand-primary);
            color: white;
            border-color: var(--brand-primary);
        }

        .btn-toggle-team svg {
            transition: transform 0.3s;
        }

        .btn-toggle-team.active svg {
            transform: rotate(180deg);
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-heading);
        }

        .mini-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--brand-gradient);
            color: white;
            font-size: 0.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        /* Card Section: Metrics */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            background: #f8fafc;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            gap: 0.75rem;
        }

        .metric-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
            padding: 0.5rem;
            border-radius: 0.75rem;
        }

        .metric-item:not(.disabled):hover { background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transform: translateY(-2px); }
        .metric-item.disabled { cursor: default; }

        .metric-val { font-size: 1.15rem; font-weight: 800; color: var(--text-heading); margin-bottom: 0.2rem; font-family: 'Outfit'; }
        .metric-lbl { font-size: 0.6rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; text-align: center; }

        .metric-item.warn .metric-val { color: var(--warning); }
        .metric-item.info .metric-val { color: var(--info); }
        .metric-item.prim .metric-val { color: var(--brand-primary); }
        .metric-item.succ .metric-val { color: var(--success); }
        .metric-item.disabled .metric-val { color: #cbd5e1 !important; }

        /* Card Section: Performance */
        .performance-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding-left: 1.5rem;
            border-left: 1px solid #f1f5f9;
        }

        .percentage-circle {
            position: relative;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.3rem;
        }

        .percentage-circle svg {
            position: absolute;
            transform: rotate(-90deg);
        }

        .percentage-circle .circle-bg { fill: none; stroke: #f1f5f9; stroke-width: 4; }
        .percentage-circle .circle-progress { fill: none; stroke: var(--brand-primary); stroke-width: 4; stroke-linecap: round; transition: stroke-dashoffset 1s ease-out; }

        .percentage-circle .perc-val {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-heading);
            font-family: 'Outfit';
        }

        .perf-label { font-size: 0.65rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }

        /* Detail Area */
        .detail-expansion {
            grid-column: 1 / -1;
            margin-top: 1rem;
            border-top: 1px dashed var(--card-border);
            padding-top: 1rem;
            display: none;
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .no-results {
            text-align: center;
            padding: 4rem;
            background: white;
            border-radius: 2rem;
            color: var(--text-muted);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }


    </style>
</head>
<body>

<div class="wrapper">
    <header class="page-header">
        <div class="title-section">
            <h1>SIGTRAM <span>Internos</span></h1>
            <p>Monitoreo en Tiempo Real · Trámites Internos por Cargo</p>
        </div>
        
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="{{ route('inicio') }}"
               style="display:inline-flex; align-items:center; gap:0.5rem; padding:0.5rem 1rem; background:white; border:1.5px solid #e2e8f0; border-radius:0.85rem; font-size:0.82rem; font-weight:700; color:#475569; text-decoration:none; box-shadow:0 2px 8px rgba(0,0,0,0.05); transition:all 0.2s;"
               onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#334155'; this.style.color='#334155';"
               onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'; this.style.color='#475569';">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Volver a SIGTRAM
            </a>

            <div class="view-switch-tabs" style="display: flex; background: #f1f5f9; padding: 0.25rem; border-radius: 1rem;">
                <button type="button" class="btn-view-toggle active" id="btnDashboard" onclick="switchView('dashboard')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    Dashboard
                </button>
                <button type="button" class="btn-view-toggle" id="btnReports" onclick="switchView('reports')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    Reportes
                </button>
            </div>
            
        </div>
    </header>

    <form action="{{ route('mod_tram_inter.v2_lista_reporte_area_cargo') }}" method="post" id="mainFilterForm">
        @csrf
        <!-- ===== NUEVO FILTRO ===== -->
        <div class="nf-bar">

            <!-- Búsqueda -->
            <div class="nf-search">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"/></svg>
                <input type="text" id="cardSearch" placeholder="Buscar cargo o responsable…">
            </div>

            <!-- Separador -->
            <div class="nf-sep"></div>

            <!-- Fechas + Presets -->
            <div class="nf-dates">
                <svg width="15" height="15" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                <input type="text" name="txt_fe_inicio" id="txt_fe_inicio" class="nf-date-input" value="{{ $desdeStr }}" placeholder="Desde" readonly>
                <span class="nf-date-sep">→</span>
                <input type="text" name="txt_fe_fin" id="txt_fe_fin" class="nf-date-input" value="{{ $hastaStr }}" placeholder="Hasta" readonly>

                <!-- Presets dropdown -->
                <div class="nf-presets-wrap">
                    <button type="button" class="nf-presets-btn" onclick="$('#nfPresetsMenu').toggle(); event.stopPropagation();" title="Periodos rápidos">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    </button>
                    <div id="nfPresetsMenu" class="nf-presets-menu">
                        <button type="button" onclick="setRange('today')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1M4.22 4.22l.7.7m12.02 12.02l.7.7M3 12h1m16 0h1M4.22 19.78l.7-.7M18.36 5.64l.7-.7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                            Hoy
                        </button>
                        <button type="button" onclick="setRange('this_month')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                            Este Mes
                        </button>
                        <button type="button" onclick="setRange('last_3_months')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                            Últimos 3 Meses
                        </button>
                    </div>
                </div>
            </div>

            <!-- Botón sincronizar -->
            <button type="submit" class="nf-sync-btn" name="Buscar">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"/></svg>
                Sincronizar
            </button>
        </div>

        <!-- Chips centrados debajo -->
        <div class="nf-chips-row" id="nfChips">
            <button type="button" class="nf-chip active" onclick="applySmartFilter('all', this)">Todos</button>
            <button type="button" class="nf-chip excelente" onclick="applySmartFilter('EXCELENTE', this)">
                <span class="nf-dot" style="background:#475569;"></span>Excelente
            </button>
            <button type="button" class="nf-chip aldia" onclick="applySmartFilter('AL DÍA', this)">
                <span class="nf-dot" style="background:#0ea5e9;"></span>Al Día
            </button>
            <button type="button" class="nf-chip revisar" onclick="applySmartFilter('REVISAR', this)">
                <span class="nf-dot" style="background:#f59e0b;"></span>Revisar
            </button>
            <button type="button" class="nf-chip critico" onclick="applySmartFilter('CRÍTICO', this)">
                <span class="nf-dot" style="background:#ef4444;"></span>Crítico
            </button>
            <button type="button" class="nf-chip pendiente" id="pendingToggle" onclick="togglePending(this)">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                Pendientes
            </button>
        </div>
    </form>

    <section id="dashboardView" class="view-container view-active">
    <section class="summary-stats">
        <div class="summary-card total">
            <div class="icon-box">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            </div>
            <div class="stat-info">
                <h4>Total</h4>
                <div class="stat-value">{{ number_format($totales['sum_total_doc']) }}</div>
            </div>
        </div>
        <div class="summary-card warning">
            <div class="icon-box">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4a2 2 0 012-2m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            </div>
            <div class="stat-info">
                <h4>Por Recibir</h4>
                <div class="stat-value">{{ number_format($totales['sum_entregado']) }}</div>
            </div>
        </div>
        <div class="summary-card info">
            <div class="icon-box">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            </div>
            <div class="stat-info">
                <h4>Recibidos</h4>
                <div class="stat-value">{{ number_format($totales['sum_recibido']) }}</div>
            </div>
        </div>
        <div class="summary-card primary">
            <div class="icon-box">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            </div>
            <div class="stat-info">
                <h4>Derivados</h4>
                <div class="stat-value">{{ number_format($totales['sum_salida']) }}</div>
            </div>
        </div>
        <div class="summary-card success">
            <div class="icon-box">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            </div>
            <div class="stat-info">
                <h4>Finalizados</h4>
                <div class="stat-value">{{ number_format($totales['sum_finalizado']) }}</div>
            </div>
        </div>
        <div class="summary-card" style="border: 2px solid {{ $totales['pct_color'] }}20;">
            <div class="icon-box" style="background: {{ $totales['pct_bg'] }}; color: {{ $totales['pct_color'] }};">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            </div>
            <div class="stat-info">
                <h4>% de Trabajo</h4>
                <div class="stat-value" style="color: {{ $totales['pct_color'] }};">{{ $totales['pct_trabajo'] }}%</div>
                <div style="font-size:0.65rem; color: var(--text-muted); font-weight:600; margin-top:0.15rem;">(Der. + Fin.) / Total</div>
            </div>
        </div>
        </section>
    

    <!-- reportsView moved below dashboardView -->

    <div class="report-section-header">
        <h2>Detalle de Gestión por Cargo</h2>
        <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">
            Mostrando <span style="color: var(--brand-primary);">{{ count($rows_data) }}</span> posiciones activas
        </div>
    </div>

    <div class="data-cards-list" id="cardsList">
        @foreach($rows_data as $index => $row)
        <div class="data-card" 
             data-search-content="{{ strtolower($row['cargo'] . ' ' . implode(' ', $row['usuarios'])) }}"
             data-status-text="{{ $row['status_text'] }}"
             data-performance="{{ $row['porcentaje'] }}"
             data-vacant="{{ count($row['usuarios']) == 0 ? 'true' : 'false' }}"
             data-total="{{ $row['total'] }}"
             data-pending="{{ $row['entrada'] + $row['recibido'] }}"
             data-responsables="{{ count($row['usuarios']) > 0 ? implode(', ', $row['usuarios']) : 'Sin asignar' }}">
            <div class="cargo-info">
                <h3 class="cargo-name">{{ $row['cargo'] }}</h3>
            </div>

            <div class="team-section">
                <span class="team-label">Responsables</span>
                <div class="team-list">
                    @if(count($row['usuarios']) > 0)
                        @php
                            $first_user = $row['usuarios'][0];
                            $init = $row['iniciales'][0] ?? '';
                        @endphp
                        <div class="user-item">
                            <div class="mini-avatar">{{ substr($init, 0, 2) }}</div>
                            <span>{{ $first_user }}</span>
                        </div>

                        @if(count($row['usuarios']) > 1)
                            <button class="btn-toggle-team" onclick="toggleTeamList({{ $index }}, this)">
                                <span>Ver {{ count($row['usuarios']) - 1 }} más</span>
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"/></svg>
                            </button>
                            
                            <div id="extra-team-{{ $index }}" class="extra-team">
                                @foreach(array_slice($row['usuarios'], 1, null, true) as $i => $user)
                                    @php $init = $row['iniciales'][$i] ?? ''; @endphp
                                    <div class="user-item">
                                        <div class="mini-avatar" style="opacity: 0.7;">{{ substr($init, 0, 2) }}</div>
                                        <span style="color: var(--text-muted);">{{ $user }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div style="font-size: 0.75rem; color: var(--danger); font-weight: 700;">VACANTE / SIN ASIGNAR</div>
                    @endif
                </div>
            </div>

            <div class="metrics-grid">
                <div class="metric-item" style="border-right: 1px solid #e2e8f0; cursor: default;">
                    <span class="metric-val" style="color: #0f172a;">{{ $row['total'] }}</span>
                    <span class="metric-lbl" style="color: #0f172a;">Total</span>
                </div>
                <div class="metric-item warn {{ $row['entrada'] <= 0 ? 'disabled' : '' }}" @if($row['entrada'] > 0) onclick="toggleDetails({{ $row['id'] }}, 1, {{ $index }})" @endif>
                    <span class="metric-val">{{ $row['entrada'] }}</span>
                    <span class="metric-lbl">Por Recibir</span>
                </div>
                <div class="metric-item info {{ $row['recibido'] <= 0 ? 'disabled' : '' }}" @if($row['recibido'] > 0) onclick="toggleDetails({{ $row['id'] }}, 2, {{ $index }})" @endif>
                    <span class="metric-val">{{ $row['recibido'] }}</span>
                    <span class="metric-lbl">Recibidos</span>
                </div>
                <div class="metric-item prim {{ $row['salida'] <= 0 ? 'disabled' : '' }}" @if($row['salida'] > 0) onclick="toggleDetails({{ $row['id'] }}, 3, {{ $index }})" @endif>
                    <span class="metric-val">{{ $row['salida'] }}</span>
                    <span class="metric-lbl">Derivados</span>
                </div>
                <div class="metric-item succ {{ $row['finalizado'] <= 0 ? 'disabled' : '' }}" @if($row['finalizado'] > 0) onclick="toggleDetails({{ $row['id'] }}, 4, {{ $index }})" @endif>
                    <span class="metric-val">{{ $row['finalizado'] }}</span>
                    <span class="metric-lbl">Finalizados</span>
                </div>
            </div>

            <div class="performance-section">
                @php
                    $pct_row = $row['total'] > 0 ? round((($row['salida'] + $row['finalizado']) / $row['total']) * 100, 1) : 0;
                    $pct_diff = round(100 - $pct_row, 1);
                    if ($pct_row >= 80)     { $pct_row_color = '#475569'; $pct_row_bg = 'rgba(71, 85, 105,0.1)'; $pct_row_border = 'rgba(71, 85, 105,0.25)'; }
                    elseif ($pct_row >= 50) { $pct_row_color = '#f59e0b'; $pct_row_bg = 'rgba(245,158,11,0.1)';  $pct_row_border = 'rgba(245,158,11,0.25)'; }
                    else                    { $pct_row_color = '#ef4444'; $pct_row_bg = 'rgba(239,68,68,0.1)';   $pct_row_border = 'rgba(239,68,68,0.25)'; }
                @endphp
                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.4rem;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem; background: {{ $pct_row_bg }}; border: 1px solid {{ $pct_row_border }}; border-radius: 1rem; padding: 0.5rem 0.85rem; width: 100%;">
                        <span style="font-size: 1.4rem; font-weight: 800; color: {{ $pct_row_color }}; font-family: 'Outfit'; line-height: 1;">{{ $pct_row }}%</span>
                        <span style="font-size: 0.58rem; font-weight: 700; color: {{ $pct_row_color }}; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">% Trabajo</span>
                    </div>
                    @if($pct_diff > 0)
                    <div style="display: flex; align-items: center; gap: 0.3rem; background: rgba(239,68,68,0.07); border: 1px solid rgba(239,68,68,0.15); border-radius: 0.75rem; padding: 0.25rem 0.65rem;">
                        <span style="font-size: 0.75rem; color: #ef4444;">▼</span>
                        <span style="font-size: 0.85rem; font-weight: 800; color: #ef4444; font-family: 'Outfit';">{{ $pct_diff }}%</span>
                        <span style="font-size: 0.58rem; font-weight: 700; color: #ef4444; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">pendiente</span>
                    </div>
                    @else
                    <div style="display: flex; align-items: center; gap: 0.3rem; background: rgba(71, 85, 105,0.07); border: 1px solid rgba(71, 85, 105,0.15); border-radius: 0.75rem; padding: 0.25rem 0.65rem;">
                        <span style="font-size: 0.75rem; color: #475569;">✓</span>
                        <span style="font-size: 0.85rem; font-weight: 800; color: #475569; font-family: 'Outfit';">Al día</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Detail expansion -->
            <div id="detail-pane-{{ $index }}" class="detail-expansion">
                <div style="background: #fcfcfd; border-radius: 1rem; padding: 1.5rem; border: 1px solid #f1f5f9;">
                    <div id="detail-loader-{{ $index }}" style="text-align:center; padding: 2rem; color: var(--text-muted);">
                        Obteniendo detalles inteligentes...
                    </div>
                </div>
            </div>
        </div>
        @endforeach
        </div>
    </section>

    <!-- Advanced Reports View -->
    <section id="reportsView" class="view-container view-hidden">
        <!-- KPI Row -->
        <div class="kpi-row">
            <div class="kpi-card purple">
                <div class="kpi-icon">📄</div>
                <div class="kpi-content">
                    <h4>Total Registros</h4>
                    <div class="stat-value counter" id="rep-kpi-total">0</div>
                    <p>Carga histórica total</p>
                </div>
            </div>
            <div class="kpi-card orange">
                <div class="kpi-icon">🕒</div>
                <div class="kpi-content">
                    <h4>Pendientes</h4>
                    <div class="stat-value counter" id="rep-kpi-pending">0</div>
                    <p>Por gestionar</p>
                </div>
            </div>
            <div class="kpi-card green">
                <div class="kpi-icon">✅</div>
                <div class="kpi-content">
                    <h4>Finalizados</h4>
                    <div class="stat-value counter" id="rep-kpi-completed">0</div>
                    <p>Trámites cerrados</p>
                </div>
            </div>
            <div class="kpi-card blue">
                <div class="kpi-icon">⚡</div>
                <div class="kpi-content">
                    <h4>Eficiencia</h4>
                    <div class="stat-value counter" id="rep-kpi-efficiency">0</div>
                    <p>Cumplimiento global</p>
                </div>
            </div>
        </div>

        <!-- Main Analytics Grid -->
        <div class="reports-grid">
            <!-- Row 1 -->
            <div class="report-card">
                <div class="report-header">
                    <h3 class="report-title">📊 Distribución de Estados</h3>
                </div>
                <div class="analytics-split">
                    <div class="chart-main">
                        <canvas id="statusChart"></canvas>
                        <div class="donut-center-label">
                            <div class="total-num" id="donut-total">0</div>
                            <div class="total-lbl">Cargos</div>
                        </div>
                    </div>
                    <div class="chart-side-panel" id="statusPanel">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
            </div>
            
            <div class="report-card">
                <div class="report-header">
                    <h3 class="report-title">📈 Evolución de Gestión</h3>
                </div>
                <div class="analytics-split">
                    <div class="chart-main" style="flex: 0 0 70%;">
                        <canvas id="evolutionChart"></canvas>
                    </div>
                    <div class="chart-side-panel" id="evolutionPanel">
                        <div class="side-metric-item">
                            <span class="total-lbl" style="font-size: 0.6rem;">Total Acumulado</span>
                            <div class="stat-value" id="evo-total" style="font-size: 1.5rem;">0</div>
                        </div>
                        <div class="side-metric-item">
                            <span class="total-lbl" style="font-size: 0.6rem;">Promedio Mensual</span>
                            <div class="stat-value" id="evo-avg" style="font-size: 1.25rem;">0</div>
                        </div>
                        <div class="side-metric-item">
                            <div class="trend-pill up" id="evo-trend">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd"/></svg>
                                <span>12% ↑</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2 -->
            <div class="report-card">
                <div class="report-header">
                    <h3 class="report-title">👔 Carga Crítica por Cargo</h3>
                </div>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="workloadChart"></canvas>
                </div>
            </div>

            <div class="report-card">
                <div class="report-header">
                    <h3 class="report-title">🏢 Distribución por Área</h3>
                </div>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="areaChart"></canvas>
                </div>
            </div>



            <!-- Row 3: Tables -->


            <div class="report-card full">
                <div class="report-header">
                    <h3 class="report-title">📝 Detalle General por Cargo</h3>
                </div>
                <div class="report-table-container">
                    <table class="report-table" id="cargoDetailTable">
                        <thead>
                            <tr>
                                <th style="text-align:left; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Cargo Administrativo</th>
                                <th style="text-align:right; padding-right:1.5rem; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Total</th>
                                <th style="text-align:right; padding-right:1.5rem; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Pendientes</th>
                                <th style="text-align:right; padding-right:1.5rem; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Finalizados</th>
                                <th style="text-align:center; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Estado Actual</th>
                                <th style="text-align:center; position: sticky; top: 0; background: #f8fafc; z-index: 10;">Eficiencia / Tendencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamically loaded -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <footer style="margin-top: 5rem; border-top: 1px solid #eef2f6; padding-top: 2rem; text-align: center; color: var(--text-muted); font-size: 0.8rem; font-weight: 600;">
        Sigtram Intelligence Engine © {{ date('Y') }} • Municipalidad Distrital de Puente Piedra
    </footer>
</div>



<script>
    const V2_URL_DETALLE = @json(route('mod_tram_inter.v2_lista_reporte_area_cargo.detalle'));
    const V2_FECHA_INI = @json($desdeStr);
    const V2_FECHA_FIN = @json($hastaStr);
    const V2_CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Initialize Modern Flatpickr
    flatpickr("#txt_fe_inicio", {
        locale: "es",
        dateFormat: "d/m/Y",
        allowInput: false,
        disableMobile: true
    });
    
    flatpickr("#txt_fe_fin", {
        locale: "es",
        dateFormat: "d/m/Y",
        allowInput: false,
        disableMobile: true
    });

    // Legacy triggers removed in favor of direct onclick for better reliability
    // Calendar.setup is sometimes unreliable in dynamic layouts.

    // Date Range Presets
    function setRange(type) {
        const startInput = document.getElementById('txt_fe_inicio');
        const endInput = document.getElementById('txt_fe_fin');
        const today = new Date();
        let start = new Date();
        
        if (type === 'today') {
            start = today;
        } else if (type === 'this_month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
        } else if (type === 'last_3_months') {
            start = new Date(today.getFullYear(), today.getMonth() - 3, today.getDate());
        } else if (type === 'year') {
            start = new Date(today.getFullYear(), 0, 1);
        }

        const format = (d) => d.getDate().toString().padStart(2, '0') + '/' + (d.getMonth() + 1).toString().padStart(2, '0') + '/' + d.getFullYear();
        startInput.value = format(start);
        endInput.value = format(today);
        
        $('#presetsMenu').hide();
        $('#mainFilterForm').submit();
    }

    // Smart Real-time Filtering
    function applySmartFilter(status, el) {
        $('.nf-chip').not('#pendingToggle').removeClass('active');
        $(el).addClass('active');
        applyAllFilters();
    }

    function togglePending(el) {
        $(el).toggleClass('active');
        applyAllFilters();
    }

    function applyAllFilters() {
        const activeChip = $('.nf-chip.active').not('#pendingToggle');
        const activeStatus = activeChip.length ? activeChip.attr('onclick').match(/'([^']+)'/)[1] : 'all';
        const onlyPending = $('#pendingToggle').hasClass('active');
        const searchValue = $("#cardSearch").val().toLowerCase();
        const cards = $('.data-card');

        cards.each(function() {
            const card = $(this);
            const cardStatus = card.data('status-text');
            const cardPending = parseInt(card.data('pending'));
            const cardSearch = card.attr('data-search-content');

            let show = true;
            if (activeStatus !== 'all' && cardStatus !== activeStatus) show = false;
            if (show && onlyPending && cardPending <= 0) show = false;
            if (show && searchValue && cardSearch.indexOf(searchValue) === -1) show = false;

            card.toggle(show);
        });
    }

    // View Switcher SPA Logic
    let chartsInitialized = false;

    function switchView(view) {
        if (view === 'dashboard') {
            $('#btnReports').removeClass('active');
            $('#btnDashboard').addClass('active');
            $('#reportsView').addClass('view-hidden').removeClass('view-active');
            $('#dashboardView').removeClass('view-hidden').addClass('view-active');
            $('.nf-chips, .nf-search, #nfChips').fadeIn();
        } else {
            $('#btnDashboard').removeClass('active');
            $('#btnReports').addClass('active');
            $('#dashboardView').addClass('view-hidden').removeClass('view-active');
            $('#reportsView').removeClass('view-hidden').addClass('view-active');
            $('.nf-chips, .nf-search, #nfChips').fadeOut();
            
            // Allow transition to finish before initializing charts
            setTimeout(initReports, 300);
        }
    }

    function animateValue(obj, start, end, duration, suffix = "") {
        if(!obj) return;
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const val = Math.floor(progress * (end - start) + start);
            obj.innerText = val + suffix;
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }

    function initReports() {
        const cards = $('.data-card');
        let stats = { EXCELENTE: 0, 'AL DÍA': 0, REVISAR: 0, 'CRÍTICO': 0 };
        let workloads = [];
        let totalDocs = 0, totalPending = 0, totalCompleted = 0, totalEfficiency = 0;
        let responsibleData = {};

        cards.each(function() {
            const card = $(this);
            const status = card.data('status-text');
            const pending = parseInt(card.data('pending')) || 0;
            const total = parseInt(card.data('total')) || 0;
            const performance = parseFloat(card.data('performance')) || 0;
            const cargo = card.find('.cargo-name').text();
            const searchContent = card.attr('data-search-content') || '';
            const responsables = searchContent.indexOf('|') !== -1 ? searchContent.split('|')[1].split(',').map(s => s.trim()) : ['Sin asignar'];
            const responsablesLabel = card.attr('data-responsables') || 'Sin asignar';

            if (stats[status] !== undefined) stats[status]++;
            totalDocs += total;
            totalPending += pending;
            totalCompleted += (total - pending);
            totalEfficiency += performance;

            workloads.push({ label: cargo, total: total, pending: pending, status: status, performance: performance, responsables: responsablesLabel });

            responsables.forEach(resp => {
                if (!responsibleData[resp]) responsibleData[resp] = { count: 0, pending: 0, completed: 0, totalPerf: 0 };
                responsibleData[resp].count++;
                responsibleData[resp].pending += pending;
                responsibleData[resp].completed += (total - pending);
                responsibleData[resp].totalPerf += performance;
            });
        });

        const globalEfficiency = (totalCompleted / (totalDocs || 1)) * 100;
        const totalCargos = cards.length;

        // KPI Counters
        animateValue(document.getElementById('rep-kpi-total'), 0, totalDocs, 1000);
        animateValue(document.getElementById('rep-kpi-pending'), 0, totalPending, 1000);
        animateValue(document.getElementById('rep-kpi-completed'), 0, totalCompleted, 1000);
        animateValue(document.getElementById('rep-kpi-efficiency'), 0, globalEfficiency, 1000, "%");
        // Center total donut
        const donutTotalEl = document.getElementById('donut-total');
        if (donutTotalEl) donutTotalEl.innerText = totalCargos;

        // Populate Status Side Panel
        const statusPanel = document.getElementById('statusPanel');
        if (statusPanel) {
            statusPanel.innerHTML = '';
            const statusColors = { EXCELENTE: '#475569', 'AL DÍA': '#0ea5e9', REVISAR: '#f59e0b', 'CRÍTICO': '#ef4444' };
            
            Object.keys(stats).forEach(key => {
                const count = stats[key];
                const perc = totalCargos ? Math.round((count / totalCargos) * 100) : 0;
                const color = statusColors[key];
                
                statusPanel.innerHTML += `
                    <div class="side-metric-item">
                        <div class="side-metric-info">
                            <div class="side-metric-label">
                                <div class="side-metric-dot" style="background:${color}"></div>
                                ${key}
                            </div>
                            <div class="side-metric-value">${count} (${perc}%)</div>
                        </div>
                        <div class="side-progress-bg">
                            <div class="side-progress-fill" style="width:${perc}%; background:${color}"></div>
                        </div>
                    </div>
                `;
            });
        }

        // Update Evolution Stats
        const evoTotalEl = document.getElementById('evo-total');
        if (evoTotalEl) evoTotalEl.innerText = totalCompleted;
        const evoAvgEl = document.getElementById('evo-avg');
        if (evoAvgEl) evoAvgEl.innerText = (totalCompleted / 7).toFixed(1) + " / mes";

        // Detail Table - Optimized for Analysis
        const detailBody = $('#cargoDetailTable tbody').empty();
        
        // Sorting: Criticos first (pending desc), then total volume
        const sortedDetails = [...workloads].sort((a,b) => (b.pending - a.pending) || (b.total - a.total));

        sortedDetails.forEach(w => {
            const completed = w.total - w.pending;
            const efficiency = w.total > 0 ? Math.round((completed / w.total) * 100) : 100;
            
            // Row styling based on critical level
            let rowStyle = "";
            let statusBadge = "";
            let trendIcon = "→";
            let trendColor = "#94a3b8";

            if (w.total === 0) {
                rowStyle = "opacity: 0.5; background: #f8fafc;";
                statusBadge = `<span style="background:#f1f5f9; color:#64748b; padding:4px 10px; border-radius:6px; font-size:0.65rem; font-weight:800;">SIN ACTIVIDAD</span>`;
            } else if (w.pending > 5 || (efficiency < 50 && w.total > 2)) {
                rowStyle = "background: rgba(239, 68, 68, 0.02);";
                statusBadge = `<span style="background:#fef2f2; color:#ef4444; padding:4px 10px; border-radius:6px; font-size:0.65rem; font-weight:800; border:1px solid #fee2e2;">CRÍTICO</span>`;
                trendIcon = "↓";
                trendColor = "#ef4444";
            } else if (efficiency < 85) {
                statusBadge = `<span style="background:#fffbeb; color:#f59e0b; padding:4px 10px; border-radius:6px; font-size:0.65rem; font-weight:800; border:1px solid #fef3c7;">AL DÍA</span>`;
                trendIcon = "→";
                trendColor = "#f59e0b";
            } else {
                statusBadge = `<span style="background:#f8fafc; color:#475569; padding:4px 10px; border-radius:6px; font-size:0.65rem; font-weight:800; border:1px solid #e2e8f0;">EXCELENTE</span>`;
                trendIcon = "↑";
                trendColor = "#475569";
            }

            detailBody.append(`
                <tr style="${rowStyle} transition: background 0.2s;" onmouseover="this.style.background='rgba(59, 130, 246, 0.04)'" onmouseout="this.style.background='${rowStyle.includes('rgba') ? 'rgba(239, 68, 68, 0.02)' : (rowStyle.includes('f8fafc') ? '#f8fafc' : 'transparent')}'">
                    <td style="padding: 0.5rem 1rem; font-weight: 600; color: var(--text-heading); font-size: 0.8rem;">${w.label}</td>
                    <td style="text-align:right; font-weight: 700; color: #64748b; padding-right: 1.5rem; font-size: 0.85rem;">${w.total}</td>
                    <td style="text-align:right; font-weight: 700; color: var(--danger); padding-right: 1.5rem; font-size: 0.85rem;">${w.pending}</td>
                    <td style="text-align:right; font-weight: 700; color: var(--success); padding-right: 1.5rem; font-size: 0.85rem;">${completed}</td>
                    <td style="text-align:center;">${statusBadge}</td>
                    <td style="text-align:center;">
                        <div style="display:flex; align-items:center; justify-content:center; gap:0.6rem;">
                            <span style="font-size:0.75rem; font-weight:800; color:${trendColor}; min-width:35px; text-align:right;">${efficiency}%</span>
                            <span style="font-size:1.1rem; color:${trendColor}; font-weight:bold; line-height:1;">${trendIcon}</span>
                        </div>
                    </td>
                </tr>
            `);
        });
        if (!chartsInitialized) {
            Chart.defaults.font.family = "'Figtree'";
            Chart.defaults.color = "#64748b";

            // 1. Status Chart
            const ctxStatus = document.getElementById('statusChart');
            if (ctxStatus) {
                new Chart(ctxStatus, {
                    type: 'doughnut',
                    data: {
                        labels: ['Excelente', 'Al Día', 'Revisar', 'Crítico'],
                        datasets: [{
                            data: [
                                $('.data-card[data-status-text="EXCELENTE"]').length,
                                $('.data-card[data-status-text="AL DÍA"]').length,
                                $('.data-card[data-status-text="REVISAR"]').length,
                                $('.data-card[data-status-text="CRÍTICO"]').length
                            ],
                            backgroundColor: ['#475569', '#0ea5e9', '#f59e0b', '#ef4444'],
                            borderWidth: 0,
                            cutout: '82%'
                        }]
                    },
                    options: { 
                        maintainAspectRatio: false, 
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 2. Evolution Chart (Real Data)
            const ctxEvo = document.getElementById('evolutionChart');
            if (ctxEvo) {
                const gradGreen = ctxEvo.getContext('2d').createLinearGradient(0, 0, 0, 300);
                gradGreen.addColorStop(0, 'rgba(71, 85, 105, 0.15)');
                gradGreen.addColorStop(1, 'rgba(71, 85, 105, 0)');

                new Chart(ctxEvo, {
                    type: 'line',
                    data: {
                        labels: @json($evoData['labels']),
                        datasets: [
                            { 
                                label: 'Finalizados', 
                                data: @json($evoData['finalizados']), 
                                borderColor: '#475569', 
                                borderWidth: 3,
                                pointBackgroundColor: '#475569',
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                tension: 0.4, 
                                fill: true, 
                                backgroundColor: gradGreen 
                            },
                            { 
                                label: 'Pendientes', 
                                data: @json($evoData['pendientes']), 
                                borderColor: '#f59e0b', 
                                borderWidth: 2,
                                borderDash: [5, 5],
                                pointRadius: 0,
                                tension: 0.4,
                                fill: false
                            }
                        ]
                    },
                    options: { 
                        maintainAspectRatio: false, 
                        interaction: { intersect: false, mode: 'index' },
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                            x: { grid: { display: false } }
                        }
                    }
                });

                // Update evolution summaries
                const evoTotalEl = document.getElementById('evo-total');
                if (evoTotalEl) evoTotalEl.innerText = "{{ $evoData['total_fin'] }}";

                const evoAvgEl = document.getElementById('evo-avg');
                if (evoAvgEl) evoAvgEl.innerText = "{{ $evoData['avg_fin'] }} / mes";

                const evoTrendEl = document.getElementById('evo-trend-pct');
                if (evoTrendEl) {
                    const trend = {{ $evoData['trend_pct'] }};
                    evoTrendEl.innerText = (trend >= 0 ? "+" : "") + trend + "%";
                }
            }

            // 3. Workload Chart (Compact & Analytical)
            const ctxWorkload = document.getElementById('workloadChart');
            if (ctxWorkload) {
                const sortedWorkloads = [...workloads].sort((a,b) => b.pending - a.pending).slice(0, 10);
                const palette = [
                    '#ef4444', '#f97316', '#f59e0b', '#eab308', 
                    '#94a3b8', '#475569', '#06b6d4', '#3b82f6', 
                    '#334155', '#94a3b8'
                ];
                
                new Chart(ctxWorkload, {
                    type: 'bar',
                    data: {
                        labels: sortedWorkloads.map(w => w.label.substring(0, 40)),
                        datasets: [{
                            label: 'Pendientes',
                            data: sortedWorkloads.map(w => w.pending),
                            backgroundColor: sortedWorkloads.map((w, i) => palette[i] || '#cbd5e1'),
                            borderRadius: 6,
                            barThickness: 24,
                            maxBarThickness: 30
                        }]
                    },
                    options: { 
                        indexAxis: 'y', 
                        maintainAspectRatio: false, 
                        layout: { padding: { right: 40 } },
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1e293b',
                                padding: 14,
                                titleFont: { size: 12, weight: '700' },
                                bodyFont: { size: 11 },
                                callbacks: {
                                    title: function(items) {
                                        return sortedWorkloads[items[0].dataIndex].label;
                                    },
                                    label: function(item) {
                                        return '  Pendientes: ' + item.raw;
                                    },
                                    afterLabel: function(item) {
                                        var resp = sortedWorkloads[item.dataIndex].responsables;
                                        return '  👤 ' + resp;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { 
                                grid: { color: '#f1f5f9' },
                                ticks: { font: { weight: '600' } }
                            },
                            y: { 
                                grid: { display: false },
                                ticks: { font: { weight: '700', size: 11 } }
                            }
                        }
                    }
                });
            }

            // 4. Area Chart (Optimized Polar)
            const ctxArea = document.getElementById('areaChart');
            if (ctxArea) {
                new Chart(ctxArea, {
                    type: 'polarArea',
                    data: {
                        labels: [
                            'GERENCIA DE ASESORÍA JURÍDICA', 
                            'GERENCIA DE ADMINISTRACIÓN Y FINANZAS', 
                            'GERENCIA DE DESARROLLO URBANO', 
                            'GERENCIA DE FISCALIZACIÓN Y CONTROL'
                        ],
                        datasets: [{
                            data: [11, 16, 7, 14],
                            backgroundColor: [
                                'rgba(99, 102, 241, 0.7)', 
                                'rgba(71, 85, 105, 0.7)', 
                                'rgba(245, 158, 11, 0.7)', 
                                'rgba(239, 68, 68, 0.7)'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: { 
                        maintainAspectRatio: false, 
                        layout: { padding: 10 },
                        plugins: { 
                            legend: { 
                                position: 'bottom', 
                                labels: { 
                                    boxWidth: 8, 
                                    usePointStyle: true, 
                                    padding: 15,
                                    font: { size: 11, weight: '600' }
                                } 
                            } 
                        },
                        scales: { 
                            r: { 
                                grid: { color: '#f1f5f9' }, 
                                ticks: { display: false },
                                angleLines: { color: '#f1f5f9' }
                            } 
                        }
                    }
                });
            }

            chartsInitialized = true;
        }
    }

    // Close presets on click outside
    $(document).mouseup(function(e) {
        var menu = $('#nfPresetsMenu');
        if (!menu.is(e.target) && menu.has(e.target).length === 0 && !$('.nf-presets-btn').is(e.target)) {
            menu.hide();
        }
    });

    // Premium Search Filter
    $(document).ready(function() {
        $("#cardSearch").on("keyup", function() {
            applyAllFilters();
        });
    });

    // Team Toggle Function
    function toggleTeamList(index, btn) {
        const extra = $(`#extra-team-${index}`);
        const isVisible = extra.is(":visible");
        
        $(btn).toggleClass('active');
        extra.slideToggle();
        
        const label = isVisible ? `Ver ${extra.children().length} más` : 'Ocultar';
        $(btn).find('span').text(label);
    }

    // Detail Toggle Function
    function toggleDetails(cdgo_crgo, tipo, index) {
        const pane   = $('#detail-pane-' + index);
        const loader = $('#detail-loader-' + index);

        if (pane.is(":visible")) {
            pane.slideUp();
        } else {
            $(".detail-expansion").slideUp();
            pane.slideDown();
            loader.html('<div style="text-align:center;padding:2rem;color:#94a3b8;">Cargando...</div>');
            $.ajax({
                type: 'POST',
                url: V2_URL_DETALLE,
                data: {
                    _token: V2_CSRF_TOKEN,
                    cargo_id: cdgo_crgo,
                    tipo_bandeja: tipo,
                    fecha_inicio: V2_FECHA_INI,
                    fecha_fin: V2_FECHA_FIN
                },
                success: function(res) {
                    loader.html(res.html);
                },
                error: function() {
                    loader.html('<div style="text-align:center;padding:2rem;color:#ef4444;">Error al cargar el detalle</div>');
                }
            });
        }
    }
</script>
</body>
</html>
