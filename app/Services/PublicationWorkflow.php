<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Publikasi;
use App\Models\PublishAttempt;
use App\Models\User;
use App\Models\VerificationHistory;
use App\Policies\WorkPolicy;
use App\Support\WorkType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PublicationWorkflow
{
    public function __construct(
        private WorkService $works,
        private PublishingService $publishing,
    ) {}

    public function transition(
        Model $subject,
        User $actor,
        array $input
    ): Model {
        // Check private PDF bytes before locking the publication row.
        // We still authorize again and compare document metadata under the final lock.
        $verifiedDocuments = null;
        $preflightFailure = null;
        if (($input['action'] ?? null) === 'publish' && $subject instanceof Publikasi) {
            $candidate = $subject->fresh();
            // Keep optimistic-lock error semantics on duplicate requests.
            // Both version and permission are checked AGAIN under the row lock.
            $this->works->checkVersion($candidate, (int) $input['lock_version']);
            Gate::forUser($actor)->authorize('publish', $candidate);
            try {
                $verifiedDocuments = $this->publishing->inspectStorage($candidate);
            } catch (\Throwable $exception) {
                $preflightFailure = $exception;
                if (! $exception instanceof \RuntimeException) {
                    report($exception);
                }
            }
        }

        // State/attempt/final outcome are committed atomically. An interrupted
        // request rolls back rather than leaving a permanent 'publishing' state.
        return DB::transaction(function () use ($subject, $actor, $input, $verifiedDocuments, $preflightFailure) {
            $attempt = null;
            $work = $subject->newQuery()
                ->lockForUpdate()
                ->findOrFail($subject->id);

            $this->works->checkVersion(
                $work,
                (int) $input['lock_version']
            );

            $action = $input['action'];
            $notes = $input['notes'] ?? null;

            $ability = match ($action) {
                'review', 'approve', 'revision', 'reject' => 'verify',
                'archive' => 'delete',
                default => $action,
            };

            Gate::forUser($actor)->authorize($ability, $work);
            $adminSelfReview = $ability === 'verify' && $actor->isAdmin() && app(WorkPolicy::class)->verificationConflict($actor, $work);
            if ($adminSelfReview && ! filled($notes)) {
                throw ValidationException::withMessages(['notes' => 'Admin perlu mencatat alasan pemeriksaan untuk karya yang dibuat atau ditulis sendiri.']);
            }
            if ($adminSelfReview) {
                AuditLog::record($work, $actor, 'admin_self_review', [], [], $notes);
            }

            $old = [
                'status' => WorkType::status($work),
            ];

            if (in_array(
                $action,
                ['submit', 'publish', 'approve'],
                true
            )) {
                $this->works->assertReady($work);
            }

            if ($action === 'submit') {
                $work->repository_status = 'submitted';

            } elseif ($action === 'review') {
                if ($work->repository_status !== 'submitted') {
                    throw ValidationException::withMessages([
                        'workflow' => 'Karya harus diajukan terlebih dahulu.',
                    ]);
                }

                $work->repository_status = 'pending_verification';

            } elseif (in_array(
                $action,
                ['approve', 'revision', 'reject'],
                true
            )) {
                if ($work->repository_status !== 'pending_verification') {
                    throw ValidationException::withMessages([
                        'workflow' => 'Mulai pemeriksaan terlebih dahulu.',
                    ]);
                }

                $work->repository_status = $action === 'approve'
                    ? 'verified'
                    : 'revision_required';

                VerificationHistory::create([
                    'publikasi_id' => $work->id,
                    'verifier_id' => $actor->id,
                    'status' => match ($action) {
                        'approve' => 'approved',
                        'reject' => 'rejected',
                        default => 'revision_required',
                    },
                    'notes' => $notes,
                    'created_at' => now(),
                ]);

            } elseif ($action === 'reopen') {
                $work->repository_status = 'revision_required';

            } elseif ($action === 'publish') {
                if ($work instanceof Publikasi) {
                    // Status sementara internal.
                    // Akan diselesaikan dalam request yang sama.
                    $work->repository_status = 'publishing';

                    $attemptNumber = 1 + (int) PublishAttempt::query()
                        ->where('publikasi_id', $work->id)
                        ->max('attempt_number');

                    $attempt = PublishAttempt::create([
                        'publikasi_id' => $work->id,
                        'attempt_number' => $attemptNumber,
                        'triggered_by' => $actor->id,
                        'status' => 'queued',
                    ]);
                } else {
                    // Alur Buku dan HKI tidak berubah.
                    $work->status = 'dipublikasikan';
                }

            } elseif ($action === 'unpublish') {
                if ($work instanceof Publikasi) {
                    $work->repository_status = 'unpublished';
                    $work->repository_published_at = null;
                } else {
                    $work->status = 'draft';
                }

            } elseif ($action === 'archive') {
                $work->archived_at = now();

                if ($work instanceof Publikasi) {
                    $work->repository_status = 'archived';
                }

            } elseif ($action === 'restore') {
                $work->archived_at = null;

                if ($work instanceof Publikasi) {
                    $work->repository_status = 'draft';
                } else {
                    $work->status = 'draft';
                }
            }

            $work->lock_version++;
            $work->save();

            AuditLog::record(
                $work,
                $actor,
                $action,
                $old,
                ['status' => WorkType::status($work)],
                $notes
            );

            if ($attempt !== null) {
                if ($preflightFailure !== null) {
                    // An attempted publish must retain its start timestamp
                    // even when storage preflight fails before the row lock.
                    $attempt->update(['started_at' => now()]);
                    $this->publishing->fail(
                        $attempt->id,
                        'PUBLISH_INVALID_DOCUMENT',
                        'Penerbitan gagal. Periksa metadata, dokumen dan keamanan, lalu coba lagi.'
                    );
                } else {
                    try {
                        // The file bytes were verified outside the database lock.
                        // The document metadata is rechecked inside the transaction.
                        $this->publishing->process($attempt->id, $verifiedDocuments);
                    } catch (\Throwable $exception) {
                        report($exception);
                        $this->publishing->fail(
                            $attempt->id,
                            'PUBLISH_FAILED',
                            'Penerbitan gagal. Periksa metadata, dokumen dan keamanan, lalu coba lagi.'
                        );
                    }
                }
            }

            return $work->fresh();
        }, 1);
    }
}
