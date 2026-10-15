<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generar Trámite — {{ $numeroExpediente }}</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/modales-tramite.css', 'resources/js/app.js'])
</head>
<body class="modal-body">
@php
    $areasJson = $todasAreas->map(fn ($a) => [
        'id' => (int) $a->cdgo_area,
        'nombre' => $a->de_area,
        'padre' => (int) ($a->cdgo_area_prim ?? 0),
    ])->values();
@endphp
<div class="wrap" id="gene-expedi-obser-app" x-data="geneExpediObserForm" data-areas="{{ json_encode($areasJson) }}">
<form action="{{ $storeUrl }}" method="post" name="form1" id="form1">
    @csrf
    <input type="hidden" name="id_tram" value="{{ $tramite->cdgo_dto_trmte }}">
    <input type="hidden" name="hdn_cdgo_usrios" value="{{ $tramite->cdgo_usrios }}">

    <div class="titulo">Generar Trámite de {{ $numeroExpediente }}</div>

    <table>
        <tr>
            <td class="item" width="28%">Tipo de documento:</td>
            <td class="itemtex" colspan="3">
                <label><input type="radio" name="tipo_tramite" value="E" @checked(old('tipo_tramite', old('rdn_cdgo_tpo_trmtes', 'E')) === 'E')> Expediente</label>
                <label><input type="radio" name="tipo_tramite" value="S" @checked(old('tipo_tramite', old('rdn_cdgo_tpo_trmtes')) === 'S')> Simple</label>
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

    <table class="mt-6px">
        <tr>
            <td class="item" width="28%">Folio:</td>
            <td class="itemtex" colspan="3">
                &nbsp;&nbsp;<input name="T6" type="text" id="T6" value="{{ old('T6', '') }}" maxlength="4" class="w-48px" inputmode="numeric">
            </td>
        </tr>
    </table>

    <fieldset>
        <legend>Destino del Expediente</legend>
        <table>
            <tr>
                <td class="item" width="24%">Gerencia:</td>
                <td class="itemtex">
                    <select name="Se2" id="Se2" class="modal-select-wide" @change="cargarSubgerencias">
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
                    <select name="Se6" id="Se6" class="modal-select-wide">
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
        <tr><td class="item item-left" colspan="4">Observaciones:</td></tr>
        <tr>
            <td colspan="4"><textarea name="TA1" id="TA1">{{ old('TA1', $tramite->de_obse_trmte) }}</textarea></td>
        </tr>
    </table>

    <div class="btns">
        <button type="submit" class="Button">Generar Expediente</button>
        <button type="button" class="Button secondary" data-action="close">Cerrar</button>
    </div>
</form>
</div>
</body>
</html>
