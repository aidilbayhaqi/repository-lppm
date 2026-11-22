<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Totp;
use App\Support\SessionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function challenge(Request $request, Totp $totp): View
    {
        $user = $request->user();
        if (! $user->mfa_secret && ! $request->session()->has('security.mfa_setup_secret')) {
            $request->session()->put('security.mfa_setup_secret', $totp->generateSecret());
        }
        $secret = $user->mfa_secret ? null : $request->session()->get('security.mfa_setup_secret');

        return view('auth.mfa', [
            'configured' => (bool) $user->mfa_secret,
            'secret' => $secret,
            'provisioningUri' => $secret ? $totp->provisioningUri($secret, $user->email) : null,
            'verified' => $request->session()->get('security.mfa_verified'),
        ]);
    }

    public function enroll(Request $request, Totp $totp): RedirectResponse
    {
        $input = $request->validate([
            'current_password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);
        if (! Hash::check($input['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini salah.']);
        }

        $secret = $request->session()->get('security.mfa_setup_secret');
        if (! is_string($secret) || $totp->verifyStep($secret, $input['code']) === null) {
            throw ValidationException::withMessages(['code' => 'Kode authenticator tidak valid.']);
        }

        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(8)));
        }
        $user = DB::transaction(function () use ($request, $secret, $codes) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($user->mfa_secret) {
                throw ValidationException::withMessages(['code' => 'MFA sudah aktif.']);
            }
            $user->forceFill([
                'mfa_secret' => $secret,
                'mfa_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
                'mfa_last_step' => null,
            ])->save();
            AuditLog::record($user, $user, 'mfa_enabled');
            return $user;
        });

        Auth::setUser($user);
        SessionState::establish($request, $user);
        $request->session()->forget(['security.mfa_setup_secret', 'auth.password_confirmed_at']);
        $request->session()->put('security.mfa_verified', true);
        $request->session()->flash('mfa_recovery_display', $codes);

        return redirect()->route('mfa.recovery');
    }

    public function verify(Request $request, Totp $totp): RedirectResponse
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);
        $code = strtoupper(trim($input['code']));

        $user = DB::transaction(function () use ($request, $code, $totp) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! $user->mfa_secret) {
                throw ValidationException::withMessages(['code' => 'Aktifkan autentikasi dua faktor dahulu.']);
            }
            if (preg_match('/^\\d{6}$/D', $code)) {
                $step = $totp->verifyStep($user->mfa_secret, $code);
                if ($step === null || ($user->mfa_last_step !== null && $step <= $user->mfa_last_step)) {
                    throw ValidationException::withMessages(['code' => 'Kode tidak valid atau sudah digunakan.']);
                }
                $user->forceFill(['mfa_last_step' => $step])->save();
            } else {
                $hashes = $user->mfa_recovery_codes ?? [];
                $match = null;
                foreach ($hashes as $index => $hash) {
                    if (Hash::check($code, $hash)) {
                        $match = $index;
                        break;
                    }
                }
                if ($match === null) {
                    throw ValidationException::withMessages(['code' => 'Kode tidak valid atau sudah digunakan.']);
                }
                unset($hashes[$match]);
                $user->forceFill(['mfa_recovery_codes' => array_values($hashes)])->save();
                AuditLog::record($user, $user, 'mfa_recovery_used');
            }
            return $user;
        }, 3);

        Auth::setUser($user);
        SessionState::establish($request, $user);
        $request->session()->forget('auth.password_confirmed_at');
        $request->session()->put('security.mfa_verified', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function recovery(Request $request): View
    {
        abort_unless($request->session()->get('security.mfa_verified'), 403);
        $codes = $request->session()->get('mfa_recovery_display');
        abort_unless(is_array($codes), 404);
        $request->session()->forget('mfa_recovery_display');

        return view('auth.mfa-recovery', ['codes' => $codes]);
    }

    public function disable(Request $request, Totp $totp): RedirectResponse
    {
        // Privileged accounts cannot bypass a mandatory second factor by disabling it.
        abort_if($request->user()->requiresMfa(), 403);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:64'],
        ]);
        if (! $request->session()->get('security.mfa_verified')
            || ! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Konfirmasi ulang password dan MFA diperlukan.']);
        }
        $user = DB::transaction(function () use ($request, $data, $totp) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $step = $user->mfa_secret ? $totp->verifyStep($user->mfa_secret, trim($data['code'])) : null;
            if ($step === null || ($user->mfa_last_step !== null && $step <= $user->mfa_last_step)) {
                throw ValidationException::withMessages(['code' => 'Kode tidak valid atau sudah digunakan.']);
            }
            $user->forceFill(['mfa_secret' => null, 'mfa_recovery_codes' => null, 'mfa_last_step' => null])->save();
            AuditLog::record($user, $user, 'mfa_disabled');
            return $user;
        }, 3);
        Auth::setUser($user);
        SessionState::establish($request, $user);
        $request->session()->forget(['auth.password_confirmed_at', 'security.mfa_verified']);

        return redirect()->route('profile.edit')->with('success', 'MFA dinonaktifkan.');
    }
}
