/**
 * Controlador de modales accesible con gestión de foco y tecla Escape (RF-02 / RF-04).
 */

const FOCUSABLE_SELECTOR = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

let activeModalsStack = [];
let escapeListenerRegistered = false;

function handleGlobalKeydown(e) {
    if (e.key === 'Escape' && activeModalsStack.length > 0) {
        const topModal = activeModalsStack[activeModalsStack.length - 1];
        if (topModal && typeof topModal.close === 'function') {
            e.preventDefault();
            topModal.close();
        }
    }
}

/**
 * Abre un modal gestionando el foco y estado de accesibilidad.
 * @param {HTMLElement|string} modalOrSelector
 * @param {object} [options={}]
 * @returns {{ close: Function }}
 */
export function openModal(modalOrSelector, options = {}) {
    if (typeof document === 'undefined') {
        return { close: () => {} };
    }

    const modal = typeof modalOrSelector === 'string'
        ? document.querySelector(modalOrSelector)
        : modalOrSelector;

    if (!modal) {
        return { close: () => {} };
    }

    const previousActiveElement = document.activeElement;

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    // Mover foco al primer elemento interactivo del modal
    const focusTarget = options.initialFocus
        ? modal.querySelector(options.initialFocus)
        : modal.querySelector(FOCUSABLE_SELECTOR);

    if (focusTarget && typeof focusTarget.focus === 'function') {
        setTimeout(() => focusTarget.focus(), 50);
    }

    const modalEntry = {
        element: modal,
        close: () => closeModal(modal, previousActiveElement),
    };

    activeModalsStack.push(modalEntry);

    if (!escapeListenerRegistered) {
        document.addEventListener('keydown', handleGlobalKeydown);
        escapeListenerRegistered = true;
    }

    return modalEntry;
}

/**
 * Cierra un modal y restaura el foco al elemento anterior.
 * @param {HTMLElement|string} modalOrSelector
 * @param {HTMLElement} [restoreFocusElement]
 */
export function closeModal(modalOrSelector, restoreFocusElement) {
    if (typeof document === 'undefined') {
        return;
    }

    const modal = typeof modalOrSelector === 'string'
        ? document.querySelector(modalOrSelector)
        : modalOrSelector;

    if (!modal) {
        return;
    }

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');

    activeModalsStack = activeModalsStack.filter((entry) => entry.element !== modal);

    if (activeModalsStack.length === 0 && escapeListenerRegistered) {
        document.removeEventListener('keydown', handleGlobalKeydown);
        escapeListenerRegistered = false;
    }

    if (restoreFocusElement && typeof restoreFocusElement.focus === 'function') {
        restoreFocusElement.focus();
    }
}

/**
 * Atrapa la navegación de Tab dentro del modal activo.
 * @param {HTMLElement} modal
 * @param {KeyboardEvent} e
 */
export function trapFocus(modal, e) {
    if (e.key !== 'Tab' || !modal) return;

    const focusables = modal.querySelectorAll(FOCUSABLE_SELECTOR);
    if (focusables.length === 0) {
        e.preventDefault();
        return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}
