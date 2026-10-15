/* Accessible description validation; submission stays server-owned. */
(() => {
    'use strict';
    // Keep validation compatible with drafts made before the writing guide was removed.
    const legacyGuideHeadings = ['Qué ocurre', 'Dónde y desde cuándo', 'Cómo afecta tu trabajo', 'Qué cambio necesitas', 'Dónde se debe aplicar', 'Qué resultado esperas', 'Qué necesitas saber', 'En qué tarea estás', 'Qué revisaste'];
    // Empty guide headings are not a completed description. Text comes from the DOM, not HTML.
    const hasDescription = text => {
        let content = text;
        legacyGuideHeadings.forEach(title => { content = content.split(title).join(''); });
        return /[\p{L}\p{N}]/u.test(content);
    };
    if (typeof module !== 'undefined' && module.exports) module.exports = {hasDescription};
    if (typeof document === 'undefined') return;

    const init = () => {
        const form = document.getElementById('ticketForm');
        if (!form) return;
        const source = form.querySelector('#editor');
        const writing = form.querySelector('.hd-writing');
        const error = writing.querySelector('#descripcionError');
        let editor, descriptionTouched = false;
        const readText = () => {
            const html = editor ? editor.getData() : source.value;
            return new DOMParser().parseFromString(html, 'text/html').body.textContent || '';
        };
        const editable = () => editor ? editor.ui.getEditableElement() : source;
        const updateError = () => {
            const invalid = !hasDescription(readText());
            error.hidden = !descriptionTouched || !invalid;
            if (editor) editor.editing.view.change(writer => writer.setAttribute('aria-invalid', String(descriptionTouched && invalid), editor.editing.view.document.getRoot()));
            else source.setAttribute('aria-invalid', String(descriptionTouched && invalid));
            writing.classList.toggle('has-error', descriptionTouched && invalid);
            return invalid;
        };
        const onDescriptionChange = () => { if (descriptionTouched) updateError(); };
        const attachEditor = instance => {
            editor = instance;
            const field = editable();
            editor.editing.view.change(writer => {
                const root = editor.editing.view.document.getRoot();
                writer.setAttribute('aria-labelledby', 'hd-description-label', root);
                writer.setAttribute('aria-describedby', 'hd-description-help descripcionError', root);
                writer.setAttribute('aria-required', 'true', root);
            });
            field.addEventListener('blur', () => { descriptionTouched = true; updateError(); });
            editor.model.document.on('change:data', onDescriptionChange);
        };
        document.addEventListener('sigtram:helpdesk-editor-ready', event => attachEditor(event.detail.editor), {once:true});
        source.setAttribute('aria-labelledby', 'hd-description-label');
        source.setAttribute('aria-describedby', 'hd-description-help descripcionError');
        source.setAttribute('aria-required', 'true');
        source.addEventListener('input', onDescriptionChange);
        source.addEventListener('blur', () => { descriptionTouched = true; updateError(); });

        // Capture prevents empty descriptions before the legacy handler prepares the submission.
        form.addEventListener('submit', event => {
            descriptionTouched = true;
            if (updateError()) event.preventDefault();
            // Wait for all validation listeners and layout before focusing a field in closed details.
            requestAnimationFrame(() => {
                if (!event.defaultPrevented) return;
                const first = [['telefono_contacto','telefonoError'],['anydesk_contacto','anydeskError'],['asunto','asuntoError'],['incidencia','incidenciaError']]
                    .filter(([, id]) => !document.getElementById(id).classList.contains('hidden'))
                    .map(([id]) => document.getElementById(id))[0];
                const target = first || (!error.hidden ? editable() : null);
                if (target) {
                    target.closest('details')?.setAttribute('open','');
                    target.focus();
                }
            });
        }, true);

        [['asunto','asuntoError'],['incidencia','incidenciaError'],['telefono_contacto','telefonoError'],['anydesk_contacto','anydeskError']].forEach(([id, errorId]) => {
            const field = document.getElementById(id), message = document.getElementById(errorId);
            if (!field || !message) return;
            field.setAttribute('aria-describedby', errorId);
            field.setAttribute('aria-required','true');
            const sync = () => field.setAttribute('aria-invalid', String(!message.classList.contains('hidden')));
            new MutationObserver(sync).observe(message,{attributes:true,attributeFilter:['class']});
            sync();
        });
        document.getElementById('urgencia-descripcion').setAttribute('aria-label','Motivo de la prioridad urgente');
        const files = form.querySelector('#archivos');
        const list = document.createElement('ul'); list.className = 'hd-selected-files'; list.setAttribute('aria-live','polite');
        files.after(list);
        files.addEventListener('change', () => {
            list.replaceChildren();
            [...files.files].forEach(file => {
                const item = document.createElement('li');
                item.textContent = `${file.name} (${Math.max(1, Math.ceil(file.size / 1024))} KB)`;
                list.append(item);
            });
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
