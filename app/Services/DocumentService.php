<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DocumentVersion;
use App\Models\Dokumen;
use App\Models\User;
use App\Support\WorkType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public function store(Model $subject, User $actor, UploadedFile $file, array $input, ?int $replaceId = null): Dokumen
    {
        Gate::forUser($actor)->authorize('update', $subject);
        $head = file_get_contents($file->getRealPath(), false, null, 0, 5);
        if ($head !== '%PDF-') {
            throw ValidationException::withMessages(['file' => 'Signature file bukan PDF.']);
        }
        $scanStatus = 'unscanned';
        if (config('repository.scan_required')) {
            $stream = fopen($file->getRealPath(), 'rb');
            try {
                app(MalwareScanner::class)->scan($stream);
                $scanStatus = 'clean';
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
        $disk = config('repository.documents_disk');
        $path = $file->store('documents/'.date('Y/m'), $disk);
        if (! $path) {
            throw ValidationException::withMessages(['file' => 'Penyimpanan tidak tersedia.']);
        }
        try {
            return DB::transaction(function () use ($subject, $actor, $file, $input, $replaceId, $path, $disk, $scanStatus) {
                $work = $subject->newQuery()->lockForUpdate()->findOrFail($subject->id);
                Gate::forUser($actor)->authorize('update', $work);
                app(WorkService::class)->checkVersion($work, (int) $input['lock_version']);
                $document = $replaceId ? $work->dokumen()->lockForUpdate()->findOrFail($replaceId) : new Dokumen;
                $old = $document->exists ? $document->only(['id', 'current_version', 'checksum', 'publik', 'jenis_dokumen', 'nama_file_asli']) : [];
                if ($replaceId && ! DocumentVersion::where('dokumen_id', $document->id)->exists()) {
                    $this->version($document, $actor);
                }
                $document->fill([
                    'nama_file' => basename($path), 'nama_file_asli' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 240),
                    'path_file' => $path, 'storage_disk' => $disk, 'mime_type' => 'application/pdf', 'ukuran_file' => $file->getSize(),
                    'checksum' => hash_file('sha256', $file->getRealPath()), 'publik' => (bool) $input['publik'],
                    'scan_status' => $scanStatus, 'scanned_at' => $scanStatus === 'clean' ? now() : null,
                    'jenis_dokumen' => $input['jenis_dokumen'], 'current_version' => $replaceId ? $document->current_version + 1 : 1,
                ]);
                $work->dokumen()->save($document);
                $this->version($document, $actor);
                $work->increment('lock_version');
                AuditLog::record($work, $actor, $replaceId ? 'document_replaced' : 'document_uploaded', $old, $document->only(['id', 'current_version', 'checksum', 'publik', 'jenis_dokumen', 'nama_file_asli']));

                return $document;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }

    private function version(Dokumen $document, User $actor): void
    {
        DocumentVersion::create([
            'dokumen_id' => $document->id, 'version_number' => $document->current_version, 'storage_disk' => $document->storage_disk,
            'path_file' => $document->path_file, 'nama_file_asli' => $document->nama_file_asli ?? $document->nama_file,
            'ukuran_file' => $document->ukuran_file ?? 0, 'mime_type' => $document->mime_type ?? 'application/pdf',
            'scan_status' => $document->scan_status, 'scanned_at' => $document->scanned_at,
            'checksum' => $document->checksum, 'uploaded_by' => $actor->id, 'created_at' => now(),
        ]);
    }

    public function canDownload(Dokumen $document, ?User $user): bool
    {
        if (config('repository.scan_required') && $document->scan_status !== 'clean') {
            return false;
        }
        $work = $document->pemilikDokumen;
        if (! $work || ! in_array($work::class, array_map(fn ($t) => WorkType::model($t), array_keys(WorkType::TYPES)))) {
            return false;
        }
        if (WorkType::public($work) && $document->publik) {
            return true;
        }

        return $user && $user->isActive() && Gate::forUser($user)->allows('view', $work);
    }
}
