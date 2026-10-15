/**
 * Presentador semántico puro de estados para Trámite Interno y Externo (RA-08).
 *
 * Mapeo canónico:
 * - espera/pendiente: ámbar (bg-amber-50 text-amber-800 border-amber-200)
 * - asignado/derivado: azul (bg-blue-50 text-blue-800 border-blue-200)
 * - recibido: verde (bg-emerald-50 text-emerald-800 border-emerald-200)
 * - archivado/finalizado: pizarra (bg-slate-100 text-slate-800 border-slate-300)
 * - devuelto/rechazado/anulado: rojo (bg-rose-50 text-rose-800 border-rose-200)
 * - desconocido: gris neutro (bg-gray-100 text-gray-700 border-gray-200)
 */

export function getEstadoBadgeClass(estadoId, estadoNombre) {
    const id = Number(estadoId || 0);
    const nombre = String(estadoNombre || '').toUpperCase().trim();

    // 1. Familia Espera / Pendiente (Ámbar)
    if (
        id === 1 ||
        id === 51 ||
        id === 53 ||
        nombre.includes('PENDIENTE') ||
        nombre.includes('ESPERA') ||
        nombre.includes('REITERATIVO')
    ) {
        return 'bg-amber-50 text-amber-800 border border-amber-200';
    }

    // 2. Familia Ingreso / Asignación / Transferencia / Derivado (Azul)
    if (
        id === 7 ||
        id === 17 ||
        nombre.includes('ASIGNADO') ||
        nombre.includes('DERIVADO') ||
        nombre.includes('INGRESADO') ||
        nombre.includes('ENVIADO') ||
        nombre.includes('TRANSFERIDO')
    ) {
        return 'bg-blue-50 text-blue-800 border border-blue-200';
    }

    // 3. Familia Recepción / Aprobación / Realizado (Verde)
    if (
        id === 2 ||
        id === 23 ||
        (nombre.includes('RECIBIDO') && !nombre.includes('NO RECIBIDO')) ||
        (nombre.includes('RECEPCIONADO') && !nombre.includes('NO RECEPCIONADO')) ||
        nombre.includes('APROBADO') ||
        nombre.includes('REALIZADO')
    ) {
        return 'bg-emerald-50 text-emerald-800 border border-emerald-200';
    }

    // 4. Familia Finalización / Archivo (Pizarra / Slate)
    if (
        id === 3 ||
        id === 24 ||
        id === 26 ||
        id === 27 ||
        nombre.includes('FINALIZADO') ||
        nombre.includes('ARCHIVADO')
    ) {
        return 'bg-slate-100 text-slate-800 border border-slate-300';
    }

    // 5. Familia Devolución / Rechazo / Anulación / No recepción (Rojo)
    if (
        id === 4 ||
        id === 5 ||
        id === 6 ||
        id === 29 ||
        nombre.includes('DEVUELTO') ||
        nombre.includes('RECHAZADO') ||
        nombre.includes('ANULADO') ||
        nombre.includes('NO RECEPCIONADO')
    ) {
        return 'bg-rose-50 text-rose-800 border border-rose-200';
    }

    // 6. Desconocido o neutro (Gris)
    return 'bg-gray-100 text-gray-700 border border-gray-200';
}
