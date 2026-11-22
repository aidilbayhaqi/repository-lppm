<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * A valid, time-limited signed link proves control of the destination inbox.
     * Verification does not establish an authenticated session.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::query()->findOrFail($request->route('id'));

        // Unlike EmailVerificationRequest, this flow must work for guests.
        // Never trust the user id/hash without both the route's signed middleware
        // and this constant-time comparison against the actual account email.
        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash')),
            403
        );

        // Registration requests must still be approved by Superadmin first.
        abort_unless($user->isActive(), 403);
        abort_if(
            $user->registrationRequest && $user->registrationRequest->status !== 'approved',
            403
        );

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $request->session()->flash('verification_success', true);

        return redirect()->route('verification.success');
    }
}
