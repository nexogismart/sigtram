import { readRouteConfig } from '../shared/route-config.js';

export function createReporteTramiteComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('#reporte-tramite-app');

    return {
        urlDetalle: config.urlDetalle || '',
        fechaIni: config.fechaIni || '',
        fechaFin: config.fechaFin || '',
        csrfToken: config.csrfToken || '',
        lastKey: null,

        async toggleDetalle(area, tipo, idx, event) {
            const box = document.getElementById('conte_resultados' + idx);
            if (!box) return;
            const key = area + '-' + tipo + '-' + idx;

            document.querySelectorAll('.cell-click').forEach(c => c.classList.remove('active'));

            if (this.lastKey === key && box.classList.contains('is-open')) {
                box.classList.remove('is-open');
                box.innerHTML = '';
                this.lastKey = null;
                return;
            }

            this.lastKey = key;
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }

            document.querySelectorAll('[id^="conte_resultados"]').forEach(d => {
                if (d !== box) {
                    d.classList.remove('is-open');
                    d.innerHTML = '';
                }
            });

            box.classList.add('is-open');
            box.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';

            try {
                const res = await fetch(this.urlDetalle, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json, text/javascript, */*; q=0.01'
                    },
                    body: new URLSearchParams({
                        _token: this.csrfToken,
                        area_id: area,
                        tipo_bandeja: tipo,
                        fecha_inicio: this.fechaIni,
                        fecha_fin: this.fechaFin
                    })
                });
                const data = await res.json();
                box.innerHTML = data.html || '<div class="empty">Sin datos</div>';
            } catch (err) {
                box.innerHTML = '<div class="err">Error al cargar el detalle</div>';
            }
        }
    };
}
