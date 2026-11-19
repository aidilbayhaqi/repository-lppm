<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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