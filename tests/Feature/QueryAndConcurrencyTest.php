<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\Penulis;
use App\Models\Publikasi;
use App\Models\User;
use App\Services\SearchService;
use App\Support\LookupOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueryAndConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_master_edits_and_deletions_do_not_overwrite_current_data(): void
    {
        $admin = User::factory()->admin()->create();
        $faculty = Fakultas::factory()->create();
        $data = ['kode' => $faculty->kode, 'nama_fakultas' => 'Nama pertama', 'aktif' => 1, 'lock_version' => 0];
        $url = route('admin.reference.update', ['fakultas', $faculty->id]);
        $this->actingAs($admin)->put($url, $data)->assertSessionHasNoErrors();
        $this->put($url, [...$data, 'nama_fakultas' => 'Nama tertimpa'])->assertSessionHasErrors('lock_version');
        $this->delete(route('admin.reference.destroy', ['fakultas', $faculty->id]), ['lock_version' => 0])->assertSessionHasErrors('lock_version');
        $this->assertSame('Nama pertama', $faculty->fresh()->nama_fakultas);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_stale_user_edits_do_not_restore_old_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $payload = ['nama' => $target->nama, 'email' => $target->email, 'role' => 'verifier', 'is_active' => 1, 'lock_version' => 0];
        $url = route('admin.users.update', $target);
        $this->actingAs($admin)->put($url, $payload)->assertSessionHasNoErrors();
        $this->put($url, [...$payload, 'role' => 'admin'])->assertSessionHasErrors('lock_version');
        $this->assertSame('verifier', $target->fresh()->role);
        $this->assertSame(1, $target->fresh()->auth_version);
    }

    public function test_lookup_is_bounded_and_only_exposes_public_labels(): void
    {
        Penulis::factory()->count(60)->create();
        $this->getJson(route('lookups', 'penulis'))->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson(route('lookups', 'penulis'))
            ->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('has_more', true)
            ->assertJsonStructure(['data' => [['id', 'label']], 'has_more']);
        $this->assertSame(['id', 'label'], array_keys($this->getJson(route('lookups', 'penulis'))->json('data.0')));
        $this->getJson(route('lookups', ['entity' => 'penulis', 'available_for' => 1]))->assertForbidden();
        $this->getJson(route('lookups', 'users'))->assertNotFound();
    }

    public function test_lookup_escapes_wildcards_and_preserves_selected_records_beyond_first_page(): void
    {
        Penulis::factory()->count(35)->create(['nama' => 'Alpha']);
        $selected = Penulis::factory()->create(['nama' => 'Zeta Selected']);
        $options = LookupOptions::initial(Penulis::query(), 'nama', [$selected->id]);
        $this->assertCount(26, $options);
        $this->assertTrue($options->contains('id', $selected->id));
        $this->actingAs(User::factory()->create())->getJson(route('lookups', ['entity' => 'penulis', 'q' => '%']))->assertJsonCount(0, 'data');
        $this->getJson(route('lookups', ['entity' => 'penulis', 'q' => 'Zeta']))->assertJsonPath('data.0.id', $selected->id);
    }

    public function test_public_search_excludes_private_columns_and_has_bounded_query_count(): void
    {
        $public = Publikasi::factory()->count(30)->create(['repository_status' => 'published', 'public_visibility' => 'public']);
        Publikasi::factory()->draft()->create(['judul' => 'Rahasia privat']);
        DB::enableQueryLog();
        $results = app(SearchService::class)->publicResults(['type' => 'publikasi']);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(12, $results);
        $this->assertSame(30, $results->total());
        $this->assertLessThanOrEqual(5, count($queries));
        $this->assertArrayNotHasKey('keterangan', $results->first()->getAttributes());
        $this->assertArrayNotHasKey('abstrak', $results->first()->getAttributes());
    }

    public function test_unknown_nested_work_fields_are_rejected(): void
    {
        $author = Penulis::factory()->create();
        $this->actingAs(User::factory()->create())->post('/admin/karya/publikasi', [
            'judul' => 'Test', 'public_visibility' => 'private',
            'authors' => [['id' => $author->id, 'user_id' => 999]],
        ])->assertSessionHasErrors('authors.0');
        $this->assertDatabaseCount('publikasi', 0);
    }
}
