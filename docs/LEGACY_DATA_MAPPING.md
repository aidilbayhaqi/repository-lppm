# Migrasi workbook data sumber

Sumber: `^DATA PENELITIAN, BUKU DAN HKI - 01.09.2026.xlsx` dalam paket induk, ditinjau secara baca-saja. Workbook asli dipertahankan tanpa perubahan. Pemeriksaan ini berfokus pada struktur untuk migrasi, bukan audit kebenaran angka rekap atau publikasi setiap dosen.

Workbook mempunyai 12 sheet. `Publikasi Karya`, `BUKU`, dan `HKI` adalah sumber kandidat karya. `Rumus` adalah referensi klasifikasi. `Rekap`, `Rekap Per Publikasi`, lima rekap program studi, serta `Rekap Per Dosen Institusi` adalah ringkasan/reference, bukan baris karya tambahan.

Template 1.6 juga mendukung penulis_nama/penulis_jenis/penulis_nidn, journal_name_manual, publisher_name, serta ISBN/ISSN artikel. Gunakan [IMPORT_EXCEL.md](IMPORT_EXCEL.md); mapping di bawah tetap berguna untuk migrasi terkontrol berbasis ID yang sudah diperiksa.

## Mengapa workbook tidak diunggah langsung

- Header aplikasi memakai kontrak template, sedangkan workbook memakai label bisnis dan banyak kolom tambahan.
- Header HKI berada pada baris 2; sheet publikasi/buku menggunakan baris 1.
- Workbook berisi ribuan formula, baris kosong/template, serta rentang yang melebihi 1.000 baris. Dimensi worksheet bukan jumlah karya valid.
- Ada tanggal teks Indonesia dan tanggal serial Excel; ISBN juga mengandung variasi tanda hubung Unicode.
- Nama penulis mengandung gelar/penanda peran dan dapat memuat banyak orang dalam satu sel. Split koma akan merusak nama bergelar.
- Data keuangan, identitas dosen dan administrasi akun tidak boleh ikut menjadi metadata publik secara otomatis.

## Mapping yang dapat dipakai

| Sheet / kolom sumber | Tujuan | Aturan |
| --- | --- | --- |
| Publikasi Karya / Judul, Tahun | `judul`, `tahun_publikasi` | Wajib periksa tahun invalid dan judul kosong. |
| Publikasi / DOI | `doi` | Normalisasi URL/prefix; jangan mengisi dari Link bila link bukan DOI. |
| Publikasi / Penulis Artikel, Dosen UMIBA, Dosen PI | `penulis_ids`, flag penulis pada form | Verifikasi identitas dan urutan; hubungkan ID master, jangan menggabungkan otomatis berdasarkan nama. |
| Publikasi / Jurnal, Publisher, ISSN | master jurnal/penerbit, `jurnal_id` | Buat master dahulu; validasi ISSN sebagai identifier, bukan judul. |
| Publikasi / Vol. No. | `volume`, `nomor` | Kolom gabungan harus dipisah sesuai isi, tidak menebak seluruh baris memakai delimiter sama. |
| Publikasi / Link, Halaman, Google Scholar | `tautan_eksternal`, `halaman`, `tautan_google_scholar` | Simpan hanya URL HTTP(S) yang sesuai; aplikasi tidak mengambil isi URL. |
| Publikasi / Sitasi 2018–2026 | `sitasi_2018` … `sitasi_2026` | Gunakan nilai angka yang telah diperiksa; jangan mengganti kosong menjadi nol tanpa dasar. |
| Publikasi / Indexing, Kesimpulan Akhir Indexing | master pengindeks/tingkat, `pengindeks_ids` | Pisahkan lembaga pengindeks dari tingkat Q/S; lengkapi tingkat lewat form setelah import. |
| Publikasi / SPMI, Tahun Dasar, Klasifikasi, BIAYA | `spmi`, `tahun_dasar`, `klasifikasi`, `biaya` | Field internal; tahun dasar harus angka, biaya tanpa format mata uang. |
| BUKU / Judul, Tahun, ISBN | `judul`, `tahun_publikasi`, `isbn` | Normalisasi semua variasi tanda hubung/spasi sebelum template; periksa identifier. |
| BUKU / Penulis, Penerbit, Jumlah Halaman, Kategori Buku | `penulis_ids`, `penerbit_id`, `jumlah_halaman`, `kategori` | Mapping ID master harus disetujui; angka halaman positif. |
| BUKU / Tanggal Terbit, Link | `tanggal_publikasi`, `tautan_eksternal` | Tanggal diubah menjadi ISO; periksa tanggal kosong/ambigu. |
| HKI / Judul, Tahun, Jenis Ciptaan, Pencipta | `judul`, `tahun`, `jenis`, `penulis_ids` | Pertahankan urutan pencipta; hindari duplikasi nama/gelar. |
| HKI / Nomor dan Tanggal Permohonan/Pencatatan | field `nomor_*`, `tanggal_*` yang sesuai | Nomor disimpan teks agar nol di depan tidak hilang; tanggal ISO. |
| Soft File, Link Gdrive, Cover | Dokumen melalui upload terpisah | Link bukan file yang sudah dipindahkan. Periksa hak akses/distribusi sebelum menyalin PDF. |
| Publish, SINTA, kompensasi, pencairan, data akun | Pemeriksaan administratif/manual | Jangan dijadikan perintah publish, role akun, atau bukti verifikasi otomatis. |

Sitasi dan biaya buku/HKI serta rekap semester yang belum memiliki field aplikasi tidak dipaksa masuk ke kolom lain. Pertahankan workbook sumber; ajukan perluasan schema/report setelah definisi metrik disepakati. Import sederhana saat ini hanya memetakan sitasi publikasi sebagai Google Scholar cakupan umum; pastikan sumber angka benar sebelum menggunakannya.

## Prosedur migrasi

1. Buat salinan kerja, pertahankan file sumber asli dan nomor baris asal.
2. Bentuk daftar fakultas/prodi/dosen/penulis/jurnal/penerbit/indexer yang telah diverifikasi. Nama tanpa gelar membantu pencarian kandidat, tetapi tidak membuktikan identitas sama.
3. Siapkan tabel mapping nama sumber → ID master dengan persetujuan operator. Pertahankan penulis eksternal/mahasiswa, bukan hanya dosen UMIBA.
4. Pilih baris karya, buang baris template hanya setelah diketahui bukan data. Evaluasi formula di aplikasi spreadsheet asal dan salin hasil sebagai nilai.
5. Konversi tanggal, identifier dan kolom sesuai tabel; bagi setiap jenis karya ke batch maksimal 1.000 baris.
6. Set `public_visibility=private`. Unggah template, tinjau seluruh error/duplikat, lalu commit sebagai draf.
7. Cocokkan jumlah dan contoh baris ke sumber. Rekap per dosen tidak boleh dijumlahkan sebagai jumlah karya institusi karena satu karya dapat memiliki banyak dosen.
8. Unggah PDF yang telah diperiksa, lengkapi flag penulis/pengindeks, lalu jalankan verifikasi dan publish normal.

Tidak ada data workbook asli yang sudah dimasukkan ke database demo atau dipublikasikan dalam rilis ini. Tampilan demo tetap memakai data sintetis.
