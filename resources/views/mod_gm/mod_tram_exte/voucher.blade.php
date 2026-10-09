<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Trámite - {{ $expediente->nu_expe_todo }}</title>
    
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
            align-items: center;
            min-height: 100vh;
        }

        .voucher-card {
            background-color: #ffffff;
            width: 380px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            box-sizing: border-box;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px dashed #e2e8f0;
            padding-bottom: 15px;
        }

        .company-name {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin: 0 0 4px 0;
        }

        .title {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }

        .expediente-section {
            text-align: center;
            margin-bottom: 20px;
            background-color: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
        }

        .expediente-label {
            font-size: 10px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            display: block;
        }

        .expediente-number {
            font-size: 20px;
            font-weight: 800;
            color: #2563eb;
            letter-spacing: 0.5px;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-grid td {
            padding: 5px 0;
            vertical-align: top;
        }

        .label {
            font-weight: 700;
            color: #475569;
            width: 90px;
            font-size: 11px;
            text-transform: uppercase;
        }

        .value {
            color: #0f172a;
            font-size: 11px;
        }

        .signatures {
            margin-top: 30px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }

        .signature-line {
            width: 140px;
            text-align: center;
        }

        .line {
            border-top: 1px solid #94a3b8;
            margin-bottom: 6px;
        }

        .signature-title {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .footer {
            border-top: 2px dashed #e2e8f0;
            padding-top: 15px;
            font-size: 10px;
            color: #64748b;
            text-align: center;
            line-height: 1.4;
        }

        .footer-highlight {
            font-weight: 700;
            color: #0f172a;
            font-size: 11px;
            text-transform: uppercase;
            margin-top: 4px;
            display: block;
        }

        .actions {
            margin-top: 20px;
            text-align: center;
        }

        .btn {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: #1d4ed8;
        }

        .btn-close {
            background-color: #64748b;
            margin-left: 8px;
        }

        .btn-close:hover {
            background-color: #475569;
        }

        @media print {
            body {
                background-color: #ffffff;
                padding: 0;
                display: block;
            }
            .voucher-card {
                width: 100%;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="voucher-card">
        <div class="header">
            <h1 class="company-name">Municipalidad de Puente Piedra</h1>
            <p class="title">Trámite Documentario</p>
        </div>

        <div class="expediente-section">
            <span class="expediente-label">
                @if($expediente->cdgo_tpo_trmte === 17)
                    Trámite Simple N°
                @elseif($expediente->cdgo_tpo_trmte === 40)
                    Expediente Observado N°
                @else
                    Expediente N°
                @endif
            </span>
            <div class="expediente-number">{{ $expediente->nu_expe_todo }}</div>
        </div>

        <table class="info-grid">
            <tr>
                <td class="label">Fecha:</td>
                <td class="value">{{ \Carbon\Carbon::parse($expediente->fe_ingr_trmte)->format('d/m/Y') }}</td>
                <td class="label" style="width: 50px;">Hora:</td>
                <td class="value">{{ \Carbon\Carbon::parse($expediente->fe_ingr_trmte)->format('H:i') }}</td>
            </tr>
            <tr>
                <td class="label">Folios:</td>
                <td class="value" colspan="3">{{ $expediente->nu_foli_trmte }}</td>
            </tr>
            <tr>
                <td class="label">N° Iden:</td>
                <td class="value" colspan="3">
                    @if($expediente->usrio?->in_tipo_pers === '1')
                        {{ $expediente->usrio->nu_ruc }} (RUC)
                    @else
                        {{ $expediente->usrio->nu_docu ?? 'Sin Documento' }}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Solicitante:</td>
                <td class="value" colspan="3" style="font-weight: 600; text-transform: uppercase;">
                    {{ $expediente->usrio?->no_crto }}
                </td>
            </tr>
            <tr>
                <td class="label">Domicilio:</td>
                <td class="value" colspan="3">{{ $expediente->usrio?->de_dire ?: 'No especificado' }}</td>
            </tr>
            @if($expediente->usrio?->nu_tele)
            <tr>
                <td class="label">Teléfono:</td>
                <td class="value" colspan="3">{{ $expediente->usrio->nu_tele }}</td>
            </tr>
            @endif
            @if($expediente->usrio?->de_mail)
            <tr>
                <td class="label">E-mail:</td>
                <td class="value" colspan="3">{{ strtolower($expediente->usrio->de_mail) }}</td>
            </tr>
            @endif
            @if($expediente->documentoTupa)
            <tr>
                <td class="label">Trámite:</td>
                <td class="value" colspan="3" style="text-align: justify; font-weight: 500;">
                    {{ $expediente->documentoTupa->de_docmnto_trmte }}
                </td>
            </tr>
            @endif
            @if($expediente->de_obse_trmte)
            <tr>
                <td class="label">Detalles:</td>
                <td class="value" colspan="3" style="text-align: justify; font-style: italic;">
                    {{ $expediente->de_obse_trmte }}
                </td>
            </tr>
            @endif
        </table>

        <div class="signatures">
            <div class="signature-line">
                <div class="line"></div>
                <div class="signature-title">Recepcionista</div>
            </div>
            <div class="signature-line">
                <div class="line"></div>
                <div class="signature-title">Solicitante</div>
            </div>
        </div>

        <div class="footer">
            <span>Su trámite será gestionado en:</span>
            <span class="footer-highlight">{{ $expediente->area?->de_area ?? 'Mesa de Partes' }}</span>
            @if($expediente->area?->nu_tele)
                <span>Consultas al Teléfono: <b>{{ $expediente->area->nu_tele }}</b></span><br>
            @endif
            <div style="margin-top: 8px; font-size: 9px; color: #94a3b8;">
                Atendido por: {{ $attendant ? $attendant->nombreMostrar() : 'Operador' }}
            </div>
        </div>

        <div class="actions no-print">
            <button onclick="window.print()" class="btn">Imprimir</button>
            <button onclick="window.close()" class="btn btn-close">Cerrar</button>
        </div>
    </div>

    <script class="no-print">
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
