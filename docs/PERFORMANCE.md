# Fetching, indexing, dan kapasitas

## Perubahan aktual

| Area | Implementasi |
| --- | --- |
| Search lintas jenis | UNION ALL di database, sort deterministik, 12 hasil/halaman |
| Hydration kartu | Kolom sesuai kebutuhan kartu; tipe tanpa hasil dilewati; scope publik diperiksa kembali |
| Form referensi | 25 opsi awal ditambah selected ID yang valid; tidak memuat seluruh tabel |
| Lookup | 25 hasil/halaman, has_more, whitelist entity, response ID/label tanpa data akun |
| JS lookup | Debounce 300 ms, AbortController, penolakan respons terlambat, status error, pagination |
| Dashboard | Total publikasi memakai agregasi status yang sudah diambil |
| Beranda | Cache agregat jumlah publik maksimal 60 detik; kartu terbaru tetap query publik aktual |
| Riwayat | Audit, attempt, dan verifikasi masing-masing 5; versi dokumen 20 per halaman |
| Daftar admin | 20/halaman; daftar akun hanya memilih kolom yang ditampilkan |
| Asset/proses | Build Vite, immutable asset cache, OPcache, PHP-FPM, worker terpisah |

Jumlah beranda boleh tertinggal sampai 60 detik setelah perubahan. HTML, kartu privat, dan hasil yang bergantung izin tidak dimasukkan ke cache global. Search lama memang sudah dipaginasi di database; perbaikan rilis ini tidak mengklaim baru memperkenalkan pagination.

## Indeks baru

| Tabel | Kolom |
| --- | --- |
| publikasi | repository_status, public_visibility, archived_at, tahun_publikasi |
| buku | status, public_visibility, archived_at, tahun_publikasi |
| kekayaan_intelektual | status, public_visibility, archived_at, tahun |
| Tiga tabel karya | created_by, created_at, id |
| penulis dan dosen | aktif, nama, id |
| audit_logs | subject_type, subject_id, id |
| verification_histories / publish_attempts | publikasi_id, id |
| import_batches | user_id, id |
| users | role, is_active, id |

Migration additive mempertahankan indeks lama. publication_discovery tumpang tindih sebagian; evaluasi pemakaian dan EXPLAIN di staging sebelum menghapus melalui migration terpisah. Indeks menambah biaya write/disk, sehingga tidak semua kolom diberi indeks.

## Batas pencarian

Search utama mempertahankan contains `LIKE '%kata%'`. B-tree tidak mengubahnya menjadi full-text search. Indeks gabungan membantu filter/status/ownership, sedangkan scanning teks masih perlu diukur. Lookup memakai prefix `kata%` dengan escaping wildcard agar sesuai dengan indeks nama.

MySQL FULLTEXT/OpenSearch dalam baseline masih target pengembangan. Tambahkan bila EXPLAIN dan latency membuktikan kebutuhan, lalu uji relevansi, stopword, token pendek, DOI/ISBN, dan bahasa Indonesia. Sort lintas UNION masih dapat memakai filesort.

```sql
EXPLAIN SELECT id, judul, tahun_publikasi FROM publikasi
WHERE repository_status='published' AND public_visibility='public'
AND archived_at IS NULL AND tahun_publikasi=2026
ORDER BY tahun_publikasi DESC, judul, id LIMIT 12;

EXPLAIN SELECT id,nama FROM penulis
WHERE aktif=1 AND nama LIKE 'Adi%'
ORDER BY nama,id LIMIT 26;
```

Jalankan pada staging dengan dataset representatif. Bandingkan rows examined, query count, p50/p95/p99, response size, RAM dan CPU. Tes regresi membatasi pencarian satu jenis publikasi hingga lima query; ini bukan SLA semua halaman.

## Load balancing

Nginx least_conn membagi FastCGI ke dua instance. Redis session/limiter/lock, MySQL, dan private volume digunakan bersama sehingga tidak memerlukan sticky session. Konfigurasi tidak mengaktifkan retry non-idempotent setelah POST dikirim upstream. Session blocking menserialkan penyimpanan session pengguna yang sama; pengguna berbeda tetap paralel.

Dua replica dalam Compose berada pada satu host, sehingga bukan HA lintas host. pm.max_children=12 per replica adalah titik awal, bukan kapasitas terukur. Sesuaikan dengan RSS proses, RAM MySQL/ClamAV, dan beban. Untuk multi-host, sediakan shared filesystem/private object storage dan pengelolaan database/Redis yang sesuai; local volume Docker tidak otomatis terbagi ke host lain.

## Load test

```bash
BASE_URL=https://staging.example.org SEARCH_TERM=pendidikan k6 run deploy/load-tests/public.js
```

Skenario read-only 10→25 virtual users dengan jeda 20 detik mempertimbangkan limiter per-IP. Target contoh p95 search <1 detik, error <1%, **belum diukur pada server tujuan**. Ini bukan bukti kapasitas 1.000 pengguna. Untuk uji lebih besar, gunakan staging terisolasi, dataset sintetis representatif, dan konfigurasi limiter staging yang tercatat.

Uji penerimaan juga harus membuktikan session tetap hidup saat satu replica restart, upload melalui satu replica dapat dibaca dari replica lain, worker mati tidak kehilangan attempt, dan dua editor tidak menimpa perubahan. Simpan spesifikasi hardware, konfigurasi, commit, jumlah record, EXPLAIN, serta hasil load test.

## Input dan import 1.6

Pencocokan referensi baru memakai unique entry_key/NIDN. Referensi legacy masih dicari menurut nama yang dinormalisasi; ukur query ini dengan data institusi sebelum memperluas volume import. Preview tidak menulis referensi, commit memproses maksimal 1.000 baris dalam satu transaksi. Unggahan form dibatasi total 20 MB agar berada di bawah post_max_size 25 MB. Antivirus tetap sinkron; timeout request perlu diuji dengan beberapa file pada infrastruktur target.
