<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string|null $cakupan
 * @property Carbon $created_at
 * @property int $id
 * @property int $jumlah_sitasi
 * @property int|null $publikasi_id
 * @property string|null $sumber
 * @property int $tahun
 * @property Carbon $updated_at
 * @property-read Publikasi|null $publikasi
 */
class Sitasi extends Model
{
    use HasFactory;

    protected $table = 'sitasi';

    protected $fillable = [
        'publikasi_id',
        'tahun',
        'sumber',
        'cakupan',
        'jumlah_sitasi',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'jumlah_sitasi' => 'integer',
        ];
    }

    public function publikasi(): BelongsTo
    {
        return $this->belongsTo(
            Publikasi::class,
            'publikasi_id'
        );
    }

    public function scopeForYear(
        Builder $query,
        int $tahun
    ): Builder {
        return $query->where(
            'tahun',
            $tahun
        );
    }
}
