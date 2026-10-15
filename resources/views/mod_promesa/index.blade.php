<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audiencias Públicas</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('mod_promesa/css/app.css') }}?v=4">
    @include('components.sigtram.module-head', ['screenKey' => 'mod_promesa.index'])
</head>
<body data-sigtram-screen="mod_promesa.index" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_promesa.index'])
<main class="app">
    <div class="app__title-row">
        <h1>Audiencias Públicas</h1>
    </div>

    <section class="card">
        <h2 class="card__title">Consultas</h2>
        <form class="filters" id="frmConsultas" autocomplete="off" onsubmit="return false;">
            <div class="field">
                <label for="sel_criterio">Opción</label>
                <select id="sel_criterio" name="sel_criterio">
                    <option value="zona_nombre">AA.HH Urb. Gru.</option>
                </select>
            </div>
            <div class="field">
                <label for="txt_palabra">Palabra</label>
                <input id="txt_palabra" name="txt_palabra" type="text" placeholder="escriba en MAYUSCULAS">
            </div>
            <div class="field">
                <label for="txt_fechadesde">Desde</label>
                <input id="txt_fechadesde" name="txt_fechadesde" type="date">
            </div>
            <div class="field">
                <label for="txt_fechahasta">Hasta</label>
                <input id="txt_fechahasta" name="txt_fechahasta" type="date">
            </div>
            <div class="field" style="max-width: 100px;">
                <label for="txt_cantidad">Registros</label>
                <input id="txt_cantidad" name="txt_cantidad" type="text" value="7">
            </div>
            <div class="filters__actions">
                <button class="btn btn--primary" type="button" id="btn_consultar">Consultar</button>
            </div>
        </form>
    </section>

    @if($accesoPleno)
    <div class="table-meta" style="margin: 0;">
        <button type="button" class="btn btn--ghost" id="btn_nuevo">Nuevo</button>
    </div>
    @endif

    <section class="card">
        <div id="conte_listaLugar"></div>
    </section>

    <section class="card" id="cardPromesas" style="display: none;">
        <div id="conte_promesaLugar"></div>
    </section>
</main>

<div id="detailModal" class="detalles">
    <div class="modal-content">
        <span class="close" id="detailModalClose">&times;</span>
        <div id="modalBody"></div>
    </div>
</div>

<script>
    window.PROMESA_ROUTES = @json($routes);
    window.PROMESA_ACCESO_PLENO = {{ $accesoPleno ? 'true' : 'false' }};
</script>
<script src="{{ asset('mod_promesa/js/app.js') }}?v=4"></script>
</body>
</html>
