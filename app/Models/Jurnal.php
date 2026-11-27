<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property bool $aktif
 * @property Carbon $created_at
 * @property int $id
 * @property string|null $issn_cetak
 * @property string|null $issn_elektronik
 * @property string|null $nama_jurnal
 * @property string|null $penerbit_id
 * @property Carbon $updated_at
 * @property string|null $website
 * @property-read Penerbit|null $penerbit
 * @property-read Collection<int, Publikasi>|null $publikasi
 */
class Jurnal extends Model
{
    use HasFactory;

    protected $table = 'jurnal';

    protected $fillable = [
        'entry_key',
        'penerbit_id',
        'nama_jurnal',
        'issn_cetak',
        'issn_elektronik',
        'website',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function penerbit(): BelongsTo
    {
        return $this->belongsTo(
            Penerbit::class,
            'penerbit_id'
        );
    }

    public function publikasi(): HasMany
    {
        return $this->hasMany(
            Publikasi::class,
            'jurnal_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
