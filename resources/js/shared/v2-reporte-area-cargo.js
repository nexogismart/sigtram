import flatpickr from 'flatpickr';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import Chart from 'chart.js/auto';
import { readRouteConfig } from './route-config.js';

// Setup delegated pagination and modal handlers for v2 detail tables
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="v2-pg"]');
    if (btn) {
        e.preventDefault();
        const uid = btn.dataset.uid;
        const page = parseInt(btn.dataset.page, 10);
        if (uid && typeof window['v2pg_' + uid] === 'function') {
            window['v2pg_' + uid](page);
        }
        return;
    }

    const ojoBtn = e.target.closest('[data-action="v2-abrir-ojo"]');
    if (ojoBtn) {
        e.preventDefault();
        const id = ojoBtn.dataset.id;
        if (typeof window.v2AbrirOjo === 'function') {
            window.v2AbrirOjo(id);
        }
        return;
    }

    const closeOjoBtn = e.target.closest('[data-action="v2-cerrar-ojo"]');
    if (closeOjoBtn) {
        if (closeOjoBtn.id === 'v2OverlayOjo' && e.target !== closeOjoBtn) {
            // Clicked inside modal box, ignore
        } else {
            e.preventDefault();
            if (typeof window.v2CerrarOjo === 'function') {
                window.v2CerrarOjo();
            }
            return;
        }
    }

    const histBtn = e.target.closest('[data-action="v2-abrir-historico"]');
    if (histBtn) {
        e.preventDefault();
        const id = histBtn.dataset.id;
        const titulo = histBtn.dataset.titulo || '';
        if (typeof window.v2AbrirHistorico === 'function') {
            window.v2AbrirHistorico(id, titulo);
        }
        return;
    }

    const closeHistBtn = e.target.closest('[data-action="v2-cerrar-historico"]');
    if (closeHistBtn) {
        if (closeHistBtn.id === 'v2OverlayHistorico' && e.target !== closeHistBtn) {
            // Clicked inside modal box, ignore
        } else {
            e.preventDefault();
            if (typeof window.v2CerrarHistorico === 'function') {
                window.v2CerrarHistorico();
            }
            return;
        }
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (typeof window.v2CerrarOjo === 'function') window.v2CerrarOjo();
        if (typeof window.v2CerrarHistorico === 'function') window.v2CerrarHistorico();
    }
});


export function setupV2Pagination(wrapper) {
    if (!wrapper) return;
    const uid = wrapper.dataset.uid;
    const table = document.getElementById(uid);
    if (!table) return;

    const perPage = parseInt(wrapper.dataset.perPage || '15', 10);
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const total = rows.length;
    const totalPages = Math.ceil(total / perPage);
    let curPage = 1;

    function render(page) {
        curPage = page;
        const start = (page - 1) * perPage;
        const end = start + perPage;
        rows.forEach((r, i) => {
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });
        buildPager();
    }

    function buildPager() {
        const pg = document.getElementById('pg_info_' + uid);
        if (!pg) return;
        if (totalPages <= 1) {
            pg.innerHTML = '';
            return;
        }

        const from = (curPage - 1) * perPage + 1;
        const to = Math.min(curPage * perPage, total);

        let html = '<span class="v2-pagination-info">Mostrando ' + from + ' – ' + to + ' de ' + total + ' registros</span>';
        html += '<div class="v2-pagination-btns">';
        html += '<button type="button" class="v2-pg-btn" data-action="v2-pg" data-uid="' + uid + '" data-page="' + (curPage - 1) + '" ' + (curPage === 1 ? 'disabled' : '') + '>‹ Ant.</button>';

        const startPg = Math.max(1, curPage - 3);
        const endPg = Math.min(totalPages, curPage + 3);
        if (startPg > 1) {
            html += '<button type="button" class="v2-pg-btn" data-action="v2-pg" data-uid="' + uid + '" data-page="1">1</button>';
            if (startPg > 2) html += '<span style="padding:0 4px;color:#94a3b8;">…</span>';
        }
        for (let i = startPg; i <= endPg; i++) {
            html += '<button type="button" class="v2-pg-btn' + (i === curPage ? ' active' : '') + '" data-action="v2-pg" data-uid="' + uid + '" data-page="' + i + '">' + i + '</button>';
        }
        if (endPg < totalPages) {
            if (endPg < totalPages - 1) html += '<span style="padding:0 4px;color:#94a3b8;">…</span>';
            html += '<button type="button" class="v2-pg-btn" data-action="v2-pg" data-uid="' + uid + '" data-page="' + totalPages + '">' + totalPages + '</button>';
        }
        html += '<button type="button" class="v2-pg-btn" data-action="v2-pg" data-uid="' + uid + '" data-page="' + (curPage + 1) + '" ' + (curPage === totalPages ? 'disabled' : '') + '>Sig. ›</button>';
        html += '</div>';
        pg.innerHTML = html;
    }

    window['v2pg_' + uid] = function(page) {
        if (page < 1 || page > totalPages) return;
        render(page);
        wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    render(1);
}

export function createV2ReporteAreaCargoComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('#v2-reporte-area-cargo-app');

    return {
        urlDetalle: config.urlDetalle || '',
        urlOjo: config.urlOjo || '',
        urlHistorico: config.urlHistorico || '',
        fechaIni: config.fechaIni || '',
        fechaFin: config.fechaFin || '',
        csrfToken: config.csrfToken || '',
        evoData: config.evoData || { labels: [], finalizados: [], pendientes: [], total_fin: 0, avg_fin: 0, trend_pct: 0 },
        
        currentView: 'dashboard',
        activeStatus: 'all',
        onlyPending: false,
        searchQuery: '',
        presetsOpen: false,
        chartsInitialized: false,

        init() {
            window.v2AbrirOjo = (id) => this.v2AbrirOjo(id);
            window.v2CerrarOjo = () => this.v2CerrarOjo();
            window.v2AbrirHistorico = (id, titulo) => this.v2AbrirHistorico(id, titulo);
            window.v2CerrarHistorico = () => this.v2CerrarHistorico();

            flatpickr('#txt_fe_inicio', {
                locale: Spanish,
                dateFormat: 'd/m/Y',
                allowInput: false,
                disableMobile: true
            });
            flatpickr('#txt_fe_fin', {
                locale: Spanish,
                dateFormat: 'd/m/Y',
                allowInput: false,
                disableMobile: true
            });

            // Close presets on click outside
            document.addEventListener('click', (e) => {
                const menu = document.getElementById('nfPresetsMenu');
                const btn = e.target.closest('.nf-presets-btn');
                if (menu && !menu.contains(e.target) && !btn) {
                    this.presetsOpen = false;
                }
            });
        },

        v2AbrirOjo(id) {
            const overlay = document.getElementById('v2OverlayOjo');
            const iframe = document.getElementById('v2IframeOjo');
            if (!overlay || !iframe) return;
            iframe.src = (this.urlOjo || '') + '?cdgo_tram=' + encodeURIComponent(id);
            overlay.hidden = false;
        },

        v2CerrarOjo() {
            const overlay = document.getElementById('v2OverlayOjo');
            const iframe = document.getElementById('v2IframeOjo');
            if (iframe) iframe.src = 'about:blank';
            if (overlay) overlay.hidden = true;
        },

        v2AbrirHistorico(id, titulo) {
            const overlay = document.getElementById('v2OverlayHistorico');
            const iframe = document.getElementById('v2IframeHistorico');
            const label = document.getElementById('v2HistoricoTitulo');
            if (!overlay || !iframe) return;
            if (label) label.textContent = titulo ? ('Histórico del Expediente N° ' + titulo) : 'Histórico del Expediente';
            const tmpl = this.urlHistorico || '';
            iframe.src = tmpl.replace('__HID__', encodeURIComponent(id));
            overlay.hidden = false;
        },

        v2CerrarHistorico() {
            const overlay = document.getElementById('v2OverlayHistorico');
            const iframe = document.getElementById('v2IframeHistorico');
            if (iframe) iframe.src = 'about:blank';
            if (overlay) overlay.hidden = true;
        },


        switchView(view) {
            this.currentView = view;
            const btnDash = document.getElementById('btnDashboard');
            const btnRep = document.getElementById('btnReports');
            const dashView = document.getElementById('dashboardView');
            const repView = document.getElementById('reportsView');
            const chips = document.getElementById('nfChips');

            if (view === 'dashboard') {
                if (btnRep) btnRep.classList.remove('active');
                if (btnDash) btnDash.classList.add('active');
                if (repView) { repView.classList.add('view-hidden'); repView.classList.remove('view-active'); }
                if (dashView) { dashView.classList.remove('view-hidden'); dashView.classList.add('view-active'); }
                if (chips) chips.style.display = 'flex';
            } else {
                if (btnDash) btnDash.classList.remove('active');
                if (btnRep) btnRep.classList.add('active');
                if (dashView) { dashView.classList.add('view-hidden'); dashView.classList.remove('view-active'); }
                if (repView) { repView.classList.remove('view-hidden'); repView.classList.add('view-active'); }
                if (chips) chips.style.display = 'none';

                setTimeout(() => this.initReports(), 300);
            }
        },

        togglePresets() {
            this.presetsOpen = !this.presetsOpen;
            const menu = document.getElementById('nfPresetsMenu');
            if (menu) {
                menu.style.display = this.presetsOpen ? 'block' : 'none';
            }
        },

        setRange(type) {
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

            const format = (d) => String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
            if (startInput) startInput.value = format(start);
            if (endInput) endInput.value = format(today);

            this.presetsOpen = false;
            const menu = document.getElementById('nfPresetsMenu');
            if (menu) menu.style.display = 'none';

            const form = document.getElementById('mainFilterForm');
            if (form) form.submit();
        },

        applySmartFilter(status) {
            this.activeStatus = status;
            document.querySelectorAll('.nf-chip').forEach(c => {
                if (c.id !== 'pendingToggle') c.classList.remove('active');
            });
            const target = event && event.currentTarget ? event.currentTarget : null;
            if (target) target.classList.add('active');

            this.filterCards();
        },

        togglePending() {
            this.onlyPending = !this.onlyPending;
            const el = document.getElementById('pendingToggle');
            if (el) el.classList.toggle('active', this.onlyPending);
            this.filterCards();
        },

        onSearchInput(val) {
            this.searchQuery = (val || '').toLowerCase();
            this.filterCards();
        },

        filterCards() {
            const cards = document.querySelectorAll('.data-card');
            cards.forEach(card => {
                const cardStatus = card.dataset.statusText;
                const cardPending = parseInt(card.dataset.pending || '0', 10);
                const cardSearch = (card.getAttribute('data-search-content') || '').toLowerCase();

                let show = true;
                if (this.activeStatus !== 'all' && cardStatus !== this.activeStatus) show = false;
                if (show && this.onlyPending && cardPending <= 0) show = false;
                if (show && this.searchQuery && !cardSearch.includes(this.searchQuery)) show = false;

                card.style.display = show ? '' : 'none';
            });
        },

        toggleTeamList(index) {
            const extra = document.getElementById('extra-team-' + index);
            const btn = event ? event.currentTarget : null;
            if (!extra) return;

            const isVisible = extra.style.display !== 'none' && extra.style.display !== '';
            extra.style.display = isVisible ? 'none' : 'flex';
            if (btn) {
                btn.classList.toggle('active', !isVisible);
                const span = btn.querySelector('span');
                if (span) {
                    span.textContent = isVisible ? ('Ver ' + extra.children.length + ' más') : 'Ocultar';
                }
            }
        },

        async toggleDetails(cargoId, tipo, index) {
            const pane = document.getElementById('detail-pane-' + index);
            const loader = document.getElementById('detail-loader-' + index);
            if (!pane || !loader) return;

            const isVisible = pane.style.display !== 'none' && pane.style.display !== '';
            if (isVisible) {
                pane.style.display = 'none';
                return;
            }

            document.querySelectorAll('.detail-expansion').forEach(p => p.style.display = 'none');
            pane.style.display = 'block';
            loader.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8;"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>';

            try {
                const res = await fetch(this.urlDetalle, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json, text/javascript, */*; q=0.01'
                    },
                    body: new URLSearchParams({
                        _token: this.csrfToken,
                        cargo_id: cargoId,
                        tipo_bandeja: tipo,
                        fecha_inicio: this.fechaIni,
                        fecha_fin: this.fechaFin
                    })
                });
                const data = await res.json();
                loader.innerHTML = data.html || '<div style="text-align:center;padding:2rem;color:#94a3b8;">Sin datos</div>';

                const detailWrapper = loader.querySelector('.v2-detail-wrapper, [data-uid]');
                if (detailWrapper) {
                    setupV2Pagination(detailWrapper);
                }
            } catch (err) {
                loader.innerHTML = '<div style="text-align:center;padding:2rem;color:#ef4444;">Error al cargar el detalle</div>';
            }
        },

        initReports() {
            const cards = document.querySelectorAll('.data-card');
            let stats = { EXCELENTE: 0, 'AL DÍA': 0, REVISAR: 0, 'CRÍTICO': 0 };
            let workloads = [];
            let totalDocs = 0, totalPending = 0, totalCompleted = 0, totalEfficiency = 0;

            cards.forEach(card => {
                const status = card.dataset.statusText;
                const pending = parseInt(card.dataset.pending || '0', 10);
                const total = parseInt(card.dataset.total || '0', 10);
                const performance = parseFloat(card.dataset.performance || '0');
                const cargoEl = card.querySelector('.cargo-name');
                const cargo = cargoEl ? cargoEl.textContent.trim() : '';
                const responsablesLabel = card.getAttribute('data-responsables') || 'Sin asignar';

                if (stats[status] !== undefined) stats[status]++;
                totalDocs += total;
                totalPending += pending;
                totalCompleted += (total - pending);
                totalEfficiency += performance;

                workloads.push({ label: cargo, total: total, pending: pending, status: status, performance: performance, responsables: responsablesLabel });
            });

            const globalEfficiency = totalDocs ? Math.round((totalCompleted / totalDocs) * 100) : 0;
            const totalCargos = cards.length;

            const repTotal = document.getElementById('rep-kpi-total');
            if (repTotal) repTotal.innerText = totalDocs;
            const repPending = document.getElementById('rep-kpi-pending');
            if (repPending) repPending.innerText = totalPending;
            const repCompleted = document.getElementById('rep-kpi-completed');
            if (repCompleted) repCompleted.innerText = totalCompleted;
            const repEff = document.getElementById('rep-kpi-efficiency');
            if (repEff) repEff.innerText = globalEfficiency + '%';

            const donutTotalEl = document.getElementById('donut-total');
            if (donutTotalEl) donutTotalEl.innerText = totalCargos;

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

            // Populate cargo detail table
            const detailBody = document.querySelector('#cargoDetailTable tbody');
            if (detailBody) {
                detailBody.innerHTML = '';
                const sortedDetails = [...workloads].sort((a,b) => (b.pending - a.pending) || (b.total - a.total));

                sortedDetails.forEach(w => {
                    const completed = w.total - w.pending;
                    const efficiency = w.total > 0 ? Math.round((completed / w.total) * 100) : 100;

                    let rowStyle = '';
                    let statusBadge = '';
                    let trendIcon = '→';
                    let trendColor = '#94a3b8';

                    if (w.total === 0) {
                        rowStyle = 'opacity: 0.5; background: #f8fafc;';
                        statusBadge = '<span style="background:#f1f5f9; color:#64748b; padding:4px 10px; border-radius:6px; font-size:0.65rem; font-weight:800;">SIN ACTIVIDAD</span>';
                    } else if (w.pending > 5 || (efficiency < 50 && w.total > 2)) {
                        rowStyle = 'background: rgba(239, 68, 68, 0.02);';
                        statusBadge = '<span style="background:#fef2f2; color:#ef4444; padding:4px 10px; border-radius:6px; font-size:0.65rem; font-weight:800; border:1px solid #fee2e2;">CRÍTICO</span>';
                        trendIcon = '↓';
                        trendColor = '#ef4444';
                    } else if (efficiency < 85) {
                        statusBadge = '<span style="background:#fffbeb; color:#f59e0b; padding:4px 10px; border-radius:6px; font-size:0.65rem; font-weight:800; border:1px solid #fef3c7;">AL DÍA</span>';
                        trendIcon = '→';
                        trendColor = '#f59e0b';
                    } else {
                        statusBadge = '<span style="background:#f8fafc; color:#475569; padding:4px 10px; border-radius:6px; font-size:0.65rem; font-weight:800; border:1px solid #e2e8f0;">EXCELENTE</span>';
                        trendIcon = '↑';
                        trendColor = '#475569';
                    }

                    detailBody.innerHTML += `
                        <tr style="${rowStyle}">
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
                    `;
                });
            }

            if (!this.chartsInitialized) {
                Chart.defaults.font.family = "'Figtree'";
                Chart.defaults.color = "#64748b";

                const ctxStatus = document.getElementById('statusChart');
                if (ctxStatus) {
                    new Chart(ctxStatus, {
                        type: 'doughnut',
                        data: {
                            labels: ['Excelente', 'Al Día', 'Revisar', 'Crítico'],
                            datasets: [{
                                data: [stats.EXCELENTE, stats['AL DÍA'], stats.REVISAR, stats['CRÍTICO']],
                                backgroundColor: ['#475569', '#0ea5e9', '#f59e0b', '#ef4444'],
                                borderWidth: 0,
                                cutout: '82%'
                            }]
                        },
                        options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
                    });
                }

                const ctxEvo = document.getElementById('evolutionChart');
                if (ctxEvo) {
                    new Chart(ctxEvo, {
                        type: 'line',
                        data: {
                            labels: this.evoData.labels || [],
                            datasets: [
                                {
                                    label: 'Finalizados',
                                    data: this.evoData.finalizados || [],
                                    borderColor: '#475569',
                                    borderWidth: 3,
                                    tension: 0.4,
                                    fill: false
                                },
                                {
                                    label: 'Pendientes',
                                    data: this.evoData.pendientes || [],
                                    borderColor: '#f59e0b',
                                    borderWidth: 2,
                                    borderDash: [5, 5],
                                    tension: 0.4,
                                    fill: false
                                }
                            ]
                        },
                        options: {
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                                x: { grid: { display: false } }
                            }
                        }
                    });

                    const evoTotalEl = document.getElementById('evo-total');
                    if (evoTotalEl) evoTotalEl.innerText = this.evoData.total_fin || '0';
                    const evoAvgEl = document.getElementById('evo-avg');
                    if (evoAvgEl) evoAvgEl.innerText = (this.evoData.avg_fin || '0') + ' / mes';
                    const evoTrendEl = document.getElementById('evo-trend-pct');
                    if (evoTrendEl) {
                        const trend = this.evoData.trend_pct || 0;
                        evoTrendEl.innerText = (trend >= 0 ? '+' : '') + trend + '%';
                    }
                }

                const ctxWorkload = document.getElementById('workloadChart');
                if (ctxWorkload) {
                    const sortedWorkloads = [...workloads].sort((a,b) => b.pending - a.pending).slice(0, 10);
                    const palette = ['#ef4444', '#f97316', '#f59e0b', '#eab308', '#94a3b8', '#475569', '#06b6d4', '#3b82f6', '#334155', '#94a3b8'];

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
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { color: '#f1f5f9' } },
                                y: { grid: { display: false } }
                            }
                        }
                    });
                }

                const ctxArea = document.getElementById('areaChart');
                if (ctxArea) {
                    new Chart(ctxArea, {
                        type: 'polarArea',
                        data: {
                            labels: ['ASESORÍA JURÍDICA', 'ADMINISTRACIÓN Y FINANZAS', 'DESARROLLO URBANO', 'FISCALIZACIÓN Y CONTROL'],
                            datasets: [{
                                data: [11, 16, 7, 14],
                                backgroundColor: ['rgba(99, 102, 241, 0.7)', 'rgba(71, 85, 105, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(239, 68, 68, 0.7)'],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                    });
                }

                this.chartsInitialized = true;
            }
        }
    };
}
