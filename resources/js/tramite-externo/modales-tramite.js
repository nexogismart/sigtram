/**
 * Componentes Alpine para modales y popups de Trámite Externo
 */

export function createAgreAnexoComponent() {
    return {
        init() {
            const ta = document.getElementById('TA1');
            if (ta) ta.focus();
        },
        submitForm() {
            const foli = (document.getElementById('txt_nu_foli_anex')?.value || '').toString().trim();
            if (foli === '') {
                alert('Debe especificar la cantidad de folios');
                document.getElementById('txt_nu_foli_anex')?.focus();
                return;
            }
            const ta = document.getElementById('TA1');
            if (!ta || !ta.value.trim()) {
                alert('Debe especificar las observaciones');
                if (ta) ta.focus();
                return;
            }
            if (!confirm('¿Confirma generar el anexo/reingreso?')) {
                return;
            }
            document.getElementById('form1')?.submit();
        }
    };
}

export function createGeneExpediObserComponent(customAreas = null) {
    let areas = customAreas;
    if (!areas) {
        const root = document.getElementById('gene-expedi-obser-app');
        if (root && root.dataset.areas) {
            try {
                areas = JSON.parse(root.dataset.areas);
            } catch {
                areas = [];
            }
        } else {
            areas = [];
        }
    }

    return {
        areas,
        cargarSubgerencias() {
            const padre = parseInt(document.getElementById('Se2')?.value || '0', 10);
            const sel = document.getElementById('Se6');
            if (!sel) return;
            const actual = sel.value;
            sel.innerHTML = '<option value="0">-- Seleccione la subgerencia --</option>';
            this.areas.filter(a => a.padre === padre).forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.nombre;
                if (String(a.id) === String(actual)) opt.selected = true;
                sel.appendChild(opt);
            });
        }
    };
}
