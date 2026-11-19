<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Dokumen;
use App\Models\Publikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function upload(User $user, Publikasi $work, string $name = 'paper.pdf', string $content = '%PDF-1.4 demo'): TestResponse
    {
        return $this->actingAs($user)->post(route('admin.works.documents', ['publikasi', $work->id]), ['file' => UploadedFile::fake()->createWithContent($name, $content), 'publik' => 1, 'jenis_dokumen' => 'dokumen_utama', 'lock_version' => $work->fresh()->lock_version]);
    }

    public function test_pdf_is_private_until_work_published_and_permissions_are_rechecked_on_download(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'mahasiswa']);
        $work = Publikasi::factory()->draft()->create(['created_by' => $user->id, 'public_visibility' => 'public']);
        $this->upload($user, $work)->assertSessionHasNoErrors();
        $document = Dokumen::firstOrFail();
        Storage::disk('local')->assertExists($document->path_file);
        $this->assertSame(hash('sha256', '%PDF-1.4 demo'), $document->checksum);
        $this->assertNotSame('paper.pdf', basename($document->path_file));
        $this->get(route('documents.download', $document))->assertOk()->assertDownload('paper.pdf');
        auth()->logout();
        $this->get(route('documents.download', $document))->assertNotFound();
        $work->forceFill(['repository_status' => 'published'])->save();
        $this->get(route('documents.download', $document))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $work->forceFill(['repository_status' => 'unpublished'])->save();
        $this->get(route('documents.download', $document))->assertNotFound();
    }

    public function test_document_replacement_preserves_versions_and_foreign_users_cannot_access_them(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'dosen']);
        $stranger = User::factory()->create(['role' => 'dosen']);
        $work = Publikasi::factory()->draft()->create(['created_by' => $owner->id]);
        $this->upload($owner, $work)->assertSessionHasNoErrors();
        $document = Dokumen::firstOrFail();
        $oldPath = $document->path_file;
        $this->post(route('admin.documents.replace', $document), ['file' => UploadedFile::fake()->createWithContent('revision.pdf', '%PDF-1.4 revised'), 'publik' => 0, 'jenis_dokumen' => 'dokumen_utama', 'lock_version' => $work->fresh()->lock_version])->assertSessionHasNoErrors();
        $this->assertSame(2, $document->fresh()->current_version);
        $this->assertDatabaseCount('document_versions', 2);
        Storage::disk('local')->assertExists($oldPath);
        $this->actingAs($stranger)->get(route('admin.documents.version', DocumentVersion::first()))->assertForbidden();
        $this->get(route('documents.download', $document))->assertNotFound();
    }

    public function test_invalid_file_and_foreign_upload_are_rejected_without_document_rows(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'dosen']);
        $other = User::factory()->create(['role' => 'dosen']);
        $work = Publikasi::factory()->draft()->create(['created_by' => $owner->id]);
        $this->upload($owner, $work, 'payload.php', '<?php echo "bad";')->assertSessionHasErrors('file');
        $this->upload($owner, $work, 'payload.pdf', 'not a PDF')->assertSessionHasErrors('file');
        $this->upload($other, $work)->assertForbidden();
        $this->assertDatabaseCount('dokumen', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
