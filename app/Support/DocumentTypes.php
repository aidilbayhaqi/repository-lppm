<?php

namespace App\Support;

final class DocumentTypes
{
    public const OPTIONS = [
        'dokumen_utama' => 'Dokumen utama', 'cover' => 'Cover', 'sertifikat' => 'Sertifikat',
        'lampiran' => 'Lampiran', 'pendukung' => 'Dokumen pendukung', 'lainnya' => 'Dokumen lainnya',
    ];

    public static function label(?string $type): string
    {
        return self::OPTIONS[$type] ?? 'Dokumen pendukung';
    }
}
