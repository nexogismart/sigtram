<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reporte por Cargo — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { font-family: "Figtree", "Segoe UI", Tahoma, sans-serif; }
        .cell-click {
            cursor: pointer;
            transition: background-color .15s, color .15s;
        }
        .cell-click:hover {
            background-color: #f1f5f9;
            color: #0f172a;
            border-radius: .375rem;
        }
        .cell-click.active {
            background-color: #334155;
            color: #fff;
            border-radius: .375rem;
            font-weight: 800;
        }
        .detalle-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: .75rem;
            padding: .75rem;
            margin-top: .25rem;
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-gray-900 min-h-screen">

<main class="p-4 lg:p-6 max-w-[1400px] mx-auto w-full">

    <div class="mb-4 text-right">
        <h1 class="text-lg font-extrabold text-slate-800 uppercase tracking-wide">
            Reporte General de Trámites Internos por Cargo
        </h1>
        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-1">
            Área de sesión · Cargos activos
        </p>
    </div>

    {{-- Filtros: Desde / Hasta / Buscar (como el antiguo) --}}
    <form method="POST" action="{{ route('mod_tram_inter.reporte_cargo_interno') }}" class="bg-white border border-slate-100 rounded-xl shadow-sm p-4 mb-5">
        @csrf
        <div class="flex flex-wrap items-end justify-center gap-3">
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1" for="txt_fe_inicio">Desde</label>
                <input type="text" name="txt_fe_inicio" id="txt_fe_inicio" value="{{ $desdeStr }}"
                       placeholder="dd/mm/aaaa"
                       class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold text-gray-700 focus:ring-2 focus:ring-slate-500 focus:border-slate-500 outline-none"
                       pattern="\d{2}/\d{2}/\d{4}" required>
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1" for="txt_fe_fin">Hasta</label>
                <input type="text" name="txt_fe_fin" id="txt_fe_fin" value="{{ $hastaStr }}"
                       placeholder="dd/mm/aaaa"
                       class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm font-bold text-gray-700 focus:ring-2 focus:ring-slate-500 focus:border-slate-500 outline-none"
                       pattern="\d{2}/\d{2}/\d{4}" required>
            </div>
            <button type="submit" name="Buscar" value="1"
                    class="px-5 py-2.5 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition-colors">
                <i class="fas fa-search mr-1"></i> Buscar
            </button>
        </div>
    </form>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-700 text-white text-[10px] font-black uppercase tracking-wider">
                        <th class="px-4 py-3 text-left">Nombre del Cargo</th>
                        <th class="px-3 py-3 text-center w-24">Por recibir</th>
                        <th class="px-3 py-3 text-center w-24">Recibidos</th>
                        <th class="px-3 py-3 text-center w-28">Derivado UU.OO</th>
                        <th class="px-3 py-3 text-center w-24">Finalizados</th>
                        <th class="px-3 py-3 text-center w-24">Total Doc.</th>
                        <th class="px-3 py-3 text-center w-28">Total Rec. Der.</th>
                        <th class="px-3 py-3 text-right w-24">% Trabajo</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows_data as $i => $row)
                    @php
                        $idx = $i + 1;
                        $titleUsuarios = implode(' -- ', $row['usuarios']);
                        $labelIniciales = implode(' - ', $row['iniciales']);
                        $cargoLabel = $row['cargo'] . ($labelIniciales !== '' ? ' (' . $labelIniciales . ')' : '');
                    @endphp
                    <tr class="border-b border-gray-100 hover:bg-slate-50/40">
                        <td class="px-4 py-3 font-semibold text-gray-800" title="{{ $titleUsuarios }}">
                            {{ $cargoLabel }}
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="cell-click inline-block min-w-[2rem] px-2 py-1"
                                  data-cargo="{{ $row['id'] }}" data-tipo="1" data-idx="{{ $idx }}">
                                {{ $row['entrada'] }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="cell-click inline-block min-w-[2rem] px-2 py-1"
                                  data-cargo="{{ $row['id'] }}" data-tipo="2" data-idx="{{ $idx }}">
                                {{ $row['recibido'] }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="cell-click inline-block min-w-[2rem] px-2 py-1"
                                  data-cargo="{{ $row['id'] }}" data-tipo="3" data-idx="{{ $idx }}">
                                {{ $row['salida'] }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="cell-click inline-block min-w-[2rem] px-2 py-1"
                                  data-cargo="{{ $row['id'] }}" data-tipo="4" data-idx="{{ $idx }}">
                                {{ $row['finalizado'] }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-center font-bold text-gray-800">{{ $row['total'] }}</td>
                        <td class="px-3 py-3 text-center font-bold text-gray-700">{{ $row['gestionados'] }}</td>
                        <td class="px-3 py-3 text-right font-bold
                            {{ $row['porcentaje'] >= 80 ? 'text-slate-600' : ($row['porcentaje'] >= 50 ? 'text-amber-600' : 'text-red-500') }}">
                            {{ number_format($row['porcentaje'], 2) }}
                        </td>
                    </tr>
                    <tr class="border-b border-gray-100">
                        <td colspan="8" class="px-4 py-0">
                            <div id="conte_resultados{{ $idx }}" class="detalle-box hidden"></div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400 font-semibold">
                            No hay cargos activos en el área actual.
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if(count($rows_data) > 0)
                <tfoot>
                    <tr class="bg-slate-900 text-white font-bold">
                        <td class="px-4 py-3 text-right uppercase tracking-wide text-xs">Totales :</td>
                        <td class="px-3 py-3 text-center">{{ number_format($totales['sum_entregado'], 0) }}</td>
                        <td class="px-3 py-3 text-center">{{ number_format($totales['sum_recibido'], 0) }}</td>
                        <td class="px-3 py-3 text-center">{{ number_format($totales['sum_salida'], 0) }}</td>
                        <td class="px-3 py-3 text-center">{{ number_format($totales['sum_finalizado'], 0) }}</td>
                        <td class="px-3 py-3 text-center">{{ number_format($totales['sum_total_doc'], 0) }}</td>
                        <td class="px-3 py-3 text-center">{{ number_format($totales['sum_total_ges'], 0) }}</td>
                        <td class="px-3 py-3 text-right">&nbsp;</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</main>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const URL_DETALLE = @json(route('mod_tram_inter.reporte_cargo_interno.detalle'));
    const FECHA_INI = @json($desdeStr);
    const FECHA_FIN = @json($hastaStr);
    let lastKey = null;

    document.querySelectorAll('.cell-click').forEach(el => {
        el.addEventListener('click', function () {
            const cargo = this.dataset.cargo;
            const tipo = this.dataset.tipo;
            const idx = this.dataset.idx;
            const box = document.getElementById('conte_resultados' + idx);
            const key = cargo + '-' + tipo + '-' + idx;

            document.querySelectorAll('.cell-click').forEach(c => c.classList.remove('active'));

            // Toggle: mismo click cierra el detalle (como el antiguo con sesión entro)
            if (lastKey === key && !box.classList.contains('hidden')) {
                box.classList.add('hidden');
                box.innerHTML = '';
                lastKey = null;
                return;
            }

            lastKey = key;
            this.classList.add('active');

            // Ocultar otros detalles abiertos
            document.querySelectorAll('[id^="conte_resultados"]').forEach(d => {
                if (d !== box) {
                    d.classList.add('hidden');
                    d.innerHTML = '';
                }
            });

            box.classList.remove('hidden');
            box.innerHTML = '<div class="text-center py-4 text-slate-600 text-xs font-bold uppercase tracking-widest"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>';

            $.ajax({
                url: URL_DETALLE,
                method: 'POST',
                data: {
                    _token: CSRF,
                    cargo_id: cargo,
                    tipo_bandeja: tipo,
                    fecha_inicio: FECHA_INI,
                    fecha_fin: FECHA_FIN
                },
                success: function (res) {
                    box.innerHTML = res.html || '<div class="text-center text-gray-400 py-3">Sin datos</div>';
                },
                error: function () {
                    box.innerHTML = '<div class="text-center text-red-500 py-3 font-bold text-xs">Error al cargar el detalle</div>';
                }
            });
        });
    });
})();
</script>
</body>
</html>
