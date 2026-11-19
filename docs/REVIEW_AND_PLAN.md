# Revisi 1.6.0 — 13 November 2026

| Temuan | Hasil perubahan |
| --- | --- |
| Form panjang dan input referensi wajib tersedia dahulu | Tiga tahap; input manual membuat rekomendasi saat penyimpanan berhasil |
| Penulis dosen belum tercatat | NIDN wajib dan pencocokan identitas; afiliasi boleh belum diketahui |
| Lampiran harus diunggah setelah draft terbuat | Lampiran dapat disertakan pada simpan draf; rollback database dan file ketika gagal |
| Jenis file tidak terlihat sebagai judul | Heading mengikuti jenis dokumen termasuk pada baris baru |
| Admin tidak menemukan aksi verifikasi karya sendiri | Pengecualian admin dengan alasan wajib, audit, dan status/version gate tetap berlaku |
| Template hanya CSV dan butuh ID penulis | XLSX asli; penulis manual, tanggal Excel dan NIDN leading zero teruji |
| Identitas institusi masih ikon generik | Wordmark resmi lokal dan nama Universitas Mitra Bangsa |

Pemeriksaan menemukan dan memperbaiki status DB default yang belum dimuat sebelum unggahan pertama, label repeater yang tidak terinisialisasi pada root elemen baru, serta akses prodi nullable pada direktori dosen. Bukti akhir ada pada VALIDATION.md. Prioritas deployment tetap MySQL concurrency, ClamAV nyata, Redis, TLS, dan restore backup; tidak ada klaim bahwa infrastruktur tersebut sudah diuji di sini.

## Catatan review rilis sebelumnya (historis)

### Revisi 1.5.0 — 10 November 2026

Sumber utama: `Repository-LPPM-1.4 (2).zip` yang dimodifikasi pengguna. Baseline source ini memiliki 117 tes lulus / 604 assertions. PublicationWorkflow dan PublishingService pengguna dipertahankan, termasuk penerbitan sinkron setelah transaksi commit.

| Masalah | Perubahan |
| --- | --- |
| Kartu tindakan menumpuk | Toolbar ringkas; input alasan muncul pada konfirmasi |
| Riwayat berupa status teknis | Presenter label manusia, kalimat perubahan, pagination 5 |
| Rank tidak konsisten untuk dicari | Kolom canonical SINTA/Scopus serta normalisasi referensi/manual |
| Filter terbatas | Partial dan validasi filter bersama publik/ruang kerja |
| Jurnal belum tersedia di master | Input nama manual tanpa mengubah master |
| Remember me hanya klaim visual | Checkbox opt-in, cookie recall dan batas server |
| Tema gelap biru dan landing kaku | Palette charcoal, layout editorial, foto lokal dan panduan singkat |

Jenis karya dipisahkan dari indeks: SINTA/Scopus bukan jenis publikasi. Data lama tidak otomatis dianggap artikel jurnal. Klasifikasi yang belum diketahui tetap kosong. Foto adalah ilustrasi, tidak diklaim sebagai fasilitas UMIBA.

Perubahan ditambahkan ke struktur Laravel yang ada: `JournalMetadata` untuk normalisasi, `WorkFilters` untuk validasi/pilihan filter, `journal-experience.css` untuk visual baru, serta `source-choice.js` untuk pergantian input. Tidak ada framework atau dependency tambahan. Lihat VALIDATION.md untuk hasil dan batas verifikasi.

---

# Audit dan perbaikan 1.4.0 — 6 November 2026

Audit memakai ZIP modifikasi pengguna sebagai sumber utama. Perubahan form, validasi PDF, checklist PublicationReadiness, serta AuditHistoryPresenter dipertahankan. Dependency Laravel pengguna (13.40.0) tetap dipakai. Ini audit source dan pengujian aplikasi, bukan penetration test eksternal atau sertifikasi production.

| Temuan | Perbaikan | Verifikasi |
| --- | --- | --- |
| 7 advisory pada 4 paket Symfony | Update terarah http-foundation, http-kernel, routing, yaml | Composer audit akhir tanpa advisory |
| ZIP memuat .env, vendor, cache dan runtime | Paket distribusi hanya source, lockfile, asset build, template environment, dokumen | Pemeriksaan isi ZIP |
| Pengambilan aktivitas dashboard memakai created_at padahal label menyebut diperbarui | Urut updated_at, pilih kolom yang diperlukan | Regression suite |
| Grafik tahunan belum menyediakan tahun kosong dan cakupan jelas | Grafik enam tahun dengan zero values, tabel angka, komposisi tiga jenis karya; scope akses dipakai pada agregasi | Tes tidak membocorkan karya akun lain |
| Konfirmasi bawaan browser dan tombol bisa diklik ulang | SweetAlert2 lokal, fokus awal Batal, pesan dampak tindakan, submit guard/loading | Browser cancel/confirm/payload |
| Error masih berupa daftar tanpa navigasi ke field | Bahasa Indonesia, summary fokus, tautan ke field, aria-invalid; error pages khusus | Tes escaping dan browser |
| Status publishing membingungkan | Langkah workflow, petunjuk per status, polling 15 detik maksimal 20 kali; refresh eksplisit | Authorization endpoint dan idle session |
| Retry lookup halaman berikutnya bisa melompati hasil ketika koneksi gagal | Nomor halaman baru disimpan hanya setelah respons berhasil | Review JS dan browser lookup |
| Aksi mutasi tidak seluruhnya punya batas per akun | Limiter 60 per menit, tetap mempertahankan limiter khusus | Tes konfigurasi limiter dan suite akses |
| Docker sebelumnya hanya profil HTTPS produksi | Profil lokal HTTP, Mailpit, DB/volume terpisah, target testing dan job CI Docker | YAML/isolasi port diperiksa; build/runtime belum dijalankan |

## Keputusan UX

Spacing memakai area konten 32 px desktop/16 px mobile dan panel 24/16 px. Perubahan visual ditempatkan pada experience.css agar stylesheet pengguna tidak ditimpa. Notifikasi sukses tetap inline dan bisa ditutup; error tidak menghilang otomatis. Grafik memakai SVG/progress HTML sehingga tidak membutuhkan library chart dan tetap mempunyai angka alternatif. Tidak ada data statistik acak yang ditampilkan pada aplikasi.

SweetAlert2 diminta untuk konfirmasi; paket dibundel melalui Vite tanpa CDN. Tidak ada HTML dari input pengguna yang dimasukkan ke popup: titleText/text digunakan. Form metadata mengingatkan ketika pengguna meninggalkan perubahan yang belum dikirim. Loading hanya menyatakan proses berjalan, bukan persentase upload palsu.

Polling status tidak memperpanjang waktu idle session. Endpoint tetap melewati auth, active, verification (sesuai config), Gate view, rate limit dan no-store. Polling tidak mengubah status karya dan berhenti saat sesi berakhir/status berubah; hidden tab tidak terus meminta data.

## Upgrade

Backup DB dan dokumen sebelum mengganti release. Jangan menimpa .env atau mengganti APP_KEY lama. Rilis ini tidak menambah migration baru; migration hardening dari 1.3 tetap wajib, termasuk scan_status. Jalankan composer install dari lockfile baru, rebuild assets jika perlu, cache ulang config/routes/views dan restart worker. Pada Docker rebuild image app/web lalu recreate app1/app2/worker/scheduler/web. Jangan hanya restart container lama karena source tersimpan dalam image.

## Prioritas tersisa

1. Jalankan Docker build/test dan smoke stack lokal sesuai DOCKER_LOCAL.md pada mesin dengan daemon Docker.
2. Uji stack HTTPS production, SMTP, ClamAV nyata, concurrency MySQL, Redis multi-replica, restore backup dan beban sebelum go-live.
3. Tentukan kebutuhan institusi untuk MFA/SSO, retensi, serta audit independen; belum diimplementasikan pada rilis ini.
4. Ukur pencarian contains pada data representatif sebelum memutuskan FULLTEXT. Indeks biasa tidak menjamin query LIKE '%kata%' cepat.

---

## Catatan review rilis sebelumnya

# Review engineering dan rancangan kelanjutan

## Penilaian

Penilaian engineering source 1.3.0: **8/10 untuk kesiapan aplikasi menuju staging**, bersifat subjektif berdasarkan review source dan pengujian lokal. Ini bukan skor penetration test, bukan sertifikasi keamanan, dan bukan pengesahan kapasitas/HA production.

Fondasi yang dipertahankan: model relasional Laravel, policy ownership/lifecycle, transaksi karya, validasi import, jejak audit, publikasi asynchronous yang idempotent, penyimpanan PDF privat, dan pagination database. Source sebelumnya sudah memiliki 77 tes lulus; masalahnya tidak memerlukan penggantian framework.

## Temuan dan tindakan

| Temuan sebelumnya | Tindakan 1.3.0 | Bukti / batas |
| --- | --- | --- |
| Dropdown mengambil seluruh referensi | Lookup 25 item + selected records, debounce/cancel | Tes bounded results, selected record, browser repeater |
| Kartu mengambil kolom besar/tidak diperlukan | Pilih kolom kartu dan periksa ulang public scope | Tes query count dan tidak mengambil catatan/abstrak |
| Query total dashboard berulang | Pakai agregasi status yang sudah tersedia | Regression suite |
| Master/user belum memiliki token konflik | lock_version dan row lock pada edit/delete | Tes stale edit tidak menimpa perubahan/audit |
| Kredensial/peran berubah tetapi sesi lama bertahan | auth_version dan server-side expiry | Tes password, reset, email, role, nonaktif, idle/absolute |
| Password rule berbeda antarjalur | PasswordPolicy bersama | Tes registrasi/reset/password; bcrypt byte limit |
| Respons reset membedakan akun | Pesan generik + rate limit, notifikasi queue | Tes respons dikenal/tidak dikenal |
| Header belum mencakup CSP/host | Nonce CSP, strict host, no-store, proxy allowlist | Tes host/CSRF/header dan browser |
| Upload belum diperiksa antivirus | ClamAV fail-closed, status scan/legacy command | Mock integrasi aplikasi dan scanner unavailable; daemon nyata belum diuji |
| Tampilan hanya light | System/light/dark dan palet semantik | Browser responsif, fokus, persistensi tema |
| Multi-instance hanya konsep | Compose, Nginx least_conn, Redis, shared volume | Konfigurasi disediakan; stack belum dijalankan di sini |
| Dokumentasi release tersebar | Indeks docs dan panduan per area | Link dan isi disesuaikan source |

Advisory symfony/mime yang ditemukan pada dependency awal ditangani dengan update terarah. Hasil composer audit terakhir disimpan dalam VALIDATION.md. Tidak ditambahkan dependency PHP aplikasi baru untuk security/UI.

## Keputusan arsitektur

Monolith tetap dipakai karena kebutuhan domain masih saling terhubung dan deployment institusi harus mudah dipelihara. Folder Laravel standar dipertahankan. Service tidak dipecah menjadi lapisan abstraksi generik yang tidak mempunyai penggunaan nyata. Query Eloquent tetap terbaca; helper kecil hanya menyatukan kebijakan yang berulang.

Pencarian contains dipertahankan demi kompatibilitas. FULLTEXT menjadi tindak lanjut berdasarkan metrik, bukan klaim bahwa indeks B-tree dapat mempercepat semua pencarian substring. Cache hanya agregat publik dengan TTL pendek; aturan izin tetap dibaca dari database.

Pemeriksaan PDF synchronous dipilih agar file tidak dapat diunduh sebelum lolos scan tanpa menambah lifecycle karantina baru. Konsekuensinya upload bergantung pada ketersediaan scanner; timeout dan kegagalan harus dimonitor. Queue terpisah tetap menangani penerbitan dan email.

Desain mempertahankan struktur halaman yang sudah digunakan. Variasi dibatasi pada palet dark yang konsisten, aksen tipe karya, status, hirarki tipografi, dan form yang jelas. Tidak ditambahkan animasi dekoratif, ilustrasi generik, maupun panel yang tidak menyelesaikan kebutuhan pengguna.

## Rencana berikutnya dan acceptance criteria

| Prioritas | Pekerjaan | Syarat selesai |
| --- | --- | --- |
| P0 sebelum go-live | Uji Docker/MySQL/Redis/ClamAV/SMTP/TLS nyata | Checklist OPERATIONS lengkap dengan log |
| P0 sebelum go-live | Migrasi salinan data dan scan legacy | Row count konsisten, dokumen gagal ditangani tanpa membuka akses |
| P0 sebelum go-live | Backup dan restore terisolasi | Berhasil memulihkan DB + file + APP_KEY, durasi tercatat |
| P1 sesuai kebijakan | MFA/SSO administrator | Enrollment, recovery, revoke, audit dan tes akses |
| P1 berdasarkan metrik | EXPLAIN + load test representatif | p95/error/rows examined dan bottleneck terdokumentasi |
| P1 bila kapasitas perlu | FULLTEXT serta benchmark relevansi | Query bahasa/DOI/ISBN dan regression privacy lulus |
| P2 bila lintas host | Private object storage/shared filesystem, managed DB/Redis | Upload/download silang, failover dan restore terbukti |
| P2 kepatuhan | Audit eksternal immutable, retensi/purge dokumen | Kebijakan institusi disetujui dan diuji |

Tidak ada tindakan deployment terhadap server publik yang dilakukan pada sesi ini. Status release tetap production candidate sampai bukti operasional di server tujuan lengkap.
