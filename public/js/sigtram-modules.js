/* Progressive presentation only: no requests, permission changes or form interception. */
(() => {
    'use strict';
    const body = document.body;
    if (!body.classList.contains('sigtram-module')) return;
    const storage = {
        get(key) { try { return localStorage.getItem(key); } catch (_) { return null; } },
        set(key, value) { try { localStorage.setItem(key, value); } catch (_) {} },
    };
    const densityKey = 'sigtram.tables.density';
    function setDensity(value) {
        body.dataset.density = value === 'compact' ? 'compact' : 'comfortable';
        document.querySelectorAll('[data-sigtram-density]').forEach(button => {
            button.setAttribute('aria-pressed', String(body.dataset.density === 'compact'));
            button.textContent = body.dataset.density === 'compact' ? 'Vista cómoda' : 'Vista compacta';
        });
    }
    function syncTheme() {
        let theme = storage.get('nexosigtram.theme') || 'light';
        try { if (window.parent !== window) theme = window.parent.document.documentElement.dataset.theme || theme; } catch (_) {}
        body.dataset.theme = theme === 'dark' ? 'dark' : 'light';
    }
    if (window.parent !== window) document.documentElement.classList.add('sigtram-embedded');
    setDensity(storage.get(densityKey));
    syncTheme();
    document.querySelectorAll('[data-sigtram-density]').forEach(button => button.addEventListener('click', () => {
        setDensity(body.dataset.density === 'compact' ? 'comfortable' : 'compact');
        storage.set(densityKey, body.dataset.density);
    }));
    window.addEventListener('storage', event => {
        if (event.key === 'nexosigtram.theme') syncTheme();
        if (event.key === densityKey) setDensity(event.newValue);
    });
    try {
        if (window.parent !== window) new MutationObserver(syncTheme).observe(window.parent.document.documentElement, { attributes:true, attributeFilter:['data-theme'] });
    } catch (_) {}
    const processedTables = new WeakSet();
    const iconLabels = [
        ['fa-eye', 'Ver detalle'], ['fa-pen', 'Editar'], ['fa-edit', 'Editar'],
        ['fa-trash', 'Eliminar'], ['fa-download', 'Descargar'], ['fa-print', 'Imprimir'],
        ['fa-times', 'Cerrar'], ['fa-xmark', 'Cerrar'], ['fa-chevron-left', 'Anterior'],
        ['fa-chevron-right', 'Siguiente'], ['fa-search', 'Buscar'], ['fa-magnifying-glass', 'Buscar'],
    ];
    function enhance(root) {
        const hasTables = Boolean(root.querySelector('table:not([role="presentation"])'));
        document.querySelectorAll('[data-sigtram-density]').forEach(button => { button.hidden = !hasTables; });
        root.querySelectorAll('table').forEach(table => {
            if (processedTables.has(table) || table.closest('[role=presentation],.dataTables_wrapper,.dt-container,.leaflet-container') || !table.querySelector('thead th')) return;
            processedTables.add(table);
            const title = table.getAttribute('aria-label') || document.querySelector('h1')?.textContent.trim() || 'Resultados';
            let parent = table.parentElement;
            const style = getComputedStyle(parent);
            const alreadyScrolls = /auto|scroll/.test(style.overflowX) || /auto|scroll/.test(style.overflowY);
            if (!alreadyScrolls && !table.closest('.modal,.modal-content') && !table.matches('[data-no-wrap]')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'sigtram-table-scroll';
                table.before(wrapper); wrapper.append(table); parent = wrapper;
            }
            if (alreadyScrolls || parent.classList.contains('sigtram-table-scroll')) {
                parent.setAttribute('tabindex', '0');
                if (!parent.hasAttribute('aria-label')) parent.setAttribute('aria-label', `${title}: tabla desplazable`);
                if (!parent.hasAttribute('role')) parent.setAttribute('role', 'region');
            }
            table.querySelectorAll('thead th').forEach(th => { if (!th.hasAttribute('scope') && !th.hasAttribute('colspan') && !th.hasAttribute('rowspan')) th.setAttribute('scope', 'col'); });
        });
        root.querySelectorAll('button,a').forEach(control => {
            if (control.hasAttribute('aria-label') || control.textContent.trim() || control.hasAttribute('x-text') || control.querySelector('input')) return;
            const known = iconLabels.find(([icon]) => control.querySelector(`.${icon}`));
            const label = control.getAttribute('title') || known?.[1];
            if (label) control.setAttribute('aria-label', label);
        });
    }
    enhance(document);
    let queued = false;
    new MutationObserver(records => {
        if (queued || !records.some(record => record.addedNodes.length)) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; enhance(document); });
    }).observe(body, { childList:true, subtree:true });
})();
