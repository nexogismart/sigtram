import { readRouteConfig } from '../shared/route-config.js';

export function createSecuencialAreaComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('#secuencial-area-app');

    return {
        urlLista: config.urlLista || '',
        csrfToken: config.csrfToken || '',
        errorMessage: '',
        hasError: false,

        init() {
            this.cargarLista();
        },

        async cargarLista() {
            const selTipo = document.getElementById('sel_cdgo_tpo_trmte');
            const selAnio = document.getElementById('sel_cdgo_anio');
            const box = document.getElementById('conte_lista_documentos');
            if (!selTipo || !selAnio || !box) return;

            const tpo = selTipo.value;
            const anio = selAnio.value;
            if (!tpo || tpo === '0' || !anio) {
                box.innerHTML = '<p style="text-align:center;color:#94a3b8;font-weight:700;margin:0">Seleccione un tipo de documento.</p>';
                return;
            }
            box.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';

            try {
                const res = await fetch(this.urlLista, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json, text/javascript, */*; q=0.01'
                    },
                    body: new URLSearchParams({
                        _token: this.csrfToken,
                        cdgo_tpo_trmte: tpo,
                        cdgo_anio: anio
                    })
                });
                const data = await res.json();
                box.innerHTML = data.html || '';
            } catch (err) {
                box.innerHTML = '<p style="text-align:center;color:#ef4444;font-weight:700">Error al cargar la lista.</p>';
            }
        },

        generarNumero() {
            const selTipo = document.getElementById('sel_cdgo_tpo_trmte');
            this.hasError = false;
            this.errorMessage = '';

            if (!selTipo || !selTipo.value || selTipo.value === '0') {
                this.errorMessage = 'Seleccione un tipo de documento.';
                this.hasError = true;
                return;
            }

            const form = document.getElementById('formSecuencial');
            if (form) {
                form.submit();
            }
        }
    };
}
