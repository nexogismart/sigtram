<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico {{ $expediente }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="v2-historico-body">
    <h1 class="v2-historico-title">Histórico del Expediente N° {{ $expediente }}</h1>
    <table class="v2-historico-table">
        <thead>
            <tr>
                <th style="width:10%">F. Entrada</th>
                <th style="width:6%; text-align:center">T.E</th>
                <th style="width:22%">Área</th>
                <th style="width:12%">Estado</th>
                <th>Observaciones</th>
                <th style="width:8%; text-align:center">U</th>
                <th style="width:4%"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($movimientos as $i => $mov)
                <tr style="{{ $mov['color'] ? 'background-color:' . $mov['color'] : '' }}">
                    <td style="white-space:nowrap">{{ $mov['fecha'] }}</td>
                    <td style="text-align:center; text-transform:lowercase">{{ $mov['ta'] }}</td>
                    <td>{{ $mov['area'] }}</td>
                    <td>{{ $mov['estado'] }}</td>
                    <td>{{ $mov['observaciones'] }}</td>
                    <td style="text-align:center" title="{{ $mov['usuario'] }}">{{ $mov['usuario'] !== '' ? mb_substr($mov['usuario'], 0, 1) : '' }}</td>
                    <td style="text-align:center">
                        @if($mov['adjunto'] !== '')
                            <a href="{{ route('mod_gm.segi_expedi.descargar', $mov['id']) }}" target="_blank" title="Adjunto">📎</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">No hay movimientos para este expediente.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
