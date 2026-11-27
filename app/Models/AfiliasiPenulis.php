<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool $afiliasi_utama
 * @property Carbon $created_at
 * @property string|null $departemen
 * @property int $id
 * @property string|null $institusi
 * @property string|null $penulis_id
 * @property Carbon $updated_at
 * @property-read Penulis|null $penulis
 */
class AfiliasiPenulis extends Model
{
    use HasFactory;

    protected $table = 'afiliasi_penulis';

    protected $fillable = [
        'penulis_id',
        'institusi',
        'departemen',
        'afiliasi_utama',
    ];

    protected function casts(): array
    {
        return [
            'afiliasi_utama' => 'boolean',
        ];
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(
            Penulis::class,
            'penulis_id'
        );
    }
}
