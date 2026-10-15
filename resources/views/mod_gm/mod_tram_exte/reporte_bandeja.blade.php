<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Bandeja</title>
    <style>
        body { font-family: sans-serif; font-size: 9px; color: #333; margin: 15px; }
        .header { text-align: center; margin-bottom: 12px; }
        h2 { text-transform: uppercase; margin: 0 0 4px 0; font-size: 14px; }
        .meta { font-size: 9px; color: #666; margin: 0; }
        .alerta-limite { margin: 8px 0; padding: 4px 8px; background-color: #fef3c7; color: #92400e; font-size: 8px; border: 1px solid #fde68a; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background-color: #f1f5f9; font-weight: bold; font-size: 8.5px; text-transform: uppercase; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .whitespace-nowrap { white-space: nowrap; }
    </style>

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gm.mod_tram_exte.reporte_bandeja', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body>
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gm.mod_tram_exte.reporte_bandeja', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}

    <div class="header">
        <h2>Reporte de Bandeja Trámite Externo: {{ strtoupper($bandeja) }}</h2>
        <p class="meta">Fecha de Impresión: {{ $fechaImpresion }} | Total registros: {{ count($data) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 20px;" class="text-center">#</th>
                <th style="width: 25px;" class="text-center">O</th>
                <th class="whitespace-nowrap">Nº Exp.</th>
                <th class="whitespace-nowrap">F. Ingreso</th>
                <th class="whitespace-nowrap">Doc. Identidad</th>
                <th>Nombre / Razón Social</th>
                <th>Asunto / Detalle</th>
                <th>Área Ingreso</th>
                <th class="whitespace-nowrap">Estado</th>
                <th class="whitespace-nowrap">F. Último</th>
                <th>Última Observación</th>
                <th>Cargo Destino</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $index => $row)
                @php
                    $item = (object) $row;
                    $indicadorOrigen = $item->indicador_origen ?? ($item->origen_flag ?? 'L');
                    $numeroExpediente = $item->tramite_formateado ?? $item->nu_expe_todo ?? '—';
                    $fechaIngreso = $item->fe_ingr_trmte ?? '—';
                    $nuDocu = $item->nu_docu ?? '—';
                    $solicitante = $item->no_crto ?? 'Ciudadano';
                    $asunto = $item->asunto_expediente ?? $item->de_obse_trmte ?? '—';
                    $area = $item->de_area ?? $item->dc_area ?? '—';
                    $estado = $item->dc_estdo_trmte ?? '—';
                    $fechaUltimo = $item->fe_rcbdo_trmte ?? '—';
                    $observacion = $item->de_obse_trmte ?? '—';
                    $cargoDestino = $item->de_crgo_en ?? $item->de_crgo ?? '—';
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center font-bold">{{ $indicadorOrigen }}</td>
                    <td class="whitespace-nowrap font-bold">{{ $numeroExpediente }}</td>
                    <td class="whitespace-nowrap">{{ $fechaIngreso }}</td>
                    <td class="whitespace-nowrap">{{ $nuDocu }}</td>
                    <td>{{ $solicitante }}</td>
                    <td>{{ $asunto }}</td>
                    <td>{{ $area }}</td>
                    <td class="whitespace-nowrap">{{ $estado }}</td>
                    <td class="whitespace-nowrap">{{ $fechaUltimo }}</td>
                    <td>{{ $observacion }}</td>
                    <td>{{ $cargoDestino }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center" style="padding: 15px; color: #64748b;">
                        No se encontraron expedientes en la bandeja seleccionada.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
