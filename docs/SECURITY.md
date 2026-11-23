# Keamanan aplikasi dan data

## Autentikasi dan sesi

| Kontrol | Implementasi 1.6.0 |
| --- | --- |
| Password baru | Minimal 12 karakter, maksimal 72 byte; bcrypt Laravel |
| Login | Email dinormalisasi, akun harus aktif, session ID diganti |
| Remember me | Opt-in, cookie recall Laravel dengan masa berlaku 14 hari dan batas server `remember_expires_at` |
| Idle / absolute timeout | Default 30 menit tidak aktif / 480 menit sejak autentikasi |
| Pencabutan sesi | `auth_version` berubah saat password, email, role, atau is_active berubah |
| Ganti password sendiri | Browser dengan current password memperoleh sesi baru; sesi lain ditolak |
| Reset atau perubahan oleh admin | Sesi lama ditolak pada request berikutnya |
| Cookie production | Secure, HttpOnly, SameSite=Lax, host-only |
| Penyimpanan session | Payload terenkripsi dan serialisasi JSON; Redis bersama di production |
| Session blocking | Distributed lock Redis untuk mencegah request satu sesi saling menimpa |
| Pengelolaan pengguna | Konfirmasi password berlaku 15 menit |
| Ganti email profil | Wajib current password; email_verified_at dibatalkan |
| Reset password | Token 30 menit; respons permintaan sama untuk alamat dikenal/tidak dikenal |
| Registrasi | Production default mati; bila aktif hanya membuat mahasiswa |
| Verifikasi email | Wajib untuk ruang kerja pada konfigurasi production |

Password lama masih dapat dipakai login; kebijakan panjang berlaku ketika membuat atau mengganti password. Batas 72 byte mencegah pemotongan bcrypt secara diam-diam. Gunakan passphrase unik; password tidak dicatat dalam audit/log atau dikembalikan sebagai old input.

Sesi dari rilis lama tanpa penanda keamanan diminta login ulang. Revocation tidak bergantung pada penghapusan tabel sessions sehingga berlaku juga di Redis. Request yang sudah berjalan sebelum perubahan akun tidak dapat dibatalkan retroaktif. Edit nama profil tidak memperpanjang absolute timeout maupun password confirmation.

Email verifikasi dan reset dikirim melalui queue setelah transaksi commit. Pantau worker agar pesan tidak tertunda. Respons reset yang seragam mengurangi pengungkapan akun melalui pesan; ini bukan jaminan waktu respons identik.

**Aturan developer:** perubahan field keamanan harus melalui instance Eloquent `User` dalam transaksi/row lock. Bulk `User::where(...)->update(...)` dan SQL langsung melewati event model. Script darurat yang memakai SQL harus menaikkan auth_version dan mencatat tindakan secara eksplisit.

Remember me memakai token recall Laravel; password tidak disimpan di localStorage atau JavaScript. Login tanpa checkbox tidak membuat cookie recall baru. Sesi hasil recall diganti ID-nya dan tidak dianggap sebagai konfirmasi password. Akun nonaktif, token berubah, atau batas server kedaluwarsa tidak boleh membentuk sesi baru. Perubahan password/email/peran/keaktifan merotasi token; logout Laravel juga merotasi token recall akun. Implementasi token Laravel bersifat per akun, belum menyediakan daftar atau pencabutan token per perangkat. Pada perangkat bersama, jangan centang Remember me.

## Rate limit

| Kegiatan | Batas aplikasi |
| --- | --- |
| Login gagal email+IP | 5/menit |
| Login per IP / per akun | 20/menit / 10/menit |
| Registrasi, reset, konfirmasi password | 5/menit per IP dan 10/jam per akun |
| Lookup | 120/menit per IP |
| Search publik | 90/menit per IP |
| Download | 30/menit per IP |
| Workflow dan upload | 20/menit per IP |
| Preview import | 10/menit per IP |

Limiter memakai cache Redis bersama pada production. Nginx membatasi burst tambahan. Kampus dengan NAT bersama perlu menyesuaikan batas berdasarkan pengukuran; jangan menonaktifkan limiter pada production untuk mengejar benchmark.

## Otorisasi dan CRUD

Policy memeriksa actor, record, status, ownership, dan benturan kepentingan. Request dibatasi melalui FormRequest, whitelist field dan key nested array, batas jumlah item, validasi relasi, serta parameter binding. Role, creator, status workflow, dan auth_version tidak diterima bebas dari form karya.

Transaksi mengikat metadata, pivot, dan audit. Row lock serta lock_version melindungi lost update pada karya, master, pengguna, dan profil. Unique constraint menjadi pertahanan terakhir terhadap race; konflik dikembalikan sebagai pesan aman tanpa SQL. Master yang masih dipakai tidak boleh dihapus. Arsip karya dan soft delete dokumen menjaga riwayat.

Audit model menolak update/delete dan tidak menyimpan password/hash/token. Ini tidak mencegah administrator database mengubah tabel. Kebutuhan anti-tamper memerlukan salinan audit eksternal dengan retensi immutable serta pemisahan kredensial runtime dan migrasi.

### Referensi langsung dari form

Pembuatan referensi hanya dijalankan setelah policy dan versi karya diperiksa, di dalam transaksi. Field akun/role/user_id dan key deduplikasi tidak diterima dari nested author input. Dosen baru membutuhkan NIDN 10 digit; NIDN yang sudah dipakai dengan nama berbeda ditolak. Data lama tidak ditimpa atau diaktifkan kembali otomatis. Unique entry_key dan unique NIDN membantu mencegah duplikasi permintaan bersamaan; pengujian race MySQL nyata tetap diperlukan pada staging.

Admin boleh memverifikasi karya sendiri hanya dengan alasan wajib dan audit khusus; verifier tetap dilarang. Ini merupakan pengecualian peran yang eksplisit, bukan pencabutan pemeriksaan status atau lock_version. Keputusan institusi yang membutuhkan dua orang berbeda tetap memerlukan penggunaan verifier independen.

## Kuota penyimpanan PDF

Setiap karya memiliki pembatasan untuk **dokumen baru**, **versi per dokumen**, dan **total bytes yang tersimpan**. Nilai awal adalah 50 dokumen/karya, 20 versi/dokumen dan 500 MB/karya; dapat disesuaikan dengan `DOCUMENT_MAX_FILES_PER_WORK`, `DOCUMENT_MAX_VERSIONS_PER_FILE`, serta `DOCUMENT_MAX_STORAGE_MB_PER_WORK` sebelum deployment. Kuota menghitung dokumen soft-deleted dan seluruh versi retained (termasuk dokumen legacy tanpa version row). Upload dalam transaksi memakai row lock karya agar permintaan bersamaan tidak melampaui kuota, dan upload yang ditolak akan membuang file sementara yang sempat tersimpan. Tidak ada penghapusan otomatis atau perubahan migration. Batas ini adalah baseline proteksi resource, bukan pengganti monitoring disk, quota per-user, dan policy retensi yang disetujui institusi. Jika dataset lama melebihi kuota, file tidak dihapus tetapi upload tambahan ditolak sampai pengelola meninjau kebijakan.

## File PDF

PDF maksimal 20 MB, harus lolos MIME/signature check. Di production, ClamAV memeriksa stream sebelum bytes diterima ke disk privat. Malware, timeout, scanner mati, atau respons tidak dikenal menyebabkan upload ditolak. Tidak ada karantina asynchronous; file temporary HTTP tetap dikelola PHP dan tidak dipublikasikan.

Form tiga tahap membatasi 5 PDF dan total 20 MB. WorkSubmissionService membatalkan metadata/referensi/file baru bila salah satu unggahan gagal. Kompensasi penghapusan file tidak menggantikan pemantauan kegagalan storage atau proses yang mati mendadak.

File memakai storage key acak, checksum SHA-256, serta versi dokumen. Status scan disalin ke setiap versi. Unduhan dan penerbitan diblokir untuk file unscanned bila enforcement aktif. Legacy file diproses melalui `repository:scan-documents`; command tidak menghapus dokumen. Mode `--all` juga memindai ulang file bersih dan dapat memblokirnya bila pemeriksaan gagal.

Storage wajib di luar public/. Nginx menolak /storage/. Download memakai ID aplikasi, policy, attachment, dan private/no-store. Tidak ada input path bebas. Pemisahan metadata publik dan PDF publik tetap berlaku. Antivirus perlu signature terkini dan tidak menjamin mendeteksi seluruh bentuk file berbahaya.

## Header, host, dan log

CSP memakai script-src self + nonce per response, tanpa unsafe-eval. Bootstrap tema diberi nonce. object-src none, frame-ancestors none, base-uri self, dan form-action self membatasi konten aktif. Style masih mengizinkan unsafe-inline untuk gaya Blade/grafik yang ada; pengecualian ini didokumentasikan, bukan dianggap CSP tanpa kompromi.

HTML dan respons authenticated memakai private/no-store. Hanya asset fingerprinted /build/ boleh cache immutable. Header lain: nosniff, DENY framing, referrer policy, permissions policy, HSTS pada HTTPS. Host production dibatasi ke APP_URL. Trusted proxy default kosong. Nginx contoh mengosongkan forwarded header klien dan menetapkan FastCGI HTTPS. Untuk proxy tambahan, gunakan CIDR spesifik dan real-client-IP yang benar; jangan memakai wildcard.

Log production berisi request ID, user ID, nama route, metode, status, dan durasi. Login gagal mencatat hash email serta IP. Tidak mencatat request body, query string reset, password, session token, atau isi dokumen. Terapkan retensi dan pembatasan akses log.

## Hardening akses privat dan antrean Verifier (Security Sprint 1)

- Pemeriksaan **email terverifikasi** kini dilakukan di `WorkPolicy::view` ketika `REQUIRE_VERIFIED_EMAIL=true`. Semua akses privat berbasis policy, termasuk endpoint unduh PDF langsung, versi PDF, dan cover privat, mengikuti aturan yang sama. File publik tetap dapat diunduh tanpa akun jika karya berstatus publik, dokumen ditandai publik, dan persyaratan scan terpenuhi.
- Verifier tidak lagi menerima akses baca global untuk seluruh publikasi. Akses antrean dibatasi ke publikasi **tidak diarsipkan** dengan status `submitted` atau `pending_verification`. Pengecualian terhadap karya yang memang dibuat/ditulis user tetap memakai aturan kepemilikan normal; verifier tetap tidak bisa mengubah metadata. Setelah `approve`, `revision`, atau `reject`, verifier diarahkan ke daftar antrean karena akses detail karya selesai sudah ditutup.
- Penerapan **assignment verifier individual** belum ada: semua verifier yang aktif dan terverifikasi masih bisa memeriksa antrean bersama. Perlu desain penugasan jika pemeriksaan wajib dibatasi per orang. Reviewer tetap role berbeda tanpa izin verifikasi.
- Tambah regresi: akses PDF, versi dan cover privat oleh pemilik email belum diverifikasi, pengecualian PDF/cover publik, larangan akses draft dengan ID berbeda, daftar antrean Verifier, dan larangan mutasi Reviewer. Uji ulang pada MySQL dan staging sebelum produksi.

## Security Sprint 2: MFA dan pencabutan sesi

- TOTP + recovery codes opt-in menggunakan `MFA_ENABLED`. Secret terenkripsi via APP_KEY, recovery codes berbentuk hash, digunakan sekali, dan TOTP tidak boleh replay.
- Wajib untuk admin/operator/verifier saat flag aktif. Enrollment memerlukan password dan TOTP; peran wajib tidak boleh menonaktifkan MFA.
- Login normal tidak lagi mengonfirmasi password untuk tindakan admin; tindakan pengelolaan akun dan persetujuan memerlukan konfirmasi terbaru (maksimal lima menit).
- Revoke sesi lain melalui profil memerlukan password, merotasi remember token dan menambah auth_version; sesi lama ditolak pada request selanjutnya. Belum ada daftar perangkat granular.
- Sebelum enable produksi: backup APP_KEY, latihan recovery dan rencana break-glass tertulis.

## Kontrol operasional dan batas rilis

TLS, firewall, patch server, backup terenkripsi, restore test, monitoring, dan pengelolaan secret tetap tanggung jawab deployment. MFA/SSO admin **belum diimplementasikan**; bila diwajibkan institusi, pasang identity gateway atau implementasikan MFA sebelum membuka panel admin ke internet. Tidak ada crawler URL pengguna, sehingga jangan menambahkan fetch URL tanpa pertahanan SSRF. Review penetrasi independen dan pemeriksaan kepatuhan institusi belum dilakukan.

Referensi: [Laravel authentication](https://laravel.com/docs/13.x/authentication), [Laravel session](https://laravel.com/docs/13.x/session), [OWASP session management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html), [ClamD protocol](https://docs.clamav.net/manual/Usage/ClamdProtocol.html).
