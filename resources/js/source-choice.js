export function enhanceSourceChoices(root) {
    root.querySelectorAll('[data-source-choice]').forEach(group => {
        if (group.dataset.sourceEnhanced) return;
        group.dataset.sourceEnhanced = 'true';
        const sync = () => {
            const selected = [...group.querySelectorAll('.choice-tabs input[type="radio"]')].find(input => input.checked)?.value;
            group.querySelectorAll('[data-source-panel]').forEach(panel => {
                const active = panel.dataset.sourcePanel === selected;
                panel.hidden = !active;
                panel.querySelectorAll('input,select,textarea,button').forEach(input => { input.disabled = !active; });
            });
            const kind = group.querySelector('[data-author-kind]');
            if (kind) {
                const lecturer = selected === 'manual' && kind.value === 'dosen';
                group.querySelectorAll('[data-author-dosen]').forEach(panel => {
                    panel.hidden = !lecturer;
                    panel.querySelectorAll('input,select,button').forEach(input => { input.disabled = !lecturer; });
                });
                group.querySelector('[data-nidn]').required = lecturer;
                const identity = group.querySelector('[data-author-identity]');
                identity.hidden = lecturer;
                identity.querySelector('input').disabled = selected !== 'manual' || lecturer;
                group.querySelector('[data-source-panel="reference"] select').required = selected === 'reference';
            }
            const scopus = group.querySelector('[name="scopus_indexed"]');
            const quartile = group.querySelector('[name="scopus_quartile"]');
            if (quartile) quartile.disabled = scopus.disabled || scopus.value !== '1';
        };
        group.addEventListener('change', sync);
        sync();
    });
}

export function enhanceDocuments(root) {
    [...(root.matches?.('[data-document-fields]') ? [root] : []), ...root.querySelectorAll('[data-document-fields]')].forEach(group => {
        if (group.dataset.documentEnhanced) return;
        group.dataset.documentEnhanced = 'true';
        const kind = group.querySelector('[data-document-kind]');
        const heading = group.querySelector('[data-document-heading]');
        const sync = () => { heading.textContent = kind.selectedOptions[0]?.textContent.trim() || 'Dokumen'; };
        kind.addEventListener('change', sync);
        sync();
    });
}
