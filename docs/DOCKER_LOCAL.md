# Docker lokal — rilis 1.6

Gunakan untuk belajar dan menguji dengan data sintetis. Profil ini berbeda dari compose.production.yml: HTTP lokal, Mailpit untuk email, dan volume database/dokumen terpisah bernama osdj-local. Port aplikasi dan Mailpit hanya diikat ke 127.0.0.1. Jangan membuka profil ini ke internet.

## Persiapan Windows

1. Instal Docker Desktop dengan WSL 2, buka Docker Desktop dan tunggu engine siap.
2. Ekstrak source, buka PowerShell pada folder OSDJ-LPPM yang memuat Dockerfile.
3. Jalankan `docker info` dan `docker compose version`. Tidak perlu PHP, Composer, Node atau MySQL di Windows untuk profil ini.
4. Jangan menjalankan MySQL/XAMPP sebagai database target profil ini; MySQL container menyimpan data pada volume sendiri.

## Konfigurasi sekali untuk instalasi baru

```powershell
Copy-Item .env.docker.example .env.docker
```

Buat APP_KEY serta dua password acak menggunakan PowerShell berikut. Jalankan hanya saat file baru dibuat; jangan mengganti APP_KEY atau password DB setelah data tersimpan.

```powershell
function New-RandomBytes {
    $bytes = New-Object byte[] 32
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes); return ,$bytes } finally { $rng.Dispose() }
}
$localConfig = Get-Content .env.docker -Raw
$localConfig = $localConfig -replace '(?m)^APP_KEY=.*$', ('APP_KEY=base64:' + [Convert]::ToBase64String((New-RandomBytes)))
$localConfig = $localConfig -replace '(?m)^DB_PASSWORD=.*$', ('DB_PASSWORD=' + [BitConverter]::ToString((New-RandomBytes)).Replace('-', ''))
$localConfig = $localConfig -replace '(?m)^MYSQL_ROOT_PASSWORD=.*$', ('MYSQL_ROOT_PASSWORD=' + [BitConverter]::ToString((New-RandomBytes)).Replace('-', ''))
[IO.File]::WriteAllText((Join-Path $PWD '.env.docker'), $localConfig, (New-Object Text.UTF8Encoding $false))
```

Nilai tidak dicetak ke terminal. Simpan .env.docker secara privat. Alternatif Linux/macOS: `cp .env.docker.example .env.docker`, buat APP_KEY dari 32 byte base64 dan dua password hex melalui password manager/OpenSSL, lalu isi file dengan editor. Pertahankan nilai lain dari template.

## Build dan mulai

```powershell
docker compose --env-file .env.docker -f compose.local.yml config --quiet
docker compose --env-file .env.docker -f compose.local.yml build app
docker compose --env-file .env.docker -f compose.local.yml up -d mysql redis mailpit
docker compose --env-file .env.docker -f compose.local.yml run --rm app php artisan migrate --force
docker compose --env-file .env.docker -f compose.local.yml up -d app worker scheduler
docker compose --env-file .env.docker -f compose.local.yml exec app php artisan repository:create-admin
```

Buka http://localhost:8000. Email verifikasi admin ditangkap di http://localhost:8025, bukan dikirim ke internet. Buka email dan klik tautan verifikasi pada browser yang sama. Gunakan localhost secara konsisten; jangan berganti ke 127.0.0.1 karena cookie dan signed URL berbeda host.

Karya dan akun awal kosong; katalog indeks SINTA/Scopus beserta peringkat tersedia setelah migration. Penulis, jurnal/prosiding, dan penerbit baru dapat diketik langsung saat membuat karya. Untuk demonstrasi sintetis, tetapkan DEMO_PASSWORD pilihan sendiri dalam .env.docker, jalankan ulang `up -d app worker scheduler` agar environment diperbarui, lalu `exec app php artisan db:seed`. Seeder hanya diizinkan pada local/testing. Jangan gunakan akun demo di production.

Source tidak di-bind mount; setelah mengubah PHP/Blade/CSS/JS, jalankan `build app` lalu `up -d app worker scheduler`. Worker perlu container baru juga. Build menyertakan asset terbaru, termasuk dark mode dan SweetAlert2.

## Upgrade instalasi Docker yang sudah ada

Backup database dan dokumen terlebih dahulu. Gunakan `.env.docker`, APP_KEY, dan volume lama; jangan membuat ulang kunci atau menghapus volume. Ganti source dengan rilis ini, lalu jalankan:

```powershell
docker compose --env-file .env.docker -f compose.local.yml build app
docker compose --env-file .env.docker -f compose.local.yml stop app worker scheduler
docker compose --env-file .env.docker -f compose.local.yml run --rm app php artisan migrate --force
docker compose --env-file .env.docker -f compose.local.yml up -d app worker scheduler
```

Tunggu migration berhasil sebelum menjalankan perintah terakhir. Jangan memakai `migrate:fresh`. CSS/JS dan foto lokal ikut image baru; restart container lama saja tidak mengganti desain. Setelah upgrade, periksa form tiga tahap, dosen baru dengan NIDN, label Cover, template XLSX, riwayat 5 item, dan pilihan tema Gelap. Migration 1.6 wajib selesai agar kolom entry_key, ISBN, dan ISSN tersedia. Worker tetap diperlukan untuk email dan recovery, tetapi penerbitan normal tidak menunggu worker.

## Tes otomatis dalam Docker

```powershell
docker compose --env-file .env.docker -f compose.local.yml build tests
docker compose --env-file .env.docker -f compose.local.yml run --rm --no-deps tests
```

Target testing mempunyai dependencies developer dan SQLite in-memory. Tidak memakai volume MySQL/dokumen lokal. APP_KEY pada image testing hanya untuk tes. Image production tetap tanpa dependencies developer. Tes SQLite tidak membuktikan concurrency MySQL; workflow CI juga menyediakan matrix MySQL.

## Uji scanner opsional

```powershell
docker compose --env-file .env.docker -f compose.local.yml --profile scanner up -d clamav
```

Ubah DOCUMENT_SCAN_REQUIRED=true dalam .env.docker, tunggu scanner memuat signature, lalu jalankan `up -d app worker scheduler`. Dokumen yang sudah diunggah saat scanner dimatikan akan tetap unscanned. Jalankan `exec app php artisan repository:scan-documents` untuk memindainya; jangan mengubah scan_status langsung di database. Scanner memerlukan RAM tambahan dan akses jaringan untuk signature.

## Pemeriksaan dan troubleshooting

```powershell
docker compose --env-file .env.docker -f compose.local.yml ps
docker compose --env-file .env.docker -f compose.local.yml logs --tail=100 app worker mysql
docker compose --env-file .env.docker -f compose.local.yml exec app php artisan migrate:status
docker compose --env-file .env.docker -f compose.local.yml exec app php artisan queue:failed
```

- Browser tidak terbuka: pastikan app berjalan dan port 8000 tidak dipakai proses lain.
- SQL unknown column scan_status: pastikan menggunakan database container yang benar dan migration hardening berstatus Ran. Jalankan migrate, bukan migrate:fresh.
- Sedang diterbitkan: penerbitan normal sekarang sinkron. Periksa log request dan attempt recovery jika proses terputus. Klik muat status terbaru jika pemeriksaan otomatis berhenti; jangan set status published secara manual.
- Email tidak masuk: periksa worker dan Mailpit, bukan inbox email sungguhan.
- DB access denied setelah mengubah .env: password environment baru tidak mengubah user pada volume MySQL yang sudah ada. Pulihkan konfigurasi semula atau ubah kredensial dengan prosedur DB yang sesuai. Jangan menghapus volume sebagai langkah troubleshooting default.
- production-check gagal pada profil lokal itu wajar: profil ini memakai HTTP dan scanner opsional. Gunakan check tersebut pada profil production.

Untuk berhenti tanpa menghapus data: `docker compose --env-file .env.docker -f compose.local.yml stop`. Jangan memakai `down -v` kecuali memang bermaksud menghapus database dan dokumen lokal.

## Batas verifikasi rilis

Baca VALIDATION.md. Konfigurasi diperiksa secara statis di lingkungan editor; daemon Docker tidak tersedia di sana. Build container, Mailpit, MySQL dan Redis harus diverifikasi dengan langkah di atas pada komputer Anda. Workflow CI Docker disertakan, belum dianggap telah berjalan hanya karena filenya tersedia.
