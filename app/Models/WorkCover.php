<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read Model|null $subject
 */
class WorkCover extends Model
{
    protected $fillable = ['path', 'disk', 'uploaded_by'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
