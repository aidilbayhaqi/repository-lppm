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


## Image SBOM — OWASP 2025 A03/A08

Setiap job `docker` di CI kini membangun image aplikasi dan Nginx lalu membuat dua artefak CycloneDX JSON dari **image build yang sama**, bukan hanya source lockfile. Scanner `Syft v1.57.2` dan `anchore/sbom-action v0.24.3` dikunci; SHA action diverifikasi terhadap GitHub official release. `tools/verify-sbom.mjs` menolak output non-CycloneDX atau daftar komponen kosong/tidak valid. Artefak bernama `osdj-app-cyclonedx-sbom` dan `osdj-web-cyclonedx-sbom` tersedia dari run Actions dan mengikuti kebijakan retensi artefak organisasi.

SBOM membantu inventaris dan analisis CVE, **bukan** bukti image bebas vulnerability. Lengkapi dengan scan CVE berdasarkan SBOM, vulnerability triage, SBOM untuk image yang benar-benar dipromosikan di staging/production (digest & release tag sama), provenance/signature, dan approval release. Jangan mengasumsikan image lokal `RELEASE_TAG=local` sama dengan production bila image dibangun ulang.


## Active secret content gate (OWASP A04)

The source gate now checks **tracked text files** for high-confidence embedded Laravel APP_KEY values, PEM private-key headers, GitHub PATs, and AWS access key identifiers. It prints only file paths and categories—not matched values, lines, or environment contents. Files beyond 4 MiB in recognized text formats and tracked symlinks cause a conservative failure. Files ignored by Git, including each developer's untracked .env, are not opened.

This is deliberately a narrow preventive guard: it **does not scan historical commits**, does not detect every token/provider format, cannot certify absence of secrets, and does not replace dedicated GitHub secret scanning or a history-aware scanner. Existing APP_KEY previously committed in .env.docker remains an unresolved incident until the owner assesses usage, creates verified backups, rotates safely if necessary, and approves any coordinated history remediation. Never commit original key values, even in a ticket or fixture.


## Release evidence manifest — OWASP A08

Setelah image aplikasi dan Nginx di-build dan masing-masing CycloneDX SBOM divalidasi, CI membuat `release-evidence.json` berisi Git commit SHA, **local Docker image configuration IDs**, dan SHA-256 kedua SBOM. Tool `node tools/release-evidence.mjs create` lalu `verify` harus lulus; manifest disimpan sebagai artefak `osdj-release-evidence` dan dapat diperiksa bersama SBOM dari workflow run yang sama.

**Penting:** ID image hasil `docker image inspect` adalah configuration digest image lokal, **bukan** registry manifest digest, signed artifact, atau bukti deploy menggunakan image yang sama. Untuk production, publikasikan/pin image registry dengan `repo@sha256:...`, buat attestation yang benar, dan verifikasi digest tersebut setelah promosi ke staging/production. Jangan menyimpulkan release supply chain siap hanya berdasarkan hash lokal ini.
