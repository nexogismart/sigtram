<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Trámite Interno — {{ $tituloBandeja }}</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-interno/reporte-bandeja.css', 'resources/js/app.js'])
</head>
<body class="reporte-bandeja-body">

<div class="container">
    {{-- Barra de herramientas (solo en pantalla) --}}
    <div class="toolbar no-print">
        <h1><i class="fas fa-file-invoice mr-2"></i> Reporte Oficial de Bandeja</h1>
        <div class="toolbar-buttons">
            <button type="button" data-action="print" class="btn btn-print">
                <i class="fas fa-print"></i> Imprimir Reporte
            </button>
            <button type="button" data-action="export-csv" data-table="tabla-reporte" data-filename="reporte_bandeja_{{ $bandeja }}_{{ date('Y-m-d') }}.csv" class="btn btn-csv">
                <i class="fas fa-file-excel"></i> Exportar a Excel
            </button>
            <button type="button" data-action="close" class="btn btn-close">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
    </div>

    {{-- Encabezado Institucional --}}
    <div class="header-report">
        <div class="logo-title">
            <div class="logo-box">
                <i class="fas fa-landmark"></i>
            </div>
            <div class="titles">
                <h2>MUNICIPALIDAD DISTRITAL DE PUENTE PIEDRA</h2>
                <h3>SISTEMA INTEGRADO DE GESTIÓN DE TRÁMITE DOCUMENTARIO — SIGTRAM</h3>
            </div>
        </div>
        <div class="meta-box">
            <div><strong>Área:</strong> {{ $areaNombre }}</div>
            <div><strong>Cargo:</strong> {{ $cargoNombre }}</div>
            <div><strong>Usuario:</strong> {{ $usuario }}</div>
            <div><strong>Fecha Emisión:</strong> {{ $fechaImpresion }}</div>
        </div>
    </div>

    {{-- Banner de la Bandeja --}}
    <div class="banner-bandeja">
        <span><i class="fas fa-folder-open mr-1"></i> {{ $tituloBandeja }}</span>
        <span class="badge-count">{{ count($items) }} Documento(s)</span>
    </div>

    {{-- Tabla de Datos --}}
    <table class="report-table" id="tabla-reporte">
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">Nº</th>
                <th style="width: 14%;">Tipo Trámite</th>
                <th style="width: 10%;" class="text-center">Nº Trámite</th>
                <th style="width: 18%;">Área Remitente / Envio</th>
                <th style="width: 11%;" class="text-center">Fecha Ingreso</th>
                <th style="width: 33%;">Observaciones / Detalle</th>
                <th style="width: 10%;" class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $idx => $row)
            @php
                $item = (object) $row;
                $numTram = str_pad($item->nu_tram_todo ?? $item->cdgo_dtos_ntrnos ?? '', 8, '0', STR_PAD_LEFT);
                $tipoTram = $item->de_tpo_trmte ?? ($item->dc_tpo_trmte ?? 'DOCUMENTO');
                $areaTexto = $item->de_area ?? ($item->dc_area ?? ($item->de_area_ref ?? '-'));
                $fechaTexto = !empty($item->fe_ingrso) ? date('d/m/Y H:i', strtotime($item->fe_ingrso)) : (!empty($item->fe_rcbdo) ? date('d/m/Y H:i', strtotime($item->fe_rcbdo)) : '-');
                $obsTexto = !empty($item->de_obser) ? $item->de_obser : (!empty($item->de_obser_ntrnos) ? $item->de_obser_ntrnos : 'SIN OBSERVACIONES');
                $estadoTexto = $item->dc_estdo_trmte ?? 'INGRESADO';
            @endphp
            <tr>
                <td class="text-center font-bold">{{ $idx + 1 }}</td>
                <td>{{ $tipoTram }}</td>
                <td class="text-center font-mono font-bold">{{ $numTram }}</td>
                <td>{{ $areaTexto }}</td>
                <td class="text-center whitespace-nowrap">{{ $fechaTexto }}</td>
                <td>{{ $obsTexto }}</td>
                <td class="text-center font-bold">{{ $estadoTexto }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="padding: 30px; color: #94a3b8;">
                    No se encontraron registros para los filtros seleccionados.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Pie de Página del Reporte --}}
    <div class="footer-report">
        <span>SIGTRAM v3 • Módulo de Trámite Interno (mod_tram_inter)</span>
        <span>Página 1 de 1</span>
    </div>
</div>

</body>
</html>
