<?php

namespace Tests\Feature;

use App\Models\Penulis;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ImportFeedbackRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_import_preview_displays_row_errors_without_server_error(): void
    {
        [$user, $batch] = $this->invalidBatch();

        $this->actingAs($user)
            ->get(route('admin.import.show', $batch))
            ->assertOk()
            ->assertSee('Perbaiki baris berikut');
    }

    public function test_global_validation_feedback_and_import_row_errors_can_coexist(): void
    {
        [$user, $batch] = $this->invalidBatch();
        $errors = new ViewErrorBag();
        $errors->put('default', new MessageBag(['file' => ['Berkas perlu diperiksa kembali.']]));

        // The HTTP preview is already covered above. Render the real Blade page
        // with an explicit validation bag to test that both error sources coexist,
        // without depending on session-flash timing in a synthetic GET request.
        $this->actingAs($user)
            ->get(route('admin.import.show', $batch))
            ->assertOk()
            ->assertSee('Perbaiki baris berikut');

        $authorNames = Penulis::whereIn('id', collect($batch->rows)
            ->flatMap(fn ($row) => array_column($row['data']['authors'], 'id'))
            ->unique())->pluck('nama', 'id');

        $html = view('admin.import.show', compact('batch', 'authorNames', 'errors'))->render();

        $this->assertStringContainsString('Perbaiki baris berikut', $html);
        $this->assertStringContainsString('Berkas perlu diperiksa kembali.', $html);
    }

    private function invalidBatch(): array
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        $batch = app(ImportService::class)->preview('publikasi', [
            ['judul', 'penulis_ids', 'tahun_publikasi'],
            ['Valid', (string) $author->id, '2025'],
            ['Perlu diperbaiki', (string) $author->id, 'bukan-tahun'],
        ], $user, 'import-regression.csv');

        $this->assertNotEmpty($batch->errors);

        return [$user, $batch];
    }
}
