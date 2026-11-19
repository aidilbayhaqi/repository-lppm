const watch = document.querySelector('[data-publish-watch]');
if (watch) {
    let attempts = 0;
    const message = watch.querySelector('[data-publish-message]');
    const refresh = watch.querySelector('[data-publish-refresh]');
    const check = async () => {
        if (document.hidden) { setTimeout(check, 15000); return; }
        attempts++;
        try {
            const response = await fetch(watch.dataset.publishWatch, { headers: {Accept:'application/json'}, credentials:'same-origin', signal:AbortSignal.timeout(10000) });
            if (response.status === 401 || response.status === 403) {
                message.textContent = 'Sesi berakhir atau akses berubah. Muat ulang halaman untuk melanjutkan.';
                refresh.hidden = false; return;
            }
            if (response.status === 429) {
                message.textContent = 'Pemeriksaan terlalu sering. Muat ulang halaman nanti.';
                refresh.hidden = false; return;
            }
            if (!response.ok) throw new Error('status unavailable');
            const result = await response.json();
            if (!result || typeof result.status !== 'string' || !Number.isInteger(Number(result.version))) {
                throw new Error('invalid status response');
            }
            if (result.status !== 'publishing' || String(result.version) !== watch.dataset.version) {
                message.textContent = result.status === 'published' ? 'Karya sudah terbit.' : 'Status karya berubah. Muat ulang untuk melihat hasil dan tindakan berikutnya.';
                refresh.hidden = false; return;
            }
            message.textContent = 'Masih dalam antrean. Pemeriksaan berikutnya dalam 15 detik.';
        } catch {
            message.textContent = navigator.onLine ? 'Status belum bisa diperiksa. Sistem akan mencoba lagi.' : 'Koneksi terputus. Periksa internet lalu muat ulang.';
        }
        if (attempts < 20) setTimeout(check, 15000);
        else { message.textContent = 'Pemeriksaan otomatis dihentikan setelah beberapa menit. Muat ulang status atau hubungi pengelola.'; refresh.hidden = false; }
    };
    setTimeout(check, 15000);
}
