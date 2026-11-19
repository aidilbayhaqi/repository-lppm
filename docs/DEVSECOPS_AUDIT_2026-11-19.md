# OSDJ-LPPM — audit dan baseline DevSecOps (2026-11-19)

## Sumber & batas verifikasi

Audit didasarkan pada arsip `Repository-LPPM 1.6 (2).zip` yang tersedia dalam percakapan, **bukan** hasil `git status` dari Windows pengguna. Berkas lokal setelah updater 1.6.5–1.8.1.3, versi branch/commit, status deployment, secrets runtime, dan kesehatan database tidak bisa dikonfirmasi. Setiap temuan di bawah hanya dianggap **confirmed pada arsip tersebut**, bukan pada semua instalasi.

| Severity | Bukti pada arsip | Risiko | Tindak lanjut |
|---|---|---|---|
| CRITICAL | ZIP berisi `.env`, `storage` runtime, backup/updater, dan file proyek lain | Distribusi ZIP yang sama ke pihak lain dapat mengekspos data/kunci aplikasi | Jangan distribusikan arsip lama. Gunakan clean-release yang telah dipindai, evaluasi eksposur historis. |
| HIGH | `.dockerignore` belum mengecualikan `.lppm-updater-backups`, folder updater dan arsip ZIP lokal | Dockerfile menjalankan `COPY . .` sehingga context yang lolos ignore bisa masuk ke image | Perluasan exclusion `.dockerignore` dan CI policy gate pada change set ini. |
| MEDIUM | `.github/workflows/ci.yml` sudah menguji SQLite, MySQL, build frontend, audit Composer/npm, dan build Docker | Belum ada inspeksi path ZIP/release dan kontrak `.dockerignore` | Tambahkan gate read-only, **jangan ganti pipeline yang ada**. |
| MEDIUM | `compose.production.yml` memiliki app1/app2, Redis, volume dokumen, ClamAV; nginx `least_conn` | Berkas ini tidak membuktikan dua replica berjalan, shared Redis benar, AV sehat, atau storage tahan gagal | Uji jalur staging nyata dan failover dengan data uji. |
| HIGH | Dokumentasi operasi/backup ada; tidak ada bukti restore hasil uji yang tersedia | Backup tidak dapat dianggap bisa dipulihkan | Restore terisolasi dengan hasil dan checksum yang ditandatangani operator. |
| HIGH | Health endpoint memeriksa DB, cache, dan disk; log request sudah dicatat | Liveness/readiness dan alarm penerimaan belum terbukti | Uji staging, aktifkan monitoring dan alert; jangan klaim sudah aktif. |
| HIGH | Kontrol RBAC, session, PDF, transaksi publishing telah memiliki test historis | Belum membuktikan sumber Windows saat ini dan pengujian integrasi mutakhir | Jalankan semua test pada commit dan DB testing aktual; tinjau hasil. |

## Perubahan tahap ini

1. Tambahkan exclusion yang belum ada ke `.dockerignore` tanpa mengubah aturan terdahulu.
2. Tambahkan `tools/release-safety.mjs` untuk dua gate read-only:
   - `--source .`: periksa bahwa Docker context memiliki pola perlindungan minimum;
   - `--archive path.zip`: periksa daftar entri ZIP untuk path sensitif (tanpa membuka konten berkas).
3. Tambahkan 14 regresi Node tanpa dependency eksternal.
4. Sisipkan gate pada dua job CI yang sudah ada, dengan pemeriksaan checksum pada file workflow sebelum menulis.
5. Sediakan updater `CHECK ONLY` default dengan backup, verifikasi payload dan rollback file di saat terjadi kegagalan.

**Bukan pengganti** secret scanning isi file, verifikasi efektif Docker build context, vulnerability scanning, SAST/DAST, penetration test, monitoring aktif, atau restore test.

## Langkah produksi yang belum terverifikasi

- Tentukan commit/rilis aktual; cocokkan daftar updater yang diterapkan dan `git status`.
- Jalankan PHPunit dan integration test MySQL pada database **dedicated**, build Vite, dan dependency audit dari commit yang sama.
- Audit auth/authorization, private file download, upload, dan audit history pada hasil sumber terbaru.
- Ukur query catalog/search/dashboard dengan log query yang disanitasi, EXPLAIN ANALYZE pada data uji, dan p95 HTTP; jangan menyimpulkan load balancing dibutuhkan dari kode saja.
- Bangun staging terpisah (secret, DB, dokumen, domain TLS, dan volumes); validasi cache/session silang replica, ClamAV, SMTP, backup/restore, serta notifikasi alarm.
- Rilis production harus butuh persetujuan manual, snapshot/backup, rollback terlatih, dan health check sesudah rilis.

## Status

**NO-GO**: release safety gate di source hanya menutup sebagian risiko packaging. Bukti staging hidup, isolated integration tests terkini, perbaikan risiko kritis, monitor/alarm, dan restore tervalidasi belum ada.
