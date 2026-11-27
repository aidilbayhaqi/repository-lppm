<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $action
 * @property Carbon $created_at
 * @property string|null $description
 * @property int $id
 * @property array<int, mixed> $new_values
 * @property array<int, mixed> $old_values
 * @property int $subject_id
 * @property string $subject_type
 * @property int|null $user_id
 * @property-read User|null $user
 */
class AuditLog extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit bersifat append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit tidak dapat dihapus melalui aplikasi.'));
    }

    public static function record(Model $subject, ?User $actor, string $action, array $old = [], array $new = [], ?string $description = null): self
    {
        return self::create(['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'user_id' => $actor?->id, 'action' => $action, 'old_values' => $old, 'new_values' => $new, 'description' => $description, 'created_at' => now()]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
