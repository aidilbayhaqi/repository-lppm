<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Dokumen extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'dokumen';

    protected $fillable = [
        'pemilik_dokumen_type',
        'pemilik_dokumen_id',
        'nama_file',
        'nama_file_asli',
        'path_file',
        'ukuran_file',
        'mime_type',
        'jenis_dokumen',
        'publik',
        'storage_disk', 'checksum', 'current_version', 'scan_status', 'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'ukuran_file' => 'integer',
            'current_version' => 'integer',
            'publik' => 'boolean',
            'scanned_at' => 'datetime',
        ];
    }

    public function pemilikDokumen(): MorphTo
    {
        return $this->morphTo('pemilik_dokumen');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('publik', true);
    }
}
