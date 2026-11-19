<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\KekayaanIntelektual;
use App\Models\Publikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileCoverFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function png(): UploadedFile
    {
        // A genuine 1x1 PNG; no GD extension required to construct the fixture.
        return UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9aN7SWUAAAAASUVORK5CYII='));
    }

    public function test_profile_job_title_and_bio_are_saved(): void
    {
        $user = User::factory()->create()->refresh();
        $this->actingAs($user)->patch(route('profile.update'), [
            'nama' => $user->nama, 'email' => $user->email,
            'jabatan' => 'Dosen Informatika', 'bio' => 'Riset keamanan aplikasi', 'lock_version' => $user->lock_version,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Dosen Informatika', $user->fresh()->jabatan);
    }

    public function test_profile_photo_requires_authentication(): void
    {
        $this->get(route('profile.photo.show'))->assertRedirect(route('login'));
    }

    public function test_cover_can_be_uploaded_to_a_draft_for_all_work_types(): void
    {
        Storage::fake(config('repository.documents_disk', 'local'));
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        foreach ([['publikasi', Publikasi::factory()->draft()->create(['created_by' => $actor->id])->refresh()],
                  ['buku', Buku::factory()->create(['created_by' => $actor->id])->refresh()],
                  ['hki', KekayaanIntelektual::factory()->create(['created_by' => $actor->id])->refresh()]] as [$type, $work]) {
            $this->post(route('admin.works.cover.store', [$type, $work->id]), [
                'cover' => $this->png(), 'lock_version' => $work->lock_version,
            ])->assertSessionHasNoErrors();
            $cover = $work->fresh()->cover;
            $this->assertNotNull($cover);
            Storage::disk($cover->disk)->assertExists($cover->path);
            $this->get(route('works.cover.show', [$type, $work->id]))->assertOk();
        }
    }

    public function test_private_cover_cannot_be_read_by_guest(): void
    {
        Storage::fake(config('repository.documents_disk', 'local'));
        $actor = User::factory()->admin()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $actor->id])->refresh();
        $this->actingAs($actor)->post(route('admin.works.cover.store', ['publikasi', $work->id]), [
            'cover' => $this->png(), 'lock_version' => $work->lock_version,
        ])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('works.cover.show', ['publikasi', $work->id]))->assertNotFound();
    }

    public function test_cover_rejects_non_png_and_stale_version(): void
    {
        Storage::fake(config('repository.documents_disk', 'local'));
        $actor = User::factory()->admin()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $actor->id])->refresh();
        $this->actingAs($actor)->post(route('admin.works.cover.store', ['publikasi', $work->id]), [
            'cover' => UploadedFile::fake()->create('cover.pdf', 3, 'application/pdf'), 'lock_version' => $work->lock_version,
        ])->assertSessionHasErrors('cover');
        $this->post(route('admin.works.cover.store', ['publikasi', $work->id]), [
            'cover' => $this->png(), 'lock_version' => $work->lock_version + 1,
        ])->assertSessionHasErrors('cover');
        $this->assertDatabaseCount('work_covers', 0);
    }
}
