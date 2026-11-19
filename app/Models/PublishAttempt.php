<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
