import Alpine from 'alpinejs';
import { initTramiteInterno } from './tramite-interno/index.js';
import { createIngresarTramiteComponent } from './tramite-interno/ingresar-tramite.js';
import { createBuscarTramiteComponent } from './tramite-interno/buscar-tramite.js';
import { createReporteCargoInternoComponent } from './tramite-interno/reporte-cargo-interno.js';
import { createReporteTramiteComponent } from './tramite-interno/reporte-tramite.js';
import { createSecuencialAreaComponent } from './tramite-interno/secuencial-area.js';
import { createCorreoExternoComponent } from './tramite-externo/index.js';
import { createIngresoExpedienteComponent } from './tramite-externo/ingresar-expediente.js';
import { createV2ReporteAreaCargoComponent } from './shared/v2-reporte-area-cargo.js';
import {
    createListaExpediTodoComponent,
    createListaReporAreaComponent,
    createListaReporteComponent,
    createNotificarPlataformaComponent,
    createRedirigirComponent,
    createReporteAreaExternaComponent,
    createArchivoExpedientesComponent,
    createSegiVerExpediComponent,
} from './tramite-externo/reportes-expedientes.js';
import {
    createAgreAnexoComponent,
    createGeneExpediObserComponent,
} from './tramite-externo/modales-tramite.js';

// Registrar Alpine localmente para la aplicación
window.Alpine = Alpine;
Alpine.data('correoExternoApp', (cfg) => createCorreoExternoComponent(cfg));
Alpine.data('ingresarTramiteForm', (cfg) => createIngresarTramiteComponent(cfg));
Alpine.data('buscarTramiteApp', (cfg) => createBuscarTramiteComponent(cfg));
Alpine.data('reporteCargoInternoApp', (cfg) => createReporteCargoInternoComponent(cfg));
Alpine.data('reporteTramiteApp', (cfg) => createReporteTramiteComponent(cfg));
Alpine.data('secuencialAreaApp', (cfg) => createSecuencialAreaComponent(cfg));
Alpine.data('ingresoExpediente', (cfg) => createIngresoExpedienteComponent(cfg));
Alpine.data('v2ReporteAreaCargoApp', (cfg) => createV2ReporteAreaCargoComponent(cfg));
Alpine.data('listaExpediTodo', (cfg) => createListaExpediTodoComponent(cfg));
Alpine.data('listaReporArea', (cfg) => createListaReporAreaComponent(cfg));
Alpine.data('listaReporte', (cfg) => createListaReporteComponent(cfg));
Alpine.data('notificarPlataforma', (cfg) => createNotificarPlataformaComponent(cfg));
Alpine.data('redirigirExpedientes', (cfg) => createRedirigirComponent(cfg));
Alpine.data('reporteAreaExterna', (cfg) => createReporteAreaExternaComponent(cfg));
Alpine.data('archivoExpedientes', (cfg) => createArchivoExpedientesComponent(cfg));
Alpine.data('segiVerExpedi', (cfg) => createSegiVerExpediComponent(cfg));
Alpine.data('agreAnexoForm', () => createAgreAnexoComponent());
Alpine.data('geneExpediObserForm', (areas) => createGeneExpediObserComponent(areas));
Alpine.start();

// Delegación global para botones de impresión y cierre sin inline handlers
document.addEventListener('click', (e) => {
    const printBtn = e.target.closest('[data-action="print"]');
    if (printBtn) {
        e.preventDefault();
        window.print();
        return;
    }
    const closeBtn = e.target.closest('[data-action="close"]');
    if (closeBtn) {
        e.preventDefault();
        window.close();
        return;
    }
    const popupBtn = e.target.closest('[data-action="popup"]');
    if (popupBtn) {
        e.preventDefault();
        const url = popupBtn.getAttribute('href') || popupBtn.dataset.url;
        const width = popupBtn.dataset.width || 470;
        const height = popupBtn.dataset.height || 310;
        const target = popupBtn.getAttribute('target') || '_blank';
        window.open(url, target, `scrollbars=auto,width=${width},height=${height}`);
        return;
    }
    const csvBtn = e.target.closest('[data-action="export-csv"]');
    if (csvBtn) {
        e.preventDefault();
        const tableId = csvBtn.dataset.table || 'tabla-reporte';
        const filename = csvBtn.dataset.filename || 'reporte_' + new Date().toISOString().slice(0, 10) + '.csv';
        const table = document.getElementById(tableId);
        if (table) {
            let csv = [];
            for (let row of table.rows) {
                let cols = [];
                for (let cell of row.cells) {
                    let text = cell.innerText.replace(/"/g, '""').replace(/\n/g, ' ');
                    cols.push('"' + text + '"');
                }
                csv.push(cols.join(';'));
            }
            const csvContent = "\uFEFF" + csv.join("\r\n");
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        return;
    }
    const confirmBtn = e.target.closest('[data-action="submit-confirm"]');
    if (confirmBtn) {
        e.preventDefault();
        const msg = confirmBtn.dataset.confirm || '¿Está seguro de continuar?';
        if (confirm(msg)) {
            const form = confirmBtn.closest('form') || document.querySelector(confirmBtn.dataset.form);
            if (form) form.submit();
        }
        return;
    }
    const navBtn = e.target.closest('[data-href]');
    if (navBtn) {
        window.location.href = navBtn.dataset.href;
        return;
    }
});

// Inicialización condicional e independiente de Trámite Interno
function bootstrap() {
    const internoRoot = document.getElementById('tramite-interno-app');
    if (internoRoot) {
        initTramiteInterno(internoRoot);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
} else {
    bootstrap();
}

export {
    initTramiteInterno,
    createIngresarTramiteComponent,
    createCorreoExternoComponent,
    createIngresoExpedienteComponent,
};

