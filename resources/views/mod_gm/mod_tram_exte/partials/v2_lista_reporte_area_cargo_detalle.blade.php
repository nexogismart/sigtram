<style>
    .v2-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 0.5rem 0;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .v2-pagination-info {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 600;
    }
    .v2-pagination-btns {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
    }
    .v2-pg-btn {
        padding: 0.3rem 0.65rem;
        border-radius: 0.5rem;
        border: 1px solid #e2e8f0;
        background: white;
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s;
        line-height: 1.4;
    }
    .v2-pg-btn:hover { background: #f1f5f9; border-color: #334155; color: #334155; }
    .v2-pg-btn.active { background: #1e293b; color: white; border-color: #1e293b; }
    .v2-pg-btn:disabled { opacity: 0.4; cursor: default; }
    .v2-detail-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.8rem;
        background: white;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .v2-detail-table th {
        background: #f1f5f9;
        padding: 0.75rem;
        text-align: left;
        font-weight: 600;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
    }
    .v2-detail-table td {
        padding: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }
    .v2-detail-table tr:last-child td {
        border-bottom: none;
    }
    .v2-detail-table tr:hover {
        background: #f8fafc;
    }
    .btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 0.25rem;
        background: #f1f5f9;
        color: #475569;
        text-decoration: none;
        border: 0;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-icon:hover {
        background: #e2e8f0;
        color: #1e293b;
    }
</style>

<div id="wrapper_{{ $uid }}">
<table class="v2-detail-table" id="{{ $uid }}">
    <thead>
        <tr>
            <th>Nº Exp.</th>
            <th>F. Creación</th>
            <th>Administrado</th>
            <th>Áreas Involucradas</th>
            <th>Área Actual</th>
            <th>F. Derivación</th>
            <th style="text-align:center">Días</th>
            <th style="text-align:center">Doc</th>
            <th style="text-align:center">Ver</th>
        </tr>
    </thead>
    <tbody>
    @forelse($tramites as $t)
        @php
            $nombre = trim((string) ($t->no_crto ?? ''));
            $ruta_areas = $t->ruta_areas ?? [];
        @endphp
        <tr>
            <td>
                <a href="#"
                   onclick="if (typeof v2AbrirHistorico === 'function') { v2AbrirHistorico({{ (int) $t->cdgo_dto_trmte }}, @json($t->nu_expe_format)); } return false;"
                   style="font-size:0.9rem; font-weight:800; color:#334155; text-decoration:none; border-bottom:2px dashed #334155; transition:all 0.2s; white-space:nowrap;"
                   onmouseover="this.style.borderBottomStyle='solid';"
                   onmouseout="this.style.borderBottomStyle='dashed';">
                   {{ $t->nu_expe_format }}
                </a>
            </td>
            <td>{{ $t->fechaini }}</td>
            <td title="{{ $nombre }}">{{ mb_strlen($nombre) > 20 ? mb_substr($nombre, 0, 20) . '…' : $nombre }}</td>
            <td>
                <div style="display:flex; flex-wrap:wrap; gap:4px; align-items:center; max-width:200px;">
                    @if(empty($ruta_areas))
                        <span style="color:#94a3b8; font-style:italic;">-</span>
                    @else
                        @foreach($ruta_areas as $i => $ar)
                            <span style="background:#e2e8f0; color:#1e293b; padding:2px 6px; border-radius:4px; font-size:0.7rem; font-weight:700; white-space:nowrap;">{{ $ar }}</span>
                            @if($i < count($ruta_areas) - 1)
                                <svg width="12" height="12" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            @endif
                        @endforeach
                    @endif
                </div>
            </td>
            <td style="font-weight:600; color:#334155;">{{ $t->area_actual }}</td>
            <td>{{ $t->fe_derivacion }}</td>
            <td align="center">
                <span style="display:inline-block; padding:0.25rem 0.5rem; border-radius:0.25rem; font-weight:700; background:{{ $t->dias_area > 5 ? '#fee2e2; color:#ef4444;' : '#f1f5f9; color:#475569;' }}">
                    {{ $t->dias_area }}
                </span>
            </td>
            <td align="center">
                @if(!empty($t->nom_archivo))
                    <a href="{{ route('tramites.adjuntar.archivo', ['nombre' => $t->nom_archivo]) }}" target="_blank" class="btn-icon" title="Ver Adjunto">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </a>
                @endif
            </td>
            <td align="center">
                <button type="button" class="btn-icon" title="Ver Detalle"
                        onclick="if (typeof v2AbrirOjo === 'function') { v2AbrirOjo({{ (int) $t->cdgo_dto_trmte }}); }">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="9" style="text-align:center; padding:2rem; color:#94a3b8;">No hay registros encontrados en este rango de fechas.</td>
        </tr>
    @endforelse
    </tbody>
</table>
<div class="v2-pagination" id="pg_info_{{ $uid }}"></div>
</div>

<script>
(function(){
    var uid     = @json($uid);
    var perPage = 15;
    var curPage = 1;

    var table = document.getElementById(uid);
    if (!table) return;
    var rows  = Array.from(table.querySelectorAll('tbody tr'));
    var total = rows.length;
    var totalPages = Math.ceil(total / perPage);

    function render(page) {
        curPage = page;
        var start = (page - 1) * perPage;
        var end   = start + perPage;
        rows.forEach(function(r, i) {
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });
        buildPager();
    }

    function buildPager() {
        var pg = document.getElementById('pg_info_' + uid);
        if (!pg) return;
        if (totalPages <= 1) { pg.innerHTML = ''; return; }

        var from = (curPage - 1) * perPage + 1;
        var to   = Math.min(curPage * perPage, total);

        var html = '<span class="v2-pagination-info">Mostrando ' + from + ' – ' + to + ' de ' + total + ' registros</span>';
        html += '<div class="v2-pagination-btns">';
        html += '<button class="v2-pg-btn" onclick="v2pg_' + uid + '(' + (curPage-1) + ')" ' + (curPage===1?'disabled':'') + '>‹ Ant.</button>';

        var startPg = Math.max(1, curPage - 3);
        var endPg   = Math.min(totalPages, curPage + 3);
        if (startPg > 1) html += '<button class="v2-pg-btn" onclick="v2pg_' + uid + '(1)">1</button>' + (startPg > 2 ? '<span style="padding:0 4px;color:#94a3b8;">…</span>' : '');
        for (var i = startPg; i <= endPg; i++) {
            html += '<button class="v2-pg-btn' + (i===curPage?' active':'') + '" onclick="v2pg_' + uid + '(' + i + ')">' + i + '</button>';
        }
        if (endPg < totalPages) html += (endPg < totalPages-1 ? '<span style="padding:0 4px;color:#94a3b8;">…</span>' : '') + '<button class="v2-pg-btn" onclick="v2pg_' + uid + '(' + totalPages + ')">' + totalPages + '</button>';
        html += '<button class="v2-pg-btn" onclick="v2pg_' + uid + '(' + (curPage+1) + ')" ' + (curPage===totalPages?'disabled':'') + '>Sig. ›</button>';
        html += '</div>';
        pg.innerHTML = html;
    }

    window['v2pg_' + uid] = function(page) {
        if (page < 1 || page > totalPages) return;
        render(page);
        var wrapper = document.getElementById('wrapper_' + uid);
        if (wrapper) wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    render(1);
})();
</script>
