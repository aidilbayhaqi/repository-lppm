// Progressive enhancement: a native select remains the submitted value and keyboard control.
export function enhanceLookups(root) {
    root.querySelectorAll('select[data-lookup]:not([data-enhanced])').forEach((select) => {
        select.dataset.enhanced = 'true';
        const wrapper = document.createElement('div');
        wrapper.className = 'lookup-field';
        select.before(wrapper);
        wrapper.append(select);
        const input = document.createElement('input');
        input.type = 'search';
        input.maxLength = 100;
        input.placeholder = 'Cari awal nama atau ID…';
        input.autocomplete = 'off';
        const label = select.labels?.[0]?.textContent.trim() || select.getAttribute('aria-label') || 'referensi';
        input.setAttribute('aria-label', `Cari ${label}`);
        const hint = document.createElement('span');
        hint.className = 'lookup-status';
        hint.setAttribute('role', 'status');
        hint.textContent = 'Cari referensi berdasarkan nama atau ID.';
        hint.setAttribute('aria-live', 'polite');
        const more = document.createElement('button');
        more.type = 'button';
        more.className = 'text-button lookup-more';
        more.textContent = 'Hasil berikutnya';
        more.hidden = true;
        wrapper.prepend(input);
        wrapper.append(hint, more);
        let controller;
        let timer;
        let page = 1;
        let sequence = 0;

        const fetchOptions = async (append = false) => {
            const requestNumber = ++sequence;
            controller?.abort();
            controller = new AbortController();
            const requestedPage = append ? page + 1 : 1;
            hint.textContent = '';
            hint.classList.add('is-loading');
            hint.setAttribute('aria-label', 'Mencari referensi');
            more.disabled = true;
            const url = new URL(select.dataset.lookup, location.origin);
            url.searchParams.set('q', input.value.trim());
            url.searchParams.set('page', String(requestedPage));
            try {
                const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (response.status === 401 || response.status === 403) throw new Error('session');
                if (response.status === 429) throw new Error('rate-limit');
                if (!response.ok) throw new Error('lookup-failed');
                const result = await response.json();
                if (!Array.isArray(result.data) || typeof result.has_more !== 'boolean') throw new Error('invalid-response');
                if (requestNumber !== sequence) return;
                page = requestedPage;
                const current = select.selectedOptions[0]?.cloneNode(true);
                const blank = [...select.options].find((option) => option.value === '')?.cloneNode(true);
                if (!append) {
                    select.replaceChildren();
                    if (blank) select.append(blank);
                    if (current?.value) select.append(current);
                }
                const present = new Set([...select.options].map((option) => option.value));
                result.data.forEach((item) => {
                    if (!present.has(String(item.id))) select.add(new Option(item.label, String(item.id)));
                });
                if (current) select.value = current.value;
                hint.textContent = result.data.length ? `${result.data.length} hasil. Pilih dari daftar.` : 'Tidak ada hasil. Coba awal nama atau ID lain.';
                more.hidden = !result.has_more;
            } catch (error) {
                if (error.name !== 'AbortError' && requestNumber === sequence) {
                    hint.textContent = error.message === 'session' ? 'Sesi berakhir. Masuk kembali, lalu ulangi pencarian.' : error.message === 'rate-limit' ? 'Terlalu banyak pencarian. Coba kembali sebentar lagi.' : !navigator.onLine ? 'Koneksi terputus. Periksa internet lalu coba lagi.' : 'Referensi belum dapat dimuat. Coba lagi.';
                }
            } finally {
                if (requestNumber === sequence) { more.disabled = false; hint.classList.remove('is-loading'); hint.removeAttribute('aria-label'); }
            }
        };
        input.addEventListener('input', () => {
            clearTimeout(timer);
            controller?.abort();
            sequence++;
            more.hidden = true;
            timer = setTimeout(() => fetchOptions(), 300);
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') { event.preventDefault(); clearTimeout(timer); fetchOptions(); }
            if (event.key === 'ArrowDown') { event.preventDefault(); select.focus(); }
        });
        more.addEventListener('click', () => { fetchOptions(true); });
    });
}
