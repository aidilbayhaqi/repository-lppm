<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $guarded = ['id'];

    public $timestamps = true;

    protected function casts(): array
    {
        return ['rows' => 'array', 'errors' => 'array', 'committed_at' => 'datetime'];
    }
}
