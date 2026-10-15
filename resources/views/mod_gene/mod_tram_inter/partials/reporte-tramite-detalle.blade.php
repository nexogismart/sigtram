{{-- Detalle AJAX — equivalente a ajax_reporte_area.php --}}
<div class="overflow-x-auto" style="overflow-x:auto">
    <table class="det-tbl">
        <thead>
            <tr>
                <th class="c" style="width:2rem">#</th>
                <th>Trámite</th>
                <th>N° Tram</th>
                <th class="c">Área</th>
                <th class="c">F. Inicial</th>
                <th class="c">F. Última</th>
                <th>Observaciones</th>
                <th class="c" style="width:1.75rem"></th>
                <th class="c" style="width:1.75rem"></th>
            </tr>
        </thead>
        <tbody>
        @forelse($tramites as $i => $t)
            <tr>
                <td class="c muted">{{ $i + 1 }}</td>
                <td class="tipo" title="{{ $t->de_tpo_trmte }}">{{ $t->dc_tpo_trmte }}</td>
                <td class="tram">{{ $t->nu_tram_format }}</td>
                <td class="c muted" title="{{ $t->de_area_inco }}">{{ $t->dc_area_inco }}</td>
                <td class="c muted" style="white-space:nowrap">{{ $t->fechaini }}</td>
                <td class="c muted" style="white-space:nowrap">{{ $t->fe_ultima }}</td>
                <td title="{{ $t->de_obser }}">{{ $t->de_obser_corto }}</td>
                <td class="c">
                    @if (!empty($t->de_urls_adjnto))
                        <a href="{{ route('mod_tram_inter.descargar', ['id' => $t->cdgo_dtos_ntrnos]) }}"
                           target="_blank" title="Adjunto"><i class="fas fa-paperclip"></i></a>
                    @endif
                </td>
                <td class="c">
                    <a href="/Presentacion/busca_vauche.php?id_tram={{ $t->cdgo_dtos_ntrnos }}"
                       data-action="popup" data-width="470" data-height="310"
                       target="_blank" title="Ver"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="det-empty">No hay registros en este rango de fechas.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
