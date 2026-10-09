@extends('mod_gdu.layouts.iframe')

@section('title', 'Licencias Procedente — GDU')

@section('content')
<div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-4 shadow-sm">
        <form method="GET" action="{{ route('gdu.procedente_licencias') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-slate-500 mb-1">Buscar</label>
                    <select name="D1" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                        @foreach ($camposBusqueda as $valor => $etiqueta)
                            <option value="{{ $valor }}" {{ $filtros['D1'] === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-slate-500 mb-1">Palabra</label>
                    <input type="text" name="criterio" value="{{ $filtros['criterio'] }}" maxlength="80"
                           class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <button type="submit" class="w-full md:w-auto px-5 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-xs font-bold uppercase tracking-wider">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end border-t border-slate-100 pt-4">
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-slate-500 mb-1">Por</label>
                    <select name="sel_tipo_fecha" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                        @foreach ($camposFecha as $valor => $etiqueta)
                            <option value="{{ $valor }}" {{ $filtros['sel_tipo_fecha'] === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-slate-500 mb-1">Fecha inicio</label>
                    <input type="date" name="txt_inicio" value="{{ $filtros['txt_inicio'] }}"
                           class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-slate-500 mb-1">Fecha fin</label>
                    <input type="date" name="txt_fin" value="{{ $filtros['txt_fin'] }}"
                           class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
        </form>
    </div>

    @if (!empty($error))
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-6 text-center text-sm text-rose-700">
            <i class="fas fa-exclamation-triangle mb-2"></i>
            <p>{{ $error }}</p>
        </div>
    @elseif (!$registros || $registros->total() === 0)
        <div class="bg-white border border-slate-200 rounded-xl p-8 text-center text-sm text-slate-500">
            No se encontraron resultados
        </div>
    @else
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-800 text-white">
                        <tr>
                            <th class="p-3 w-8"></th>
                            <th class="p-3 font-semibold uppercase tracking-wider">Expediente</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">Fecha Lic.</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">N° Licencia</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">N° Resolución</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">Solicitante</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">Dirección de la licencia</th>
                            <th class="p-3 font-semibold uppercase tracking-wider text-center">Riesgo</th>
                            <th class="p-3 font-semibold uppercase tracking-wider text-center w-12" title="Certificado">Cer.</th>
                            <th class="p-3 font-semibold uppercase tracking-wider text-center w-12" title="Resolución">Res.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($registros as $index => $row)
                            <tr class="hover:bg-slate-50 {{ $index % 2 === 0 ? 'bg-white' : 'bg-slate-50/40' }}">
                                <td class="p-3 text-center">
                                    <input type="radio" name="rdn_cdgo_dclrcon_jrda" value="{{ $row->cdgo_dclrcon_jrda }}" class="text-slate-800">
                                </td>
                                <td class="p-3 font-medium text-blue-700 cursor-pointer hover:underline"
                                    onclick="window.open('{{ $row->expediente_url }}', '_blank', 'scrollbars=auto,width=770,height=310')">
                                    {{ $row->nu_expe_fmt }}
                                </td>
                                <td class="p-3 text-slate-600">{{ $row->fe_lcncia_fmt }}</td>
                                <td class="p-3 text-slate-700">{{ $row->nu_crtfcdo }}</td>
                                <td class="p-3 text-slate-700">{{ $row->nu_rslcon }}</td>
                                <td class="p-3 text-slate-700" title="{{ $row->no_crto }}">{{ $row->no_crto_corto }}</td>
                                <td class="p-3 text-slate-500" title="{{ $row->de_drccon_estblcmnto }}">{{ $row->direccion_corta }}</td>
                                <td class="p-3 text-center font-semibold text-slate-700">{{ $row->riesgo }}</td>
                                <td class="p-3 text-center">
                                    <a href="{{ $row->certificado_url }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700"
                                       title="Certificado">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                                <td class="p-3 text-center">
                                    @if ($row->resolucion_url)
                                        <a href="{{ $row->resolucion_url }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700"
                                           title="Resolución">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                    @else
                                        <span class="text-slate-300">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($registros->hasPages())
                <div class="p-4 border-t border-slate-200 bg-slate-50 flex flex-wrap justify-between items-center gap-3">
                    <div class="text-xs text-slate-500">
                        Mostrando {{ $registros->firstItem() }} - {{ $registros->lastItem() }} de {{ $registros->total() }}
                    </div>
                    <div class="flex items-center gap-1">
                        @if ($registros->onFirstPage())
                            <span class="px-3 py-1 rounded border border-slate-200 text-slate-300 text-xs">Anterior</span>
                        @else
                            <a href="{{ $registros->previousPageUrl() }}" class="px-3 py-1 rounded border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 text-xs">Anterior</a>
                        @endif

                        @foreach ($registros->getUrlRange(max(1, $registros->currentPage() - 2), min($registros->lastPage(), $registros->currentPage() + 2)) as $page => $url)
                            @if ($page == $registros->currentPage())
                                <span class="px-3 py-1 rounded bg-slate-800 text-white text-xs font-bold">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="px-3 py-1 rounded border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 text-xs">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if ($registros->hasMorePages())
                            <a href="{{ $registros->nextPageUrl() }}" class="px-3 py-1 rounded border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 text-xs">Siguiente</a>
                        @else
                            <span class="px-3 py-1 rounded border border-slate-200 text-slate-300 text-xs">Siguiente</span>
                        @endif
                    </div>
                </div>
            @endif

            <div class="p-4 border-t border-slate-200 flex flex-wrap justify-center gap-6 text-xs font-bold uppercase tracking-wider">
                <a href="{{ $urls['ver_todo'] }}" target="_blank" class="text-slate-700 hover:text-blue-700">Ver todo</a>
                <a href="{{ $urls['imprimir'] }}" target="_blank" class="text-slate-700 hover:text-blue-700">Imprimir</a>
                <a href="{{ $urls['descargar'] }}" target="_blank" class="text-slate-700 hover:text-blue-700">Descargar</a>
            </div>
        </div>
    @endif
</div>
@endsection
