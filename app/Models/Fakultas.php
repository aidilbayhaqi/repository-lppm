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
 * @property Carbon $created_at
 * @property string|null $deskripsi
 * @property int $id
 * @property string $kode
 * @property string $nama_fakultas
 * @property Carbon $updated_at
 * @property-read Collection<int, ProgramStudi>|null $programStudi
 */
class Fakultas extends Model
{
    use HasFactory;

    protected $table = 'fakultas';

    protected $fillable = [
        'kode',
        'nama_fakultas',
        'deskripsi',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function programStudi(): HasMany
    {
        return $this->hasMany(
            ProgramStudi::class,
            'fakultas_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
