<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Penulis;
use App\Models\Publikasi;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class ImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_preview_normalizes_and_commit_is_idempotent(): void
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        $csv = "judul,doi,tahun_publikasi,penulis_ids,public_visibility,sitasi_2025\nRiset iklim,https://doi.org/10.1234/CLIMATE,2025,{$author->id},public,7\n";
        $this->actingAs($user)->post('/admin/import/preview', ['type' => 'publikasi', 'file' => UploadedFile::fake()->createWithContent('data.csv', $csv)])->assertSessionHasNoErrors()->assertRedirect();
        $batch = ImportBatch::firstOrFail();
        $this->assertSame([], $batch->errors);
        $this->assertDatabaseCount('publikasi', 0);
        $this->get(route('admin.import.show', $batch))->assertOk()->assertSee('Riset iklim');
        $this->post(route('admin.import.commit', $batch))->assertSessionHasNoErrors();
        $this->post(route('admin.import.commit', $batch))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('publikasi', 1);
        $this->assertDatabaseHas('publikasi', ['doi' => '10.1234/climate', 'repository_status' => 'draft']);
        $this->assertDatabaseHas('sitasi', ['tahun' => 2025, 'jumlah_sitasi' => 7]);
    }

    public function test_invalid_rows_block_entire_batch_and_do_not_create_partial_data(): void
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        $batch = app(ImportService::class)->preview('publikasi', [['judul', 'penulis_ids', 'tahun_publikasi'], ['Valid', (string) $author->id, '2025'], ['Invalid', (string) $author->id, 'not-a-year']], $user, 'sample.csv');
        $this->assertArrayHasKey(3, $batch->errors);
        $this->actingAs($user)->post(route('admin.import.commit', $batch))->assertSessionHasErrors('import');
        $this->assertDatabaseCount('publikasi', 0);
    }

    public function test_duplicates_introduced_after_preview_roll_back_the_entire_batch(): void
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        $batch = app(ImportService::class)->preview('publikasi', [['judul', 'penulis_ids', 'doi'], ['First', (string) $author->id, '10.1234/first'], ['Second', (string) $author->id, '10.1234/second']], $user, 'sample.csv');
        Publikasi::factory()->draft()->create(['doi' => '10.1234/second']);
        $this->actingAs($user)->post(route('admin.import.commit', $batch))->assertSessionHasErrors('doi');
        $this->assertDatabaseCount('publikasi', 1);
        $this->assertDatabaseMissing('publikasi', ['doi' => '10.1234/first']);
        $this->assertSame('preview', $batch->fresh()->status);
    }

    public function test_duplicate_rows_and_unknown_columns_require_manual_correction(): void
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        $batch = app(ImportService::class)->preview('publikasi', [['judul', 'penulis_ids'], ['Repeated', (string) $author->id], ['Repeated', (string) $author->id]], $user, 'sample.csv');
        $this->assertArrayHasKey(3, $batch->errors);
        $this->actingAs($user)->post('/admin/import/preview', ['type' => 'publikasi', 'file' => UploadedFile::fake()->createWithContent('data.csv', "judul,penulis_ids,unrecognized\nA,1,B")])->assertSessionHasErrors('file');
    }

    public function test_import_owner_and_expiry_are_enforced(): void
    {
        $this->freezeTime();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $author = Penulis::factory()->create();
        $batch = app(ImportService::class)->preview('publikasi', [['judul', 'penulis_ids'], ['Entry', (string) $author->id]], $owner, 'sample.csv');
        $this->actingAs($other)->get(route('admin.import.show', $batch))->assertForbidden();
        $this->post(route('admin.import.commit', $batch))->assertForbidden();
        $this->travel(25)->hours();
        $this->actingAs($owner)->post(route('admin.import.commit', $batch))->assertSessionHasErrors('import');
        $this->assertDatabaseCount('publikasi', 0);
    }

    public function test_xlsx_inline_strings_are_read_and_formulas_are_rejected(): void
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        $file = $this->xlsx('<row r="1"><c r="A1" t="inlineStr"><is><t>judul</t></is></c><c r="B1" t="inlineStr"><is><t>penulis_ids</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Workbook entry</t></is></c><c r="B2"><v>'.$author->id.'</v></c></row>');
        $this->actingAs($user)->post('/admin/import/preview', ['type' => 'publikasi', 'file' => $file])->assertSessionHasNoErrors();
        $this->assertSame('Workbook entry', ImportBatch::first()->rows[0]['data']['judul']);
        $formula = $this->xlsx('<row r="1"><c r="A1"><f>1+1</f><v>2</v></c></row>');
        $this->post('/admin/import/preview', ['type' => 'publikasi', 'file' => $formula])->assertSessionHasErrors('file');
    }

    private function xlsx(string $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>');
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('data.xlsx', $bytes);
    }

    public function test_blank_csv_and_xlsx_rows_preserve_source_line_numbers(): void
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        $csv = UploadedFile::fake()->createWithContent('data.csv', "judul,penulis_ids,tahun_publikasi\n\nInvalid,{$author->id},not-a-year\n");
        $this->actingAs($user)->post('/admin/import/preview', ['type' => 'publikasi', 'file' => $csv])->assertSessionHasNoErrors();
        $this->assertArrayHasKey(3, ImportBatch::latest('id')->first()->errors);
        $xlsx = $this->xlsx('<row r="1"><c r="A1" t="inlineStr"><is><t>judul</t></is></c><c r="B1" t="inlineStr"><is><t>penulis_ids</t></is></c></row><row r="4"><c r="A4" t="inlineStr"><is><t>Invalid author</t></is></c><c r="B4"><v>99999</v></c></row>');
        $this->post('/admin/import/preview', ['type' => 'publikasi', 'file' => $xlsx])->assertSessionHasNoErrors();
        $this->assertArrayHasKey(4, ImportBatch::latest('id')->first()->errors);
    }

    public function test_distinct_identifiers_with_the_same_title_are_allowed_at_preview_and_commit(): void
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        Publikasi::factory()->draft()->create(['judul' => 'Matching title', 'doi' => '10.1234/original', 'jurnal_id' => null, 'tahun_publikasi' => 2025]);
        $batch = app(ImportService::class)->preview('publikasi', [['judul', 'penulis_ids', 'tahun_publikasi', 'doi'], ['Matching title', (string) $author->id, '2025', '10.1234/another']], $user, 'sample.csv');
        $this->assertSame([], $batch->errors);
        $this->actingAs($user)->post(route('admin.import.commit', $batch))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('publikasi', 2);
    }

    public function test_identifierless_duplicate_titles_are_detected_case_insensitively(): void
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        Publikasi::factory()->draft()->create(['judul' => 'Existing Title', 'doi' => null, 'jurnal_id' => null, 'tahun_publikasi' => 2025]);
        $batch = app(ImportService::class)->preview('publikasi', [['judul', 'penulis_ids', 'tahun_publikasi'], ['existing title', (string) $author->id, '2025']], $user, 'sample.csv');
        $this->assertArrayHasKey(2, $batch->errors);
    }
}
