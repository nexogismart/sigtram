<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Bandeja</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background-color: #f4f4f4; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        h2 { text-transform: uppercase; margin-bottom: 5px; }
    </style>
</head>
<body>
    <div style="text-align: center;">
        <h2>Reporte de Bandeja: {{ strtoupper($bandeja) }}</h2>
        <p>Fecha de Impresión: {{ $fechaImpresion }}</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Nº Exp.</th>
                <th>Nombre / Razón Social</th>
                <th>F. Entrada</th>
                <th>Estado</th>
                <th>Observaciones</th>
                <th>Origen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td>{{ $item->tramite_formateado }}</td>
                <td>{{ $item->no_crto }}</td>
                <td>{{ $item->fe_rcbdo_trmte }}</td>
                <td>{{ $item->dc_estdo_trmte }}</td>
                <td>{{ $item->de_obse_trmte }}</td>
                <td>{{ $item->de_crgo_en ?? $item->de_area }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
