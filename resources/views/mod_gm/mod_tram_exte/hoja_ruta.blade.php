<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoja de Ruta - {{ $expediente->nu_expe_todo }}</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body { background-color: #f8fafc; }
        @media print {
            body { background-color: white; }
            .no-print { display: none !important; }
            .print-border { border: 1px solid #e2e8f0 !important; }
            .page-break { page-break-inside: avoid; }
        }
    </style>
</head>
<body class="text-gray-800 antialiased py-8">

    
    <div class="max-w-4xl mx-auto mb-6 flex justify-end no-print">
        <button type="button" data-action="print" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl shadow-md transition-colors flex items-center gap-2 cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Imprimir Hoja de Ruta
        </button>
    </div>

   
    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-200 print-border p-8 md:p-12">
        
        <!-- Encabezado -->
        <div class="border-b-2 border-gray-800 pb-6 mb-8 text-center">
            <h1 class="text-2xl font-black uppercase tracking-widest text-gray-900">Hoja de Ruta del Tramite</h1>
            <h2 class="text-xl font-bold text-blue-600 mt-2">N° {{ $expediente->nu_expe_todo }}</h2>
            <div class="mt-4 flex flex-col md:flex-row justify-center gap-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                <p>Fecha de Ingreso: <span class="text-gray-800 font-bold">{{ \Carbon\Carbon::parse($expediente->fe_ingr_trmte)->format('d/m/Y H:i') }}</span></p>
                <p class="hidden md:block">•</p>
                <p>Solicitante: <span class="text-gray-800 font-bold">{{ $expediente->no_crto }}</span></p>
            </div>
        </div>

        <!-- Tabla de Movimientos -->
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="bg-gray-100/50 border-b-2 border-gray-300">
                    <th class="py-3 px-4 font-black text-gray-500 uppercase tracking-widest text-xs w-12 text-center">N°</th>
                    <th class="py-3 px-4 font-black text-gray-500 uppercase tracking-widest text-xs w-1/3">Destino</th>
                    <th class="py-3 px-4 font-black text-gray-500 uppercase tracking-widest text-xs">Fecha</th>
                    <th class="py-3 px-4 font-black text-gray-500 uppercase tracking-widest text-xs">Estado</th>
                    <th class="py-3 px-4 font-black text-gray-500 uppercase tracking-widest text-xs">Responsable</th>
                    <th class="py-3 px-4 font-black text-gray-500 uppercase tracking-widest text-xs w-24 text-center">Conformidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($historial as $index => $mov)
                <tr class="border-b border-gray-200 page-break">
                    <td class="py-4 px-4 text-center font-bold text-gray-400">{{ $index + 1 }}</td>
                    <td class="py-4 px-4 font-bold text-gray-800">{{ $mov->area }}</td>
                    <td class="py-4 px-4 text-gray-600 text-xs">{{ $mov->fe_entrada }}</td>
                    <td class="py-4 px-4">
                        <span class="px-2 py-1 bg-gray-100 rounded text-xs font-bold text-gray-700">
                            {{ $mov->estado }}
                        </span>
                    </td>
                    <td class="py-4 px-4 text-xs font-semibold text-gray-600">{{ $mov->usuario }}</td>
                    <td class="py-4 px-4 h-16 border-l border-gray-100">
                        <!-- Espacio en blanco para firma o sello -->
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Pie de página -->
        <div class="mt-12 pt-6 border-t border-gray-200 text-center text-xs text-gray-400 font-semibold uppercase tracking-wider">
            Documento generado el {{ date('d/m/Y H:i:s') }}
        </div>
    </div>

</body>
</html>
