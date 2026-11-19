# Dokumentasi OSDJ LPPM 1.6.0

Tanggal: 13 November 2026. Status: **kandidat staging**. Source, migrasi, pengujian otomatis, dan konfigurasi deployment disiapkan dalam paket ini. Penerimaan infrastruktur kampus tetap harus dilakukan sebelum go-live; lihat `VALIDATION.md`.

Dokumen di folder ini menjelaskan **implementasi aktual**. Dokumen pada `../../DOKUMENTASI/`, Word, PDF, workbook, dan diagram paket induk dipertahankan sebagai baseline rancangan. Jika berbeda, gunakan dokumentasi rilis ini untuk menjalankan kode 1.6.0; target baseline yang belum diterapkan disebutkan secara eksplisit.

| Kebutuhan | Dokumen |
| --- | --- |
| Docker lokal tanpa domain | [DOCKER_LOCAL.md](DOCKER_LOCAL.md) |
| Mulai dari instalasi | [README aplikasi](../README.md) |
| Memahami alur pengguna dan status karya | [APPLICATION_FLOW.md](APPLICATION_FLOW.md) |
| Memahami struktur kode, relasi, dan batas modul | [ARCHITECTURE.md](ARCHITECTURE.md) |
| Login, session, otorisasi, upload, dan batas keamanan | [SECURITY.md](SECURITY.md) |
| Query, indexing, caching, dan kapasitas | [PERFORMANCE.md](PERFORMANCE.md) |
| Server, load balancer, upgrade, backup, rollback | [OPERATIONS.md](OPERATIONS.md) |
| Menambah fitur dan menjalankan pengujian | [DEVELOPMENT.md](DEVELOPMENT.md) |
| Catatan review dan prioritas lanjutan | [REVIEW_AND_PLAN.md](REVIEW_AND_PLAN.md) |
| Bukti verifikasi dan pekerjaan yang belum diuji | [VALIDATION.md](VALIDATION.md) |
| Perubahan kompatibilitas rilis | [CHANGELOG.md](CHANGELOG.md) |
| Mengisi Excel dan penulis baru | [IMPORT_EXCEL.md](IMPORT_EXCEL.md) |
| Mengadaptasi data lama | [LEGACY_DATA_MAPPING.md](LEGACY_DATA_MAPPING.md) |

Urutan belajar yang disarankan: README → APPLICATION_FLOW → ARCHITECTURE → DEVELOPMENT. Pengelola server perlu membaca SECURITY, OPERATIONS, dan VALIDATION sebelum membuka layanan untuk pengguna.
