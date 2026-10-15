{{-- Detalle AJAX — equivalente a ajax_reporte_cargo.php --}}
<div class="overflow-x-auto">
    <table class="w-full text-xs border border-slate-200 rounded-lg overflow-hidden">
        <thead>
            <tr class="bg-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-600">
                <th class="px-2 py-2 text-center w-8">#</th>
                <th class="px-2 py-2 text-left">Tipo Tram.</th>
                <th class="px-2 py-2 text-left">N° Tram</th>
                <th class="px-2 py-2 text-center">Área Envía</th>
                <th class="px-2 py-2 text-center">F. Inicial</th>
                <th class="px-2 py-2 text-center">F. Última</th>
                <th class="px-2 py-2 text-left">Observaciones</th>
                <th class="px-2 py-2 text-center w-8"></th>
                <th class="px-2 py-2 text-center w-8"></th>
            </tr>
        </thead>
        <tbody>
        @forelse($tramites as $i => $t)
            <tr class="border-t border-slate-100 hover:bg-slate-50/50">
                <td class="px-2 py-2 text-center text-slate-400 font-semibold">{{ $i + 1 }}</td>
                <td class="px-2 py-2 font-bold text-slate-700" title="{{ $t->de_tpo_trmte }}">{{ $t->dc_tpo_trmte }}</td>
                <td class="px-2 py-2 font-mono font-bold text-slate-800">{{ $t->nu_tram_format }}</td>
                <td class="px-2 py-2 text-center font-semibold text-slate-600" title="{{ $t->de_area_inco }}">{{ $t->dc_area_inco }}</td>
                <td class="px-2 py-2 text-center text-slate-600 whitespace-nowrap">{{ $t->fechaini }}</td>
                <td class="px-2 py-2 text-center text-slate-600 whitespace-nowrap">{{ $t->fe_ultima }}</td>
                <td class="px-2 py-2 text-slate-700" title="{{ $t->de_obser }}">{{ $t->de_obser_corto }}</td>
                <td class="px-2 py-2 text-center">
                    @if (!empty($t->de_urls_adjnto))
                        <a href="{{ route('mod_tram_inter.descargar', ['id' => $t->cdgo_dtos_ntrnos]) }}"
                           target="_blank" class="text-slate-600 hover:text-slate-800" title="Adjunto">
                            <i class="fas fa-paperclip"></i>
                        </a>
                    @endif
                </td>
                <td class="px-2 py-2 text-center">
                    <a href="/Presentacion/busca_vauche.php?id_tram={{ $t->cdgo_dtos_ntrnos }}"
                       data-action="popup" data-width="470" data-height="310"
                       target="_blank" class="text-slate-500 hover:text-slate-700" title="Ver">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="px-3 py-6 text-center text-slate-400 font-semibold">
                    No hay registros en este rango de fechas.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
