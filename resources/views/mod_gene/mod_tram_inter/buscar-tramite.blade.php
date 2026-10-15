<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buscar Trámite Interno — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/tramite-interno/buscar-tramite.css', 'resources/js/app.js'])

{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-head', ['screenKey' => 'mod_gene.mod_tram_inter.buscar-tramite', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}
</head>
<body class="bg-[#f8fafc] text-gray-800 min-h-screen">
{{-- SIGTRAM integration: presentation only --}}
@include('components.sigtram.module-bar', ['screenKey' => 'mod_gene.mod_tram_inter.buscar-tramite', 'sigtramNative' => true])
{{-- /SIGTRAM integration --}}


@php
    $f = $filtros ?? [];
    $frontendConfig = [
        'csrfToken' => csrf_token(),
        'urlTipos' => route('mod_tram_inter.buscar_tramite.tipos'),
        'urlHist' => route('mod_tram_inter.buscar_tramite.historial'),
        'urlDet' => route('mod_tram_inter.buscar_tramite.detalle'),
        'urlDesc' => route('mod_tram_inter.descargar'),
    ];
@endphp

<div id="buscar-tramite-app" x-data="buscarTramiteApp" data-config="{{ json_encode($frontendConfig) }}" class="p-3 sm:p-5 w-full max-w-[1400px] mx-auto space-y-4">

    {{-- Título (lista_tramite_todo) --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between gap-3">
            <h1 class="text-sm sm:text-base font-bold uppercase tracking-wide">Buscar Trámite Interno</h1>
            <a href="{{ route('mod_tram_inter.ingresar_tramite') }}" class="text-[10px] font-black uppercase tracking-widest bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-lg">
                <i class="fas fa-plus mr-1"></i> Nuevo
            </a>
        </div>

        {{-- Filtros como el antiguo --}}
        <form method="get" action="{{ route('mod_tram_inter.buscar_tramite') }}" id="form-buscar" class="px-4 py-5 bg-gray-50/70 border-b border-gray-100 space-y-4">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Búsqueda genérica</p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                <div class="lg:col-span-3">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Gerencia Origen</label>
                    <select name="sel_cdgo_area" id="sel_cdgo_area" @change="cargarTipos($event.target.value)"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                        @foreach ($areas as $area)
                            <option value="{{ $area->cdgo_area }}" @selected((int)($f['sel_cdgo_area'] ?? 0) === (int)$area->cdgo_area)>
                                {{ $area->de_area }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-3">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Tipo Documento</label>
                    <select name="sel_cdgo_tpo_trmte" id="sel_cdgo_tpo_trmte"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                        <option value="0">-- Seleccione --</option>
                        @foreach ($tiposDocumento as $tipo)
                            <option value="{{ $tipo->cdgo_tpo_trmte }}" @selected((string)($f['sel_cdgo_tpo_trmte'] ?? '0') === (string)$tipo->cdgo_tpo_trmte)>
                                {{ $tipo->de_tpo_trmte }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">N° Documento</label>
                    <input type="text" name="txt_nu_tram_todo" id="txt_nu_tram_todo" value="{{ $f['txt_nu_tram_todo'] ?? '' }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none"
                           placeholder="Nº trámite" inputmode="numeric" pattern="[0-9\-]*">
                </div>

                <div class="lg:col-span-4">
                    <label class="block text-[10px] font-bold text-gray-500 mb-1">Gerencia Destino</label>
                    <select name="sel_cdgo_area_en" id="sel_cdgo_area_en"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                        <option value="">-- Seleccione área destino --</option>
                        @foreach ($destinoAreas as $area)
                            <option value="{{ $area->cdgo_area }}" @selected((string)($f['sel_cdgo_area_en'] ?? '') === (string)$area->cdgo_area)>
                                {{ $area->de_area }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Fechas opcionales (ver_nover del antiguo) --}}
            <div class="flex flex-wrap items-end gap-3 pt-1">
                <button type="button" @click="toggleFechas()" class="text-[10px] font-black uppercase tracking-widest text-slate-700 hover:text-slate-900">
                    <i class="fas fa-calendar-alt mr-1"></i> <span id="lbl-fechas">Mostrar fechas</span>
                </button>
                <div id="panel-fechas" class="hidden flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Desde</label>
                        <input type="date" name="T1" id="T1" value="{{ $f['T1'] ?? '' }}"
                               class="border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1">Hasta</label>
                        <input type="date" name="T2" id="T2" value="{{ $f['T2'] ?? '' }}"
                               class="border border-gray-300 rounded-lg px-3 py-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                </div>
                <div class="flex-1"></div>
                <button type="submit" class="px-6 py-2.5 bg-slate-600 hover:bg-slate-700 text-white text-[11px] font-black uppercase tracking-widest rounded-lg shadow-sm">
                    <i class="fas fa-search mr-1"></i> Buscar
                </button>
                <a href="{{ route('mod_tram_inter.buscar_tramite') }}" class="px-4 py-2.5 bg-white border border-gray-300 text-gray-600 text-[11px] font-black uppercase tracking-widest rounded-lg hover:bg-gray-50">
                    Ver todo
                </a>
            </div>
        </form>

        {{-- Tabla: Trámite | N° | Origen | Destino | Ingreso | Observaciones | ojo --}}
        <div class="relative overflow-auto custom-scroll max-h-[520px]">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-slate-50 sticky top-0 z-[5]">
                    <tr>
                        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Trámite</th>
                        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">N° Trámite</th>
                        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Origen</th>
                        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Destino</th>
                        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center">Ingreso</th>
                        <th class="px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700">Observaciones</th>
                        <th class="px-2 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-700 text-center w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tramites as $i => $row)
                        <tr class="{{ $i % 2 ? 'fila-alt' : 'bg-white' }} fila-hover cursor-pointer border-b border-gray-100"
                            @click="verHistorial({{ (int)$row->cdgo_dtos_ntrnos }}, '{{ e($row->nu_tram_todo) }}')">
                            <td class="px-3 py-2 text-[12px] font-bold">{{ $row->dc_tpo_trmte }}</td>
                            <td class="px-3 py-2 text-[12px] font-mono font-bold text-slate-700">{{ $row->nu_tram_todo }}</td>
                            <td class="px-3 py-2 text-[11px]" title="{{ $row->area_origen }}">{{ $row->dc_area_origen }}</td>
                            <td class="px-3 py-2 text-[11px]" title="{{ $row->area_destino }}">{{ $row->dc_area_destino ?: '—' }}</td>
                            <td class="px-3 py-2 text-center text-[11px] whitespace-nowrap font-semibold text-gray-600">{{ $row->fe_ingrso_fmt }}</td>
                            <td class="px-3 py-2 text-[11px] text-gray-600" title="{{ $row->de_obser }}">{{ \Illuminate\Support\Str::limit($row->de_obser ?? '', 75) }}</td>
                            <td class="px-2 py-2 text-center" @click.stop>
                                <button type="button" @click="verVoucher({{ (int)$row->cdgo_dtos_ntrnos }})" class="text-slate-600 hover:text-slate-800" title="Detalle">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-xs text-gray-400 font-semibold">
                                No se encontraron resultados
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3 bg-white">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                {{ $tramites->total() }} registro(s) — pág. {{ $tramites->currentPage() }} / {{ max(1, $tramites->lastPage()) }}
            </span>
            <div class="flex items-center gap-1 text-xs font-bold">
                @if ($tramites->onFirstPage())
                    <span class="px-2 py-1 text-gray-300">Anterior</span>
                @else
                    <a href="{{ $tramites->previousPageUrl() }}" class="px-2 py-1 text-slate-700 hover:underline">Anterior</a>
                @endif

                @foreach ($tramites->getUrlRange(max(1, $tramites->currentPage() - 3), min($tramites->lastPage(), $tramites->currentPage() + 3)) as $page => $url)
                    @if ($page == $tramites->currentPage())
                        <span class="px-2.5 py-1 bg-slate-800 text-white rounded">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="px-2.5 py-1 text-slate-700 hover:bg-slate-50 rounded">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($tramites->hasMorePages())
                    <a href="{{ $tramites->nextPageUrl() }}" class="px-2 py-1 text-slate-700 hover:underline">Siguiente</a>
                @else
                    <span class="px-2 py-1 text-gray-300">Siguiente</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Panel historial (popup segi_tramite del antiguo) --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-slate-800 text-white px-5 py-2.5 flex items-center justify-between">
            <h2 id="hist-titulo" class="text-xs font-bold uppercase tracking-wide">Detalle / historial del trámite</h2>
            <button type="button" @click="limpiarHistorial()" class="text-white/80 hover:text-white text-sm"><i class="fas fa-times"></i></button>
        </div>
        <div id="panel-historial" class="p-4 max-h-[320px] overflow-auto custom-scroll">
            <p class="text-xs text-gray-400 italic text-center py-10">Seleccione un trámite de la lista para ver su historial.</p>
        </div>
    </div>
</div>

{{-- Modal voucher (ojo) --}}
<div id="modal-voucher" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="bg-slate-800 text-white px-4 py-3 flex items-center justify-between">
            <h3 class="text-sm font-bold uppercase">Detalle del trámite</h3>
            <button type="button" @click="cerrarVoucher()" class="text-white/80 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <div id="voucher-body" class="p-5 text-sm space-y-2"></div>
    </div>
</div>

</body>
</html>
