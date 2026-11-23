# Release Safety Runbook — OSDJ-LPPM

Jalankan dari root yang memiliki `artisan` pada Windows PowerShell. Semua perintah di bawah **read-only** kecuali langkah *pembuatan source release* yang dilakukan secara terpisah.

```powershell
node .\tools\release-safety.mjs --source .
node --test .\tests\Tooling\release-safety.test.mjs
```

Jika membuat ZIP rilis bersih lewat workflow release existing, sebelum menyerahkan file ke orang lain jalankan:

```powershell
node .\tools\release-safety.mjs --archive "C:\path\to\clean-release.zip"
```

Tool ini membaca daftar path di ZIP, **tidak pernah menampilkan nilai `.env` atau isi file**. Nilai exit code `0` berarti lolos aturan path; code `1` berarti diblokir. Lolos gate **tidak otomatis berarti ZIP aman**: isi source, file kunci yang tidak memakai ekstensi dikenal, dan kebocoran yang sudah masuk Git tetap harus dipindai menggunakan secret scanner terpisah.

### Alur rilis yang direkomendasikan

1. Pastikan `git status --short`, revision, dependency lock, dan test hasil commit diketahui.
2. Di CI, jalankan pemeriksaan source, PHPUnit di SQLite+MySQL dedicated, `composer audit`, `npm audit`, `npm ci`, `npm run build`, dan Docker build.
3. Buat source archive bersih dari allowlist atau dari `git archive` untuk tracked files (setelah repo discan; `git archive` **tidak menghapus secrets yang sudah di-track**). Hindari zip seluruh direktori Windows.
4. Jalankan `--archive` pada ZIP yang *persis* akan didistribusikan, disusul secret scanning dan inspeksi manual manifest.
5. Promosikan artefak yang sama ke staging; cek storage, session, Redis, SQL, SMTP, ClamAV, perizinan, dan TLS.
6. Uji backup/restore ke infrastruktur terisolasi; simpan bukti dan uji perintah rollback.
7. Deployment production hanya melalui approval eksplisit, bukan melalui script ini.

### Jika gate gagal

- `Docker context policy`: periksa `.dockerignore`; jangan men-disable gate. Build context kemungkinan masih mencakup backup/secrets.
- `ZIP sensitive path`: buat ulang archive dari allowlist; **jangan edit/redistribusikan arsip lama** yang berisi `.env` dan dokumen privat.
- `ZIP64`: pemeriksa ini sengaja fail-closed; gunakan scanner arsip ZIP64 yang sesuai.
- `CONFLICT` saat pemasangan: source lokal berbeda. Hentikan dan merge manual hanya file konflik; jangan menimpa `ci.yml` yang sudah diubah.

### Secret incident

Bila arsip lama pernah dibagikan ke pihak tak berwenang: batasi akses arsip, identifikasi credential yang mungkin terekspos, susun rotasi sesuai dependensi aplikasi dan data terenkripsi, serta konsultasikan pemilik sistem. **Jangan langsung mengganti `APP_KEY` secara spontan** karena data terenkripsi dan session bisa terdampak. Jangan mengirim isi `.env` atau dump DB melalui chat.


## Immutable CI actions dan update dependency

Workflow .github/workflows/ci.yml menggunakan commit SHA eksplisit untuk actions/checkout, actions/setup-node, dan shivammathur/setup-php. GitHub API mengonfirmasi commit tersebut sesuai tag rilis resmi pada 22 November 2026. Gate release-safety.mjs --source menolak action pihak ketiga yang memakai versi/tag bergerak.

Dependabot di .github/dependabot.yml mengusulkan pembaruan Composer, npm, dan GitHub Actions setiap minggu. Perubahan tidak di-merge otomatis; periksa provenance SHA, diff, advisory, dan CI. Pinning tidak menggantikan SBOM, SAST, DAST atau vulnerability scanning container.
