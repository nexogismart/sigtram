<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f172a;
            --line: #d8dee6;
            --bg: #f6f8fb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px;
            font-family: "Figtree", "Segoe UI", Tahoma, sans-serif;
            font-size: 12px;
            color: #334155;
            background: var(--bg);
        }
        .wrap {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px 14px;
        }
        .title_td {
            font-weight: 700;
            font-size: 14px;
            color: var(--ink);
            text-align: center;
            padding-bottom: 10px;
        }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 4px; vertical-align: middle; }
        .item { font-weight: 700; color: #1e293b; text-align: right; white-space: nowrap; }
        .itemtex { text-align: left; }
        input[type="file"], input[type="number"], textarea {
            border: 1px solid var(--line);
            border-radius: 4px;
            padding: 4px 6px;
            font: inherit;
            background: #fff;
        }
        textarea { width: 95%; resize: vertical; }
        .btns { text-align: center; padding-top: 14px; }
        .Button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--ink);
            background: var(--ink);
            color: #fff;
            font: inherit;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 6px;
        }
        .Button.secondary { background: #fff; color: var(--ink); }
        .Button.secondary:hover { background: #f1f5f9; }
        .Button:hover { background: #1e293b; }
        .Button.secondary:hover { color: var(--ink); }
        .error { color: #dc2626; font-size: 11px; margin-top: 2px; }
    </style>
</head>
<body onload="document.form1.TA1.focus();">
<div class="wrap">
<form name="form1" id="form1" action="{{ $storeUrl }}" method="post" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="Cha" id="Cha" value="{{ $idTram }}">
    <input type="hidden" name="hdn_tipo" id="hdn_tipo" value="{{ $tipo }}">

    <table>
        <tr>
            <td class="title_td" colspan="3">{{ $titulo }}</td>
        </tr>
        <tr>
            <td width="18%" class="item">Fecha:</td>
            <td width="75%" class="itemtex">&nbsp;&nbsp;{{ $fechaMostrar }}</td>
            <td width="7%"></td>
        </tr>
        <tr>
            <td class="item">Adjuntar:</td>
            <td class="itemtex">&nbsp;&nbsp;<input type="file" name="fle_adjunto" id="fle_adjunto" style="width:90%"></td>
            <td></td>
        </tr>
        <tr>
            <td class="item">Folio:</td>
            <td class="itemtex">
                &nbsp;&nbsp;<input type="number" name="txt_nu_foli_anex" id="txt_nu_foli_anex" style="width:15%" value="0" min="0">
                Cantidad de folios adjuntados
                @error('txt_nu_foli_anex')
                    <div class="error">{{ $message }}</div>
                @enderror
            </td>
            <td></td>
        </tr>
        <tr>
            <td class="item" valign="top">Observaciones:</td>
            <td colspan="2">
                &nbsp;&nbsp;<textarea name="TA1" id="TA1" cols="45" rows="5">{{ old('TA1', $mensaje) }}</textarea>
                @error('TA1')
                    <div class="error">{{ $message }}</div>
                @enderror
            </td>
        </tr>
        <tr>
            <td colspan="3" class="btns">
                <button type="button" class="Button secondary" onclick="window.close()">Cancelar</button>
                <button type="button" class="Button" onclick="return validaanexo()">Generar</button>
            </td>
        </tr>
    </table>
</form>
</div>
<script>
function validaanexo() {
    var foli = document.form1.txt_nu_foli_anex.value;
    if (foli === '') {
        alert('Debe especificar la cantidad de folios');
        document.form1.txt_nu_foli_anex.focus();
        return false;
    }
    if (document.form1.TA1.value === '') {
        alert('Debe especificar las observaciones');
        document.form1.TA1.focus();
        return false;
    }
    if (!confirm('¿Confirma generar el anexo/reingreso?')) {
        return false;
    }
    document.form1.submit();
    return false;
}
</script>
</body>
</html>
