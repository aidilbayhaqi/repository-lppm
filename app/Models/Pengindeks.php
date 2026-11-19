<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pengindeks extends Model
{
    use HasFactory;

    protected $table = 'pengindeks';

    protected $fillable = [
        'nama',
        'kategori',
        'website',
        'deskripsi',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function publikasi(): BelongsToMany
    {
        return $this->belongsToMany(
            Publikasi::class,
            'pengindeksan_publikasi',
            'pengindeks_id',
            'publikasi_id'
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

    public function tingkatPengindeksan(): BelongsToMany
    {
        return $this->belongsToMany(
            TingkatPengindeksan::class,
            'pengindeksan_publikasi',
            'pengindeks_id',
            'tingkat_pengindeksan_id'
        )
            ->withPivot('publikasi_id')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}