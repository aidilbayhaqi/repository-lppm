# Validasi 1.6.0 — 13 November 2026

Input: `Repository-LPPM-1.5(1).zip`, source terbaru yang tersedia. Baseline: **128 tes / 669 assertions lulus** sebelum perubahan. Hasil akhir: **144 tes / 774 assertions lulus**. Pengujian memakai PHP 8.5.12, Laravel 13.40.0, Node 24.22.1 dan database SQLite terisolasi.

| Pemeriksaan | Hasil aktual |
| --- | --- |
| Backend | 144 tes, 774 assertions lulus; tanpa kegagalan |
| Browser | 37 pemeriksaan Chromium Headless Shell lulus; tidak ada JavaScript exception atau respons HTTP 5xx |
| Responsive | Landing 1440, 390, 320 px; form lampiran dan detail 320/390 px tanpa page overflow |
| Input | Tahap aktif tunggal, judul/NIDN wajib, penulis baru pada repeater, mode referensi/manual, label Cover/Sertifikat, simpan dua PDF, edit mempertahankan file |
| Loading | Spinner tampil dan tombol simpan terkunci; navigasi ditunda hanya oleh harness pengujian agar indikator sempat diperiksa |
| Admin | Alasan wajib untuk pemeriksaan karya sendiri, audit tercatat; verifier yang terkait tetap ditolak |
| Excel | Template XLSX diunduh lewat browser, dibuka/disimpan oleh openpyxl, diunggah, preview nama penulis, commit sebagai draf, hasil muncul di daftar |
| Format XLSX | Reader independen openpyxl berhasil membaca 39 header, sheet Data, freeze A2, format Text |
| Integritas | Rollback referensi/metadata/file setelah PDF kedua gagal; rollback seluruh import saat konflik muncul setelah preview; import idempotent; stale/unauthorized edit tetap ditolak |
| Validasi identitas | NIDN 10 digit, konflik nama/NIDN, author duplicate, nested privilege injection, prodi nullable, referensi yang berganti nama |
| Import | CSV lama, nama baru/ID, kolom selaras, NIDN nol awal, DOI/ISBN, rank manual, sheet relationship, tanggal serial Excel, formula ditolak |
| Quality | Pint lulus, composer validate --strict lulus, git diff --check bersih |
| Build | Vite build, Blade view cache, route cache berhasil; route cache lokal dibersihkan setelah pemeriksaan |
| Migrasi | Seluruh migration berjalan pada SQLite QA baru; data baseline di fixture dipertahankan oleh alur edit/upgrade |

Tidak ada dependency yang ditambah/diganti. Audit advisory dependency tidak dijalankan ulang untuk 1.6; laporan audit rilis sebelumnya di bawah bersifat historis.

**Batas verifikasi:** Docker tidak tersedia pada runtime ini, sehingga image/container, MySQL 8.4 concurrency dan EXPLAIN, Redis multi-replica, ClamAV/SMTP nyata, TLS, load test, serta backup/restore server tujuan belum diuji. Browser memakai Chromium dengan emulasi viewport; Excel desktop, LibreOffice, Safari/Firefox, perangkat fisik, dan audit aksesibilitas penuh belum diuji. Status paket tetap kandidat staging, bukan bukti go-live tanpa penerimaan infrastruktur.

Panduan upgrade ada di DOCKER_LOCAL.md. Jalankan migration 1.6 dan build assets/image sebelum membuka aplikasi. Pertahankan APP_KEY, .env, database, dan volume lama; jangan menggunakan migrate:fresh untuk upgrade.

## Bukti rilis sebelumnya (historis)

### Validasi 1.5.0 — 10 November 2026

Input: ZIP `Repository-LPPM-1.4 (2).zip` modifikasi pengguna. Baseline source ini: **117 tes / 604 assertions**. Hasil akhir: **128 tes / 669 assertions lulus** pada PHP 8.5.12, Laravel 13.40.0 dan SQLite terisolasi. Node 24.22.1 serta Chromium Headless Shell digunakan untuk frontend.

| Pemeriksaan | Hasil |
| --- | --- |
| Regression backend | 128 tes, 669 assertions lulus |
| Cakupan baru | Metadata manual/referensi, pasangan peringkat, perpindahan mode, stale edit, filter+visibility, pagination audit/attempt/verifikasi, backfill migration, Remember me dan revocation |
| Browser | 38 pemeriksaan lolos; hasil per skenario di validation-results.json |
| Viewport | 1440, 390 dan 320 px; beranda, filter, form dan detail tanpa overflow halaman pada skenario yang dicoba |
| Interaksi | Filter gabungan SINTA+Scopus+prodi, pagination riwayat, konfirmasi batal/alasan wajib, revisi dengan catatan, simpan jurnal manual, mode referensi/manual, cookie recall dan login sesi baru |
| Browser error | Tidak ada JavaScript page error atau HTTP 5xx pada skenario akhir |
| Aset | Build Vite berhasil; foto WebP lokal ikut paket |
| PHP / source | Composer validate --strict, platform non-dev, Pint, git diff --check lulus |
| Cache | Route cache dan Blade view cache berhasil; cache lokal dibersihkan setelah pemeriksaan |
| Dependency audit | Composer 0 advisory/abandoned; npm 0 vulnerability pada pemeriksaan 10 November 2026; lockfile tidak berubah |
| Migration | Instalasi SQLite baru+seed serta upgrade/backfill legacy diuji; data pengguna tidak dipakai sebagai database pengujian |
| Docker | Kedua Compose valid secara parser YAML; daemon Docker tidak tersedia, build/run container belum dijalankan |

Browser QA menemukan dan memperbaiki overflow grid hero mobile serta listener Scopus yang semula ikut terpasang pada halaman filter. Pengujian memakai data demo sintetis, bukan data karya nyata. Alur penerbitan langsung dalam source pengguna dipertahankan; tidak dikembalikan ke alur yang menunggu worker.

**Batas verifikasi:** ini kandidat staging. Docker/PHP 8.4 target, MySQL concurrency dan EXPLAIN, Redis multi-replica, ClamAV/SMTP nyata, load test, TLS dan restore operasional belum diuji pada rilis ini. Chromium memakai emulasi viewport; Safari/Firefox, perangkat fisik dan audit aksesibilitas penuh belum dijalankan. Hasil dependency audit bukan jaminan bebas celah keamanan.

Petunjuk instalasi/upgrade berada di DOCKER_LOCAL.md. Jalankan migration tambahan sebelum traffic dibuka; jangan memakai migrate:fresh pada data pengguna. Bukti rilis lama di bawah bersifat historis dan tidak berarti seluruh pemeriksaan tersebut diulang pada 1.5.

---

# Validasi 1.4.0 — 6 November 2026

Source input: ZIP modifikasi pengguna. Baseline: 109 tes lulus, 556 assertions. Rilis setelah perubahan: **115 tes lulus, 579 assertions** pada PHP 8.5.12, Laravel 13.40.0, SQLite terisolasi; Node 24 dan Chromium headless untuk frontend/browser.

- Composer validate --strict, platform requirements non-dev dan Pint lulus.
- Audit awal: 7 advisory pada symfony/http-foundation, http-kernel, routing dan yaml. Keempat paket diperbarui ke v8.1.11; audit akhir 0 advisory/abandoned. Ini tidak berarti seluruh advisory dapat dieksploitasi pada konfigurasi aplikasi ini.
- npm audit: 0 vulnerability pada saat pemeriksaan. SweetAlert2 11.26.25 dibundel lokal; tidak memakai CDN.
- Build Vite, route cache dan view cache berhasil. Cache route lokal dibersihkan setelah verifikasi.
- Browser: lihat validation-results.json untuk jumlah dan nama skenario akhir. Halaman diuji pada 1440, 390 dan 320 px, light/dark; grafik, navigasi, lookup, konfirmasi, loading dan validasi termasuk cakupan.
- Grafik diperiksa dengan data demo sintetis. Tes backend memastikan karya privat milik akun lain tidak ikut masuk agregasi akun biasa.
- Polling status diuji untuk policy view serta idle expiry; response hanya status dan versi, tanpa metadata karya/dokumen.
- Struktur YAML Compose lokal/production dan isolasi port diperiksa dengan parser. Ini bukan eksekusi docker compose config atau build image.

## Belum dijalankan

Daemon Docker tidak tersedia di environment editor. Image build/run PHP 8.4 target, MySQL/Redis/Mailpit/ClamAV aktual, TLS, SMTP eksternal, cross-replica failover, load test dan backup restore belum diuji di sini. Job CI Docker disertakan tetapi belum dijalankan di GitHub pada sesi ini. Ikuti DOCKER_LOCAL.md dan OPERATIONS.md pada mesin tujuan.

Browser memakai emulasi Chromium, bukan perangkat fisik atau audit aksesibilitas eksternal. Hasil tanpa advisory tidak menggantikan penetration test. Suite SQLite tidak membuktikan locking/concurrency MySQL. Status rilis: kandidat staging, belum dideploy.

---

## Bukti historis rilis sebelumnya

# Bukti validasi rilis

## Hardening 1.3.0 — 3 November 2026

Status: **production candidate**. Hasil berikut berasal dari source rilis ini pada lingkungan lokal terisolasi: PHP 8.5.12, Laravel 13.33.0, Node.js 24.22.1, Vite 8.3.5, SQLite, dan Chromium headless. Target Docker memakai PHP 8.4; image dan layanan production belum dijalankan di lingkungan ini.

| Pemeriksaan | Hasil |
| --- | --- |
| Baseline sebelum hardening | 77 tes lulus, 394 assertions |
| Suite akhir `php artisan test --compact` | **104 tes lulus, 549 assertions**, sekitar 29 detik |
| Laravel Pint | Lulus pada file PHP yang berubah |
| `git diff --check` | Lulus |
| `composer validate --strict` | Lulus |
| Composer audit | Tidak ada advisory atau abandoned package pada pemeriksaan akhir |
| npm audit | 0 vulnerability; package-lock tidak diubah oleh hardening ini |
| Build frontend | Berhasil; JavaScript 6,46 kB (gzip 2,35 kB), CSS 89,36 kB (gzip 17,56 kB) |
| Browser Chromium | 32 pemeriksaan halaman/interaksi lulus; tema light/dark, persistensi, responsif 320/390/1440 px, lookup, repeater, menu keyboard, dialog, login/cookie, dan CSRF |
| Browser error | Tidak ada JavaScript page error, console error, atau HTTP 5xx dalam skenario akhir |
| Migrasi | Suite menjalankan migration pada SQLite terisolasi; migration upgrade juga dijalankan pada database demo lokal |
| Deployment config | YAML dapat diparse, dua replica dan shared document mount ada, hanya web memublikasikan port; ini pemeriksaan statis, bukan `docker compose config` atau uji runtime |
| Dokumentasi | Tautan Markdown lokal README/docs diperiksa; panduan disesuaikan dengan source |
| Paket ZIP | Source, aset build, dan dokumen baseline dipertahankan; .env aktual, DB lokal, upload pribadi, log, vendor/node_modules, dan cache runtime tidak disertakan |

Audit awal menemukan advisory symfony/mime. Lock file diperbarui secara terarah, lalu Composer audit diulang tanpa advisory. Audit ini mencerminkan database advisory saat dijalankan, bukan jaminan bebas celah keamanan.

### Cakupan tambahan rilis ini

- Sesi tanpa marker, idle/absolute expiry, pencabutan lewat auth_version setelah perubahan password/reset/email/role/status aktif; login rate limit dan respons reset generik.
- Kebijakan password terpusat, perubahan email memakai password saat ini, keamanan host/CSRF pada konfigurasi production, dan verifikasi email.
- Stale edit master/user, validasi nested array, bounded lookup dan otorisasi lookup privat.
- Query search tanpa kolom besar, selected lookup di luar batch pertama, dan cakupan publik saat hydration.
- Penolakan dokumen unscanned dan scanner unavailable; hasil scanner sukses menggunakan mock pada pengujian aplikasi.
- Seluruh regression workflow, ownership, upload PDF, versioning, import, publishing, audit, dan admin terakhir dari suite sebelumnya.

Pengujian browser menggunakan database demo sintetis. Pemeriksaan pergantian tema menunggu computed style sesuai pilihan agar pembacaan tidak dilakukan sebelum browser menyelesaikan perubahan tampilan. Emulasi viewport bukan pengujian perangkat fisik atau audit aksesibilitas penuh.

### Belum dibuktikan di server tujuan

Docker image build, PHP 8.4 runtime target, Nginx TLS, MySQL concurrency/EXPLAIN, Redis multi-replica session dan failure handling, ClamAV daemon/signature nyata, SMTP/provider, backup/restore operasional, kapasitas k6, serta failover host/database. Queue/notification pada automated tests dapat memakai fake; hasil tersebut tidak membuktikan pengiriman email nyata.

SQLite tidak membuktikan perilaku lock MySQL di bawah beban. Tidak ada angka kapasitas, latency produksi, zero downtime, atau HA yang disertifikasi. Konfigurasi dua replica pada satu host tetap memiliki single point of failure pada host, DB, dan Redis. Ikuti acceptance checklist [OPERATIONS.md](OPERATIONS.md) sebelum go-live.

Ringkasan hasil yang dapat dibaca mesin ada pada [validation-results.json](validation-results.json). Catatan 1.2.x di bawah adalah riwayat; bukan klaim bahwa seluruh pengujian infrastruktur lama diulang pada 1.3.0.

---

## Revisi desain 1.2.1-mvp.1 — 27 Oktober 2026

Lingkungan: PHP 8.5.12, Laravel 13.33.0, Node.js 24.22.1, Vite 8.3.5, SQLite lokal terisolasi, Chromium headless 156.0.8078.47.

| Pemeriksaan | Hasil |
| --- | --- |
| Suite aplikasi setelah perubahan Blade | 77 tes lulus, 394 assertions. |
| Kompilasi seluruh Blade dan parsing PHP hasil kompilasi | 117 view berhasil diparse. |
| Build frontend produksi | Berhasil; manifest dan aset baru disertakan. |
| Format PHP dan diff whitespace | Laravel Pint dan git diff --check lulus. |
| Desktop 1440 px | Beranda, pencarian, detail karya, login, dashboard, form publikasi, import, dan profil diperiksa secara visual. |
| Ponsel 390 px | Beranda, pencarian, detail, dosen, dashboard, daftar karya, form, import, pengguna, referensi, dan profil diperiksa. |
| Lebar 320 dan 768 px | Beranda/dashboard/form pada 320 px, tabel pengguna/referensi pada 320 px, dashboard pada 768 px. |
| Navigasi mobile | Buka/tutup, Escape, fokus kembali ke pemicu, siklus Tab/Shift+Tab, navigasi referensi, serta reset saat resize berhasil. |
| Interaksi form | Filter koleksi buku, tambah/hapus baris penulis, serta buka/batalkan dialog profil berhasil. |
| Tabel pada ponsel | Tabel pengguna diperbaiki dan diperiksa ulang pada 390/320 px. Scroll berada di area tabel; tidak ada overflow halaman pada matriks akhir. |
| Preferensi sistem gelap | Profil dan dialog tetap terbaca dengan tema terang yang konsisten. |
| Error browser/server | Tidak ditemukan JavaScript page error atau HTTP 5xx pada pemeriksaan browser akhir. |

Pemeriksaan tabel ditindaklanjuti setelah menemukan elemen label tersembunyi yang melebar keluar area scroll. Wrapper tabel kini menjadi acuan posisi elemen tersebut. Navigasi mobile menggunakan atribut hidden dan inert agar tampilan tertutup serta fokus keyboard konsisten.

Fokus revisi ini adalah antarmuka. Alur queue produksi, audit dependency, dan instalasi server tidak diuji ulang pada revisi desain; bukti 24 Oktober di bawah merupakan catatan rilis sebelumnya. Pemeriksaan browser memakai emulasi viewport Chromium, belum perangkat fisik, Safari/Firefox, atau audit aksesibilitas menyeluruh.

## Validasi implementasi 1.2.0 — 24 Oktober 2026

Tanggal: 24 Oktober 2026. Lingkungan lokal Linux, PHP 8.5.12, Laravel 13.33.0, PHPUnit 12.5.35, Node.js 24.19, Vite 8.3.5, SQLite in-memory untuk test. Browser QA: Chromium headless 156.0.8078.47.

| Pemeriksaan | Hasil |
| --- | --- |
| Baseline sebelum perbaikan | 25 tes: 2 lulus, 23 error migration. |
| Suite akhir | 77 tes lulus, 394 assertions. |
| Composer validate --strict | Lulus. |
| Composer dependency audit | Tidak ada advisory pada pemeriksaan rilis. |
| npm audit | 0 advisory, setelah update dan override parser. |
| Build frontend | Berhasil; manifest dan aset disertakan. |
| Laravel Pint | Dijalankan pada seluruh file PHP yang berubah. |
| Route cache dan Blade view cache | Berhasil dikompilasi, kemudian cache lokal dibersihkan. |
| Instalasi bersih | `composer install --no-dev` berhasil dari lock file, migration dan demo seed berhasil pada SQLite baru. |
| Alur browser + worker nyata | Operator membuat/mengajukan, verifier memeriksa/menyetujui, worker database queue menerbitkan; guest mendapat 200, lalu 404 setelah unpublish; arsip berhasil. |
| Browser desktop 1440×1000 | Home, login, dashboard, form dan penambahan penulis berhasil. |
| Browser ponsel 390×844 | Home/dashboard dapat digunakan, tidak ada overflow halaman; tabel memakai scroll lokal. |
| JavaScript dan respons server saat browser QA | Tidak ditemukan page error atau respons HTTP 5xx pada halaman yang dicoba. |

## Cakupan automated test

- Auth/profil bawaan, registrasi tanpa privilege escalation, akun nonaktif, matriks enam role dan kepemilikan.
- CRUD publikasi/buku/HKI, normalisasi DOI, duplikasi, relasi penulis, stale form, transaksi sitasi dan public visibility.
- Submit/review/approval/revision/reject, larangan self-verification/skip state, queue dispatch, failure/retry, reopening, academic status yang terpisah dan attempt idempotent.
- MIME/signature PDF, private download, versi lama, upload tanpa akses dan soft-delete dokumen yang mempertahankan file.
- CSV/XLSX/formula, preview/commit, duplikasi setelah preview, rollback batch, expiry/ownership dan nomor baris setelah baris kosong.
- Seeder berulang, production guard, bootstrap admin, admin terakhir, recovery queued attempt, referensi master, audit immutability dan health.

## Batas kesimpulan

Test SQLite dan request berurutan tidak membuktikan lock/concurrency MySQL multiproses. Sebagian automated test memakai fake queue/storage; job service dijalankan untuk memeriksa state dan integritas. Smoke terpisah pada instalasi bersih menggunakan browser dan worker database queue nyata dengan SQLite. SMTP, antivirus nyata, Redis, S3, CI jarak jauh, server Nginx/Supervisor, restore DB operasional, accessibility audit menyeluruh dan load test belum dijalankan. Audit tanpa advisory bukan sertifikasi keamanan.

Test dapat diulang dengan perintah README. Jangan memakai database operasional sebagai target test.
