/**
 * Utilidades seguras para manipulación de DOM y mitigación de DOM XSS (RF-02 / RF-03).
 * Prohíbe innerHTML, eval, y valida estrictamente esquemas de URLs.
 */

const DANGEROUS_PROTOCOLS = /^(javascript|data|vbscript|file):/i;

/**
 * Valida y desinfecta una URL contra protocolos peligrosos (javascript:, data:, vbscript:).
 * @param {string} rawUrl
 * @param {string} [fallback='#']
 * @returns {string}
 */
export function sanitizeUrl(rawUrl, fallback = '#') {
    if (!rawUrl || typeof rawUrl !== 'string') {
        return fallback;
    }

    // Limpiar espacios en blanco y caracteres de control invisibles
    const cleaned = rawUrl.trim().replace(/[\x00-\x1F\x7F-\x9F]/g, '');

    // Comprobar si inicia con protocolo peligroso
    if (DANGEROUS_PROTOCOLS.test(cleaned)) {
        return fallback;
    }

    // Permitir URLs relativas seguras
    if (cleaned.startsWith('/') || cleaned.startsWith('./') || cleaned.startsWith('../') || cleaned.startsWith('?') || cleaned.startsWith('#')) {
        return cleaned;
    }

    // Validar esquema HTTP/HTTPS
    try {
        const parsed = new URL(cleaned, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
        if (parsed.protocol === 'http:' || parsed.protocol === 'https:' || parsed.protocol === 'mailto:' || parsed.protocol === 'tel:') {
            return cleaned;
        }
        return fallback;
    } catch {
        return fallback;
    }
}

/**
 * Escapa caracteres HTML especiales para evitar inyección en contextos de texto.
 * @param {string} str
 * @returns {string}
 */
export function escapeHtml(str) {
    if (str === null || str === undefined) {
        return '';
    }
    const s = String(str);
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/`/g, '&#96;');
}

/**
 * Lista blanca de nombres de atributos HTML permitidos para createElement.
 */
const ALLOWED_ATTRIBUTES = new Set([
    'id', 'title', 'type', 'name', 'value', 'placeholder',
    'rows', 'cols', 'min', 'max', 'step', 'maxlength',
    'tabindex', 'role', 'autocomplete', 'target', 'rel',
    'download', 'colspan', 'rowspan', 'scope', 'alt', 'src',
]);

/**
 * Crea un elemento DOM de forma completamente segura usando createElement y textContent.
 * @param {string} tagName
 * @param {object} [options={}]
 * @returns {HTMLElement}
 */
export function createSafeElement(tagName, options = {}) {
    if (typeof document === 'undefined') {
        throw new Error('createSafeElement requiere un entorno con DOM (document).');
    }

    const el = document.createElement(tagName);
    const {
        className,
        textContent,
        attributes = {},
        dataset = {},
        events = {},
        children = [],
        checked,
        disabled,
        selected,
    } = options;

    if (className) {
        el.className = className;
    }

    if (textContent !== undefined && textContent !== null) {
        el.textContent = String(textContent);
    }

    if (checked !== undefined) {
        el.checked = Boolean(checked);
    }
    if (disabled !== undefined) {
        el.disabled = Boolean(disabled);
    }
    if (selected !== undefined) {
        el.selected = Boolean(selected);
    }

    for (const [key, val] of Object.entries(attributes)) {
        if (val === undefined || val === null) {
            continue;
        }
        const lowerKey = key.toLowerCase();

        // Control estricto de href
        if (lowerKey === 'href') {
            const safeHref = sanitizeUrl(String(val));
            el.setAttribute('href', safeHref);
            continue;
        }

        // Atributos aria-*
        if (lowerKey.startsWith('aria-')) {
            el.setAttribute(lowerKey, String(val));
            continue;
        }

        // Atributos en lista blanca
        if (ALLOWED_ATTRIBUTES.has(lowerKey)) {
            el.setAttribute(lowerKey, String(val));
        }
    }

    for (const [dataKey, dataVal] of Object.entries(dataset)) {
        if (dataVal !== undefined && dataVal !== null) {
            el.dataset[dataKey] = String(dataVal);
        }
    }

    for (const [eventName, handler] of Object.entries(events)) {
        if (typeof handler === 'function') {
            el.addEventListener(eventName, handler);
        }
    }

    for (const child of children) {
        if (!child) continue;
        if (typeof child === 'string' || typeof child === 'number') {
            el.appendChild(document.createTextNode(String(child)));
        } else if (child instanceof Node) {
            el.appendChild(child);
        }
    }

    return el;
}

/**
 * Vacía de forma segura todos los nodos hijos de un elemento sin usar innerHTML = ''.
 * @param {HTMLElement} element
 */
export function clearChildren(element) {
    if (!element) return;
    if (typeof element.replaceChildren === 'function') {
        element.replaceChildren();
    } else {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
    }
}

/**
 * Crea un DocumentFragment con resaltado seguro de texto para términos de búsqueda
 * sin recurrir a innerHTML.
 * @param {string} fullText
 * @param {string} query
 * @param {string} [highlightClass='bg-yellow-200 text-yellow-900 font-semibold px-0.5 rounded']
 * @returns {DocumentFragment}
 */
export function createSafeHighlight(fullText, query, highlightClass = 'bg-yellow-200 text-yellow-900 font-semibold px-0.5 rounded') {
    if (typeof document === 'undefined') {
        throw new Error('createSafeHighlight requiere un entorno con DOM (document).');
    }

    const fragment = document.createDocumentFragment();
    const text = String(fullText ?? '');
    const trimmedQuery = String(query ?? '').trim();

    if (!trimmedQuery) {
        fragment.appendChild(document.createTextNode(text));
        return fragment;
    }

    const lowerText = text.toLowerCase();
    const lowerQuery = trimmedQuery.toLowerCase();
    let startIndex = 0;
    let matchIndex = lowerText.indexOf(lowerQuery, startIndex);

    while (matchIndex !== -1) {
        if (matchIndex > startIndex) {
            fragment.appendChild(document.createTextNode(text.substring(startIndex, matchIndex)));
        }

        const mark = document.createElement('mark');
        mark.className = highlightClass;
        mark.textContent = text.substring(matchIndex, matchIndex + trimmedQuery.length);
        fragment.appendChild(mark);

        startIndex = matchIndex + trimmedQuery.length;
        matchIndex = lowerText.indexOf(lowerQuery, startIndex);
    }

    if (startIndex < text.length) {
        fragment.appendChild(document.createTextNode(text.substring(startIndex)));
    }

    return fragment;
}
