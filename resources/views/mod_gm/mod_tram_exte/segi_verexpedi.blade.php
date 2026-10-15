<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $tituloOpcion ?? 'Seguimiento del expediente' }} — SIGTRAM</title>
    @vite(['resources/css/app.css', 'resources/css/tramite-externo/reportes-expedientes.css', 'resources/js/app.js'])
</head>
@php
    $frontendConfig = [
        'routes' => [
            'buscar' => route('mod_gm.segi_verexpedi.buscar', [], false),
            'historico' => route('mod_gm.segi_verexpedi.historico', [], false),
            'cambiar' => route('mod_gm.segi_verexpedi.cambiar_estado', [], false),
            'buscaExpediDeta' => route('tramites.busca_expedi_deta', [], false),
            'descargar' => route('mod_gm.segi_expedi.descargar', ['id' => '__HID__'], false),
        ],
        'estados' => $estados ?? [],
    ];
@endphp
<body class="bg-[#f8fafc] text-gray-800 min-h-screen" x-data="segiVerExpedi()" data-config="{{ json_encode($frontendConfig) }}">

    <div x-show="toast.show" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[200]">
        <div class="backdrop-blur-md text-white px-6 py-2.5 rounded-full shadow-2xl flex items-center gap-3"
             :class="toast.type === 'error' ? 'bg-red-700/90' : 'bg-gray-900/90'">
            <i class="text-xs" :class="toast.type === 'error' ? 'fas fa-exclamation-circle text-red-200' : 'fas fa-check-circle text-emerald-400'"></i>
            <p class="text-[10px] font-black uppercase tracking-widest" x-text="toast.message"></p>
        </div>
    </div>

    <div class="p-3 sm:p-4 w-full max-w-[1600px] mx-auto space-y-3">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-slate-800 text-white px-5 py-2.5">
                <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Trámite Externo</p>
                <h1 class="text-sm font-bold uppercase tracking-wide">{{ $tituloOpcion ?? 'Seguimiento del expediente' }}</h1>
            </div>

            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/70">
                <div class="flex flex-wrap items-end justify-center gap-2">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Palabra</label>
                        <input type="text" x-model="filtros.palabra" maxlength="80" @keydown.enter.prevent="buscarConValidacion()"
                               class="w-48 bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Buscar por</label>
                        <select x-model="filtros.campo" class="w-56 bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none">
                            <option value="p_dto_trmtes.nu_expe_todo">Nº de Expediente</option>
                            <option value="p_usrios.no_crto">Nombre o Razon Social</option>
                            <option value="a_tpo_trmtes.dc_tpo_trmte">Tipo de documento</option>
                            <option value="p_dto_trmtes.de_obse_todo">Observaciones</option>
                        </select>
                    </div>
                    <button type="button" @click="buscarConValidacion()" class="px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                        Buscar
                    </button>
                </div>
            </div>

            <div class="px-4 py-1.5 text-right text-[10px] font-bold text-gray-500" x-show="total > 0">
                <button type="button" @click="pagina--; fetchLista()" :disabled="pagina <= 1" class="disabled:opacity-30">Anterior</button>
                <template x-for="n in paginasVisibles()" :key="n">
                    <button type="button" @click="pagina = n; fetchLista()"
                            class="px-1"
                            :class="n === pagina ? 'text-slate-900 font-black' : 'text-gray-400 hover:text-slate-700'"
                            x-text="n"></button>
                </template>
                <button type="button" @click="pagina++; fetchLista()" :disabled="pagina >= paginas" class="disabled:opacity-30">Siguiente</button>
            </div>

            <div class="relative overflow-x-auto">
                <div x-show="loading" x-cloak class="absolute inset-0 bg-white/70 z-10 flex items-center justify-center">
                    <i class="fas fa-spinner fa-spin text-slate-600"></i>
                </div>
                <table class="w-full text-left border-collapse min-w-[920px] text-[11px]">
                    <thead>
                        <tr class="text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-200 bg-slate-100">
                            <th class="px-2 py-2 w-8"></th>
                            <th class="px-2 py-2">Nº Exp.</th>
                            <th class="px-2 py-2">Nombre o Razón Social</th>
                            <th class="px-2 py-2 text-center">Ubi.</th>
                            <th class="px-2 py-2">F. Entrada</th>
                            <th class="px-2 py-2">Estado</th>
                            <th class="px-2 py-2">Observaciones</th>
                            <th class="px-2 py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, idx) in items" :key="item.id">
                            <tr class="border-b border-gray-100 cursor-pointer"
                                :class="[idx % 2 === 0 ? 'fila-1' : 'fila-2', seleccionado === item.id ? 'ring-1 ring-inset ring-slate-400' : '']"
                                @click="abrirHistorico(item)">
                                <td class="px-2 py-1.5" @click.stop>
                                    <input type="checkbox" class="rounded text-slate-700" :value="item.id" x-model="marcados">
                                </td>
                                <td class="px-2 py-1.5 font-bold text-slate-800 whitespace-nowrap" x-text="item.expediente"></td>
                                <td class="px-2 py-1.5" :title="item.solicitante" x-text="item.solicitante_corto"></td>
                                <td class="px-2 py-1.5 text-center" x-text="item.ubicacion"></td>
                                <td class="px-2 py-1.5 whitespace-nowrap" x-text="item.fecha_entrada"></td>
                                <td class="px-2 py-1.5" @click.stop>
                                    <select class="w-[150px] bg-transparent border border-gray-300 rounded px-1 py-0.5 text-[11px] outline-none"
                                            :value="item.estado_id"
                                            @change="pedirCambioEstado(item, $event.target.value, $event.target)">
                                        <option :value="item.estado_id" x-text="item.estado"></option>
                                        <option :value="item.estado_id">- Otro -</option>
                                        <template x-for="est in estados" :key="est.id">
                                            <option :value="est.id" x-text="est.nombre"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="px-2 py-1.5 text-gray-600" :title="item.observaciones_full" x-text="item.observaciones"></td>
                                <td class="px-2 py-1.5 text-center" @click.stop>
                                    <button type="button" @click="abrirOjo(item)"
                                            :class="item.alerta ? 'text-amber-500 hover:text-amber-600' : 'text-slate-600 hover:text-slate-900'"
                                            :title="item.alerta ? 'Plazo por vencer' : 'Ver expediente'">
                                        <i class="fas" :class="item.alerta ? 'fa-exclamation-triangle' : 'fa-eye'"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && items.length === 0">
                            <td colspan="8" class="px-3 py-6 text-center text-[11px] font-bold text-gray-400">No se encontraron resultados</td>
                        </tr>
                        <tr x-show="items.length > 0" class="bg-slate-50 text-[10px] font-bold text-gray-600">
                            <td class="px-2 py-1.5 text-slate-500"><i class="fas fa-level-up-alt fa-rotate-90"></i></td>
                            <td colspan="7" class="px-2 py-1.5">
                                <button type="button" x-show="!todosMarcados" @click="marcarTodos()" class="uppercase tracking-widest hover:text-slate-800">Todos</button>
                                <button type="button" x-show="todosMarcados" x-cloak @click="desmarcarTodos()" class="uppercase tracking-widest hover:text-slate-800">Ninguno</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="py-2 text-center text-[11px] font-bold">
                <button type="button" @click="verTodo()" class="hover:text-slate-800">Ver todo</button>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-4 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-800 border-b border-gray-100">
                Histórico del Expediente N° <span x-text="historico.expediente || ''"></span>
            </div>
            <div class="overflow-auto custom-scrollbar" style="height: 220px;">
                <table class="w-full text-left border-collapse min-w-[900px] text-[11px]">
                    <thead>
                        <tr class="text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-200 bg-slate-100 sticky top-0">
                            <th class="px-2 py-2 w-[10%]">F. Entrada</th>
                            <th class="px-2 py-2 w-[6%] text-center">T.E</th>
                            <th class="px-2 py-2 w-[22%]">Area</th>
                            <th class="px-2 py-2 w-[12%]">Estado</th>
                            <th class="px-2 py-2">Observaciones</th>
                            <th class="px-2 py-2 w-8 text-center">U</th>
                            <th class="px-2 py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(mov, idx) in historico.rows" :key="mov.id">
                            <tr class="border-b border-gray-100"
                                :class="idx % 2 === 0 ? 'fila-1' : 'fila-2'"
                                :style="mov.color ? ('background-color:' + mov.color) : ''">
                                <td class="px-2 py-1.5 whitespace-nowrap" x-text="mov.fecha"></td>
                                <td class="px-2 py-1.5 text-center lowercase" x-text="mov.ta"></td>
                                <td class="px-2 py-1.5" x-text="mov.area"></td>
                                <td class="px-2 py-1.5" x-text="mov.estado"></td>
                                <td class="px-2 py-1.5" x-text="mov.observaciones"></td>
                                <td class="px-2 py-1.5 text-center">
                                    <i class="fas fa-users text-slate-500" :title="mov.usuario"></i>
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <a x-show="mov.adjunto" x-cloak
                                       :href="routes.descargar.replace('__HID__', mov.id)"
                                       target="_blank" class="text-slate-600 hover:text-slate-900" title="Adjunto">
                                        <i class="fas fa-paperclip"></i>
                                    </a>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="historico.rows.length === 0">
                            <td colspan="7" class="px-3 py-8 text-center text-[11px] text-gray-400">Seleccione un expediente para ver su histórico</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div x-show="modalOjo.show" x-cloak class="modal-overlay" @keydown.escape.window="modalOjo.show = false">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-[520px] h-[80vh] overflow-hidden flex flex-col" @click.outside="modalOjo.show = false">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between shrink-0">
                <h3 class="text-sm font-bold uppercase tracking-wide">Expediente</h3>
                <button type="button" @click="modalOjo.show = false" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <iframe :src="modalOjo.url" class="flex-1 w-full border-0 bg-white"></iframe>
        </div>
    </div>

    <div x-show="modalEstado.show" x-cloak class="modal-overlay" @keydown.escape.window="cancelarEstado()">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-[350px] overflow-hidden" @click.outside="cancelarEstado()">
            <div class="bg-slate-800 text-white px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wide">Cambiar estado del expediente</h3>
                <button type="button" @click="cancelarEstado()" class="w-7 h-7 rounded-lg hover:bg-white/10"><i class="fas fa-times text-xs"></i></button>
            </div>
            <div class="p-4 space-y-3">
                <label class="block text-[11px] font-semibold text-gray-700">Observaciones:</label>
                <textarea x-model="modalEstado.observacion" rows="5"
                          class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-slate-500 outline-none"></textarea>
                <div class="flex justify-center gap-2">
                    <button type="button" @click="confirmarEstado()" class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-lg">Cambiar Estado</button>
                    <button type="button" @click="cancelarEstado()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-[10px] font-black uppercase tracking-widest rounded-lg">Cancelar</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
