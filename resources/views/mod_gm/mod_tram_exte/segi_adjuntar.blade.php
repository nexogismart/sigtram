<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sigtram | Adjuntar</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/segi-adjuntar.css', 'resources/js/app.js'])
</head>
<body class="segi-adjuntar-body">
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
                                   data-action="popup" data-width="470" data-height="510"
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
