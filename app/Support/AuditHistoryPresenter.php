<?php

namespace App\Support;

use App\Enums\RepositoryStatus;
use App\Models\AuditLog;
use App\Models\Fakultas;
use App\Models\Jurnal;
use App\Models\ProgramStudi;
use Illuminate\Support\Str;

class AuditHistoryPresenter
{
    private const ACTIONS = [
        'created' => 'Karya dibuat',
        'admin_self_review' => 'Pemeriksaan oleh admin terkait karya',
        'demo_seeded' => 'Data contoh ditambahkan',
        'updated' => 'Metadata diperbarui',
        'document_uploaded' => 'Dokumen diunggah',
        'document_replaced' => 'Versi dokumen diperbarui',
        'document_removed' => 'Dokumen dilepas',
        'document_visibility' => 'Akses dokumen diubah',
        'submit' => 'Karya diajukan',
        'review' => 'Pemeriksaan dimulai',
        'approve' => 'Verifikasi disetujui',
        'revision' => 'Revisi diminta',
        'reject' => 'Pengajuan ditolak',
        'publish' => 'Proses penerbitan dimulai',
        'published' => 'Karya diterbitkan',
        'publish_failed' => 'Penerbitan gagal',
        'reopen' => 'Karya dibuka kembali',
        'unpublish' => 'Karya ditarik',
        'archive' => 'Karya diarsipkan',
        'restore' => 'Karya dipulihkan',
    ];

    private const FIELDS = [
        'judul' => 'Judul',
        'abstrak' => 'Abstrak',
        'doi' => 'DOI',
        'jurnal_id' => 'Jurnal',
        'fakultas_id' => 'Fakultas asal jurnal',
        'program_studi_id' => 'Program studi asal jurnal',
        'tahun_publikasi' => 'Tahun publikasi',
        'tanggal_publikasi' => 'Tanggal publikasi',
        'tautan_eksternal' => 'Tautan eksternal',
        'tautan_google_scholar' => 'Google Scholar',
        'academic_status' => 'Status akademik',
        'repository_status' => 'Status repository',
        'status' => 'Status',
        'public_visibility' => 'Akses metadata',
        'publik' => 'Akses publik dokumen',
        'current_version' => 'Versi dokumen',
        'isbn' => 'ISBN', 'issn_cetak' => 'ISSN cetak', 'issn_elektronik' => 'ISSN elektronik',
        'jenis_dokumen' => 'Jenis dokumen', 'nama_file_asli' => 'Nama file',
        'authors' => 'Penulis / pencipta',
        'citations' => 'Data sitasi',
        'indexers' => 'Data pengindeksan',
        'tahun_dasar' => 'Tahun dasar',
        'biaya' => 'Biaya',
        'publication_type' => 'Jenis karya', 'journal_name_manual' => 'Nama jurnal manual', 'sinta_rank' => 'Peringkat SINTA',
        'scopus_indexed' => 'Terindeks Scopus', 'scopus_quartile' => 'Kuartil Scopus', 'indexing_year' => 'Tahun indeks', 'indexing_source' => 'Sumber indeks', 'indexing_mode' => 'Cara mengisi indeks',
    ];

    public static function title(string $action): string
    {
        return self::ACTIONS[$action]
            ?? Str::headline($action);
    }

    public static function summary(string $action): string
    {
        return match ($action) {
            'created' => 'Data karya baru berhasil ditambahkan ke repositori.',
            'admin_self_review' => 'Admin memeriksa karya yang dibuat atau ditulisnya sendiri. Alasan tindakan tercatat di bawah.',
            'document_uploaded' => 'File PDF baru ditambahkan ke karya.',
            'document_replaced' => 'Versi PDF baru disimpan dan versi sebelumnya dipertahankan.',
            'document_removed' => 'Dokumen dilepas dari karya; riwayat audit tetap disimpan.',
            'updated' => 'Metadata telah diperbarui.',
            'submit' => 'Karya masuk antrean pemeriksaan. Metadata dikunci selama diperiksa.',
            'review' => 'Verifier mulai memeriksa metadata dan kelengkapan karya.',
            'approve' => 'Pemeriksaan selesai. Karya dapat dilanjutkan ke tahap penerbitan.',
            'revision' => 'Perbaiki karya sesuai catatan, lalu ajukan kembali.',
            'reject' => 'Pengajuan tidak disetujui. Lihat catatan pemeriksaan.',
            'publish' => 'Pemeriksaan akhir dan proses penerbitan dimulai.',
            'published' => 'Karya berhasil diterbitkan sesuai pengaturan aksesnya.',
            'publish_failed' => 'Penerbitan belum berhasil. Pengelola perlu memeriksa penyebabnya.',
            'archive' => 'Karya disimpan dalam arsip dan tidak tampil di repositori.',
            'restore' => 'Karya dipulihkan sebagai draf.',
            'reopen' => 'Karya dibuka kembali untuk perbaikan.',
            'unpublish' => 'Karya ditarik dari repositori.',
            default => 'Aktivitas tersimpan dalam riwayat karya.',
        };
    }

    public static function changes(AuditLog $history): array
    {
        // Pembuatan karya ditampilkan sebagai satu aktivitas,
        // tanpa memunculkan seluruh snapshot JSON.
        if ($history->action === 'created') {
            return [];
        }

        $old = $history->old_values ?? [];
        $new = $history->new_values ?? [];

        $ignored = [
            'id',
            'document_id',
            'created_by',
            'lock_version',
            'checksum',
            'attempt',
            'code',
        ];

        $changes = [];

        $fields = array_unique(array_merge(
            array_keys($old),
            array_keys($new)
        ));

        foreach ($fields as $field) {
            if (in_array($field, $ignored, true)) {
                continue;
            }

            $before = $old[$field] ?? null;
            $after = $new[$field] ?? null;

            if ($before === $after) {
                continue;
            }

            $listChanged = is_array($before) || is_array($after);

            $changes[] = [
                'label' => self::FIELDS[$field]
                    ?? Str::headline($field),

                'before' => $listChanged
                    ? count(is_array($before) ? $before : [])
                        .' entri'
                    : self::formatValue($before, $field),

                'after' => $listChanged
                    ? count(is_array($after) ? $after : [])
                        .' entri'
                    : self::formatValue($after, $field),

                'list' => $listChanged,
            ];
        }

        return $changes;
    }

    private static function formatValue(mixed $value, string $field = ''): string
    {
        if ($value !== null && in_array($field, ['repository_status', 'status'], true)) {
            return RepositoryStatus::tryFrom((string) $value)?->label()
                ?? ['dipublikasikan' => 'Terbit', 'rejected' => 'Ditolak', 'approved' => 'Disetujui', 'queued' => 'Menunggu antrean', 'processing' => 'Sedang diproses', 'success' => 'Berhasil', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan'][$value] ?? 'Status diperbarui';
        }
        if ($field === 'public_visibility') {
            return ['public' => 'Publik', 'private' => 'Privat tim karya', 'internal' => 'Internal', 'restricted' => 'Terbatas'][$value] ?? 'Belum diisi';
        }
        if ($field === 'jenis_dokumen' && $value) {
            return DocumentTypes::label((string) $value);
        }
        if ($field === 'academic_status') {
            return ['draft' => 'Draf', 'submitted' => 'Diajukan ke jurnal', 'under_review' => 'Ditelaah jurnal', 'accepted' => 'Diterima jurnal', 'published' => 'Terbit di jurnal', 'rejected' => 'Ditolak jurnal'][$value] ?? 'Belum diisi';
        }
        if ($field === 'publication_type') {
            return JournalMetadata::TYPES[$value] ?? 'Belum diisi';
        }
        if ($field === 'indexing_mode') {
            return $value === 'manual' ? 'Input manual' : 'Data referensi';
        }
        if ($field === 'fakultas_id' && $value) {
            return Fakultas::find($value)?->nama_fakultas ?? 'Referensi fakultas sebelumnya';
        }
        if ($field === 'program_studi_id' && $value) {
            return ProgramStudi::find($value)?->nama ?? 'Referensi prodi sebelumnya';
        }
        if ($field === 'jurnal_id' && $value) {
            return Jurnal::find($value)?->nama_jurnal ?? 'Referensi jurnal sebelumnya';
        }
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        return Str::limit((string) $value, 160);
    }
}
