<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cita Virtual — {{ $numeroExpediente }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#0f172a; --line:#d8dee6; --bg:#f6f8fb; }
        * { box-sizing: border-box; }
        body { margin:0; padding:12px; font-family:"Figtree","Segoe UI",Tahoma,sans-serif; font-size:12px; color:#334155; background:var(--bg); }
        .wrap { background:#fff; border:1px solid var(--line); border-radius:8px; padding:12px 14px; }
        .titulo { text-align:center; font-weight:700; font-size:15px; color:var(--ink); margin-bottom:12px; }
        table { width:100%; border-collapse:collapse; }
        td { padding:4px; vertical-align:middle; }
        .item { font-weight:700; color:#1e293b; text-align:right; white-space:nowrap; }
        .itemtex { text-align:left; }
        input[type=date], input[type=time], textarea {
            border:1px solid var(--line); border-radius:4px; padding:4px 6px; font:inherit;
        }
        textarea { width:100%; min-height:60px; }
        .btns { text-align:center; padding-top:14px; }
        .Button {
            border:1px solid var(--ink); background:var(--ink); color:#fff; font:inherit; font-weight:700;
            padding:5px 12px; border-radius:5px; cursor:pointer; margin:0 4px;
        }
        .Button.secondary { background:#fff; color:var(--ink); }
        .error { color:#dc2626; font-size:11px; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.gene_expedi_cita'])
</head>
<body data-sigtram-screen="mod_gm.mod_tram_exte.gene_expedi_cita" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.gene_expedi_cita'])
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
        <button type="button" class="Button secondary" onclick="window.close()">Cerrar</button>
    </div>
</form>
</div>
</body>
</html>
