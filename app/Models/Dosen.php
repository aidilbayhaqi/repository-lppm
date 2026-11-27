<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property bool $aktif
 * @property Carbon $created_at
 * @property string|null $email
 * @property string|null $gelar
 * @property string|null $google_scholar_id
 * @property int $id
 * @property string $nama
 * @property string|null $nidn
 * @property string|null $orcid_id
 * @property int|null $program_studi_id
 * @property string|null $scopus_id
 * @property Carbon $updated_at
 * @property-read ProgramStudi|null $programStudi
 * @property-read Penulis|null $penulis
 */
class Dosen extends Model
{
    use HasFactory;

    protected $table = 'dosen';

    protected $fillable = [
        'program_studi_id',
        'nidn',
        'nama',
        'gelar',
        'email',
        'scopus_id',
        'orcid_id',
        'google_scholar_id',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(
            ProgramStudi::class,
            'program_studi_id'
        );
    }

    public function penulis(): HasOne
    {
        return $this->hasOne(
            Penulis::class,
            'dosen_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
