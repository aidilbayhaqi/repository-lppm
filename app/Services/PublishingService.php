<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Publikasi;
use App\Models\PublishAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PublishingService
{
    /**
     * Read and verify private document bytes BEFORE obtaining the publication row lock.
     * Returned metadata is checked again under lock before commit. Do not expose this
     * method as a public endpoint: caller must authorize the publication first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inspectStorage(Publikasi $work): array
    {
        $documents = $work->dokumen()->orderBy('id')->get();
        foreach ($documents as $document) {
            if (config('repository.scan_required') && $document->scan_status !== 'clean') {
                throw new RuntimeException('Dokumen belum lolos pemeriksaan keamanan.');
            }
            $disk = Storage::disk($document->storage_disk);
            if (! $disk->exists($document->path_file)) {
                throw new RuntimeException('Dokumen tidak ditemukan di penyimpanan privat.');
            }
            if ($document->checksum) {
                $stream = $disk->readStream($document->path_file);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Dokumen tidak dapat dibaca dari penyimpanan privat.');
                }
                try {
                    $hash = hash_init('sha256');
                    hash_update_stream($hash, $stream);
                    if (! hash_equals($document->checksum, hash_final($hash))) {
                        throw new RuntimeException('Checksum dokumen tidak cocok. Unggah versi yang benar.');
                    }
                } finally {
                    fclose($stream);
                }
            }
        }

        return $this->snapshotRows($documents);
    }

    /** @return array<int, array<string, mixed>> */
    private function documentSnapshot(Publikasi $work): array
    {
        return $this->snapshotRows($work->dokumen()->orderBy('id')->get());
    }

    /** @return array<int, array<string, mixed>> */
    private function snapshotRows(Collection $documents): array
    {
        return $documents->map(static fn ($doc) => [
            'id' => (int) $doc->id,
            'version' => (int) $doc->current_version,
            'disk' => $doc->storage_disk,
            'path' => $doc->path_file,
            'checksum' => $doc->checksum,
            'scan_status' => $doc->scan_status,
            'updated_at' => $doc->updated_at?->format('Y-m-d H:i:s.u'),
        ])->all();
    }

    /**
     * Optional preverified snapshot avoids filesystem I/O while holding the row lock.
     * Legacy queued jobs may call without a snapshot (and will verify storage inline).
     *
     * @param  array<int, array<string, mixed>>|null  $verifiedDocuments
     */
    public function process(int $id, ?array $verifiedDocuments = null): void
    {
        $candidate = PublishAttempt::findOrFail($id);
        PublishAttempt::whereKey($id)->where('status', 'queued')->whereNull('started_at')->update(['started_at' => now()]);
        DB::transaction(function () use ($candidate, $id, $verifiedDocuments) {
            $work = Publikasi::lockForUpdate()->findOrFail($candidate->publikasi_id);
            $attempt = PublishAttempt::lockForUpdate()->findOrFail($id);
            if ($attempt->status !== 'queued') {
                return;
            }
            if ($work->repository_status !== 'publishing') {
                $attempt->update(['status' => 'cancelled', 'completed_at' => now()]);

                return;
            }
            $attempt->update(['status' => 'processing']);
            app(WorkService::class)->assertReady($work);
            if ($verifiedDocuments !== null) {
                // DocumentService changes lock_version and the work row. This second
                // check rejects changes between preflight and the final transaction.
                if ($verifiedDocuments !== $this->documentSnapshot($work)) {
                    throw new RuntimeException('Dokumen berubah saat pemeriksaan penerbitan. Coba kembali.');
                }
            } else {
                // Backward compatible only for legacy worker/queued attempts.
                $this->inspectStorage($work);
            }
            $work->forceFill(['repository_status' => 'published', 'repository_published_at' => now(), 'lock_version' => $work->lock_version + 1])->save();
            $attempt->update(['status' => 'success', 'completed_at' => now()]);
            AuditLog::record($work, User::find($attempt->triggered_by), 'published', ['status' => 'publishing'], ['status' => 'published', 'attempt' => $attempt->attempt_number]);
        });
    }

    public function fail(int $id, string $code, string $message): void
    {
        $candidate = PublishAttempt::find($id);
        if (! $candidate) {
            return;
        }
        DB::transaction(function () use ($candidate, $id, $code, $message) {
            $work = Publikasi::lockForUpdate()->find($candidate->publikasi_id);
            $attempt = PublishAttempt::lockForUpdate()->find($id);
            if (! $work || ! $attempt || in_array($attempt->status, ['success', 'failed', 'cancelled'])) {
                return;
            }
            $attempt->update(['status' => 'failed', 'error_code' => $code, 'error_message' => $message, 'completed_at' => now()]);
            if ($work->repository_status === 'publishing') {
                $work->forceFill(['repository_status' => 'publish_failed', 'lock_version' => $work->lock_version + 1])->save();
                AuditLog::record($work, User::find($attempt->triggered_by), 'publish_failed', [], ['attempt' => $attempt->attempt_number, 'code' => $code], $message);
            }
        });
    }
}
