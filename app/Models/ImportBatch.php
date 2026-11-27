<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $committed_at
 * @property Carbon $created_at
 * @property array<int, mixed> $errors
 * @property string $filename
 * @property int $id
 * @property array<int, mixed> $rows
 * @property string $status
 * @property string $token
 * @property string $type
 * @property Carbon $updated_at
 * @property int|null $user_id
 */
class ImportBatch extends Model
{
    protected $guarded = ['id'];

    public $timestamps = true;

    protected function casts(): array
    {
        return ['rows' => 'array', 'errors' => 'array', 'committed_at' => 'datetime'];
    }
}
