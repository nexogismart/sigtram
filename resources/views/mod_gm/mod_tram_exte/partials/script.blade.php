<script>
    function adjuntarSearch() {
        return {
            loading: false,
            modalLoading: false,
            openModal: false,
            totalResults: 0,
            currentPage: 1,
            lastPage: 1,
            selectedItem: null,
            items: [],
            isOnline: navigator.onLine,
            showToast: false,
            toastMessage: '',
            showErrorToast: false,
            toastErrorMessage: '',
            areaCombobox: null,
            tipoCombobox: null,
            filters: {
                nu_expe_todo: '{{ request('nu_expe_todo', '') }}',
                no_crto: '{{ request('no_crto', '') }}',
                cdgo_area: '{{ request('cdgo_area', '') }}',
                cdgo_tpo_trmte: '{{ request('cdgo_tpo_trmte', '') }}',
                fe_inicio: '{{ request('fe_inicio', '') }}',
                fe_fin: '{{ request('fe_fin', '') }}',
                per_page: '{{ request('per_page', '15') }}',
                rango_rapido: '{{ request('rango_rapido', 'todo_el_tiempo') }}'
            },

            init() {
                this.areaCombobox = {
                    open: false,
                    search: '',
                    selectedId: '{{ request('cdgo_area', '') }}',
                    options: @json($areas ?? []).map(a => ({ id: a.cdgo_area, text: a.de_area })),
                    
                    init(parent) {
                        if (this.selectedId) {
                            const selected = this.options.find(opt => opt.id == this.selectedId);
                            if (selected) this.search = selected.text;
                        }
                    },
                    
                    get filteredOptions() {
                        if (!this.search) return this.options;
                        return this.options.filter(opt => 
                            opt.text.toLowerCase().includes(this.search.toLowerCase())
                        );
                    },
                    
                    select(option, parent) {
                        this.selectedId = option.id;
                        this.search = option.text;
                        parent.filters.cdgo_area = option.id;
                        this.open = false;
                    },
                    
                    clear(parent) {
                        this.selectedId = '';
                        this.search = '';
                        parent.filters.cdgo_area = '';
                        this.open = false;
                    },
                    
                    highlight(text) {
                        const escapedText = String(text)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                            
                        if (!this.search) return escapedText;
                        
                        const escapedSearch = String(this.search)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                            
                        const index = escapedText.toLowerCase().indexOf(escapedSearch.toLowerCase());
                        if (index === -1) return escapedText;
                        
                        const originalText = escapedText.substring(index, index + escapedSearch.length);
                        return escapedText.substring(0, index) + '<u class="font-black text-blue-600 bg-blue-50/50 px-0.5 rounded decoration-2">' + originalText + '</u>' + escapedText.substring(index + escapedSearch.length);
                    }
                };

                this.tipoCombobox = {
                    open: false,
                    search: '',
                    selectedId: '{{ request('cdgo_tpo_trmte', '') }}',
                    options: @json($tiposDocumento ?? []).map(t => ({ id: t.cdgo_tpo_trmte, text: t.de_tpo_trmte })),
                    
                    init(parent) {
                        if (this.selectedId) {
                            const selected = this.options.find(opt => opt.id == this.selectedId);
                            if (selected) this.search = selected.text;
                        }
                    },
                    
                    get filteredOptions() {
                        if (!this.search) return this.options;
                        return this.options.filter(opt => 
                            opt.text.toLowerCase().includes(this.search.toLowerCase())
                        );
                    },
                    
                    select(option, parent) {
                        this.selectedId = option.id;
                        this.search = option.text;
                        parent.filters.cdgo_tpo_trmte = option.id;
                        this.open = false;
                    },
                    
                    clear(parent) {
                        this.selectedId = '';
                        this.search = '';
                        parent.filters.cdgo_tpo_trmte = '';
                        this.open = false;
                    },
                    
                    highlight(text) {
                        const escapedText = String(text)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                            
                        if (!this.search) return escapedText;
                        
                        const escapedSearch = String(this.search)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                            
                        const index = escapedText.toLowerCase().indexOf(escapedSearch.toLowerCase());
                        if (index === -1) return escapedText;
                        
                        const originalText = escapedText.substring(index, index + escapedSearch.length);
                        return escapedText.substring(0, index) + '<u class="font-black text-blue-600 bg-blue-50/50 px-0.5 rounded decoration-2">' + originalText + '</u>' + escapedText.substring(index + escapedSearch.length);
                    }
                };

                this.areaCombobox.init(this);
                this.tipoCombobox.init(this);

                const initialData = @json($tramites ?? []);
                if(initialData && initialData.data) {
                    this.items = initialData.data.map(i => ({...i, _uploading: false}));
                    this.totalResults = initialData.total;
                    this.currentPage = initialData.current_page;
                    this.lastPage = initialData.last_page;
                } else {
                    this.fetchResults();
                }
                
                window.addEventListener('online', () => this.isOnline = true);
                window.addEventListener('offline', () => this.isOnline = false);
            },

            debounceSearch: (function() {
                let t; return function() { clearTimeout(t); t = setTimeout(() => this.fetchResults(1), 800); }
            })(),

            hasActiveFilters() { 
                return this.filters.nu_expe_todo || this.filters.no_crto || this.filters.cdgo_area || this.filters.cdgo_tpo_trmte || this.filters.fe_inicio || this.filters.fe_fin; 
            },

            setRangoRapido(rango, autoFetch = true) {
                this.filters.rango_rapido = rango;
                
                if (rango === 'todo_el_tiempo') {
                    this.filters.fe_inicio = '';
                    this.filters.fe_fin = '';
                } else {
                    const hoy = new Date();
                    let inicio = new Date();
                    let fin = new Date();
                    
                    if (rango === 'hoy') {
                        inicio = hoy;
                        fin = hoy;
                    } else if (rango === 'ultimos_7_dias') {
                        inicio.setDate(hoy.getDate() - 7);
                        fin = hoy;
                    } else if (rango === 'este_mes') {
                        inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                        fin = hoy;
                    }
                    
                    this.filters.fe_inicio = this.formatDateISOLocal(inicio);
                    this.filters.fe_fin = this.formatDateISOLocal(fin);
                }
                
                if (autoFetch) {
                    this.fetchResults(1);
                }
            },

            formatDateISOLocal(date) {
                const offset = date.getTimezoneOffset();
                const localDate = new Date(date.getTime() - (offset * 60 * 1000));
                return localDate.toISOString().split('T')[0];
            },

            async fetchResults(page = 1) {
                if (!this.isOnline) return;
                this.loading = true;
                const params = new URLSearchParams();
                Object.keys(this.filters).forEach(k => { if(this.filters[k]) params.append(k, this.filters[k]); });
                if(page > 1) params.append('page', page);
                const baseUrl = '{{ url("mod_gm/mod_tram_exte/segi_adjuntar") }}';
                
                if (window.location.pathname.includes('segi_adjuntar')) {
                    window.history.pushState({}, '', `${baseUrl}?${params.toString()}`);
                }

                try {
                    const res = await fetch(`${baseUrl}?${params.toString()}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.items = data.data.map(i => ({...i, _uploading: false}));
                        this.totalResults = data.total;
                        this.currentPage = data.current_page;
                        this.lastPage = data.last_page;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                } catch (e) { console.error(e); }
                finally { setTimeout(() => this.loading = false, 400); }
            },

            async handleFileUpload(event, item) {
                const file = event.target.files[0];
                if (!file) return;

                item._uploading = true;
                const formData = new FormData();
                formData.append('documento', file);
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                formData.append('_token', token);

                try {
                    const res = await fetch(`{{ url('mod_gm/mod_tram_exte/segi_adjuntar') }}/${item.cdgo_trmte_dcmnto}`, {
                        method: 'POST',
                        body: formData,
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    
                    const data = await res.json();
                    if (data.success) {
                        item.de_urls_imag = data.file;
                        this.displayToast('Archivo guardado: ' + data.file);
                    } else {
                        throw new Error(data.message || 'Error en servidor');
                    }
                } catch (e) {
                    console.error('Upload Error:', e);
                    this.displayErrorToast('Error al subir el archivo');
                } finally {
                    item._uploading = false;
                    event.target.value = '';
                }
            },

            async showDetail(item) {
                this.openModal = true;
                this.modalLoading = true;
                this.selectedItem = null;
                try {
                    const res = await fetch(`{{ route('tramites.adjuntar.detalle') }}?id=${item.cdgo_trmte_dcmnto}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.success) this.selectedItem = data.data;
                } catch (e) { console.error(e); }
                finally { this.modalLoading = false; }
            },

            closeModal() { this.openModal = false; },

            resetFilters() {
                this.filters = {
                    nu_expe_todo: '',
                    no_crto: '',
                    cdgo_area: '',
                    cdgo_tpo_trmte: '',
                    fe_inicio: '',
                    fe_fin: '',
                    per_page: '15',
                    rango_rapido: 'todo_el_tiempo'
                };
                this.areaCombobox.clear(this);
                this.tipoCombobox.clear(this);
                this.fetchResults(1);
            },

            displayToast(msg) {
                this.toastMessage = msg;
                this.showToast = true;
                setTimeout(() => this.showToast = false, 3000);
            },

            displayErrorToast(msg) {
                this.toastErrorMessage = msg;
                this.showErrorToast = true;
                setTimeout(() => this.showErrorToast = false, 3000);
            },

            escapeHTML(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            },

            highlight(text, field = 'nu_expe_todo') {
                const escaped = this.escapeHTML(text);
                const term = this.filters[field];
                if (!term) return escaped;
                const q = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return escaped.replace(new RegExp(q, 'gi'), m => `<mark class="bg-yellow-100 text-gray-900 font-bold p-0.5 rounded">${m}</mark>`);
            },

            getRelativeTime(date) {
                if (!date) return '-';
                const serverDate = new Date(date.includes('Z') ? date : date.replace(' ', 'T') + 'Z');
                const diff = (new Date() - serverDate) / 1000;
                if (diff < 60) return 'Ahora';
                if (diff < 3600) return `Hace ${Math.floor(diff/60)} min`;
                if (diff < 86400) return `Hace ${Math.floor(diff/3600)}h`;
                return this.formatDate(date);
            },

            formatDate(d) { 
                if (!d) return '-';
                const dateObj = new Date(d.includes('Z') ? d : d.replace(' ', 'T') + 'Z');
                if (d.length === 10) {
                    return d.split('-').reverse().join('/');
                }
                return dateObj.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' }); 
            }
        }
    }
</script>
