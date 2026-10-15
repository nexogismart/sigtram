{{-- Lista AJAX — equivalente a ajax_lista_secuencial_documentos.php --}}
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
