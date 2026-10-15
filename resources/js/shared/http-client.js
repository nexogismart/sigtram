/**
 * Cliente HTTP centralizado para peticiones seguras (RF-02).
 * Normaliza CSRF, cabeceras estándar, JSON/FormData, AbortController y respuestas de error.
 */

/**
 * Obtiene el token CSRF activo desde el elemento meta de la página.
 * @returns {string}
 */
export function getCsrfToken() {
    if (typeof document === 'undefined') {
        return '';
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
}

/**
 * Normaliza errores HTTP a una estructura estándar y segura sin filtrar trazas del servidor.
 * @param {number} status
 * @param {any} responseData
 * @param {string} [fallbackMessage]
 * @returns {{ ok: false, status: number, message: string, errors: Record<string, string[]> }}
 */
export function normalizeHttpError(status, responseData = {}, fallbackMessage = '') {
    const data = (responseData && typeof responseData === 'object') ? responseData : {};
    let message = data.message || fallbackMessage;
    const errors = data.errors && typeof data.errors === 'object' ? data.errors : {};

    switch (status) {
        case 401:
            message = message || 'Sesión no válida o expirada. Por favor inicie sesión nuevamente.';
            break;
        case 403:
            message = message || 'Acceso denegado: no cuenta con permisos para ejecutar esta acción.';
            break;
        case 409:
            message = message || 'Conflicto: el registro fue modificado o su estado actual no permite la acción.';
            break;
        case 422:
            if (!message && errors && Object.keys(errors).length > 0) {
                const firstKey = Object.keys(errors)[0];
                const firstErr = Array.isArray(errors[firstKey]) ? errors[firstKey][0] : errors[firstKey];
                if (firstErr && typeof firstErr === 'string') {
                    message = firstErr;
                }
            }
            message = message || 'Existen errores de validación en los datos enviados.';
            break;
        case 404:
            message = message || 'El recurso solicitado no fue encontrado.';
            break;
        case 500:
        case 502:
        case 503:
        case 504:
            message = 'Ocurrió un error inesperado en el servidor. Por favor reintente más tarde.';
            break;
        default:
            if (!message) {
                message = status === 0
                    ? 'No se pudo conectar con el servidor. Verifique su conexión de red.'
                    : `Error en la solicitud (Código HTTP ${status}).`;
            }
            break;
    }

    return {
        ok: false,
        status,
        message,
        errors,
    };
}

/**
 * Ejecuta una petición HTTP genérica utilizando fetch.
 * @param {string} url
 * @param {RequestInit & { json?: any, formData?: FormData }} [options]
 * @returns {Promise<{ ok: boolean, status: number, data?: any, message?: string, errors?: Record<string, string[]> }>}
 */
export async function request(url, options = {}) {
    const {
        method = 'GET',
        headers = {},
        json,
        formData,
        signal,
        ...rest
    } = options;

    const requestHeaders = new Headers(headers);

    if (!requestHeaders.has('Accept')) {
        requestHeaders.set('Accept', 'application/json');
    }
    if (!requestHeaders.has('X-Requested-With')) {
        requestHeaders.set('X-Requested-With', 'XMLHttpRequest');
    }

    const csrf = getCsrfToken();
    if (csrf && !requestHeaders.has('X-CSRF-TOKEN')) {
        requestHeaders.set('X-CSRF-TOKEN', csrf);
    }

    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
        if (!requestHeaders.has('Idempotency-Key')) {
            const key = options.idempotencyKey || (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : null);
            if (key) {
                requestHeaders.set('Idempotency-Key', key);
            }
        }
    }

    let body = rest.body;

    if (json !== undefined) {
        body = JSON.stringify(json);
        if (!requestHeaders.has('Content-Type')) {
            requestHeaders.set('Content-Type', 'application/json');
        }
    } else if (formData instanceof FormData) {
        body = formData;
        // El navegador asigna automáticamente multipart/form-data con boundary
        requestHeaders.delete('Content-Type');
    }

    try {
        const response = await fetch(url, {
            method,
            headers: requestHeaders,
            body,
            signal,
            ...rest,
        });

        const status = response.status;
        let responseData = null;
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            try {
                responseData = await response.json();
            } catch {
                responseData = null;
            }
        } else {
            try {
                responseData = await response.text();
            } catch {
                responseData = null;
            }
        }

        if (response.ok) {
            return {
                ok: true,
                status,
                data: responseData,
                message: (responseData && typeof responseData === 'object' && responseData.message)
                    ? responseData.message
                    : 'Operación realizada correctamente.',
            };
        }

        return normalizeHttpError(status, responseData);
    } catch (err) {
        if (err && err.name === 'AbortError') {
            return {
                ok: false,
                status: 0,
                isAborted: true,
                message: 'Petición cancelada por el usuario.',
                errors: {},
            };
        }

        return {
            ok: false,
            status: 0,
            message: 'Error de red o conexión no disponible.',
            errors: {},
        };
    }
}

export const httpClient = {
    get: (url, options = {}) => request(url, { ...options, method: 'GET' }),
    post: (url, data, options = {}) => {
        if (data instanceof FormData) {
            return request(url, { ...options, method: 'POST', formData: data });
        }
        return request(url, { ...options, method: 'POST', json: data });
    },
    put: (url, data, options = {}) => {
        if (data instanceof FormData) {
            return request(url, { ...options, method: 'PUT', formData: data });
        }
        return request(url, { ...options, method: 'PUT', json: data });
    },
    delete: (url, options = {}) => request(url, { ...options, method: 'DELETE' }),
    request,
};
