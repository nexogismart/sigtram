
<div class="bg-gray-50/80 border-b border-gray-200 p-4 lg:p-6 space-y-5">
    
 
    <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between">
        
       
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mr-1">Rango Rápido:</span>
            <div class="inline-flex bg-gray-200/50 p-1 rounded-2xl gap-1 border border-gray-300/40">
                <button @click="setRangoRapido('hoy')" 
                        class="px-4 py-2 text-[10px] font-black uppercase tracking-wider rounded-xl transition-all duration-300 active:scale-95"
                        :class="filters.rango_rapido === 'hoy' ? 'bg-blue-600 text-white shadow-md shadow-blue-100' : 'text-gray-500 hover:text-gray-700 hover:bg-white/40'">
                    Hoy
                </button>
                <button @click="setRangoRapido('ultimos_7_dias')" 
                        class="px-4 py-2 text-[10px] font-black uppercase tracking-wider rounded-xl transition-all duration-300 active:scale-95"
                        :class="filters.rango_rapido === 'ultimos_7_dias' ? 'bg-blue-600 text-white shadow-md shadow-blue-100' : 'text-gray-500 hover:text-gray-700 hover:bg-white/40'">
                    Últimos 7 Días
                </button>
                <button @click="setRangoRapido('este_mes')" 
                        class="px-4 py-2 text-[10px] font-black uppercase tracking-wider rounded-xl transition-all duration-300 active:scale-95"
                        :class="filters.rango_rapido === 'este_mes' ? 'bg-blue-600 text-white shadow-md shadow-blue-100' : 'text-gray-500 hover:text-gray-700 hover:bg-white/40'">
                    Este Mes
                </button>
                <button @click="setRangoRapido('todo_el_tiempo')" 
                        class="px-4 py-2 text-[10px] font-black uppercase tracking-wider rounded-xl transition-all duration-300 active:scale-95"
                        :class="filters.rango_rapido === 'todo_el_tiempo' ? 'bg-blue-600 text-white shadow-md shadow-blue-100' : 'text-gray-500 hover:text-gray-700 hover:bg-white/40'">
                    Todo
                </button>
            </div>
        </div>

     
        <div class="w-full lg:w-96">
            <div class="relative flex items-center bg-white border border-gray-300 rounded-xl px-3 py-2 focus-within:ring-2 focus-within:ring-blue-500 transition-all shadow-sm group">
                <i class="fas fa-search text-gray-400 group-focus-within:text-blue-500 mr-2 transition-colors text-xs"></i>
                <input 
                    type="text" 
                    x-model="filters.nu_expe_todo" 
                    @input="debounceSearch()" 
                    placeholder="N° Expediente (Filtro en tiempo real)..." 
                    class="bg-transparent border-none p-0 focus:ring-0 text-gray-700 text-sm font-bold w-full outline-none placeholder:font-medium placeholder:text-gray-400"
                >
                <button x-show="filters.nu_expe_todo" @click="filters.nu_expe_todo = ''; fetchResults(1);" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        </div>

    </div>

    <div class="pt-4 border-t border-gray-200/80">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
            
            <div class="lg:col-span-3">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Solicitante</label>
                <div class="relative flex items-center bg-white border border-gray-300 rounded-xl px-3 py-2.5 focus-within:ring-2 focus-within:ring-blue-500 transition-all shadow-sm group">
                    <i class="fas fa-user text-gray-400 group-focus-within:text-blue-500 mr-2 transition-colors text-xs"></i>
                    <input 
                        type="text" 
                        x-model="filters.no_crto" 
                        @keydown.enter="fetchResults(1)" 
                        placeholder="Nombre o Razón Social..." 
                        class="bg-transparent border-none p-0 focus:ring-0 text-gray-700 text-sm font-bold w-full outline-none placeholder:font-medium placeholder:text-gray-400"
                    >
                </div>
            </div>

            
            <div class="lg:col-span-3 relative">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Gerencia Destino</label>
                <div class="relative flex items-center bg-white border border-gray-300 rounded-xl px-3 py-2.5 focus-within:ring-2 focus-within:ring-blue-500 transition-all shadow-sm group">
                    <i class="fas fa-building text-gray-400 group-focus-within:text-blue-500 mr-2 transition-colors text-xs"></i>
                    <input 
                        type="text" 
                        x-model="areaCombobox.search" 
                        @focus="areaCombobox.open = true" 
                        @click.outside="
                            areaCombobox.open = false; 
                            const selected = areaCombobox.options.find(o => o.id == areaCombobox.selectedId);
                            areaCombobox.search = selected ? selected.text : '';
                        "
                        placeholder="Buscar área receptora..." 
                        class="bg-transparent border-none p-0 focus:ring-0 text-gray-700 text-sm font-bold w-full outline-none placeholder:font-medium placeholder:text-gray-400"
                    >
                    <button x-show="areaCombobox.selectedId" @click="areaCombobox.clear($data)" class="text-gray-400 hover:text-red-500 mr-1.5">
                        <i class="fas fa-times text-[10px]"></i>
                    </button>
                    <i class="fas fa-chevron-down text-gray-400 text-[10px] pointer-events-none transition-transform duration-300" :class="areaCombobox.open ? 'rotate-180 text-blue-500' : ''"></i>
                </div>

                
                <div 
                    x-show="areaCombobox.open" 
                    x-transition 
                    x-cloak 
                    class="absolute left-0 right-0 mt-2 bg-white border border-gray-200 rounded-2xl shadow-xl z-50 max-h-60 overflow-y-auto custom-scrollbar p-1.5"
                >
                    <div @click="areaCombobox.clear($data)" class="flex items-center px-4 py-2 text-xs font-bold text-gray-400 uppercase tracking-widest hover:bg-gray-50 rounded-xl cursor-pointer">
                        -- Sin selección --
                    </div>
                    <template x-for="opt in areaCombobox.filteredOptions" :key="opt.id">
                        <div 
                            @click="areaCombobox.select(opt, $data)"
                            class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-xl cursor-pointer transition-colors"
                            :class="areaCombobox.selectedId == opt.id ? 'bg-blue-50/70 text-blue-700' : ''"
                        >
                            <span x-html="areaCombobox.highlight(opt.text)"></span>
                        </div>
                    </template>
                    <div x-show="areaCombobox.filteredOptions.length === 0" class="px-4 py-3 text-xs text-gray-400 italic text-center">
                        No se encontraron áreas
                    </div>
                </div>
            </div>

           
            <div class="lg:col-span-3 relative">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Tipo Documento</label>
                <div class="relative flex items-center bg-white border border-gray-300 rounded-xl px-3 py-2.5 focus-within:ring-2 focus-within:ring-blue-500 transition-all shadow-sm group">
                    <i class="fas fa-file-alt text-gray-400 group-focus-within:text-blue-500 mr-2 transition-colors text-xs"></i>
                    <input 
                        type="text" 
                        x-model="tipoCombobox.search" 
                        @focus="tipoCombobox.open = true" 
                        @click.outside="
                            tipoCombobox.open = false; 
                            const selected = tipoCombobox.options.find(o => o.id == tipoCombobox.selectedId);
                            tipoCombobox.search = selected ? selected.text : '';
                        "
                        placeholder="Buscar tipo de documento..." 
                        class="bg-transparent border-none p-0 focus:ring-0 text-gray-700 text-sm font-bold w-full outline-none placeholder:font-medium placeholder:text-gray-400"
                    >
                    <button x-show="tipoCombobox.selectedId" @click="tipoCombobox.clear($data)" class="text-gray-400 hover:text-red-500 mr-1.5">
                        <i class="fas fa-times text-[10px]"></i>
                    </button>
                    <i class="fas fa-chevron-down text-gray-400 text-[10px] pointer-events-none transition-transform duration-300" :class="tipoCombobox.open ? 'rotate-180 text-blue-500' : ''"></i>
                </div>

                
                <div 
                    x-show="tipoCombobox.open" 
                    x-transition 
                    x-cloak 
                    class="absolute left-0 right-0 mt-2 bg-white border border-gray-200 rounded-2xl shadow-xl z-50 max-h-60 overflow-y-auto custom-scrollbar p-1.5"
                >
                    <div @click="tipoCombobox.clear($data)" class="flex items-center px-4 py-2 text-xs font-bold text-gray-400 uppercase tracking-widest hover:bg-gray-50 rounded-xl cursor-pointer">
                        -- Sin selección --
                    </div>
                    <template x-for="opt in tipoCombobox.filteredOptions" :key="opt.id">
                        <div 
                            @click="tipoCombobox.select(opt, $data)"
                            class="flex items-center px-4 py-2 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-700 rounded-xl cursor-pointer transition-colors"
                            :class="tipoCombobox.selectedId == opt.id ? 'bg-blue-50/70 text-blue-700' : ''"
                        >
                            <span x-html="tipoCombobox.highlight(opt.text)"></span>
                        </div>
                    </template>
                    <div x-show="tipoCombobox.filteredOptions.length === 0" class="px-4 py-3 text-xs text-gray-400 italic text-center">
                        No se encontraron tipos
                    </div>
                </div>
            </div>

         
            <div class="lg:col-span-2 grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">F. Inicio</label>
                    <input 
                        type="date" 
                        x-model="filters.fe_inicio" 
                        @change="filters.rango_rapido = 'personalizado'"
                        class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 text-gray-700 text-xs font-bold focus:ring-2 focus:ring-blue-500 outline-none shadow-sm"
                    >
                </div>
                <div>
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">F. Fin</label>
                    <input 
                        type="date" 
                        x-model="filters.fe_fin" 
                        @change="filters.rango_rapido = 'personalizado'"
                        class="w-full bg-white border border-gray-300 rounded-xl px-2 py-2 text-gray-700 text-xs font-bold focus:ring-2 focus:ring-blue-500 outline-none shadow-sm"
                    >
                </div>
            </div>

           
            <div class="lg:col-span-1 flex flex-col gap-2">
                <button @click="fetchResults(1)" :disabled="loading" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-md shadow-blue-100 transition-all flex items-center justify-center gap-2 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-search" x-show="!loading"></i>
                    <i class="fas fa-spinner fa-spin" x-show="loading" x-cloak></i>
                    <span x-text="loading ? '' : 'BUSCAR'"></span>
                </button>
                <button @click="resetFilters()" :disabled="loading" class="w-full py-2 flex items-center justify-center bg-white hover:bg-red-50 text-gray-500 hover:text-red-600 border border-gray-200 rounded-xl shadow-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed text-[9px] font-black uppercase tracking-widest gap-1.5" title="Limpiar Filtros">
                    <i class="fas fa-undo-alt text-[10px]"></i>
                    <span>LIMPIAR</span>
                </button>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100/50" x-show="hasActiveFilters()" x-cloak>
        <template x-if="filters.nu_expe_todo">
            <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-50 text-blue-700 text-[10px] font-black rounded-full border border-blue-100 uppercase tracking-tight">
                EXPEDIENTE: <span x-text="filters.nu_expe_todo"></span>
                <button @click="filters.nu_expe_todo = ''; fetchResults(1);" class="hover:text-red-500 transition-colors"><i class="fas fa-times"></i></button>
            </span>
        </template>
        <template x-if="filters.no_crto">
            <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-50 text-blue-700 text-[10px] font-black rounded-full border border-blue-100 uppercase tracking-tight">
                SOLICITANTE: <span x-text="filters.no_crto"></span>
                <button @click="filters.no_crto = ''; fetchResults(1);" class="hover:text-red-500 transition-colors"><i class="fas fa-times"></i></button>
            </span>
        </template>
        <template x-if="filters.cdgo_area">
            <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-50 text-blue-700 text-[10px] font-black rounded-full border border-blue-100 uppercase tracking-tight">
                DESTINO: <span x-text="areaCombobox.search"></span>
                <button @click="areaCombobox.clear($data); fetchResults(1);" class="hover:text-red-500 transition-colors"><i class="fas fa-times"></i></button>
            </span>
        </template>
        <template x-if="filters.cdgo_tpo_trmte">
            <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-50 text-blue-700 text-[10px] font-black rounded-full border border-blue-100 uppercase tracking-tight">
                DOCUMENTO: <span x-text="tipoCombobox.search"></span>
                <button @click="tipoCombobox.clear($data); fetchResults(1);" class="hover:text-red-500 transition-colors"><i class="fas fa-times"></i></button>
            </span>
        </template>
        <template x-if="filters.fe_inicio || filters.fe_fin">
            <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-50 text-blue-700 text-[10px] font-black rounded-full border border-blue-100 uppercase tracking-tight">
                📅 <span x-text="(filters.fe_inicio ? formatDate(filters.fe_inicio) : '...') + ' - ' + (filters.fe_fin ? formatDate(filters.fe_fin) : '...')"></span>
                <button @click="filters.fe_inicio = ''; filters.fe_fin = ''; filters.rango_rapido = 'todo_el_tiempo'; fetchResults(1);" class="hover:text-red-500 transition-colors"><i class="fas fa-times"></i></button>
            </span>
        </template>
    </div>

</div>
