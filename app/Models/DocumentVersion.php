<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string|null $checksum
 * @property Carbon $created_at
 * @property int|null $dokumen_id
 * @property int $id
 * @property string $mime_type
 * @property string $nama_file_asli
 * @property string $path_file
 * @property string $storage_disk
 * @property int $ukuran_file
 * @property int|null $uploaded_by
 * @property int $version_number
 */
class DocumentVersion extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
