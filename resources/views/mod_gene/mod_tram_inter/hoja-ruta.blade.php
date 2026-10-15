<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoja de Ruta — {{ $tramite->de_tpo_trmte ?? 'DOCUMENTO' }} {{ $tramite->nu_tram_todo ?? '' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: "Figtree", Arial, sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0 !important; background: white !important; }
            .hoja-a4 { box-shadow: none !important; border: none !important; max-width: 100% !important; margin: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 p-4 sm:p-6 min-h-screen">

    {{-- Barra de herramientas superior (oculta al imprimir) --}}
    <div class="max-w-4xl mx-auto mb-4 flex items-center justify-between no-print">
        <button type="button" data-action="close" class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 hover:text-slate-900 bg-white px-3.5 py-2 rounded-xl border border-gray-200 shadow-sm transition-colors cursor-pointer">
            <i class="fas fa-arrow-left"></i> Volver / Cerrar
        </button>
        <div class="flex items-center gap-2">
            <button type="button" data-action="print" class="inline-flex items-center gap-2 text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-xl shadow-sm transition-all cursor-pointer">
                <i class="fas fa-print"></i> Imprimir Hoja de Ruta
            </button>
        </div>
    </div>

    {{-- Documento en Hoja A4 --}}
    <div class="hoja-a4 max-w-4xl mx-auto bg-white border border-gray-200 rounded-2xl shadow-md p-6 sm:p-8">
        
        {{-- Membrete --}}
        <div class="flex items-start justify-between border-b-2 border-slate-800 pb-4 mb-5">
            <div class="flex items-center gap-3">
                <img src="/asset/images/escudo.png" alt="Escudo" class="w-12 h-12 object-contain">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-600">Municipalidad Distrital de Puente Piedra</h2>
                    <h1 class="text-base font-extrabold text-slate-900 uppercase">Hoja de Ruta — Trámite Interno</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Sistema Integrado de Trámite Municipal — SIGTRAM</p>
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider block">Fecha y Hora de Emisión</span>
                <span class="text-xs font-bold text-slate-700 font-mono">{{ $fechaImpresion }}</span>
            </div>
        </div>

        {{-- Datos Generales del Documento --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-50 border border-slate-200 rounded-xl p-4 mb-6 text-xs">
            <div>
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider block">Expediente / Trámite</span>
                <span class="font-bold text-slate-900 font-mono text-sm">{{ $tramite->de_tpo_trmte ?? 'DOCUMENTO' }} {{ $tramite->nu_tram_todo ?? $tramite->cdgo_dtos_ntrnos }}</span>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider block">Área de Inicio</span>
                <span class="font-bold text-slate-800">{{ $tramite->de_area ?? '-' }} ({{ $tramite->dc_area ?? '' }})</span>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider block">Fecha de Registro</span>
                <span class="font-bold text-slate-800">{{ $tramite->fe_ingrso ? \Carbon\Carbon::parse($tramite->fe_ingrso)->format('d/m/Y H:i') : '-' }}</span>
            </div>
            <div class="md:col-span-3 pt-2 border-t border-slate-200">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider block mb-0.5">Asunto / Observaciones Iniciales</span>
                <p class="text-slate-700 leading-relaxed">{{ $tramite->de_obser ?: 'Sin observaciones iniciales registradas.' }}</p>
            </div>
        </div>

        {{-- Tabla de Trazabilidad y Recorrido --}}
        <div class="mb-6">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 mb-2 flex items-center gap-2">
                <i class="fas fa-route text-amber-500"></i> Historial y Secuencia de Movimientos
            </h3>
            <table class="w-full text-left text-xs border border-gray-200 rounded-xl overflow-hidden border-collapse">
                <thead class="bg-slate-800 text-white text-[10px] uppercase font-bold tracking-wider">
                    <tr>
                        <th class="px-2.5 py-2 text-center w-8">N°</th>
                        <th class="px-3 py-2">Área Origen</th>
                        <th class="px-3 py-2">Área Destino</th>
                        <th class="px-2.5 py-2 text-center whitespace-nowrap">Fecha / Hora</th>
                        <th class="px-2.5 py-2 text-center">Estado</th>
                        <th class="px-3 py-2">Responsable</th>
                        <th class="px-4 py-2 w-36 text-center">Sello / Firma</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-[11px]">
                    @forelse($movimientos as $idx => $m)
                    <tr class="{{ $idx % 2 === 1 ? 'bg-slate-50/70' : 'bg-white' }}">
                        <td class="px-2.5 py-2 text-center font-bold text-slate-700">{{ $idx + 1 }}</td>
                        <td class="px-3 py-2 text-slate-700">
                            <span class="font-bold block text-slate-800">{{ $m->area_origen_sigla ?: $m->area_origen }}</span>
                            <span class="text-[10px] text-slate-400">{{ $m->area_origen }}</span>
                        </td>
                        <td class="px-3 py-2 text-slate-700">
                            <span class="font-bold block text-slate-800">{{ $m->area_destino_sigla ?: $m->area_destino }}</span>
                            <span class="text-[10px] text-slate-400">{{ $m->area_destino }}</span>
                        </td>
                        <td class="px-2.5 py-2 text-center whitespace-nowrap font-mono text-[10px]">
                            {{ $m->fe_rcbdo ? \Carbon\Carbon::parse($m->fe_rcbdo)->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="px-2.5 py-2 text-center">
                            <span class="inline-block px-2 py-0.5 rounded-md bg-slate-100 text-slate-800 font-bold text-[9px] uppercase border border-slate-200">
                                {{ $m->estado ?: 'EN CURSO' }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-slate-800 font-medium">
                            {{ $m->usuario_nombre ?: '-' }}
                        </td>
                        <td class="px-4 py-2 text-center align-middle border-l border-gray-100 h-16">
                            <div class="w-full h-12 border border-dashed border-gray-300 rounded-lg flex items-center justify-center text-[9px] text-gray-300">
                                Conformidad
                            </div>
                        </td>
                    </tr>
                    @if($m->de_obser_ntrnos)
                    <tr class="bg-amber-50/30 text-[10px] text-slate-600">
                        <td colspan="7" class="px-4 py-1.5 italic border-b border-gray-100">
                            <strong class="text-slate-700 not-italic">Proveído/Obs:</strong> {{ $m->de_obser_ntrnos }}
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-400 italic">
                            Sin movimientos registrados para este documento.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer institucional --}}
        <div class="border-t border-gray-200 pt-4 flex items-center justify-between text-[10px] text-gray-400">
            <span>MDPP — Municipalidad Distrital de Puente Piedra</span>
            <span>Documento emitido electrónicamente</span>
        </div>
    </div>

</body>
</html>