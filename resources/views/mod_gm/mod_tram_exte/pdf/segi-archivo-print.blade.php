<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bandeja de Archivo</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #4b5563; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-size: 10px; text-transform: uppercase; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
@include('components.sigtram.print-brand')
    <button class="no-print" onclick="window.print()">Imprimir</button>
    <h1>Bandeja de Archivo - {{ ucfirst($bandeja) }}</h1>
    <div class="meta">Generado: {{ $generado }} | Total: {{ $rows->count() }}</div>

    <table>
        <thead>
            <tr>
                <th>Expediente</th>
                <th>Solicitante</th>
                <th>Documento</th>
                <th>Fecha movimiento</th>
                <th>Estado</th>
                <th>Area</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['expediente'] }}</td>
                    <td>{{ $row['solicitante'] }}</td>
                    <td>{{ $row['documento'] }}</td>
                    <td>{{ $row['fecha_movimiento'] }}</td>
                    <td>{{ $row['estado'] }}</td>
                    <td>{{ $row['area'] }}</td>
                    <td>{{ $row['observaciones'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Sin resultados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
