<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sigtram Intelligence | Advanced Report</title>

    @vite(['resources/css/app.css', 'resources/css/shared/v2-reporte-area-cargo.css', 'resources/js/app.js'])
</head>
<body>

@php
    $frontendConfig = [
        'urlDetalle' => route('mod_gm.v2_lista_reporte_area_cargo.detalle'),
        'fechaIni' => $desdeStr,
        'fechaFin' => $hastaStr,
        'csrfToken' => csrf_token(),
        'evoData' => $evoData ?? null,
        'urlOjo' => route('tramites.busca_expedi_deta', [], false),
        'urlHistorico' => route('mod_gm.v2_lista_reporte_area_cargo.historico', ['id' => '__HID__']),
    ];
@endphp

<div class="wrapper" id="v2-reporte-area-cargo-app" x-data="v2ReporteAreaCargoApp" data-config="{{ json_encode($frontendConfig) }}">
    <header class="page-header">
        <div class="title-section">
            <h1>SIGTRAM <span>Externos</span></h1>
            <p>Monitoreo en Tiempo Real de Gestión por Cargo</p>
        </div>
        
        <div style="display: flex; align-items: center; gap: 1rem;">
            <a href="{{ route('inicio') }}" class="v2-back-link">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Volver a SIGTRAM
            </a>

            <div class="view-switch-tabs" style="display: flex; background: #f1f5f9; padding: 0.25rem; border-radius: 1rem;">
                <button type="button" class="btn-view-toggle active" id="btnDashboard" @click="switchView('dashboard')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    Dashboard
                </button>
                <button type="button" class="btn-view-toggle" id="btnReports" @click="switchView('reports')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    Reportes
                </button>
            </div>
            
        </div>
    </header>

    <form action="{{ route('mod_gm.v2_lista_reporte_area_cargo') }}" method="post" id="mainFilterForm">
        @csrf
        <!-- ===== NUEVO FILTRO ===== -->
        <div class="nf-bar">

            <!-- Búsqueda -->
            <div class="nf-search">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"/></svg>
                <input type="text" id="cardSearch" @input="onSearchInput($event.target.value)" placeholder="Buscar cargo o responsable…">
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
                    <button type="button" class="nf-presets-btn" @click.stop="presetsOpen = !presetsOpen" title="Periodos rápidos">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    </button>
                    <div id="nfPresetsMenu" class="nf-presets-menu" x-show="presetsOpen" x-cloak>
                        <button type="button" @click="setRange('today')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1M4.22 4.22l.7.7m12.02 12.02l.7.7M3 12h1m16 0h1M4.22 19.78l.7-.7M18.36 5.64l.7-.7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                            Hoy
                        </button>
                        <button type="button" @click="setRange('this_month')">
                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                            Este Mes
                        </button>
                        <button type="button" @click="setRange('last_3_months')">
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
            <button type="button" class="nf-chip active" @click="applySmartFilter('all', $event)">Todos</button>
            <button type="button" class="nf-chip excelente" @click="applySmartFilter('EXCELENTE', $event)">
                <span class="nf-dot" style="background:#475569;"></span>Excelente
            </button>
            <button type="button" class="nf-chip aldia" @click="applySmartFilter('AL DÍA', $event)">
                <span class="nf-dot" style="background:#0ea5e9;"></span>Al Día
            </button>
            <button type="button" class="nf-chip revisar" @click="applySmartFilter('REVISAR', $event)">
                <span class="nf-dot" style="background:#f59e0b;"></span>Revisar
            </button>
            <button type="button" class="nf-chip critico" @click="applySmartFilter('CRÍTICO', $event)">
                <span class="nf-dot" style="background:#ef4444;"></span>Crítico
            </button>
            <button type="button" class="nf-chip pendiente" id="pendingToggle" @click="togglePending()">
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
                            <button class="btn-toggle-team" type="button" @click="toggleTeamList({{ $index }})">
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
                <div class="metric-item warn {{ $row['entrada'] <= 0 ? 'disabled' : '' }}" @if($row['entrada'] > 0) @click="toggleDetails({{ $row['id'] }}, 1, {{ $index }})" @endif>
                    <span class="metric-val">{{ $row['entrada'] }}</span>
                    <span class="metric-lbl">Por Recibir</span>
                </div>
                <div class="metric-item info {{ $row['recibido'] <= 0 ? 'disabled' : '' }}" @if($row['recibido'] > 0) @click="toggleDetails({{ $row['id'] }}, 2, {{ $index }})" @endif>
                    <span class="metric-val">{{ $row['recibido'] }}</span>
                    <span class="metric-lbl">Recibidos</span>
                </div>
                <div class="metric-item prim {{ $row['salida'] <= 0 ? 'disabled' : '' }}" @if($row['salida'] > 0) @click="toggleDetails({{ $row['id'] }}, 3, {{ $index }})" @endif>
                    <span class="metric-val">{{ $row['salida'] }}</span>
                    <span class="metric-lbl">Derivados</span>
                </div>
                <div class="metric-item succ {{ $row['finalizado'] <= 0 ? 'disabled' : '' }}" @if($row['finalizado'] > 0) @click="toggleDetails({{ $row['id'] }}, 4, {{ $index }})" @endif>
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

<div id="v2OverlayOjo" class="v2-modal-overlay" hidden data-action="v2-cerrar-ojo">
    <div class="v2-modal-box" style="max-width:520px; height:80vh;">
        <div class="v2-modal-head">
            <h3>Expediente</h3>
            <button type="button" data-action="v2-cerrar-ojo" aria-label="Cerrar">&times;</button>
        </div>
        <iframe id="v2IframeOjo" title="Detalle del expediente"></iframe>
    </div>
</div>

<div id="v2OverlayHistorico" class="v2-modal-overlay" hidden data-action="v2-cerrar-historico">
    <div class="v2-modal-box" style="max-width:980px; height:80vh;">
        <div class="v2-modal-head">
            <h3 id="v2HistoricoTitulo">Histórico del Expediente</h3>
            <button type="button" data-action="v2-cerrar-historico" aria-label="Cerrar">&times;</button>
        </div>
        <iframe id="v2IframeHistorico" title="Histórico del expediente"></iframe>
    </div>
</div>

</body>
</html>
