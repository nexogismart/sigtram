<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sigtram | Adjuntar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --radius: 10px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Figtree", "Segoe UI", Tahoma, sans-serif;
            background: var(--bg);
            color: var(--ink-2);
            font-size: 13px;
        }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 1rem 1.25rem 2.5rem; }

        /* Filtros como el antiguo: una línea Palabra / Buscar por / Buscar, otra Fecha */
        .search-box {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: .85rem 1rem;
            margin-bottom: .85rem;
            text-align: center;
        }
        .search-box .row { margin: .35rem 0; }
        .search-box label {
            font-weight: 700; color: var(--ink-2); margin-right: .25rem;
        }
        .search-box input[type=text],
        .search-box select {
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: .35rem .5rem;
            font: inherit;
            font-weight: 600;
            color: var(--ink);
            outline: none;
            vertical-align: middle;
        }
        .search-box input[type=text]:focus,
        .search-box select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(51,65,85,.12);
        }
        .search-box input#criterio { width: 11rem; }
        .search-box input#fecha { width: 5.5rem; }
        .search-box select { min-width: 11rem; }
        .btn {
            display: inline-flex; align-items: center; gap: .3rem;
            border: 1px solid var(--ink);
            background: var(--ink);
            color: #fff;
            font: inherit; font-weight: 700;
            padding: .38rem .85rem;
            border-radius: 6px;
            cursor: pointer;
            vertical-align: middle;
        }
        .btn:hover { background: #1e293b; }

        .flash {
            margin-bottom: .75rem; padding: .55rem .8rem; border-radius: 8px;
            background: #fff; border: 1px solid var(--line); font-weight: 700; text-align: center;
        }

        .pager {
            text-align: right;
            padding: .35rem .15rem .55rem;
            font-size: 12px;
            font-weight: 600;
        }
        .pager a { color: var(--accent); text-decoration: none; margin: 0 .15rem; }
        .pager a:hover { text-decoration: underline; }
        .pager b { margin: 0 .15rem; color: var(--ink); }

        /* Tabla estilo CSSTableGenerator / antiguo */
        .tbl-wrap {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            overflow: hidden;
        }
        table.rep {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.rep thead th {
            background: #0f172a;
            color: #e2e8f0;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .03em;
            padding: .55rem .45rem;
            text-align: left;
        }
        table.rep thead th.c { text-align: center; }
        table.rep tbody td {
            padding: .4rem .45rem;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.rep tbody tr.fila_1 { background: #fff; }
        table.rep tbody tr.fila_2 { background: #f8fafc; }
        table.rep tbody tr:hover { background: #f1f5f9; }
        table.rep .c { text-align: center; }
        table.rep .exp { font-weight: 800; color: var(--ink); font-variant-numeric: tabular-nums; font-size: 12px; }
        table.rep input[type=file] {
            width: 100%;
            font-size: 11px;
        }
        table.rep a.icon {
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px; height: 22px;
            text-decoration: none;
        }
        table.rep a.icon:hover { color: var(--ink); }
        table.rep .miss { color: #94a3b8; }
        table.rep .ok { color: var(--ink-2); }
        table.rep tr.item td {
            text-align: center;
            padding: .85rem .45rem;
            background: #fff;
            border-bottom: none;
        }

        .empty {
            text-align: center;
            padding: 1.5rem;
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }
        .ver-todo {
            text-align: center;
            margin-top: .75rem;
        }
        .ver-todo a {
            font-weight: 800;
            color: var(--ink);
            text-decoration: none;
        }
        .ver-todo a:hover { text-decoration: underline; }

        .col-exp { width: 8%; }
        .col-nom { width: 25%; }
        .col-fec { width: 9%; }
        .col-area { width: 8%; }
        .col-obs { width: 28%; }
        .col-ojo { width: 2%; }
        .col-file { width: 18%; }
        .col-ok { width: 2%; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.segi_adjuntar'])
</head>
<body data-sigtram-screen="mod_gm.mod_tram_exte.segi_adjuntar" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.segi_adjuntar'])
<div class="wrap">

    @if (!empty($ok))
        <div class="flash">{{ $ok }}</div>
    @endif

    {{-- Filtros: misma disposición que segi_adjuntar.php --}}
    <form method="GET" action="{{ $urlModulo }}" class="search-box" name="name">
        <div class="row">
            <label for="criterio">Palabra:</label>
            <input name="criterio" type="text" id="criterio" maxlength="80" value="{{ $criterio }}">
            &nbsp;
            <label for="D1">Buscar por:</label>
            <select name="D1" id="D1">
                <option value="p_dto_trmtes.nu_expe_todo" @selected($d1 === 'p_dto_trmtes.nu_expe_todo')>Nº de Expediente</option>
                <option value="a_areas.de_area" @selected($d1 === 'a_areas.de_area')>Area</option>
                <option value="p_usrios.no_crto" @selected($d1 === 'p_usrios.no_crto')>Nombre o Razon Social</option>
                <option value="a_tpo_trmtes.dc_tpo_trmte" @selected($d1 === 'a_tpo_trmtes.dc_tpo_trmte')>Tipo de documento</option>
                <option value="p_dto_trmtes.de_obse_todo" @selected($d1 === 'p_dto_trmtes.de_obse_todo')>Observaciones</option>
                <option value="p_usrios_1.no_crto" @selected($d1 === 'p_usrios_1.no_crto')>Usuario de Plataforma</option>
            </select>
            &nbsp;
            <button type="submit" class="btn">Buscar</button>
        </div>
        <div class="row">
            <label for="fecha">Fecha:</label>
            <input name="fecha" type="text" id="fecha" value="{{ $fecha }}" placeholder="dd/mm/aaaa" pattern="\d{2}/\d{2}/\d{4}">
        </div>
    </form>

    @if ($total <= 0)
        <div class="empty">No se encontraron resultados</div>
    @else
        @php
            $qsBase = array_filter(['criterio' => $criterio, 'D1' => $d1, 'fecha' => $fecha], fn ($v) => $v !== null && $v !== '');
            // Paginación por secciones de 15 (como el antiguo)
            $seccion = intdiv($pagina - 1, 15);
            $inicio = ($seccion * 15) + 1;
            $final = min($inicio + 14, $numPags);
        @endphp

        <div class="pager">
            @if ($pagina > 1)
                <a href="{{ $urlModulo }}?{{ http_build_query($qsBase + ['pagina' => $pagina - 1]) }}">Anterior</a>
            @endif
            @for ($i = $inicio; $i <= $final; $i++)
                @if ($i === $pagina)
                    <b>{{ $i }}</b>
                @else
                    <a href="{{ $urlModulo }}?{{ http_build_query($qsBase + ['pagina' => $i]) }}">{{ $i }}</a>
                @endif
            @endfor
            @if ($pagina < $numPags)
                <a href="{{ $urlModulo }}?{{ http_build_query($qsBase + ['pagina' => $pagina + 1]) }}">Siguiente</a>
            @endif
        </div>

        <form method="POST" action="{{ $urlModulo }}" enctype="multipart/form-data" name="frm_adjuntar" id="frm_adjuntar">
            @csrf
            <input type="hidden" name="criterio_keep" value="{{ $criterio }}">
            <input type="hidden" name="D1_keep" value="{{ $d1 }}">
            <input type="hidden" name="fecha_keep" value="{{ $fecha }}">
            <input type="hidden" name="pagina_keep" value="{{ $pagina }}">

            <div class="tbl-wrap">
                <table class="rep">
                    <thead>
                        <tr>
                            <th class="col-exp">Nº Exp.</th>
                            <th class="col-nom">Nombre o Razón Social</th>
                            <th class="col-fec c">F. Entrada</th>
                            <th class="col-area c">Area</th>
                            <th class="col-obs">Observaciones</th>
                            <th class="col-ojo c">&nbsp;</th>
                            <th class="col-file">&nbsp;</th>
                            <th class="col-ok c">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $i => $row)
                        @php
                            $n = $i + 1;
                            $fila = ($n % 2 === 1) ? 'fila_1' : 'fila_2';
                        @endphp
                        <tr class="{{ $fila }}">
                            <td class="exp" style="height:23px">
                                {{ $row->nu_expe_fmt }}
                                <input type="hidden" name="hdn_cdgo_trmte_dcmnto{{ $n }}" id="hdn_cdgo_trmte_dcmnto{{ $n }}" value="{{ $row->cdgo_trmte_dcmnto }}">
                            </td>
                            <td title="{{ $row->no_crto }}">{{ $row->no_crto_corto }}</td>
                            <td class="c">{{ $row->fe_fmt }}</td>
                            <td class="c" title="{{ $row->de_area }}">{{ $row->dc_area_corto }}</td>
                            <td title="{{ $row->de_obse_trmte }}">{{ $row->obse_corto }}</td>
                            <td class="c">
                                <a class="icon" href="{{ route('tramites.busca_expedi', ['cdgo_tram' => $row->cdgo_dto_trmte, 'anex' => 1]) }}"
                                   onclick="window.open(this.href, this.target, 'scrollbars=auto,width=470,height=510'); return false;"
                                   target="_blank" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                            <td>
                                <input type="file" name="fle_de_urls_imag{{ $n }}" id="fle_de_urls_imag{{ $n }}" class="texto">
                            </td>
                            <td class="c">
                                @if ($row->de_urls_imag === null || $row->de_urls_imag === '')
                                    <span class="miss" title="Sin adjunto"><i class="fas fa-times"></i></span>
                                @else
                                    <a class="icon ok" href="{{ route('tramites.adjuntar.archivo', ['nombre' => $row->de_urls_imag]) }}"
                                       onclick="window.open(this.href, this.target); return false;"
                                       target="_blank" title="Ver archivo">
                                        <i class="fas fa-check"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                        <tr class="item">
                            <td colspan="8">
                                <input type="hidden" name="hdn_cont" id="hdn_cont" value="{{ count($rows) }}">
                                <button type="submit" name="btn_adjuntar" id="btn_adjuntar" class="btn" value="Adjuntar">Adjuntar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </form>
    @endif

    <div class="ver-todo"><b><a href="{{ url('/mod_gm/mod_tram_exte/segi_adjuntar') }}">Ver todo</a></b></div>
</div>
</body>
</html>
