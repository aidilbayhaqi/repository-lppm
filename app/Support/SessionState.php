<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class SessionState
{
    public static function establish(Request $request, User $user): void
    {
        $request->session()->regenerate(true);
        $request->session()->put(self::attributes($user));
    }

    /** Used by login and test fixtures that represent an authenticated browser. */
    public static function attributes(User $user): array
    {
        return [
            'security.user_id' => $user->id,
            'security.version' => (int) $user->auth_version,
            'security.started_at' => now()->timestamp,
            'security.last_activity' => now()->timestamp,
            'auth.password_confirmed_at' => now()->timestamp,
        ];
    }
}
