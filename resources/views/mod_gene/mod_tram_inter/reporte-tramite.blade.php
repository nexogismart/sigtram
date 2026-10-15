<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sigtram | Reporte General de Trámites Internos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/tramite-interno/reporte-tramite.css', 'resources/js/app.js'])

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gene.mod_tram_inter.reporte-tramite', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body class="reporte-tramite-page">
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gene.mod_tram_inter.reporte-tramite', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}


@php
    $frontendConfig = [
        'csrfToken' => csrf_token(),
        'urlDetalle' => route('mod_tram_inter.reporte_tramite.detalle'),
        'fechaIni' => $desdeStr,
        'fechaFin' => $hastaStr,
    ];
@endphp

<div class="wrap" id="reporte-tramite-app" x-data="reporteTramiteApp" data-config="{{ json_encode($frontendConfig) }}">

    <div class="top">
        <div>
            <h1>Reporte <span>General</span> de Trámites Internos</h1>
            <p>Seguimiento por gerencia · Período seleccionado</p>
        </div>
    </div>

    <form method="POST" action="{{ url()->current() }}" class="filters">
        @csrf
        <div class="field">
            <label for="txt_fe_inicio">Desde</label>
            <input type="text" name="txt_fe_inicio" id="txt_fe_inicio" value="{{ $desdeStr }}"
                   placeholder="dd/mm/aaaa" pattern="\d{2}/\d{2}/\d{4}" required>
        </div>
        <div class="field">
            <label for="txt_fe_fin">Hasta</label>
            <input type="text" name="txt_fe_fin" id="txt_fe_fin" value="{{ $hastaStr }}"
                   placeholder="dd/mm/aaaa" pattern="\d{2}/\d{2}/\d{4}" required>
        </div>
        <button type="submit" name="Buscar" value="1" class="btn btn--primary">
            <i class="fas fa-search"></i> Buscar
        </button>
    </form>

    <div class="panel">
        <div class="table-wrap">
            <table class="rep">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="left">Nombre de Gerencia</th>
                        <th class="center">Por recibir</th>
                        <th class="center">Recibidos</th>
                        <th class="center">Derivado UU.OO</th>
                        <th class="center">Finalizados</th>
                        <th class="center">Total Doc.</th>
                        <th class="right">% Trabajo</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reportes as $i => $row)
                    @php
                        $idx = $i + 1;
                        $pct = (float) $row->porcentaje;
                        $pctClass = $pct >= 80 ? 'ok' : ($pct >= 50 ? 'warn' : 'bad');
                    @endphp
                    <tr class="data">
                        <td class="num-cell">{{ $idx }}</td>
                        <td class="area">{{ $row->de_area }}</td>
                        <td class="center">
                            <span class="cell-click {{ $row->por_recibir == 0 ? 'is-zero' : '' }}"
                                  @click="toggleDetalle('{{ $row->cdgo_area }}', '1', '{{ $idx }}', $event)">{{ $row->por_recibir }}</span>
                        </td>
                        <td class="center">
                            <span class="cell-click {{ $row->recibidos == 0 ? 'is-zero' : '' }}"
                                  @click="toggleDetalle('{{ $row->cdgo_area }}', '2', '{{ $idx }}', $event)">{{ $row->recibidos }}</span>
                        </td>
                        <td class="center">
                            <span class="cell-click {{ $row->derivados == 0 ? 'is-zero' : '' }}"
                                  @click="toggleDetalle('{{ $row->cdgo_area }}', '3', '{{ $idx }}', $event)">{{ $row->derivados }}</span>
                        </td>
                        <td class="center">
                            <span class="cell-click {{ $row->finalizados == 0 ? 'is-zero' : '' }}"
                                  @click="toggleDetalle('{{ $row->cdgo_area }}', '4', '{{ $idx }}', $event)">{{ $row->finalizados }}</span>
                        </td>
                        <td class="center total">{{ $row->total_doc }}</td>
                        <td class="right"><span class="pct {{ $pctClass }}">{{ $row->porcentaje }}</span></td>
                    </tr>
                    <tr class="detail-row">
                        <td colspan="8">
                            <div id="conte_resultados{{ $idx }}" class="detalle-box"></div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="empty">No hay áreas activas para mostrar.</td>
                    </tr>
                @endforelse
                </tbody>
                @if(count($reportes) > 0)
                <tfoot>
                    <tr>
                        <td></td>
                        <td class="label">Totales :</td>
                        <td style="text-align:center">{{ number_format($totales['sum_entregado'], 0) }}</td>
                        <td style="text-align:center">{{ number_format($totales['sum_recibido'], 0) }}</td>
                        <td style="text-align:center">{{ number_format($totales['sum_salida'], 0) }}</td>
                        <td style="text-align:center">{{ number_format($totales['sum_finalizado'], 0) }}</td>
                        <td style="text-align:center">{{ number_format($totales['sum_total_doc'], 0) }}</td>
                        <td style="text-align:right">{{ $totales['porcentaje'] }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
</body>
</html>
