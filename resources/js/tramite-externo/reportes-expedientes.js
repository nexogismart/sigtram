/**
 * Componentes Alpine para reportes y listados interactivos de Trámite Externo
 */
import { readRouteConfig } from '../shared/route-config.js';

export function createListaExpediTodoComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('body');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const routes = config.routes || {};

    return {
        loading: false,
        items: [],
        total: 0,
        pagina: 1,
        paginas: 1,
        toast: { show: false, message: '', type: 'success' },
        filtros: {
            modo: 'generico',
            palabra: '',
            campo: 'p_dto_trmtes.nu_expe_todo',
            fecha: '',
            desde: '',
            hasta: '',
        },
        modalOjo: { show: false, url: '' },
        modalRuta: { show: false, url: '' },
        modalPrint: { show: false, rows: [] },

        init() { this.fetchLista(); },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },
        payload() {
            return { ...this.filtros, page: this.pagina };
        },
        verTodo() {
            this.filtros.palabra = '';
            this.filtros.fecha = '';
            this.filtros.desde = '';
            this.filtros.hasta = '';
            this.filtros.modo = 'generico';
            this.pagina = 1;
            this.fetchLista();
        },
        paginasVisibles() {
            const tam = 12;
            const seccion = Math.floor((this.pagina - 1) / tam);
            const inicio = seccion * tam + 1;
            const final = Math.min(inicio + tam - 1, this.paginas);
            const arr = [];
            for (let i = inicio; i <= final; i++) arr.push(i);
            return arr;
        },
        abrirOjo(item) {
            this.modalOjo.url = routes.buscaExpediDeta + '?cdgo_tram=' + item.id;
            this.modalOjo.show = true;
        },
        abrirHojaRuta(id) {
            this.modalRuta.url = routes.hojaRuta.replace('__HID__', id);
            this.modalRuta.show = true;
        },
        async fetchLista() {
            this.loading = true;
            try {
                const res = await fetch(routes.buscar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.payload()),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.items = data.data || [];
                    this.total = data.total || 0;
                    this.pagina = data.current_page || 1;
                    this.paginas = data.last_page || 1;
                } else {
                    this.showToast(data.message || 'No se pudo cargar el listado.', 'error');
                }
            } catch (e) {
                this.showToast('Error al cargar expedientes.', 'error');
            } finally {
                this.loading = false;
            }
        },
        async abrirImprimir() {
            try {
                const res = await fetch(routes.imprimir, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.payload()),
                });
                const data = await res.json();
                this.modalPrint = { show: true, rows: data.data || [] };
            } catch (e) {
                this.showToast('No se pudo generar la impresión.', 'error');
            }
        },
    };
}

export function createListaReporAreaComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('body');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const routes = config.routes || {};

    return {
        loading: false,
        items: [],
        total: 0,
        pagina: 1,
        paginas: 1,
        toast: { show: false, message: '', type: 'success' },
        filtros: {
            bandeja: '1',
            modo: 'generico',
            palabra: '',
            campo: 'p_dto_trmtes.nu_expe_todo',
            fecha: '',
            desde: '',
            hasta: '',
        },
        modalOjo: { show: false, url: '' },
        modalRuta: { show: false, url: '' },

        init() { this.fetchLista(); },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },
        verTodo() {
            this.filtros.palabra = '';
            this.filtros.fecha = '';
            this.filtros.desde = '';
            this.filtros.hasta = '';
            this.filtros.modo = 'generico';
            this.pagina = 1;
            this.fetchLista();
        },
        paginasVisibles() {
            const tam = 12;
            const seccion = Math.floor((this.pagina - 1) / tam);
            const inicio = seccion * tam + 1;
            const final = Math.min(inicio + tam - 1, this.paginas);
            const arr = [];
            for (let i = inicio; i <= final; i++) arr.push(i);
            return arr;
        },
        abrirOjo(item) {
            this.modalOjo.url = routes.buscaExpediDeta + '?cdgo_tram=' + item.id;
            this.modalOjo.show = true;
        },
        abrirHojaRuta(id) {
            this.modalRuta.url = routes.hojaRuta.replace('__HID__', id);
            this.modalRuta.show = true;
        },
        async fetchLista() {
            this.loading = true;
            try {
                const res = await fetch(routes.buscar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ...this.filtros, page: this.pagina }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.items = data.data || [];
                    this.total = data.total || 0;
                    this.pagina = data.current_page || 1;
                    this.paginas = data.last_page || 1;
                } else {
                    this.showToast(data.message || 'No se pudo cargar la lista.', 'error');
                }
            } catch (e) {
                this.showToast('Error al cargar expedientes.', 'error');
            } finally {
                this.loading = false;
            }
        },
    };
}

export function createListaReporteComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('body');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const routes = config.routes || {};

    return {
        loading: false,
        items: [],
        total: 0,
        pagina: 1,
        paginas: 1,
        toast: { show: false, message: '', type: 'success' },
        filtros: {
            modo: 'generico',
            palabra: '',
            campo: 'p_dto_trmtes.nu_expe_todo',
            fecha: '',
            desde: '',
            hasta: '',
            palabra1: '',
            palabra2: '',
            excluir: false,
            simple: false,
        },
        modalOjo: { show: false, url: '' },
        modalRuta: { show: false, url: '' },
        modalPrint: { show: false, rows: [], usuario: '', fecha: '' },

        init() { this.fetchLista(); },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },
        payload() {
            return { ...this.filtros, page: this.pagina };
        },
        verTodo() {
            this.filtros.palabra = '';
            this.filtros.fecha = '';
            this.filtros.desde = '';
            this.filtros.hasta = '';
            this.filtros.palabra1 = '';
            this.filtros.palabra2 = '';
            this.filtros.excluir = false;
            this.filtros.simple = false;
            this.filtros.modo = 'generico';
            this.pagina = 1;
            this.fetchLista();
        },
        paginasVisibles() {
            const tam = 12;
            const seccion = Math.floor((this.pagina - 1) / tam);
            const inicio = seccion * tam + 1;
            const final = Math.min(inicio + tam - 1, this.paginas);
            const arr = [];
            for (let i = inicio; i <= final; i++) arr.push(i);
            return arr;
        },
        abrirOjo(item) {
            let url = routes.buscaExpedi + '?cdgo_tram=' + item.id;
            if (item.anex === 1) url += '&anex=1';
            this.modalOjo.url = url;
            this.modalOjo.show = true;
        },
        abrirHojaRuta(id) {
            this.modalRuta.url = routes.hojaRuta.replace('__HID__', id);
            this.modalRuta.show = true;
        },
        async fetchLista() {
            this.loading = true;
            try {
                const res = await fetch(routes.buscar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.payload()),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.items = data.data || [];
                    this.total = data.total || 0;
                    this.pagina = data.current_page || 1;
                    this.paginas = data.last_page || 1;
                } else {
                    this.showToast(data.message || 'No se pudo cargar el reporte.', 'error');
                }
            } catch (e) {
                this.showToast('Error al cargar expedientes.', 'error');
            } finally {
                this.loading = false;
            }
        },
        async abrirImprimir() {
            try {
                const res = await fetch(routes.imprimir, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.payload()),
                });
                const data = await res.json();
                this.modalPrint = {
                    show: true,
                    rows: data.data || [],
                    usuario: data.usuario || '',
                    fecha: data.fecha || '',
                };
            } catch (e) {
                this.showToast('No se pudo generar la impresión.', 'error');
            }
        },
    };
}

export function createNotificarPlataformaComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('body');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const routes = config.routes || {};

    return {
        loading: false,
        guardando: false,
        items: [],
        total: 0,
        pagina: 1,
        paginas: 1,
        toast: { show: false, message: '', type: 'success' },
        filtros: { modo: 'generico', criterio: '', campo: 'p_dto_trmtes.nu_expe_todo', desde: '', hasta: '' },
        modal: { show: false, errors: [] },
        activo: {},
        form: { fecha: '', observaciones: '', archivo: null, archivoNombre: '' },

        init() { this.fetchLista(); },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 4000);
        },
        verTodo() {
            this.filtros.criterio = '';
            this.filtros.desde = '';
            this.filtros.hasta = '';
            this.pagina = 1;
            this.fetchLista();
        },
        abrirHojaRuta(id) {
            window.open(routes.hojaRuta.replace('__HID__', id), 'ventana1', 'scrollbars=auto,width=770,height=310');
        },
        abrirBusca(item) {
            window.open(routes.buscaExpediDeta + '?cdgo_tram=' + item.cdgo_dto_trmte, 'ventana2', 'scrollbars=auto,width=470,height=410');
        },
        abrirNotificar(item) {
            const now = new Date();
            const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            this.activo = item;
            this.form = {
                fecha: local,
                observaciones: 'SE LE ENTREGA EL EXPEDIENTE A LA SR(A):     CON DNI:',
                archivo: null,
                archivoNombre: '',
            };
            this.modal.errors = [];
            this.modal.show = true;
        },
        cerrarModal() {
            if (this.guardando) return;
            this.modal.show = false;
        },
        onArchivo(e) {
            const file = e.target.files && e.target.files[0];
            if (file) {
                this.form.archivo = file;
                this.form.archivoNombre = file.name;
            } else {
                this.form.archivo = null;
                this.form.archivoNombre = '';
            }
        },
        async enviarNotificar() {
            this.guardando = true;
            this.modal.errors = [];
            try {
                const fd = new FormData();
                fd.append('fecha', this.form.fecha);
                fd.append('observaciones', this.form.observaciones);
                if (this.form.archivo) {
                    fd.append('archivo', this.form.archivo);
                }
                const url = routes.notificar.replace('__ID__', this.activo.cdgo_dto_trmte);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showToast(data.message || 'Expediente notificado correctamente.');
                    this.modal.show = false;
                    this.fetchLista();
                } else {
                    this.modal.errors = data.errors || [data.message || 'Error al notificar'];
                }
            } catch (e) {
                this.modal.errors = ['Error de conexión al notificar.'];
            } finally {
                this.guardando = false;
            }
        },
        paginasVisibles() {
            const tam = 12;
            const seccion = Math.floor((this.pagina - 1) / tam);
            const inicio = seccion * tam + 1;
            const final = Math.min(inicio + tam - 1, this.paginas);
            const arr = [];
            for (let i = inicio; i <= final; i++) arr.push(i);
            return arr;
        },
        async fetchLista() {
            this.loading = true;
            try {
                const res = await fetch(routes.tabla, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        ...this.filtros,
                        page: this.pagina,
                    }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.items = data.data || [];
                    this.total = data.total || 0;
                    this.pagina = data.current_page || 1;
                    this.paginas = data.last_page || 1;
                } else {
                    this.showToast(data.message || 'No se pudo cargar la tabla.', 'error');
                }
            } catch (e) {
                this.showToast('Error al cargar datos.', 'error');
            } finally {
                this.loading = false;
            }
        },
    };
}

export function createRedirigirComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('body');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const gerenciasMeta = config.gerencias || [];
    const routes = config.routes || {};

    return {
        loading: false,
        guardando: false,
        guardandoAnular: false,
        items: [],
        selectedIds: [],
        total: 0,
        pagina: 1,
        paginas: 1,
        toast: { show: false, message: '', type: 'success' },
        filtros: { palabra: '', buscar_por: 'p_dto_trmtes.nu_expe_todo', fecha: '' },
        subgerencias: [],
        modalRedirigir: { show: false, errors: [] },
        formRed: { gerencia: '0', subgerencia: '0', usar_sub: false, observaciones: 'Sin novedades para reenviar', fecha: '' },
        modalAnular: { show: false, errors: [], id: null, expediente: '', motivo: '' },
        modalOjo: { show: false, url: '' },
        modalPrint: { show: false, areas: [], criterio: '', fecha: '' },

        init() { this.fetchLista(); },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 4000);
        },
        payloadFiltros() {
            return {
                palabra: this.filtros.palabra,
                buscar_por: this.filtros.buscar_por,
                fecha: this.filtros.fecha,
            };
        },
        buscarConValidacion() {
            if (!this.filtros.palabra && !this.filtros.fecha) {
                this.showToast('Debe especificar un criterio de busqueda', 'error');
                return;
            }
            this.pagina = 1;
            this.fetchLista();
        },
        verTodo() {
            this.filtros.palabra = '';
            this.filtros.fecha = '';
            this.pagina = 1;
            this.fetchLista();
        },
        paginasVisibles() {
            const tam = 15;
            const seccion = Math.floor((this.pagina - 1) / tam);
            const inicio = seccion * tam + 1;
            const final = Math.min(inicio + tam - 1, this.paginas);
            const arr = [];
            for (let i = inicio; i <= final; i++) arr.push(i);
            return arr;
        },
        toggleId(id) {
            const n = Number(id);
            if (this.selectedIds.includes(n)) {
                this.selectedIds = this.selectedIds.filter(x => x !== n);
            } else {
                this.selectedIds.push(n);
            }
        },
        marcarTodos() {
            this.selectedIds = this.items.map(i => Number(i.cdgo_dto_trmte));
        },
        async fetchLista() {
            this.loading = true;
            this.selectedIds = [];
            try {
                const res = await fetch(routes.buscar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        ...this.payloadFiltros(),
                        page: this.pagina,
                        per_page: 15,
                    }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.items = data.data || [];
                    this.total = data.total || 0;
                    this.pagina = data.current_page || 1;
                    this.paginas = data.last_page || 1;
                } else {
                    this.showToast(data.message || 'No se pudo cargar la bandeja.', 'error');
                }
            } catch (e) {
                this.showToast('Error al cargar expedientes.', 'error');
            } finally {
                this.loading = false;
            }
        },
        abrirOjo(item) {
            this.modalOjo.url = routes.buscaExpedi + '?cdgo_tram=' + item.cdgo_dto_trmte + '&anex=1';
            this.modalOjo.show = true;
        },
        abrirAnular(item) {
            this.modalAnular = {
                show: true,
                errors: [],
                id: item.cdgo_trmte_dcmnto,
                expediente: item.nu_expe_todo,
                motivo: '',
            };
        },
        cerrarAnular() {
            if (this.guardandoAnular) return;
            this.modalAnular.show = false;
        },
        abrirRedirigir() {
            if (this.selectedIds.length === 0) {
                this.showToast('Debe seleccionar al menos un Documento', 'error');
                return;
            }
            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            this.formRed = {
                gerencia: '0',
                subgerencia: '0',
                usar_sub: false,
                observaciones: 'Sin novedades para reenviar',
                fecha: pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear() + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()),
            };
            this.subgerencias = [];
            this.modalRedirigir = { show: true, errors: [] };
        },
        cerrarRedirigir() {
            if (this.guardando) return;
            this.modalRedirigir.show = false;
        },
        gerenciaInPart() {
            const g = gerenciasMeta.find(x => String(x.cdgo_area) === String(this.formRed.gerencia));
            return g ? String(g.in_part) : '0';
        },
        async onGerenciaChange() {
            this.formRed.subgerencia = '0';
            this.subgerencias = [];
            this.formRed.usar_sub = false;
            const gid = this.formRed.gerencia;
            if (!gid || gid === '0') return;
            try {
                const res = await fetch(routes.subgerencias.replace('__GID__', gid), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.subgerencias = Array.isArray(data) ? data : [];
                if (this.subgerencias.length > 0 && this.gerenciaInPart() === '1') {
                    this.formRed.usar_sub = true;
                }
            } catch (e) {
                this.subgerencias = [];
            }
        },
        async enviarRedirigir() {
            if (this.formRed.gerencia === '0' || !this.formRed.gerencia) {
                this.modalRedirigir.errors = ['Debe especificar la Gerencia'];
                return;
            }
            if (this.guardando) return;
            this.guardando = true;
            this.modalRedirigir.errors = [];
            try {
                const res = await fetch(routes.derivar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        ids: this.selectedIds,
                        cdgo_gerencia: this.formRed.gerencia,
                        cdgo_subgerencia: this.formRed.subgerencia,
                        usar_subgerencia: this.formRed.usar_sub ? 1 : 0,
                        observacion: this.formRed.observaciones,
                    }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.showToast(data.message || 'Los expedientes fueron reedirigidos');
                    this.modalRedirigir.show = false;
                    await this.fetchLista();
                } else if (data.errors) {
                    this.modalRedirigir.errors = Object.values(data.errors).flat();
                } else {
                    this.modalRedirigir.errors = [data.message || 'No se pudo redirigir.'];
                }
            } catch (e) {
                this.modalRedirigir.errors = ['Error de red al redirigir.'];
            } finally {
                this.guardando = false;
            }
        },
        async enviarAnular() {
            const motivo = (this.modalAnular.motivo || '').trim();
            if (motivo === '') {
                this.modalAnular.errors = ['Debe especificar el motivo'];
                return;
            }
            if (!confirm('Esta seguro de anular el expediente ' + this.modalAnular.expediente + '?')) {
                return;
            }
            if (this.guardandoAnular) return;
            this.guardandoAnular = true;
            this.modalAnular.errors = [];
            try {
                const res = await fetch(routes.anular, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        id: this.modalAnular.id,
                        motivo: this.modalAnular.motivo,
                        expediente: this.modalAnular.expediente,
                    }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.showToast(data.message || 'El trámite fue anulado');
                    this.modalAnular.show = false;
                    await this.fetchLista();
                } else if (data.errors) {
                    this.modalAnular.errors = Object.values(data.errors).flat();
                } else {
                    this.modalAnular.errors = [data.message || 'No se pudo anular.'];
                }
            } catch (e) {
                this.modalAnular.errors = ['Error de red al anular.'];
            } finally {
                this.guardandoAnular = false;
            }
        },
        async abrirImprimir() {
            try {
                const res = await fetch(routes.imprimir, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.payloadFiltros()),
                });
                const data = await res.json();
                this.modalPrint = {
                    show: true,
                    areas: data.areas || [],
                    criterio: data.criterio || this.filtros.palabra,
                    fecha: data.fecha || this.filtros.fecha,
                };
            } catch (e) {
                this.showToast('No se pudo generar la impresión.', 'error');
            }
        },
    };
}

export function createReporteAreaExternaComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('body');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const routes = config.routes || {};

    return {
        loading: false,
        filas: [],
        totales: { digitalizar: 0, por_recibir: 0, recibidos: 0, derivados: 0, finalizados: 0, total_doc: 0, porcentaje: 0, criticos: 0 },
        filtros: {
            desde: config.desde || new Date().toISOString().slice(0, 8) + '01',
            hasta: config.hasta || new Date().toISOString().slice(0, 10),
        },
        expanded: { id: null, label: '', rows: [] },
        toast: { show: false, message: '', type: 'success' },
        modalOjo: { show: false, url: '' },

        init() { this.buscar(); },

        fmt(n) {
            return Number(n || 0).toLocaleString('es-PE');
        },
        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },
        adjuntoUrl(nombre) {
            return (routes.adjunto || '').replace('__NOM__', encodeURIComponent(nombre));
        },
        abrirOjo(item) {
            this.modalOjo.url = routes.buscaExpediDeta + '?cdgo_tram=' + item.id;
            this.modalOjo.show = true;
        },
        async buscar() {
            this.loading = true;
            this.expanded = { id: null, label: '', rows: [] };
            try {
                const res = await fetch(routes.resumen, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tipo: 'area',
                        desde: this.filtros.desde,
                        hasta: this.filtros.hasta,
                    }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.filas = data.data || [];
                    this.totales = data.totales || this.totales;
                } else {
                    this.showToast(data.message || 'No se pudo generar el reporte.', 'error');
                }
            } catch (e) {
                this.showToast('Error al cargar el reporte.', 'error');
            } finally {
                this.loading = false;
            }
        },
        async toggleDetalle(row, categoria, label) {
            if (this.expanded.id === row.id && this.expanded.label === label) {
                this.expanded = { id: null, label: '', rows: [] };
                return;
            }
            this.expanded = { id: row.id, label, rows: [] };
            try {
                const res = await fetch(routes.detalle, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tipo: 'area',
                        categoria,
                        id: row.id,
                        desde: this.filtros.desde,
                        hasta: this.filtros.hasta,
                    }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.expanded.rows = data.data || [];
                } else {
                    this.showToast(data.message || 'No se pudo cargar el detalle.', 'error');
                }
            } catch (e) {
                this.showToast('Error al cargar el detalle.', 'error');
            }
        },
    };
}

export function createArchivoExpedientesComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('body');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const endpoints = config.endpoints || {};

    return {
        endpoints,
        csrf,
        bandeja: 'finalizados',
        loading: false,
        items: [],
        selectedIds: [],
        counts: { finalizados: 0, archivados: 0, prestados: 0 },
        pagination: { current_page: 1, last_page: 1, per_page: 10, total: 0 },
        goPage: 1,
        filters: {
            campo: 'expediente',
            criterio: '',
            desde: config.desde || '',
            hasta: config.hasta || '',
            per_page: 10,
        },
        searchFields: config.searchFields || {},
        toast: { show: false, message: '', type: 'info' },
        archiveModal: { show: false, saving: false, observaciones: 'SE ARCHIVA SIN NOVEDAD' },
        notifyModal: { show: false, saving: false, documento: null, observaciones: 'SE LE ENTREGA EL EXPEDIENTE AL ADMINISTRADO' },
        loanModal: { show: false, saving: false, observaciones: 'SE PRESTA A: ' },
        detailModal: { show: false, loading: false, data: null, historial: [] },
        matrix: { loading: false, items: [] },
        tabs: [
            { key: 'finalizados', label: 'Finalizados', icon: 'fas fa-check-double' },
            { key: 'archivados', label: 'Archivados', icon: 'fas fa-box-archive' },
            { key: 'prestados', label: 'Prestados', icon: 'fas fa-share-from-square' },
            { key: 'matriz', label: 'Matriz', icon: 'fas fa-border-all' },
        ],
        metricCards: [
            { key: 'finalizados', label: 'Finalizados', icon: 'fas fa-check-double', iconClass: 'bg-emerald-50 text-emerald-600' },
            { key: 'archivados', label: 'Archivados', icon: 'fas fa-box-archive', iconClass: 'bg-blue-50 text-blue-600' },
            { key: 'prestados', label: 'Prestados', icon: 'fas fa-share-from-square', iconClass: 'bg-amber-50 text-amber-600' },
        ],
        get isSelectableTab() {
            return this.bandeja !== 'matriz';
        },
        get canArchive() {
            return ['finalizados', 'prestados'].includes(this.bandeja) && this.selectedIds.length > 0 && !this.loading;
        },
        get canNotify() {
            return this.bandeja === 'finalizados' && this.selectedIds.length > 0 && !this.loading;
        },
        get canLoan() {
            return this.bandeja === 'archivados' && this.selectedIds.length > 0 && !this.loading;
        },
        get activeTabDescription() {
            const text = {
                finalizados: 'Expedientes listos para archivo',
                archivados: 'Expedientes archivados disponibles para prestamo',
                prestados: 'Expedientes prestados pendientes de retorno',
                matriz: 'Control visual de archivadores fisicos',
            };
            return text[this.bandeja] || '';
        },
        get paginationFrom() {
            if (this.pagination.total === 0) return 0;
            return ((this.pagination.current_page - 1) * this.pagination.per_page) + 1;
        },
        get paginationTo() {
            return Math.min(this.pagination.current_page * this.pagination.per_page, this.pagination.total);
        },
        init() {
            this.fetchResults(1);
        },
        async postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify(payload),
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = json.message || Object.values(json.errors || {}).flat()[0] || 'No se pudo completar la operacion.';
                throw new Error(message);
            }
            return json;
        },
        async postForm(url, formData) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: formData,
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = json.message || Object.values(json.errors || {}).flat()[0] || 'No se pudo completar la operacion.';
                throw new Error(message);
            }
            return json;
        },
        async fetchResults(page = 1) {
            if (this.bandeja === 'matriz') {
                await this.fetchMatrix();
                return;
            }
            if (page < 1) return;
            this.loading = true;
            try {
                const json = await this.postJson(this.endpoints.tabla, {
                    bandeja: this.bandeja,
                    campo: this.filters.campo,
                    criterio: this.filters.criterio,
                    desde: this.filters.desde,
                    hasta: this.filters.hasta,
                    per_page: this.filters.per_page,
                    page,
                });
                this.items = json.data || [];
                this.pagination = json.pagination || this.pagination;
                this.goPage = this.pagination.current_page;
                this.counts = json.counts || this.counts;
                this.selectedIds = this.selectedIds.filter(id => this.items.some(item => item.id === id));
            } catch (error) {
                this.notify(error.message, 'error');
            } finally {
                this.loading = false;
            }
        },
        setBandeja(next) {
            if (this.bandeja === next) return;
            this.bandeja = next;
            this.selectedIds = [];
            if (next === 'matriz') {
                this.fetchMatrix();
            } else {
                this.fetchResults(1);
            }
        },
        goToPage() {
            const page = Math.max(1, Math.min(Number(this.goPage || 1), this.pagination.last_page || 1));
            this.fetchResults(page);
        },
        resetFilters() {
            this.filters.campo = 'expediente';
            this.filters.criterio = '';
            this.filters.desde = '';
            this.filters.hasta = '';
            this.filters.per_page = 10;
            this.fetchResults(1);
        },
        toggleAll(checked) {
            this.selectedIds = checked && this.isSelectableTab ? this.items.map(item => item.id) : [];
        },
        toggleOne(id, checked) {
            if (!this.isSelectableTab) return;
            if (checked && !this.selectedIds.includes(id)) {
                this.selectedIds.push(id);
            }
            if (!checked) {
                this.selectedIds = this.selectedIds.filter(value => value !== id);
            }
        },
        openArchiveModal() {
            if (!this.canArchive) return;
            this.archiveModal.observaciones = 'SE ARCHIVA SIN NOVEDAD';
            this.archiveModal.show = true;
        },
        async archivarSeleccionados() {
            this.archiveModal.saving = true;
            try {
                const json = await this.postJson(this.endpoints.archivar, {
                    ids: this.selectedIds,
                    observaciones: this.archiveModal.observaciones,
                });
                this.archiveModal.show = false;
                this.selectedIds = [];
                this.notify(json.message || 'Expedientes archivados.');
                await this.fetchResults(1);
            } catch (error) {
                this.notify(error.message, 'error');
            } finally {
                this.archiveModal.saving = false;
            }
        },
        openNotifyModal() {
            if (!this.canNotify) return;
            this.notifyModal.observaciones = 'SE LE ENTREGA EL EXPEDIENTE AL ADMINISTRADO';
            this.notifyModal.documento = null;
            this.notifyModal.show = true;
        },
        async notificarSeleccionados() {
            this.notifyModal.saving = true;
            try {
                const form = new FormData();
                this.selectedIds.forEach(id => form.append('ids[]', id));
                form.append('observaciones', this.notifyModal.observaciones);
                if (this.notifyModal.documento) {
                    form.append('documento', this.notifyModal.documento);
                }
                const json = await this.postForm(this.endpoints.notificar, form);
                this.notifyModal.show = false;
                this.selectedIds = [];
                this.notify(json.message || 'Expedientes notificados.');
                await this.fetchResults(1);
            } catch (error) {
                this.notify(error.message, 'error');
            } finally {
                this.notifyModal.saving = false;
            }
        },
        openLoanModal() {
            if (!this.canLoan) return;
            this.loanModal.observaciones = 'SE PRESTA A: ';
            this.loanModal.show = true;
        },
        async prestarSeleccionados() {
            this.loanModal.saving = true;
            try {
                const json = await this.postJson(this.endpoints.prestar, {
                    ids: this.selectedIds,
                    observaciones: this.loanModal.observaciones,
                });
                this.loanModal.show = false;
                this.selectedIds = [];
                this.notify(json.message || 'Expedientes prestados.');
                await this.fetchResults(1);
            } catch (error) {
                this.notify(error.message, 'error');
            } finally {
                this.loanModal.saving = false;
            }
        },
        async fetchMatrix() {
            this.matrix.loading = true;
            try {
                const response = await fetch(this.endpoints.matriz, { headers: { 'Accept': 'application/json' } });
                const json = await response.json();
                if (!response.ok) throw new Error(json.message || 'No se pudo cargar la matriz.');
                this.matrix.items = json.data || [];
            } catch (error) {
                this.notify(error.message, 'error');
            } finally {
                this.matrix.loading = false;
            }
        },
        printBandeja() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = this.endpoints.imprimir;
            form.target = '_blank';
            const fields = {
                _token: this.csrf,
                bandeja: this.bandeja,
                campo: this.filters.campo,
                criterio: this.filters.criterio,
                desde: this.filters.desde,
                hasta: this.filters.hasta,
            };
            Object.entries(fields).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value || '';
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
            form.remove();
        },
        async showDetail(id) {
            this.detailModal.show = true;
            this.detailModal.loading = true;
            this.detailModal.data = null;
            this.detailModal.historial = [];
            try {
                const json = await this.postJson(this.endpoints.detalle, { id });
                this.detailModal.data = json.data;
                this.detailModal.historial = json.historial || [];
            } catch (error) {
                this.detailModal.show = false;
                this.notify(error.message, 'error');
            } finally {
                this.detailModal.loading = false;
            }
        },
        statusClass(id) {
            if ([3, 26].includes(Number(id))) return 'bg-emerald-50 text-emerald-700';
            if (Number(id) === 27) return 'bg-blue-50 text-blue-700';
            if (Number(id) === 28) return 'bg-amber-50 text-amber-700';
            return 'bg-gray-100 text-gray-600';
        },
        notify(message, type = 'info') {
            this.toast = { show: true, message, type };
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => this.toast.show = false, 3600);
        },
    };
}

export function createSegiVerExpediComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('body');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const routes = config.routes || {};

    return {
        routes,
        loading: false,
        items: [],
        total: 0,
        pagina: 1,
        paginas: 1,
        marcados: [],
        seleccionado: null,
        estados: config.estados || [],
        historico: { expediente: '', rows: [] },
        toast: { show: false, message: '', type: 'success' },
        filtros: {
            palabra: '',
            campo: 'p_dto_trmtes.nu_expe_todo',
        },
        modalOjo: { show: false, url: '' },
        modalEstado: { show: false, item: null, esta: '', observacion: 'Se cambio el estado en el area ' },
        selectPendiente: null,

        get todosMarcados() {
            return this.items.length > 0 && this.marcados.length === this.items.length;
        },

        init() { this.fetchLista(); },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },
        buscarConValidacion() {
            if (!this.filtros.palabra.trim()) {
                this.showToast('Debe especificar un criterio de busqueda', 'error');
                return;
            }
            this.pagina = 1;
            this.fetchLista();
        },
        verTodo() {
            this.filtros.palabra = '';
            this.pagina = 1;
            this.historico = { expediente: '', rows: [] };
            this.seleccionado = null;
            this.fetchLista();
        },
        marcarTodos() { this.marcados = this.items.map(i => i.id); },
        desmarcarTodos() { this.marcados = []; },
        paginasVisibles() {
            const tam = 6;
            const seccion = Math.floor((this.pagina - 1) / tam);
            const inicio = seccion * tam + 1;
            const final = Math.min(inicio + tam - 1, this.paginas);
            const arr = [];
            for (let i = inicio; i <= final; i++) arr.push(i);
            return arr;
        },
        abrirOjo(item) {
            this.modalOjo.url = routes.buscaExpediDeta + '?cdgo_tram=' + item.id;
            this.modalOjo.show = true;
        },
        async abrirHistorico(item) {
            this.seleccionado = item.id;
            try {
                const res = await fetch(routes.historico, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ id: item.id }),
                });
                const data = await res.json();
                this.historico = {
                    expediente: data.expediente || item.expediente,
                    rows: data.data || [],
                };
            } catch (e) {
                this.showToast('No se pudo cargar el histórico.', 'error');
            }
        },
        pedirCambioEstado(item, nuevo, selectEl) {
            this.selectPendiente = selectEl;
            if (String(item.estado_id) === '7') {
                this.showToast('El expediente ya no puede cambiar de estado', 'error');
                selectEl.value = item.estado_id;
                return;
            }
            if (String(nuevo) === String(item.estado_id)) {
                return;
            }
            this.modalEstado = {
                show: true,
                item,
                esta: String(nuevo),
                observacion: 'Se cambio el estado en el area ',
            };
        },
        cancelarEstado() {
            if (this.selectPendiente && this.modalEstado.item) {
                this.selectPendiente.value = this.modalEstado.item.estado_id;
            }
            this.modalEstado.show = false;
            this.selectPendiente = null;
        },
        async confirmarEstado() {
            const m = this.modalEstado;
            if (!m.item) return;
            try {
                const res = await fetch(routes.cambiar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        id: m.item.id,
                        esta: m.esta,
                        doc: m.item.movimiento_id,
                        est_act: m.item.estado_id,
                        observacion: m.observacion,
                    }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showToast(data.message || 'Estado actualizado.');
                    this.modalEstado.show = false;
                    this.selectPendiente = null;
                    await this.fetchLista();
                    this.abrirHistorico(m.item);
                } else {
                    this.showToast(data.message || 'No se pudo cambiar el estado.', 'error');
                    this.cancelarEstado();
                }
            } catch (e) {
                this.showToast('Error al cambiar el estado.', 'error');
                this.cancelarEstado();
            }
        },
        async fetchLista() {
            this.loading = true;
            this.marcados = [];
            try {
                const res = await fetch(routes.buscar, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ...this.filtros, page: this.pagina }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.items = data.data || [];
                    this.total = data.total || 0;
                    this.pagina = data.current_page || 1;
                    this.paginas = data.last_page || 1;
                } else {
                    this.showToast(data.message || 'No se pudo cargar el listado.', 'error');
                }
            } catch (e) {
                this.showToast('Error al cargar expedientes.', 'error');
            } finally {
                this.loading = false;
            }
        },
    };
}
