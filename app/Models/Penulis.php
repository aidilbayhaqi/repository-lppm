<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property bool $aktif
 * @property Carbon $created_at
 * @property int|null $dosen_id
 * @property string|null $google_scholar_id
 * @property int $id
 * @property string|null $identitas
 * @property string|null $jenis_penulis
 * @property string $nama
 * @property string|null $nama_normalisasi
 * @property string|null $orcid_id
 * @property int|null $program_studi_id
 * @property string|null $scopus_id
 * @property Carbon $updated_at
 * @property int|null $user_id
 * @property-read Dosen|null $dosen
 * @property-read Collection<int, AfiliasiPenulis>|null $afiliasi
 * @property-read Collection<int, Publikasi>|null $publikasi
 * @property-read Collection<int, Buku>|null $buku
 * @property-read Collection<int, KekayaanIntelektual>|null $kekayaanIntelektual
 * @property-read User|null $user
 */
class Penulis extends Model
{
    use HasFactory;

    protected $table = 'penulis';

    protected $fillable = [
        'entry_key',
        'user_id',
        'dosen_id',
        'nama',
        'nama_normalisasi',
        'jenis_penulis',
        'identitas',
        'orcid_id',
        'scopus_id',
        'google_scholar_id',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(
            Dosen::class,
            'dosen_id'
        );
    }

    public function afiliasi(): HasMany
    {
        return $this->hasMany(
            AfiliasiPenulis::class,
            'penulis_id'
        );
    }

    public function publikasi(): BelongsToMany
    {
        return $this->belongsToMany(
            Publikasi::class,
            'penulis_publikasi',
            'penulis_id',
            'publikasi_id'
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

    public function buku(): BelongsToMany
    {
        return $this->belongsToMany(
            Buku::class,
            'penulis_buku',
            'penulis_id',
            'buku_id'
        )
            ->withPivot([
                'urutan_penulis',
                'penulis_korespondensi',
            ])
            ->withTimestamps()
            ->orderByPivot('urutan_penulis');
    }

    public function kekayaanIntelektual(): BelongsToMany
    {
        return $this->belongsToMany(
            KekayaanIntelektual::class,
            'pencipta_kekayaan_intelektual',
            'penulis_id',
            'kekayaan_intelektual_id'
        )
            ->withPivot([
                'urutan_pencipta',
            ])
            ->withTimestamps()
            ->orderByPivot('urutan_pencipta');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
