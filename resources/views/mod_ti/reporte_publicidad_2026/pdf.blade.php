<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ANEXO 2 - Reporte de Publicidad Estatal 2026</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }
        .title { text-align: center; font-weight: bold; margin-bottom: 10px; line-height: 1.35; }
        .title .main { font-size: 12px; }
        .title .sub { font-size: 10px; }
        table.form { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.form td { vertical-align: middle; padding: 3px; }
        .label { width: 38%; font-weight: bold; font-size: 8px; }
        .box { border: 1px solid #000; text-align: center; padding: 4px; font-size: 9px; }
        .box-left { border: 1px solid #000; text-align: left; padding: 4px; font-size: 9px; }
        .fecha-box { border: 1px solid #000; display: inline-block; width: 28px; text-align: center; padding: 3px 0; }
        .fecha-box-wide { border: 1px solid #000; display: inline-block; min-width: 40px; text-align: center; padding: 3px 6px; }
        table.data { width: 100%; border-collapse: collapse; margin: 6px 0 8px; }
        table.data th, table.data td { border: 1px solid #000; padding: 4px; font-size: 8px; }
        table.data th { text-align: center; font-weight: bold; }
        .section { font-weight: bold; font-size: 8px; margin: 6px 0 3px; }
        .note { margin-top: 8px; font-size: 8px; }
        .anexo-foto { page-break-before: always; }
        .anexo-foto h2 { text-align: center; font-size: 12px; margin-bottom: 14px; }
        .grid { width: 100%; }
        .grid td { width: 50%; text-align: center; vertical-align: middle; padding: 8px; height: 260px; }
        .grid img { max-width: 230px; max-height: 230px; }
        .caption { margin-top: 4px; font-size: 8px; }
        .footer-code { position: fixed; bottom: 8px; right: 20px; font-size: 8px; font-style: italic; }
    </style>
</head>
<body>
    <div class="footer-code">2473747-1</div>

    <div class="title">
        <div class="main">ANEXO 2</div>
        <div class="sub">FORMATO DE REPORTE POSTERIOR DE PUBLICIDAD ESTATAL EN RAZÓN DE IMPOSTERGABLE<br>
        NECESIDAD O UTILIDAD PÚBLICA EN PERIODO ELECTORAL</div>
    </div>

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
            <td class="label">5. DIRECCIÓN O UBICACIÓN DE LA PUBLICIDAD*</td>
            <td class="box-left">{{ mb_strtoupper($reporte['ubicacion'] ?? '', 'UTF-8') }}</td>
        </tr>
    </table>

    <table class="form">
        <tr>
            <td class="label">6. PERIODO DE LA DIFUSIÓN</td>
            <td>
                DEL
                <span class="fecha-box">{{ $periodo['dia_ini'] }}</span> /
                <span class="fecha-box">{{ $periodo['mes_ini'] }}</span> /
                <span class="fecha-box">{{ $periodo['anio_ini'] }}</span>
                &nbsp;&nbsp;
                @if($periodo['hasta_actualidad'])
                    <span class="fecha-box-wide">HASTA LA ACTUALIDAD.</span>
                @else
                    <span class="fecha-box">{{ $periodo['dia_fin'] }}</span> /
                    <span class="fecha-box">{{ $periodo['mes_fin'] }}</span> /
                    <span class="fecha-box">{{ $periodo['anio_fin'] }}</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="section">7. INFORMACIÓN DE LA PUBLICIDAD</div>

    @if(count($detalles) > 0)
        <table class="data">
            <thead>
                <tr>
                    <th rowspan="2" style="width:38%;">EMPRESA QUE ELABORÓ LA PUBLICIDAD ESTATAL</th>
                    <th colspan="2">DATOS ESPECÍFICOS DE LA PUBLICIDAD</th>
                </tr>
                <tr>
                    <th style="width:30%;">TIPO DE MEDIO EMPLEADO **</th>
                    <th style="width:32%;">CANTIDAD</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detalles as $detalle)
                    <tr>
                        <td>{{ mb_strtoupper($detalle['empresa_elaboro'], 'UTF-8') }}</td>
                        <td>{{ mb_strtoupper($detalle['tipo_medio_empleado'], 'UTF-8') }}</td>
                        <td style="text-align:center;">{{ number_format($detalle['cantidad'], 2, '.', ',') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="text-align:right; font-weight:bold;">TOTAL</td>
                    <td style="text-align:right; font-weight:bold;">{{ number_format($cantidadTotal, 2, '.', ',') }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <p><em>No hay información de publicidad asociada a este reporte.</em></p>
    @endif

    <table class="data">
        <tr><th style="text-align:left;">Enlace o link donde se ubica el elemento publicitario:</th></tr>
        <tr><td>{{ $reporte['enlace_link'] }}</td></tr>
    </table>

    <table class="data">
        <tr><th style="text-align:left;">FUNDAMENTO DE IMPOSTERGABLE NECESIDAD O UTILIDAD PÚBLICA</th></tr>
        <tr><td style="text-align:justify;">{{ mb_strtoupper($reporte['fundamento'] ?? '', 'UTF-8') }}</td></tr>
    </table>

    <div class="note">
        <strong>NOTA:</strong> EN CASO DE PERSONA FACULTADA, SE DEBE ADJUNTAR EL DOCUMENTO QUE LA ACREDITE<br>
        (*) En caso que el medio empleado tenga ubicación física<br>
        (**) El tipo de medio empleado puede ser físico (carteles, afiches, banderolas, revistas, aviso en prensa escrita, etc.) o virtual (pieza gráfica, pieza audiovisual, video corto o short, post, reel, transmisiones, etc.)
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
