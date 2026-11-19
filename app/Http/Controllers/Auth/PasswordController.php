<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\SessionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', ...PasswordPolicy::rules()],
        ]);

        $user = DB::transaction(function () use ($request, $validated) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
            }
            $user->update(['password' => Hash::make($validated['password'])]);

            return $user;
        }, 3);
        Auth::setUser($user);
        SessionState::establish($request, $user);

        return back()->with('status', 'password-updated');
    }
}
