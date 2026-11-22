// Enhance the server-rendered registration form without changing backend role enforcement.
const form = document.querySelector('[data-registration-form]');

if (form) {
    const roleSelect = form.querySelector('#requested_role');
    const identifier = form.querySelector('#identifier');
    const identifierRequired = form.querySelector('[data-identifier-required]');
    const identifierHelp = form.querySelector('[data-identifier-help]');
    const programField = form.querySelector('[data-program-field]');
    const programSelect = form.querySelector('#program_studi_id');

    const syncRole = () => {
        const role = roleSelect.value;
        const academic = role === 'mahasiswa' || role === 'dosen';
        const lecturer = role === 'dosen';

        identifier.required = academic;
        identifierRequired.hidden = !academic;
        identifier.placeholder = role === 'mahasiswa'
            ? 'Masukkan NIM'
            : lecturer
                ? 'Masukkan NIDN'
                : 'Opsional';
        identifierHelp.textContent = academic
            ? 'Nomor identitas wajib diisi untuk kategori ini.'
            : 'Opsional untuk kategori ini. Gunakan identitas resmi jika tersedia.';

        programField.hidden = !lecturer;
        programSelect.disabled = !lecturer;
        programSelect.required = lecturer;
    };

    roleSelect.addEventListener('change', syncRole);
    syncRole();
}
