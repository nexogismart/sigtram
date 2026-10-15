/**
 * Funciones puras para cálculo y renderizado de paginación de servidor (RF-02).
 */

/**
 * Calcula los metadatos de paginación para un conjunto de resultados.
 * @param {object} params
 * @param {number} params.page
 * @param {number} params.perPage
 * @param {number} params.total
 * @returns {{
 *   currentPage: number,
 *   perPage: number,
 *   total: number,
 *   totalPages: number,
 *   from: number,
 *   to: number,
 *   hasPrev: boolean,
 *   hasNext: boolean
 * }}
 */
export function calculatePagination({ page = 1, perPage = 25, total = 0 }) {
    const validPerPage = Math.max(1, Number(perPage) || 25);
    const validTotal = Math.max(0, Number(total) || 0);
    const totalPages = Math.max(1, Math.ceil(validTotal / validPerPage));
    const currentPage = Math.min(Math.max(1, Number(page) || 1), totalPages);

    const from = validTotal === 0 ? 0 : (currentPage - 1) * validPerPage + 1;
    const to = validTotal === 0 ? 0 : Math.min(currentPage * validPerPage, validTotal);

    return {
        currentPage,
        perPage: validPerPage,
        total: validTotal,
        totalPages,
        from,
        to,
        hasPrev: currentPage > 1,
        hasNext: currentPage < totalPages,
    };
}

/**
 * Genera el rango de números de página visible con puntos suspensivos si es necesario.
 * @param {number} currentPage
 * @param {number} totalPages
 * @param {number} [delta=2]
 * @returns {(number|string)[]}
 */
export function generatePageRange(currentPage, totalPages, delta = 2) {
    if (totalPages <= 1) {
        return [1];
    }

    const range = [];
    const left = Math.max(2, currentPage - delta);
    const right = Math.min(totalPages - 1, currentPage + delta);

    range.push(1);

    if (left > 2) {
        range.push('...');
    }

    for (let i = left; i <= right; i++) {
        range.push(i);
    }

    if (right < totalPages - 1) {
        range.push('...');
    }

    range.push(totalPages);

    return range;
}

/**
 * Retorna una cadena formateada con el resumen de registros mostrados.
 * @param {number} from
 * @param {number} to
 * @param {number} total
 * @returns {string}
 */
export function formatPaginationInfo(from, to, total) {
    if (!total || total <= 0) {
        return 'No se encontraron registros';
    }
    return `Mostrando ${from} a ${to} de ${total} registros`;
}
