<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $created_at
 * @property int $current_version
 * @property int $id
 * @property string|null $jenis_dokumen
 * @property string|null $mime_type
 * @property string $nama_file
 * @property string|null $nama_file_asli
 * @property string $path_file
 * @property int $pemilik_dokumen_id
 * @property string $pemilik_dokumen_type
 * @property bool $publik
 * @property Carbon $scanned_at
 * @property int $ukuran_file
 * @property Carbon $updated_at
 * @property-read Model|null $pemilikDokumen
 */
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
