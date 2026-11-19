<?php

namespace App\Support;

use App\Models\Buku;
use App\Models\KekayaanIntelektual;
use App\Models\Publikasi;
use Illuminate\Database\Eloquent\Model;

class WorkType
{
    public const TYPES = ['publikasi' => 'Jurnal', 'buku' => 'Buku', 'hki' => 'Hak Kekayaan Intelektual'];

    public static function model(string $type): string
    {
        return match ($type) {
            'publikasi' => Publikasi::class, 'buku' => Buku::class, 'hki' => KekayaanIntelektual::class, default => abort(404)
        };
    }

    public static function key(Model $work): string
    {
        return match (true) {
            $work instanceof Publikasi => 'publikasi', $work instanceof Buku => 'buku', $work instanceof KekayaanIntelektual => 'hki', default => abort(404)
        };
    }

    public static function authors(Model $work): string
    {
        return $work instanceof KekayaanIntelektual ? 'pencipta' : 'penulis';
    }

    public static function year(string $type): string
    {
        return $type === 'hki' ? 'tahun' : 'tahun_publikasi';
    }

    public static function published(Model $work): bool
    {
        return ! $work->archived_at && ($work instanceof Publikasi ? $work->repository_status === 'published' : $work->status === 'dipublikasikan');
    }

    public static function public(Model $work): bool
    {
        return self::published($work) && $work->public_visibility === 'public';
    }

    public static function editable(Model $work): bool
    {
        return ! $work->archived_at && ($work instanceof Publikasi ? in_array($work->repository_status, ['draft', 'revision_required', 'unpublished']) : $work->status === 'draft');
    }

    public static function status(Model $work): string
    {
        if ($work->archived_at) {
            return 'archived';
        }

        return $work instanceof Publikasi ? $work->repository_status : ($work->status === 'dipublikasikan' ? 'published' : 'draft');
    }
}
