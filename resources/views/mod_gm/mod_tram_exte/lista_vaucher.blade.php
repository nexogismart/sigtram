<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lista de Vauchers</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 12px;
            font-family: "Figtree", "Segoe UI", Tahoma, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #f6f8fb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border: 1px solid #d8dee6;
            border-radius: 8px;
            overflow: hidden;
        }
        th {
            background: #0f172a;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-weight: 700;
        }
        td { padding: 8px 10px; border-top: 1px solid #e2e8f0; }
        tr.fila { cursor: pointer; }
        tr.fila:hover { background: #f1f5f9; }
        tr.fila_2 { background: #f8fafc; }
        .center { text-align: center; }
    </style>
    @include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.lista_vaucher'])
</head>
<body data-sigtram-screen="mod_gm.mod_tram_exte.lista_vaucher" class="sigtram-module">
    @include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.lista_vaucher'])
<table>
    <tr>
        <th width="55%">Descripción</th>
        <th width="25%" class="center">Fecha</th>
        <th width="20%" class="center">Hora</th>
    </tr>
    <tr class="fila fila_1" onclick="location.href='{{ $voucherPrincipalUrl }}'">
        <td>Vaucher Principal</td>
        <td class="center">{{ $fechaDia }}</td>
        <td class="center">{{ $fechaHora }}</td>
    </tr>
    @foreach ($anexos as $idx => $anexo)
        <tr class="fila {{ $idx % 2 === 0 ? 'fila_2' : 'fila_1' }}"
            onclick="location.href='{{ $anexo->url }}'">
            <td>Vaucher Anexo</td>
            <td class="center">{{ $anexo->fecha }}</td>
            <td class="center">{{ $anexo->hora }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
