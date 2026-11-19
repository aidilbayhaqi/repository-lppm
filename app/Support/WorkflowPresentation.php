<?php

namespace App\Support;

final class WorkflowPresentation
{
    public static function describe(string $status): array
    {
        return match ($status) {
            'draft' => ['title' => 'Lengkapi draf', 'detail' => 'Periksa metadata dan penulis, lalu ajukan verifikasi. PDF boleh ditambahkan bila tersedia.', 'step' => 0],
            'submitted' => ['title' => 'Menunggu pemeriksaan', 'detail' => 'Verifier akan memeriksa pengajuan. Metadata dikunci selama proses ini.', 'step' => 1],
            'pending_verification' => ['title' => 'Sedang diperiksa', 'detail' => 'Tunggu keputusan verifier. Catatan pemeriksaan tersimpan di riwayat.', 'step' => 1],
            'verified' => ['title' => 'Siap diterbitkan', 'detail' => 'Admin atau operator dapat memulai penerbitan setelah seluruh pemeriksaan terpenuhi.', 'step' => 2],
            'publishing' => ['title' => 'Penerbitan dalam antrean', 'detail' => 'Sistem memeriksa dokumen sebelum menerbitkan. Halaman ini memeriksa perubahan status secara berkala.', 'step' => 3],
            'published' => ['title' => 'Sudah terbit', 'detail' => 'Karya sudah diterbitkan. Siapa yang dapat melihatnya mengikuti pengaturan akses metadata dan dokumen.', 'step' => 4],
            'revision_required' => ['title' => 'Perlu revisi', 'detail' => 'Baca catatan verifier, ubah metadata atau dokumen, lalu ajukan kembali.', 'step' => 0],
            'rejected' => ['title' => 'Pengajuan ditolak', 'detail' => 'Baca alasan penolakan di riwayat. Perbaiki draf sebelum mengajukan kembali.', 'step' => 0],
            'publish_failed' => ['title' => 'Penerbitan belum berhasil', 'detail' => 'Baca riwayat penerbitan. Admin dapat membuka revisi untuk memperbaiki dokumen atau mencoba penerbitan kembali.', 'step' => 2],
            default => ['title' => 'Karya diarsipkan', 'detail' => 'Karya tidak ditampilkan di repositori. Admin atau operator dapat memulihkannya sebagai draf.', 'step' => -1],
        };
    }
}
