<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
