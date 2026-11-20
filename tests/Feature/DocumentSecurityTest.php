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
    public function test_unverified_owner_cannot_download_private_pdf_or_cover_when_verification_is_required(): void
    {
        Storage::fake('local');
        $owner = User::factory()->unverified()->create(['role' => 'dosen']);
        $work = Publikasi::factory()->draft()->create(['created_by' => $owner->id, 'public_visibility' => 'private']);
        $documentPath = 'documents/private-owner.pdf';
        $coverPath = 'covers/private-owner.png';
        Storage::disk('local')->put($documentPath, '%PDF-1.4 private');
        Storage::disk('local')->put($coverPath, 'png-bytes');

        $document = $work->dokumen()->create([
            'nama_file' => 'private-owner.pdf',
            'nama_file_asli' => 'private-owner.pdf',
            'path_file' => $documentPath,
            'storage_disk' => 'local',
            'mime_type' => 'application/pdf',
            'ukuran_file' => 16,
            'jenis_dokumen' => 'dokumen_utama',
            'publik' => false,
            'scan_status' => 'clean',
        ]);
        $work->cover()->create(['disk' => 'local', 'path' => $coverPath, 'uploaded_by' => $owner->id]);
        $version = DocumentVersion::create([
            'dokumen_id' => $document->id,
            'version_number' => 1,
            'storage_disk' => 'local',
            'path_file' => $documentPath,
            'nama_file_asli' => 'private-owner.pdf',
            'mime_type' => 'application/pdf',
            'ukuran_file' => 16,
            'scan_status' => 'clean',
            'created_at' => now(),
        ]);

        config(['repository.require_verified_email' => true]);
        $this->actingAs($owner)->get(route('documents.download', $document))->assertNotFound();
        $this->get(route('works.cover.show', ['publikasi', $work->id]))->assertNotFound();
        $this->get(route('admin.documents.version', $version))->assertForbidden();

        $owner->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($owner)->get(route('documents.download', $document))->assertOk();
        $this->get(route('works.cover.show', ['publikasi', $work->id]))->assertOk();
        $this->get(route('admin.documents.version', $version))->assertOk();
    }

    public function test_public_pdf_and_cover_remain_accessible_without_login_or_verified_email(): void
    {
        Storage::fake('local');
        config(['repository.require_verified_email' => true]);
        $work = Publikasi::factory()->published()->create();
        $pdf = 'documents/public-paper.pdf';
        $cover = 'covers/public-paper.png';
        Storage::disk('local')->put($pdf, '%PDF-1.4 public');
        Storage::disk('local')->put($cover, 'png-bytes');

        $document = $work->dokumen()->create([
            'nama_file' => 'public-paper.pdf',
            'nama_file_asli' => 'public-paper.pdf',
            'path_file' => $pdf,
            'storage_disk' => 'local',
            'mime_type' => 'application/pdf',
            'ukuran_file' => 15,
            'jenis_dokumen' => 'dokumen_utama',
            'publik' => true,
            'scan_status' => 'clean',
        ]);
        $work->cover()->create(['disk' => 'local', 'path' => $cover, 'uploaded_by' => null]);

        $this->get(route('documents.download', $document))->assertOk();
        $this->get(route('works.cover.show', ['publikasi', $work->id]))->assertOk();
        $unverified = User::factory()->unverified()->create(['role' => 'mahasiswa']);
        $this->actingAs($unverified)->get(route('documents.download', $document))->assertOk();
        $this->get(route('works.cover.show', ['publikasi', $work->id]))->assertOk();
        $document->update(['publik' => false]);
        $this->get(route('documents.download', $document))->assertNotFound();
    }

    public function test_other_accounts_and_verifiers_cannot_download_private_draft_files_by_id(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'dosen']);
        $outsider = User::factory()->create(['role' => 'dosen']);
        $verifier = User::factory()->create(['role' => 'verifier']);
        $work = Publikasi::factory()->draft()->create(['created_by' => $owner->id, 'public_visibility' => 'private']);
        $path = 'documents/private-draft.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 private');
        $doc = $work->dokumen()->create([
            'nama_file' => 'private-draft.pdf',
            'nama_file_asli' => 'private-draft.pdf',
            'path_file' => $path,
            'storage_disk' => 'local',
            'mime_type' => 'application/pdf',
            'ukuran_file' => 15,
            'jenis_dokumen' => 'dokumen_utama',
            'publik' => false,
            'scan_status' => 'clean',
        ]);

        $this->actingAs($outsider)->get(route('documents.download', $doc))->assertNotFound();
        $this->actingAs($verifier)->get(route('documents.download', $doc))->assertNotFound();
        $this->actingAs($owner)->get(route('documents.download', $doc))->assertOk();
    }


}
