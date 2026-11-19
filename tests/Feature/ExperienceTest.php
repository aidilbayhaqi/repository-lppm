<?php

namespace Tests\Feature;

use App\Models\Publikasi;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_charts_only_count_accessible_works_and_include_empty_years(): void
    {
        $owner = User::factory()->dosen()->create();
        Publikasi::factory()->draft()->create(['created_by' => $owner->id, 'tahun_publikasi' => now()->year]);
        Publikasi::factory()->draft()->create(['created_by' => User::factory()->create()->id, 'tahun_publikasi' => now()->year, 'judul' => 'Karya rahasia akun lain']);
        $response = $this->actingAs($owner)->get(route('admin.dashboard'));
        $response->assertOk()->assertDontSee('Karya rahasia akun lain')->assertViewHas('stats', fn ($stats) => $stats['Jurnal'] === 1);
        $years = $response->viewData('yearly');
        $this->assertCount(6, $years);
        $this->assertSame(1, $years->last()->total);
        $this->assertSame(0, $years->first()->total);
    }

    public function test_status_endpoint_does_not_disclose_another_users_work(): void
    {
        $owner = User::factory()->dosen()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $owner->id]);
        $url = route('admin.works.status', ['publikasi', $work->id]);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->dosen()->create())->getJson($url)->assertForbidden();
        $this->actingAs($owner)->getJson($url)->assertOk()->assertExactJson(['status' => 'draft', 'version' => $work->fresh()->lock_version]);
    }

    public function test_background_polling_does_not_extend_idle_session(): void
    {
        // The idle expiry is defined by config, not hard-coded by middleware.
        config(['session.lifetime' => 30]);
        $owner = User::factory()->dosen()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $owner->id]);
        $last = now()->subMinutes(5)->timestamp;
        $this->actingAs($owner)->withSession(['security.last_activity' => $last])
            ->getJson(route('admin.works.status', ['publikasi', $work->id]))->assertOk()->assertSessionHas('security.last_activity', $last);
        $this->withSession(['security.last_activity' => now()->subMinutes(31)->timestamp])
            ->getJson(route('admin.works.status', ['publikasi', $work->id]))->assertUnauthorized();
    }

    public function test_validation_summary_escapes_text_and_uses_readable_labels(): void
    {
        app()->setLocale('id');
        $user = User::factory()->create();
        $this->actingAs($user)->from('/admin/karya/publikasi/create')->post('/admin/karya/publikasi', ['judul' => '', 'public_visibility' => 'private'])
            ->assertSessionHasErrors(['judul' => 'Judul karya wajib diisi.']);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['judul' => '<script>alert(1)</script>']));
        $this->view('partials.feedback', ['errors' => $errors])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_production_error_page_does_not_expose_exception_details(): void
    {
        config(['app.debug' => false]);
        $this->get('/halaman-tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan')->assertDontSee('Stack trace');
    }

    public function test_mutation_limiter_is_bounded_and_reads_are_not_counted(): void
    {
        $user = User::factory()->create();
        $limiter = RateLimiter::limiter('mutations');
        $request = Request::create('/admin/karya/publikasi', 'POST');
        $request->setUserResolver(fn () => $user);
        $this->assertSame(60, $limiter($request)->maxAttempts);
        $this->assertSame((string) $user->id, $limiter($request)->key);
        $read = Request::create('/admin/dashboard', 'GET');
        $this->assertInstanceOf(Unlimited::class, $limiter($read));
    }
}
