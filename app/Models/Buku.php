<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $archived_at
 * @property Carbon $created_at
 * @property int $id
 * @property string|null $isbn
 * @property string $judul
 * @property int $jumlah_halaman
 * @property string|null $kategori
 * @property string|null $keterangan
 * @property int $lock_version
 * @property string|null $penerbit_id
 * @property Carbon $repository_published_at
 * @property string|null $status
 * @property int $tahun_publikasi
 * @property Carbon $tanggal_publikasi
 * @property string|null $tautan_eksternal
 * @property Carbon $updated_at
 * @property-read Penerbit|null $penerbit
 * @property-read Collection<int, Penulis>|null $penulis
 * @property-read WorkCover|null $cover
 * @property-read Collection<int, Dokumen>|null $dokumen
 */
class Buku extends Model
{
    use HasFactory;

    protected $table = 'buku';

    protected $fillable = [
        'penerbit_id',
        'judul',
        'isbn',
        'tahun_publikasi',
        'tanggal_publikasi',
        'jumlah_halaman',
        'kategori',
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
            'tahun_publikasi' => 'integer',
            'tanggal_publikasi' => 'date',
            'jumlah_halaman' => 'integer',
        ];
    }

    public function penerbit(): BelongsTo
    {
        return $this->belongsTo(
            Penerbit::class,
            'penerbit_id'
        );
    }

    public function penulis(): BelongsToMany
    {
        return $this->belongsToMany(
            Penulis::class,
            'penulis_buku',
            'buku_id',
            'penulis_id'
        )
            ->withPivot([
                'urutan_penulis',
                'penulis_korespondensi',
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(
            'status',
            'dipublikasikan'
        )->where('public_visibility', 'public')->whereNull('archived_at');
    }
}
