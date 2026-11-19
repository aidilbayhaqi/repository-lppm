<?php

namespace App\Support;

use App\Models\Publikasi;

final class PublicationReadiness
{
    public static function evaluate(Publikasi $work): array
    {
        $work->loadMissing(['penulis', 'dokumen']);

        $hasAuthors = $work->penulis->isNotEmpty();
        $scanRequired = (bool) config('repository.scan_required');

        $checks = [
            [
                'label' => 'Tahun publikasi',
                'passed' => (bool) $work->tahun_publikasi,
                'detail' => 'Isi tahun publikasi pada metadata karya.',
            ],
            [
                'label' => 'Penulis jurnal',
                'passed' => $hasAuthors,
                'detail' => 'Tambahkan minimal satu penulis.',
            ],
            [
                'label' => 'Status penulis',
                'passed' => $hasAuthors &&
                    $work->penulis->every(
                        fn ($author) => (bool) $author->aktif
                    ),
                'detail' => 'Seluruh penulis harus aktif.',
            ],
            [
                'label' => 'Verifikasi repository',
                'passed' => in_array(
                    $work->repository_status,
                    ['verified', 'publish_failed'],
                    true
                ),
                'detail' => 'Status harus Terverifikasi atau Gagal terbit untuk mencoba ulang.',
            ],
            [
                'label' => 'Status arsip',
                'passed' => $work->archived_at === null,
                'detail' => 'Karya tidak boleh diarsipkan.',
            ],
            [
                'label' => 'Pemeriksaan keamanan dokumen',
                'passed' => ! $scanRequired ||
                    $work->dokumen->every(
                        fn ($document) => $document->scan_status === 'clean'
                    ),
                'detail' => $scanRequired
                    ? 'Semua dokumen harus lolos pemeriksaan keamanan. Hubungi pengelola jika pemeriksaan tertunda.'
                    : 'Pemeriksaan keamanan dokumen tidak diwajibkan pada instalasi ini.',
            ],
        ];

        $passed = count(array_filter(
            $checks,
            fn ($check) => $check['passed']
        ));

        return [
            'checks' => $checks,
            'passed' => $passed,
            'total' => count($checks),
            'ready' => $passed === count($checks),
        ];
    }
}
