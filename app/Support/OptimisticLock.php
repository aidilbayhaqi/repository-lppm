<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class OptimisticLock
{
    public static function check(Model $record, int $expected): void
    {
        if ((int) $record->lock_version !== $expected) {
            throw ValidationException::withMessages([
                'lock_version' => 'Data telah berubah sejak form dibuka. Muat ulang halaman, lalu periksa kembali perubahan Anda.',
            ]);
        }
    }
}
