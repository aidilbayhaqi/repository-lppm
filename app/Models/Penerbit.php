<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property bool $aktif
 * @property string|null $alamat
 * @property Carbon $created_at
 * @property string|null $email
 * @property int $id
 * @property string|null $kode
 * @property string|null $kota
 * @property string|null $nama
 * @property string $negara
 * @property string|null $telepon
 * @property Carbon $updated_at
 * @property string|null $website
 * @property-read Collection<int, Jurnal>|null $jurnal
 * @property-read Collection<int, Buku>|null $buku
 */
class Penerbit extends Model
{
    use HasFactory;

    protected $table = 'penerbit';

    protected $fillable = [
        'entry_key',
        'nama',
        'kode',
        'website',
        'email',
        'telepon',
        'alamat',
        'kota',
        'negara',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function jurnal(): HasMany
    {
        return $this->hasMany(
            Jurnal::class,
            'penerbit_id'
        );
    }

    public function buku(): HasMany
    {
        return $this->hasMany(
            Buku::class,
            'penerbit_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
