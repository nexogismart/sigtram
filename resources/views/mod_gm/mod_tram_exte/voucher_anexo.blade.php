<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vaucher Anexo - {{ $numeroExpediente }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            color: #1e293b;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }
        .voucher-card {
            background: #fff;
            width: 380px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,.05);
            box-sizing: border-box;
        }
        .header { text-align: center; margin-bottom: 16px; border-bottom: 2px dashed #e2e8f0; padding-bottom: 12px; }
        .company-name { font-size: 13px; font-weight: 800; text-transform: uppercase; margin: 0 0 4px; }
        .title { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; margin: 0; }
        .expediente-section {
            text-align: center;
            margin-bottom: 16px;
            background: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
        }
        .expediente-label { font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 4px; }
        .expediente-number { font-size: 18px; font-weight: 800; color: #2563eb; }
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .info-grid td { padding: 5px 0; vertical-align: top; }
        .label { font-weight: 700; color: #475569; width: 90px; font-size: 11px; text-transform: uppercase; }
        .value { color: #0f172a; }
        .obs { margin-top: 8px; padding: 10px; background: #f8fafc; border-radius: 6px; }
        .obs-label { font-weight: 700; font-size: 11px; color: #475569; text-transform: uppercase; margin-bottom: 4px; }
        .actions { text-align: center; margin-top: 16px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #0f172a;
            background: #0f172a;
            color: #fff;
            font: inherit;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn.secondary { background: #fff; color: #0f172a; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .voucher-card { box-shadow: none; border: none; width: 100%; }
        }
    </style>
</head>
<body>
<div class="voucher-card">
@include('components.sigtram.print-brand')
    <div class="header">
        <p class="company-name">{{ $nomComp }}</p>
        <p class="title">Sistema Trámite Documentario</p>
    </div>

    <div class="expediente-section">
        <span class="expediente-label">Anexo al Nº Exp:</span>
        <div class="expediente-number">{{ $numeroExpediente }}</div>
    </div>

    <table class="info-grid">
        <tr>
            <td class="label">Fecha</td>
            <td class="value">{{ $fecha }}</td>
        </tr>
        <tr>
            <td class="label">Hora</td>
            <td class="value">{{ $hora }}</td>
        </tr>
        <tr>
            <td class="label">Folio</td>
            <td class="value">{{ $row->nu_foli_anex ?? '0' }}</td>
        </tr>
        @if ($row->de_docmnto_trmte)
            <tr>
                <td class="label">Trámite</td>
                <td class="value">{{ $row->de_docmnto_trmte }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Nº Iden.</td>
            <td class="value">{{ $row->nu_docu ?: 'No Presento' }}</td>
        </tr>
        <tr>
            <td class="label">Solicitante</td>
            <td class="value">{{ $row->no_crto }}</td>
        </tr>
        <tr>
            <td class="label">Domicilio</td>
            <td class="value">{{ $row->de_dire ?: 'No Presento' }}</td>
        </tr>
        <tr>
            <td class="label">Teléfono</td>
            <td class="value">{{ $row->nu_tele ?: 'No Presento' }}</td>
        </tr>
        @if ($row->de_area)
            <tr>
                <td class="label">Área</td>
                <td class="value">{{ $row->de_area }}</td>
            </tr>
        @endif
    </table>

    <div class="obs">
        <div class="obs-label">Observaciones</div>
        <div>{{ $row->de_obse ?: ($row->de_obse_trmte ?? '') }}</div>
    </div>

    <div class="actions">
        <a class="btn secondary" href="{{ $voucherPrincipalUrl }}">Vaucher Principal</a>
        <button type="button" class="btn" onclick="window.print()">Imprimir</button>
        <button type="button" class="btn secondary" onclick="window.close()">Cerrar</button>
    </div>
</div>
</body>
</html>
