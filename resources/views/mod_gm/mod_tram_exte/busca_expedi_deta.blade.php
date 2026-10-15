<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tituloPrefijo }}{{ $numeroExpediente }}</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/modales-tramite.css', 'resources/js/app.js'])

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.busca_expedi_deta', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body class="modal-body-compact">
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.busca_expedi_deta', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}

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
                    <td class="itemtex text-justify" colspan="3">{{ $tipoTramiteServ }}</td>
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
            <td class="item item-left">Observaciones:</td>
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
        <button type="button" class="Button" data-action="close">Cerrar</button>
        <button type="button" class="Button secondary" data-action="popup" data-url="{{ $imprUrl }}" data-width="1" data-height="1">Imprimir</button>
    </div>
</div>
</body>
</html>
