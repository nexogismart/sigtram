<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: "Figtree", "Segoe UI", Tahoma, sans-serif;
            font-size: 13px;
            color: #334155;
            background: #f6f8fb;
            text-align: center;
        }
        .box {
            display: inline-block;
            background: #fff;
            border: 1px solid #d8dee6;
            border-radius: 8px;
            padding: 28px 32px;
            max-width: 420px;
        }
        h1 { font-size: 16px; margin: 0 0 10px; color: #0f172a; }
        p { margin: 0 0 18px; color: #64748b; }
        .Button {
            display: inline-flex;
            border: 1px solid #0f172a;
            background: #0f172a;
            color: #fff;
            font: inherit;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="box">
    <h1>{{ $titulo }}</h1>
    <p>{{ $mensaje }}</p>
    <button type="button" class="Button" onclick="window.close()">Cerrar</button>
</div>
</body>
</html>
