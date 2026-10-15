<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expediente {{ $expediente['expediente'] }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; margin: 0; padding: 20px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 5px; text-transform: uppercase; }
        .subtitle { text-align: center; font-size: 12px; color: #555; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; width: 30%; font-weight: bold; text-transform: uppercase; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Detalle de Expediente</h1>
    <div class="subtitle">{{ $expediente['expediente'] }}</div>

    <table>
        <tr>
            <th>Administrado / Solicitante</th>
            <td>{{ $expediente['solicitante'] }}</td>
        </tr>
        <tr>
            <th>Documento Solicitante</th>
            <td>{{ $expediente['identificacion'] }}</td>
        </tr>
        <tr>
            <th>Tipo y Nro de Documento</th>
            <td>{{ $expediente['documento'] }}</td>
        </tr>
        <tr>
            <th>Folios</th>
            <td>{{ $expediente['folios'] }}</td>
        </tr>
        <tr>
            <th>Fecha de Ingreso</th>
            <td>{{ $expediente['fecha'] }}</td>
        </tr>
        <tr>
            <th>&Aacute;rea Actual</th>
            <td>{{ $expediente['area_larga'] ?? $expediente['area'] }}</td>
        </tr>
        <tr>
            <th>Estado Actual</th>
            <td>{{ $expediente['estado_largo'] ?? $expediente['estado'] }}</td>
        </tr>
        <tr>
            <th>Usuario de Plataforma</th>
            <td>{{ $expediente['usuario'] }}</td>
        </tr>
        <tr>
            <th>Silencio Administrativo</th>
            <td>{{ $expediente['silencio'] ? 'Aplica' : 'No Aplica' }}</td>
        </tr>
        <tr>
            <th>Descripci&oacute;n / Observaciones</th>
            <td>{{ $expediente['descripcion'] }}</td>
        </tr>
    </table>

    <div style="text-align: right; margin-top: 50px; font-size: 10px; color: #777;">
        Impreso el {{ date('d/m/Y H:i') }}
    </div>
</body>
</html>
