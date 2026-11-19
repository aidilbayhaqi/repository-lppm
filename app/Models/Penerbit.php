<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
