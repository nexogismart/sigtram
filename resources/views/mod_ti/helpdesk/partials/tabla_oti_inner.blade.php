@forelse ($tickets ?? [] as $t)
@php
    $situacion = (int) ($t->situacion ?? 0);
    $estados = [1 => 'Requerimiento', 2 => 'Pendiente', 3 => 'En Proceso', 4 => 'Finalizado', 5 => 'Confirmado'];
    $estadoTexto = $estados[$situacion] ?? 'Desconocido';
    $prioridad = strtolower($t->prioridad ?? 'normal');
@endphp
<tr class="table-row-hover hover:bg-blue-50/30">
    {{-- ID TICKET --}}
    <td class="py-1.5 px-2 whitespace-nowrap">
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-700/10">
            #{{ $t->id }}
        </span>
    </td>

    {{-- PRIORIDAD --}}
    <td class="py-1.5 px-2 whitespace-nowrap">
        @if($prioridad == 'urgente' || $prioridad == 'alta')
            <span class="inline-flex items-center gap-1 py-0.5 px-1.5 rounded text-[10px] font-bold bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> {{ $t->prioridad ?? 'Urgente' }}
            </span>
        @elseif($prioridad == 'media')
            <span class="inline-flex items-center gap-1 py-0.5 px-1.5 rounded text-[10px] font-bold bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20">
                <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span> Media
            </span>
        @else
            <span class="inline-flex items-center gap-1 py-0.5 px-1.5 rounded text-[10px] font-bold bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ $t->prioridad ?? 'Normal' }}
            </span>
        @endif
    </td>

    {{-- ASUNTO --}}
    <td class="py-1.5 px-2 max-w-[170px] break-words whitespace-normal">
        <p class="text-xs font-semibold text-gray-900 leading-tight">
            {{ $t->asunto ?? 'Sin asunto' }}
        </p>
        <p class="text-[10px] text-gray-400 mt-0.5 leading-snug truncate" title="{{ $t->detalle ?? '' }}">
            {{ $t->detalle ?? '' }}
        </p>
    </td>

    {{-- ESTADO --}}
    <td class="py-1.5 px-2 text-center whitespace-nowrap">
        @if($situacion === 1)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-700">
                Requerimiento
            </span>
        @elseif($situacion === 2)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700">
                Pendiente
            </span>
        @elseif($situacion === 3)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700">
                En Proceso
            </span>
        @elseif($situacion === 4)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-cyan-100 text-cyan-700">
                Finalizado
            </span>
        @elseif($situacion === 5)
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">
                Confirmado
            </span>
        @endif
    </td>

    {{-- ÁREA --}}
    <td class="py-1.5 px-2 max-w-[170px] break-words whitespace-normal">
        <span class="inline-block py-0.5 px-1.5 rounded text-[10px] font-semibold bg-gray-50 text-gray-600 border border-gray-100 leading-normal max-w-[160px] whitespace-normal break-words">
            {{ $t->area ?? '-' }}
        </span>
    </td>

    {{-- FECHA Y HORA --}}
    <td class="py-1.5 px-2 whitespace-nowrap text-[11px] text-gray-600 leading-tight">
        @if($t->fecha)
            <span class="font-semibold text-[10px]">{{ \Carbon\Carbon::parse($t->fecha)->format('d/m/Y') }}</span>
            <span class="text-[9px] text-gray-400 block mt-0.5">{{ \Carbon\Carbon::parse($t->fecha)->format('h:i A') }}</span>
        @else
            -
        @endif
    </td>

    {{-- SOLICITANTE --}}
    <td class="py-1.5 px-2 max-w-[130px] break-words whitespace-normal">
        <div class="flex items-center">
            <div class="h-5 w-5 rounded-full bg-gradient-to-tr from-blue-100 to-blue-200 flex items-center justify-center text-blue-700 font-bold text-[8px] shrink-0 shadow-inner">
                {{ strtoupper(substr($t->solicitante ?? 'US', 0, 2)) }}
            </div>
            <span class="ml-1.5 text-[11px] font-semibold text-gray-700 truncate leading-tight inline-block max-w-[100px]" title="{{ $t->solicitante ?? '' }}">
                {{ $t->solicitante ?? 'Usuario Desconocido' }}
            </span>
        </div>
    </td>

    {{-- ACCIONES --}}
    <td class="py-1.5 px-2 whitespace-nowrap text-right">
        <div class="flex justify-end gap-1 flex-wrap">
            {{-- Ver Detalle --}}
            <button onclick="verDetalleTicket({{ $t->id }})"
                class="inline-flex items-center px-1.5 py-0.5 bg-gray-50 hover:bg-gray-100 text-gray-700 hover:text-gray-900 text-[10px] font-semibold rounded border border-gray-300 transition-colors">
                Ver detalle
            </button>

            @if($situacion === 2)
                {{-- Confirmar --}}
                <button onclick="confirmarTicketOti({{ $t->id }})"
                    class="inline-flex items-center px-1.5 py-0.5 bg-red-50 hover:bg-red-650 text-red-700 hover:text-white text-[10px] font-semibold rounded border border-red-200 transition-colors">
                    Confirmar
                </button>
                {{-- Derivar --}}
                <button onclick="abrirDerivar({{ $t->id }}, 2)"
                    class="inline-flex items-center px-1.5 py-0.5 bg-teal-50 hover:bg-teal-650 text-teal-700 hover:text-white text-[10px] font-semibold rounded border border-teal-200 transition-colors">
                    Derivar
                </button>
            @elseif($situacion === 3)
                {{-- Finalizar --}}
                <button onclick="finalizarTicketOti({{ $t->id }})"
                    class="inline-flex items-center px-1.5 py-0.5 bg-orange-50 hover:bg-orange-650 text-orange-700 hover:text-white text-[10px] font-semibold rounded border border-orange-200 transition-colors">
                    Finalizar
                </button>
                {{-- Derivar --}}
                <button onclick="abrirDerivar({{ $t->id }}, 3)"
                    class="inline-flex items-center px-1.5 py-0.5 bg-teal-50 hover:bg-teal-650 text-teal-700 hover:text-white text-[10px] font-semibold rounded border border-teal-200 transition-colors">
                    Derivar
                </button>
            @elseif($situacion === 4)
                <span class="inline-flex items-center px-1.5 py-0.5 bg-cyan-50 text-cyan-700 text-[10px] font-semibold rounded border border-cyan-200">
                    ✓ Finalizado
                </span>
            @elseif($situacion === 5)
                <span class="inline-flex items-center px-1.5 py-0.5 bg-green-50 text-green-700 text-[10px] font-semibold rounded border border-green-200">
                    ✓ Confirmado
                </span>
            @endif
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="8" class="py-8 text-center text-gray-500 text-xs font-semibold">
        No hay tickets registrados en esta bandeja.
    </td>
</tr>
@endforelse
