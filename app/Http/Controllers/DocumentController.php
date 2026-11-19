<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\AuditLog;
use App\Models\DocumentVersion;
use App\Models\Dokumen;
use App\Services\DocumentService;
use App\Services\WorkService;
use App\Support\WorkType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(DocumentRequest $request, string $type, int $id, DocumentService $service): RedirectResponse
    {
        $service->store(WorkType::model($type)::findOrFail($id), $request->user(), $request->file('file'), $request->validated());

        return back()->with('success', 'PDF berhasil diunggah.');
    }

    public function replace(DocumentRequest $request, Dokumen $document, DocumentService $service): RedirectResponse
    {
        abort_unless($document->pemilikDokumen, 404);
        $service->store($document->pemilikDokumen, $request->user(), $request->file('file'), $request->validated(), $document->id);

        return back()->with('success', 'Versi dokumen baru disimpan.');
    }

    public function visibility(Request $request, Dokumen $document): RedirectResponse
    {
        $data = $request->validate(['publik' => 'required|boolean', 'lock_version' => 'required|integer|min:0']);
        DB::transaction(function () use ($request, $document, $data) {
            $subject = $document->pemilikDokumen;
            abort_unless($subject, 404);
            $work = $subject->newQuery()->lockForUpdate()->findOrFail($subject->id);
            Gate::authorize('update', $work);
            app(WorkService::class)->checkVersion($work, (int) $data['lock_version']);
            $document->refresh();
            $old = ['publik' => $document->publik];
            $document->update(['publik' => $data['publik']]);
            $work->increment('lock_version');
            AuditLog::record($work, $request->user(), 'document_visibility', $old, ['publik' => (bool) $data['publik'], 'document_id' => $document->id]);
        });

        return back()->with('success', 'Akses dokumen diperbarui.');
    }

    public function destroy(Request $request, Dokumen $document): RedirectResponse
    {
        $input = $request->validate(['lock_version' => 'required|integer|min:0']);
        DB::transaction(function () use ($request, $document, $input) {
            $subject = $document->pemilikDokumen;
            abort_unless($subject, 404);
            $work = $subject->newQuery()->lockForUpdate()->findOrFail($subject->id);
            Gate::authorize('update', $work);
            app(WorkService::class)->checkVersion($work, (int) $input['lock_version']);
            AuditLog::record($work, $request->user(), 'document_removed', ['document_id' => $document->id, 'checksum' => $document->checksum]);
            $document->delete();
            $work->increment('lock_version');
        });

        return back()->with('success', 'Dokumen dilepas dari karya. Versi lama dipertahankan untuk retensi internal.');
    }

    public function download(Request $request, Dokumen $document, DocumentService $service): StreamedResponse
    {
        abort_unless($service->canDownload($document, $request->user()), 404);
        $disk = Storage::disk($document->storage_disk);
        abort_unless($disk->exists($document->path_file), 404);

        return $disk->download($document->path_file, $document->nama_file_asli ?? $document->nama_file, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }

    public function version(Request $request, DocumentVersion $version): StreamedResponse
    {
        $document = Dokumen::findOrFail($version->dokumen_id);
        abort_if(config('repository.scan_required') && $version->scan_status !== 'clean', 404);
        abort_unless($document->pemilikDokumen, 404);
        Gate::authorize('view', $document->pemilikDokumen);
        $disk = Storage::disk($version->storage_disk);
        abort_unless($disk->exists($version->path_file), 404);

        return $disk->download($version->path_file, $version->nama_file_asli, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }
}
