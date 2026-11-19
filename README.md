# OSDJ LPPM — Repositori Akademik UMIBA

**Rilis 1.6.0 — kandidat staging, 13 November 2026.** Laravel/Blade untuk jurnal, buku, dan kekayaan intelektual. Form kini terdiri dari tiga tahap, referensi menjadi rekomendasi, penulis/dosen baru dapat ditambahkan dengan NIDN, dan PDF dapat diunggah bersama metadata. Template Excel mendukung input nama baru. Logo Universitas Mitra Bangsa dan navigasi publik diperbarui. Alur penerbitan langsung dari source pengguna dipertahankan. Lihat [VALIDATION](docs/VALIDATION.md) untuk hasil pengujian dan batas kesiapan deployment.

Untuk Docker lokal tanpa domain/HTTPS, ikuti [DOCKER_LOCAL.md](docs/DOCKER_LOCAL.md). Profil production tetap tersedia terpisah.

## Mulai dari sini

- [Indeks dokumentasi](docs/README.md)
- [Alur aplikasi dan hak akses](docs/APPLICATION_FLOW.md)
- [Struktur kode dan arsitektur](docs/ARCHITECTURE.md)
- [Keamanan](docs/SECURITY.md) dan [performa/indexing](docs/PERFORMANCE.md)
- [Deployment, upgrade, backup, restore](docs/OPERATIONS.md)
- [Panduan developer](docs/DEVELOPMENT.md), [review dan rencana](docs/REVIEW_AND_PLAN.md), [changelog](docs/CHANGELOG.md)
- [Mapping workbook lama](docs/LEGACY_DATA_MAPPING.md)

Dokumen Word/PDF/diagram dan folder DOKUMENTASI pada paket induk adalah baseline rancangan asli. Dokumentasi implementasi 1.6.0 ada di docs/.

## Persyaratan

PHP 8.4.1+ dalam seri 8.x, Composer 2, ekstensi DOM/Fileinfo/Mbstring/PDO/SimpleXML/Zip sesuai composer.lock, SQLite untuk lokal atau MySQL 8.4 untuk target production. Node 22.12+ diperlukan bila membangun ulang frontend; validasi memakai Node 24.22.1 dan PHP 8.5.12. Production memakai Redis/phpredis, private shared storage, ClamAV, SMTP dan HTTPS. Dockerfile menyertakan kebutuhan runtime target.

## Instalasi lokal baru

Dari folder OSDJ-LPPM, pada Linux/macOS:

```bash
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan serve
```

Pada Windows PowerShell, ganti perintah salin dengan `Copy-Item .env.example .env`; perintah PHP/Composer tetap sama. Buka http://127.0.0.1:8000. Asset hasil build disertakan; Node tidak wajib untuk mencoba UI dari ZIP.

Untuk data demonstrasi, tetapkan DEMO_PASSWORD pilihan sendiri di .env **sebelum** `php artisan db:seed`. Seeder dibatasi ke local/testing dan tidak mengganti password akun yang sudah ada. Akun demo tersedia untuk admin/operator/verifier/dosen/mahasiswa/peneliti_eksternal dengan pola email `admin@repository.test`, `operator@repository.test`, dan seterusnya. Semua karya bertanda Demo adalah sintetis. Jangan memakai akun/data demo di production.

Untuk admin tanpa data demo:

```bash
php artisan repository:create-admin --email=admin@example.org --name="Admin LPPM"
```

Password diminta secara tersembunyi. Ganti alamat contoh dengan email institusi. Di production akun perlu verifikasi email sebelum masuk ruang kerja.

Jalankan worker di terminal kedua dan scheduler di terminal ketiga:

```bash
php artisan queue:work database --sleep=2 --tries=3 --timeout=60
php artisan schedule:work
```

Jalankan keduanya sebagai proses terpisah. Penerbitan jurnal diproses langsung setelah transaksi attempt selesai; hasilnya ditampilkan pada request yang sama. Worker tetap dibutuhkan untuk email verifikasi/reset dan pekerjaan recovery yang diantrekan. Lokal memakai mailer log; production wajib SMTP/provider nyata.

## Build dan test

```bash
npm ci --ignore-scripts
npm run build
php artisan test --compact
php vendor/bin/pint --dirty --format agent
composer validate --strict
composer audit
npm audit
```

Tests default memakai SQLite in-memory. CI menyertakan matrix SQLite/MySQL. Build frontend bila mengubah CSS/JS, dan jalankan `php artisan view:clear` setelah memperbarui Blade pada instalasi tanpa image immutable.

## Upgrade / production

Jangan menimpa .env atau mengganti APP_KEY instalasi lama. Rilis 1.6.0 menambah migration kunci referensi, ISBN/ISSN artikel, serta afiliasi dosen nullable. Migration rilis sebelumnya tetap wajib. Jalankan `php artisan migrate --force` sebelum membuka traffic; jangan melewati migration versi sebelumnya. Data karya dan status lama dipertahankan. Rincian input dan backfill ada di [APPLICATION_FLOW](docs/APPLICATION_FLOW.md). Ikuti [OPERATIONS](docs/OPERATIONS.md); jangan memakai migrate:fresh pada data nyata.

Konfigurasi tersedia di `.env.production.example`, `Dockerfile`, `compose.production.yml`, dan `deploy/`. Compose menyediakan dua app instance dengan Nginx least_conn, Redis bersama, MySQL, ClamAV, worker/scheduler, dan volume dokumen privat bersama. Topologi satu host ini bukan HA lintas host.

```bash
php artisan repository:production-check
php artisan repository:scan-documents
```

Jalankan sesuai urutan deployment dalam dokumentasi. Production-check yang lulus tidak menggantikan pengujian SMTP/TLS, Redis/failover, MySQL concurrency, scanner nyata, backup/restore, dan load test.
