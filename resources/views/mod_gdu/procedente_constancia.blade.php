@extends('mod_gdu.layouts.iframe')

@section('title', 'Constancias Procedente — GDU')

@section('content')
<div x-data="{ opcion: @js($filtros['cboOPCIONES']) }">
    <div class="text-center mb-4">
        <h1 class="text-sm font-bold uppercase tracking-wide text-slate-800">
            Bandeja Solicitud de Certificado de Posesión (Procedente)
        </h1>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-4 shadow-sm">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-3">Seleccionar una opción para buscar</p>
        <form method="GET" action="{{ route('gdu.procedente_constancia') }}" class="flex flex-col lg:flex-row lg:items-end gap-3">
            <div class="flex flex-col min-w-[180px]">
                <label class="text-[10px] font-bold uppercase text-slate-500 mb-1">Buscar</label>
                <select name="cboOPCIONES" x-model="opcion" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    @foreach ($opciones as $valor => $etiqueta)
                        <option value="{{ $valor }}" {{ $filtros['cboOPCIONES'] === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col flex-1" x-show="opcion !== 'EMISION'" x-cloak>
                <label class="text-[10px] font-bold uppercase text-slate-500 mb-1">Palabra</label>
                <input type="text" name="txtBUSCAR" value="{{ $filtros['txtBUSCAR'] }}" maxlength="80"
                       class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div class="flex flex-wrap items-end gap-3" x-show="opcion === 'EMISION'" x-cloak>
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-slate-500 mb-1">Fecha inicio</label>
                    <input type="date" name="txtFechaInicio" value="{{ $filtros['txtFechaInicio'] }}"
                           class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="flex flex-col">
                    <label class="text-[10px] font-bold uppercase text-slate-500 mb-1">Fecha fin</label>
                    <input type="date" name="txtFechaFin" value="{{ $filtros['txtFechaFin'] }}"
                           class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <button type="submit" class="px-5 py-2 bg-rose-800 hover:bg-rose-900 text-white rounded-lg text-xs font-bold uppercase tracking-wider">
                <i class="fas fa-search mr-1"></i> Buscar
            </button>
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
                            <th class="p-3 font-semibold uppercase tracking-wider">Expediente</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">Ingreso</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">Emisión</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">N° Certif.</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">Solicitante</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">Dirección</th>
                            <th class="p-3 font-semibold uppercase tracking-wider">Estado</th>
                            <th class="p-3 font-semibold uppercase tracking-wider text-center w-16">Ver</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($registros as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-medium text-slate-800">{{ $row->num_expe }}</td>
                                <td class="p-3 text-slate-600">{{ $row->fe_ingr_trmte_fmt }}</td>
                                <td class="p-3 text-slate-600">{{ $row->fe_crtfcdo_poss_fmt }}</td>
                                <td class="p-3 text-slate-700">{{ $row->nu_crtfcdo_poss }}</td>
                                <td class="p-3 text-slate-700">{{ $row->nom_per }}</td>
                                <td class="p-3 text-slate-500" title="{{ $row->direccion }}">{{ $row->direccion_corta }}</td>
                                <td class="p-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold">
                                        {{ $row->nom_estado }}
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <a href="{{ $row->doc_url }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700"
                                       title="Ver solicitud">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
        </div>
    @endif
</div>
@endsection
