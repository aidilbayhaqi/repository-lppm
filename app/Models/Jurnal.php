<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
