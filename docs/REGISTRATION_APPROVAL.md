# Pendaftaran akun dan persetujuan Superadmin

## Alur

1. Pengunjung membuka `/register` bila `REGISTRATION_ENABLED=true`.
2. Mengisi nama, email, password, kategori mahasiswa/dosen/penulis eksternal, NIM/NIDN/identitas (sesuai kategori), dan Prodi aktif untuk dosen.
3. Server membuat `users.is_active=false` dan `registration_requests.status=pending`. Pendaftar **tidak** otomatis login dan **tidak** menerima role dari field `role` pada request.
4. Superadmin (role lama `admin`) membuka **Pengguna & akses → Persetujuan akun**. Password confirmation diperlukan.
5. Superadmin memeriksa identitas, dapat menetapkan mahasiswa/dosen/peneliti eksternal/operator/verifier/reviewer, atau menolak disertai alasan. Role `admin` tidak tersedia lewat approval.
6. Approval adalah transaksi: link/buat referensi `penulis`; untuk dosen juga `dosen` dengan `program_studi_id` dari referensi aktif; aktifkan user; tulis audit. Rejection mempertahankan akun nonaktif.
7. Setelah approval, event `Registered` memicu email verifikasi. Pada environment yang menetapkan `REQUIRE_VERIFIED_EMAIL=true`, pengguna harus memverifikasi alamat sebelum memakai ruang kerja.

Reviewer merupakan peran terpisah dari Verifier. Pada tahap ini Reviewer memiliki akses baca terbatas atas karya terkait akun, tanpa hak memverifikasi/menerbitkan atau mengelola pengguna; workflow peer review formal memerlukan desain penugasan dan keputusan tersendiri.

## Batas otorisasi

- `manage-users` hanya untuk role `admin`. Route review menambahkan `password.confirm`.
- Permintaan registrasi tidak pernah menerima role `admin`, `is_active`, `user_id`, atau `email_verified_at` dari form.
- Admin tidak boleh memakai form edit user biasa untuk mengaktifkan akun pending/ditolak.
- Referensi Fakultas dan Prodi **tidak** dibuat saat pendaftaran. Hanya pilih Prodi aktif dengan Fakultas aktif.
- NIDN yang sudah dimiliki dosen dengan identitas/Prodi berbeda ditolak. Referensi yang sudah ada butuh konfirmasi eksplisit dan tidak boleh diambil dari akun lain.
- Review bisa dieksekusi **sekali**. Request terproses atau akun yang sudah diaktifkan tidak bisa diproses lagi.
- Seluruh keputusan dicatat sebagai `AuditLog`.

## Instalasi

1. Backup DB terlebih dahulu. Cek migration: `php artisan migrate --pretend`.
2. Jalankan `php artisan migrate` setelah meninjau SQL dan backup. Migration hanya membuat tabel `registration_requests` tanpa mengubah data user lama.
3. Atur `REGISTRATION_ENABLED=true` pada environment yang memang ingin membuka pendaftaran. Untuk Docker local, ubah **file .env.docker yang sudah ada**; perubahan contoh tidak otomatis menimpa konfigurasi.
4. Jalankan `php artisan optimize:clear` dan `php artisan view:cache`.
5. Jalankan `php artisan test --filter=RegistrationTest`, lalu full suite pada DB testing terpisah.
6. Untuk Docker local, rebuild image `docker compose --env-file .env.docker -f compose.local.yml build app` dan `up -d app worker scheduler`. Migration sesuai prosedur staging/upgrade yang aman.

## Acceptance checks

- Register mahasiswa, dosen (dengan Prodi aktif), penulis eksternal, operator, verifier, dan reviewer (tetap menunggu approval).
- Pending tidak bisa login dan tidak mempunyai referensi auto-create.
- Superadmin bisa approve/reject, tetapi tidak bisa memberi role admin melalui approval.
- Persetujuan akademik menghasilkan referensi sesuai kategori; perubahan fakultas/prodi tetap melalui referensi.
- Penolakan membutuhkan alasan dan tidak bisa diproses ulang.
- Referensi terkait akun lain tidak boleh diambil.
- Email verification dikirim setelah approval; Mailpit digunakan untuk pengujian lokal.
- Akses admin/approval ditolak untuk operator/verifier/reviewer/dosen/mahasiswa/guest.

## Catatan

Di codebase saat ini, role `admin` menjalankan tanggung jawab Superadmin. Tidak ada role `superadmin` terpisah agar aturan RBAC lama tetap konsisten. Untuk kebutuhan dua tingkat admin nanti, buat desain RBAC dan migration terpisah.

Fitur ini belum dinyatakan production-ready tanpa bukti CI, MySQL integration dan uji UI.
