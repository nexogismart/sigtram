<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte #{{ $reporte['cdgo_reporte'] }}</title>
    <style>
        @page { margin: 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; }
        h1, h2, h3 { margin: 0; padding: 0; text-align: center; font-size: 11px; }
        .title { font-weight: bold; margin-bottom: 12px; line-height: 1.35; }
        table.form { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.form td { vertical-align: top; padding: 4px; }
        .label { width: 38%; font-weight: bold; }
        .box { border: 1px solid #000; text-align: center; padding: 6px; }
        .box-left { border: 1px solid #000; text-align: left; padding: 6px; }
        table.data { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        table.data th, table.data td { border: 1px solid #000; padding: 5px; font-size: 9px; }
        table.data th { text-align: center; font-weight: bold; }
        .section { font-weight: bold; margin: 8px 0 4px; }
        .note { margin-top: 10px; }
        .anexo-foto { page-break-before: always; }
        .anexo-foto h2 { margin-bottom: 16px; font-size: 13px; }
        .grid { width: 100%; }
        .grid td { width: 50%; text-align: center; vertical-align: middle; padding: 10px; height: 280px; }
        .grid img { max-width: 240px; max-height: 240px; }
        .caption { margin-top: 6px; font-size: 9px; }
    </style>
</head>
<body>
    <div class="title">
        ANEXO 2<br>
        FORMATO DE REPORTE POSTERIOR DE PUBLICIDAD ESTATAL EN RAZÓN DE NECESIDAD O<br>
        UTILIDAD PÚBLICA EN PERIODO ELECTORAL
    </div>

    <p style="font-weight:bold; margin-left:20px;">TIPO DE PUBLICIDAD:</p>

    <table class="form">
        <tr>
            <td class="label">1. NOMBRE DE LA ENTIDAD</td>
            <td class="box">DISTRITO DE PUENTE PIEDRA</td>
        </tr>
    </table>
    <table class="form">
        <tr>
            <td class="label">2. NOMBRE DEL TITULAR DEL PLIEGO</td>
            <td class="box">RENNÁN ESPINOZA ROSALES</td>
        </tr>
    </table>
    <table class="form">
        <tr>
            <td class="label">3. DOMICILIO DE LA ENTIDAD</td>
            <td class="box">CALLE 9 DE JUNIO N°100</td>
        </tr>
    </table>
    <table class="form">
        <tr>
            <td class="label">4. CASILLA ELECTRÓNICA</td>
            <td class="box">07523215</td>
        </tr>
    </table>
    <table class="form">
        <tr>
            <td class="label">5. MEDIO EMPLEADO (carteles, afiches, banderolas, revistas, etc.)</td>
            <td class="box-left">{{ $reporte['mdio_empleado'] }}</td>
        </tr>
    </table>
    <table class="form">
        <tr>
            <td class="label">6. UBICACION DE LA PUBLICIDAD (de ser el caso)</td>
            <td class="box-left">{{ $reporte['ubicacion'] }}</td>
        </tr>
    </table>
    <table class="form">
        <tr>
            <td class="label">7. PERIODO DE DIFUCION DE PUBLICIDAD</td>
            <td class="box" style="width:28%;">{{ $fechaIniFmt }}</td>
            <td style="width:8%;"></td>
            <td class="box" style="width:28%;">{{ $textoFechaFin }}</td>
        </tr>
    </table>

    <div class="section">8. PUBLICIDAD</div>
    <div class="section" style="margin-left:10px;">8.1. CUADRO</div>

    @if(count($cuadros) > 0)
        <table class="data">
            <thead>
                <tr>
                    <th rowspan="2" style="width:38%;">EMPRESA QUE ELABORÓ LA PUBLICIDAD ESTATAL</th>
                    <th colspan="3">DATOS ESPECÍFICOS DE LA PUBLICIDAD</th>
                </tr>
                <tr>
                    <th style="width:20%;">UNIDAD DE MEDIDA</th>
                    <th style="width:14%;">CANTIDAD</th>
                    <th style="width:28%;">INVERSION (S/)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cuadros as $cuadro)
                    <tr>
                        <td>{{ $cuadro['empresa'] }}</td>
                        <td style="text-align:center;">{{ $cuadro['medida'] }}</td>
                        <td style="text-align:center;">{{ number_format($cuadro['cantidad'], 0, '.', ',') }}</td>
                        <td style="text-align:center;">{{ (float)$cuadro['inversion'] == 0 ? 'X' : number_format($cuadro['inversion'], 2, '.', ',') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="text-align:center; font-weight:bold;">SUBTOTAL</td>
                    <td style="text-align:center;">{{ number_format($canTotal, 0, '.', ',') }}</td>
                    <td style="text-align:center;">{{ $invTotal == 0 ? 'X' : number_format($invTotal, 2, '.', ',') }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <p><em>No hay cuadros asociados a este reporte.</em></p>
        <table class="data">
            <tr>
                <td style="width:58%; text-align:center; font-weight:bold;">SUBTOTAL</td>
                <td style="width:14%; text-align:center;">0</td>
                <td style="width:28%; text-align:center;">X</td>
            </tr>
        </table>
    @endif

    <table class="data">
        <tr>
            <th>Enlace o link donde se ubica el elemento publicitario:</th>
        </tr>
        <tr>
            <td style="text-align:center;">{{ $reporte['enlace'] }}</td>
        </tr>
    </table>

    <table class="data">
        <tr>
            <th>FUNDAMENTO DE IMPOSTERGABLE NECESIDAD O UTILIDAD PÚBLICA</th>
        </tr>
        <tr>
            <td style="text-align:justify;">{{ $reporte['fundamento'] }}</td>
        </tr>
    </table>

    <div class="note">
        <strong>NOTA:</strong> ______EN CASO DE PERSONA AUTORIZADA SE DEBE ADJUNTAR EL DOCUMENTO QUE LA ACREDITE COMO TAL.
    </div>

    @if(!empty($imagenes))
        @php
            $chunks = array_chunk($imagenes, 4);
            $imgNum = 0;
        @endphp
        @foreach($chunks as $imgs)
            <div class="anexo-foto">
                <h2>ANEXO FOTOGRÁFICO</h2>
                <table class="grid">
                    @foreach(array_chunk($imgs, 2) as $fila)
                        <tr>
                            @foreach($fila as $path)
                                @php $imgNum++; @endphp
                                <td>
                                    <img src="{{ $path }}" alt="Imagen {{ $imgNum }}">
                                    <div class="caption">Imagen {{ $imgNum }}</div>
                                </td>
                            @endforeach
                            @if(count($fila) === 1)
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </div>
        @endforeach
    @endif
</body>
</html>
