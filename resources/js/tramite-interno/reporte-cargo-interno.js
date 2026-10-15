import { readRouteConfig } from '../shared/route-config.js';

export function createReporteCargoInternoComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('#reporte-cargo-interno-app');

    return {
        urlDetalle: config.urlDetalle || '',
        fechaIni: config.fechaIni || '',
        fechaFin: config.fechaFin || '',
        csrfToken: config.csrfToken || '',
        lastKey: null,

        async toggleDetalle(cargo, tipo, idx, event) {
            const box = document.getElementById('conte_resultados' + idx);
            if (!box) return;
            const key = cargo + '-' + tipo + '-' + idx;

            document.querySelectorAll('.cell-click').forEach(c => c.classList.remove('active'));

            if (this.lastKey === key && !box.classList.contains('hidden')) {
                box.classList.add('hidden');
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
                    d.classList.add('hidden');
                    d.innerHTML = '';
                }
            });

            box.classList.remove('hidden');
            box.innerHTML = '<div class="text-center py-4 text-slate-600 text-xs font-bold uppercase tracking-widest"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</div>';

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
                        cargo_id: cargo,
                        tipo_bandeja: tipo,
                        fecha_inicio: this.fechaIni,
                        fecha_fin: this.fechaFin
                    })
                });
                const data = await res.json();
                box.innerHTML = data.html || '<div class="text-center text-gray-400 py-3">Sin datos</div>';
            } catch (err) {
                box.innerHTML = '<div class="text-center text-red-500 py-3 font-bold text-xs">Error al cargar el detalle</div>';
            }
        }
    };
}
