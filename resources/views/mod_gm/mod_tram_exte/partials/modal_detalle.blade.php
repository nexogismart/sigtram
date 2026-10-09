
<div x-show="openModal" x-cloak class="fixed inset-0 z-[150] overflow-hidden flex items-center justify-center p-4 sm:p-6" x-trap.noscroll.inert="openModal">
    <div x-show="openModal" x-transition.opacity @click="closeModal()" class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>

    <div x-show="openModal" x-transition.scale class="relative bg-white rounded-3xl shadow-2xl overflow-hidden max-w-4xl w-full border border-gray-100 flex flex-col max-h-full">
     
        <div x-show="modalLoading" class="absolute inset-0 bg-white/90 backdrop-blur-sm z-[160] flex items-center justify-center">
            <div class="flex flex-col items-center">
                <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                <p class="text-[10px] font-black text-blue-700 uppercase tracking-widest animate-pulse mt-4">Obteniendo Historial...</p>
            </div>
        </div>

       
        <div class="bg-gray-50/80 px-6 sm:px-8 py-6 border-b border-gray-200 flex items-start justify-between shrink-0">
            <div class="flex gap-5 items-center">
                <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-blue-200 shrink-0">
                    <i class="fas fa-file-invoice text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-gray-800 uppercase tracking-tight" x-text="'EXPEDIENTE ' + (selectedItem?.expediente?.nu_expe_todo || '')"></h3>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 mt-1">
                        <p class="text-[10px] text-blue-600 font-black uppercase tracking-widest bg-blue-50 px-2.5 py-1 rounded-md" x-text="selectedItem?.expediente?.de_tpo_trmte || 'Documento'"></p>
                        <p class="text-[11px] text-gray-500 font-bold flex items-center gap-1.5"><i class="far fa-calendar-alt text-gray-400"></i> Ingresado: <span x-text="formatDate(selectedItem?.expediente?.fe_ingr_trmte)"></span></p>
                    </div>
                </div>
            </div>
            <button @click="closeModal()" class="w-10 h-10 bg-white hover:bg-red-50 text-gray-400 hover:text-red-500 border border-gray-200 rounded-xl transition-all flex items-center justify-center shadow-sm shrink-0">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

      
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 sm:p-8 flex flex-col lg:flex-row gap-8">
            
            
            <div class="lg:w-1/3 space-y-6">
                <div class="bg-blue-50/50 rounded-2xl p-5 border border-blue-100/50">
                    <h4 class="text-[10px] font-black text-blue-800 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="fas fa-user-circle"></i> Solicitante</h4>
                    <div class="space-y-4">
                        <div>
                            <p class="text-[9px] text-gray-400 uppercase font-black mb-1">Nombre / Razón Social</p>
                            <p class="text-sm font-bold text-gray-800" x-text="selectedItem?.expediente?.no_crto || '-'"></p>
                        </div>
                        <div>
                            <p class="text-[9px] text-gray-400 uppercase font-black mb-1">Doc. Identidad</p>
                            <p class="text-xs font-bold text-gray-700 bg-white px-3 py-1.5 rounded-lg border border-gray-100 inline-block" x-text="selectedItem?.expediente?.nu_docu || '-'"></p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-2xl p-5 border border-gray-100">
                    <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="fas fa-align-left"></i> Observaciones Generales</h4>
                    <p class="text-[11px] text-gray-600 leading-relaxed italic" x-text="selectedItem?.expediente?.de_obse_todo || 'Ninguna observación registrada al ingresar el expediente.'"></p>
                </div>
            </div>

            <div class="lg:w-2/3">
                <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-6 flex items-center gap-2 pb-2 border-b border-gray-100">
                    <i class="fas fa-history"></i> Historial de Movimientos
                </h4>
                
                <div class="space-y-0 relative before:absolute before:inset-y-0 before:left-[23px] before:w-0.5 before:bg-gray-100">
                    <template x-for="(mov, index) in (selectedItem?.historial || [])" :key="mov.cdgo_trmte_dcmnto">
                        <div class="relative pl-14 py-4 group">
                            
                            <div class="absolute left-0 top-5 w-12 flex justify-center">
                                <div class="w-10 h-10 rounded-full border-[3px] border-white flex items-center justify-center shadow-sm z-10 transition-colors" 
                                     :class="index === 0 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-400 group-hover:bg-blue-100 group-hover:text-blue-600'">
                                    <i class="fas" :class="index === 0 ? 'fa-map-marker-alt' : 'fa-check text-[10px]'"></i>
                                </div>
                            </div>

                        
                            <div class="bg-white border rounded-2xl p-4 sm:p-5 transition-shadow"
                                 :class="index === 0 ? 'border-blue-200 shadow-md shadow-blue-50' : 'border-gray-100 shadow-sm group-hover:shadow-md'">
                                
                                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest" :class="index === 0 ? 'text-blue-600' : 'text-gray-400'">
                                            Paso <span x-text="selectedItem.historial.length - index"></span>
                                        </p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <h5 class="text-sm font-bold text-gray-800" x-text="mov.area_destino || 'Recepción'"></h5>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-2.5 py-1 text-[9px] font-black uppercase rounded-md border"
                                              :class="index === 0 ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-gray-50 text-gray-500 border-gray-200'"
                                              x-text="mov.estado || 'PENDIENTE'"></span>
                                        <p class="text-[10px] font-bold text-gray-400 mt-1" x-text="formatDate(mov.fe_trmte_dcmnto)"></p>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row sm:items-center gap-4 text-[11px]">
                                    <div class="flex-1" x-show="mov.area_origen">
                                        <span class="text-gray-400 font-medium">Enviado por:</span> <span class="font-bold text-gray-700" x-text="mov.area_origen"></span>
                                    </div>
                                    
                                
                                    <template x-if="mov.de_urls_imag">
                                        <a :href="'/storage/adjuntos/' + mov.de_urls_imag" target="_blank" 
                                           class="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg border border-emerald-100 transition-colors w-fit">
                                            <i class="fas fa-paperclip"></i>
                                            <span class="font-bold">Ver Adjunto</span>
                                        </a>
                                    </template>
                                </div>

                                <template x-if="mov.observacion">
                                    <div class="mt-3 bg-gray-50 p-3 rounded-xl border border-gray-100">
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Nota:</p>
                                        <p class="text-[11px] text-gray-600 italic" x-text="mov.observacion"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    
                    
                    <template x-if="(!selectedItem?.historial || selectedItem.historial.length === 0)">
                        <div class="pl-14 py-8 text-center">
                            <p class="text-xs font-black text-gray-400 uppercase tracking-widest">No hay historial disponible</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    
        <div class="bg-gray-50 px-6 sm:px-8 py-5 border-t border-gray-200 flex justify-end shrink-0 rounded-b-3xl">
            <button @click="closeModal()" class="px-8 py-2.5 bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-black transition-all shadow-md active:scale-95">Cerrar Historial</button>
        </div>
    </div>
</div>
