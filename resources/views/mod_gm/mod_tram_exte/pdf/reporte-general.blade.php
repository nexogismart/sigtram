<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Expedientes</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 0; padding: 0; }
        h1 { font-size: 14px; text-align: center; margin-bottom: 20px; text-transform: uppercase; }
        .header-info { margin-bottom: 15px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; font-size: 9px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@include('components.sigtram.print-brand', ['sigtramPdf' => true])
    <h1>Reporte de Expedientes Externos</h1>
    
    <div class="header-info">
        <strong>Tipo de Reporte:</strong> {{ strtoupper($tipo) }} 
        @if($tipo === 'resumen')
            - {{ strtoupper($subtipo) }}
            <br><strong>Periodo:</strong> {{ \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y') }}
        @endif
        <br><strong>Fecha de Impresi&oacute;n:</strong> {{ date('d/m/Y H:i') }}
    </div>

    @if($tipo === 'buscador')
        <table>
            <thead>
                <tr>
                    <th class="text-center w-12">#</th>
                    <th>Nro Exp.</th>
                    <th>Fecha</th>
                    <th>&Aacute;rea</th>
                    <th>Estado</th>
                    <th>Solicitante</th>
                    <th>Descripci&oacute;n</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filas as $index => $item)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item['expediente'] }}</strong><br>
                            <span style="font-size: 8px;">{{ $item['documento'] }}</span>
                        </td>
                        <td>{{ $item['fecha'] }}</td>
                        <td>{{ $item['area'] }}</td>
                        <td>{{ $item['estado'] }}</td>
                        <td>{{ $item['solicitante'] }}</td>
                        <td>{{ $item['descripcion'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <table>
            <thead>
                <tr>
                    <th class="text-center w-12">#</th>
                    <th>{{ $subtipo === 'cargo' ? 'NOMBRE DEL CARGO' : 'NOMBRE DE GERENCIA' }}</th>
                    @if($subtipo === 'area')<th class="text-center">Digitalizar</th>@endif
                    <th class="text-center">Por Recibir</th>
                    <th class="text-center">Recibidos</th>
                    <th class="text-center">Derivados</th>
                    <th class="text-center">Finalizados</th>
                    <th class="text-center">Total Doc.</th>
                    <th class="text-center">% Trabajo</th>
                    @if($subtipo === 'area')<th class="text-center">F.T.</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($filas as $index => $row)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $row->nombre }}</strong>
                            @if($subtipo === 'cargo')<br><span style="font-size: 8px;">{{ $row->usuarios }}</span>@endif
                        </td>
                        @if($subtipo === 'area')<td class="text-center">{{ $row->digitalizar }}</td>@endif
                        <td class="text-center">{{ $row->por_recibir }}</td>
                        <td class="text-center">{{ $row->recibidos }}</td>
                        <td class="text-center">{{ $row->derivados }}</td>
                        <td class="text-center">{{ $row->finalizados }}</td>
                        <td class="text-center"><strong>{{ $row->total_doc }}</strong></td>
                        <td class="text-center">{{ $row->porcentaje }}%</td>
                        @if($subtipo === 'area')<td class="text-center">{{ $row->criticos }}</td>@endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: #f9f9f9; font-weight: bold;">
                    <td></td>
                    <td class="text-right">TOTALES:</td>
                    @if($subtipo === 'area')<td class="text-center">{{ $totales['digitalizar'] }}</td>@endif
                    <td class="text-center">{{ $totales['por_recibir'] }}</td>
                    <td class="text-center">{{ $totales['recibidos'] }}</td>
                    <td class="text-center">{{ $totales['derivados'] }}</td>
                    <td class="text-center">{{ $totales['finalizados'] }}</td>
                    <td class="text-center">{{ $totales['total_doc'] }}</td>
                    <td class="text-center">{{ $totales['porcentaje'] }}%</td>
                    @if($subtipo === 'area')<td class="text-center">{{ $totales['criticos'] }}</td>@endif
                </tr>
            </tfoot>
        </table>
    @endif
</body>
</html>
