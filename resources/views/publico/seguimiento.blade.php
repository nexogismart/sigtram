<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Trámite {{ $expediente['numero_expediente'] }} — SIGTRAM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen antialiased flex flex-col justify-between">

    <header class="bg-slate-900 text-white shadow-md border-b border-slate-800">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo-escudo.png') }}" alt="Escudo Institucional" class="h-10 w-auto object-contain" onerror="this.style.display='none'">
                <div>
                    <h1 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-100">{{ $expediente['compania_nombre'] }}</h1>
                    <p class="text-[10px] sm:text-xs text-slate-400 font-semibold">Consulta Pública de Trámite Documentario</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Oficial
            </span>
        </div>
    </header>

    <main class="max-w-4xl w-full mx-auto px-4 py-8 flex-1">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="bg-slate-800 text-white p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-widest text-slate-400 block mb-1">Expediente Registrado</span>
                    <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-white">{{ $expediente['numero_expediente'] }}</h2>
                </div>
                <div class="sm:text-right">
                    <span class="text-[10px] uppercase font-bold tracking-widest text-slate-400 block mb-1">Estado Actual</span>
                    <span class="inline-block px-3 py-1 bg-blue-600/30 text-blue-300 border border-blue-500/40 rounded-lg text-xs font-bold uppercase tracking-wider">
                        {{ $expediente['estado_actual'] }}
                    </span>
                </div>
            </div>

            <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-6 border-b border-slate-100">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Tipo de Trámite</h3>
                    <p class="text-sm font-semibold text-slate-800">{{ $expediente['tipo_tramite'] }}</p>
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Fecha de Ingreso</h3>
                    <p class="text-sm font-semibold text-slate-800">{{ $expediente['fecha_ingreso'] }}</p>
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Remitente</h3>
                    <p class="text-sm font-bold text-slate-800 uppercase">{{ $expediente['solicitante'] }}</p>
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Oficina / Dependencia Actual</h3>
                    <p class="text-sm font-semibold text-slate-800">{{ $expediente['area_actual'] }}</p>
                </div>
                <div class="md:col-span-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Detalle / Asunto</h3>
                    <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-100 italic">{{ $expediente['asunto'] }}</p>
                </div>
            </div>

            <div class="p-6 sm:p-8">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-route text-slate-500"></i> Línea de Tiempo de Movimientos
                </h3>

                @if(count($expediente['movimientos']) === 0)
                    <p class="text-xs text-slate-500">Expediente recién recepcionado en Mesa de Partes.</p>
                @else
                    <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                        @foreach($expediente['movimientos'] as $mov)
                            <div class="relative">
                                <span class="absolute -left-6 top-1 w-4 h-4 rounded-full border-2 border-white bg-slate-600"></span>
                                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/80">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-1">
                                        <p class="text-xs font-bold text-slate-800">{{ $mov['oficina'] }}</p>
                                        <span class="text-[11px] font-semibold text-slate-500">{{ $mov['fecha'] }}</span>
                                    </div>
                                    <span class="inline-block text-[10px] font-bold uppercase tracking-wider text-slate-600 bg-slate-200 px-2 py-0.5 rounded">
                                        {{ $mov['estado'] }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="text-center text-xs text-slate-400 space-y-1">
            <p>La información visualizada refleja el estado administrativo en tiempo real.</p>
            <p>Por razones de seguridad y privacidad, los documentos internos no están disponibles para descarga pública abierta.</p>
        </div>
    </main>

    <footer class="bg-slate-900 text-slate-400 text-center py-4 text-xs border-t border-slate-800">
        <p>&copy; {{ date('Y') }} {{ $expediente['compania_nombre'] }}. Todos los derechos reservados.</p>
    </footer>

</body>
</html>
