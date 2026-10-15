<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tituloPrefijo }}{{ $numeroExpediente }}</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/modales-tramite.css', 'resources/js/app.js'])

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.busca_expedi', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body class="modal-body-compact">
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.busca_expedi', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}

@php
    $idTram = (int) $tramite->cdgo_dto_trmte;
    $tieneSilencio = (string) ($tramite->in_sile_admi ?? '') === '1';
    $diasSile = $tramite->nu_tiem_sile ?? '';
@endphp
<div class="wrap">
<form action="{{ $modificarUrl }}" method="post" name="form1" id="form1">
    @csrf
    <input type="hidden" name="id_tram" id="id_tram" value="{{ $idTram }}">

    <div class="titulo">{{ $tituloPrefijo }}{{ $numeroExpediente }}</div>

    <table>
        <tr>
            <td class="item" width="28%">Tipo de documento:</td>
            <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $tramite->de_tpo_trmte }}</td>
        </tr>
    </table>

    @if ($persona && $persona->tipo === '0' && $persona->data)
        <table>
            <tr>
                <td class="item" width="28%">Nº de {{ $persona->data->tipo_doc }}:</td>
                <td class="itemtex" width="22%">&nbsp;&nbsp;{{ $persona->data->nu_docu }}</td>
                <td class="item" width="22%">Nombres:</td>
                <td class="itemtex" width="28%">&nbsp;&nbsp;{{ $persona->data->nombres }}</td>
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
                <td class="item">Correo:</td>
                <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $correo }}</td>
            </tr>
        </table>
    @elseif ($persona && $persona->tipo === '1' && $persona->data)
        <table>
            <tr>
                <td class="item" width="28%">Razón Social:</td>
                <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $persona->data->rz_soci }}</td>
            </tr>
            <tr>
                <td class="item">Nº Ruc:</td>
                <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $persona->data->ruc }}</td>
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
                <td class="item">Correo:</td>
                <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $correo }}</td>
            </tr>
        </table>
    @endif

    <table>
        <tr>
            <td class="item" width="28%">Folio:</td>
            <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $tramite->nu_foli_trmte }}</td>
        </tr>
        <tr>
            <td class="item">Fecha ingreso:</td>
            <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $fechaIngreso }}</td>
        </tr>
    </table>

    @if ($tramite->cdgo_docmnto_trmte)
        <fieldset>
            <legend>Datos del Expediente</legend>
            <table>
                <tr>
                    <td class="item" width="28%">Servicio:&nbsp;&nbsp;</td>
                    <td class="itemtex">{{ $servicio }}</td>
                </tr>
                <tr>
                    <td class="item">Tipo de tramite:&nbsp;&nbsp;</td>
                    <td class="itemtex">{{ $tipoTramiteServ }}</td>
                </tr>
            </table>
        </fieldset>
    @endif

    <div class="silencio">
        Silen. Administ.:
        @if ($tieneSilencio)
            <i class="fas fa-check-circle ico-ok" title="Con Silencio"></i> {{ $diasSile !== null && $diasSile !== '' ? $diasSile : '0' }} Dias
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
                <textarea name="TA1" id="TA1" rows="3">{{ $tramite->de_obse_trmte }}</textarea>
            </td>
        </tr>
        <tr>
            <td>
                <div class="requi">
                    @if ($requisitos->count() >= 1)
                        <table>
                            @php $i = 0; @endphp
                            @foreach ($requisitos as $r)
                                @php $i++; @endphp
                                <tr>
                                    <td width="7%" align="center">
                                        <input type="hidden" name="cont{{ $i }}" id="cont{{ $i }}" value="{{ $r->cdgo_rqto_trmte }}">
                                        @if ((string) $r->in_pres === '1')
                                            <input type="hidden" name="Che{{ $i }}" id="Che{{ $i }}" value="1">
                                            <i class="fas fa-check ico-ok"></i>
                                        @else
                                            <input type="checkbox" name="Che{{ $i }}" id="Che{{ $i }}" value="1">
                                        @endif
                                    </td>
                                    <td width="93%" title="{{ $r->de_rqto_trmte }}">{{ $r->dc_rqto_trmte }}</td>
                                </tr>
                            @endforeach
                            <input type="hidden" name="req_count" value="{{ $i }}">
                        </table>
                    @else
                        <span class="sin-req">&nbsp;&nbsp;&nbsp;Sin Requisitos</span>
                        <input type="hidden" name="req_count" value="0">
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="btns">
        @php
            $cdgoTpo = (string) ($tramite->cdgo_tpo_trmte ?? '');
            $estado = (string) $cdgoEstado;
        @endphp

        @if ($cdgoTpo === '55' && $estado !== '3')
            <button type="button" class="Button secondary" data-href="{{ $citaUrl }}">Cita Virtual</button>
        @endif

        @if (($cdgoTpo === '40' || $cdgoTpo === '55') && $estado !== '3')
            <button type="button" class="Button secondary" data-href="{{ $generarUrl }}">Generar Expediente</button>
        @else
            @if ($anex === '1' && $estado !== '3')
                <button type="button" class="Button secondary" id="Modificar" data-action="submit-confirm" data-confirm="Esta Seguro de Modificar los datos">Modificar</button>
            @endif
            <button type="button" class="Button secondary" data-action="popup" data-url="{{ $imprUrl }}" data-width="1" data-height="1">Imprimir</button>
            @if ($estado !== '3' && $estado !== '26')
                <button type="button" class="Button secondary" data-action="popup" data-url="{{ $anexoUrl }}" data-width="450" data-height="350">Ingresar Anexo</button>
            @elseif ($estado === '3' || $estado === '26')
                <button type="button" class="Button secondary" data-action="popup" data-url="{{ $reabrirUrl }}" data-width="450" data-height="350">Reabrir trámite</button>
            @endif
        @endif

        <button type="button" class="Button" data-action="popup" data-url="{{ $voucherUrl }}" data-width="400" data-height="580">Ver Vaucher</button>
        <button type="button" class="Button secondary" data-action="close">Cerrar</button>
    </div>
</form>
</div>
</body>
</html>
