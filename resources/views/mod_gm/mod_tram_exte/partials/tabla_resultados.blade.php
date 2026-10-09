
<div class="flex-1 relative overflow-hidden flex flex-col bg-gray-50/30">
    
    <div x-show="loading" class="loading-overlay" x-cloak>
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin shadow-xl shadow-blue-100"></div>
            <p class="mt-4 text-xs font-black text-blue-700 uppercase tracking-widest animate-pulse">Consultando base.</p>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto max-h-[600px] custom-scrollbar px-4 pb-4 pt-0 lg:px-6 lg:pb-6 lg:pt-0 transition-all duration-500" :class="loading ? 'blur-[2px]' : ''">
        <table class="w-full text-left border-separate border-spacing-y-3">
            <thead>
                <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                    <th class="sticky top-0 bg-gray-50 z-10 px-6 py-3 border-b border-gray-200">Expediente</th>
                    <th class="sticky top-0 bg-gray-50 z-10 px-6 py-3 border-b border-gray-200 text-center">F. Entrada</th>
                    <th class="sticky top-0 bg-gray-50 z-10 px-6 py-3 border-b border-gray-200 text-center">Área Actual / Destino</th>
                    <th class="sticky top-0 bg-gray-50 z-10 px-6 py-3 border-b border-gray-200">Observaciones</th>
                    <th class="sticky top-0 bg-gray-50 z-10 px-6 py-3 border-b border-gray-200 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
              
                <template x-if="loading && items.length === 0">
                    <template x-for="i in 5" :key="i">
                        <tr class="bg-white/50 animate-pulse">
                            <td class="px-6 py-4 first:rounded-l-2xl"><div class="h-10 bg-gray-100 rounded-lg w-full"></div></td>
                            <td class="px-6 py-4"><div class="h-10 bg-gray-100 rounded-lg w-full"></div></td>
                            <td class="px-6 py-4"><div class="h-10 bg-gray-100 rounded-lg w-full"></div></td>
                            <td class="px-6 py-4"><div class="h-10 bg-gray-100 rounded-lg w-full"></div></td>
                            <td class="px-6 py-4 last:rounded-r-2xl"><div class="h-10 bg-gray-100 rounded-lg w-full"></div></td>
                        </tr>
                    </template>
                </template>

                <template x-if="items.length > 0">
                    <template x-for="item in items" :key="item.cdgo_trmte_dcmnto">
                        <tr class="bg-white hover-card cursor-pointer group" @click="showDetail(item)">
                            <td class="px-6 py-5 first:rounded-l-2xl border-y border-transparent group-hover:border-blue-100">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full shadow-sm" :class="item.de_urls_imag ? 'bg-emerald-500' : 'bg-yellow-500'"></span>
                                        <span class="text-sm font-black text-gray-800" x-html="highlight(item.nu_expe_todo, 'nu_expe_todo')"></span>
                                        <span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-[8px] font-black uppercase rounded tracking-wider border border-blue-100/50" x-text="item.de_tpo_trmte || 'TRAMITE'"></span>
                                    </div>
                                    <span class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-4 truncate max-w-[200px]" :title="item.no_crto" x-html="highlight(item.no_crto, 'no_crto')"></span>
                                </div>
                            </td>
                            <td class="px-6 py-5 border-y border-transparent group-hover:border-blue-100 text-center">
                                <div class="flex flex-col items-center">
                                    <span class="text-sm font-bold text-gray-700" x-text="formatDate(item.fe_ingr_trmte)"></span>
                                    <span class="text-[10px] text-blue-500 font-black uppercase tracking-tight mt-0.5" x-text="getRelativeTime(item.fe_ingr_trmte)"></span>
                                </div>
                            </td>
                            <td class="px-6 py-5 border-y border-transparent group-hover:border-blue-100 text-center">
                                <span class="px-3 py-1 bg-slate-100 text-slate-700 text-[10px] font-black rounded-lg border border-slate-200 uppercase tracking-tight" x-text="item.de_area_destino || item.dc_area || 'Mesa Partes'"></span>
                            </td>
                            <td class="px-6 py-5 border-y border-transparent group-hover:border-blue-100">
                                <p class="text-[11px] text-gray-500 font-medium leading-relaxed max-w-xs line-clamp-2 italic" x-text="item.de_obse_trmte || 'SIN NOTAS'"></p>
                            </td>
                            <td class="px-6 py-5 last:rounded-r-2xl border-y border-transparent group-hover:border-blue-100" @click.stop>
                                <div class="flex items-center justify-center gap-3">
                                    
                                    <button @click="showDetail(item)" class="w-9 h-9 flex items-center justify-center bg-gray-50 hover:bg-blue-600 text-gray-400 hover:text-white rounded-xl transition-all shadow-sm border border-gray-100" title="Ver Detalle">
                                        <i class="fas fa-search-plus text-xs"></i>
                                    </button>

                                    <template x-if="!item.de_urls_imag">
                                        <div class="relative">
                                            <label class="cursor-pointer text-gray-500 hover:text-blue-600 transition-colors flex items-center justify-center gap-1.5 bg-white border border-gray-200 px-3 py-2 rounded-xl shadow-sm text-[10px] font-black uppercase tracking-widest hover:bg-blue-50 hover:border-blue-200" :class="{ 'opacity-50 cursor-wait': item._uploading }">
                                                <i class="fas fa-paperclip" x-show="!item._uploading"></i>
                                                <i class="fas fa-spinner fa-spin text-blue-600" x-show="item._uploading" x-cloak></i>
                                                <span x-text="item._uploading ? 'SUBIENDO' : 'ADJUNTAR'"></span>
                                                <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @change="handleFileUpload($event, item)" :disabled="item._uploading">
                                            </label>
                                        </div>
                                    </template>

                                    <template x-if="item.de_urls_imag">
                                        <div class="flex items-center gap-2">
                                            <a :href="'/storage/adjuntos/' + item.de_urls_imag" target="_blank" class="flex items-center gap-1.5 px-3 py-2 bg-emerald-50 text-emerald-700 text-[10px] font-black rounded-xl border border-emerald-100 uppercase tracking-widest hover:bg-emerald-100 transition-colors shadow-sm" title="Ver Archivo">
                                                <i class="fas fa-check-circle"></i>
                                                LISTO
                                            </a>
                                            <label class="cursor-pointer text-gray-400 hover:text-blue-600 transition-colors w-9 h-9 flex items-center justify-center bg-white border border-gray-200 rounded-xl shadow-sm hover:bg-blue-50 hover:border-blue-200" title="Reemplazar archivo" :class="{ 'opacity-50 cursor-wait': item._uploading }">
                                                <i class="fas fa-sync-alt" x-show="!item._uploading"></i>
                                                <i class="fas fa-spinner fa-spin text-blue-600" x-show="item._uploading" x-cloak></i>
                                                <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" @click="if(!confirm('¿Estás seguro que deseas reemplazar el archivo existente?')) $event.preventDefault();" @change="handleFileUpload($event, item)" :disabled="item._uploading">
                                            </label>
                                        </div>
                                    </template>

                                </div>
                            </td>
                        </tr>
                    </template>
                </template>
            </tbody>
        </table>

       
        <div x-show="items.length === 0 && !loading" x-cloak class="py-24 flex flex-col items-center justify-center text-gray-400">
            <div class="w-20 h-20 bg-white rounded-3xl flex items-center justify-center mb-6 border border-gray-100 shadow-sm">
                <i class="fas fa-folder-open text-3xl text-gray-200"></i>
            </div>
            <p class="text-sm font-black text-gray-500 uppercase tracking-[0.2em]">No se encontraron registros</p>
        </div>
    </div>

    
    <footer class="p-5 bg-white border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4" x-show="items.length > 0" x-cloak>
        <div class="flex items-center gap-6">
            <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                Página <span class="text-gray-800" x-text="currentPage"></span> de <span x-text="lastPage"></span> — <span x-text="totalResults"></span> TOTAL
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[9px] font-black text-gray-400 uppercase">Ver:</span>
                <select x-model="filters.per_page" @change="fetchResults(1)" :disabled="loading" class="bg-gray-50 border border-gray-200 text-gray-600 text-[10px] font-bold rounded-lg px-2 py-1 outline-none focus:ring-1 focus:ring-blue-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2">
            <button @click="if(currentPage > 1) fetchResults(currentPage - 1)" 
                    :disabled="currentPage === 1 || loading"
                    :class="(currentPage === 1 || loading) ? 'opacity-40 grayscale cursor-not-allowed' : 'hover:bg-blue-50 hover:text-blue-600 active:scale-95 shadow-sm'"
                    class="px-4 py-2 bg-white border border-gray-200 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-600 transition-all shadow-sm">
                Anterior
            </button>
            <button @click="if(currentPage < lastPage) fetchResults(currentPage + 1)" 
                    :disabled="currentPage === lastPage || loading"
                    :class="(currentPage === lastPage || loading) ? 'opacity-40 grayscale cursor-not-allowed' : 'hover:bg-blue-700 active:scale-95 shadow-md shadow-blue-100'"
                    class="px-4 py-2 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md">
                Siguiente
            </button>
        </div>
    </footer>
</div>
