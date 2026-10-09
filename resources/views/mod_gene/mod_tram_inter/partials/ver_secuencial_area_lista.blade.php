{{-- Lista AJAX — equivalente a ajax_lista_secuencial_documentos.php --}}
<style>
    .sec-tbl { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .sec-tbl thead th {
        padding: .5rem .6rem; background: #0f172a; color: #e2e8f0;
        font-size: .65rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
        text-align: left;
    }
    .sec-tbl thead th.c { text-align: center; }
    .sec-tbl tbody td {
        padding: .5rem .6rem; border-bottom: 1px solid #e6ecf2; color: #334155; vertical-align: middle;
    }
    .sec-tbl tbody tr:nth-child(even) { background: #f8fafc; }
    .sec-tbl tbody tr:hover { background: #f8fafc; }
    .sec-tbl .c { text-align: center; }
    .sec-tbl .r { text-align: right; }
    .sec-tbl .doc { font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
    .sec-tbl .muted { color: #64748b; font-weight: 600; }
    .det-empty { text-align: center; color: #94a3b8; font-weight: 700; margin: .5rem 0; }
</style>
@if ($rows->isEmpty())
    <p class="det-empty">Sin registros para el filtro seleccionado.</p>
@else
<table class="sec-tbl">
    <thead>
        <tr>
            <th>Documento</th>
            <th class="c">Fecha Hora</th>
            <th>Detalle Documento</th>
            <th class="c">Usu</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            <td class="r doc" title="{{ $row->cdgo_scncal_tpo_trmtes_area }}">{{ $row->documento }}</td>
            <td class="c muted">{{ $row->fecha_fmt }}</td>
            <td>{{ $row->de_scncal_tpo_trmtes_area }}</td>
            <td class="c muted" title="{{ $row->no_crto }}">{{ $row->usu_ini }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
