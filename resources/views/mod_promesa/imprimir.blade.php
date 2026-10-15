<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impresión de Compromisos - Audiencias Públicas</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: #1f2937;
            padding: 24px;
        }
        .print-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #b71223;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .print-header h1 {
            font-size: 20px;
            margin: 0 0 4px;
            color: #b71223;
        }
        .print-header p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
        }
        .print-actions {
            margin-bottom: 16px;
            text-align: right;
        }
        .btn-print {
            background: #b71223;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-print:hover {
            background: #9f0f1d;
        }
        .lugar-info {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .lugar-info strong {
            color: #334155;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #fef2f2;
            color: #7f1d1d;
        }
        tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .estado-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        .estado-pill--ok {
            background: #dcfce7;
            color: #166534;
        }
        .estado-pill--pendiente {
            background: #fef3c7;
            color: #92400e;
        }
        .empty {
            text-align: center;
            color: #64748b;
            padding: 30px;
        }
        @media print {
            .print-actions { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
@include('components.sigtram.print-brand')
    <div class="print-actions">
        <button type="button" class="btn-print" onclick="window.print()">Imprimir</button>
    </div>

    <div class="print-header">
        <div>
            <h1>Relación de Compromisos - Audiencias Públicas</h1>
            <p>Generado el {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    @if($lugar)
        <div class="lugar-info">
            <strong>Lugar:</strong> {{ $lugar['zona_nombre'] ?? '' }} &nbsp;|&nbsp;
            <strong>Fecha de reunión:</strong> {{ $lugar['fecha_rnn'] ?? '' }} {{ $lugar['hora_rnn'] ?? '' }}
        </div>
    @endif

    @if(count($items) === 0)
        <p class="empty">No se seleccionaron compromisos para imprimir.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 18%;">Dirigente</th>
                    <th style="width: 26%;">Pedido</th>
                    <th style="width: 26%;">Compromiso</th>
                    <th style="width: 14%;">Área</th>
                    <th style="width: 12%;">Fec. Compromiso / Cumplimiento</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item['dirigente'] ?? '' }}</td>
                        <td>{{ $item['pedido'] ?? '' }}</td>
                        <td>{{ $item['compromiso'] ?? '' }}</td>
                        <td>{{ $item['de_area'] ?? '' }}</td>
                        <td>
                            {{ $item['fec_compromiso'] ?? '' }}
                            @if(!empty($item['fec_cumplimiento']))
                                <br><span class="estado-pill estado-pill--ok">Cumplido: {{ $item['fec_cumplimiento'] }}</span>
                            @else
                                <br><span class="estado-pill estado-pill--pendiente">Pendiente</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
