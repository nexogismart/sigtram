{{-- Detalle AJAX — equivalente a ajax_reporte_area.php --}}
<style>
    .det-tbl { width: 100%; border-collapse: collapse; font-size: .78rem; }
    .det-tbl thead th {
        padding: .45rem .55rem; background: #f8fafc; color: #0f172a;
        font-size: .62rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
        border-bottom: 1px solid #e2e8f0; text-align: left; white-space: nowrap;
    }
    .det-tbl thead th.c { text-align: center; }
    .det-tbl tbody td {
        padding: .45rem .55rem; border-bottom: 1px solid #e6ecf2; vertical-align: middle;
        color: #334155;
    }
    .det-tbl tbody tr:hover { background: #f8fafc; }
    .det-tbl .c { text-align: center; }
    .det-tbl .tipo { font-weight: 800; color: #0f172a; }
    .det-tbl .tram { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #0f172a; }
    .det-tbl .muted { color: #64748b; font-weight: 600; }
    .det-tbl a { color: #334155; text-decoration: none; }
    .det-tbl a:hover { color: #0f172a; }
    .det-empty { padding: 1.25rem; text-align: center; color: #94a3b8; font-weight: 700; }
</style>
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
                        <a href="/mod_gene/mod_tram_inter/descargar?{{ str_contains((string) $t->de_urls_adjnto, '/') ? 'path' : 'nom_archivo' }}={{ urlencode($t->de_urls_adjnto) }}"
                           target="_blank" title="Adjunto"><i class="fas fa-paperclip"></i></a>
                    @endif
                </td>
                <td class="c">
                    <a href="/Presentacion/busca_vauche.php?id_tram={{ $t->cdgo_dtos_ntrnos }}"
                       onclick="window.open(this.href, this.target, 'scrollbars=auto,width=470,height=310'); return false;"
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
