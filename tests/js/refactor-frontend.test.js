import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { httpClient } from '../../resources/js/shared/http-client.js';
import { resolveRoute, validateConfig } from '../../resources/js/shared/route-config.js';
import { sanitizeUrl, escapeHtml, createSafeHighlight } from '../../resources/js/shared/safe-dom.js';
import { calculatePagination, generatePageRange } from '../../resources/js/shared/pagination.js';
import { getEstadoBadgeClass } from '../../resources/js/tramite-externo/estados.js';
import { createInitialState } from '../../resources/js/tramite-externo/state.js';
import { bandejasMixin } from '../../resources/js/tramite-externo/bandejas.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// ============================================================================
// 1. SAFE DOM & URL SANITIZATION TESTS (P0 XSS PREVENTION)
// ============================================================================
test('safe-dom: sanitizeUrl blocks dangerous schemes and scripts', () => {
    // Malicious schemes
    assert.equal(sanitizeUrl("javascript:alert('XSS')"), '#');
    assert.equal(sanitizeUrl('JAVASCRIPT:alert(1)'), '#');
    assert.equal(sanitizeUrl('JaVaScRiPt:alert(document.cookie)'), '#');
    assert.equal(sanitizeUrl('data:text/html,<script>alert(1)</script>'), '#');
    assert.equal(sanitizeUrl('DATA:application/javascript;base64,YWxlcnQoMSk='), '#');
    assert.equal(sanitizeUrl('vbscript:msgbox("xss")'), '#');
    assert.equal(sanitizeUrl('blob:http://example.com/uuid'), '#');
    assert.equal(sanitizeUrl('file:///etc/passwd'), '#');

    // Safe absolute URLs
    assert.equal(sanitizeUrl('https://munipuentepiedra.gob.pe/tramite'), 'https://munipuentepiedra.gob.pe/tramite');
    assert.equal(sanitizeUrl('http://localhost:8000/inicio'), 'http://localhost:8000/inicio');

    // Safe relative URLs
    assert.equal(sanitizeUrl('/mod_gene/correo_interno/tabla'), '/mod_gene/correo_interno/tabla');
    assert.equal(sanitizeUrl('./voucher/1234'), './voucher/1234');
    assert.equal(sanitizeUrl('?bandeja=entrada&page=2'), '?bandeja=entrada&page=2');

    // Empty or non-string
    assert.equal(sanitizeUrl(''), '#');
    assert.equal(sanitizeUrl(null), '#');
    assert.equal(sanitizeUrl(undefined), '#');
});

test('safe-dom: escapeHtml encodes all HTML metacharacters and attribute breakers', () => {
    const raw = '<script>alert("XSS & \'quote\' `backtick`");</script>';
    const escaped = escapeHtml(raw);

    assert.equal(escaped.includes('<script>'), false);
    assert.equal(escaped.includes('</script>'), false);
    assert.equal(escaped.includes('&lt;script&gt;'), true);
    assert.equal(escaped.includes('&amp;'), true);
    assert.equal(escaped.includes('&quot;'), true);
    assert.equal(escaped.includes('&#39;'), true);
    assert.equal(escaped.includes('&#96;'), true);

    // Empty inputs
    assert.equal(escapeHtml(''), '');
    assert.equal(escapeHtml(null), '');
    assert.equal(escapeHtml(undefined), '');
});

test('safe-dom: createSafeHighlight escapes text before highlighting matches', () => {
    const originalDoc = globalThis.document;
    try {
        globalThis.document = {
            createDocumentFragment: () => ({
                childNodes: [],
                appendChild(c) { this.childNodes.push(c); return c; },
            }),
            createElement: (t) => ({ tagName: t, className: '', textContent: '' }),
            createTextNode: (t) => ({ textContent: t }),
        };

        const text = 'Expediente 2026-001';
        const term = '2026-001';
        const fragment = createSafeHighlight(text, term, 'bg-amber-200');

        assert.equal(fragment.childNodes.length, 2);
        assert.equal(fragment.childNodes[0].textContent, 'Expediente ');
        assert.equal(fragment.childNodes[1].tagName, 'mark');
        assert.equal(fragment.childNodes[1].textContent, '2026-001');
        assert.equal(fragment.childNodes[1].className, 'bg-amber-200');
    } finally {
        globalThis.document = originalDoc;
    }
});

test('tramite-externo: highlightMatch in bandejasMixin safely highlights search text', () => {
    const ctx = {
        criterio: 'expe_todo',
        descripcion: 'VILLANUEVA',
        escapeRegex: bandejasMixin.escapeRegex,
    };
    const html = bandejasMixin.highlightMatch.call(ctx, 'JUAN CARLOS VILLANUEVA <img src=x onerror=alert(1)>');

    assert.equal(html.includes('<img'), false);
    assert.equal(html.includes('&lt;img src=x onerror=alert(1)&gt;'), true);
    assert.equal(html.includes('<mark class="bg-yellow-200 text-yellow-900 rounded px-1 font-semibold">VILLANUEVA</mark>'), true);
});

// ============================================================================
// 2. HTTP CLIENT & ERROR NORMALIZATION TESTS
// ============================================================================
test('http-client: normalizes success responses with JSON', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ status: 'success', data: { total: 42 } }),
    });

    try {
        const res = await httpClient.get('/test/endpoint');
        assert.equal(res.ok, true);
        assert.equal(res.status, 200);
        assert.equal(res.data.status, 'success');
        assert.equal(res.data.data.total, 42);
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('http-client: normalizes 422 validation errors without leaking traces', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: false,
        status: 422,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({
            message: 'Los datos proporcionados no son válidos.',
            errors: { id: ['El campo id es obligatorio.'] },
        }),
    });

    try {
        const res = await httpClient.post('/test/validate', { id: null });
        assert.equal(res.ok, false);
        assert.equal(res.status, 422);
        assert.equal(res.message, 'Los datos proporcionados no son válidos.');
        assert.deepEqual(res.errors, { id: ['El campo id es obligatorio.'] });
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('http-client: normalizes 401 unauthenticated response', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: false,
        status: 401,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ message: 'Unauthenticated.' }),
    });

    try {
        const res = await httpClient.get('/test/protected');
        assert.equal(res.ok, false);
        assert.equal(res.status, 401);
        assert.equal(res.message, 'Unauthenticated.');
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('http-client: normalizes network failure or AbortError', async () => {
    const originalFetch = globalThis.fetch;
    const abortErr = new Error('The operation was aborted');
    abortErr.name = 'AbortError';

    globalThis.fetch = async () => {
        throw abortErr;
    };

    try {
        const res = await httpClient.get('/test/abort');
        assert.equal(res.ok, false);
        assert.equal(res.status, 0);
        assert.equal(res.message, 'Petición cancelada por el usuario.');
    } finally {
        globalThis.fetch = originalFetch;
    }
});

// ============================================================================
// 3. ROUTE CONFIG RESOLUTION & VALIDATION
// ============================================================================
test('route-config: resolveRoute replaces placeholders and appends query params', () => {
    const template = '/mod_gene/correo_interno/detalle_documento/{id}';
    const resolved = resolveRoute(template, { id: 9876 }, { download: 1, format: 'pdf' });

    assert.equal(resolved, '/mod_gene/correo_interno/detalle_documento/9876?download=1&format=pdf');
});

test('route-config: validateConfig identifies missing routes', () => {
    const config = {
        routes: {
            tabla: '/api/tabla',
            conteos: '/api/conteos',
        },
    };
    const { valid, missing } = validateConfig(config, ['routes.tabla', 'routes.conteos', 'routes.historial', 'routes.recepcionar']);

    assert.equal(valid, false);
    assert.deepEqual(missing, ['routes.historial', 'routes.recepcionar']);
});

// ============================================================================
// 4. PAGINATION CALCULATOR & RANGE GENERATOR
// ============================================================================
test('pagination: calculatePagination calculates correct indices and boundaries', () => {
    const p = calculatePagination({ total: 105, page: 2, perPage: 10 });

    assert.equal(p.currentPage, 2);
    assert.equal(p.perPage, 10);
    assert.equal(p.total, 105);
    assert.equal(p.totalPages, 11);
    assert.equal(p.from, 11);
    assert.equal(p.to, 20);
    assert.equal(p.hasPrev, true);
    assert.equal(p.hasNext, true);

    // Boundary first page
    const pFirst = calculatePagination({ total: 50, page: 1, perPage: 10 });
    assert.equal(pFirst.hasPrev, false);
    assert.equal(pFirst.hasNext, true);

    // Boundary last page
    const pLast = calculatePagination({ total: 50, page: 5, perPage: 10 });
    assert.equal(pLast.hasPrev, true);
    assert.equal(pLast.hasNext, false);
});

test('pagination: generatePageRange generates correct window with ellipses', () => {
    const range = generatePageRange(5, 10, 2);
    // current 5, total 10: 1, '...', 3, 4, 5, 6, 7, '...', 10
    assert.deepEqual(range, [1, '...', 3, 4, 5, 6, 7, '...', 10]);
});

// ============================================================================
// 5. TRÁMITE EXTERNO SEMANTIC STATE BADGES
// ============================================================================
test('tramite-externo: getEstadoBadgeClass matches all 6 semantic families correctly', () => {
    // 1. Ámbar (Pendiente / Espera)
    assert.match(getEstadoBadgeClass(1, 'PENDIENTE'), /amber/);
    assert.match(getEstadoBadgeClass(51, 'EN ESPERA'), /amber/);

    // 2. Azul (Ingreso / Asignación / Derivado / Enviado)
    assert.match(getEstadoBadgeClass(7, 'DERIVADO'), /blue/);
    assert.match(getEstadoBadgeClass(17, 'ASIGNADO'), /blue/);
    assert.match(getEstadoBadgeClass(99, 'TRANSFERIDO'), /blue/);

    // 3. Verde (Recepción / Aprobación / Realizado)
    assert.match(getEstadoBadgeClass(2, 'RECEPCIONADO'), /emerald/);
    assert.match(getEstadoBadgeClass(99, 'APROBADO'), /emerald/);

    // 4. Gris Pizarra (Finalizado / Archivo)
    assert.match(getEstadoBadgeClass(3, 'FINALIZADO'), /slate/);
    assert.match(getEstadoBadgeClass(24, 'ARCHIVADO'), /slate/);

    // 5. Rojo (Devolución / Rechazo / Anulación)
    assert.match(getEstadoBadgeClass(4, 'DEVUELTO'), /rose/);
    assert.match(getEstadoBadgeClass(5, 'RECHAZADO'), /rose/);
    assert.match(getEstadoBadgeClass(29, 'NO RECEPCIONADO'), /rose/);

    // 6. Neutro / Desconocido
    assert.match(getEstadoBadgeClass(999, 'OTRO ESTADO INÉDITO'), /gray/);
});

// ============================================================================
// 6. TRÁMITE EXTERNO INITIAL STATE FACTORY
// ============================================================================
test('tramite-externo: createInitialState sets up clean defaults without dead showSilencio', () => {
    const state = createInitialState({
        routes: { tabla: '/test/tabla' },
        bandejaInicial: 'recibidos',
        puedeExportarExcel: true,
    });

    assert.equal(state.activeBandeja, 'recibidos');
    assert.equal(state.puedeExportarExcel, true);
    assert.equal(state.page, 1);
    assert.equal(state.perPage, 25);
    assert.equal(state.criterio, 'expe_todo');
    assert.deepEqual(state.selectedIds, []);
    assert.equal(state.routes.tabla, '/test/tabla');

    // Verify dead modalSilencio is not present
    assert.equal('modalSilencio' in state, false);
});

// ============================================================================
// 7. FASE 10 & FASE 9: PRUEBAS VISUALES, ESTRUCTURALES Y DE SEGURIDAD
// ============================================================================
test('FASE 10 & FASE 6: filtros-tabla.blade.php cumple proporciones, anchos y truncamiento visual en Trámite Externo', () => {
    const bladePath = path.resolve(__dirname, '../../resources/views/mod_gm/mod_tram_exte/partials_segi/filtros-tabla.blade.php');
    const blade = fs.readFileSync(bladePath, 'utf8');

    // 1. Contenedor permite scroll horizontal en viewport reducido con min-w-[1080px]
    assert.match(blade, /overflow-auto/);
    assert.match(blade, /min-w-\[1080px\]/);

    // 2. Nº EXP. utiliza whitespace-nowrap, min-width legible (140-170px) y no usa break-all
    assert.match(blade, /whitespace-nowrap\s+min-w-\[150px\]/);
    assert.doesNotMatch(blade, /break-all/);

    // 3. NOMBRE O RAZÓN SOCIAL tiene ancho amplio (300-420px), line-clamp-2 (máximo 2 líneas) y conserva title
    assert.match(blade, /min-w-\[300px\]/);
    assert.match(blade, /max-w-\[420px\]/);
    assert.match(blade, /line-clamp-2/);
    assert.match(blade, /:title="item\.no_crto"/);

    // 4. F. ENTRADA utiliza whitespace-nowrap y ancho suficiente
    assert.match(blade, /whitespace-nowrap\s+min-w-\[150px\]/);

    // 5. OBSERVACIONES utiliza line-clamp-2 (máximo 2 líneas), ancho controlado (220-320px) y conserva title
    assert.match(blade, /line-clamp-2/);
    assert.match(blade, /min-w-\[220px\]/);
    assert.match(blade, /max-w-\[320px\]/);
    assert.match(blade, /:title="item\.de_obse_trmte"/);

    // 6. ACCIONES utiliza whitespace-nowrap y mantiene botones visibles
    assert.match(blade, /whitespace-nowrap\s+min-w-\[80px\]/);

    // 7. No se agregaron bloques <style> ni atributos style="" estáticos
    assert.doesNotMatch(blade, /<style/i);
    assert.doesNotMatch(blade, /style="/i);
});

test('FASE 6: segi_expedi.blade.php (Blade raíz) no contiene <style> ni scripts complejos y carga CSS vía Vite', () => {
    const rootBladePath = path.resolve(__dirname, '../../resources/views/mod_gm/mod_tram_exte/segi_expedi.blade.php');
    const rootBlade = fs.readFileSync(rootBladePath, 'utf8');

    // 1. Sin bloque <style> incrustado
    assert.doesNotMatch(rootBlade, /<style/i);

    // 2. Sin tags <script> con lógica compleja incrustada
    assert.doesNotMatch(rootBlade, /<script(?![^>]*src=)[^>]*>[\s\S]*function[\s\S]*<\/script>/i);

    // 3. Importa app.css y app.js por Vite
    assert.match(rootBlade, /@vite\(\['resources\/css\/app\.css',\s*'resources\/js\/app\.js'\]\)/);

    // 4. No contiene estadosList residual
    assert.doesNotMatch(rootBlade, /'estadosList'/);

    // 5. Verifica que app.css importa tramite-externo.css
    const appCssPath = path.resolve(__dirname, '../../resources/css/app.css');
    const appCss = fs.readFileSync(appCssPath, 'utf8');
    assert.match(appCss, /@import '\.\/tramite-externo\.css';/);
});

test('FASE 10 & FASE 7: historico.blade.php colapsable, sin scroll duplicado, mantiene U, A y retira columna D', () => {
    const bladePath = path.resolve(__dirname, '../../resources/views/mod_gm/mod_tram_exte/partials_segi/historico.blade.php');
    const blade = fs.readFileSync(bladePath, 'utf8');

    // 1. Permite scroll horizontal y depende de selectedExpediente
    assert.match(blade, /overflow-x-auto/);
    assert.match(blade, /selectedExpediente/);

    // 2. NO contiene max-height ni overflow-y (elimina scroll vertical interno duplicado)
    assert.doesNotMatch(blade, /max-h-/);
    assert.doesNotMatch(blade, /max-height/i);
    assert.doesNotMatch(blade, /overflow-y-/);

    // 3. Observaciones truncadas visualmente conservando title
    assert.match(blade, /truncate/);
    assert.match(blade, /:title="hist\.observaciones"/);

    // 4. Columna U accesible con openModalUsuarios
    assert.match(blade, /openModalUsuarios\(hist,\s*\$el\)/);

    // 5. Columna A accesible con abrirModalAuditoria condicionado a tiene_descargas
    assert.match(blade, /abrirModalAuditoria\(hist,\s*\$el\)/);
    assert.match(blade, /hist\.tiene_descargas/);

    // 6. Columna D retirada según homologación con legacy
    assert.doesNotMatch(blade, /<th[^>]*>D<\/th>/i);
    assert.doesNotMatch(blade, /verVoucher\(hist\)/);

    // 7. No contiene bloques <style> ni estilos inline
    assert.doesNotMatch(blade, /<style/i);
    assert.doesNotMatch(blade, /style="/i);
});

test('FASE 9: Seguridad Frontend — mitigación estricta de XSS en highlightMatch y escapeHtml', () => {
    // 1. Vector XSS complejo con img onerror
    const xssImg = '<img src=x onerror=alert(1)>';
    const escapedImg = escapeHtml(xssImg);
    assert.equal(escapedImg.includes('<img'), false);
    assert.equal(escapedImg.includes('&lt;img'), true);

    // 2. Vector XSS con script tag
    const xssScript = '<script>alert("pwned")</script>';
    const escapedScript = escapeHtml(xssScript);
    assert.equal(escapedScript.includes('<script>'), false);
    assert.equal(escapedScript.includes('&lt;script&gt;'), true);

    // 3. Comillas dobles, simples y ampersand
    const xssQuotes = '"test" & \'single\' `tick`';
    const escapedQuotes = escapeHtml(xssQuotes);
    assert.equal(escapedQuotes.includes('"'), false);
    assert.equal(escapedQuotes.includes("'"), false);
    assert.equal(escapedQuotes.includes('`'), false);
    assert.equal(escapedQuotes.includes('&quot;'), true);
    assert.equal(escapedQuotes.includes('&#39;'), true);
    assert.equal(escapedQuotes.includes('&#96;'), true);
    assert.equal(escapedQuotes.includes('&amp;'), true);
});

