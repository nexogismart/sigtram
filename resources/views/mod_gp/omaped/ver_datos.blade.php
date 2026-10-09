<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OMAPED - Datos de emergencia</title>
    <style>
        :root { --red: #c0392b; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; font-family: "Segoe UI", Tahoma, sans-serif;
            background: #111 url('{{ $routes['fondo'] }}') center/cover no-repeat fixed;
            display: flex; align-items: center; justify-content: center; padding: 16px; color: #222;
        }
        .card {
            width: 100%; max-width: 480px; background: rgba(255,255,255,.96);
            border-radius: 10px; padding: 24px; box-shadow: 0 10px 36px rgba(0,0,0,.4);
        }
        .logo { display: block; max-width: 200px; margin: 0 auto 12px; }
        h1 { margin: 0 0 16px; font-size: 17px; text-align: center; }
        .foto-wrap { text-align: center; margin-bottom: 14px; }
        .foto {
            width: 140px; height: 140px; object-fit: cover; border-radius: 50%;
            border: 3px solid var(--red); background: #eee;
        }
        .nombre {
            text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 18px;
            letter-spacing: .5px;
        }
        .bloque { background: #f7f7f7; border-radius: 6px; padding: 12px 14px; margin-bottom: 12px; }
        .bloque h2 { margin: 0 0 8px; font-size: 13px; color: var(--red); text-transform: uppercase; }
        .bloque p { margin: 4px 0; font-size: 14px; }
        .wa {
            display: block; text-align: center; margin-top: 8px; padding: 10px;
            background: #25d366; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;
        }
        .wa--serenazgo { background: #128c7e; margin-top: 10px; }
        .nota { font-size: 11px; color: #777; text-align: center; margin-top: 14px; }
    </style>
</head>
<body>
@php
    $nombre = $datos['nombre_completo'] ?? '';
    $palabras = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $mascarado = implode(' ', array_map(fn ($p) => mb_substr($p, 0, 2) . '***', $palabras));
    $cuidador = $datos['cuidador'] ?? null;
    $telCuid = $cuidador['telefono'] ?? '';
    $digitos = preg_replace('/\D/', '', (string) $telCuid) ?? '';
    if (strlen($digitos) === 9 && str_starts_with($digitos, '9')) {
        $digitos = '51' . $digitos;
    }
    $waCuid = $digitos !== '' ? 'https://wa.me/' . $digitos : '';
    $waSerenazgo = 'https://wa.me/51979643921';
@endphp
    <div class="card">
        <img class="logo" src="{{ $routes['logo'] }}" alt="Municipalidad">
        <h1>Perfil de emergencia OMAPED</h1>
        <div class="foto-wrap">
            @if ($fotoUrl)
                <img class="foto" src="{{ $fotoUrl }}" alt="Foto">
            @else
                <div class="foto" style="display:inline-flex;align-items:center;justify-content:center;color:#999;">Sin foto</div>
            @endif
        </div>
        <div class="nombre">{{ $mascarado !== '' ? $mascarado : 'No disponible' }}</div>

        @if ($cuidador)
            <div class="bloque">
                <h2>Cuidador / Familiar</h2>
                <p><strong>Nombre:</strong> {{ $cuidador['nombre_completo'] ?? '-' }}</p>
                <p><strong>Parentesco:</strong> {{ $cuidador['parentesco'] ?? '-' }}</p>
                <p><strong>Teléfono:</strong> {{ $cuidador['telefono'] ?? '-' }}</p>
                @if ($waCuid)
                    <a class="wa" href="{{ $waCuid }}" target="_blank" rel="noopener">WhatsApp cuidador</a>
                @endif
            </div>
        @endif

        <a class="wa wa--serenazgo" href="{{ $waSerenazgo }}" target="_blank" rel="noopener">Contactar Serenazgo</a>
        <p class="nota">Este enlace es temporal. Si expiró, vuelva a escanear el código QR.</p>
    </div>
</body>
</html>
