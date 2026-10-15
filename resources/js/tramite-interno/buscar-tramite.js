import { readRouteConfig } from '../shared/route-config.js';
import { escapeHtml } from '../shared/safe-dom.js';

export function createBuscarTramiteComponent(customConfig = null) {
    const config = customConfig || readRouteConfig('#buscar-tramite-app');

    return {
        urlTipos: config.urlTipos || '',
        urlHist: config.urlHist || '',
        urlDet: config.urlDet || '',
        urlDesc: config.urlDesc || '',
        csrfToken: config.csrfToken || '',

        modalVoucherOpen: false,
        voucherLoading: false,
        voucherData: null,
        voucherError: '',

        historialLoading: false,
        historialTitulo: 'Detalle / historial del trámite',
        historialItems: [],
        historialError: '',

        mostrarFechas: false,

        init() {
            const fechasPanel = document.getElementById('panel-fechas');
            if (fechasPanel && !fechasPanel.classList.contains('hidden')) {
                this.mostrarFechas = true;
            }
        },

        toggleFechas() {
            this.mostrarFechas = !this.mostrarFechas;
            const p = document.getElementById('panel-fechas');
            if (p) {
                p.classList.toggle('hidden', !this.mostrarFechas);
            }
            const lbl = document.getElementById('lbl-fechas');
            if (lbl) {
                lbl.textContent = this.mostrarFechas ? 'Ocultar fechas' : 'Mostrar fechas';
            }
            if (!this.mostrarFechas) {
                const t1 = document.getElementById('T1');
                const t2 = document.getElementById('T2');
                if (t1) t1.value = '';
                if (t2) t2.value = '';
            }
        },

        async cargarTipos(area) {
            const sel = document.getElementById('sel_cdgo_tpo_trmte');
            if (!sel) return;
            const prev = sel.value;
            sel.innerHTML = '<option value="0">-- Seleccione --</option>';

            try {
                const res = await fetch(this.urlTipos + '?area=' + encodeURIComponent(area || '0'), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                (json.data || []).forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.cdgo_tpo_trmte;
                    opt.textContent = t.de_tpo_trmte;
                    if (String(t.cdgo_tpo_trmte) === String(prev)) opt.selected = true;
                    sel.appendChild(opt);
                });
            } catch (e) {
                console.error('Error al cargar tipos de documento:', e);
            }
        },

        async verHistorial(id, numero) {
            this.historialTitulo = 'Historial del trámite Nº ' + (numero || id);
            this.historialLoading = true;
            this.historialError = '';
            this.historialItems = [];

            const panel = document.getElementById('panel-historial');
            if (panel) {
                panel.innerHTML = '<p class="text-center text-xs text-slate-600 py-8"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</p>';
            }

            try {
                const res = await fetch(this.urlHist, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                const items = json.data || [];
                this.historialItems = items;

                if (!panel) return;

                if (!items.length) {
                    panel.innerHTML = '<p class="text-center text-xs text-gray-400 py-8">Sin movimientos.</p>';
                    return;
                }

                const rowsHtml = items.map(it => `
                    <tr class="border-b border-gray-100 hover:bg-slate-50/40">
                        <td class="px-3 py-2 whitespace-nowrap font-semibold">${this.fmtFecha(it.fe_rcbdo)}</td>
                        <td class="px-3 py-2"><div class="font-bold">${escapeHtml(it.dc_area || '-')}</div><div class="text-gray-400">${escapeHtml(it.de_area || '')}</div></td>
                        <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded-full bg-slate-50 text-slate-700 border border-slate-100 font-black uppercase text-[9px]">${escapeHtml(it.dc_estdo_trmte || '-')}</span></td>
                        <td class="px-3 py-2"><div class="font-bold">${escapeHtml(it.dc_area_1 || '-')}</div><div class="text-gray-400">${escapeHtml(it.de_area_1 || '')}</div></td>
                        <td class="px-3 py-2 text-gray-600 italic">${escapeHtml(it.de_obser_ntrnos || '-')}</td>
                        <td class="px-3 py-2 text-center">${it.de_urls_agrdo
                            ? `<button type="button" data-mov-id="${it.cdgo_trmtes_ntrnos}" class="btn-descargar-adj text-slate-600 hover:text-slate-900 cursor-pointer"><i class="fas fa-download"></i></button>`
                            : '-'}</td>
                    </tr>
                `).join('');

                panel.innerHTML = `
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700">Fecha mov.</th>
                                <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700">Origen</th>
                                <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700">Estado</th>
                                <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700">Destino</th>
                                <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700">Observación</th>
                                <th class="px-3 py-2 font-black uppercase tracking-widest text-slate-700 text-center">Doc</th>
                            </tr>
                        </thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                `;

                // Delegación de clic para descarga de adjunto de movimiento
                panel.querySelectorAll('.btn-descargar-adj').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const movId = btn.getAttribute('data-mov-id');
                        this.verAdjuntoMov(movId);
                    });
                });
            } catch (e) {
                console.error('Error al cargar historial:', e);
                if (panel) {
                    panel.innerHTML = '<p class="text-center text-xs text-red-500 py-8">Error al cargar el historial.</p>';
                }
            } finally {
                this.historialLoading = false;
            }
        },

        limpiarHistorial() {
            this.historialTitulo = 'Detalle / historial del trámite';
            const panel = document.getElementById('panel-historial');
            if (panel) {
                panel.innerHTML = '<p class="text-xs text-gray-400 italic text-center py-10">Seleccione un trámite de la lista para ver su historial.</p>';
            }
        },

        async verVoucher(id) {
            const modal = document.getElementById('modal-voucher');
            const body = document.getElementById('voucher-body');
            if (!modal || !body) return;

            body.innerHTML = '<p class="text-center text-slate-600"><i class="fas fa-spinner fa-spin"></i></p>';
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            try {
                const res = await fetch(this.urlDet + '?id=' + encodeURIComponent(id), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                if (!json.success || !json.data) {
                    body.innerHTML = '<p class="text-red-500 text-xs">No se encontró el trámite.</p>';
                    return;
                }
                const d = json.data;
                const adj = d.de_urls_adjnto
                    ? `<a href="${this.urlDesc}?id=${encodeURIComponent(d.cdgo_dtos_ntrnos)}" target="_blank" class="text-slate-600 font-bold underline">Ver adjunto</a>`
                    : '—';

                body.innerHTML = `
                    <p><span class="text-[10px] font-black uppercase text-gray-400">Tipo</span><br><strong>${escapeHtml(d.de_tpo_trmte || d.dc_tpo_trmte)}</strong></p>
                    <p><span class="text-[10px] font-black uppercase text-gray-400">Nº</span><br><strong class="text-slate-700 font-mono">${escapeHtml(d.nu_tram_todo)}</strong></p>
                    <p><span class="text-[10px] font-black uppercase text-gray-400">Área</span><br>${escapeHtml(d.area_origen || d.dc_area_origen)}</p>
                    <p><span class="text-[10px] font-black uppercase text-gray-400">Ingreso</span><br>${escapeHtml(d.fe_ingrso_fmt || this.fmtFecha(d.fe_ingrso))}</p>
                    <p><span class="text-[10px] font-black uppercase text-gray-400">Estado</span><br>${escapeHtml(d.estado || '—')}</p>
                    <p><span class="text-[10px] font-black uppercase text-gray-400">Observaciones</span><br class="mb-1">${escapeHtml(d.de_obser || '—')}</p>
                    <p><span class="text-[10px] font-black uppercase text-gray-400">Adjunto</span><br>${adj}</p>
                `;
            } catch (e) {
                console.error('Error al cargar voucher:', e);
                body.innerHTML = '<p class="text-red-500 text-xs">Error al cargar.</p>';
            }
        },

        cerrarVoucher() {
            const modal = document.getElementById('modal-voucher');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        },

        verAdjuntoMov(movId) {
            if (!movId) return;
            window.open(this.urlDesc + '?movimiento_id=' + encodeURIComponent(movId), '_blank');
        },

        fmtFecha(f) {
            if (!f) return '-';
            try {
                return new Date(f).toLocaleString('es-PE', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });
            } catch (e) {
                return f;
            }
        }
    };
}
