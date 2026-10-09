<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Imprimir expediente</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 12px;
            font-family: "Figtree", "Segoe UI", Tahoma, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #fff;
        }
        table { width: 440px; border-collapse: collapse; margin: 0 auto; }
        td { padding: 3px 4px; vertical-align: middle; }
        .item { font-weight: 700; text-align: right; white-space: nowrap; }
        .textoblack { text-align: left; }
        .titulo { text-align: center; font-size: 14px; font-weight: 700; padding: 8px 0; }
        fieldset { border: 1px solid #cbd5e1; margin: 6px 0; padding: 6px 8px; }
        legend { font-weight: 700; padding: 0 4px; }
        .silencio { text-align: center; font-weight: 700; padding: 6px 0; }
        .ico-ok { color: #16a34a; }
        .ico-no { color: #dc2626; }
        .requi td { padding: 2px 4px; }
        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print();">
@php
    $tieneSilencio = (string) ($tramite->in_sile_admi ?? '') === '1';
    $cdgoTpo = (string) ($tramite->cdgo_tpo_trmte ?? '');
    $tipoDocum = $cdgoTpo === '17' ? 'Documento Simple N°: ' : 'Número del Expediente: ';
@endphp
<form name="form1">
    <table>
        <tr>
            <td colspan="4" class="titulo">{{ $tipoDocum }}{{ $tramite->nu_expe_todo }}</td>
        </tr>
        <tr>
            <td class="item" width="28%">Tipo de documento:</td>
            <td class="textoblack" width="22%">&nbsp;&nbsp;{{ $tramite->de_tpo_trmte }}</td>
            <td class="item" width="28%">Nº Documento:</td>
            <td class="textoblack" width="22%">&nbsp;&nbsp;{{ $tramite->nu_docu_trmte }}</td>
        </tr>

        @if ($persona && $persona->tipo === '0' && $persona->data)
            <tr><td colspan="4">
                <table width="100%">
                    <tr>
                        <td class="item" width="25%">Nº de {{ $persona->data->tipo_doc }}:</td>
                        <td class="textoblack" width="20%">&nbsp;&nbsp;{{ $persona->data->nu_docu }}</td>
                        <td class="item" width="25%">Nombres:</td>
                        <td class="textoblack" width="30%">&nbsp;&nbsp;{{ $persona->data->nombres }}</td>
                    </tr>
                    <tr>
                        <td class="item">Apellido paterno:</td>
                        <td class="textoblack">&nbsp;&nbsp;{{ $persona->data->ap_pate }}</td>
                        <td class="item">Apellido materno:</td>
                        <td class="textoblack">&nbsp;&nbsp;{{ $persona->data->ap_mate }}</td>
                    </tr>
                    <tr>
                        <td class="item">Domicilio Fiscal:</td>
                        <td class="textoblack" colspan="3">&nbsp;&nbsp;{{ $persona->data->de_dire }}</td>
                    </tr>
                    <tr>
                        <td class="item">Teléfono:</td>
                        <td class="textoblack" colspan="3">&nbsp;&nbsp;{{ $persona->data->nu_tele }}</td>
                    </tr>
                </table>
            </td></tr>
        @elseif ($persona && $persona->tipo === '1' && $persona->data)
            <tr><td colspan="4">
                <table width="100%">
                    <tr>
                        <td class="item" width="25%">Razon Social:</td>
                        <td class="textoblack" colspan="3">&nbsp;&nbsp;{{ $persona->data->rz_soci }}</td>
                    </tr>
                    <tr>
                        <td class="item">Nº Ruc:</td>
                        <td class="textoblack" colspan="3">&nbsp;&nbsp;{{ $persona->data->ruc }}</td>
                    </tr>
                    <tr>
                        <td class="item">Domicilio Fiscal:</td>
                        <td class="textoblack" colspan="3">&nbsp;&nbsp;{{ $persona->data->de_dire }}</td>
                    </tr>
                    <tr>
                        <td class="item">Teléfono:</td>
                        <td class="textoblack" colspan="3">&nbsp;&nbsp;{{ $persona->data->nu_tele }}</td>
                    </tr>
                </table>
            </td></tr>
        @endif

        <tr>
            <td class="item">Foleo:</td>
            <td class="textoblack" colspan="3">&nbsp;&nbsp;{{ $tramite->nu_foli_trmte }}</td>
        </tr>
        <tr>
            <td class="item">Fecha ingreso:</td>
            <td class="textoblack">&nbsp;&nbsp;{{ $fechaIngreso }}</td>
            <td class="item">Fecha salida:</td>
            <td class="textoblack">&nbsp;&nbsp;{{ $fechaSalida }}</td>
        </tr>

        @if ($tramite->cdgo_docmnto_trmte)
            <tr>
                <td colspan="4">
                    <fieldset>
                        <legend>Datos del Expediente</legend>
                        <table width="100%">
                            <tr>
                                <td class="item" width="25%">Servicio:</td>
                                <td class="textoblack">&nbsp;&nbsp;{{ $servicio }}</td>
                            </tr>
                            <tr>
                                <td class="item">Tipo de tramite:</td>
                                <td class="textoblack" align="justify">&nbsp;&nbsp;{{ $tipoTramiteServ }}</td>
                            </tr>
                        </table>
                    </fieldset>
                </td>
            </tr>
        @endif

        <tr>
            <td colspan="4" class="silencio">
                Silen. Administ.:
                @if ($tieneSilencio)
                    <i class="fas fa-check-circle ico-ok"></i>
                @else
                    <i class="fas fa-times-circle ico-no"></i>
                @endif
            </td>
        </tr>
        <tr>
            <td class="item" style="text-align:left" colspan="4">Observaciones:</td>
        </tr>
        <tr>
            <td colspan="4" class="textoblack" align="justify">{{ $tramite->de_obse_trmte }}<br><br></td>
        </tr>
        <tr>
            <td colspan="4">
                @if ($requisitos->count() >= 1)
                    <table class="requi" width="100%">
                        @foreach ($requisitos as $r)
                            <tr>
                                <td width="7%" align="center">
                                    @if ((string) $r->in_pres === '1')
                                        <i class="fas fa-check ico-ok"></i>
                                    @else
                                        <i class="fas fa-times ico-no"></i>
                                    @endif
                                </td>
                                <td width="93%">{{ $r->dc_rqto_trmte }}</td>
                            </tr>
                        @endforeach
                    </table>
                @else
                    <span>&nbsp;&nbsp;&nbsp;Sin Requisitos</span>
                @endif
            </td>
        </tr>
    </table>
</form>
</body>
</html>
