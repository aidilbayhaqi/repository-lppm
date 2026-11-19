<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WorkCover;
use App\Support\WorkType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkCoverController extends Controller
{
    private function authorizeManage(Request $request, Model $work): void
    {
        abort_if((bool) $work->archived_at, 403);
        if ($request->user()->isStaff()) {
            return; // Admin/Operator may update the presentation cover even after publication.
        }
        Gate::authorize('update', $work);
    }

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $request->validate([
            'cover' => ['required', 'file', 'mimes:png', 'mimetypes:image/png', 'extensions:png', 'max:3072', 'dimensions:max_width=2400,max_height=2400'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);
        $work = WorkType::model($type)::findOrFail($id);
        $this->authorizeManage($request, $work);
        $disk = config('repository.documents_disk', 'local');
        $path = $request->file('cover')->store('covers/'.$type.'/'.$id, $disk);
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['cover' => 'Sampul gagal diunggah. Coba lagi.']);
        }
        try {
            $previous = DB::transaction(function () use ($request, $type, $id, $path, $disk) {
                $work = WorkType::model($type)::query()->lockForUpdate()->findOrFail($id);
                $this->authorizeManage($request, $work);
                if ((int) $work->lock_version !== (int) $request->input('lock_version')) {
                    throw ValidationException::withMessages(['cover' => 'Data karya telah berubah. Muat ulang dan coba lagi.']);
                }
                $previous = $work->cover()->first();
                $oldFile = $previous ? [$previous->disk, $previous->path] : null;
                if ($previous) {
                    $previous->update(['disk' => $disk, 'path' => $path, 'uploaded_by' => $request->user()->id]);
                } else {
                    $work->cover()->create(['disk' => $disk, 'path' => $path, 'uploaded_by' => $request->user()->id]);
                }
                $work->increment('lock_version');
                AuditLog::record($work, $request->user(), 'work_cover_updated', [], ['cover_updated' => true]);
                return $oldFile;
            }, 3);
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
        if ($previous && ($previous[0] !== $disk || $previous[1] !== $path)) {
            Storage::disk($previous[0])->delete($previous[1]);
        }
        return back()->with('success', 'Sampul karya berhasil diperbarui.');
    }

    public function destroy(Request $request, string $type, int $id): RedirectResponse
    {
        $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $previous = DB::transaction(function () use ($request, $type, $id) {
            $work = WorkType::model($type)::query()->lockForUpdate()->findOrFail($id);
            $this->authorizeManage($request, $work);
            if ((int) $work->lock_version !== (int) $request->input('lock_version')) {
                throw ValidationException::withMessages(['cover' => 'Data karya telah berubah. Muat ulang.']);
            }
            $old = $work->cover()->first();
            if (! $old) {
                return null;
            }
            $file = [$old->disk, $old->path];
            $old->delete();
            $work->increment('lock_version');
            AuditLog::record($work, $request->user(), 'work_cover_removed');
            return $file;
        });
        if ($previous) {
            Storage::disk($previous[0])->delete($previous[1]);
        }
        return back()->with('success', 'Sampul karya dihapus.');
    }

    public function show(Request $request, string $type, int $id): StreamedResponse|RedirectResponse
    {
        $work = WorkType::model($type)::query()->with('cover')->findOrFail($id);
        $public = WorkType::public($work);
        if (! $public) {
            abort_unless($request->user() && Gate::forUser($request->user())->allows('view', $work), 404);
        }
        abort_unless($work->cover, 404);
        $disk = Storage::disk($work->cover->disk);
        if (! $disk->exists($work->cover->path)) {
            return redirect(asset('images/cover-placeholder.svg'))->header('Cache-Control', 'private, no-store');
        }
        return $disk->response($work->cover->path, null, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store', // Permission can change; never serve a stale public copy.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
