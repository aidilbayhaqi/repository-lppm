<?php

namespace App\Support;

use Closure;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public static function rules(): array
    {
        // Bcrypt accepts at most 72 bytes. Explicitly reject longer input instead of truncating it.
        return ['string', Password::min(12), function (string $attribute, mixed $value, Closure $fail): void {
            if (strlen((string) $value) > 72) {
                $fail('Kata sandi maksimal 72 byte. Gunakan frasa sepanjang sedikitnya 12 karakter.');
            }
        }];
    }
}
