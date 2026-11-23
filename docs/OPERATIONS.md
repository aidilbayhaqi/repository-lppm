# Deployment, upgrade, backup, dan operasional

## Status dan pilihan deployment

Rilis 1.3.0 menyediakan source yang diperkuat beserta konfigurasi deployment. Docker/MySQL/Redis/ClamAV, SMTP nyata, TLS, failover, dan load test di host tujuan belum dijalankan dalam lingkungan pengembangan ini. Selesaikan penerimaan di bagian akhir sebelum go-live.

Pilihan utama paket: compose.production.yml, dua PHP-FPM app, Nginx TLS least_conn, MySQL 8.4, Redis 7.4, ClamAV 1.4, worker database, dan scheduler. Semua berada di satu host. Volume dokumen dibagi ke app/worker; database, redis, dan scanner tidak memublikasikan port host. Ini menyediakan pembagian beban proses, bukan HA mesin/database.

Alternatif tanpa Docker: gunakan PHP 8.4.1+ / PHP-FPM, ekstensi yang dipersyaratkan Composer ditambah phpredis, Nginx, MySQL, Redis, ClamAV, dan process manager. deploy/nginx.conf.example dan supervisor.conf.example adalah contoh satu instance; adaptasi path/socket/domain dan gunakan environment production yang sama. Document root harus public/.

## Instalasi Docker baru

1. Siapkan domain, DNS, TLS fullchain/private key, akses SMTP, server Docker Compose v2, serta firewall yang hanya membuka 80/443 dan akses administrasi yang dibatasi.
2. Salin `.env.production.example` menjadi `.env.production`. Isi APP_HOST, APP_URL, APP_KEY, DB_PASSWORD, REDIS_PASSWORD, SMTP dan alamat pengirim. APP_HOST harus hostname saja, tanpa scheme/path. APP_URL harus HTTPS. Gunakan password acak berbeda; jangan memasukkan nilai rahasia dalam tiket, chat, log, atau Git.
3. Buat deploy/secrets/mysql_root_password dengan password root DB acak. File ini hanya dipasang pada service MySQL; aplikasi memakai akun DB terpisah. Batasi permission folder/file, misalnya 700/600. Root password tidak perlu berada di environment aplikasi.
4. Tempatkan certificate pada deploy/tls/fullchain.pem dan deploy/tls/privkey.pem. Atur pembaruan certificate melalui pengelola TLS server; reload Nginx setelah diperbarui. Folder secrets dan tls diabaikan Git serta build context.
5. Buat APP_KEY pada mesin administrasi yang aman menggunakan `php artisan key:generate --show`, lalu masukkan secara privat ke environment. Untuk instalasi lama pertahankan APP_KEY lama; jangan menjalankan key:generate ulang.
6. Jalankan perintah di bawah dari folder aplikasi.

```bash
docker compose --env-file .env.production -f compose.production.yml config --quiet
docker compose --env-file .env.production -f compose.production.yml build
docker compose --env-file .env.production -f compose.production.yml up -d mysql redis clamav
docker compose --env-file .env.production -f compose.production.yml run --rm app1 php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yml run --rm app1 php artisan repository:create-admin
docker compose --env-file .env.production -f compose.production.yml up -d app1 app2 worker scheduler web
docker compose --env-file .env.production -f compose.production.yml exec app1 php artisan repository:production-check
```

**Security preflight:** sebelum deployment, periksa `repository:production-check --skip-services`. Perintah menolak APP_KEY yang tidak valid 32 byte, password MySQL/Redis kosong, koneksi DB bukan MySQL atau DB menggunakan root, serta MFA istimewa mati. Jangan masukkan nilai secrets dalam output, log atau issue. Untuk instalasi existing yang memakai DB_URL/REDIS_URL, validator mengambil user/password dari URL jika disediakan. Siapkan rollover APP_KEY terencana karena perubahan kunci mendadak dapat merusak data terenkripsi.

Admin menerima verifikasi email melalui queue. Worker dan SMTP harus berjalan; jika pesan belum sampai, gunakan kirim ulang pada halaman verifikasi. Jangan memalsukan email_verified_at untuk menghindari pengujian SMTP. Perintah production-check memeriksa konfigurasi, schema, database/cache, writability disk lokal, dan respons ClamAV. Exit nonzero harus diselidiki. `--skip-services` hanya memeriksa konfigurasi, bukan kesiapan seluruh stack.

Entrypoint membuat cache config/route/view. Ia tidak membuat APP_KEY, tidak menjalankan migration otomatis, dan tidak melakukan seeding. Jalankan migration sekali per deployment, bukan pada setiap replica. Image PHP berjalan sebagai www-data. Root filesystem masih writable pada area container; jangan mengklaim image sepenuhnya read-only. Untuk reproducibility yang lebih kuat, pin digest image yang telah diuji oleh institusi dan simpan digest tersebut dalam catatan release.

## Upgrade dari 1.2.x

Migration baru menambah versi akun/master, status scan, dan indeks. Tidak menghapus record, tetapi pembuatan indeks dapat mengunci tabel pada dataset besar. Uji durasi pada salinan data produksi dan jadwalkan maintenance.

1. Backup database, private documents, APP_KEY dan environment; uji restore ke staging.
2. Inventaris migration, jumlah record, disk/path dokumen, serta email akun yang belum terverifikasi.
3. Matikan traffic mutasi/aktifkan maintenance; hentikan worker dan scheduler versi lama. Jangan melakukan rolling upgrade campuran lama/baru untuk rilis ini: versi lama belum memahami auth_version dan scan enforcement.
4. Pasang release dan dependencies dari lock file. Pertahankan APP_KEY dan data volume. Sesuaikan .env lama secara eksplisit: session 30 menit, encryption, verified email, scan enforcement, shared Redis. Menyalin source saja tidak mengganti environment lama.
5. Jalankan `php artisan migrate --force` sekali dengan kredensial deployment yang memiliki izin DDL. Jangan menggunakan migrate:fresh, reset, atau rollback massal pada data nyata.
6. Jalankan `php artisan repository:scan-documents`. File lama mendapat default unscanned; unduhan ditolak sampai berhasil dipindai. File gagal tidak dihapus. Selidiki path, checksum, dan hak akses secara aman.
7. Cache ulang config/route/view. Hidupkan aplikasi, worker, scheduler; jalankan production-check dan smoke tests.
8. Semua sesi lama harus login ulang. Pastikan email verifikasi, akses privat, dan publishing berhasil sebelum membuka maintenance.

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan repository:production-check
```

Migrasi additive tidak berarti downgrade kode otomatis aman: kode lama tidak menerapkan enforcement scan/session yang baru. Jika perlu rollback, aktifkan maintenance, hentikan worker/scheduler, pertahankan backup/schema/data, pulihkan image release yang telah disetujui, dan evaluasi konsekuensi kontrol keamanan sebelum membuka akses. Restore database hanya dari backup yang sudah diverifikasi dan memperhitungkan perubahan setelah backup.

## Worker, scheduler, dan batas resource

Queue memakai database; timeout job 60 detik harus lebih pendek daripada retry_after (default 90). Tiga percobaan dan backoff 10/30/60 detik. Worker direstart berkala dengan max-time 3600. Supervisor/Compose menghidupkan ulang proses. Scheduler menjalankan recovery setiap lima menit, memakai onOneServer serta distributed lock Redis.

Jangan menjalankan scheduler sistem dan service scheduler Compose sekaligus. Tinjau `queue:failed`, backlog jobs, dan publish_attempts sebelum retry. Job penerbitan idempotent per attempt, tetapi retry umum tetap harus dipahami berdasarkan job yang gagal. Redis noeviction menghindari penghapusan sesi diam-diam; monitor kapasitas karena Redis penuh menyebabkan operasi gagal. Sesuaikan RAM Redis dan PHP-FPM setelah pengukuran.

ClamAV memerlukan waktu mengunduh/memuat signature. Upload gagal dengan aman sampai scanner siap. Pantau health/signature dan gunakan penyimpanan signature persisten. Jangan menonaktifkan DOCUMENT_SCAN_REQUIRED di production untuk menyembunyikan gangguan scanner.

## Health dan observability

- /up: liveness Laravel. /health: koneksi DB/cache dan kesiapan disk; hanya mengembalikan status umum, tanpa credentials.
- Nginx memakai passive upstream failure handling, bukan active application health removal. Monitor /health dari luar dan tiap replica melalui jaringan tepercaya.
- Catat HTTP p95, 4xx/5xx, CPU/RAM, koneksi DB, slow queries, disk, Redis memory, queue age, failed jobs, scanner/signature age, serta backup terakhir.
- Log production ke stderr dengan request ID. Batasi akses dan retensi. Jangan membuka debug mode di internet.
- Alert awal: lonjakan 5xx/login gagal, queue age >5 menit, disk >80%, Redis mendekati batas, backup gagal, dan certificate mendekati expiry. Nilai ambang perlu dituning.

## Backup dan restore

Backup adalah tiga aset: database, private document volume termasuk semua versi, serta konfigurasi/APP_KEY. Database snapshot tanpa file bukan backup aplikasi lengkap. Gunakan job backup institusi, enkripsi, salinan off-host, retensi, akses terbatas, dan pemantauan kegagalan. Hindari password pada command line; gunakan file konfigurasi DB yang permission-nya dibatasi atau secret manager.

Untuk backup konsisten awal, gunakan maintenance dan hentikan mutasi/worker, ambil dump transaksi atau snapshot MySQL yang konsisten serta snapshot volume dokumen. Rekam waktu, versi source, dan manifest checksum. Simpan APP_KEY secara terpisah namun dapat dipulihkan. Redis dapat dibuat ulang dengan logout pengguna, tetapi database queue dan dokumen harus dipulihkan konsisten.

Latihan restore dilakukan pada host/database terisolasi: restore DB dan volume → konfigurasi APP_KEY yang sesuai → jalankan versi aplikasi yang cocok → periksa jumlah record, file/checksum, login, private download, dan workflow → ukur durasi. Jangan menguji restore dengan menimpa produksi. Target awal yang diusulkan RPO 24 jam/RTO 4 jam harus disetujui dan dibuktikan, bukan dianggap sudah tercapai.

## Penerimaan sebelum go-live

| Pemeriksaan | Bukti yang disimpan |
| --- | --- |
| Build image dan nginx -t | Log build/config valid |
| MySQL dan migrasi data representatif | Migration log, row counts, EXPLAIN |
| Redis session dan load balancer | Login bertahan saat replica restart |
| Shared documents | Upload app1, download app2, private URL tetap ditolak |
| ClamAV | File aman diterima, file uji antivirus ditolak, scanner mati menolak upload |
| SMTP/verification/reset | Pesan diterima, token valid dan kedaluwarsa ditangani |
| Concurrent CRUD | Stale update ditolak pada MySQL nyata |
| Backup/restore | Restore terisolasi berhasil dan RPO/RTO tercatat |
| Beban | Laporan k6 dan bottleneck yang ditangani |
| Akses institusi | Peran, distribusi PDF, retention, kebutuhan MFA disetujui |

## Troubleshooting

419: sesi/CSRF kedaluwarsa, muat ulang form; jangan menonaktifkan CSRF. Login berulang: periksa APP_KEY/cookie/Redis sama di seluruh replica dan HTTPS forwarding. 403: cek role, ownership, status karya, email verification. Konflik versi: muat ulang lalu bandingkan perubahan. File 404: cek policy, visibility, scan status dan disk; jangan memublikasikan folder storage sebagai jalan pintas. Publishing tertahan: cek log request, attempt dan scheduler recovery; penerbitan normal diproses sinkron, sementara recovery dapat memakai worker; scanner error: cek service/signature. Tema tidak berubah: rebuild asset dan clear view, periksa cache browser serta CSP nonce.
