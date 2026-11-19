<?php

namespace App\Enums;

enum RepositoryStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingVerification = 'pending_verification';
    case RevisionRequired = 'revision_required';
    case Verified = 'verified';
    case Publishing = 'publishing';
    case Published = 'published';
    case PublishFailed = 'publish_failed';
    case Unpublished = 'unpublished';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf', self::Submitted => 'Diajukan', self::PendingVerification => 'Menunggu verifikasi',
            self::RevisionRequired => 'Perlu revisi', self::Verified => 'Terverifikasi', self::Publishing => 'Sedang diterbitkan',
            self::Published => 'Terbit', self::PublishFailed => 'Gagal terbit', self::Unpublished => 'Ditarik', self::Archived => 'Diarsipkan',
        };
    }
}
