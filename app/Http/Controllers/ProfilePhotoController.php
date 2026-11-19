<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'mimetypes:image/png,image/jpeg', 'extensions:png,jpg,jpeg', 'max:2048', 'dimensions:max_width=2400,max_height=2400'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);
        $disk = config('repository.documents_disk', 'local'); // Keep photos on the same private/shared storage as repository documents.
        $path = $request->file('photo')->store('profile-photos/'.$request->user()->id, $disk);
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['photo' => 'Foto gagal disimpan. Silakan coba lagi.']);
        }
        try {
            $old = DB::transaction(function () use ($request, $disk, $path) {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                abort_unless($user->isActive(), 403);
                if ($user->lock_version !== (int) $request->input('lock_version')) {
                    throw ValidationException::withMessages(['photo' => 'Profil berubah di tab lain. Muat ulang sebelum mengunggah foto.']);
                }
                $previous = [$user->profile_photo_disk, $user->profile_photo_path];
                $user->forceFill(['profile_photo_disk' => $disk, 'profile_photo_path' => $path])->save();
                AuditLog::record($user, $user, 'profile_photo_updated');
                return $previous;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
        if ($old[0] && $old[1] && $old[1] !== $path) {
            Storage::disk($old[0])->delete($old[1]);
        }
        return back()->with('success', 'Foto profil diperbarui.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $old = DB::transaction(function () use ($request) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($user->lock_version !== (int) $request->input('lock_version')) {
                throw ValidationException::withMessages(['photo' => 'Profil berubah. Muat ulang halaman.']);
            }
            $previous = [$user->profile_photo_disk, $user->profile_photo_path];
            $user->forceFill(['profile_photo_disk' => null, 'profile_photo_path' => null])->save();
            AuditLog::record($user, $user, 'profile_photo_removed');
            return $previous;
        });
        if ($old[0] && $old[1]) {
            Storage::disk($old[0])->delete($old[1]);
        }
        return back()->with('success', 'Foto profil dihapus.');
    }

    public function show(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user?->profile_photo_disk && $user->profile_photo_path, 404);
        $disk = Storage::disk($user->profile_photo_disk);
        abort_unless($disk->exists($user->profile_photo_path), 404);
        $mime = strtolower(pathinfo($user->profile_photo_path, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';
        return $disk->response($user->profile_photo_path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
