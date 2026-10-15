<div id="wrapper_{{ $uid }}" class="v2-detail-wrapper" data-uid="{{ $uid }}" data-per-page="15">
<table class="v2-detail-table" id="{{ $uid }}">
    <thead>
        <tr>
            <th>Nº Tram.</th>
            <th>Asunto</th>
            <th>Tipo Trámite</th>
            <th>F. Creación</th>
            <th>Área Origen</th>
            <th>Áreas Involucradas</th>
            <th>Área Actual</th>
            <th>F. Última Mov.</th>
            <th style="text-align:center">Días</th>
            <th style="text-align:center">Doc</th>
            <th style="text-align:center">Ver</th>
        </tr>
    </thead>
    <tbody>
    @forelse($tramites as $t)
        @php
            $asunto = trim($t->de_obser ?? '');
            $ruta_areas = $t->ruta_areas ?? [];
        @endphp
        <tr>
            <td>
                <a href="/Presentacion/segi_tramite.php?nu_expe={{ $t->cdgo_dtos_ntrnos }}"
                   data-action="popup" data-width="800" data-height="400" target="historico"
                   class="v2-link-num">
                   {{ $t->nu_tram_format }}
                </a>
            </td>
            <td title="{{ $asunto }}" style="max-width:200px;">
                @if($asunto === '')
                    <span style="color:#94a3b8; font-style:italic;">Sin asunto</span>
                @else
                    <span style="font-size:0.78rem; color:#334155; line-height:1.4;">{{ mb_strlen($asunto) > 60 ? mb_substr($asunto, 0, 60) . '…' : $asunto }}</span>
                @endif
            </td>
            <td title="{{ $t->de_tpo_trmte }}" style="font-size:0.75rem; color:#6366f1; font-weight:700;">
                {{ $t->dc_tpo_trmte }}
            </td>
            <td>{{ $t->fechaini }}</td>
            <td title="{{ $t->de_area_inco }}" style="font-size:0.75rem; font-weight:700; color:#475569;">
                {{ $t->dc_area_inco }}
            </td>
            <td>
                <div style="display:flex; flex-wrap:wrap; gap:4px; align-items:center; max-width:200px;">
                    @if(empty($ruta_areas))
                        <span style="color:#94a3b8; font-style:italic;">-</span>
                    @else
                        @foreach($ruta_areas as $i => $ar)
                            <span style="background:#e0e7ff; color:#3730a3; padding:2px 6px; border-radius:4px; font-size:0.7rem; font-weight:700; white-space:nowrap;">{{ $ar }}</span>
                            @if($i < count($ruta_areas) - 1)
                                <svg width="12" height="12" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            @endif
                        @endforeach
                    @endif
                </div>
            </td>
            <td style="font-weight:600; color:#6366f1;">{{ $t->area_actual }}</td>
            <td>{{ $t->fe_ultima }}</td>
            <td align="center">
                <span style="display:inline-block; padding:0.25rem 0.5rem; border-radius:0.25rem; font-weight:700; background:{{ $t->dias_area > 5 ? '#fee2e2; color:#ef4444;' : '#f1f5f9; color:#475569;' }}">
                    {{ $t->dias_area }}
                </span>
            </td>
            <td align="center">
                @if(!empty($t->de_urls_adjnto))
                    <a href="{{ route('mod_tram_inter.descargar', ['id' => $t->cdgo_dtos_ntrnos]) }}" target="_blank" class="btn-icon" title="Ver Adjunto">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </a>
                @endif
            </td>
            <td align="center">
                <a href="/Presentacion/busca_vauche.php?id_tram={{ $t->cdgo_dtos_ntrnos }}"
                   data-action="popup" data-width="470" data-height="550"
                   target="_blank" class="btn-icon" title="Ver Detalle">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </a>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="11" style="text-align:center; padding:2rem; color:#94a3b8;">No hay registros en este rango de fechas.</td>
        </tr>
    @endforelse
    </tbody>
</table>
<div class="v2-pagination" id="pg_info_{{ $uid }}"></div>
</div>
