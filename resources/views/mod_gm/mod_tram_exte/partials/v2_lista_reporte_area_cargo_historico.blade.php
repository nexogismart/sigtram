<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico {{ $expediente }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Figtree, system-ui, sans-serif; background: #fff; color: #334155; padding: 0.75rem; }
        h1 { font-size: 0.78rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; color: #1e293b; margin-bottom: 0.75rem; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; min-width: 860px; }
        th { text-align: left; font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; padding: 0.5rem 0.5rem; position: sticky; top: 0; }
        td { padding: 0.4rem 0.5rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .empty { text-align: center; color: #94a3b8; padding: 2rem; }
        a { color: #334155; }
    </style>
</head>
<body>
    <h1>Histórico del Expediente N° {{ $expediente }}</h1>
    <table>
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
