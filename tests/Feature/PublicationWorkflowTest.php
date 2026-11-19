<?php

namespace Tests\Feature;

use App\Jobs\PublishPublication;
use App\Models\Dokumen;
use App\Models\Penulis;
use App\Models\Publikasi;
use App\Models\PublishAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('repository.scan_required', false);
    }

    private function draft(User $owner): Publikasi
    {
        $work = Publikasi::factory()->draft()->create([
            'created_by' => $owner->id,
            'tahun_publikasi' => 2025,
            'public_visibility' => 'public',
        ]);

        $work->penulis()->attach(
            Penulis::factory()->create(),
            ['urutan_penulis' => 1]
        );

        return $work;
    }

    private function verifiedPublication(User $owner): Publikasi
    {
        $work = $this->draft($owner);

        $work->forceFill([
            'repository_status' => 'verified',
        ])->save();

        return $work;
    }

    private function step(
        User $actor,
        Publikasi $work,
        string $action
    ): TestResponse {
        return $this->actingAs($actor)->post(
            route('admin.works.workflow', [
                'publikasi',
                $work->id,
            ]),
            [
                'action' => $action,
                'lock_version' => $work->fresh()->lock_version,
                'notes' => 'Catatan pemeriksaan',
            ]
        );
    }

    private function createDocument(
        Publikasi $work,
        string $path,
        string $content
    ): Dokumen {
        return Dokumen::factory()->forOwner($work)->create([
            'nama_file' => basename($path),
            'nama_file_asli' => basename($path),
            'path_file' => $path,
            'storage_disk' => 'local',
            'mime_type' => 'application/pdf',
            'ukuran_file' => strlen($content),
            'jenis_dokumen' => 'dokumen_utama',
            'checksum' => hash('sha256', $content),
            'scan_status' => 'unscanned',
            'current_version' => 1,
            'publik' => true,
        ]);
    }

    public function test_full_submission_verification_immediate_publish_and_unpublish_lifecycle(): void
    {
        Queue::fake([PublishPublication::class]);

        $author = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $admin = User::factory()->admin()->create();

        $work = $this->draft($author);

        foreach ([
            'submit' => 'submitted',
            'review' => 'pending_verification',
            'approve' => 'verified',
        ] as $action => $expectedStatus) {
            $actor = $action === 'submit'
                ? $author
                : $admin;

            $this->step($actor, $work, $action)
                ->assertSessionHasNoErrors();

            $this->assertSame(
                $expectedStatus,
                $work->fresh()->repository_status
            );
        }

        // Penerbitan harus selesai dalam request ini.
        $this->step($admin, $work, 'publish')
            ->assertSessionHasNoErrors()
            ->assertSessionHas(
                'success',
                'Jurnal berhasil diterbitkan.'
            );

        $this->assertSame(
            'published',
            $work->fresh()->repository_status
        );

        $this->assertNotNull(
            $work->fresh()->repository_published_at
        );

        // Percobaan penerbitan tetap tercatat.
        $attempt = PublishAttempt::firstOrFail();

        $this->assertSame(
            'success',
            $attempt->status
        );

        $this->assertNotNull($attempt->started_at);
        $this->assertNotNull($attempt->completed_at);

        $this->assertDatabaseCount('publish_attempts', 1);

        // Tidak boleh mengirim job asynchronous.
        Queue::assertNotPushed(PublishPublication::class);

        // Publikasi sudah bisa diakses secara publik.
        $this->get(
            route('repository.show', [
                'publikasi',
                $work->id,
            ])
        )->assertOk();

        // Admin menarik publikasi.
        $this->step($admin, $work, 'unpublish')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'unpublished',
            $work->fresh()->repository_status
        );

        $this->get(
            route('repository.show', [
                'publikasi',
                $work->id,
            ])
        )->assertNotFound();

        $this->assertDatabaseHas(
            'verification_histories',
            [
                'publikasi_id' => $work->id,
                'status' => 'approved',
            ]
        );
    }

    public function test_revision_must_be_resubmitted_and_cannot_skip_review(): void
    {
        $author = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $admin = User::factory()->admin()->create();

        $work = $this->draft($author);

        $this->step($author, $work, 'submit')
            ->assertSessionHasNoErrors();

        // Tidak boleh approve sebelum review.
        $this->step($admin, $work, 'approve')
            ->assertSessionHasErrors('workflow');

        $this->step($admin, $work, 'review')
            ->assertSessionHasNoErrors();

        $this->step($admin, $work, 'revision')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'revision_required',
            $work->fresh()->repository_status
        );

        // Revisi tidak boleh langsung diterbitkan.
        $this->step($admin, $work, 'publish')
            ->assertForbidden();

        $this->step($author, $work, 'submit')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'submitted',
            $work->fresh()->repository_status
        );

        $this->assertDatabaseCount('publish_attempts', 0);
    }

    public function test_admin_can_review_own_submission_with_notes_but_cannot_publish_a_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $work = $this->draft($admin);

        // Draft tidak boleh diterbitkan langsung.
        $this->step($admin, $work, 'publish')
            ->assertForbidden();

        $this->step($admin, $work, 'submit')
            ->assertSessionHasNoErrors();

        // Admin dapat memeriksa karya sendiri dengan alasan yang tercatat.
        $this->step($admin, $work, 'review')
            ->assertSessionHasNoErrors();
        $this->assertSame('pending_verification', $work->fresh()->repository_status);

        $this->assertDatabaseCount('publish_attempts', 0);
    }

    public function test_missing_document_fails_immediately_and_retry_creates_another_attempt(): void
    {
        Storage::fake('local');
        Queue::fake([PublishPublication::class]);

        $owner = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $operator = User::factory()->create([
            'role' => 'operator',
        ]);

        $work = $this->verifiedPublication($owner);

        $pdf = '%PDF-1.4 repaired';
        $path = 'documents/lost.pdf';

        $this->createDocument($work, $path, $pdf);

        // File tercatat di database, tetapi tidak ada
        // dalam private storage.
        $this->step($operator, $work, 'publish')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');

        $first = PublishAttempt::firstOrFail();

        $this->assertSame(
            'publish_failed',
            $work->fresh()->repository_status
        );

        $this->assertSame(
            'failed',
            $first->fresh()->status
        );

        $this->assertNotNull(
            $first->fresh()->started_at
        );

        $this->assertNotNull(
            $first->fresh()->completed_at
        );

        $this->assertNotEmpty(
            $first->fresh()->error_message
        );

        Queue::assertNotPushed(PublishPublication::class);

        // Simulasikan perbaikan file yang hilang.
        Storage::disk('local')->put($path, $pdf);

        // Admin/Operator mencoba menerbitkan ulang.
        $this->step($operator, $work, 'publish')
            ->assertSessionHasNoErrors()
            ->assertSessionHas(
                'success',
                'Jurnal berhasil diterbitkan.'
            );

        $second = PublishAttempt::latest('id')
            ->firstOrFail();

        $this->assertSame(
            2,
            $second->attempt_number
        );

        $this->assertSame(
            'success',
            $second->status
        );

        $this->assertSame(
            'published',
            $work->fresh()->repository_status
        );

        $this->assertDatabaseCount('publish_attempts', 2);

        // Riwayat kegagalan pertama tidak dihapus.
        $this->assertDatabaseHas(
            'publish_attempts',
            [
                'id' => $first->id,
                'status' => 'failed',
            ]
        );

        Queue::assertNotPushed(PublishPublication::class);
    }

    public function test_checksum_mismatch_fails_without_publishing(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $admin = User::factory()->admin()->create();

        $work = $this->verifiedPublication($owner);

        $path = 'documents/checksum.pdf';

        $this->createDocument(
            $work,
            $path,
            '%PDF-1.4 original'
        );

        // File aktual berbeda dengan checksum di DB.
        Storage::disk('local')->put(
            $path,
            '%PDF-1.4 tampered'
        );

        $this->step($admin, $work, 'publish')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');

        $this->assertSame(
            'publish_failed',
            $work->fresh()->repository_status
        );

        $this->assertNull(
            $work->fresh()->repository_published_at
        );

        $this->assertDatabaseHas(
            'publish_attempts',
            [
                'publikasi_id' => $work->id,
                'status' => 'failed',
            ]
        );
    }

    public function test_unscanned_document_blocks_publish_when_antivirus_is_required(): void
    {
        config()->set('repository.scan_required', true);

        Storage::fake('local');

        $owner = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $admin = User::factory()->admin()->create();

        $work = $this->verifiedPublication($owner);

        $path = 'documents/unscanned.pdf';
        $pdf = '%PDF-1.4 unscanned';

        $this->createDocument($work, $path, $pdf);

        Storage::disk('local')->put($path, $pdf);

        // Pemeriksaan keamanan wajib.
        $this->step($admin, $work, 'publish')
            ->assertSessionHasErrors('workflow');

        // Gagal validasi awal, status tidak berubah.
        $this->assertSame(
            'verified',
            $work->fresh()->repository_status
        );

        $this->assertDatabaseCount('publish_attempts', 0);
    }

    public function test_two_publish_requests_with_same_version_create_only_one_attempt(): void
    {
        Queue::fake([PublishPublication::class]);

        $owner = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $admin = User::factory()->admin()->create();

        $work = $this->verifiedPublication($owner);

        $payload = [
            'action' => 'publish',
            'lock_version' => $work->fresh()->lock_version,
        ];

        // Request pertama berhasil.
        $this->actingAs($admin)
            ->post(
                route('admin.works.workflow', [
                    'publikasi',
                    $work->id,
                ]),
                $payload
            )
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'published',
            $work->fresh()->repository_status
        );

        // Request kedua memakai versi lama.
        $this->post(
            route('admin.works.workflow', [
                'publikasi',
                $work->id,
            ]),
            $payload
        )->assertSessionHasErrors('lock_version');

        // Tidak boleh menghasilkan dua penerbitan.
        $this->assertDatabaseCount('publish_attempts', 1);

        Queue::assertNotPushed(PublishPublication::class);
    }

    public function test_published_metadata_is_locked_even_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $work = Publikasi::factory()
            ->published()
            ->create();

        $this->actingAs($admin)
            ->get(
                route('admin.works.edit', [
                    'publikasi',
                    $work->id,
                ])
            )
            ->assertForbidden();
    }

    public function test_failed_publication_can_be_reopened_but_must_be_verified_again(): void
    {
        $author = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $operator = User::factory()->create([
            'role' => 'operator',
        ]);

        $admin = User::factory()->admin()->create();

        $work = $this->draft($author);

        $work->forceFill([
            'repository_status' => 'publish_failed',
        ])->save();

        // Pemilik tidak berhak membuka ulang.
        $this->step($author, $work, 'reopen')
            ->assertForbidden();

        // Operator boleh membuka ulang.
        $this->step($operator, $work, 'reopen')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'revision_required',
            $work->fresh()->repository_status
        );

        $this->actingAs($author)
            ->get(
                route('admin.works.edit', [
                    'publikasi',
                    $work->id,
                ])
            )
            ->assertOk();

        // Harus melalui proses verifikasi ulang.
        $this->step($operator, $work, 'publish')
            ->assertForbidden();

        $this->step($author, $work, 'submit')
            ->assertSessionHasNoErrors();

        $this->step($admin, $work, 'review')
            ->assertSessionHasNoErrors();

        $this->step($admin, $work, 'approve')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'verified',
            $work->fresh()->repository_status
        );

        // Karya terverifikasi masih boleh dibuka
        // kembali untuk revisi oleh operator.
        $this->step($operator, $work, 'reopen')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'revision_required',
            $work->fresh()->repository_status
        );
    }

    public function test_repository_rejection_does_not_change_academic_status(): void
    {
        $author = User::factory()->create([
            'role' => 'mahasiswa',
        ]);

        $verifier = User::factory()->create([
            'role' => 'verifier',
        ]);

        $work = $this->draft($author);

        $work->forceFill([
            'academic_status' => 'published',
        ])->save();

        $this->step($author, $work, 'submit')
            ->assertSessionHasNoErrors();

        $this->step($verifier, $work, 'review')
            ->assertSessionHasNoErrors();

        $this->step($verifier, $work, 'reject')
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'revision_required',
            $work->fresh()->repository_status
        );

        // Academic status tidak boleh ikut berubah.
        $this->assertSame(
            'published',
            $work->fresh()->academic_status
        );

        $this->assertDatabaseHas(
            'verification_histories',
            [
                'publikasi_id' => $work->id,
                'status' => 'rejected',
            ]
        );
    }
}
