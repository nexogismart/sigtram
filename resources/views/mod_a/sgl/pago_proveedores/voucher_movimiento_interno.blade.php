<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle del documento interno</title>
    <style>
        body {
            margin: 0;
            padding: 1rem 1.1rem 1.25rem;
            font-family: Verdana, Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #fff url("{{ asset('images/logomdppcorto.jpg') }}") center / contain no-repeat;
            background-blend-mode: soft-light;
            min-height: 100vh;
        }
        .tit { text-align: right; font-weight: 800; letter-spacing: .02em; margin-bottom: 1rem; }
        .num {
            text-align: center;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 13px;
            margin: .5rem 0 1rem;
        }
        .row { display: grid; grid-template-columns: 110px 1fr 70px 80px; gap: .35rem .5rem; align-items: start; margin-bottom: .55rem; }
        .lbl { text-align: right; font-weight: 700; color: #334155; }
        .val { font-weight: 600; color: #0f172a; }
        .actions { text-align: center; margin-top: 1.25rem; }
        .btn {
            border: 1px solid #0f172a;
            background: #0f172a;
            color: #fff;
            font: inherit;
            font-weight: 700;
            padding: .4rem 1rem;
            border-radius: 6px;
            cursor: pointer;
        }
        .msg { text-align: center; color: #b91c1c; font-weight: 700; padding: 2rem; }
        a { color: #0f766e; font-weight: 700; }
    </style>
</head>
<body>
@if (! $ok)
    <p class="msg">{{ $error ?: 'No se pudo cargar el detalle.' }}</p>
@else
    <div class="tit">DETALLE DEL DOCUMENTO INTERNO&nbsp;&nbsp;&nbsp;</div>
    <div class="num">
        <span style="font-weight:700">Nº Tram:</span>
        {{ $data['numero'] }}
    </div>
    <div class="row">
        <div class="lbl">Fecha:&nbsp;</div>
        <div class="val">{{ $data['fecha'] }}</div>
        <div class="lbl">Hora:&nbsp;</div>
        <div class="val">{{ $data['hora'] !== '' ? $data['hora'] : '—' }}</div>
    </div>
    <div class="row" style="grid-template-columns: 110px 1fr;">
        <div class="lbl">Adjuntado:&nbsp;</div>
        <div class="val">
            @if (!empty($data['adjunto']) && !empty($data['id']))
                <a href="{{ route('mod_tram_inter.descargar', ['movimiento_id' => $data['id']]) }}" target="_blank">Click Ver Adjunto</a>
            @else
                No Tiene Adjunto
            @endif
        </div>
    </div>
    <div class="row" style="grid-template-columns: 110px 1fr;">
        <div class="lbl">Detalles:&nbsp;</div>
        <div class="val">{{ $data['detalles'] !== '' ? $data['detalles'] : '—' }}</div>
    </div>
    <div class="actions">
        <button type="button" class="btn" onclick="window.close()">Cerrar</button>
    </div>
@endif
</body>
</html>
