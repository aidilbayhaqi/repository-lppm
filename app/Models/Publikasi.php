<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Publikasi extends Model
{
    use HasFactory;

    protected $table = 'publikasi';

    protected $fillable = [
        'isbn', 'issn_cetak', 'issn_elektronik',
        'jurnal_id', 'fakultas_id', 'program_studi_id',
        'publication_type', 'journal_name_manual', 'indexing_mode', 'sinta_rank', 'scopus_indexed', 'scopus_quartile', 'indexing_year', 'indexing_source',
        'judul',
        'abstrak',
        'doi',
        'tahun_publikasi',
        'tanggal_publikasi',
        'volume',
        'nomor',
        'halaman',
        'tautan_eksternal',
        'tautan_google_scholar',
        'spmi',
        'tahun_dasar',
        'klasifikasi',
        'biaya',
        'keterangan',
        'academic_status',
        'public_visibility',
    ];

    protected function casts(): array
    {
        return [
            'sinta_rank' => 'integer', 'scopus_indexed' => 'boolean', 'indexing_year' => 'integer',
            'archived_at' => 'datetime',
            'repository_published_at' => 'datetime',
            'lock_version' => 'integer',
            'tanggal_publikasi' => 'date',
            'tahun_publikasi' => 'integer',
            'tahun_dasar' => 'integer',
            'biaya' => 'decimal:2',
        ];
    }

    public function getJournalNameAttribute(): ?string
    {
        return $this->jurnal?->nama_jurnal ?? $this->journal_name_manual;
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class);
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function jurnal(): BelongsTo
    {
        return $this->belongsTo(
            Jurnal::class,
            'jurnal_id'
        );
    }

    public function penulis(): BelongsToMany
    {
        return $this->belongsToMany(
            Penulis::class,
            'penulis_publikasi',
            'publikasi_id',
            'penulis_id'
        )
            ->withPivot([
                'urutan_penulis',
                'penulis_pertama',
                'penulis_korespondensi',
                'peneliti_utama',
                'jenis_afiliasi',
            ])
            ->withTimestamps()
            ->orderByPivot('urutan_penulis');
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

    public function sitasi(): HasMany
    {
        return $this->hasMany(
            Sitasi::class,
            'publikasi_id'
        );
    }

    public function pengindeks(): BelongsToMany
    {
        return $this->belongsToMany(
            Pengindeks::class,
            'pengindeksan_publikasi',
            'publikasi_id',
            'pengindeks_id'
        )
            ->withPivot([
                'tingkat_pengindeksan_id',
                'tautan_sumber',
                'tautan_sumber_internasional',
                'keterangan',
                'final',
                'tanggal_verifikasi',
            ])
            ->withTimestamps();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('repository_status', 'published')->where('public_visibility', 'public')->whereNull('archived_at');
    }

    public function scopeTahun(
        Builder $query,
        int $tahun
    ): Builder {
        return $query->where(
            'tahun_publikasi',
            $tahun
        );
    }
}
