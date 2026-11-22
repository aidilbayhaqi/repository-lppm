<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\SessionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionSecurityController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string']]);
        $wasMfaVerified = $request->session()->get('security.mfa_verified');

        $user = DB::transaction(function () use ($request, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Password tidak sesuai.']);
            }

            // Force all other sessions to fail SecureSession on their next request.
            $user->forceFill([
                'auth_version' => $user->auth_version + 1,
                'remember_token' => Str::random(60),
                'remember_expires_at' => null,
            ])->save();
            AuditLog::record($user, $user, 'other_sessions_revoked');

            return $user;
        }, 3);

        Auth::setUser($user);
        SessionState::establish($request, $user);
        $request->session()->forget('auth.password_confirmed_at');
        if ($wasMfaVerified) {
            $request->session()->put('security.mfa_verified', true);
        }

        return redirect()->route('profile.edit')->with('success', 'Sesi perangkat lain sudah dicabut.');
    }
}
