<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Trámite - {{ $expediente->nu_expe_todo }}</title>
    @vite(['resources/js/app.js'])
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Verdana, Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000000;
            background-color: #f1f5f9;
            margin: 0;
            padding: 16px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        .voucher-card {
            background-color: #ffffff;
            width: 370px;
            max-width: 100%;
            border: 1px solid #cbd5e1;
            padding: 14px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .logo-container {
            margin-bottom: 6px;
            text-align: center;
        }

        .logo-img {
            max-height: 65px;
            width: auto;
            object-fit: contain;
            margin: 0 auto;
            display: block;
        }

        .company-name {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
            margin: 4px 0 2px 0;
            line-height: 1.3;
        }

        .title {
            font-size: 11px;
            color: #265682;
            text-transform: uppercase;
            margin: 0 0 10px 0;
        }

        .expediente-section {
            text-align: center;
            margin-bottom: 12px;
            padding: 6px 0;
            border-top: 1px solid #000000;
            border-bottom: 1px solid #000000;
        }

        .expediente-label {
            font-size: 13px;
            color: #265682;
            margin-bottom: 3px;
            display: inline-block;
        }

        .expediente-number {
            font-size: 19px;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            display: block;
            margin-top: 3px;
        }

        .compact-row {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .compact-row td {
            padding: 3px 2px;
            font-size: 11px;
        }

        .item-label {
            color: #265682;
            font-weight: bold;
            font-size: 11px;
            margin-top: 5px;
            margin-bottom: 1px;
        }

        .item-value {
            color: #000000;
            font-size: 11px;
            text-align: justify;
            text-transform: uppercase;
            line-height: 1.35;
            word-wrap: break-word;
            margin-bottom: 6px;
            padding-left: 4px;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
            margin-bottom: 14px;
        }

        .signatures-table td {
            text-align: center;
            vertical-align: top;
            padding: 2px 4px;
            font-size: 11px;
        }

        .signature-line-text {
            color: #000000;
            font-size: 11px;
        }

        .signature-subtext {
            font-size: 9px;
            color: #334155;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .area-info {
            text-align: center;
            font-size: 11px;
            color: #265682;
            margin-top: 10px;
            line-height: 1.4;
        }

        .area-name {
            font-size: 13px;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            display: block;
            margin: 2px 0;
        }

        .phone-highlight {
            font-size: 13px;
            font-weight: bold;
            color: #000000;
        }

        .atendido-por {
            margin-top: 6px;
            font-size: 11px;
            color: #265682;
        }

        .atendido-nombre {
            color: #000000;
            font-weight: bold;
            text-transform: uppercase;
        }

        .qr-container {
            margin-top: 12px;
            text-align: center;
            padding-top: 6px;
        }

        .qr-image {
            width: 135px;
            height: 135px;
            margin: 0 auto;
            display: block;
            background: #ffffff;
            padding: 4px;
        }

        .qr-caption {
            font-size: 9px;
            color: #000000;
            margin-top: 4px;
            margin-bottom: 0;
        }

        .actions {
            margin-top: 16px;
            text-align: center;
        }

        .btn {
            background-color: #265682;
            color: #ffffff;
            border: 1px solid #1e40af;
            padding: 6px 14px;
            font-size: 11px;
            font-family: inherit;
            font-weight: bold;
            cursor: pointer;
            margin: 0 4px;
            border-radius: 4px;
        }

        .btn-close {
            background-color: #64748b;
            border-color: #475569;
        }

        /* Estilos estrictos para impresión térmica de 80 mm (72-76 mm ancho útil) */
        @media print {
            @page {
                margin: 2mm 3mm;
                size: auto;
            }

            body {
                background-color: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                display: block !important;
                color: #000000 !important;
                width: 100% !important;
            }

            .voucher-card {
                width: 100% !important;
                max-width: 76mm !important;
                border: none !important;
                box-shadow: none !important;
                padding: 2mm 0 !important;
                margin: 0 auto !important;
                border-radius: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .expediente-section {
                border-top: 1px solid #000000 !important;
                border-bottom: 1px solid #000000 !important;
            }
        }
    </style>
</head>
<body>

    <div class="voucher-card">
        <div class="header">
            <div class="logo-container">
                <img src="{{ asset('images/logo_entidad.png') }}" alt="Escudo Institucional" class="logo-img">
            </div>
            <div class="company-name">{{ $compania->de_cmpnia ?? 'Municipalidad Distrital de Puente Piedra' }}</div>
            <div class="title">Sistema de Gestión de Trámite Documentario</div>
        </div>

        @php
            $codigoTipoVoucher = strtoupper(trim((string) ($dc_tpo_trmte ?? $expediente->dc_tpo_trmte ?? '')));
            $descTipoVoucher = trim((string) ($de_tpo_trmte ?? $expediente->de_tpo_trmte ?? ''));
        @endphp

        <div class="expediente-section">
            <span class="expediente-label">
                @if($codigoTipoVoucher === 'S')
                    Documento Simple N°:
                @elseif($codigoTipoVoucher === 'O')
                    Expediente Observado N°:
                @elseif($codigoTipoVoucher === 'E')
                    Expediente:
                @elseif($descTipoVoucher !== '')
                    {{ ucwords(mb_strtolower($descTipoVoucher)) }} N°:
                @else
                    Expediente:
                @endif
            </span>
            <span class="expediente-number">{{ $expediente->nu_expe_todo }}</span>
        </div>

        <table class="compact-row">
            <tr>
                <td style="color:#265682; font-weight:bold; width:45px;">Fecha:</td>
                <td style="font-weight:bold;">{{ \Carbon\Carbon::parse($expediente->fe_ingr_trmte)->format('d/m/Y') }}</td>
                <td style="color:#265682; font-weight:bold; width:40px; text-align:right;">Hora:</td>
                <td>{{ \Carbon\Carbon::parse($expediente->fe_ingr_trmte)->format('H:i') }}</td>
                <td style="color:#265682; font-weight:bold; width:40px; text-align:right;">Folio:</td>
                <td style="font-weight:bold; text-align:right;">{{ $expediente->nu_foli_trmte }}</td>
            </tr>
        </table>

        @php
            $numDocTram = trim((string) ($expediente->nu_docu_trmte ?? ''));
        @endphp
        @if($numDocTram !== '')
            <div class="item-label">Nº Documento:</div>
            <div class="item-value">{{ $numDocTram }}</div>
        @endif

        @php
            $docIdentidad = '';
            if ($expediente->usrio?->in_tipo_pers === '1') {
                $docIdentidad = trim((string) ($expediente->usrio->nu_ruc ?? ''));
            } else {
                $docIdentidad = trim((string) ($expediente->usrio->nu_docu ?? ''));
            }
        @endphp
        <div class="item-label">Nº Iden.:</div>
        <div class="item-value">{{ $docIdentidad !== '' ? $docIdentidad : 'No Presentó' }}</div>

        @php
            $nombreSolicitante = trim((string) ($expediente->usrio?->no_crto ?? ''));
        @endphp
        <div class="item-label">Solicitante:</div>
        <div class="item-value" style="font-weight:bold;">{{ $nombreSolicitante !== '' ? $nombreSolicitante : 'No Presentó' }}</div>

        @php
            $domicilio = trim((string) ($expediente->usrio?->de_dire ?? ''));
        @endphp
        <div class="item-label">Domicilio:</div>
        <div class="item-value">{{ $domicilio !== '' ? $domicilio : 'No Presentó' }}</div>

        @php
            $telefono = trim((string) ($expediente->usrio?->nu_tele ?? ''));
        @endphp
        @if($telefono !== '')
            <div class="item-label">Teléfono:</div>
            <div class="item-value">{{ $telefono }}</div>
        @endif

        @php
            $correo = trim((string) ($expediente->usrio?->de_mail ?? ''));
        @endphp
        @if($correo !== '')
            <div class="item-label">E-mail:</div>
            <div class="item-value" style="text-transform:lowercase;">{{ strtolower($correo) }}</div>
        @endif

        @if($expediente->documentoTupa && !empty($expediente->documentoTupa->de_docmnto_trmte))
            <div class="item-label">Trámite:</div>
            <div class="item-value">{{ $expediente->documentoTupa->de_docmnto_trmte }}</div>
        @endif

        @php
            $detalles = trim((string) ($expediente->de_obse_trmte ?? ''));
        @endphp
        <div class="item-label">Detalles:</div>
        <div class="item-value">{{ $detalles !== '' ? $detalles : 'No Presentó' }}</div>

        <table class="signatures-table">
            <tr>
                <td style="width:50%;">_________________</td>
                <td style="width:50%;">_________________</td>
            </tr>
            <tr>
                <td class="signature-line-text">Recepcionista</td>
                <td class="signature-line-text">Solicitante</td>
            </tr>
            <tr>
                <td class="signature-subtext">
                    {{ is_object($attendant) && method_exists($attendant, 'nombreMostrar') ? $attendant->nombreMostrar() : ($attendant->de_lgin ?? $attendant->name ?? '') }}
                </td>
                <td class="signature-subtext">&nbsp;</td>
            </tr>
        </table>

        <div class="area-info">
            Su trámite será gestionado en:<br>
            <span class="area-name">{{ ($expediente->area ?? null)?->de_area ?? 'Mesa de Partes' }}</span>

            @if(!empty(($expediente->area ?? null)?->nu_tele))
                Consultas al Teléfono: <span class="phone-highlight">{{ $expediente->area->nu_tele }}</span><br>
            @endif

            <div class="atendido-por">
                Que tenga un buen día, Ud. fue atendido por:<br>
                <span class="atendido-nombre">{{ is_object($attendant) && method_exists($attendant, 'nombreMostrar') ? $attendant->nombreMostrar() : ($attendant->de_lgin ?? $attendant->name ?? 'Operador') }}</span>
            </div>
        </div>

        @if(!empty($qrCode))
        <div class="qr-container">
            <img src="{{ $qrCode }}" alt="Código QR de Seguimiento" class="qr-image" />
            <p class="qr-caption">Escanear código QR para ver el seguimiento</p>
        </div>
        @endif

        <div class="actions no-print">
            <button type="button" data-action="print" class="btn">Imprimir</button>
            <button type="button" data-action="close" class="btn btn-close">Cerrar</button>
        </div>
    </div>
</body>
</html>
