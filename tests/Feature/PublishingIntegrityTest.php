<?php

namespace Tests\Feature;

use App\Models\Penulis;
use App\Models\Publikasi;
use App\Models\PublishAttempt;
use App\Models\User;
use App\Services\PublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PublishingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_preverified_document_metadata_cannot_change_before_final_commit(): void
    {
        Storage::fake('local');
        config()->set('repository.scan_required', false);

        $author = User::factory()->create();
        $work = Publikasi::factory()->draft()->create([
            'created_by' => $author->id,
            'tahun_publikasi' => 2026,
            'repository_status' => 'publishing',
        ]);
        $work->penulis()->attach(Penulis::factory()->create(), ['urutan_penulis' => 1]);

        $content = '%PDF-1.4 valid';
        Storage::disk('local')->put('documents/one.pdf', $content);
        $doc = $work->dokumen()->create([
            'nama_file' => 'one.pdf',
            'path_file' => 'documents/one.pdf',
            'storage_disk' => 'local',
            'checksum' => hash('sha256', $content),
            'scan_status' => 'unscanned',
            'current_version' => 1,
        ]);
        $attempt = PublishAttempt::create([
            'publikasi_id' => $work->id,
            'attempt_number' => 1,
            'triggered_by' => $author->id,
            'status' => 'queued',
        ]);

        $publishing = app(PublishingService::class);
        $snapshot = $publishing->inspectStorage($work);
        $doc->update(['current_version' => 2]);

        try {
            $publishing->process($attempt->id, $snapshot);
            $this->fail('Document changes must not be published.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('berubah', $e->getMessage());
        }

        $this->assertSame('publishing', $work->fresh()->repository_status);
        $this->assertSame('queued', $attempt->fresh()->status);
    }

    public function test_publishing_preflight_rejects_tampered_pdf(): void
    {
        Storage::fake('local');
        config()->set('repository.scan_required', false);

        $work = Publikasi::factory()->draft()->create();
        Storage::disk('local')->put('documents/tampered.pdf', '%PDF-1.4 tampered');
        $work->dokumen()->create([
            'nama_file' => 'tampered.pdf',
            'path_file' => 'documents/tampered.pdf',
            'storage_disk' => 'local',
            'checksum' => hash('sha256', '%PDF-1.4 original'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Checksum');
        app(PublishingService::class)->inspectStorage($work);
    }
}
