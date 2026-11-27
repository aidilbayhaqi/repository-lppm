<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Dosen;
use App\Models\Fakultas;
use App\Models\ImportBatch;
use App\Models\Jurnal;
use App\Models\Penulis;
use App\Models\ProgramStudi;
use App\Models\Publikasi;
use App\Models\User;
use App\Services\ImportService;
use App\Services\SpreadsheetReader;
use App\Services\SpreadsheetTemplate;
use App\Services\WorkSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class GuidedSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $extra = []): array
    {
        return array_replace([
            'judul' => 'Riset komunitas', 'public_visibility' => 'private', 'tahun_publikasi' => 2026,
            'authors' => [['mode' => 'manual', 'nama' => 'Rani Prasetya', 'jenis_penulis' => 'mahasiswa', 'identitas' => 'M001']],
            'journal_source' => 'manual', 'journal_name_manual' => 'Prosiding Mitra Riset', 'publication_type' => 'prosiding',
        ], $extra);
    }

    public function test_student_can_save_new_authors_journal_and_cover_in_one_request(): void
    {
        Storage::fake('local');
        config(['repository.documents_disk' => 'local', 'repository.scan_required' => false]);
        $user = User::factory()->create(['role' => 'mahasiswa']);
        $data = $this->data(['isbn' => '978-602-8519-93-9', 'issn_cetak' => '1234-567X', 'attachments' => [
            ['jenis_dokumen' => 'cover', 'publik' => 0, 'file' => UploadedFile::fake()->createWithContent('sampul.pdf', '%PDF-1.4 cover')],
        ]]);
        $this->actingAs($user)->post('/admin/karya/publikasi', $data)->assertSessionHasNoErrors()->assertRedirect();
        $work = Publikasi::firstOrFail();
        $this->assertSame('draft', $work->repository_status);
        $this->assertSame('9786028519939', $work->isbn);
        $this->assertSame('Prosiding Mitra Riset', $work->jurnal->nama_jurnal);
        $this->assertSame('Rani Prasetya', $work->penulis->first()->nama);
        $this->assertNull($work->penulis->first()->user_id);
        $this->assertSame('cover', $work->dokumen->first()->jenis_dokumen);
        $this->assertFalse($work->dokumen->first()->publik);
        Storage::disk('local')->assertExists($work->dokumen->first()->path_file);
        $this->get('/admin/karya/publikasi/'.$work->id)->assertOk()->assertSee('Cover')->assertSee('sampul.pdf');
    }

    public function test_new_lecturer_uses_existing_active_program_reference(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'mahasiswa']));
        $faculty = Fakultas::factory()->create(['aktif' => true]);
        $program = ProgramStudi::factory()->create(['fakultas_id' => $faculty->id, 'nama' => 'Sains Terapan', 'aktif' => true]);
        $data = $this->data(['authors' => [[
            'mode' => 'manual', 'nama' => 'Dewi Riset',
            'jenis_penulis' => 'dosen', 'nidn' => '0011223344',
            'program_studi_id' => $program->id,
        ]]]);
        $this->post('/admin/karya/publikasi', $data)->assertSessionHasNoErrors();
        $data['judul'] = 'Riset lanjutan';
        $this->post('/admin/karya/publikasi', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('fakultas', 1);
        $this->assertDatabaseCount('program_studi', 1);
        $this->assertSame(1, Dosen::where('nidn', '0011223344')->count());
        $this->assertSame(1, Penulis::where('nama', 'Dewi Riset')->count());
        $this->assertSame(1, Jurnal::where('nama_jurnal', 'Prosiding Mitra Riset')->count());
        $this->assertSame('Sains Terapan', Dosen::where('nidn', '0011223344')->first()->programStudi->nama);
    }

    public function test_new_lecturer_requires_program_reference_and_cannot_use_inactive_faculty(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'mahasiswa']));
        $lecturer = ['mode' => 'manual', 'nama' => 'Dewi Riset', 'jenis_penulis' => 'dosen', 'nidn' => '0011223344'];
        $this->post('/admin/karya/publikasi', $this->data(['authors' => [$lecturer]]))
            ->assertSessionHasErrors('authors.0.program_studi_id');
        $faculty = Fakultas::factory()->create(['aktif' => false]);
        $program = ProgramStudi::factory()->create(['fakultas_id' => $faculty->id, 'aktif' => true]);
        $this->post('/admin/karya/publikasi', $this->data(['authors' => [[...$lecturer, 'program_studi_id' => $program->id]]]))
            ->assertSessionHasErrors('authors.0.program_studi_id');
        $this->assertDatabaseCount('dosen', 0);
    }

    public function test_non_lecturer_cannot_set_program_and_valid_top_level_references_are_accepted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'mahasiswa']));
        $faculty = Fakultas::factory()->create(['aktif' => true]);
        $program = ProgramStudi::factory()->create(['fakultas_id' => $faculty->id, 'aktif' => true]);
        $data = $this->data();
        $data['authors'][0]['program_studi_id'] = $program->id;
        $this->post('/admin/karya/publikasi', $data)->assertSessionHasErrors('authors.0.program_studi_id');
        $this->assertDatabaseCount('publikasi', 0);

        // Metadata may use existing, active faculty/program references.
        // Manual faculty/program names are rejected in the separate test below.
        $this->post('/admin/karya/publikasi', $this->data([
            'fakultas_id' => $faculty->id,
            'program_studi_id' => $program->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('publikasi', [
            'judul' => 'Riset komunitas',
            'fakultas_id' => $faculty->id,
            'program_studi_id' => $program->id,
        ]);
        $this->assertDatabaseCount('fakultas', 1);
        $this->assertDatabaseCount('program_studi', 1);
    }

    public function test_manual_faculty_program_creation_from_submission_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'mahasiswa']));
        $data = $this->data(['authors' => [[
            'mode' => 'manual', 'nama' => 'Dewi Riset',
            'jenis_penulis' => 'dosen', 'nidn' => '0011223344',
            'program_name' => 'Prodi Tidak Sah',
            'faculty_name' => 'Fakultas Tidak Sah',
        ]]]);

        $this->post('/admin/karya/publikasi', $data)
            ->assertSessionHasErrors(['authors.0.program_name', 'authors.0.faculty_name']);
        $this->assertDatabaseCount('fakultas', 0);
        $this->assertDatabaseCount('program_studi', 0);
        $this->assertDatabaseCount('dosen', 0);
    }

    public function test_missing_nidn_conflicting_identity_and_privilege_fields_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $author = ['mode' => 'manual', 'nama' => 'Dewi Riset', 'jenis_penulis' => 'dosen'];
        $this->post('/admin/karya/publikasi', $this->data(['authors' => [$author]]))->assertSessionHasErrors('authors.0.nidn');
        Dosen::factory()->create(['nidn' => '0011223344', 'nama' => 'Nama Lain']);
        $author['nidn'] = '0011223344';
        $this->post('/admin/karya/publikasi', $this->data(['authors' => [$author]]))->assertSessionHasErrors('authors.0.nidn');
        $author['user_id'] = 1;
        $this->post('/admin/karya/publikasi', $this->data(['authors' => [$author]]))->assertSessionHasErrors('authors.0');
        $this->assertDatabaseCount('publikasi', 0);
        $this->assertDatabaseCount('penulis', 0);
    }

    public function test_failed_second_attachment_rolls_back_metadata_references_and_first_file(): void
    {
        Storage::fake('local');
        config(['repository.documents_disk' => 'local', 'repository.scan_required' => false]);
        $data = $this->data(['attachments' => [
            ['jenis_dokumen' => 'cover', 'file' => UploadedFile::fake()->createWithContent('cover.pdf', '%PDF-1.4 cover')],
            ['jenis_dokumen' => 'lampiran', 'file' => UploadedFile::fake()->createWithContent('bad.pdf', 'not a pdf')],
        ]]);
        try {
            app(WorkSubmissionService::class)->save('publikasi', $data, User::factory()->create());
            $this->fail('Expected invalid PDF rejection.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('attachments.1.file', $error->errors());
        }
        foreach (['publikasi', 'penulis', 'jurnal', 'dokumen', 'document_versions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_unauthorized_update_does_not_create_inline_references(): void
    {
        $work = Publikasi::factory()->draft()->create(['created_by' => User::factory()->create()->id]);
        $this->actingAs(User::factory()->create(['role' => 'mahasiswa']))->put('/admin/karya/publikasi/'.$work->id, $this->data(['lock_version' => $work->fresh()->lock_version]))->assertForbidden();
        $this->assertDatabaseMissing('penulis', ['nama' => 'Rani Prasetya']);
        $this->assertDatabaseMissing('jurnal', ['nama_jurnal' => 'Prosiding Mitra Riset']);
    }

    public function test_duplicate_manual_author_is_rejected_and_reordered_rows_keep_order(): void
    {
        $this->actingAs(User::factory()->create());
        $a = $this->data()['authors'][0];
        $this->post('/admin/karya/publikasi', $this->data(['authors' => [$a, $a]]))->assertSessionHasErrors('authors.1.id');
        $this->assertDatabaseCount('penulis', 0);
        $this->post('/admin/karya/publikasi', $this->data(['authors' => [7 => $a, 2 => array_replace($a, ['nama' => 'Budi Riset', 'identitas' => 'M002'])]]))->assertSessionHasNoErrors();
        $authors = Publikasi::first()->penulis;
        $this->assertSame(['Rani Prasetya', 'Budi Riset'], $authors->pluck('nama')->all());
        $this->assertSame(1, $authors->first()->pivot->urutan_penulis);
    }

    public function test_book_manual_publisher_and_hki_manual_creator_are_supported(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'mahasiswa']));
        $data = $this->data(['publisher_source' => 'manual', 'publisher_name' => 'Penerbit Mandiri']);
        $this->post('/admin/karya/buku', $data)->assertSessionHasNoErrors();
        $this->assertSame('Penerbit Mandiri', Buku::first()->penerbit->nama);
        $this->post('/admin/karya/hki', $this->data(['jenis' => 'Hak Cipta', 'tahun' => 2026]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('kekayaan_intelektual', 1);
        foreach (['publikasi', 'buku', 'hki'] as $type) {
            $this->get('/admin/karya/'.$type.'/create')->assertOk()->assertSee('data-work-wizard', false)->assertSee('Identitas terbitan');
        }
    }

    public function test_admin_self_review_requires_notes_and_is_audited_but_verifier_conflict_is_denied(): void
    {
        $admin = User::factory()->admin()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $admin->id]);
        $work->forceFill(['repository_status' => 'submitted'])->save();
        $url = route('admin.works.workflow', ['publikasi', $work->id]);
        $this->actingAs($admin)->post($url, ['action' => 'review', 'lock_version' => $work->fresh()->lock_version])->assertSessionHasErrors('notes');
        $this->post($url, ['action' => 'review', 'lock_version' => $work->fresh()->lock_version, 'notes' => 'Pemeriksaan administratif oleh admin.'])->assertSessionHasNoErrors();
        $this->assertSame('pending_verification', $work->fresh()->repository_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_self_review', 'user_id' => $admin->id]);
        $verifier = User::factory()->create(['role' => 'verifier']);
        $work->forceFill(['created_by' => $verifier->id, 'repository_status' => 'submitted'])->save();
        $this->actingAs($verifier)->post($url, ['action' => 'review', 'lock_version' => $work->fresh()->lock_version])->assertForbidden();
    }

    public function test_excel_template_and_manual_import_preserve_nidn_without_preview_side_effects(): void
    {
        $actor = User::factory()->create();
        $faculty = Fakultas::factory()->create(['aktif' => true]);
        $program = ProgramStudi::factory()->create(['fakultas_id' => $faculty->id, 'aktif' => true]);
        $headers = app(ImportService::class)->headers('publikasi');
        $values = ['judul' => 'Riset dari Excel', 'penulis_nama' => 'Rani|Dewi Riset', 'penulis_jenis' => 'mahasiswa|dosen', 'penulis_nidn' => '|0011223344', 'penulis_prodi_id' => '|'.$program->id, 'journal_name_manual' => 'Prosiding Riset Baru', 'publication_type' => 'prosiding', 'sinta_rank' => '2', 'doi' => 'https://doi.org/10.1234/EXCEL', 'isbn' => '978-602-8519-93-9'];
        $file = $this->workbook($headers, $values);
        $this->actingAs($actor)->get(route('admin.import.template', 'publikasi'))->assertOk()->assertDownload('template-publikasi.xlsx');
        $this->post('/admin/import/preview', ['type' => 'publikasi', 'file' => $file])->assertSessionHasNoErrors();
        $batch = ImportBatch::firstOrFail();
        $this->assertSame([], $batch->errors);
        foreach (['publikasi', 'jurnal', 'dosen', 'penulis'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->get(route('admin.import.show', $batch))->assertOk()->assertSee('Dewi Riset');
        $this->post(route('admin.import.commit', $batch))->assertSessionHasNoErrors();
        $this->post(route('admin.import.commit', $batch))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('publikasi', 1);
        $this->assertDatabaseHas('dosen', ['nama' => 'Dewi Riset', 'nidn' => '0011223344', 'program_studi_id' => $program->id]);
        $this->assertDatabaseHas('publikasi', ['doi' => '10.1234/excel', 'sinta_rank' => 2, 'isbn' => '9786028519939']);
    }

    public function test_excel_first_sheet_relationship_and_serial_date_are_honored(): void
    {
        $file = $this->workbook(['judul', 'tanggal_publikasi'], ['judul' => 'Tanggal Excel'], true);
        $rows = app(SpreadsheetReader::class)->read($file);
        $this->assertSame('2025-01-01', $rows[1][1]);
        $this->assertSame('Tanggal Excel', $rows[1][0]);
    }

    public function test_import_refuses_mixed_authors_and_conflicting_nidn_without_writes(): void
    {
        $actor = User::factory()->create();
        $author = Penulis::factory()->create();
        $batch = app(ImportService::class)->preview('publikasi', [['judul', 'penulis_ids', 'penulis_nama'], ['Campuran', (string) $author->id, 'Rani']], $actor, 'data.xlsx');
        $this->assertArrayHasKey(2, $batch->errors);
        Dosen::factory()->create(['nama' => 'Dosen Lama', 'nidn' => '0011223344']);
        $batch = app(ImportService::class)->preview('publikasi', [['judul', 'penulis_nama', 'penulis_jenis', 'penulis_nidn'], ['Konflik', 'Nama Lain', 'dosen', '0011223344']], $actor, 'data.xlsx');
        $this->assertArrayHasKey(2, $batch->errors);
        $this->assertDatabaseCount('publikasi', 0);
    }

    public function test_new_lecturer_without_affiliation_has_a_readable_public_profile(): void
    {
        $lecturer = Dosen::factory()->create(['program_studi_id' => null]);
        $this->get(route('repository.lecturers'))->assertOk()->assertSee('Program studi belum dicatat');
        $this->get(route('repository.lecturer', $lecturer))->assertOk()->assertSee('Program studi belum dicatat');
    }

    public function test_import_reference_conflict_rolls_back_an_entire_batch(): void
    {
        $actor = User::factory()->create();
        $faculty = Fakultas::factory()->create(['aktif' => true]);
        $program = ProgramStudi::factory()->create(['fakultas_id' => $faculty->id, 'aktif' => true]);
        $sheet = [['judul', 'penulis_nama', 'penulis_jenis', 'penulis_nidn', 'penulis_prodi_id'],
            ['Baris awal', 'Rani', 'mahasiswa', '', ''], ['Baris akhir', 'Dewi', 'dosen', '0011223344', (string) $program->id]];
        $batch = app(ImportService::class)->preview('publikasi', $sheet, $actor, 'data.csv');
        $this->assertSame([], $batch->errors);
        Dosen::factory()->create(['nama' => 'Identitas berbeda', 'nidn' => '0011223344']);
        $this->actingAs($actor)->post(route('admin.import.commit', $batch))->assertSessionHasErrors('authors.0.nidn');
        $this->assertDatabaseMissing('penulis', ['nama' => 'Rani']);
        $this->assertDatabaseCount('publikasi', 0);
        $this->assertSame('preview', $batch->fresh()->status);
    }

    public function test_import_rejects_misaligned_author_columns_and_duplicate_manual_journal_work(): void
    {
        $actor = User::factory()->create();
        $service = app(ImportService::class);
        $batch = $service->preview('publikasi', [['judul', 'penulis_nama', 'penulis_nidn'], ['Data', 'Rani', '|0011223344']], $actor, 'data.csv');
        $this->assertArrayHasKey(2, $batch->errors);
        $sheet = [['judul', 'penulis_nama', 'journal_name_manual'], ['Riset sama', 'Rani', 'Jurnal Mandiri']];
        $batch = $service->preview('publikasi', $sheet, $actor, 'data.csv');
        $this->assertSame(1, $service->commit($batch, $actor));
        $repeated = $service->preview('publikasi', $sheet, $actor, 'ulang.csv');
        $this->assertArrayHasKey(2, $repeated->errors);
    }

    public function test_excel_unknown_program_reference_is_rejected_during_preview(): void
    {
        $actor = User::factory()->create();
        $sheet = [
            ['judul', 'penulis_nama', 'penulis_jenis', 'penulis_nidn', 'penulis_prodi_id'],
            ['Invalid reference', 'Dewi', 'dosen', '0011223344', '999999'],
        ];
        $batch = app(ImportService::class)->preview('publikasi', $sheet, $actor, 'data.csv');
        $this->assertArrayHasKey(2, $batch->errors);
        $this->assertDatabaseCount('program_studi', 0);
        $this->assertDatabaseCount('fakultas', 0);
    }

    public function test_renamed_reference_is_not_silently_reused_by_an_old_entry_key(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor)->post('/admin/karya/publikasi', $this->data())->assertSessionHasNoErrors();
        Jurnal::where('nama_jurnal', 'Prosiding Mitra Riset')->first()->update(['nama_jurnal' => 'Nama jurnal terbaru']);
        $this->post('/admin/karya/publikasi', $this->data(['judul' => 'Karya berikutnya']))->assertSessionHasErrors('journal_name_manual');
        $this->assertDatabaseCount('publikasi', 1);
    }

    public function test_empty_recommendation_points_to_the_visible_author_control(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/karya/publikasi', $this->data([
            'authors' => [['mode' => 'reference', 'id' => null]],
        ]))->assertSessionHasErrors('authors.0.id')->assertSessionDoesntHaveErrors(['authors.0.nama', 'authors.0.jenis_penulis']);
        $this->assertDatabaseCount('publikasi', 0);
    }

    private function workbook(array $headers, array $values, bool $nativeDate = false): UploadedFile
    {
        $path = app(SpreadsheetTemplate::class)->create($headers);
        $zip = new ZipArchive;
        $zip->open($path);
        $cells = '';
        foreach ($headers as $i => $header) {
            $n = $i + 1;
            $column = '';
            while ($n > 0) {
                $n--;
                $column = chr(65 + $n % 26).$column;
                $n = intdiv($n, 26);
            }
            $cells .= $nativeDate && $i === 1 ? '<c r="B2" s="2"><v>45658</v></c>' : '<c r="'.$column.'2" t="inlineStr"><is><t>'.htmlspecialchars($values[$header] ?? '', ENT_XML1).'</t></is></c>';
        }
        $sheet = str_replace('<row r="2"/>', '<row r="2">'.$cells.'</row>', $zip->getFromName('xl/worksheets/sheet1.xml'));
        if ($nativeDate) {
            $zip->deleteName('xl/worksheets/sheet1.xml');
            $zip->addFromString('xl/_rels/workbook.xml.rels', str_replace('sheet1.xml', 'sheet7.xml', $zip->getFromName('xl/_rels/workbook.xml.rels')));
            $zip->addFromString('xl/worksheets/sheet7.xml', $sheet);
            $styles = str_replace('cellXfs count="2"', 'cellXfs count="3"', $zip->getFromName('xl/styles.xml'));
            $zip->addFromString('xl/styles.xml', str_replace('</cellXfs>', '<xf numFmtId="14"/></cellXfs>', $styles));
        } else {
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        }
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('data.xlsx', $bytes);
    }
}
