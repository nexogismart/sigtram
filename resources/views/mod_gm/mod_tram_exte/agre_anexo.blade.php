<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/modales-tramite.css', 'resources/js/app.js'])

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.agre_anexo', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body class="modal-body">
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.agre_anexo', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}

<div class="wrap" id="agre-anexo-app" x-data="agreAnexoForm">
<form name="form1" id="form1" action="{{ $storeUrl }}" method="post" enctype="multipart/form-data" @submit.prevent="submitForm">
    @csrf
    <input type="hidden" name="Cha" id="Cha" value="{{ $idTram }}">
    <input type="hidden" name="hdn_tipo" id="hdn_tipo" value="{{ $tipo }}">

    <table>
        <tr>
            <td class="title_td" colspan="3">{{ $titulo }}</td>
        </tr>
        <tr>
            <td style="width:18%" class="item">Fecha:</td>
            <td style="width:75%" class="itemtex">&nbsp;&nbsp;{{ $fechaMostrar }}</td>
            <td style="width:7%"></td>
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
            <td class="item" style="vertical-align:top">Observaciones:</td>
            <td colspan="2">
                &nbsp;&nbsp;<textarea name="TA1" id="TA1" cols="45" rows="5" autofocus>{{ old('TA1', $mensaje) }}</textarea>
                @error('TA1')
                    <div class="error">{{ $message }}</div>
                @enderror
            </td>
        </tr>
        <tr>
            <td colspan="3" class="btns">
                <button type="button" class="Button secondary" data-action="close">Cancelar</button>
                <button type="submit" class="Button">Generar</button>
            </td>
        </tr>
    </table>
</form>
</div>
</body>
</html>
