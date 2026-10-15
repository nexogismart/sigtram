<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cita Virtual — {{ $numeroExpediente }}</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/modales-tramite.css', 'resources/js/app.js'])
</head>
<body class="modal-body">
<div class="wrap">
<form action="{{ $storeUrl }}" method="post" name="form1">
    @csrf
    <input type="hidden" name="id_tram" value="{{ $tramite->cdgo_dto_trmte }}">
    <input type="hidden" name="hdn_cdgo_usrios" value="{{ $tramite->cdgo_usrios }}">

    <div class="titulo">Generar Cita Virtual de {{ $numeroExpediente }}</div>

    @if ($persona && $persona->tipo === '0' && $persona->data)
        <table>
            <tr>
                <td class="item" width="28%">Nº de {{ $persona->data->tipo_doc }}:</td>
                <td class="itemtex" width="22%">&nbsp;&nbsp;{{ $persona->data->nu_docu }}</td>
                <td class="item" width="22%">Nombres:</td>
                <td class="itemtex">&nbsp;&nbsp;{{ $persona->data->nombres }}</td>
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
                <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $correo !== '' ? $correo : 'No Presento' }}</td>
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
                <td class="item">E-mail:</td>
                <td class="itemtex" colspan="3">&nbsp;&nbsp;{{ $correo !== '' ? $correo : 'No Presento' }}</td>
            </tr>
        </table>
    @endif

    <table style="margin-top:8px">
        <tr>
            <td class="item" width="28%">Fecha Cita:</td>
            <td class="itemtex" width="30%">&nbsp;&nbsp;<input name="txt_fecha" type="date" id="txt_fecha" value="{{ old('txt_fecha') }}" required></td>
            <td class="item" width="15%">Hora Cita:</td>
            <td class="itemtex">&nbsp;&nbsp;<input name="txt_hora" type="time" id="txt_hora" value="{{ old('txt_hora') }}" required></td>
        </tr>
        @error('txt_fecha')
            <tr><td colspan="4" class="error">{{ $message }}</td></tr>
        @enderror
        <tr>
            <td class="item" style="text-align:left" colspan="4">Observaciones:</td>
        </tr>
        <tr>
            <td colspan="4"><textarea name="TA1" id="TA1">{{ old('TA1') }}</textarea></td>
        </tr>
    </table>

    <div class="btns">
        <button type="submit" class="Button">Generar Cita</button>
        <button type="button" class="Button secondary" data-action="close">Cerrar</button>
    </div>
</form>
</div>
</body>
</html>
