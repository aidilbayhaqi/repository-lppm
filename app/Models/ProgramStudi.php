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
 * @property string|null $deskripsi
 * @property int|null $fakultas_id
 * @property int $id
 * @property string|null $jenjang_pendidikan
 * @property string $kode
 * @property string $nama
 * @property Carbon $updated_at
 * @property-read Fakultas|null $fakultas
 * @property-read Collection<int, Dosen>|null $dosen
 */
class ProgramStudi extends Model
{
    use HasFactory;

    protected $table = 'program_studi';

    protected $fillable = [
        'fakultas_id',
        'kode',
        'nama',
        'jenjang_pendidikan',
        'deskripsi',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(
            Fakultas::class,
            'fakultas_id'
        );
    }

    public function dosen(): HasMany
    {
        return $this->hasMany(
            Dosen::class,
            'program_studi_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
