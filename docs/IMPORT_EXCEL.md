# Import Excel — rilis 1.6

Buka **Import data**, unduh template Excel sesuai jenis karya, isi sheet **Data**, lalu unggah untuk preview. Template berformat XLSX asli, kolom memakai format Text agar NIDN/ISBN tidak kehilangan digit. Header dibekukan di baris pertama. Tidak ada data contoh yang akan ikut terimpor tanpa sengaja.

Jangan mengganti nama header. Hapus kolom yang tidak dibutuhkan jika perlu; `judul` dan salah satu `penulis_nama` atau `penulis_ids` harus tetap ada. Maksimal file 5 MB, 1.000 baris data, dan 50 kolom. Sheet pertama yang tercantum pada workbook dipakai. CSV UTF-8 dengan koma/titik koma tetap didukung; endpoint template menerima `?format=csv` untuk format lama.

## Contoh satu karya

Isi nilai berikut pada satu baris template Jurnal. Sel lain boleh kosong.

| Kolom | Contoh nilai |
| --- | --- |
| `judul` | Pengelolaan Arsip Penelitian pada Repositori Kampus |
| `tahun_publikasi` | `2026` |
| `publication_type` | `artikel_jurnal` |
| `penulis_nama` | `Rani Prasetya\|Budi Santoso` |
| `penulis_jenis` | `mahasiswa\|dosen` |
| `penulis_nidn` | `\|0011223344` |
| `penulis_identitas` | `M2026001\|` |
| `journal_name_manual` | Jurnal Riset Contoh |
| `sinta_rank` | `3` |
| `public_visibility` | `private` |

Data ini hanya contoh. NIDN, nama jurnal, dan peringkat harus diganti dengan informasi yang benar sebelum digunakan. DOI/ISBN/ISSN boleh kosong jika belum tersedia; jangan menciptakan identifier.

## Dua metode penulis

- **Nama baru/rekomendasi berdasarkan identitas:** gunakan `penulis_nama`. Jenis mendukung `mahasiswa`, `dosen`, `eksternal`; jenis kosong dianggap mahasiswa. Dosen wajib NIDN 10 digit. Mahasiswa/eksternal dapat mengisi `penulis_identitas` seperti NIM. Untuk afiliasi dosen, isi `penulis_prodi` dan `penulis_fakultas`; boleh kosong jika belum diketahui. Prodi baru membutuhkan fakultas.
- **ID yang sudah tersedia:** isi `penulis_ids`, misalnya `12|35`, lalu kosongkan kolom `penulis_nama`. ID harus aktif. Gunakan ID bila identitas sudah dipastikan, terutama jika ada nama yang sama.

Jangan mengisi kedua metode pada satu baris. Untuk beberapa nama, pisahkan setiap kolom penulis dengan `|` dalam urutan yang sama. Contoh NIDN `|0011223344` artinya penulis pertama tidak memiliki NIDN, penulis kedua memakai `0011223344`. Jangan pisahkan dengan koma karena nama dapat memuat gelar. Jangan menulis karakter backslash; karakter pemisah hanya `|`.

Jika NIDN sudah ada dengan nama berbeda, preview/commit menolak data. Periksa identitas atau gunakan rekomendasi yang sesuai; sistem tidak mengganti nama dosen lama. Nama tanpa identitas tidak dipakai untuk menggabungkan orang lintas kontributor. Penambahan referensi tidak membuat akun login atau mengubah peran.

Flag korespondensi/peneliti utama belum menjadi kolom import; lengkapi dari edit karya setelah import bila diperlukan. Urutan penulis tetap mengikuti kolom input.

## Identitas terbitan

| Kelompok | Kolom dan aturan |
| --- | --- |
| Judul dan akses | `judul` wajib; `public_visibility` default `private`, pilihan lain `public`, `internal`, `restricted`. Status tetap draf setelah import. |
| Jurnal / prosiding | Isi `journal_name_manual` untuk nama baru atau `jurnal_id` untuk rekomendasi. `journal_source` boleh kosong dan ditentukan dari isian nama; pilihan eksplisit `manual`/`reference` tersedia. |
| Jenis karya | `publication_type`: `artikel_jurnal`, `prosiding`, `lainnya`. SINTA/Scopus adalah pengindeksan, bukan jenis dokumen. |
| DOI | `doi` menerima `10.xxxx/...` atau URL doi.org; dinormalisasi menjadi huruf kecil dan harus unik. |
| ISBN / ISSN | `isbn` 10/13 digit (ISBN-10 dapat berakhir X), spasi/tanda hubung dihapus. Artikel prosiding dapat berbagi ISBN. Buku memakai ISBN unik. `issn_cetak` / `issn_elektronik` memakai format `1234-567X` atau tanpa tanda hubung. Validasi format tidak memverifikasi checksum/registrasi eksternal. |
| SINTA | `sinta_rank` 1–6; kosong bila belum dicatat. |
| Scopus | `scopus_indexed` 1/0; `scopus_quartile` Q1–Q4. Pada import, kuartil terisi dengan flag kosong menyiratkan terindeks. Flag eksplisit 0 mengosongkan kuartil. |
| Cara indeks | `indexing_mode` kosong ditentukan dari peringkat yang diisi. `manual` menyimpan rank; `reference` memakai `pengindeks_ids` (pisah `|`). Tingkat referensi dapat dilengkapi dari form setelah import. |
| Bukti indeks | `indexing_year`, `indexing_source` (URL HTTP/HTTPS). Tidak mengambil data otomatis dari URL. |
| Buku | `publisher_name` atau `penerbit_id`; `publisher_source` boleh kosong dan diinferensikan dari nama. `jumlah_halaman` bilangan bulat positif; `kategori` teks. |
| HKI | `jenis` wajib, `tahun`, `nomor_permohonan`, `nomor_pencatatan`, `tanggal_permohonan`, `tanggal_pencatatan`, `periode`. Tanggal pencatatan tidak boleh sebelum permohonan. |
| Tanggal | Utamakan `YYYY-MM-DD`. Sel bertipe tanggal Excel dengan format tanggal standar/custom juga dibaca; sistem tanggal 1900/1904 diperhitungkan. Sel General berisi angka tidak otomatis dianggap tanggal. |
| Sitasi artikel | `sitasi_2026` dan tahun lain 1901–tahun berjalan. Angka nonnegatif; sumber default Google Scholar, cakupan umum. Pastikan sesuai sumber asli. |
| Metadata artikel lain | `abstrak`, `volume`, `nomor`, `halaman`, `tautan_google_scholar`, `spmi`, `tahun_dasar`, `klasifikasi`, `biaya`. Biaya angka tanpa simbol rupiah, desimal titik. |
| Semua jenis | `tautan_eksternal` URL HTTP/HTTPS maksimal 500 karakter; `keterangan` catatan internal. |

## Preview dan penyimpanan

Preview menampilkan judul, tahun, nama penulis, akses, dan error per nomor baris. Tidak ada penulis/dosen/jurnal/penerbit yang dibuat saat preview. Semua error harus dibenahi sebelum batch dapat disimpan. Preview berlaku 24 jam dan hanya dapat dibuka/disimpan oleh pemilik batch yang berwenang.

Saat disimpan, validasi dijalankan lagi karena referensi atau identifier mungkin berubah setelah preview. Semua baris dan referensi baru masuk satu transaksi. Konflik pada baris terakhir membatalkan baris sebelumnya. Batch yang sudah berhasil disimpan tidak membuat data ganda jika diklik ulang. Duplikat berdasarkan DOI/ISBN buku/nomor pencatatan HKI atau kandidat judul+tahun+terbitan harus ditinjau.

Import hanya membuat draf. PDF tidak diambil dari URL atau sel Excel. Unggah dokumen dari edit karya tahap ketiga atau halaman detail, kemudian ikuti proses verifikasi/penerbitan yang sama.

## Jika file ditolak

- **NIDN kehilangan nol awal:** kembalikan format Text dan masukkan ulang nilai asli. Nol yang sudah hilang tidak ditebak oleh aplikasi.
- **Rumus Excel:** salin hasil → Paste Values. Formula tidak dievaluasi, sekalipun menyertakan cached result.
- **Workbook lama:** pindahkan kolom yang diperlukan ke template, bagi maksimal 1.000 baris, dan periksa mapping pada LEGACY_DATA_MAPPING.md.
- **Macro, external link, atau entity XML:** gunakan XLSX bersih tanpa konten tersebut. Pembaca tidak membuka tautan eksternal.
- **Header tidak dikenal:** pakai header template sesuai jenis karya; file lampiran dan field akun/status workflow bukan kolom import.
