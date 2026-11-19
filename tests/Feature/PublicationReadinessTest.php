<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\Penulis;
use App\Models\Publikasi;
use App\Support\PublicationReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicationReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedPublication(): Publikasi
    {
        $work = Publikasi::factory()->draft()->create([
            'tahun_publikasi' => 2025,
            'repository_status' => 'verified',
        ]);

        $author = Penulis::factory()->create([
            'aktif' => true,
        ]);

        $work->penulis()->attach($author->id, [
            'urutan_penulis' => 1,
        ]);

        return $work;
    }

    public function test_draft_without_metadata_is_not_ready(): void
    {
        $work = Publikasi::factory()->draft()->create();

        $result = PublicationReadiness::evaluate($work);

        $this->assertFalse($result['ready']);
        $this->assertSame(6, $result['total']);
    }

    public function test_verified_publication_without_pdf_can_be_ready(): void
    {
        config(['repository.scan_required' => false]);

        $work = $this->verifiedPublication();

        $result = PublicationReadiness::evaluate($work);

        $this->assertTrue($result['ready']);
        $this->assertSame(6, $result['passed']);
    }

    public function test_inactive_author_blocks_readiness(): void
    {
        $work = $this->verifiedPublication();

        $work->penulis->first()->update([
            'aktif' => false,
        ]);

        $work->unsetRelation('penulis');

        $result = PublicationReadiness::evaluate($work);

        $this->assertFalse($result['ready']);
    }

    public function test_unscanned_document_blocks_when_scan_required(): void
    {
        config(['repository.scan_required' => true]);

        $work = $this->verifiedPublication();

        Dokumen::factory()->forOwner($work)->create([
            'scan_status' => 'unscanned',
        ]);

        $result = PublicationReadiness::evaluate($work);

        $this->assertFalse($result['ready']);
    }

    public function test_archived_publication_is_not_ready(): void
    {
        $work = $this->verifiedPublication();

        $work->forceFill([
            'archived_at' => now(),
        ])->save();

        $result = PublicationReadiness::evaluate($work);

        $this->assertFalse($result['ready']);
    }
}
