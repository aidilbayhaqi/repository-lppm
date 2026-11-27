<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $created_at
 * @property int $id
 * @property string|null $identifier
 * @property int|null $program_studi_id
 * @property string $requested_role
 * @property string|null $review_note
 * @property Carbon $reviewed_at
 * @property int|null $reviewed_by
 * @property string $status
 * @property Carbon $updated_at
 * @property int|null $user_id
 * @property-read User|null $user
 * @property-read ProgramStudi|null $programStudi
 * @property-read User|null $reviewer
 */
class RegistrationRequest extends Model
{
    protected $fillable = [
        'user_id', 'requested_role', 'identifier', 'program_studi_id',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
