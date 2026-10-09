<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OMAPED - Verificar identidad</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root { --red: #c0392b; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; font-family: "Segoe UI", Tahoma, sans-serif;
            background: #1a1a1a url('{{ $routes['fondo'] }}') center/cover no-repeat fixed;
            display: flex; align-items: center; justify-content: center; padding: 16px;
        }
        .card {
            width: 100%; max-width: 420px; background: rgba(255,255,255,.95);
            border-radius: 8px; padding: 28px 24px; box-shadow: 0 8px 32px rgba(0,0,0,.35);
        }
        .logo { display: block; max-width: 220px; margin: 0 auto 16px; }
        h1 { margin: 0 0 8px; font-size: 18px; text-align: center; color: #222; }
        p { margin: 0 0 16px; font-size: 13px; color: #555; text-align: center; }
        label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; }
        input {
            width: 100%; height: 40px; padding: 0 12px; border: 1px solid #ccc; border-radius: 4px;
            font-size: 16px; letter-spacing: 2px; text-align: center;
        }
        button {
            width: 100%; margin-top: 14px; height: 42px; border: 0; border-radius: 4px;
            background: var(--red); color: #fff; font-weight: 600; cursor: pointer; font-size: 14px;
        }
        button:hover { background: #a93226; }
        .error { color: #c0392b; font-size: 13px; text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <img class="logo" src="{{ $routes['logo'] }}" alt="Municipalidad">
        <h1>OMAPED – Acceso de emergencia</h1>
        <p>Ingrese el DNI del usuario para ver sus datos de contacto.</p>
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ $routes['entrada'] }}" autocomplete="off">
            @csrf
            <input type="hidden" name="t" value="{{ $token }}">
            <label for="dni">Número de DNI</label>
            <input id="dni" name="dni" type="text" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" required value="{{ old('dni') }}">
            <button type="submit">Continuar</button>
        </form>
    </div>
    <script src="{{ asset('mod_gp/omaped/js/entrada_dni.js') }}?v=1"></script>
</body>
</html>
