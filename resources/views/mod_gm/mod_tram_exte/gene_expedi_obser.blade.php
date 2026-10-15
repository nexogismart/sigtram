<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generar Trámite — {{ $numeroExpediente }}</title>
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
        fieldset { border:1px solid var(--line); border-radius:6px; margin:8px 0; padding:8px; }
        legend { font-weight:700; padding:0 4px; }
        input[type=text], select, textarea {
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
        label { margin-right:18px; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.gene_expedi_obser'])
</head>
<body data-sigtram-screen="mod_gm.mod_tram_exte.gene_expedi_obser" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.gene_expedi_obser'])
@php
    $areasJson = $todasAreas->map(fn ($a) => [
        'id' => (int) $a->cdgo_area,
        'nombre' => $a->de_area,
        'padre' => (int) ($a->cdgo_area_prim ?? 0),
    ])->values();
@endphp
<div class="wrap">
<form action="{{ $storeUrl }}" method="post" name="form1" id="form1">
    @csrf
    <input type="hidden" name="id_tram" value="{{ $tramite->cdgo_dto_trmte }}">
    <input type="hidden" name="hdn_cdgo_usrios" value="{{ $tramite->cdgo_usrios }}">

    <div class="titulo">Generar Trámite de {{ $numeroExpediente }}</div>

    <table>
        <tr>
            <td class="item" width="28%">Tipo de documento:</td>
            <td class="itemtex" colspan="3">
                <label><input type="radio" name="rdn_cdgo_tpo_trmtes" value="22" @checked(old('rdn_cdgo_tpo_trmtes', '22') == '22')> Expediente</label>
                <label><input type="radio" name="rdn_cdgo_tpo_trmtes" value="17" @checked(old('rdn_cdgo_tpo_trmtes') == '17')> Simple</label>
            </td>
        </tr>
    </table>

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
        </table>
    @endif

    <table style="margin-top:6px">
        <tr>
            <td class="item" width="28%">Folio:</td>
            <td class="itemtex" colspan="3">
                &nbsp;&nbsp;<input name="T6" type="text" id="T6" value="{{ old('T6', '') }}" maxlength="4" style="width:48px" inputmode="numeric">
            </td>
        </tr>
    </table>

    <fieldset>
        <legend>Destino del Expediente</legend>
        <table>
            <tr>
                <td class="item" width="24%">Gerencia:</td>
                <td class="itemtex">
                    <select name="Se2" id="Se2" style="width:100%; max-width:340px" onchange="cargarSubgerencias()">
                        <option value="0">-- Seleccione --</option>
                        @foreach ($gerencias as $g)
                            <option value="{{ $g->cdgo_area }}" @selected((int) old('Se2', $idArea) === (int) $g->cdgo_area)>
                                {{ $g->de_area }}
                            </option>
                        @endforeach
                    </select>
                    @error('Se2')<div class="error">{{ $message }}</div>@enderror
                </td>
            </tr>
            <tr>
                <td class="item">Sub Gerencia:</td>
                <td class="itemtex">
                    <select name="Se6" id="Se6" style="width:100%; max-width:340px">
                        <option value="0">-- Seleccione la subgerencia --</option>
                        @foreach ($subgerencias as $sg)
                            <option value="{{ $sg->cdgo_area }}" @selected((int) old('Se6', $idSubarea) === (int) $sg->cdgo_area)>
                                {{ $sg->de_area }}
                            </option>
                        @endforeach
                    </select>
                </td>
            </tr>
        </table>
    </fieldset>

    <table>
        <tr><td class="item" style="text-align:left" colspan="4">Observaciones:</td></tr>
        <tr>
            <td colspan="4"><textarea name="TA1" id="TA1">{{ old('TA1', $tramite->de_obse_trmte) }}</textarea></td>
        </tr>
    </table>

    <div class="btns">
        <button type="submit" class="Button">Generar Expediente</button>
        <button type="button" class="Button secondary" onclick="window.close()">Cerrar</button>
    </div>
</form>
</div>
<script>
const AREAS = @json($areasJson);
function cargarSubgerencias() {
    const padre = parseInt(document.getElementById('Se2').value || '0', 10);
    const sel = document.getElementById('Se6');
    const actual = sel.value;
    sel.innerHTML = '<option value="0">-- Seleccione la subgerencia --</option>';
    AREAS.filter(a => a.padre === padre).forEach(a => {
        const opt = document.createElement('option');
        opt.value = a.id;
        opt.textContent = a.nombre;
        if (String(a.id) === String(actual)) opt.selected = true;
        sel.appendChild(opt);
    });
}
</script>
</body>
</html>
