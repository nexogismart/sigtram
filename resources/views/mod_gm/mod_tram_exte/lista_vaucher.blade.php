<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lista de Vauchers</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/modales-tramite.css', 'resources/js/app.js'])
</head>
<body class="modal-body">
<table class="vaucher-table">
    <thead>
        <tr>
            <th style="width:55%">Descripción</th>
            <th style="width:25%" class="center">Fecha</th>
            <th style="width:20%" class="center">Hora</th>
        </tr>
    </thead>
    <tbody>
        <tr class="fila fila_1" data-href="{{ $voucherPrincipalUrl }}">
            <td>Vaucher Principal</td>
            <td class="center">{{ $fechaDia }}</td>
            <td class="center">{{ $fechaHora }}</td>
        </tr>
        @foreach ($anexos as $idx => $anexo)
            <tr class="fila {{ $idx % 2 === 0 ? 'fila_2' : 'fila_1' }}"
                data-href="{{ $anexo->url }}">
                <td>Vaucher Anexo</td>
                <td class="center">{{ $anexo->fecha }}</td>
                <td class="center">{{ $anexo->hora }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
