@php
    /**
     * Tabla compacta del detalle de requerimientos (respuesta AJAX).
     * Equivale a la salida de action=get_detail_html del reporte legacy.
     */
    $conHito = $ml !== null;
    $desde = $total > 0 ? $offset + 1 : 0;
    $hasta = $offset + count($rows);
    $mlArg = $conHito ? ",'" . $ml . "'" : '';
@endphp
<div class="req-detail-wrap">
    @if ($conHito)
        <div class="rd-hito-note">
            <div class="rd-hito-note__title">Reporte por áreas · <span>{{ $mlLabel }}</span></div>
            <div class="rd-hito-note__text">
                Incluye requerimientos que <strong>alcanzaron este hito o ya están en una etapa posterior</strong>
                (huella del avance; coincide con la barra de la píldora). «<strong>Quién / cuándo</strong>» refiere al
                registro vinculado a <em>este</em> paso del circuito. «Estado req.» es el código actual en gestión de proveedores.
            </div>
        </div>
    @endif

    <div class="req-detail-filter-host"></div>

    <div class="rd-scroll">
        <table class="req-detail-table">
            <thead>
                <tr>
                    <th class="rd-th-sel" title="Seleccionar filas visibles">
                        <input type="checkbox" class="req-det-select-all" aria-label="Seleccionar todas las filas visibles">
                    </th>
                    <th>N° Exp.</th>
                    <th>Asunto</th>
                    <th>Estado req.</th>
                    @if ($conHito)
                        <th>Quién / cuándo (hito)</th>
                    @endif
                    <th>Locador</th>
                    <th>Área solicitante</th>
                    <th>Ruta (áreas)</th>
                    <th>Área actual</th>
                    <th>Cargo destino</th>
                    <th>Asignado</th>
                    <th class="rd-center">Días</th>
                    <th>F. registro</th>
                    <th class="rd-center">Hist.</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    @php
                        $dto = (int) $r->dto_int;
                        $info = $rutas[$dto] ?? null;
                        $areaActual = $info['area_actual'] ?? '—';
                        $cargoDestino = $info['cargo_actual'] ?? '—';
                        $rutaLabels = $info['ruta_labels'] ?? [];

                        $areaSol = trim((string) $r->dc_area_req) !== ''
                            ? (string) $r->dc_area_req
                            : (string) ($r->de_area ?? '');

                        if ($dto > 0) {
                            $hrefHist = $urlSeguimiento . '?nu_expe=' . rawurlencode((string) $dto);
                            $titleLnk = 'Histórico del trámite interno';
                        } else {
                            $hrefHist = $urlRequerimiento . '?intCdgoPgoPrvdres=' . rawurlencode((string) $r->cdgo_pgo_prvdres);
                            $titleLnk = 'Ver requerimiento';
                        }

                        $estado = $r->estado_display;
                        $asunto = (string) ($r->de_pgo_prvdres ?? '');
                        $locador = trim((string) ($r->no_crto_titular ?? ''));
                        $asignado = trim((string) ($r->no_crto_asignado ?? ''));
                        $dias = $r->dias_area;
                    @endphp
                    <tr class="req-det-data-row" data-reg-day="{{ $r->fe_registro_ymd }}" data-pgo-id="{{ (int) $r->cdgo_pgo_prvdres }}">
                        <td class="rd-center">
                            <input type="checkbox" class="req-det-row-cb" value="{{ (int) $r->cdgo_pgo_prvdres }}" aria-label="Marcar fila">
                        </td>
                        <td class="rd-nowrap">
                            <a class="rd-exp" href="{{ $hrefHist }}" title="{{ $titleLnk }}"
                               onclick="window.open(this.href,'sigtram_historico','scrollbars=yes,width=980,height=640,resizable=yes');return false;">{{ $r->nu_expe }}</a>
                        </td>
                        <td class="rd-asunto" title="{{ $asunto }}">
                            {{ mb_substr($asunto, 0, 85) }}{{ mb_strlen($asunto) > 85 ? '…' : '' }}
                        </td>
                        <td class="rd-estado" title="{{ $estado['title'] }}">
                            <span class="rd-badge rd-badge--{{ $estado['badge_variant'] }}">{{ $estado['label'] }}</span>
                            <span class="rd-cod">{{ $estado['cod_line'] }}</span>
                        </td>
                        @if ($conHito)
                            <td class="rd-oper">
                                @php $oper = trim((string) ($r->rep_area_ml_oper_txt ?? '')); @endphp
                                {{ $oper !== '' ? $oper : '—' }}
                            </td>
                        @endif
                        <td class="rd-locador" title="{{ mb_substr($locador, 0, 400) }}">
                            @if ($locador !== '')
                                <span class="rd-strong">{{ mb_substr($locador, 0, 85) }}{{ mb_strlen($locador) > 85 ? '…' : '' }}</span>
                            @else
                                <span class="rd-empty">Sin asignar</span>
                            @endif
                        </td>
                        <td class="rd-area-sol" title="{{ $r->de_area ?? '' }}">{{ $areaSol }}</td>
                        <td>
                            @if ($dto <= 0)
                                @if (trim((string) $r->dc_area_req) !== '')
                                    <span class="rd-muted">{{ $r->dc_area_req }}</span>
                                @else
                                    <span class="rd-empty">Sin trámite interno aún</span>
                                @endif
                            @elseif ($rutaLabels === [])
                                <span class="rd-empty">—</span>
                            @else
                                <div class="rd-ruta">
                                    @foreach ($rutaLabels as $i => $ar)
                                        <span class="rd-chip">{{ $ar }}</span>@if ($i < count($rutaLabels) - 1)<span class="rd-arrow">›</span>@endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="rd-area-act">{{ $areaActual }}</td>
                        <td class="rd-cargo">{{ $cargoDestino }}</td>
                        <td class="rd-strong" title="Personal del cargo destino (último movimiento interno)">{{ $asignado !== '' ? $asignado : 'Sin asignar' }}</td>
                        <td class="rd-center" title="Días transcurridos desde la fecha del último movimiento en el histórico interno (F. recepción)">
                            @if ($dias === null)
                                <span class="rd-empty">—</span>
                            @else
                                <span class="rd-dias {{ $dias > 5 ? 'rd-dias--alto' : '' }}">{{ $dias }}</span>
                            @endif
                        </td>
                        <td class="rd-nowrap rd-strong">{{ $r->fe_registro }}</td>
                        <td class="rd-center">
                            <a class="rd-eye" href="{{ $hrefHist }}" title="{{ $titleLnk }}"
                               onclick="window.open(this.href,'sigtram_historico','scrollbars=yes,width=980,height=640,resizable=yes');return false;"
                               aria-label="{{ $titleLnk }}">
                                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $conHito ? 14 : 13 }}" class="rd-vacio">No hay registros en este rango / filtro.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="req-det-pager">
        <span class="rd-pager-info">{{ $desde }}–{{ $hasta }} de {{ $total }}</span>
        <div class="rd-pager-btns">
            @if ($offset > 0)
                <button type="button" class="rd-btn rd-btn--ghost"
                        onclick="goDetailPage({{ $areaId }},{{ $tipo }},{{ max(0, $offset - $limit) }},'{{ $pane }}'{!! $mlArg !!})">← Anterior</button>
            @endif
            @if ($total > $offset + $limit)
                <button type="button" class="rd-btn rd-btn--primary"
                        onclick="goDetailPage({{ $areaId }},{{ $tipo }},{{ $offset + $limit }},'{{ $pane }}'{!! $mlArg !!})">Siguiente →</button>
            @endif
        </div>
    </div>

    @unless ($conHito)
        <div class="rd-export-row">
            <button type="button" class="rd-btn rd-btn--export" onclick="exportDetalle({{ $areaId }},{{ $tipo }})"
                    title="Descarga un CSV con todos los registros de esta vista (no solo la página visible)">
                Exportar CSV (listado completo)
            </button>
        </div>
    @endunless
</div>
