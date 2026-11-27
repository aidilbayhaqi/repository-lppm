<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property bool $aktif
 * @property Carbon $created_at
 * @property string|null $deskripsi
 * @property int $id
 * @property string|null $kategori
 * @property string $kode
 * @property string $nama
 * @property Carbon $updated_at
 * @property int $urutan
 * @property-read Collection<int, Publikasi>|null $publikasi
 * @property-read Collection<int, Pengindeks>|null $pengindeks
 */
class TingkatPengindeksan extends Model
{
    use HasFactory;

    protected $table = 'tingkat_pengindeksan';

    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'urutan',
        'deskripsi',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function publikasi(): BelongsToMany
    {
        return $this->belongsToMany(
            Publikasi::class,
            'pengindeksan_publikasi',
            'tingkat_pengindeksan_id',
            'publikasi_id'
        )
            ->withPivot([
                'pengindeks_id',
                'tautan_sumber',
                'tautan_sumber_internasional',
                'keterangan',
                'final',
                'tanggal_verifikasi',
            ])
            ->withTimestamps();
    }

    public function pengindeks(): BelongsToMany
    {
        return $this->belongsToMany(
            Pengindeks::class,
            'pengindeksan_publikasi',
            'tingkat_pengindeksan_id',
            'pengindeks_id'
        )
            ->withPivot('publikasi_id')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
