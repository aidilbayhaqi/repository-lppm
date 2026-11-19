<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Dosen;
use App\Models\KekayaanIntelektual;
use App\Models\Penulis;
use App\Models\Publikasi;
use App\Models\User;
use App\Support\ReferenceCatalog;
use App\Support\WorkType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RepositoryFeatureTest extends TestCase
{
    use RefreshDatabase;

    public static function workTypes(): array
    {
        return [['publikasi'], ['buku'], ['hki']];
    }

    #[DataProvider('workTypes')]
    public function test_author_can_create_edit_and_archive_each_work_type(string $type): void
    {
        $user = User::factory()->create(['role' => 'mahasiswa']);
        $author = Penulis::factory()->create();
        $data = ['judul' => 'Riset energi untuk komunitas', 'public_visibility' => 'public', 'authors' => [['id' => $author->id]], 'tahun_publikasi' => 2025, 'tahun' => 2025, 'jenis' => 'Hak cipta', 'repository_status' => 'published', 'created_by' => 999];
        $this->actingAs($user)->post(route('admin.works.store', $type), $data)->assertSessionHasNoErrors()->assertRedirect();
        $class = WorkType::model($type);
        $work = $class::firstOrFail();
        $this->assertSame($user->id, $work->created_by);
        $this->assertSame('draft', WorkType::status($work));
        $this->assertSame($author->id, $work->{WorkType::authors($work)}->first()->id);
        $this->get(route('admin.works.show', [$type, $work->id]))->assertOk()->assertSee($data['judul']);
        $this->get(route('admin.works.edit', [$type, $work->id]))->assertOk();
        $data['judul'] = 'Riset energi revisi';
        $data['lock_version'] = $work->lock_version;
        $this->put(route('admin.works.update', [$type, $work->id]), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Riset energi revisi', $work->fresh()->judul);
        $this->post(route('admin.works.workflow', [$type, $work->id]), ['action' => 'archive', 'notes' => 'Draf tidak dilanjutkan', 'lock_version' => $work->fresh()->lock_version])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull($work->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $work->id, 'subject_type' => $class, 'action' => 'archive']);
    }

    public function test_doi_is_normalized_and_duplicates_and_duplicate_authors_are_rejected(): void
    {
        $user = User::factory()->operator()->create();
        $author = Penulis::factory()->create();
        $data = ['judul' => 'Judul uji', 'doi' => 'https://doi.org/10.1234/ENERGY', 'authors' => [['id' => $author->id]], 'public_visibility' => 'private'];
        $this->actingAs($user)->post('/admin/karya/publikasi', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('publikasi', ['doi' => '10.1234/energy']);
        $this->post('/admin/karya/publikasi', $data)->assertSessionHasErrors('doi');
        $data['doi'] = null;
        $data['authors'][] = ['id' => $author->id];
        $this->post('/admin/karya/publikasi', $data)->assertSessionHasErrors('authors.0.id');
        $this->assertDatabaseCount('publikasi', 1);
    }

    public function test_invalid_update_is_atomic_and_stale_edits_are_rejected(): void
    {
        $user = User::factory()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $user->id]);
        $author = Penulis::factory()->create();
        $work->penulis()->attach($author, ['urutan_penulis' => 1]);
        $payload = ['judul' => 'Changed', 'public_visibility' => 'public', 'authors' => [['id' => $author->id]], 'lock_version' => 10];
        $this->actingAs($user)->put(route('admin.works.update', ['publikasi', $work->id]), $payload)->assertSessionHasErrors('lock_version');
        $this->assertNotSame('Changed', $work->fresh()->judul);
        $payload['lock_version'] = 0;
        $payload['citations'] = [['tahun' => 2025, 'sumber' => 'scopus', 'cakupan' => 'umum', 'jumlah_sitasi' => 5], ['tahun' => 2025, 'sumber' => 'scopus', 'cakupan' => 'umum', 'jumlah_sitasi' => 8]];
        $this->put(route('admin.works.update', ['publikasi', $work->id]), $payload)->assertSessionHasErrors('citations');
        $this->assertNotSame('Changed', $work->fresh()->judul);
        $this->assertDatabaseCount('sitasi', 0);
    }

    public function test_non_scalar_identifiers_are_validation_errors_instead_of_server_errors(): void
    {
        $user = User::factory()->create();
        $author = Penulis::factory()->create();
        $this->actingAs($user);
        foreach (['publikasi' => 'doi', 'buku' => 'isbn'] as $type => $field) {
            $this->post(route('admin.works.store', $type), ['judul' => 'Malformed identifier', 'public_visibility' => 'private', 'authors' => [['id' => $author->id]], $field => ['invalid']])->assertSessionHasErrors($field);
            $this->assertDatabaseCount($type, 0);
        }
    }

    public function test_search_combines_published_public_works_and_hides_private_metadata(): void
    {
        $public = Publikasi::factory()->published()->create(['judul' => 'Energi angin terbuka', 'keterangan' => 'INTERNAL SECRET', 'biaya' => 987654321]);
        Publikasi::factory()->published()->create(['judul' => 'Energi angin rahasia', 'public_visibility' => 'private']);
        Publikasi::factory()->draft()->create(['judul' => 'Energi angin draf']);
        Buku::factory()->create(['judul' => 'Energi untuk masyarakat', 'status' => 'dipublikasikan', 'public_visibility' => 'public']);
        KekayaanIntelektual::factory()->create(['judul' => 'Energi turbin', 'status' => 'dipublikasikan', 'public_visibility' => 'public']);
        $this->get('/search?q=Energi')->assertOk()->assertSee('Energi angin terbuka')->assertSee('Energi untuk masyarakat')->assertSee('Energi turbin')->assertDontSee('Energi angin rahasia')->assertDontSee('Energi angin draf');
        $this->get(route('repository.show', ['publikasi', $public->id]))->assertOk()->assertDontSee('INTERNAL SECRET')->assertDontSee('987654321');
        $this->get('/search?type=evil')->assertSessionHasErrors('type');
        $this->get('/search?q='.urlencode("' OR 1=1 --"))->assertOk()->assertDontSee('Energi angin terbuka');
    }

    public function test_public_filters_authors_programs_and_escapes_user_text(): void
    {
        $author = Penulis::factory()->dosen(Dosen::factory()->create()->id)->create(['nama' => '<script>alert(1)</script>']);
        $work = Publikasi::factory()->published()->create(['judul' => '<script>alert(2)</script>', 'tahun_publikasi' => 2025]);
        $work->penulis()->attach($author, ['urutan_penulis' => 1]);
        $this->get('/search?year=2025')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(2)</script>', false);
        $this->get(route('repository.author', $author))->assertOk()->assertSee($work->judul);
        $this->get(route('repository.journal', $work->jurnal))->assertOk()->assertSee($work->judul);
    }

    public function test_all_master_and_work_forms_render_and_reference_crud_is_validated(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        foreach (ReferenceCatalog::all() as $entity => $definition) {
            $this->get(route('admin.reference.index', $entity))->assertOk();
            $this->get(route('admin.reference.create', $entity))->assertOk();
        }
        foreach (array_keys(WorkType::TYPES) as $type) {
            $this->get(route('admin.works.create', $type))->assertOk();
        }
        $this->get('/admin/users/create')->assertOk();
        $this->get('/admin/import')->assertOk();
        $this->post('/admin/master/fakultas', ['kode' => 'FT', 'nama_fakultas' => 'Fakultas Teknologi', 'aktif' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fakultas', ['kode' => 'FT']);
        $this->post('/admin/master/fakultas', ['kode' => 'FT', 'nama_fakultas' => 'Duplikat', 'aktif' => 1])->assertSessionHasErrors('kode');
        $this->post('/admin/master/program-studi', ['kode' => 'TI', 'nama' => 'Informatika', 'fakultas_id' => 999, 'aktif' => 1])->assertSessionHasErrors('fakultas_id');
    }
}
