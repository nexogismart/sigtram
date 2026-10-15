<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tituloPrefijo }}{{ $numeroExpediente }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #0f172a;
            --ink-2: #334155;
            --muted: #64748b;
            --line: #d8dee6;
            --bg: #f6f8fb;
            --card: #fff;
            --label: #1e293b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 8px 10px 12px;
            font-family: "Figtree", "Segoe UI", Tahoma, sans-serif;
            font-size: 12px;
            color: var(--ink-2);
            background: var(--bg);
        }
        .wrap {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 8px 10px 10px;
        }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 4px; vertical-align: middle; }
        .item {
            font-weight: 700;
            color: var(--label);
            text-align: right;
            white-space: nowrap;
        }
        .itemtex { text-align: left; color: var(--ink-2); }
        .titulo {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            color: var(--ink);
            padding: 6px 0 8px;
        }
        fieldset {
            border: 1px solid var(--line);
            border-radius: 6px;
            margin: 6px 0;
            padding: 6px 8px 8px;
        }
        legend {
            font-weight: 700;
            color: var(--label);
            padding: 0 4px;
            font-size: 12px;
        }
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 4px 6px;
            font: inherit;
            resize: vertical;
            min-height: 44px;
            color: var(--ink-2);
            background: #fff;
        }
        .requi {
            max-height: 110px;
            overflow-y: auto;
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 4px 6px;
            background: #fafbfc;
            margin-top: 2px;
        }
        .requi table td { padding: 2px 4px; }
        .ico-ok { color: #16a34a; }
        .ico-no { color: #dc2626; }
        .silencio { text-align: center; font-weight: 700; padding: 4px 0; }
        .btns {
            text-align: center;
            padding-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
        }
        .Button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--ink);
            background: var(--ink);
            color: #fff;
            font: inherit;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }
        .Button:hover { background: #1e293b; }
        .Button.secondary {
            background: #fff;
            color: var(--ink);
        }
        .Button.secondary:hover { background: #f1f5f9; }
        .sin-req { color: var(--muted); padding: 4px 8px; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.busca_expedi_deta'])
</head>
<body data-sigtram-screen="mod_gm.mod_tram_exte.busca_expedi_deta" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.busca_expedi_deta'])
@php
    $diasSile = $tramite->nu_tiem_sile ?? '';
    $tieneSilencio = is_numeric($diasSile) && (int) $diasSile >= 1;
@endphp
<div class="wrap">
    <div class="titulo">{{ $tituloPrefijo }}{{ $numeroExpediente }}</div>

    @if ($persona && $persona->tipo === '0' && $persona->data)
        <table>
            <tr>
                <td class="item" width="25%">Nº de {{ $persona->data->tipo_doc }}:</td>
                <td class="itemtex" width="20%">&nbsp;&nbsp;{{ $persona->data->nu_docu }}</td>
                <td class="item" width="25%">Nombres:</td>
                <td class="itemtex" width="30%">&nbsp;&nbsp;{{ $persona->data->nombres }}</td>
            </tr>
            <tr>
                <td class="item">Apellido paterno:</td>
                <td class="itemtex">&nbsp;&nbsp;{{ $persona->data->ap_pate }}</td>
                <td class="item">Apellido materno:</td>
                <td class="itemtex">&nbsp;&nbsp;{{ $persona->data->ap_mate }}</td>
            </tr>
            <tr>
                <td class="item">Domicilio Fiscal:</td>
                <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $persona->data->de_dire }}</td>
            </tr>
            <tr>
                <td class="item">Teléfono:</td>
                <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $persona->data->nu_tele }}</td>
            </tr>
            <tr>
                <td class="item">E-mail:</td>
                <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $correo }}</td>
            </tr>
        </table>
    @elseif ($persona && $persona->tipo === '1' && $persona->data)
        <table>
            <tr>
                <td class="item" width="25%">Razon Social:</td>
                <td class="itemtex" width="75%">&nbsp;&nbsp;{{ $persona->data->rz_soci }}</td>
            </tr>
            <tr>
                <td class="item">Nº Ruc:</td>
                <td class="itemtex">&nbsp;&nbsp;{{ $persona->data->ruc }}</td>
            </tr>
            <tr>
                <td class="item">Domicilio Fiscal:</td>
                <td class="itemtex">&nbsp;&nbsp;{{ $persona->data->de_dire }}</td>
            </tr>
            <tr>
                <td class="item">Teléfono:</td>
                <td class="itemtex">&nbsp;&nbsp;{{ $persona->data->nu_tele }}</td>
            </tr>
            <tr>
                <td class="item">E-mail:</td>
                <td class="itemtex">&nbsp;&nbsp;{{ $correo }}</td>
            </tr>
        </table>
    @endif

    <table>
        <tr>
            <td class="item" width="25%">Folio:</td>
            <td class="itemtex" width="30%">&nbsp;&nbsp;{{ $tramite->nu_foli_trmte }}</td>
            <td width="19%"></td>
            <td width="26%"></td>
        </tr>
        <tr>
            <td class="item">Fecha ingreso:</td>
            <td class="itemtex">&nbsp;&nbsp;{{ $fechaIngreso }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    @if ($tramite->cdgo_docmnto_trmte)
        <fieldset>
            <legend>Datos del Expediente</legend>
            <table>
                <tr>
                    <td class="item" width="22%">Servicio:</td>
                    <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $servicio }}</td>
                </tr>
                <tr>
                    <td class="item">Tipo de tramite:</td>
                    <td class="itemtex" colspan="3" style="text-align:justify;">{{ $tipoTramiteServ }}</td>
                </tr>
            </table>
        </fieldset>
    @endif

    <div class="silencio">
        Silen. Administ.:
        @if ($tieneSilencio)
            <i class="fas fa-check-circle ico-ok" title="Con Silencio"></i> {{ $diasSile }} Dias
        @else
            <i class="fas fa-times-circle ico-no" title="Sin Silencio"></i>
        @endif
    </div>

    <table>
        <tr>
            <td class="item" style="text-align:left">Observaciones:</td>
        </tr>
        <tr>
            <td>
                <textarea name="TA1" id="TA1" rows="2" readonly>{{ $tramite->de_obse_trmte }}</textarea>
            </td>
        </tr>
        <tr>
            <td>
                <div class="requi">
                    @if ($requisitos->count() >= 1)
                        <table>
                            @foreach ($requisitos as $r)
                                <tr>
                                    <td width="7%" align="center">
                                        @if ((string) $r->in_pres === '1')
                                            <i class="fas fa-check-circle ico-ok"></i>
                                        @else
                                            <i class="fas fa-times-circle ico-no"></i>
                                        @endif
                                    </td>
                                    <td width="93%" title="{{ $r->de_rqto_trmte }}">{{ $r->dc_rqto_trmte }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @else
                        <span class="sin-req">&nbsp;&nbsp;&nbsp;Sin Requisitos</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="btns">
        <button type="button" class="Button" onclick="window.close()">Cerrar</button>
        <button type="button" class="Button secondary" onclick="imprimir()">Imprimir</button>
    </div>
</div>
<script>
function imprimir() {
    window.open(@json($imprUrl), '_blank', 'scrollbars=yes,width=1,height=1');
}
</script>
</body>
</html>
