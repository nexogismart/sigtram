import test from 'node:test';
import assert from 'node:assert/strict';
import { pad8, textoTramite, textoReferencia } from '../../resources/js/tramite-interno/renderers.js';

test('pad8: maneja correctamente nulos, ceros, enteros y cadenas compuestas', () => {
    // Valores vacíos o nulos nunca deben generar 00000000 falso
    assert.equal(pad8(null), '-');
    assert.equal(pad8(undefined), '-');
    assert.equal(pad8(''), '-');
    assert.equal(pad8('   '), '-');
    assert.equal(pad8('-'), '-');

    // Cero real sí genera 00000000
    assert.equal(pad8(0), '00000000');
    assert.equal(pad8('0'), '00000000');

    // Números enteros
    assert.equal(pad8(1), '00000001');
    assert.equal(pad8(23), '00000023');
    assert.equal(pad8('12345'), '00012345');
    assert.equal(pad8('12345678'), '12345678');
    assert.equal(pad8('123456789'), '123456789');

    // Números con año compuesto (ej. 23-2026 -> 00000023-2026)
    assert.equal(pad8('23-2026'), '00000023-2026');
    assert.equal(pad8('1-2025'), '00000001-2025');

    // No numéricos basura retornan '-'
    assert.equal(pad8('abc'), '-');
});

test('textoTramite: construye el texto de trámite con tipo, número y área sin ceros fantasmas', () => {
    const itemConTodo = {
        de_tpo_trmte: 'MEMORANDUM',
        nu_tram_todo: '23-2026',
        dc_area: 'OTI',
    };
    assert.equal(textoTramite(itemConTodo), 'MEMORANDUM 00000023-2026-OTI');

    const itemSinArea = {
        de_tpo_trmte: 'MEMORANDUM',
        nu_tram_todo: '23-2026',
    };
    assert.equal(textoTramite(itemSinArea), 'MEMORANDUM 00000023-2026');

    const itemSoloNumero = {
        nu_tram_todo: 45,
    };
    assert.equal(textoTramite(itemSoloNumero), '00000045');

    const itemSinNumero = {
        de_tpo_trmte: 'OFICIO',
        nu_tram_todo: null,
    };
    assert.equal(textoTramite(itemSinNumero), 'OFICIO');

    const itemVacio = {};
    assert.equal(textoTramite(itemVacio), '-');
});

test('textoReferencia: construye la referencia sólo cuando existen datos reales', () => {
    const itemConRefCompleta = {
        dc_tpo_trmte_ref: 'INFORME',
        nu_tram_todo_ref: '50-2026',
        dc_area_ref: 'GM',
    };
    assert.equal(textoReferencia(itemConRefCompleta), 'INFORME 00000050-2026-GM');

    const itemConRefSoloNumero = {
        nu_tram_todo_ref: '50-2026',
    };
    assert.equal(textoReferencia(itemConRefSoloNumero), '00000050-2026');

    const itemSinRef = {
        nu_tram_todo_ref: null,
        cdgo_dtos_ntrnos_ref: null,
    };
    assert.equal(textoReferencia(itemSinRef), '');

    const itemRefCero = {
        nu_tram_todo_ref: 0,
        cdgo_dtos_ntrnos_ref: 0,
    };
    assert.equal(textoReferencia(itemRefCero), '');
});

// ============================================================================
// FASE 10: PRUEBAS VISUALES Y ESTRUCTURALES — TRÁMITE INTERNO
// ============================================================================
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

import {
    createTramiteCell,
    createReferenciaCell,
    createFechaCell,
    createObservacionesCell,
    renderFilasSeguras,
} from '../../resources/js/tramite-interno/renderers.js';

function setupMockDOM() {
    class MockNode {
        constructor(nodeType = 1) {
            this.nodeType = nodeType;
        }
    }
    globalThis.Node = MockNode;

    function createMockElement(tag) {
        const el = Object.assign(new MockNode(1), {
            tagName: tag.toUpperCase(),
            className: '',
            textContent: '',
            dataset: {},
            attributes: {},
            childNodes: [],
            replaceChildren() { this.childNodes = []; },
            get firstChild() { return this.childNodes[0] || null; },
            removeChild(c) {
                const idx = this.childNodes.indexOf(c);
                if (idx !== -1) this.childNodes.splice(idx, 1);
                return c;
            },
            setAttribute(k, v) { this.attributes[k] = String(v); this[k] = String(v); },
            getAttribute(k) { return this.attributes[k] || this[k]; },
            appendChild(c) {
                if (c && c.nodeType === 11) {
                    this.childNodes.push(...c.childNodes);
                    c.childNodes = [];
                    return c;
                }
                this.childNodes.push(c);
                return c;
            },
        });
        return el;
    }

    return {
        createElement: createMockElement,
        createTextNode: (t) => Object.assign(new MockNode(3), { textContent: String(t) }),
        createDocumentFragment: () => Object.assign(new MockNode(11), {
            childNodes: [],
            appendChild(c) { this.childNodes.push(c); return c; },
        }),
    };
}

test('FASE 10.1 - 10.4: createTramiteCell y createReferenciaCell conservan texto completo en title y evitan break-all', () => {
    const originalDoc = globalThis.document;
    try {
        globalThis.document = setupMockDOM();

        const largoTram = 'MEMORANDUM CIRCULAR 00000003-2026-OTI-GERENCIA';
        const cellTram = createTramiteCell(largoTram, largoTram);

        assert.equal(cellTram.className.includes('celda-tram'), true);
        assert.equal(cellTram.className.includes('break-all'), false);
        assert.equal(cellTram.title, largoTram);
        assert.equal(cellTram.childNodes.length, 1);
        assert.equal(cellTram.childNodes[0].className.includes('celda-tram-texto'), true);
        assert.equal(cellTram.childNodes[0].textContent, largoTram);

        const largoRef = 'INFORME TECNICO 00000045-2026-SECRETARIA GENERAL';
        const cellRef = createReferenciaCell(largoRef, largoRef);

        assert.equal(cellRef.className.includes('celda-ref'), true);
        assert.equal(cellRef.className.includes('break-all'), false);
        assert.equal(cellRef.title, largoRef);
        assert.equal(cellRef.childNodes.length, 1);
        assert.equal(cellRef.childNodes[0].className.includes('celda-ref-texto'), true);
        assert.equal(cellRef.childNodes[0].textContent, largoRef);
    } finally {
        globalThis.document = originalDoc;
    }
});

test('FASE 10.5 & 10.6: createObservacionesCell utiliza ellipsis visual sin substring ni slice y conserva texto completo en title', () => {
    const originalDoc = globalThis.document;
    try {
        globalThis.document = setupMockDOM();

        const obsLarga = 'SE REMITE EL EXPEDIENTE COMPLETO PARA SU EVALUACION TECNICA Y EMISION DE INFORME FINAL RESPECTIVO.';
        const cellObs = createObservacionesCell(obsLarga);

        assert.equal(cellObs.className.includes('celda-observaciones'), true);
        assert.equal(cellObs.title, obsLarga);
        assert.equal(cellObs.childNodes.length, 1);
        assert.equal(cellObs.childNodes[0].className.includes('celda-observaciones-texto'), true);
        assert.equal(cellObs.childNodes[0].textContent, obsLarga);
        // Garantizar que no se recortó físicamente el texto
        assert.equal(cellObs.childNodes[0].textContent.length, obsLarga.length);
    } finally {
        globalThis.document = originalDoc;
    }
});

test('FASE 10.9: createFechaCell utiliza whitespace-nowrap y celda-fecha', () => {
    const originalDoc = globalThis.document;
    try {
        globalThis.document = setupMockDOM();

        const fechaStr = '12/10/2026 15:30';
        const cellFecha = createFechaCell(fechaStr);

        assert.equal(cellFecha.className.includes('whitespace-nowrap'), true);
        assert.equal(cellFecha.className.includes('celda-fecha'), true);
        assert.equal(cellFecha.textContent, fechaStr);
    } finally {
        globalThis.document = originalDoc;
    }
});

test('FASE 10 CSS: tramite-interno.css cumple reglas de anchos mínimos, 2 líneas y ausencia de break-all', () => {
    const cssPath = path.resolve(__dirname, '../../resources/css/tramite-interno.css');
    const css = fs.readFileSync(cssPath, 'utf8');

    // 1. .celda-tram tiene min-width entre 190 y 230 px
    assert.match(css, /\.celda-tram\s*\{[^}]*min-width:\s*(19\d|2[0-3]\d)px/);

    // 2. .celda-ref tiene min-width entre 210 y 260 px
    assert.match(css, /\.celda-ref\s*\{[^}]*min-width:\s*(2[1-6]\d)px/);

    // 3. Ninguno usa break-all
    assert.doesNotMatch(css, /break-all/);

    // 4. .celda-tram-texto y .celda-ref-texto usan line-clamp 2
    assert.match(css, /-webkit-line-clamp:\s*2/);

    // 5. .celda-observaciones no tiene width: 100%
    assert.doesNotMatch(css, /\.celda-observaciones\s*\{[^}]*width:\s*100%/);

    // 6. .celda-observaciones tiene min-width y max-width balanceados
    assert.match(css, /\.celda-observaciones\s*\{[^}]*min-width:\s*200px/);
    assert.match(css, /\.celda-observaciones\s*\{[^}]*max-width:\s*320px/);

    // 7. .celda-observaciones-texto usa white-space: nowrap y text-overflow: ellipsis
    assert.match(css, /\.celda-observaciones-texto\s*\{[^}]*white-space:\s*nowrap/);
    assert.match(css, /\.celda-observaciones-texto\s*\{[^}]*text-overflow:\s*ellipsis/);
});

test('FASE 6: renderFilasSeguras para Entrada ubica Trámite en col 1, F. Entrada en col 2 y Trámite Refer. en col 3', () => {
    const originalDoc = globalThis.document;
    try {
        globalThis.document = setupMockDOM();

        const tbody = document.createElement('tbody');

        const itemConRef = {
            cdgo_dtos_ntrnos: 100,
            cdgo_trmtes_ntrnos: 200,
            de_tpo_trmte: 'OFICIO',
            nu_tram_todo: '10-2026',
            dc_area: 'OTI',
            fe_ingrso: '2026-10-10 09:30:00',
            dc_tpo_trmte_ref: 'MEMORANDUM',
            nu_tram_todo_ref: '20-2026',
            dc_area_ref: 'GM',
            fe_ingrso_ref: '2026-10-10 09:00:00',
            de_obser: 'ATENCION INMEDIATA',
            de_urls_adjnto: 'doc.pdf',
        };

        renderFilasSeguras('entrada', [itemConRef], tbody);

        assert.equal(tbody.childNodes.length, 1, 'Debe renderizarse una fila tr');
        const tr = tbody.childNodes[0];

        // Verificar columnas por índice:
        // Columna 0: Checkbox
        assert.equal(tr.childNodes[0].tagName, 'TD');

        // Columna 1: Trámite principal (createTramiteCell)
        const colTramite = tr.childNodes[1];
        assert.equal(colTramite.className.includes('celda-tram'), true, 'Columna 1 debe ser celda-tram');
        assert.equal(colTramite.childNodes[0].textContent.includes('OFICIO 00000010-2026-OTI'), true, 'Columna 1 debe contener el documento principal');

        // Columna 2: F. Entrada (createFechaCell)
        const colFecha = tr.childNodes[2];
        assert.equal(colFecha.className.includes('celda-fecha'), true, 'Columna 2 debe ser celda-fecha');
        assert.equal(colFecha.textContent !== '', true, 'Columna 2 debe contener la fecha');

        // Columna 3: Trámite Refer. (createReferenciaCell)
        const colRef = tr.childNodes[3];
        assert.equal(colRef.className.includes('celda-ref'), true, 'Columna 3 debe ser celda-ref');
        assert.equal(colRef.childNodes[0].textContent.includes('MEMORANDUM 00000020-2026-GM'), true, 'Columna 3 debe contener la referencia');

        // Caso sin referencia
        const tbodySinRef = document.createElement('tbody');
        const itemSinRef = {
            cdgo_dtos_ntrnos: 101,
            cdgo_trmtes_ntrnos: 201,
            de_tpo_trmte: 'INFORME',
            nu_tram_todo: '15-2026',
            dc_area: 'SG',
            fe_ingrso: '2026-10-11 11:00:00',
            nu_tram_todo_ref: null,
            cdgo_dtos_ntrnos_ref: null,
            de_obser: 'REGISTRO REGULAR',
        };

        renderFilasSeguras('entrada', [itemSinRef], tbodySinRef);
        const trSinRef = tbodySinRef.childNodes[0];

        // Columna 1: Trámite principal
        assert.equal(trSinRef.childNodes[1].className.includes('celda-tram'), true);
        assert.equal(trSinRef.childNodes[1].childNodes[0].textContent.includes('INFORME 00000015-2026-SG'), true);

        // Columna 2: F. Entrada
        assert.equal(trSinRef.childNodes[2].className.includes('celda-fecha'), true);

        // Columna 3: Celda vacía de referencia
        assert.equal(trSinRef.childNodes[3].textContent, '');
    } finally {
        globalThis.document = originalDoc;
    }
});

// ============================================================================
// FASE 4 & 5: CONTRATO EXPLÍCITO DE IDENTIFICADORES Y AISLAMIENTO CSS
// ============================================================================
import { apiCargarHistorial } from '../../resources/js/tramite-interno/api.js';
import { verDetalle } from '../../resources/js/tramite-interno/historico.js';

test('FASE 4: apiCargarHistorial y verDetalle rechazan escalares y objetos vacíos con TypeError', async () => {
    // Escalares numéricos o de texto
    await assert.rejects(apiCargarHistorial(123), {
        name: 'TypeError',
        message: /apiCargarHistorial requiere un objeto explícito/,
    });
    await assert.rejects(apiCargarHistorial('123'), {
        name: 'TypeError',
        message: /apiCargarHistorial requiere un objeto explícito/,
    });
    await assert.rejects(apiCargarHistorial(null), {
        name: 'TypeError',
        message: /apiCargarHistorial requiere un objeto explícito/,
    });
    await assert.rejects(apiCargarHistorial({}), {
        name: 'TypeError',
        message: /apiCargarHistorial requiere un objeto explícito/,
    });

    // verDetalle rechaza escalares
    await assert.rejects(verDetalle(456), {
        name: 'TypeError',
        message: /verDetalle requiere un objeto explícito/,
    });
    await assert.rejects(verDetalle('456'), {
        name: 'TypeError',
        message: /verDetalle requiere un objeto explícito/,
    });
    await assert.rejects(verDetalle(null), {
        name: 'TypeError',
        message: /verDetalle requiere un objeto explícito/,
    });
    await assert.rejects(verDetalle({}), {
        name: 'TypeError',
        message: /verDetalle requiere un objeto explícito/,
    });
});

test('FASE 5: CSS modular completamente aislado bajo #tramite-interno-app y #tramite-externo-app', () => {
    const cssInternoPath = path.resolve(__dirname, '../../resources/css/tramite-interno.css');
    const cssExternoPath = path.resolve(__dirname, '../../resources/css/tramite-externo.css');

    const cssInterno = fs.readFileSync(cssInternoPath, 'utf-8');
    const cssExterno = fs.readFileSync(cssExternoPath, 'utf-8');

    // 1. No existe selector global 'body' en ninguno de los archivos modulares
    assert.doesNotMatch(cssInterno, /(^|\})\s*body\s*\{/m, 'tramite-interno.css no debe contener body global');
    assert.doesNotMatch(cssExterno, /(^|\})\s*body\s*\{/m, 'tramite-externo.css no debe contener body global');

    // 2. No existe :focus-visible sin prefijo modular
    assert.doesNotMatch(cssInterno, /(^|\})\s*:focus-visible\s*\{/m, 'tramite-interno.css no debe tener :focus-visible global');
    assert.doesNotMatch(cssExterno, /(^|\})\s*:focus-visible\s*\{/m, 'tramite-externo.css no debe tener :focus-visible global');

    // 3. Todo bloque de estilo de interno comienza con #tramite-interno-app
    const reglasInterno = cssInterno
        .split('}')
        .map(r => r.replace(/\/\*[\s\S]*?\*\//g, '').trim())
        .filter(r => r.length > 0 && r.includes('{'));
    for (const regla of reglasInterno) {
        const selector = regla.split('{')[0].trim();
        assert.equal(
            selector.startsWith('#tramite-interno-app'),
            true,
            `Selector en tramite-interno.css no está encapsulado: "${selector}"`
        );
    }

    // 4. Todo bloque de estilo de externo comienza con #tramite-externo-app
    const reglasExterno = cssExterno
        .split('}')
        .map(r => r.replace(/\/\*[\s\S]*?\*\//g, '').trim())
        .filter(r => r.length > 0 && r.includes('{'));
    for (const regla of reglasExterno) {
        const selector = regla.split('{')[0].trim();
        assert.equal(
            selector.startsWith('#tramite-externo-app'),
            true,
            `Selector en tramite-externo.css no está encapsulado: "${selector}"`
        );
    }

    // 5. tramite-externo.css no debe tener duplicadas las clases de interno .celda-ref y .celda-tram
    assert.doesNotMatch(cssExterno, /\.celda-ref\b/, 'tramite-externo.css no debe definir .celda-ref');
    assert.doesNotMatch(cssExterno, /\.celda-tram\b/, 'tramite-externo.css no debe definir .celda-tram');
});

