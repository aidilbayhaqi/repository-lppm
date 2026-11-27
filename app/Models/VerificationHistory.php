<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $created_at
 * @property int $id
 * @property string|null $notes
 * @property int|null $publikasi_id
 * @property string $status
 * @property int|null $verifier_id
 * @property-read User|null $verifier
 */
class VerificationHistory extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
