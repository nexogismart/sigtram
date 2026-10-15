(() => {
    'use strict';
    const init = () => {
        const root = document.querySelector('[data-helpdesk]');
        if (!root) return;
        const tabs = [...root.querySelectorAll('[role="tab"]')];
        const panels = {compose: root.querySelector('#hd-compose-panel'), history: root.querySelector('#hd-history-panel')};
        const show = (view, focusPanel = false) => {
            if (!panels[view]) return;
            Object.entries(panels).forEach(([key, panel]) => { panel.hidden = key !== view; });
            tabs.forEach(tab => {
                const selected = tab.dataset.helpdeskView === view;
                tab.setAttribute('aria-selected', String(selected)); tab.tabIndex = selected ? 0 : -1;
            });
            if (focusPanel) panels[view].focus({preventScroll:true});
        };
        root.addEventListener('click', event => {
            const button = event.target.closest('[data-helpdesk-view]');
            if (button) show(button.dataset.helpdeskView, button.getAttribute('role') !== 'tab');
        });
        root.querySelector('.hd-tabs').addEventListener('keydown', event => {
            const current = tabs.indexOf(document.activeElement);
            if (current < 0 || !['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
            event.preventDefault();
            const index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
                : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            show(tabs[index].dataset.helpdeskView); tabs[index].focus();
        });
        show('compose');
        const contact = root.querySelector('.hd-contact-details');
        if (matchMedia('(max-width:980px)').matches && contact.dataset.contactRequired !== 'true') contact.open = false;
        root.querySelector('#ticketForm')?.addEventListener('submit', event => {
            if (!event.defaultPrevented) return;
            const error = ['telefonoError','anydeskError'].map(id => document.getElementById(id)).find(node => node && !node.classList.contains('hidden'));
            if (error) { contact.open = true; error.scrollIntoView({block:'center', behavior:matchMedia('(prefers-reduced-motion:reduce)').matches ? 'auto' : 'smooth'}); }
        });
        const modal = document.getElementById('modal_ajax');
        if (modal) {
            const close = modal.querySelector('button[onclick="closeAjaxModal()"]');
            close?.setAttribute('aria-label', 'Cerrar detalle del ticket');
            let previousFocus;
            new MutationObserver(() => {
                if (!modal.classList.contains('hidden')) { previousFocus = document.activeElement; close?.focus(); }
                else if (previousFocus?.isConnected) previousFocus.focus({preventScroll:true});
            }).observe(modal, {attributes:true, attributeFilter:['class']});
            modal.addEventListener('keydown', event => {
                if (event.key === 'Escape') { event.preventDefault(); window.closeAjaxModal(); return; }
                if (event.key !== 'Tab') return;
                const focusable = [...modal.querySelectorAll('button,a[href],input,select,textarea,[tabindex="0"]')].filter(node => !node.disabled && node.getClientRects().length);
                const first = focusable[0], last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
            });
        }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
