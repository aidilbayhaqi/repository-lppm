# Panduan developer

## Mulai belajar

Baca README dan APPLICATION_FLOW, kemudian ikuti satu request: route di routes/web.php → controller → FormRequest → WorkPolicy → WorkService → model/pivot/audit → Blade. Untuk alur status lanjutkan ke PublicationWorkflow dan PublishingService; PublishPublication tetap dipakai untuk recovery. AUTH dan session dijelaskan pada SECURITY.md.

Laravel 13, PHP 8.4.1+, Blade, Tailwind, JavaScript ES module, PHPUnit, dan Vite dipertahankan. Composer/npm lock file adalah sumber versi dependencies. Jangan mengganti framework karena preferensi personal bila kebutuhan dapat diselesaikan dalam struktur yang ada.

## Menjalankan dan memeriksa

```bash
composer install
npm ci --ignore-scripts
npm run build
php artisan serve
```

Worker dijalankan di terminal terpisah: `php artisan queue:work database --tries=3 --timeout=60`. Scheduler lokal: `php artisan schedule:work`. Jangan memakai database produksi untuk pengujian atau seeder. File phpunit.xml memilih SQLite in-memory secara default; CI menguji juga konfigurasi MySQL melalui environment matrix.

```bash
php artisan test --compact
php vendor/bin/pint --dirty --format agent
composer validate --strict
composer audit
npm audit
npm run build
```

Untuk perubahan sempit, mulai dengan test terkait lalu jalankan seluruh suite ketika beberapa modul berubah. PHPUnit membuktikan aturan aplikasi; browser smoke test diperlukan untuk JS, CSS, cookie, dan fokus keyboard. Jangan menambahkan tes yang hanya menyalin implementasi.

## Aturan implementasi

- Controller menangani HTTP dan mengirim validated data; service mengikat transaksi domain; policy menjadi sumber izin record.
- Gunakan named route, Eloquent relation, bound query parameter, type hint, return type, dan nama yang menjelaskan tujuan.
- Hindari mass assignment dari request->all(). Tambahkan rule nested key saat memperluas repeater.
- Untuk edit baru, kirim lock_version dan cek di dalam transaksi setelah lockForUpdate. Audit dan pivot harus ikut commit/rollback.
- Untuk field keamanan akun, pakai instance User agar auth_version berubah. Jangan menggunakan bulk update yang melewati model event.
- Jangan menambah cache data private tanpa kunci scope pengguna, invalidation, dan tes izin.
- Untuk dropdown besar, gunakan LookupOptions dan endpoint lookup; jangan mengembalikan seluruh tabel ke HTML/JSON.
- Tambahkan migration baru; jangan mengubah migration yang sudah berjalan di server. Uji upgrade pada salinan DB.
- File binary baru harus melewati validasi dan scan, disimpan pada disk privat, dan dibaca melalui policy.
- Gunakan queue untuk pekerjaan lambat. Job harus aman diulang, mempunyai timeout/retry, dan dijalankan setelah commit.

## Menambah field karya

1. Buat migration dengan artisan make:migration.
2. Tambahkan casts/fillable yang memang boleh diubah pada model, lalu rule pada WorkRequest.
3. Tambahkan input Blade. Jangan menambahkan status internal sebagai input bebas.
4. Periksa WorkService snapshot, import headers, detail publik dan detail admin; tentukan apakah field bersifat privat.
5. Tambahkan tes create/edit, validasi gagal, akses tanpa izin, dan konflik bila perilakunya terpengaruh.
6. Jalankan migration serta tests pada SQLite dan MySQL staging sebelum release.

## Endpoint lookup

`GET /lookups/{entity}?q=Adi&page=1` mengembalikan `data: [{id,label}]` dan `has_more`. Query maksimal 100 karakter, page 1–1000, paling banyak 25 hasil per halaman. Entity di-whitelist. Fakultas/program studi/dosen/pengindeks aktif dapat dicari publik; entity lain membutuhkan akun aktif dan, pada production, email terverifikasi. available_for hanya boleh dipakai admin untuk penulis yang belum terhubung atau terhubung pada akun sasaran.

Frontend mempertahankan selected option saat hasil baru masuk. AbortController serta nomor request mencegah response lama mengganti hasil terbaru. Option dibuat dengan new Option, bukan HTML dari API. Bila query gagal, pilihan lama tidak dihapus. Elemen select tetap menjadi nilai yang dikirim form; penambahan baris repeater mengaktifkan lookup pada baris baru.

## UI dan aksesibilitas

Gunakan token warna pada repository.css. Setiap warna teks/background baru harus diperiksa pada light/dark. Tema system mengikuti prefers-color-scheme; preference lokal dibaca sebelum paint melalui script bernonce. Jangan memasukkan script inline baru tanpa nonce atau menambah unsafe-eval demi library UI.

Tombol aksi, tabel, status, spacing, dan form harus memakai komponen yang ada. Uji lebar 320, 390, 768, dan desktop; tabel besar bergeser di container, bukan seluruh body. Periksa label, error text, Tab/Shift+Tab, Escape, fokus modal/menu, serta prefers-reduced-motion. Komponen profil memakai native dialog; aplikasi aktif tidak lagi mengimpor Alpine.

## Perubahan dependency dan CI

Gunakan update terarah untuk advisory, pertahankan lock file, lalu jalankan audit/build/test. Jangan menjalankan composer update tanpa target untuk perbaikan kecil. Rilis ini memperbarui symfony/mime setelah advisory ditemukan. Audit dependency bukan pengganti review kode.

CI yang disertakan memasang PHP/Node, menguji SQLite/MySQL, memvalidasi Composer, membangun asset, dan memeriksa advisory. CI belum dijalankan di akun GitHub dalam sesi ini. Build image, integrasi Redis/ClamAV, SMTP, serta failover tetap harus dijalankan pada staging sesuai OPERATIONS.md.

## Proses release

Catat versi → jalankan pemeriksaan → build asset → periksa migrasi dan changelog → backup → deploy staging → jalankan checklist penerimaan → deploy production sesuai maintenance plan. Jangan mengirim .env, database lokal, private PDF, vendor/node_modules, cache runtime, atau secrets pada source ZIP. Dokumentasi implementasi dan hasil tes harus mencerminkan kode release, bukan sekadar target baseline.

## Form dan referensi 1.6

WorkController → WorkRequest → WorkSubmissionService → WorkService → InlineReferenceService, kemudian DocumentService untuk setiap lampiran. Jangan memanggil resolver referensi saat preview: proses preview hanya validasi dan menyimpan batch, tanpa mengubah katalog. Repeater memakai partial author-row/attachment-row; inisialisasi ulang lookup, source-choice, dan label dokumen pada elemen baru. Hidden step tetap dikirim, sedangkan panel mode yang tidak aktif harus disabled. Validasi browser hanya membantu pengguna; backend tetap wajib memvalidasi seluruh payload.

`SpreadsheetTemplate` membuat XLSX teks tanpa dependency tambahan; `SpreadsheetReader` membaca sheet pertama sesuai relasi workbook, string, tanggal Excel, dan menolak formula/entitas XML/macro. Jangan menambahkan evaluasi formula atau fetch external link. Untuk field tambahan, periksa jumlah header tidak melebihi batas 50 kolom.
