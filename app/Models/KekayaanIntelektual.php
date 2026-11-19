<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class KekayaanIntelektual extends Model
{
    use HasFactory;

    protected $table = 'kekayaan_intelektual';

    protected $fillable = [
        'judul',
        'jenis',
        'tahun',
        'tanggal_permohonan',
        'nomor_permohonan',
        'tanggal_pencatatan',
        'nomor_pencatatan',
        'periode',
        'tautan_eksternal',
        'keterangan',
        'public_visibility',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'repository_published_at' => 'datetime',
            'lock_version' => 'integer',
            'tahun' => 'integer',
            'tanggal_permohonan' => 'date',
            'tanggal_pencatatan' => 'date',
        ];
    }

    public function pencipta(): BelongsToMany
    {
        return $this->belongsToMany(
            Penulis::class,
            'pencipta_kekayaan_intelektual',
            'kekayaan_intelektual_id',
            'penulis_id'
        )
            ->withPivot([
                'urutan_pencipta',
            ])
            ->withTimestamps()
            ->orderByPivot('urutan_pencipta');
    }

    public function cover(): MorphOne
    {
        return $this->morphOne(WorkCover::class, 'subject');
    }

    public function dokumen(): MorphMany
    {
        return $this->morphMany(
            Dokumen::class,
            'pemilik_dokumen'
        );
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(
            'status',
            'dipublikasikan'
        )->where('public_visibility', 'public')->whereNull('archived_at');
    }
}
