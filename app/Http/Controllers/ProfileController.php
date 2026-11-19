<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\User;
use App\Support\OptimisticLock;
use App\Support\SessionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $emailChanged = $request->input('email') !== $request->user()->email;
        DB::transaction(function () use ($request) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            OptimisticLock::check($user, (int) $request->validated('lock_version'));
            $old = $user->only(['nama', 'email', 'jabatan', 'bio']);
            $user->fill($request->safe()->only(['nama', 'email', 'jabatan', 'bio']));
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
            AuditLog::record($user, $user, 'profile_updated', $old, $user->only(['nama', 'email', 'jabatan', 'bio']));
            Auth::setUser($user);
        });

        if ($emailChanged) {
            SessionState::establish($request, $request->user());
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user) {
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            if ($user->isAdmin() && $admins->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['account' => 'Admin aktif terakhir tidak dapat menghapus akun. Tetapkan admin lain terlebih dahulu.']);
            }
            if (ImportBatch::where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['account' => 'Akun memiliki riwayat import yang harus dipertahankan. Minta admin menonaktifkan akun.']);
            }
            AuditLog::record($user, $user, 'account_deleted', $user->only(['nama', 'email', 'role']));
            Auth::logout();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
