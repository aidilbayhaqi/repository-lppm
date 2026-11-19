<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Jurnal;
use App\Models\Pengindeks;
use App\Models\Penulis;
use App\Models\Publikasi;
use App\Models\PublishAttempt;
use App\Models\TingkatPengindeksan;
use App\Models\User;
use App\Models\VerificationHistory;
use App\Services\SearchService;
use App\Support\AuditHistoryPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class JournalExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $overrides = []): array
    {
        return array_replace([
            'judul' => 'Artikel uji jurnal', 'public_visibility' => 'private', 'tahun_publikasi' => 2026,
            'authors' => [['id' => Penulis::factory()->create()->id]], 'publication_type' => 'artikel_jurnal',
            'journal_source' => 'manual', 'journal_name_manual' => 'Jurnal Riset Mandiri', 'indexing_mode' => 'manual',
            'sinta_rank' => 2, 'scopus_indexed' => 1, 'scopus_quartile' => 'Q3', 'indexing_year' => 2025,
        ], $overrides);
    }

    public function test_manual_metadata_creates_shared_recommendation_and_is_visible(): void
    {
        $user = User::factory()->create();
        $before = Jurnal::count();
        $this->actingAs($user)->post('/admin/karya/publikasi', $this->data())->assertSessionHasNoErrors()->assertRedirect();
        $work = Publikasi::firstOrFail();
        $this->assertSame($before + 1, Jurnal::count());
        $this->assertSame('Jurnal Riset Mandiri', $work->jurnal->nama_jurnal);
        $this->assertSame('draft', $work->repository_status);
        $this->assertSame(2, $work->sinta_rank);
        $this->assertSame('Q3', $work->scopus_quartile);
        $this->get('/admin/karya/publikasi/'.$work->id)->assertOk()->assertSee('Jurnal Riset Mandiri')->assertSee('SINTA 2')->assertSee('Scopus Q3');
    }

    public function test_reference_mode_derives_rank_and_clears_manual_journal(): void
    {
        $journal = Jurnal::factory()->create();
        $indices = [['id' => Pengindeks::where('nama', 'SINTA')->value('id'), 'tingkat_pengindeksan_id' => TingkatPengindeksan::where('kode', 'S4')->value('id')]];
        $this->actingAs(User::factory()->create())->post('/admin/karya/publikasi', $this->data(['journal_source' => 'reference', 'jurnal_id' => $journal->id, 'indexing_mode' => 'reference', 'indexers' => $indices]))->assertSessionHasNoErrors();
        $work = Publikasi::firstOrFail();
        $this->assertSame($journal->id, $work->jurnal_id);
        $this->assertNull($work->journal_name_manual);
        $this->assertSame(4, $work->sinta_rank);
        $this->assertFalse($work->scopus_indexed);
        $this->assertNull($work->scopus_quartile);
    }

    public function test_invalid_manual_rank_and_incompatible_reference_rank_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/admin/karya/publikasi', $this->data(['sinta_rank' => 7, 'scopus_quartile' => 'Q5']))->assertSessionHasErrors(['sinta_rank', 'scopus_quartile']);
        $this->post('/admin/karya/publikasi', $this->data(['indexing_mode' => 'reference', 'indexers' => [['id' => Pengindeks::where('nama', 'SINTA')->value('id'), 'tingkat_pengindeksan_id' => TingkatPengindeksan::where('kode', 'Q1')->value('id')]]]))->assertSessionHasErrors('indexers.0.tingkat_pengindeksan_id');
        $this->assertDatabaseCount('publikasi', 0);
    }

    public function test_switching_to_manual_replaces_index_references_and_remains_version_locked(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/admin/karya/publikasi', $this->data())->assertSessionHasNoErrors();
        $work = Publikasi::firstOrFail();
        $work->pengindeks()->attach(Pengindeks::where('nama', 'Scopus')->value('id'));
        $payload = $this->data(['lock_version' => $work->lock_version, 'scopus_indexed' => 0, 'scopus_quartile' => null, 'sinta_rank' => 5]);
        $url = '/admin/karya/publikasi/'.$work->id;
        $this->put($url, $payload)->assertSessionHasNoErrors();
        $this->assertCount(0, $work->fresh()->pengindeks);
        $this->assertSame(5, $work->fresh()->sinta_rank);
        $this->put($url, $payload)->assertSessionHasErrors('lock_version');
    }

    public function test_combined_filters_keep_public_and_owner_scopes(): void
    {
        $owner = User::factory()->dosen()->create();
        $matching = Publikasi::factory()->create(['sinta_rank' => 2, 'scopus_quartile' => 'Q1', 'scopus_indexed' => true, 'publication_type' => 'artikel_jurnal', 'created_by' => $owner->id]);
        $matching->forceFill(['repository_status' => 'published', 'public_visibility' => 'public'])->save();
        Publikasi::factory()->draft()->create(['sinta_rank' => 2, 'scopus_quartile' => 'Q1', 'scopus_indexed' => true, 'publication_type' => 'artikel_jurnal', 'created_by' => User::factory()->create()->id]);
        Publikasi::factory()->create(['sinta_rank' => 3, 'scopus_quartile' => 'Q1', 'scopus_indexed' => true, 'publication_type' => 'artikel_jurnal']);
        $filters = ['sinta_rank' => 2, 'scopus_quartile' => 'Q1', 'publication_type' => 'artikel_jurnal'];
        $search = app(SearchService::class);
        $this->assertSame([$matching->id], $search->query('publikasi', $filters)->pluck('id')->all());
        $this->assertSame([$matching->id], $search->query('publikasi', $filters, $owner)->pluck('id')->all());
        $this->get('/search?'.http_build_query($filters))->assertOk()->assertViewHas('results', fn ($results) => $results->total() === 1)->assertSee('SINTA 2');
        $this->actingAs($owner)->get('/admin/karya/publikasi?'.http_build_query($filters))->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1);
    }

    public function test_audit_is_paginated_five_at_a_time_with_readable_status(): void
    {
        $user = User::factory()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $user->id]);
        for ($i = 0; $i < 7; $i++) {
            AuditLog::record($work, $user, 'published', ['status' => 'publishing'], ['status' => 'published']);
        }
        $this->actingAs($user)->get('/admin/karya/publikasi/'.$work->id)->assertOk()->assertViewHas('histories', fn ($rows) => $rows->count() === 5 && $rows->total() === 7)->assertSee('Status berubah dari')->assertSee('Sedang diterbitkan')->assertSee('Terbit');
        $this->get('/admin/karya/publikasi/'.$work->id.'?history_page=2')->assertOk()->assertViewHas('histories', fn ($rows) => $rows->count() === 2);
        for ($i = 1; $i <= 7; $i++) {
            PublishAttempt::create(['publikasi_id' => $work->id, 'attempt_number' => $i, 'status' => 'success']);
            VerificationHistory::create(['publikasi_id' => $work->id, 'verifier_id' => $user->id, 'status' => 'approved', 'created_at' => now()]);
        }
        $this->get('/admin/karya/publikasi/'.$work->id)
            ->assertViewHas('attempts', fn ($rows) => $rows->count() === 5 && $rows->total() === 7)
            ->assertViewHas('verifications', fn ($rows) => $rows->count() === 5 && $rows->total() === 7)
            ->assertSee('Verifikasi disetujui');
        $this->get('/admin/karya/publikasi/'.$work->id.'?attempt_page=2&verification_page=2')
            ->assertViewHas('attempts', fn ($rows) => $rows->count() === 2)
            ->assertViewHas('verifications', fn ($rows) => $rows->count() === 2);
        $history = AuditLog::latest('id')->first();
        $this->assertSame('Terbit', AuditHistoryPresenter::changes($history)[0]['after']);
    }

    public function test_remember_cookie_is_opt_in_and_server_limited(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 1])->assertRedirect('/dashboard')->assertCookie(Auth::guard()->getRecallerName());
        $this->assertTrue($user->fresh()->remember_expires_at->between(now()->addDays(13), now()->addDays(15)));
    }

    public function test_recalled_session_can_resume_without_password_confirmation(): void
    {
        $user = User::factory()->create(['remember_token' => 'a-valid-test-token']);
        $user->forceFill(['remember_expires_at' => now()->addDay()])->save();
        $name = Auth::guard()->getRecallerName();
        $this->withCookie($name, $user->id.'|'.$user->remember_token.'|'.$user->password)->get('/admin/dashboard')->assertOk()->assertSessionHas('security.user_id', $user->id)->assertSessionMissing('auth.password_confirmed_at');
    }

    public function test_expired_remember_token_cannot_resume(): void
    {
        $user = User::factory()->create(['remember_token' => 'an-expired-test-token']);
        $user->forceFill(['remember_expires_at' => now()->subMinute()])->save();
        $this->withCookie(Auth::guard()->getRecallerName(), $user->id.'|'.$user->remember_token.'|'.$user->password)->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_sensitive_account_change_revokes_remember_token(): void
    {
        $user = User::factory()->create(['remember_token' => 'previous-token']);
        $user->forceFill(['remember_expires_at' => now()->addDay()])->save();
        $cookie = $user->id.'|'.$user->remember_token.'|'.$user->password;
        $user->update(['password' => 'a-new-strong-password']);
        $this->withCookie(Auth::guard()->getRecallerName(), $cookie)->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_upgrade_backfills_only_recognized_legacy_index_labels(): void
    {
        $work = Publikasi::factory()->draft()->create();
        $work->pengindeks()->attach(Pengindeks::where('nama', 'SINTA')->value('id'), ['tingkat_pengindeksan_id' => TingkatPengindeksan::where('kode', 'S3')->value('id')]);
        $migration = require database_path('migrations/2026_11_10_153540_add_journal_discovery_fields.php');
        $migration->down();
        $migration->up();
        $this->assertSame(3, $work->fresh()->sinta_rank);
        $this->assertSame('draft', $work->fresh()->repository_status);
        $this->assertNull($work->fresh()->publication_type);
    }
}
