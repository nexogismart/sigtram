@php
    /** @var array $totales */
    $pct = (float) ($totales['pct_trabajo'] ?? 0);
    $pctClase = $pct >= 80 ? 'ok' : ($pct >= 50 ? 'warn' : 'bad');
    $milestoneColors = ['#0d9488', '#0ea5e9', '#6366f1', '#8b5cf6', '#f59e0b', '#ec4899', '#14b8a6', '#334155'];
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sigtram | Requerimientos de servicios</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --ink: #0f172a;
            --ink-2: #334155;
            --muted: #64748b;
            --faint: #94a3b8;
            --line: #e6ecf2;
            --bg: #f6f8fb;
            --card: #ffffff;
            --teal: #0d9488;
            --teal-soft: #ccfbf1;
            --teal-dark: #0f766e;
            --ok: #10b981;
            --warn: #f59e0b;
            --bad: #ef4444;
            --info: #0ea5e9;
            --violet: #6366f1;
            --radius: 14px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Figtree", "Segoe UI", Tahoma, sans-serif;
            background: var(--bg);
            color: var(--ink-2);
            font-size: 14px;
        }

        h1, h2, h3, .num { font-family: "Outfit", "Figtree", sans-serif; }

        .wrap { max-width: 1600px; margin: 0 auto; padding: 1.25rem 1.5rem 4rem; }

        /* ── Cabecera ─────────────────────────────────────────── */
        .top {
            display: flex; flex-wrap: wrap; gap: 1rem;
            align-items: flex-end; justify-content: space-between; margin-bottom: 1.1rem;
        }
        .top h1 { margin: 0; font-size: 1.6rem; font-weight: 800; letter-spacing: -.02em; color: var(--ink); }
        .top h1 span { color: var(--teal); }
        .top p { margin: .2rem 0 0; font-size: .8rem; font-weight: 600; color: var(--muted); }
        .top__actions { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }

        .btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .5rem .9rem; border-radius: 10px; border: 1px solid var(--line);
            background: var(--card); color: var(--ink-2); font: inherit;
            font-size: .78rem; font-weight: 700; cursor: pointer; text-decoration: none;
            transition: background .15s, border-color .15s, color .15s;
        }
        .btn:hover { border-color: var(--teal); color: var(--teal-dark); }
        .btn--primary { background: var(--teal); border-color: var(--teal); color: #fff; }
        .btn--primary:hover { background: var(--teal-dark); border-color: var(--teal-dark); color: #fff; }
        .btn--export { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }

        .switch { display: inline-flex; background: #eef2f7; padding: 3px; border-radius: 12px; gap: 3px; }
        .switch button {
            border: none; background: transparent; font: inherit; font-size: .78rem; font-weight: 700;
            color: var(--muted); padding: .45rem .85rem; border-radius: 9px; cursor: pointer;
        }
        .switch button.is-active { background: var(--card); color: var(--teal-dark); box-shadow: 0 1px 3px rgba(15,23,42,.12); }

        /* ── Barra de filtros ─────────────────────────────────── */
        .filters {
            display: flex; flex-wrap: wrap; align-items: flex-end; gap: .75rem;
            background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
            padding: .75rem .9rem; margin-bottom: .75rem;
        }
        .field { display: flex; flex-direction: column; gap: .25rem; }
        .field label { font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--faint); }
        .field input[type="date"], .field input[type="search"] {
            border: 1px solid var(--line); border-radius: 9px; padding: .42rem .6rem;
            font: inherit; font-size: .8rem; font-weight: 600; color: var(--ink); background: #fbfdff;
        }
        .field input:focus { outline: 2px solid var(--teal-soft); border-color: var(--teal); }
        .field--grow { flex: 1 1 220px; }
        .field--grow input { width: 100%; }
        .filters__presets { display: flex; gap: .35rem; flex-wrap: wrap; }
        .filters__presets button {
            border: 1px dashed var(--line); background: #fbfdff; color: var(--muted);
            font: inherit; font-size: .7rem; font-weight: 700; padding: .35rem .6rem; border-radius: 8px; cursor: pointer;
        }
        .filters__presets button:hover { border-color: var(--teal); color: var(--teal-dark); }

        .chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: 1rem; }
        .chip {
            border: 1px solid var(--line); background: var(--card); color: var(--muted);
            font: inherit; font-size: .72rem; font-weight: 700; padding: .35rem .75rem;
            border-radius: 999px; cursor: pointer; display: inline-flex; align-items: center; gap: .35rem;
        }
        .chip.is-active { background: var(--ink); border-color: var(--ink); color: #fff; }
        .chip__dot { width: 7px; height: 7px; border-radius: 50%; }

        /* ── KPIs ─────────────────────────────────────────────── */
        .kpis { display: grid; grid-template-columns: repeat(6, 1fr); gap: .75rem; margin-bottom: 1.25rem; }
        @media (max-width: 1200px) { .kpis { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 700px) { .kpis { grid-template-columns: repeat(2, 1fr); } }
        .kpi {
            background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
            padding: .8rem .95rem; display: flex; flex-direction: column; gap: .1rem;
        }
        .kpi__label { font-size: .64rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--faint); }
        .kpi__value { font-size: 1.6rem; font-weight: 800; color: var(--ink); line-height: 1.1; }
        .kpi__hint { font-size: .62rem; font-weight: 600; color: var(--faint); }
        .kpi--ink { border-top: 3px solid var(--ink); }
        .kpi--warn { border-top: 3px solid var(--warn); }
        .kpi--info { border-top: 3px solid var(--info); }
        .kpi--violet { border-top: 3px solid var(--violet); }
        .kpi--ok { border-top: 3px solid var(--ok); }
        .kpi--pct.ok .kpi__value { color: var(--ok); }
        .kpi--pct.warn .kpi__value { color: var(--warn); }
        .kpi--pct.bad .kpi__value { color: var(--bad); }

        /* ── Vistas ───────────────────────────────────────────── */
        .view { display: none; }
        .view.is-active { display: block; }

        .section-head {
            display: flex; flex-wrap: wrap; align-items: center; gap: .6rem;
            justify-content: space-between; margin: 0 0 .8rem;
        }
        .section-head h2 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--ink); }
        .section-head__meta { font-size: .78rem; font-weight: 700; color: var(--muted); }

        /* Tarjetas por área */
        .area-list { display: flex; flex-direction: column; gap: .65rem; }
        .area-card {
            background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
            padding: .85rem 1.1rem; display: grid; gap: 1rem;
            grid-template-columns: minmax(200px, 1.1fr) minmax(160px, .9fr) minmax(340px, 1.7fr) 130px;
            align-items: center;
        }
        @media (max-width: 1100px) { .area-card { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 640px) { .area-card { grid-template-columns: 1fr; } }
        .area-card__tag { font-size: .6rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--teal-dark); background: var(--teal-soft); padding: .18rem .45rem; border-radius: 6px; display: inline-block; }
        .area-card__name { margin: .35rem 0 0; font-size: 1rem; font-weight: 700; color: var(--ink); line-height: 1.25; }
        .team__label { font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--faint); }
        .team__item { display: flex; align-items: center; gap: .45rem; font-size: .76rem; font-weight: 600; color: var(--ink-2); margin-top: .3rem; }
        .avatar { width: 22px; height: 22px; border-radius: 50%; background: var(--teal); color: #fff; font-size: .55rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .team__more { margin-top: .35rem; border: 1px solid var(--line); background: #fbfdff; color: var(--teal-dark); font: inherit; font-size: .66rem; font-weight: 700; padding: .2rem .55rem; border-radius: 7px; cursor: pointer; }
        .team__extra { display: none; margin-top: .3rem; max-height: 160px; overflow-y: auto; padding-right: .2rem; border-top: 1px dashed var(--line); }
        .team__extra.is-open { display: block; }
        .team__empty { font-size: .72rem; font-weight: 700; color: var(--bad); margin-top: .3rem; }

        .metrics { display: grid; grid-template-columns: repeat(5, 1fr); gap: .4rem; background: #f8fafc; border-radius: 12px; padding: .5rem; }
        .metric { border: none; background: transparent; font: inherit; display: flex; flex-direction: column; align-items: center; gap: .1rem; padding: .35rem .2rem; border-radius: 9px; cursor: pointer; }
        .metric:hover:not(:disabled) { background: var(--card); box-shadow: 0 2px 8px rgba(15,23,42,.06); }
        .metric:disabled { cursor: default; opacity: .55; }
        .metric__val { font-family: "Outfit", sans-serif; font-size: 1.1rem; font-weight: 800; color: var(--ink); }
        .metric__lbl { font-size: .56rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--faint); text-align: center; }
        .metric--warn .metric__val { color: var(--warn); }
        .metric--info .metric__val { color: var(--info); }
        .metric--violet .metric__val { color: var(--violet); }
        .metric--ok .metric__val { color: var(--ok); }

        .perf { display: flex; flex-direction: column; align-items: center; gap: .3rem; }
        .perf__value { font-family: "Outfit", sans-serif; font-size: 1.35rem; font-weight: 800; line-height: 1; }
        .perf__label { font-size: .56rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--faint); }
        .perf__status { font-size: .6rem; font-weight: 800; padding: .18rem .5rem; border-radius: 999px; }
        .perf__pend {
            display: inline-flex; align-items: center; gap: .25rem;
            font-size: .68rem; font-weight: 800; color: #ef4444;
            background: rgba(239,68,68,.07); border: 1px solid rgba(239,68,68,.15);
            border-radius: 8px; padding: .2rem .5rem; margin-top: .2rem;
        }
        .perf__ok {
            display: inline-flex; align-items: center; gap: .25rem;
            font-size: .68rem; font-weight: 800; color: #10b981;
            background: rgba(16,185,129,.07); border: 1px solid rgba(16,185,129,.15);
            border-radius: 8px; padding: .2rem .5rem; margin-top: .2rem;
        }
        .rep-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem; margin-bottom: 1.1rem; }
        @media (max-width: 900px) { .rep-kpis { grid-template-columns: repeat(2, 1fr); } }
        .rep-kpi {
            background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
            padding: .85rem 1rem;
        }
        .rep-kpi h4 { margin: 0; font-size: .64rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--faint); }
        .rep-kpi .stat { font-size: 1.7rem; font-weight: 800; color: var(--ink); line-height: 1.1; margin: .15rem 0; }
        .rep-kpi p { margin: 0; font-size: .65rem; font-weight: 600; color: var(--muted); }
        .analytics-split { display: grid; grid-template-columns: 1.1fr .9fr; gap: 1rem; align-items: center; }
        @media (max-width: 900px) { .analytics-split { grid-template-columns: 1fr; } }
        .chart-main { position: relative; max-width: 280px; margin: 0 auto; }
        .donut-center-label {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; pointer-events: none;
        }
        .donut-center-label .total-num { font-size: 1.4rem; font-weight: 800; color: var(--ink); }
        .donut-center-label .total-lbl { font-size: .62rem; font-weight: 700; color: var(--faint); text-transform: uppercase; }
        .side-metric-item { margin-bottom: .65rem; }
        .side-metric-info { display: flex; justify-content: space-between; gap: .5rem; margin-bottom: .2rem; }
        .side-metric-label { display: flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 800; color: var(--ink-2); }
        .side-metric-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .side-metric-value { font-size: .72rem; font-weight: 700; color: var(--muted); }
        .side-progress-bg { height: 6px; background: #eef2f7; border-radius: 99px; overflow: hidden; }
        .side-progress-fill { height: 100%; border-radius: 99px; }
        .chart-box { height: 300px; }
        .st-EXCELENTE { background: #dcfce7; color: #15803d; }
        .st-ALDIA { background: #e0f2fe; color: #0369a1; }
        .st-REVISAR { background: #fef3c7; color: #b45309; }
        .st-CRITICO { background: #fee2e2; color: #b91c1c; }

        .area-card__foot { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: .5rem; border-top: 1px solid #f1f5f9; padding-top: .5rem; }
        .area-card__detail { grid-column: 1 / -1; display: none; border-top: 1px dashed var(--line); padding-top: .6rem; }
        .area-card__detail.is-open { display: block; }

        /* Reporte por áreas — píldoras de hitos */
        .ml-card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: .9rem 1rem; margin-bottom: .75rem; }
        .ml-card__head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .5rem; padding-bottom: .6rem; margin-bottom: .7rem; border-bottom: 1px solid #f1f5f9; }
        .ml-card__head h3 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ink); }
        .ml-card__meta { font-size: .74rem; font-weight: 700; color: var(--muted); }
        .ml-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 10.5rem), 1fr)); gap: .55rem; }
        .ml-pill {
            display: flex; flex-direction: column; gap: .25rem; text-align: left;
            background: linear-gradient(180deg, #fbfdff, #f4f7fb); border: 1px solid var(--line);
            border-radius: 12px; padding: .55rem .6rem; font: inherit; cursor: default;
        }
        .ml-pill--clickable { cursor: pointer; }
        .ml-pill--clickable:hover { border-color: var(--teal); box-shadow: 0 3px 12px rgba(13,148,136,.14); }
        .ml-pill__label { font-size: .58rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); line-height: 1.25; }
        .ml-pill__area { font-size: .6rem; font-weight: 700; color: var(--teal-dark); line-height: 1.2; overflow-wrap: anywhere; }
        .ml-pill__pct { font-family: "Outfit", sans-serif; font-size: 1.1rem; font-weight: 800; color: var(--ink); line-height: 1; margin-top: auto; }
        .ml-pill__count { font-size: .6rem; font-weight: 700; color: var(--faint); }
        .ml-pill__here { font-size: .58rem; font-weight: 700; color: var(--teal-dark); }
        .ml-bar { height: 6px; background: #e6ecf2; border-radius: 999px; overflow: hidden; margin-top: .3rem; }
        .ml-bar__fill { height: 100%; border-radius: 999px; }

        /* Vista Reportes */
        .panel { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem; }
        .dist { display: grid; gap: .6rem; }
        .dist__row { display: grid; gap: .25rem; }
        .dist__info { display: flex; justify-content: space-between; align-items: center; font-size: .75rem; font-weight: 700; color: var(--ink-2); }
        .dist__track { height: 7px; background: #eef2f7; border-radius: 999px; overflow: hidden; }
        .dist__fill { height: 100%; border-radius: 999px; }
        .rank { display: grid; gap: .45rem; max-height: 340px; overflow-y: auto; }
        .rank__row { display: grid; grid-template-columns: 1fr 46px; gap: .5rem; align-items: center; font-size: .75rem; font-weight: 700; color: var(--ink-2); }
        .rank__bar { height: 6px; background: #eef2f7; border-radius: 999px; overflow: hidden; margin-top: .2rem; }
        .rank__fill { height: 100%; background: var(--teal); border-radius: 999px; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        @media (max-width: 1000px) { .grid-2 { grid-template-columns: 1fr; } }

        .tbl-scroll { overflow: auto; max-height: min(70vh, 620px); border: 1px solid var(--line); border-radius: 12px; }
        table.tbl { width: 100%; border-collapse: collapse; font-size: .76rem; }
        table.tbl th {
            position: sticky; top: 0; z-index: 1; background: #f8fafc; text-align: left;
            padding: .5rem .55rem; font-size: .58rem; font-weight: 800; letter-spacing: .05em;
            text-transform: uppercase; color: var(--faint); border-bottom: 1px solid var(--line);
        }
        table.tbl td { padding: .45rem .55rem; border-bottom: 1px solid #f4f7fa; color: var(--ink-2); vertical-align: top; }
        table.tbl tbody tr:hover td { background: #fbfdff; }

        /* ── Detalle AJAX (partial) ───────────────────────────── */
        .req-detail-wrap { background: #fbfdff; border: 1px solid var(--line); border-radius: 12px; padding: .6rem .7rem; }
        .rd-scroll { overflow-x: auto; }
        table.req-detail-table { width: 100%; border-collapse: collapse; font-size: .74rem; }
        table.req-detail-table th {
            background: #f8fafc; text-align: left; padding: .38rem .5rem; color: var(--faint);
            font-size: .54rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
            border-bottom: 1px solid var(--line); white-space: nowrap;
        }
        table.req-detail-table td { padding: .42rem .5rem; border-bottom: 1px solid #f4f7fa; vertical-align: top; }
        table.req-detail-table tbody tr:hover td { background: #f8fafc; }
        .rd-th-sel { width: 34px; text-align: center; }
        .rd-center { text-align: center; }
        .rd-nowrap { white-space: nowrap; }
        .rd-strong { font-weight: 700; color: var(--ink); }
        .rd-muted { color: var(--muted); font-weight: 600; }
        .rd-empty { color: var(--faint); font-style: italic; font-weight: 600; }
        .rd-exp { font-weight: 800; color: var(--teal-dark); text-decoration: none; border-bottom: 2px dashed var(--teal); }
        .rd-asunto { max-width: 220px; line-height: 1.35; }
        .rd-locador { max-width: 190px; line-height: 1.3; }
        .rd-oper { max-width: 210px; line-height: 1.3; font-weight: 600; color: var(--ink-2); }
        .rd-area-sol { font-weight: 700; color: var(--ink-2); max-width: 150px; }
        .rd-area-act { font-weight: 700; color: var(--teal-dark); }
        .rd-cargo { max-width: 140px; color: var(--ink-2); font-weight: 600; }
        .rd-estado { max-width: 170px; }
        .rd-badge { display: inline-block; padding: .2rem .42rem; border-radius: 6px; font-size: .64rem; font-weight: 800; line-height: 1.25; }
        .rd-badge--success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .rd-badge--process { background: #ffedd5; color: #c2410c; border: 1px solid #fdba74; }
        .rd-badge--warn { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
        .rd-badge--muted { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .rd-badge--finance { background: #cffafe; color: #0e7490; border: 1px solid #67e8f9; }
        .rd-badge--default { background: var(--teal-soft); color: var(--teal-dark); border: 1px solid #99f6e4; }
        .rd-cod { display: block; font-size: .56rem; color: var(--faint); font-weight: 700; margin-top: 2px; }
        .rd-ruta { display: flex; flex-wrap: wrap; gap: 3px; align-items: center; max-width: 260px; }
        .rd-chip { background: var(--teal-soft); color: var(--teal-dark); padding: 1px 5px; border-radius: 5px; font-size: .64rem; font-weight: 700; white-space: nowrap; }
        .rd-arrow { color: var(--faint); font-weight: 800; }
        .rd-dias { display: inline-block; padding: .15rem .38rem; border-radius: 5px; font-weight: 700; background: #f1f5f9; color: var(--ink-2); }
        .rd-dias--alto { background: #fee2e2; color: #b91c1c; }
        .rd-eye { display: inline-flex; align-items: center; justify-content: center; width: 25px; height: 25px; border-radius: 7px; background: #f1f5f9; color: var(--ink-2); text-decoration: none; }
        .rd-eye:hover { background: var(--teal-soft); color: var(--teal-dark); }
        .rd-vacio { text-align: center; padding: 1.1rem; color: var(--faint); font-weight: 600; }
        .rd-hito-note { margin-bottom: .6rem; padding: .5rem .7rem; border-radius: 10px; background: #f0fdfa; border: 1px solid #99f6e4; }
        .rd-hito-note__title { font-size: .76rem; font-weight: 800; color: var(--teal-dark); }
        .rd-hito-note__text { font-size: .66rem; font-weight: 600; color: var(--muted); margin-top: .2rem; line-height: 1.4; }
        .req-det-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .5rem; padding-top: .5rem; margin-top: .35rem; border-top: 1px solid #f1f5f9; }
        .rd-pager-info { font-size: .7rem; font-weight: 700; color: var(--muted); }
        .rd-pager-btns { display: flex; gap: .4rem; }
        .rd-btn { border: 1px solid var(--line); background: var(--card); color: var(--ink-2); font: inherit; font-size: .72rem; font-weight: 800; padding: .35rem .8rem; border-radius: 8px; cursor: pointer; }
        .rd-btn--primary { background: var(--teal); border-color: var(--teal); color: #fff; }
        .rd-btn--export { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
        .rd-export-row { display: flex; justify-content: center; padding-top: .45rem; }
        .rd-filter { padding: .5rem .6rem; background: linear-gradient(180deg, #f8fafc, #fff); border: 1px solid var(--line); border-radius: 10px; margin-bottom: .5rem; }
        .rd-filter__top { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .4rem; }
        .rd-filter__lbl { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 800; color: var(--ink-2); cursor: pointer; }
        .rd-filter__days { display: flex; flex-wrap: wrap; gap: .35rem; max-height: 9rem; overflow-y: auto; }
        .rd-day { display: inline-flex; align-items: center; gap: .25rem; padding: .22rem .45rem; border-radius: 999px; border: 1px solid var(--line); background: #fff; font-size: .68rem; font-weight: 700; color: var(--ink-2); cursor: pointer; }
        .rd-filter__hint { margin: .35rem 0 0; font-size: .62rem; color: var(--faint); line-height: 1.4; }
        .req-detail-msg { padding: .9rem; font-weight: 700; text-align: center; }
        .req-detail-msg--error { color: #b91c1c; }

        /* ── Modal de hitos ───────────────────────────────────── */
        .modal { position: fixed; inset: 0; z-index: 500; display: none; }
        .modal.is-open { display: block; }
        .modal__backdrop { position: absolute; inset: 0; background: rgba(15,23,42,.45); }
        .modal__panel { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; background: #fff; }
        .modal__head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1.1rem; border-bottom: 1px solid var(--line); background: #fbfdff; }
        .modal__head h3 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ink); }
        .modal__close { width: 2.1rem; height: 2.1rem; border: none; border-radius: 9px; background: #f1f5f9; color: var(--ink-2); font-size: 1.3rem; line-height: 1; cursor: pointer; }
        .modal__body { flex: 1; overflow: auto; padding: .8rem 1rem 1.2rem; }

        .alert { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: .7rem .9rem; border-radius: 10px; font-weight: 700; font-size: .8rem; margin-bottom: 1rem; }
        .loading { text-align: center; padding: 1rem; color: var(--faint); font-weight: 600; font-size: .78rem; }
        footer { margin-top: 3rem; border-top: 1px solid var(--line); padding-top: 1.25rem; text-align: center; color: var(--faint); font-size: .74rem; font-weight: 600; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_a.sgl.pago_proveedores.v2_lista_reporte_requerimientos_general'])
</head>
<body data-sigtram-screen="mod_a.sgl.pago_proveedores.v2_lista_reporte_requerimientos_general" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_a.sgl.pago_proveedores.v2_lista_reporte_requerimientos_general'])
<div class="wrap">

    <header class="top">
        <div>
            <h1>SIGTRAM <span>Logística</span></h1>
            <p>Monitoreo de requerimientos de servicios · vista por área</p>
        </div>
        <div class="top__actions">
            <a class="btn" href="{{ $rutas['inicio'] }}">← Volver a Sigtram</a>
            <div class="switch" role="tablist">
                <button type="button" id="tabDashboard" class="is-active" onclick="switchView('dashboard')">Dashboard</button>
                <button type="button" id="tabReportes" onclick="switchView('reportes')">Reportes</button>
                <button type="button" id="tabAreas" onclick="switchView('areas')">Reporte por Áreas</button>
            </div>
        </div>
    </header>

    @if ($error)
        <div class="alert">{{ $error }}</div>
    @endif

    <form class="filters" method="get" action="{{ $rutas['index'] }}" id="filtroForm" autocomplete="off">
        <div class="field">
            <label for="fe_inicio">Desde</label>
            <input type="date" id="fe_inicio" name="fe_inicio" value="{{ $feInicio }}" required>
        </div>
        <div class="field">
            <label for="fe_fin">Hasta</label>
            <input type="date" id="fe_fin" name="fe_fin" value="{{ $feFin }}" required>
        </div>
        <div class="field">
            <label>Períodos rápidos</label>
            <div class="filters__presets">
                <button type="button" onclick="setRange('hoy')">Hoy</button>
                <button type="button" onclick="setRange('mes')">Este mes</button>
                <button type="button" onclick="setRange('trimestre')">Últimos 3 meses</button>
                <button type="button" onclick="setRange('anio')">Este año</button>
            </div>
        </div>
        <div class="field field--grow" id="buscadorCampo">
            <label for="buscador">Buscar área o locador</label>
            <input type="search" id="buscador" placeholder="Escriba para filtrar las tarjetas…">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn--primary" type="submit">Sincronizar</button>
        </div>
    </form>

    <div class="chips" id="chipsRow">
        <button type="button" class="chip is-active" data-status="all" onclick="filtrarEstado(this)">Todos</button>
        <button type="button" class="chip" data-status="EXCELENTE" onclick="filtrarEstado(this)"><span class="chip__dot" style="background:#10b981"></span>Excelente</button>
        <button type="button" class="chip" data-status="AL DÍA" onclick="filtrarEstado(this)"><span class="chip__dot" style="background:#0ea5e9"></span>Al día</button>
        <button type="button" class="chip" data-status="REVISAR" onclick="filtrarEstado(this)"><span class="chip__dot" style="background:#f59e0b"></span>Revisar</button>
        <button type="button" class="chip" data-status="CRÍTICO" onclick="filtrarEstado(this)"><span class="chip__dot" style="background:#ef4444"></span>Crítico</button>
        <button type="button" class="chip" id="chipPendiente" onclick="togglePendientes(this)">Solo con «por iniciar»</button>
    </div>

    {{-- ══════════════ DASHBOARD (KPIs solo aquí, como el antiguo) ══════════════ --}}
    <section id="viewDashboard" class="view is-active">
        <section class="kpis">
            <div class="kpi kpi--ink">
                <span class="kpi__label">Total</span>
                <span class="kpi__value">{{ number_format($totales['total']) }}</span>
                <span class="kpi__hint">Requerimientos del período</span>
            </div>
            <div class="kpi kpi--warn">
                <span class="kpi__label">Por iniciar</span>
                <span class="kpi__value">{{ number_format($totales['por_iniciar']) }}</span>
                <span class="kpi__hint">Sin tramitar aún</span>
            </div>
            <div class="kpi kpi--info">
                <span class="kpi__label">En trámite</span>
                <span class="kpi__value">{{ number_format($totales['en_tramite']) }}</span>
                <span class="kpi__hint">En circuito documental</span>
            </div>
            <div class="kpi kpi--violet">
                <span class="kpi__label">En proceso</span>
                <span class="kpi__value">{{ number_format($totales['en_proceso']) }}</span>
                <span class="kpi__hint">Conformidad / devengado</span>
            </div>
            <div class="kpi kpi--ok">
                <span class="kpi__label">Finalizados</span>
                <span class="kpi__value">{{ number_format($totales['finalizados']) }}</span>
                <span class="kpi__hint">Requerimientos cerrados</span>
            </div>
            <div class="kpi kpi--pct {{ $pctClase }}">
                <span class="kpi__label">% de trabajo</span>
                <span class="kpi__value">{{ $pct }}%</span>
                <span class="kpi__hint">(Trám. + Proc. + Fin.) / Total</span>
            </div>
        </section>

        <div class="section-head">
            <h2>Detalle de gestión por área</h2>
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;">
                <button type="button" class="btn btn--export" onclick="exportDetalle(-1, 0)"
                        title="CSV con todos los requerimientos del período (todas las áreas)">Exportar CSV (todas las áreas)</button>
                <span class="section-head__meta">{{ count($filas) }} áreas activas</span>
            </div>
        </div>

        <div class="area-list" id="areaList">
            @forelse ($filas as $index => $fila)
                @php
                    $statusKey = str_replace([' ', 'Í'], ['', 'I'], $fila['status_text']);
                    $locadores = $fila['locadores'];
                    $busqueda = mb_strtolower($fila['area'] . ' ' . implode(' ', $locadores));
                    $pctFila = $fila['total'] > 0 ? round(($fila['gestionados'] / $fila['total']) * 100, 1) : 0;
                    $colorFila = $pctFila >= 80 ? 'var(--ok)' : ($pctFila >= 50 ? 'var(--warn)' : 'var(--bad)');
                @endphp
                @php $pctPend = round(100 - $pctFila, 1); @endphp
                <article class="area-card"
                         data-search="{{ $busqueda }}"
                         data-status="{{ $fila['status_text'] }}"
                         data-pending="{{ $fila['por_iniciar'] }}"
                         data-total="{{ $fila['total'] }}"
                         data-en-tramite="{{ $fila['en_tramite'] }}"
                         data-en-proceso="{{ $fila['en_proceso'] }}"
                         data-finalizados="{{ $fila['finalizados'] }}"
                         data-performance="{{ $fila['porcentaje'] }}"
                         data-responsables="{{ count($locadores) > 0 ? implode(', ', array_slice($locadores, 0, 3)) : 'Sin asignar' }}"
                         data-area="{{ $fila['area'] }}">
                    <div>
                        <span class="area-card__tag">Área</span>
                        <h3 class="area-card__name">{{ $fila['area'] }}</h3>
                    </div>

                    <div>
                        <span class="team__label">Locadores</span>
                        @if (count($locadores) > 0)
                            <div class="team__item">
                                <span class="avatar">{{ mb_substr(collect(explode(' ', $locadores[0]))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->implode(''), 0, 2) }}</span>
                                <span>{{ $locadores[0] }}</span>
                            </div>
                            @if (count($locadores) > 1)
                                <button type="button" class="team__more" onclick="toggleLocadores({{ $index }}, this)">
                                    Ver {{ count($locadores) - 1 }} más
                                </button>
                                <div class="team__extra" id="locadores-{{ $index }}">
                                    @foreach (array_slice($locadores, 1) as $loc)
                                        <div class="team__item">
                                            <span class="avatar" style="opacity:.75">{{ mb_substr(collect(explode(' ', $loc))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->implode(''), 0, 2) }}</span>
                                            <span>{{ $loc }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div class="team__empty">Sin locadores asignados</div>
                        @endif
                    </div>

                    <div class="metrics">
                        <button type="button" class="metric" disabled>
                            <span class="metric__val">{{ $fila['total'] }}</span>
                            <span class="metric__lbl">Total</span>
                        </button>
                        <button type="button" class="metric metric--warn" @disabled($fila['por_iniciar'] <= 0)
                                onclick="toggleDetails({{ $fila['id'] }}, 1, {{ $index }})">
                            <span class="metric__val">{{ $fila['por_iniciar'] }}</span>
                            <span class="metric__lbl">Por iniciar</span>
                        </button>
                        <button type="button" class="metric metric--info" @disabled($fila['en_tramite'] <= 0)
                                onclick="toggleDetails({{ $fila['id'] }}, 2, {{ $index }})">
                            <span class="metric__val">{{ $fila['en_tramite'] }}</span>
                            <span class="metric__lbl">En trámite</span>
                        </button>
                        <button type="button" class="metric metric--violet" @disabled($fila['en_proceso'] <= 0)
                                onclick="toggleDetails({{ $fila['id'] }}, 3, {{ $index }})">
                            <span class="metric__val">{{ $fila['en_proceso'] }}</span>
                            <span class="metric__lbl">En proceso</span>
                        </button>
                        <button type="button" class="metric metric--ok" @disabled($fila['finalizados'] <= 0)
                                onclick="toggleDetails({{ $fila['id'] }}, 4, {{ $index }})">
                            <span class="metric__val">{{ $fila['finalizados'] }}</span>
                            <span class="metric__lbl">Finalizados</span>
                        </button>
                    </div>

                    <div class="perf">
                        <span class="perf__value" style="color:{{ $colorFila }}">{{ $pctFila }}%</span>
                        <span class="perf__label">% trabajo</span>
                        @if ($pctPend > 0)
                            <span class="perf__pend" title="Pendiente de avance">▼ {{ $pctPend }}% pendiente</span>
                        @else
                            <span class="perf__ok">✓ Al día</span>
                        @endif
                        <span class="perf__status st-{{ $statusKey }}">{{ $fila['status_text'] }}</span>
                    </div>

                    @if ($fila['total'] > 0)
                        <div class="area-card__foot">
                            <button type="button" class="btn btn--export" onclick="exportDetalle({{ $fila['id'] }}, 0)"
                                    title="CSV con todos los requerimientos de esta área en el período">CSV — esta área</button>
                        </div>
                    @endif

                    <div class="area-card__detail" id="detail-pane-{{ $index }}">
                        <div id="detail-loader-{{ $index }}" class="loading">Cargando detalles…</div>
                    </div>
                </article>
            @empty
                <div class="panel" style="text-align:center;color:var(--faint);font-weight:600;">
                    No hay requerimientos con adjuntos completos en el rango seleccionado.
                </div>
            @endforelse
        </div>
    </section>

    {{-- ══════════════ REPORTES (mismo contenido que el antiguo) ══════════════ --}}
    @php
        $repGestionados = (int) ($totales['gestionados'] ?? max(0, $totales['total'] - $totales['por_iniciar']));
        $repEficiencia = $totales['total'] > 0 ? (int) round(($repGestionados / $totales['total']) * 100) : 0;
    @endphp
    <section id="viewReportes" class="view">
        <div class="rep-kpis">
            <div class="rep-kpi">
                <h4>Total requerimientos</h4>
                <div class="stat" id="rep-kpi-total">{{ number_format($totales['total']) }}</div>
                <p>Carga total del período</p>
            </div>
            <div class="rep-kpi">
                <h4>Por iniciar</h4>
                <div class="stat" id="rep-kpi-pending">{{ number_format($totales['por_iniciar']) }}</div>
                <p>Sin tramitar aún</p>
            </div>
            <div class="rep-kpi">
                <h4>Finalizados</h4>
                <div class="stat" id="rep-kpi-completed">{{ number_format($repGestionados) }}</div>
                <p>Total − Por iniciar (como el antiguo)</p>
            </div>
            <div class="rep-kpi">
                <h4>Eficiencia</h4>
                <div class="stat" id="rep-kpi-efficiency">{{ $repEficiencia }}%</div>
                <p>Cumplimiento global</p>
            </div>
        </div>

        <div class="grid-2">
            <div class="panel">
                <div class="section-head"><h2>Distribución por estado</h2></div>
                <div class="analytics-split">
                    <div class="chart-main">
                        <canvas id="statusChart" width="260" height="260"></canvas>
                        <div class="donut-center-label">
                            <div class="total-num" id="donut-total">{{ number_format($totales['total']) }}</div>
                            <div class="total-lbl">Requerimientos</div>
                        </div>
                    </div>
                    <div class="chart-side-panel" id="statusPanel"></div>
                </div>
            </div>
            <div class="panel">
                <div class="section-head"><h2>Requerimientos por área</h2></div>
                <p style="margin:0 0 .5rem;font-size:.72rem;color:var(--muted);font-weight:600;">Orden: mayor a menor cantidad en el período filtrado.</p>
                <div class="chart-box"><canvas id="workloadChart"></canvas></div>
            </div>
        </div>

        <div class="panel">
            <div class="section-head"><h2>Detalle general por área</h2></div>
            <div class="tbl-scroll" style="max-height:340px;">
                <table class="tbl" id="areaDetailTable">
                    <thead>
                        <tr>
                            <th>Área</th>
                            <th style="text-align:right">Total</th>
                            <th style="text-align:right">Por iniciar</th>
                            <th style="text-align:right">Finalizados</th>
                            <th style="text-align:center">Estado</th>
                            <th style="text-align:center">Eficiencia</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="section-head">
                <h2>Todos los requerimientos del filtro</h2>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;">
                    <button type="button" class="btn btn--export" onclick="exportDetalle(-1, 0)">Exportar CSV (mismo listado)</button>
                    <span class="section-head__meta">{{ count($listado) }} reg. · {{ $feInicio }} — {{ $feFin }}</span>
                </div>
            </div>
            <p style="margin:0 0 .6rem;font-size:.72rem;color:var(--muted);font-weight:600;line-height:1.45;">
                Mismos criterios que el tablero: estados activos, adjuntos obligatorios y rango de <strong>F. registro</strong>.
                Listado completo en pantalla (sin paginar).
            </p>
            <div class="tbl-scroll">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>N°</th><th>Asunto</th><th>Estado</th><th>Locador</th><th>Área sol.</th>
                            <th>Ruta</th><th>Área act.</th><th>Cargo dest.</th><th>Asignado</th>
                            <th style="text-align:center">Días</th><th>F. reg.</th><th style="text-align:center">Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($listado as $r)
                            @php
                                $dto = (int) $r->dto_int;
                                $info = $listadoRutas[$dto] ?? null;
                                $areaActual = $info['area_actual'] ?? '—';
                                $cargoDestino = ($info['cargo_actual'] ?? '') !== '' ? $info['cargo_actual'] : '—';
                                $rutaLabels = $info['ruta_labels'] ?? [];
                                $areaSol = trim((string) $r->dc_area_req) !== '' ? (string) $r->dc_area_req : (string) ($r->de_area ?? '');
                                if ($dto > 0) {
                                    $hrefHist = $rutas['seguimiento'] . '?nu_expe=' . rawurlencode((string) $dto);
                                    $rutaTxt = $rutaLabels !== [] ? implode(' → ', $rutaLabels) : '—';
                                } else {
                                    $hrefHist = $rutas['requerimiento'] . '?intCdgoPgoPrvdres=' . rawurlencode((string) $r->cdgo_pgo_prvdres);
                                    $rutaTxt = trim((string) $r->dc_area_req) !== '' ? (string) $r->dc_area_req : '—';
                                }
                                $estado = $r->estado_display;
                                $asunto = (string) ($r->de_pgo_prvdres ?? '');
                                $locador = trim((string) ($r->no_crto_titular ?? ''));
                                $asignado = trim((string) ($r->no_crto_asignado ?? ''));
                            @endphp
                            <tr>
                                <td class="rd-nowrap">
                                    <a class="rd-exp" href="{{ $hrefHist }}"
                                       onclick="window.open(this.href,'sigtram_historico','scrollbars=yes,width=980,height=640,resizable=yes');return false;">{{ $r->nu_expe }}</a>
                                </td>
                                <td style="max-width:210px" title="{{ $asunto }}">{{ mb_substr($asunto, 0, 80) }}{{ mb_strlen($asunto) > 80 ? '…' : '' }}</td>
                                <td style="max-width:160px" title="{{ $estado['title'] }}">
                                    <span class="rd-badge rd-badge--{{ $estado['badge_variant'] }}">{{ $estado['label'] }}</span>
                                    <span class="rd-cod">{{ $estado['cod_line'] }}</span>
                                </td>
                                <td style="max-width:160px">{{ $locador !== '' ? mb_substr($locador, 0, 60) . (mb_strlen($locador) > 60 ? '…' : '') : 'Sin asignar' }}</td>
                                <td style="font-weight:600">{{ $areaSol }}</td>
                                <td style="max-width:220px;font-size:.7rem">{{ $rutaTxt }}</td>
                                <td style="font-weight:700;color:var(--teal-dark)">{{ $areaActual }}</td>
                                <td style="max-width:130px;font-size:.7rem">{{ $cargoDestino }}</td>
                                <td>{{ $asignado !== '' ? $asignado : 'Sin asignar' }}</td>
                                <td style="text-align:center">
                                    @if ($r->dias_area === null)
                                        <span class="rd-empty">—</span>
                                    @else
                                        <span class="rd-dias {{ $r->dias_area > 5 ? 'rd-dias--alto' : '' }}">{{ $r->dias_area }}</span>
                                    @endif
                                </td>
                                <td class="rd-nowrap" style="font-weight:600">{{ $r->fe_registro }}</td>
                                <td style="text-align:center">
                                    <a class="rd-eye" href="{{ $hrefHist }}"
                                       onclick="window.open(this.href,'sigtram_historico','scrollbars=yes,width=980,height=640,resizable=yes');return false;"
                                       aria-label="Ver">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="rd-vacio">No hay requerimientos con este filtro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ══════════════ REPORTE POR ÁREAS ══════════════ --}}
    <section id="viewAreas" class="view">
        <div class="section-head">
            <h2>Avance del circuito por área</h2>
            <button type="button" class="btn btn--export" onclick="exportAreas()">Exportar CSV — reporte por áreas</button>
        </div>

        <div id="areasList">
            @foreach ($filas as $fila)
                @continue($fila['total'] < 1 || empty($fila['milestones']))
                @php $busqueda = mb_strtolower($fila['area'] . ' ' . implode(' ', $fila['locadores'])); @endphp
                <article class="ml-card" data-search="{{ $busqueda }}" data-status="{{ $fila['status_text'] }}" data-pending="{{ $fila['por_iniciar'] }}">
                    <div class="ml-card__head">
                        <h3>{{ $fila['area'] }}</h3>
                        <span class="ml-card__meta">Total {{ $fila['total'] }} req. · {{ $fila['status_text'] }}</span>
                    </div>
                    <div class="ml-grid">
                        @foreach ($fila['milestones'] as $i => $ms)
                            @php
                                $color = $milestoneColors[$i % count($milestoneColors)];
                                $pctMs = min(100, (int) $ms['pct']);
                                $clickable = $ms['n'] > 0;
                            @endphp
                            <div class="ml-pill {{ $clickable ? 'ml-pill--clickable' : '' }}"
                                 @if ($clickable)
                                     role="button" tabindex="0"
                                     data-ml="{{ $ms['ml_key'] }}"
                                     data-area-id="{{ $fila['id'] }}"
                                     data-modal-title="{{ $fila['area'] }} · {{ $ms['label'] }}"
                                 @endif>
                                <div class="ml-pill__label">{{ $ms['label'] }}</div>
                                <div class="ml-pill__area">{{ $ms['area_line'] }}</div>
                                <div class="ml-pill__pct">{{ $pctMs }}%</div>
                                <div class="ml-pill__count">{{ $ms['n'] }} / {{ $fila['total'] }}</div>
                                @if ($ms['n_here'] > 0)
                                    <div class="ml-pill__here">Ahora en este hito: {{ $ms['n_here'] }}</div>
                                @endif
                                <div class="ml-bar"><div class="ml-bar__fill" style="width:{{ $pctMs }}%;background:{{ $color }}"></div></div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <div class="modal" id="milestoneModal" aria-hidden="true">
        <div class="modal__backdrop" onclick="closeMilestoneModal()"></div>
        <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="milestoneTitle">
            <div class="modal__head">
                <h3 id="milestoneTitle"></h3>
                <button type="button" class="modal__close" onclick="closeMilestoneModal()" aria-label="Cerrar">×</button>
            </div>
            <div class="modal__body"><div id="milestoneLoader"></div></div>
        </div>
    </div>

    <footer>SIGTRAM · Sistema de Gestión de Trámites — {{ now()->format('Y') }}</footer>
</div>

<script>
    const RUTAS = @json($rutas);
    const FI = @json($feInicio);
    const FF = @json($feFin);
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const TOT = @json($totales);
    let chartsInitialized = false;
    let statusChart = null;
    let workloadChart = null;

    // ── Rango de fechas (siempre en horario local; nunca toISOString/UTC) ──
    function fmtLocal(d) {
        const mes = String(d.getMonth() + 1).padStart(2, '0');
        const dia = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mes + '-' + dia;
    }

    function setRange(tipo) {
        const hoy = new Date();
        let inicio = new Date();
        if (tipo === 'hoy') {
            inicio = hoy;
        } else if (tipo === 'mes') {
            inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        } else if (tipo === 'trimestre') {
            inicio = new Date(hoy.getFullYear(), hoy.getMonth() - 3, hoy.getDate());
        } else if (tipo === 'anio') {
            inicio = new Date(hoy.getFullYear(), 0, 1);
        }
        document.getElementById('fe_inicio').value = fmtLocal(inicio);
        document.getElementById('fe_fin').value = fmtLocal(hoy);
        document.getElementById('filtroForm').submit();
    }

    // ── Cambio de vista ───────────────────────────────────────
    function switchView(vista) {
        const mapa = {
            dashboard: ['tabDashboard', 'viewDashboard'],
            reportes: ['tabReportes', 'viewReportes'],
            areas: ['tabAreas', 'viewAreas'],
        };
        Object.values(mapa).forEach(function (par) {
            document.getElementById(par[0]).classList.remove('is-active');
            document.getElementById(par[1]).classList.remove('is-active');
        });
        const activo = mapa[vista] || mapa.dashboard;
        document.getElementById(activo[0]).classList.add('is-active');
        document.getElementById(activo[1]).classList.add('is-active');

        const mostrarFiltros = vista !== 'reportes';
        document.getElementById('buscadorCampo').style.display = mostrarFiltros ? '' : 'none';
        document.getElementById('chipsRow').style.display = mostrarFiltros ? '' : 'none';

        if (vista === 'reportes') {
            setTimeout(initReports, 80);
        }
    }

    function initReports() {
        const cards = Array.prototype.slice.call(document.querySelectorAll('.area-card'));
        const reqTotal = parseInt(TOT.total || 0, 10);
        const reqStats = {
            'EXCELENTE': parseInt(TOT.finalizados || 0, 10),
            'AL DÍA': parseInt(TOT.en_proceso || 0, 10),
            'REVISAR': parseInt(TOT.en_tramite || 0, 10),
            'CRÍTICO': parseInt(TOT.por_iniciar || 0, 10),
        };

        const workloads = [];
        cards.forEach(function (c) {
            const tot = parseInt(c.getAttribute('data-total') || '0', 10);
            const pend = parseInt(c.getAttribute('data-pending') || '0', 10);
            workloads.push({
                label: c.getAttribute('data-area') || '',
                total: tot,
                pending: pend,
                enTram: parseInt(c.getAttribute('data-en-tramite') || '0', 10),
                enProc: parseInt(c.getAttribute('data-en-proceso') || '0', 10),
                fin: parseInt(c.getAttribute('data-finalizados') || '0', 10),
                performance: parseFloat(c.getAttribute('data-performance') || '0'),
                responsables: c.getAttribute('data-responsables') || 'Sin asignar',
            });
        });

        // Tabla detalle por área (mismas reglas del antiguo)
        const tb = document.querySelector('#areaDetailTable tbody');
        if (tb) {
            tb.innerHTML = '';
            const sorted = workloads.slice().sort(function (a, b) {
                return (b.pending - a.pending) || (b.total - a.total);
            });
            sorted.forEach(function (w) {
                const comp = w.total - w.pending;
                const eff = w.total > 0 ? Math.round((comp / w.total) * 100) : 100;
                let badge;
                if (w.total === 0) {
                    badge = '<span style="background:#f1f5f9;color:#64748b;padding:4px 10px;border-radius:6px;font-size:.65rem;font-weight:800">SIN ACTIVIDAD</span>';
                } else if (w.pending > 5 || (eff < 50 && w.total > 2)) {
                    badge = '<span style="background:#fef2f2;color:#ef4444;padding:4px 10px;border-radius:6px;font-size:.65rem;font-weight:800;border:1px solid #fee2e2">CRÍTICO</span>';
                } else if (eff < 85) {
                    badge = '<span style="background:#fffbeb;color:#f59e0b;padding:4px 10px;border-radius:6px;font-size:.65rem;font-weight:800;border:1px solid #fef3c7">AL DÍA</span>';
                } else {
                    badge = '<span style="background:#f0fdf4;color:#10b981;padding:4px 10px;border-radius:6px;font-size:.65rem;font-weight:800;border:1px solid #dcfce7">EXCELENTE</span>';
                }
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td style="font-weight:700;color:var(--ink)">' + w.label + '</td>' +
                    '<td style="text-align:right">' + w.total + '</td>' +
                    '<td style="text-align:right;color:var(--warn);font-weight:700">' + w.pending + '</td>' +
                    '<td style="text-align:right;color:var(--ok);font-weight:700">' + comp + '</td>' +
                    '<td style="text-align:center">' + badge + '</td>' +
                    '<td style="text-align:center;font-weight:800">' + eff + '%</td>';
                tb.appendChild(tr);
            });
        }

        // Leyenda lateral del donut
        const sp = document.getElementById('statusPanel');
        if (sp) {
            sp.innerHTML = '';
            const scol = { EXCELENTE: '#10b981', 'AL DÍA': '#0ea5e9', REVISAR: '#f59e0b', 'CRÍTICO': '#ef4444' };
            [
                ['EXCELENTE', 'Finalizados'],
                ['AL DÍA', 'En proceso'],
                ['REVISAR', 'En trámite'],
                ['CRÍTICO', 'Por iniciar'],
            ].forEach(function (pair) {
                const k = pair[0], hint = pair[1], cnt = reqStats[k] || 0;
                const perc = reqTotal ? Math.round(cnt / reqTotal * 100) : 0;
                const col = scol[k];
                sp.innerHTML +=
                    '<div class="side-metric-item"><div class="side-metric-info">' +
                    '<div class="side-metric-label"><div class="side-metric-dot" style="background:' + col + '"></div>' + k + '</div>' +
                    '<div class="side-metric-value" title="' + hint + '">' + cnt + ' (' + perc + '%)</div></div>' +
                    '<div class="side-progress-bg"><div class="side-progress-fill" style="width:' + perc + '%;background:' + col + '"></div></div>' +
                    '<div style="font-size:.58rem;color:#94a3b8;font-weight:600;margin-top:2px;">' + hint + '</div></div>';
            });
        }

        if (typeof Chart === 'undefined') { return; }

        const donutData = [
            reqStats.EXCELENTE || 0,
            reqStats['AL DÍA'] || 0,
            reqStats.REVISAR || 0,
            reqStats['CRÍTICO'] || 0,
        ];
        const donutColors = ['#10b981', '#0ea5e9', '#f59e0b', '#ef4444'];

        if (statusChart) { statusChart.destroy(); }
        const ctxS = document.getElementById('statusChart');
        if (ctxS) {
            statusChart = new Chart(ctxS, {
                type: 'doughnut',
                data: {
                    labels: ['EXCELENTE', 'AL DÍA', 'REVISAR', 'CRÍTICO'],
                    datasets: [{ data: donutData, backgroundColor: donutColors, borderWidth: 0 }],
                },
                options: {
                    cutout: '68%',
                    plugins: { legend: { display: false } },
                },
            });
        }

        const top = workloads.slice().sort(function (a, b) { return b.total - a.total; }).slice(0, 12);
        if (workloadChart) { workloadChart.destroy(); }
        const ctxW = document.getElementById('workloadChart');
        if (ctxW) {
            workloadChart = new Chart(ctxW, {
                type: 'bar',
                data: {
                    labels: top.map(function (w) {
                        return w.label.length > 38 ? w.label.substring(0, 38) + '…' : w.label;
                    }),
                    datasets: [{
                        label: 'Total',
                        data: top.map(function (w) { return w.total; }),
                        backgroundColor: '#0d9488',
                        borderRadius: 6,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterBody: function (items) {
                                    const w = top[items[0].dataIndex];
                                    if (!w) { return ''; }
                                    return [
                                        'Por iniciar: ' + w.pending,
                                        'En trámite: ' + w.enTram,
                                        'En proceso: ' + w.enProc,
                                        'Finalizados: ' + w.fin,
                                        'Locadores: ' + w.responsables,
                                    ];
                                },
                            },
                        },
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0 } },
                        y: { ticks: { font: { size: 10, weight: '600' } } },
                    },
                },
            });
        }
        chartsInitialized = true;
    }

    // ── Filtros de tarjetas ───────────────────────────────────
    let estadoActivo = 'all';
    let soloPendientes = false;

    function filtrarEstado(el) {
        document.querySelectorAll('#chipsRow .chip').forEach(function (c) {
            if (c.id !== 'chipPendiente') { c.classList.remove('is-active'); }
        });
        el.classList.add('is-active');
        estadoActivo = el.getAttribute('data-status') || 'all';
        aplicarFiltros();
    }

    function togglePendientes(el) {
        soloPendientes = !soloPendientes;
        el.classList.toggle('is-active', soloPendientes);
        aplicarFiltros();
    }

    function aplicarFiltros() {
        const q = (document.getElementById('buscador').value || '').toLowerCase().trim();
        document.querySelectorAll('.area-card, .ml-card').forEach(function (card) {
            let visible = true;
            if (estadoActivo !== 'all' && card.getAttribute('data-status') !== estadoActivo) { visible = false; }
            if (visible && soloPendientes && parseInt(card.getAttribute('data-pending') || '0', 10) <= 0) { visible = false; }
            if (visible && q && (card.getAttribute('data-search') || '').indexOf(q) === -1) { visible = false; }
            card.style.display = visible ? '' : 'none';
        });
    }

    document.getElementById('buscador').addEventListener('input', aplicarFiltros);

    // ── Locadores ─────────────────────────────────────────────
    function toggleLocadores(index, btn) {
        const caja = document.getElementById('locadores-' + index);
        if (!caja) { return; }
        const abierto = caja.classList.toggle('is-open');
        const n = caja.querySelectorAll('.team__item').length;
        btn.textContent = abierto ? 'Ocultar' : ('Ver ' + n + ' más');
    }

    // ── Detalle AJAX ──────────────────────────────────────────
    function urlDetalle(areaId, tipo, offset, ml, pane) {
        const p = new URLSearchParams();
        p.set('area', areaId);
        p.set('tipo', tipo);
        p.set('offset', offset || 0);
        p.set('pane', (pane === undefined || pane === null) ? '-1' : pane);
        p.set('fe_inicio', FI);
        p.set('fe_fin', FF);
        if (ml) { p.set('ml', ml); }
        return RUTAS.detalle + '?' + p.toString();
    }

    function cargarDetalle(destino, url, paneId) {
        destino.innerHTML = '<div class="loading">Cargando…</div>';
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                destino.innerHTML = html;
                const raiz = destino.querySelector('.req-detail-wrap');
                if (raiz) { initReqDetailFilter(raiz); }
                if (paneId) {
                    const pane = document.getElementById(paneId);
                    if (pane) { pane.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
                } else {
                    const body = document.querySelector('.modal__body');
                    if (body) { body.scrollTop = 0; }
                }
            })
            .catch(function () {
                destino.innerHTML = '<div class="req-detail-msg req-detail-msg--error">No se pudo cargar el detalle.</div>';
            });
    }

    function toggleDetails(areaId, tipo, index) {
        const pane = document.getElementById('detail-pane-' + index);
        const loader = document.getElementById('detail-loader-' + index);
        if (!pane || !loader) { return; }

        if (pane.classList.contains('is-open')) {
            pane.classList.remove('is-open');
            return;
        }
        document.querySelectorAll('.area-card__detail.is-open').forEach(function (p) { p.classList.remove('is-open'); });
        pane.classList.add('is-open');
        cargarDetalle(loader, urlDetalle(areaId, tipo, 0, null, index), 'detail-pane-' + index);
    }

    function goDetailPage(areaId, tipo, offset, pane, ml) {
        const esModal = (String(pane) === '-1');
        const destino = esModal
            ? document.getElementById('milestoneLoader')
            : document.getElementById('detail-loader-' + pane);
        if (!destino) { return; }
        cargarDetalle(destino, urlDetalle(areaId, tipo, offset, ml || null, pane), esModal ? null : ('detail-pane-' + pane));
    }

    // ── Modal de hitos ────────────────────────────────────────
    function openMilestoneDetail(areaId, ml, titulo) {
        document.getElementById('milestoneTitle').textContent = titulo || '';
        document.getElementById('milestoneModal').classList.add('is-open');
        document.getElementById('milestoneModal').setAttribute('aria-hidden', 'false');
        cargarDetalle(document.getElementById('milestoneLoader'), urlDetalle(areaId, 0, 0, ml, -1), null);
    }

    function closeMilestoneModal() {
        const modal = document.getElementById('milestoneModal');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.getElementById('milestoneLoader').innerHTML = '';
    }

    document.addEventListener('click', function (e) {
        const pill = e.target.closest('.ml-pill--clickable');
        if (!pill) { return; }
        openMilestoneDetail(
            parseInt(pill.getAttribute('data-area-id'), 10),
            pill.getAttribute('data-ml'),
            pill.getAttribute('data-modal-title')
        );
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMilestoneModal();
            return;
        }
        if (e.key === 'Enter' || e.key === ' ') {
            const pill = e.target.closest && e.target.closest('.ml-pill--clickable');
            if (pill) { e.preventDefault(); pill.click(); }
        }
    });

    // ── Exportaciones ─────────────────────────────────────────
    function exportDetalle(areaId, tipo) {
        const p = new URLSearchParams({ area: areaId, tipo: tipo, fe_inicio: FI, fe_fin: FF });
        window.location.href = RUTAS.export_detalle + '?' + p.toString();
    }

    function exportAreas() {
        const p = new URLSearchParams({ fe_inicio: FI, fe_fin: FF });
        window.location.href = RUTAS.export_areas + '?' + p.toString();
    }

    /**
     * Filtros locales de la tabla de detalle: bloques por día de F. registro y «solo filas marcadas».
     */
    function initReqDetailFilter(raiz) {
        const host = raiz.querySelector('.req-detail-filter-host');
        const filas = Array.prototype.slice.call(raiz.querySelectorAll('tbody tr.req-det-data-row'));
        if (!host) { return; }

        const bloques = {};
        filas.forEach(function (tr) {
            const d = tr.getAttribute('data-reg-day') || '';
            if (d) { bloques[d] = (bloques[d] || 0) + 1; }
        });
        const dias = Object.keys(bloques).sort().reverse();

        if (!dias.length) {
            host.innerHTML = '<div class="rd-filter" style="color:var(--faint);font-size:.72rem;">Sin filas para agrupar por fecha de registro.</div>';
            return;
        }

        let chips = '';
        dias.forEach(function (d) {
            const p = d.split('-');
            const legible = p[2] + '/' + p[1] + '/' + p[0];
            chips += '<label class="rd-day"><input type="checkbox" class="req-det-day" value="' + d + '" checked> ' +
                legible + ' <span style="color:var(--faint)">(' + bloques[d] + ')</span></label>';
        });

        host.innerHTML =
            '<div class="rd-filter">' +
            '<div class="rd-filter__top">' +
            '<label class="rd-filter__lbl"><input type="checkbox" class="req-det-all-blocks" checked> Todos los días</label>' +
            '<button type="button" class="rd-btn req-det-reset">Restablecer</button>' +
            '<label class="rd-filter__lbl" style="font-weight:700;color:var(--muted)"><input type="checkbox" class="req-det-only-picked"> Solo filas marcadas</label>' +
            '</div>' +
            '<div class="rd-filter__days">' + chips + '</div>' +
            '<p class="rd-filter__hint">Marque o desmarque fechas para mostrar u ocultar filas según el día de registro. ' +
            'Use la columna de casillas para elegir filas concretas y active «Solo filas marcadas».</p>' +
            '</div>';

        function aplicar() {
            const activos = {};
            raiz.querySelectorAll('.req-det-day:checked').forEach(function (c) { activos[c.value] = true; });
            const soloMarcadas = raiz.querySelector('.req-det-only-picked').checked;

            filas.forEach(function (tr) {
                const d = tr.getAttribute('data-reg-day') || '';
                const cb = tr.querySelector('.req-det-row-cb');
                const okDia = activos[d] === true;
                const okMarca = !soloMarcadas || (cb && cb.checked);
                tr.style.display = (okDia && okMarca) ? '' : 'none';
            });

            const todos = raiz.querySelectorAll('.req-det-day');
            const marcados = raiz.querySelectorAll('.req-det-day:checked').length;
            const master = raiz.querySelector('.req-det-all-blocks');
            master.checked = todos.length > 0 && marcados === todos.length;
            master.indeterminate = marcados > 0 && marcados < todos.length;
        }

        raiz.addEventListener('change', function (e) {
            const t = e.target;
            if (t.classList.contains('req-det-all-blocks')) {
                raiz.querySelectorAll('.req-det-day').forEach(function (c) { c.checked = t.checked; });
                t.indeterminate = false;
            }
            if (t.classList.contains('req-det-select-all')) {
                filas.forEach(function (tr) {
                    if (tr.style.display !== 'none') {
                        const cb = tr.querySelector('.req-det-row-cb');
                        if (cb) { cb.checked = t.checked; }
                    }
                });
            }
            aplicar();
        });

        raiz.addEventListener('click', function (e) {
            if (e.target.classList.contains('req-det-reset')) {
                raiz.querySelectorAll('.req-det-day').forEach(function (c) { c.checked = true; });
                raiz.querySelector('.req-det-only-picked').checked = false;
                raiz.querySelectorAll('.req-det-row-cb').forEach(function (c) { c.checked = false; });
                aplicar();
            }
        });

        aplicar();
    }
</script>
</body>
</html>
