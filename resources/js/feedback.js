import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

// Form workflow meminta `notes` sebagai konfirmasi di dalam dialog Swal.
// Textarea asli tetap `required` supaya request tanpa JS pun tidak lolos
// kosong; atribut itu hanya dilepas sementara saat dialog mengambil alih
// validasi, lalu dipasang balik bila dialog dibatalkan.
const confirmed = new WeakSet();
const pending = new WeakSet();
const originals = new WeakMap();
const reset = (form) => {
    form.removeAttribute('aria-busy');
    delete form.dataset.submitting;
    form.querySelectorAll('[data-submit-lock]').forEach(button => {
        button.disabled = false;
        button.removeAttribute('data-submit-lock');
        if (originals.has(button)) button.replaceChildren(...originals.get(button));
    });
    form.querySelector('[data-submit-status]')?.remove();
    form.querySelector('[data-submitter-value]')?.remove();
};

document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get' || form.method === 'dialog' || event.defaultPrevented) return;
    if (form.dataset.submitting || pending.has(form)) { event.preventDefault(); return; }
    const submitter = event.submitter;
    if (form.dataset.confirm && !confirmed.has(form)) {
        event.preventDefault();
        pending.add(form);
        try {
            const notes = form.dataset.requiresNotes === 'true' ? form.querySelector('[name="notes"]') : null;
            const result = await Swal.fire({
                input: notes ? 'textarea' : undefined,
                inputLabel: notes ? 'Alasan / catatan' : undefined,
                inputValue: notes?.value || '',
                inputAttributes: { maxlength: '4000', placeholder: 'Jelaskan bagian yang perlu ditindaklanjuti.' },
                inputValidator: notes ? value => !value.trim() ? 'Isi alasan sebelum melanjutkan.' : value.trim().length > 4000 ? 'Alasan maksimal 4.000 karakter.' : undefined : undefined,
                showClass: { popup: 'repository-dialog-open' },
                hideClass: { popup: 'repository-dialog-closed' },
                titleText: form.dataset.confirmTitle || 'Konfirmasi tindakan',
                text: form.dataset.confirm,
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmLabel || submitter?.textContent.trim() || 'Lanjutkan',
                cancelButtonText: 'Batal',
                focusCancel: true,
                buttonsStyling: false,
                customClass: { popup: 'repository-alert', confirmButton: form.dataset.danger === 'true' ? 'button button-danger' : 'button', cancelButton: 'button button-secondary', actions: 'confirmation-actions' },
            });
            pending.delete(form);
            if (result.isConfirmed && form.isConnected) {
                if (notes) {
                    notes.value = String(result.value).trim();
                    // Dialog sudah memvalidasi isi; lepas `required` sekali agar
                    // submit lanjutan tidak terblokir native constraint bila
                    // textarea tersembunyi/tersembunyi-karena-layout.
                    notes.required = false;
                }
                confirmed.add(form);
                form.requestSubmit(submitter || undefined);
            }
        } finally { pending.delete(form); }
        return;
    }
    confirmed.delete(form);
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    // Preserve named submitters before disabling; disabled controls are omitted by the browser.
    if (submitter?.name) {
        const value = document.createElement('input');
        value.type = 'hidden'; value.name = submitter.name; value.value = submitter.value;
        value.dataset.submitterValue = 'true'; form.append(value);
    }
    {
        form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])').forEach(button => {
            if (!button.disabled) { button.dataset.submitLock = 'true'; button.disabled = true; }
        });
        if (submitter?.tagName === 'BUTTON') {
            originals.set(submitter, [...submitter.childNodes].map(node => node.cloneNode(true)));
            const spinner = document.createElement('span');
            spinner.className = 'loading-spinner';
            spinner.setAttribute('aria-hidden', 'true');
            const label = document.createElement('span');
            label.className = 'submit-label';
            label.textContent = form.enctype === 'multipart/form-data' ? 'Mengunggah…' : 'Memproses…';
            submitter.replaceChildren(spinner, label);
        }
        const status = document.createElement('p');
        status.className = 'sr-only submit-status';
        status.dataset.submitStatus = 'true';
        status.setAttribute('role', 'status');
        status.textContent = form.enctype === 'multipart/form-data' ? 'File sedang dikirim dan diperiksa. Tunggu sampai proses selesai.' : 'Permintaan sedang diproses.';
        form.append(status);
    }
});
window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach(reset));

const summary = document.querySelector('[data-error-summary]');
if (summary) {
    summary.focus();
    summary.querySelectorAll('[data-error-field]').forEach(item => {
        const name = item.dataset.errorField.replace(/\.([^.[\]]+)/g, '[$1]');
        const field = [...document.querySelectorAll('input,select,textarea')].find(input => input.name === name && input.type !== 'hidden');
        if (!field) return;
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), item.id].filter(Boolean).join(' '));
        const link = document.createElement('a');
        link.href = '#'+(field.id || summary.id);
        link.textContent = item.textContent;
        link.addEventListener('click', event => {
            event.preventDefault();
            for (let parent = field.parentElement; parent; parent = parent.parentElement) if (parent.tagName === 'DETAILS') parent.open = true;
            field.dispatchEvent(new CustomEvent('wizard:reveal', { bubbles: true, detail: field }));
            field.focus();
            field.scrollIntoView({block:'center',behavior:'auto'});
        });
        item.replaceChildren(link);
    });
}
document.querySelectorAll('[data-dismiss-notice]').forEach(button => button.addEventListener('click', () => button.closest('.notice').remove()));
document.addEventListener('change', event => {
    const input = event.target;
    if (!input.matches('input[type="file"]')) return;
    let hint = input.parentElement.querySelector('[data-file-info]');
    if (!hint) {
        hint = document.createElement('p'); hint.className = 'helper'; hint.dataset.fileInfo = 'true';
        hint.setAttribute('role', 'status'); input.after(hint);
    }
    const file = input.files[0];
    hint.textContent = file ? `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB` : '';
});

// Warn only for edited metadata forms; successful submission permits navigation.
const dirtyForms = new Set();
document.querySelectorAll('form[data-unsaved-guard]').forEach(form => {
    form.addEventListener('input', () => dirtyForms.add(form));
    form.addEventListener('change', () => dirtyForms.add(form));
});
window.addEventListener('beforeunload', event => {
    if ([...dirtyForms].some(form => !form.dataset.submitting)) {
        event.preventDefault();
        event.returnValue = '';
    }
});
