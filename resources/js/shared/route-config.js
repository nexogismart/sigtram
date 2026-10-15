/**
 * Lector y resolutor seguro de configuración de rutas frontend (RF-02).
 */

/**
 * Lee y analiza de forma segura el atributo de configuración JSON de un elemento raíz.
 * @param {HTMLElement|string} rootOrSelector
 * @param {string} [attributeName='data-config']
 * @returns {Record<string, any>}
 */
export function readRouteConfig(rootOrSelector, attributeName = 'data-config') {
    if (typeof document === 'undefined') {
        return {};
    }

    const element = typeof rootOrSelector === 'string'
        ? document.querySelector(rootOrSelector)
        : rootOrSelector;

    if (!element) {
        return {};
    }

    const rawConfig = element.getAttribute(attributeName);
    if (!rawConfig) {
        return {};
    }

    try {
        const parsed = JSON.parse(rawConfig);
        return (parsed && typeof parsed === 'object') ? parsed : {};
    } catch (e) {
        console.error('Error al parsear la configuración JSON del nodo raíz:', e);
        return {};
    }
}

/**
 * Resuelve una plantilla de ruta reemplazando parámetros posicionales o con nombre
 * e insertando parámetros de consulta adicionales.
 *
 * Ejemplos:
 *   resolveRoute('/api/usuarios/:id', { id: 42 }) => '/api/usuarios/42'
 *   resolveRoute('/api/usuarios/{id}', { id: 42 }) => '/api/usuarios/42'
 *   resolveRoute('/api/usuarios', {}, { page: 2, q: 'test' }) => '/api/usuarios?page=2&q=test'
 *
 * @param {string} template
 * @param {Record<string, any>} [params={}]
 * @param {Record<string, any>} [query={}]
 * @returns {string}
 */
export function resolveRoute(template, params = {}, query = {}) {
    if (!template || typeof template !== 'string') {
        return '';
    }

    let url = template;

    for (const [key, val] of Object.entries(params)) {
        const encodedVal = encodeURIComponent(String(val ?? ''));
        url = url.replace(new RegExp(`:${key}\\b`, 'g'), encodedVal);
        url = url.replace(new RegExp(`\\{${key}\\}`, 'g'), encodedVal);
    }

    const queryEntries = Object.entries(query).filter(
        ([, v]) => v !== undefined && v !== null && v !== ''
    );

    if (queryEntries.length > 0) {
        const searchParams = new URLSearchParams();
        for (const [k, v] of queryEntries) {
            searchParams.append(k, String(v));
        }
        const separator = url.includes('?') ? '&' : '?';
        url = `${url}${separator}${searchParams.toString()}`;
    }

    return url;
}

/**
 * Valida que las claves requeridas existan en un objeto de configuración.
 * @param {Record<string, any>} config
 * @param {string[]} requiredKeys
 * @returns {{ valid: boolean, missing: string[] }}
 */
export function validateConfig(config, requiredKeys = []) {
    if (!config || typeof config !== 'object') {
        return { valid: false, missing: [...requiredKeys] };
    }

    const missing = requiredKeys.filter((key) => {
        const parts = key.split('.');
        let current = config;
        for (const part of parts) {
            if (current === undefined || current === null || !(part in current)) {
                return true;
            }
            current = current[part];
        }
        return false;
    });

    return {
        valid: missing.length === 0,
        missing,
    };
}
