<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\ProgramStudi;
use App\Models\Publikasi;
use App\Models\User;
use App\Services\ImportService;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FacultyInputTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_replace(['judul' => 'Jurnal uji asal prodi', 'tahun_publikasi' => 2026, 'public_visibility' => 'private', 'authors' => [['mode' => 'manual', 'nama' => 'Rani', 'jenis_penulis' => 'mahasiswa']]], $extra);
    }

    public function test_unit_belongs_to_work_and_filter_keeps_private_scope(): void
    {
        $user = User::factory()->create(['role' => 'mahasiswa']);
        $p = ProgramStudi::factory()->create();
        $this->actingAs($user)->post('/admin/karya/publikasi', $this->payload(['program_studi_id' => $p->id]))->assertSessionHasNoErrors();
        $work = Publikasi::firstOrFail();
        $this->assertEquals($p->id, $work->program_studi_id);
        $this->assertEquals($p->fakultas_id, $work->fakultas_id);
        $this->assertDatabaseCount('program_studi', 1);
        $this->assertDatabaseCount('fakultas', 1);
        $this->assertSame(1, app(SearchService::class)->query('publikasi', ['program_studi_id' => $p->id], $user)->count());
        $this->assertSame(0, app(SearchService::class)->query('publikasi', ['program_studi_id' => $p->id])->count());
        $this->get('/admin/karya/publikasi/'.$work->id)->assertOk()->assertSee($p->nama)->assertSee($p->fakultas->nama_fakultas);
        $this->put('/admin/karya/publikasi/'.$work->id, $this->payload(['program_studi_id' => null, 'fakultas_id' => null, 'lock_version' => $work->lock_version]))->assertSessionHasNoErrors();
        $this->assertNull($work->fresh()->program_studi_id);
        $this->assertNull($work->fresh()->fakultas_id);
    }

    public function test_mismatch_invalid_inactive_and_manual_units_are_rejected_without_writes(): void
    {
        $p = ProgramStudi::factory()->create();
        $other = Fakultas::factory()->create();
        $this->actingAs(User::factory()->create());
        foreach ([['program_studi_id' => $p->id, 'fakultas_id' => $other->id], ['program_studi_id' => 999999], ['program_name' => 'Manual'], ['faculty_name' => 'Manual']] as $extra) {
            $this->post('/admin/karya/publikasi', $this->payload($extra))->assertSessionHasErrors();
        }
        $p->fakultas->update(['aktif' => false]);
        $this->post('/admin/karya/publikasi', $this->payload(['program_studi_id' => $p->id]))->assertSessionHasErrors('program_studi_id');
        $this->assertDatabaseCount('publikasi', 0);
        $this->assertDatabaseCount('penulis', 0);
        $this->assertDatabaseCount('program_studi', 1);
    }

    public function test_form_has_work_fields_without_author_affiliation_inputs(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/karya/publikasi/create')->assertOk()
            ->assertSee('name="fakultas_id"', false)->assertSee('name="program_studi_id"', false)
            ->assertSee('name="authors[__INDEX__][program_studi_id]"', false)->assertDontSee('[program_name]', false)->assertDontSee('[faculty_name]', false);
    }

    public function test_excel_unit_fields_preview_commit_and_revalidation(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $p = ProgramStudi::factory()->create();
        $service = app(ImportService::class);
        $this->assertContains('fakultas_id', $service->headers('publikasi'));
        $this->assertContains('program_studi_id', $service->headers('publikasi'));
        $headers = ['judul', 'tahun_publikasi', 'penulis_nama', 'fakultas_id', 'program_studi_id'];
        $batch = $service->preview('publikasi', [$headers, ['Uji import', 2026, 'Penulis Import', $p->fakultas_id, $p->id]], $user, 'uji.xlsx');
        $this->assertEmpty($batch->errors);
        $this->assertSame(1, $service->commit($batch, $user));
        $this->assertDatabaseHas('publikasi', ['program_studi_id' => $p->id]);
        $bad = $service->preview('publikasi', [$headers, ['Uji gagal', 2026, 'Penulis lain', $p->fakultas_id, 999999]], $user, 'uji.xlsx');
        $this->assertNotEmpty($bad->errors);
        $stale = $service->preview('publikasi', [$headers, ['Uji nonaktif', 2026, 'Penulis lain', $p->fakultas_id, $p->id]], $user, 'uji.xlsx');
        $p->update(['aktif' => false]);
        try {
            $service->commit($stale, $user);
            $this->fail('Inactive program must fail commit');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('publikasi', 1);
        }
    }

    public function test_author_unit_payload_is_rejected(): void
    {
        $data = $this->payload();
        $data['authors'][0]['program_name'] = 'Manual prodi';
        $this->actingAs(User::factory()->create())->post('/admin/karya/publikasi', $data)->assertSessionHasErrors('authors.0.program_name');
        $this->assertDatabaseCount('program_studi', 0);
    }
}
