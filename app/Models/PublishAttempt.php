<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $attempt_number
 * @property Carbon $completed_at
 * @property Carbon $created_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $id
 * @property int|null $publikasi_id
 * @property Carbon $started_at
 * @property string $status
 * @property int|null $triggered_by
 * @property Carbon $updated_at
 * @property-read Publikasi|null $publikasi
 */
class PublishAttempt extends Model
{
    protected $guarded = ['id'];

    public $timestamps = true;

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function publikasi(): BelongsTo
    {
        return $this->belongsTo(Publikasi::class);
    }
}
