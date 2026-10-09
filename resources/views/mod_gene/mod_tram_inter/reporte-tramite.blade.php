<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sigtram | Reporte General de Trámites Internos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        :root {
            --ink: #0f172a;
            --ink-2: #334155;
            --muted: #64748b;
            --faint: #94a3b8;
            --line: #e6ecf2;
            --bg: #f6f8fb;
            --card: #ffffff;
            --accent: #334155;
            --accent-soft: #f1f5f9;
            --accent-dark: #0f172a;
            --ok: #475569;
            --warn: #f59e0b;
            --bad: #ef4444;
            --radius: 14px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Figtree", "Segoe UI", Tahoma, sans-serif;
            background:
                radial-gradient(900px 420px at 8% -10%, rgba(51, 65, 85, .08), transparent 55%),
                radial-gradient(700px 380px at 100% 0%, rgba(15, 23, 42, .04), transparent 50%),
                var(--bg);
            color: var(--ink-2);
            font-size: 14px;
            min-height: 100vh;
        }

        h1, .num { font-family: "Outfit", "Figtree", sans-serif; }

        .wrap { max-width: 1400px; margin: 0 auto; padding: 1.25rem 1.5rem 3rem; }

        .top {
            display: flex; flex-wrap: wrap; gap: .75rem;
            align-items: flex-end; justify-content: space-between;
            margin-bottom: 1rem;
        }
        .top h1 {
            margin: 0; font-size: 1.45rem; font-weight: 800;
            letter-spacing: -.02em; color: var(--ink);
        }
        .top h1 span { color: var(--accent); }
        .top p { margin: .25rem 0 0; font-size: .78rem; font-weight: 600; color: var(--muted); }

        .filters {
            display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: center;
            gap: .85rem; padding: 1rem 1.15rem; margin-bottom: 1rem;
            background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        .field label {
            display: block; margin-bottom: .35rem; margin-left: .15rem;
            font-size: .68rem; font-weight: 800; letter-spacing: .08em;
            text-transform: uppercase; color: var(--faint);
        }
        .field input {
            width: 8.5rem; padding: .55rem .75rem;
            border: 1px solid var(--line); border-radius: 10px;
            background: #fff; color: var(--ink); font: inherit; font-weight: 700;
            outline: none; transition: border-color .15s, box-shadow .15s;
        }
        .field input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(51, 65, 85, .15);
        }

        .btn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .6rem 1.05rem; border-radius: 10px; border: 1px solid var(--line);
            background: var(--card); color: var(--ink-2); font: inherit;
            font-size: .78rem; font-weight: 700; cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
        }
        .btn--primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn--primary:hover { background: var(--accent-dark); border-color: var(--accent-dark); }

        .panel {
            background: var(--card); border: 1px solid var(--line); border-radius: var(--radius);
            overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        .table-wrap { overflow-x: auto; }

        table.rep {
            width: 100%; border-collapse: collapse; font-size: .86rem;
        }
        table.rep thead th {
            padding: .7rem .85rem;
            background: #0f172a; color: #e2e8f0;
            font-size: .68rem; font-weight: 800; letter-spacing: .06em;
            text-transform: uppercase; white-space: nowrap;
        }
        table.rep thead th:first-child { text-align: center; width: 2.5rem; }
        table.rep thead th.left { text-align: left; }
        table.rep thead th.center { text-align: center; }
        table.rep thead th.right { text-align: right; }

        table.rep tbody td {
            padding: .65rem .85rem; border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }
        table.rep tbody tr.data:hover { background: #f8fafc; }
        table.rep tbody tr.detail-row td { padding: 0 .85rem; border-bottom: 1px solid var(--line); }
        table.rep tbody td.num-cell { text-align: center; color: var(--muted); font-weight: 700; font-variant-numeric: tabular-nums; }
        table.rep tbody td.area { font-weight: 700; color: var(--ink); }
        table.rep tbody td.center { text-align: center; }
        table.rep tbody td.right { text-align: right; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--ink-2); }
        table.rep tbody td.total { font-weight: 800; color: var(--ink); font-variant-numeric: tabular-nums; }

        .cell-click {
            display: inline-block; min-width: 2.1rem; padding: .2rem .55rem;
            border-radius: 8px; cursor: pointer; font-weight: 700;
            font-variant-numeric: tabular-nums;
            transition: background .15s, color .15s;
        }
        .cell-click:hover { background: var(--accent-soft); color: var(--accent-dark); }
        .cell-click.active { background: var(--accent); color: #fff; font-weight: 800; }
        .cell-click.is-zero { color: var(--faint); font-weight: 600; }

        .pct { font-variant-numeric: tabular-nums; }
        .pct.ok { color: var(--ok); }
        .pct.warn { color: var(--warn); }
        .pct.bad { color: var(--bad); }

        table.rep tfoot td {
            padding: .75rem .85rem; background: #0f172a; color: #fff;
            font-weight: 800; font-variant-numeric: tabular-nums;
        }
        table.rep tfoot td.label {
            text-align: right; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase;
            color: #94a3b8;
        }

        .detalle-box {
            display: none; margin: .55rem 0 .85rem; padding: .75rem;
            background: #f8fafc; border: 1px solid var(--line); border-radius: 12px;
        }
        .detalle-box.is-open { display: block; }

        .empty {
            padding: 2.5rem 1rem; text-align: center; color: var(--faint); font-weight: 700;
        }

        .loading {
            text-align: center; padding: 1rem; color: var(--accent-dark);
            font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
        }
        .err {
            text-align: center; padding: .85rem; color: var(--bad);
            font-size: .75rem; font-weight: 800;
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <div>
            <h1>Reporte <span>General</span> de Trámites Internos</h1>
            <p>Seguimiento por gerencia · Período seleccionado</p>
        </div>
    </div>

    <form method="POST" action="{{ url()->current() }}" class="filters">
        @csrf
        <div class="field">
            <label for="txt_fe_inicio">Desde</label>
            <input type="text" name="txt_fe_inicio" id="txt_fe_inicio" value="{{ $desdeStr }}"
                   placeholder="dd/mm/aaaa" pattern="\d{2}/\d{2}/\d{4}" required>
        </div>
        <div class="field">
            <label for="txt_fe_fin">Hasta</label>
            <input type="text" name="txt_fe_fin" id="txt_fe_fin" value="{{ $hastaStr }}"
                   placeholder="dd/mm/aaaa" pattern="\d{2}/\d{2}/\d{4}" required>
        </div>
        <button type="submit" name="Buscar" value="1" class="btn btn--primary">
            <i class="fas fa-search"></i> Buscar
        </button>
    </form>

    <div class="panel">
        <div class="table-wrap">
            <table class="rep">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="left">Nombre de Gerencia</th>
                        <th class="center">Por recibir</th>
                        <th class="center">Recibidos</th>
                        <th class="center">Derivado UU.OO</th>
                        <th class="center">Finalizados</th>
                        <th class="center">Total Doc.</th>
                        <th class="right">% Trabajo</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reportes as $i => $row)
                    @php
                        $idx = $i + 1;
                        $pct = (float) $row->porcentaje;
                        $pctClass = $pct >= 80 ? 'ok' : ($pct >= 50 ? 'warn' : 'bad');
                    @endphp
                    <tr class="data">
                        <td class="num-cell">{{ $idx }}</td>
                        <td class="area">{{ $row->de_area }}</td>
                        <td class="center">
                            <span class="cell-click {{ $row->por_recibir == 0 ? 'is-zero' : '' }}"
                                  data-area="{{ $row->cdgo_area }}" data-tipo="1" data-idx="{{ $idx }}">{{ $row->por_recibir }}</span>
                        </td>
                        <td class="center">
                            <span class="cell-click {{ $row->recibidos == 0 ? 'is-zero' : '' }}"
                                  data-area="{{ $row->cdgo_area }}" data-tipo="2" data-idx="{{ $idx }}">{{ $row->recibidos }}</span>
                        </td>
                        <td class="center">
                            <span class="cell-click {{ $row->derivados == 0 ? 'is-zero' : '' }}"
                                  data-area="{{ $row->cdgo_area }}" data-tipo="3" data-idx="{{ $idx }}">{{ $row->derivados }}</span>
                        </td>
                        <td class="center">
                            <span class="cell-click {{ $row->finalizados == 0 ? 'is-zero' : '' }}"
                                  data-area="{{ $row->cdgo_area }}" data-tipo="4" data-idx="{{ $idx }}">{{ $row->finalizados }}</span>
                        </td>
                        <td class="center total">{{ $row->total_doc }}</td>
                        <td class="right"><span class="pct {{ $pctClass }}">{{ $row->porcentaje }}</span></td>
                    </tr>
                    <tr class="detail-row">
                        <td colspan="8">
                            <div id="conte_resultados{{ $idx }}" class="detalle-box"></div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">No hay áreas activas para mostrar.</td>
                    </tr>
                @endforelse
                </tbody>
                @if(count($reportes) > 0)
                <tfoot>
                    <tr>
                        <td></td>
                        <td class="label">Totales :</td>
                        <td style="text-align:center">{{ number_format($totales['sum_entregado'], 0) }}</td>
                        <td style="text-align:center">{{ number_format($totales['sum_recibido'], 0) }}</td>
                        <td style="text-align:center">{{ number_format($totales['sum_salida'], 0) }}</td>
                        <td style="text-align:center">{{ number_format($totales['sum_finalizado'], 0) }}</td>
                        <td style="text-align:center">{{ number_format($totales['sum_total_doc'], 0) }}</td>
                        <td style="text-align:right">{{ $totales['porcentaje'] }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const URL_DETALLE = @json(route('mod_tram_inter.reporte_tramite.detalle'));
    const FECHA_INI = @json($desdeStr);
    const FECHA_FIN = @json($hastaStr);
    let lastKey = null;

    document.querySelectorAll('.cell-click').forEach(el => {
        el.addEventListener('click', function () {
            const area = this.dataset.area;
            const tipo = this.dataset.tipo;
            const idx = this.dataset.idx;
            const box = document.getElementById('conte_resultados' + idx);
            const key = area + '-' + tipo + '-' + idx;

            document.querySelectorAll('.cell-click').forEach(c => c.classList.remove('active'));

            if (lastKey === key && box.classList.contains('is-open')) {
                box.classList.remove('is-open');
                box.innerHTML = '';
                lastKey = null;
                return;
            }

            lastKey = key;
            this.classList.add('active');

            document.querySelectorAll('[id^="conte_resultados"]').forEach(d => {
                if (d !== box) {
                    d.classList.remove('is-open');
                    d.innerHTML = '';
                }
            });

            box.classList.add('is-open');
            box.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';

            $.ajax({
                url: URL_DETALLE,
                method: 'POST',
                data: {
                    _token: CSRF,
                    area_id: area,
                    tipo_bandeja: tipo,
                    fecha_inicio: FECHA_INI,
                    fecha_fin: FECHA_FIN
                },
                success: function (res) {
                    box.innerHTML = res.html || '<div class="empty">Sin datos</div>';
                },
                error: function () {
                    box.innerHTML = '<div class="err">Error al cargar el detalle</div>';
                }
            });
        });
    });
})();
</script>
</body>
</html>
