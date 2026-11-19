# Changelog

## 1.6.0 — 13 November 2026

- Form tiga tahap untuk jurnal, buku, dan HKI; metadata serta maksimal 5 PDF (total 20 MB) tersimpan dalam satu transaksi dengan pembersihan file saat gagal.
- Referensi menjadi rekomendasi. Nama penulis baru, mahasiswa/eksternal, dosen dengan NIDN, jurnal/prosiding, dan penerbit dapat ditambahkan langsung tanpa membuka master terlebih dahulu.
- Afiliasi dosen nullable; prodi/fakultas baru dapat ditambahkan jika diketahui. Akun login tidak dibuat/ditautkan otomatis.
- Jenis file tampil sebagai judul, termasuk Cover. Loading memakai spinner dengan status aksesibel.
- Logo resmi Universitas Mitra Bangsa, menu Beranda/Panduan, dan panduan tiga tahap pada landing.
- Admin dapat melakukan self-review dengan alasan wajib dan audit; verifier tetap tidak boleh memverifikasi karya sendiri. History tetap 5 per halaman dan berbentuk kalimat.
- XLSX template asli, input penulis manual, NIDN teks, normalisasi DOI/ISBN, tanggal Excel dan relasi sheet pertama. Import legacy berbasis ID dan CSV tetap didukung.
- Migration tambahan entry_key, ISBN/ISSN artikel, dan prodi dosen nullable. Tidak mengubah dependency atau status penerbitan sinkron.

## 1.5.0 — 10 November 2026

- Tindakan karya diringkas menjadi toolbar; alasan diminta dalam SweetAlert2 saat diperlukan. Policy, status, version lock, dan penerbitan langsung tetap dipakai.
- Riwayat audit/verifikasi/attempt 5 item per halaman; status dan perubahan ditulis dalam kalimat.
- Label koleksi menjadi Jurnal, dengan metadata jenis karya, nama manual, SINTA 1–6, Scopus Q1–Q4, tahun dan sumber indeks.
- Input identitas jurnal dan indeks melalui referensi atau manual; validasi server menolak pasangan peringkat yang salah.
- Filter bersama untuk publik/ruang kerja: peringkat, prodi, fakultas, dosen, tahun, jenis, serta urutan; batas akses tetap berlaku.
- Remember me opt-in 14 hari, validasi kedaluwarsa di server, pencabutan token saat data keamanan akun berubah.
- Landing page dengan dua foto lokal, direktori koleksi, statistik aktual, FAQ, dan tema gelap charcoal.
- Migration tambahan wajib dijalankan; tidak perlu reset DB. Tidak ada perubahan dependency pada rilis ini.

---

# 1.4.0 — 6 November 2026

- Pertahankan modifikasi pengguna pada form, validasi PDF, readiness dan audit timeline.
- Dashboard grafik, spacing, SweetAlert2, loading/submit guard, error Bahasa Indonesia, error pages, unsaved-form guard.
- Workflow progress, status polling berizin tanpa memperpanjang idle session; retry pagination lookup diperbaiki.
- Patch 4 paket Symfony, rate limit mutasi, profil Docker lokal dengan Mailpit dan target testing terisolasi.
- Lihat VALIDATION.md untuk hasil aktual dan batas pengujian; DOCKER_LOCAL.md untuk instalasi Windows/Linux.

# Changelog

## 1.3.0 — 3 November 2026

### Data dan performa

- Bounded remote lookup dengan pagination, debounce, cancellation, dan preservasi selected values.
- Hydration kartu lebih ramping dan pemeriksaan ulang visibility; agregasi dashboard dan cache jumlah publik 60 detik.
- Pagination riwayat attempt, verifikasi, dan versi dokumen.
- Migration additive untuk indeks gabungan dan optimistic version master/pengguna.

### Keamanan

- Session auth_version, idle/absolute expiry, encrypted payload, konfigurasi cookie production, dan session blocking.
- Password policy bersama, pencabutan sesi, konfirmasi tindakan admin, serta current password untuk perubahan email.
- Registrasi production default mati, verifikasi email, generic reset response, limiter IP/akun, dan email queue.
- CSP nonce tanpa unsafe-eval, host/proxy restrictions, private/no-store HTML, request ID/log metadata.
- Nested payload whitelist, stale master/user/profile conflict handling, safe unique constraint response.
- ClamAV stream scan fail-closed, scan status per versi, scan command untuk file legacy.
- Update terarah symfony/mime untuk advisory dependency.

### UI dan operasional

- Tema system/light/dark, aksen koleksi, native dialog, dan lookup yang dapat digunakan dengan keyboard.
- Docker multi-stage, dua app replica, Nginx least_conn/TLS, Redis, MySQL, scanner, worker/scheduler, shared private volume.
- Production preflight, readiness endpoint, contoh k6, dokumentasi alur, folder, security, deployment dan restore.

### Perubahan kompatibilitas

- Jalankan migration sebelum kode baru menerima traffic. Semua session lama perlu login ulang.
- PUT master/pengguna/profil dan DELETE master wajib lock_version. Password baru minimal 12 karakter, maksimal 72 byte.
- Enforcement scan memblokir dokumen legacy unscanned sampai pemindaian berhasil.
- Aktifkan worker untuk email verifikasi/reset, dan perbarui .env lama secara eksplisit.
- Hindari deployment campuran kode 1.2.x dan 1.3.0. Baca OPERATIONS sebelum upgrade.

## 1.2.1 — 27 Oktober 2026

Perapihan UI publik/ruang kerja, sidebar mobile, tabel responsif, form dan profil. Tidak ada migration khusus UI.

## 1.2.0

Implementasi workflow karya, policy, audit, private document/versioning, import dan pengujian MVP. Dokumentasi baseline asli dipertahankan di paket induk.
