<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\Publikasi;
use App\Models\User;
use App\Services\MalwareScanner;
use App\Support\SessionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_replaces_the_session_and_does_not_issue_a_remember_cookie(): void
    {
        $user = User::factory()->create();
        $this->withSession(['pre_login' => true]);
        $before = session()->getId();
        $response = $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'password']);
        $response->assertRedirect('/dashboard')->assertSessionHas('security.user_id', $user->id);
        $this->assertNotSame($before, session()->getId());
        $this->assertSame($user->remember_token, $user->fresh()->remember_token);
        $response->assertCookieMissing(auth()->guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
    }

    public static function expiredSessions(): array
    {
        return [['security.last_activity', 31], ['security.started_at', 481]];
    }

    #[DataProvider('expiredSessions')]
    public function test_idle_and_absolute_timeouts_revoke_access(string $key, int $minutes): void
    {
        $user = User::factory()->create();
        config(['session.lifetime' => 30]);
        $this->actingAs($user)->withSession([$key => now()->subMinutes($minutes)->timestamp])->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_old_sessions_without_security_markers_cannot_resume(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['security.version' => null])->getJson('/admin/dashboard')->assertUnauthorized();
        $this->assertGuest();
    }

    public static function securityChanges(): array
    {
        return [['password', 'another-long-password'], ['role', 'verifier'], ['is_active', false], ['email', 'changed@example.test']];
    }

    #[DataProvider('securityChanges')]
    public function test_security_changes_revoke_other_browser_sessions(string $field, mixed $value): void
    {
        $user = User::factory()->create();
        $oldSession = SessionState::attributes($user);
        $user->update([$field => $value]);
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->actingAs($user->fresh())->withSession($oldSession)->getJson('/admin/dashboard')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_password_update_preserves_only_the_confirmed_browser_session(): void
    {
        $user = User::factory()->create();
        $oldSession = SessionState::attributes($user);
        $this->actingAs($user)->put('/password', ['current_password' => 'password', 'password' => 'a-strong-new-password', 'password_confirmation' => 'a-strong-new-password'])->assertSessionHasNoErrors();
        $this->get('/admin/dashboard')->assertOk();
        $this->assertTrue(Hash::check('a-strong-new-password', $user->fresh()->password));
        $this->actingAs($user->fresh())->withSession($oldSession)->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_profile_name_edit_does_not_extend_password_confirmation(): void
    {
        $user = User::factory()->admin()->create();
        $confirmed = now()->subMinutes(16)->timestamp;
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => $confirmed]);
        $this->patch('/profile', ['nama' => 'Nama Baru', 'email' => $user->email, 'lock_version' => 0])->assertSessionHasNoErrors()->assertSessionHas('auth.password_confirmed_at', $confirmed);
        $this->get('/admin/users')->assertRedirect('/confirm-password');
    }

    public function test_changing_email_requires_the_current_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', ['nama' => $user->nama, 'email' => 'new@example.test', 'lock_version' => 0])->assertSessionHasErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_registration_can_be_disabled_and_short_passwords_are_rejected(): void
    {
        $payload = ['nama' => 'Student', 'email' => 'student@example.test', 'password' => 'short', 'password_confirmation' => 'short'];
        $this->post('/register', $payload)->assertSessionHasErrors('password');
        config(['repository.registration_enabled' => false]);
        $this->get('/register')->assertNotFound();
        $this->post('/register', $payload)->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reset_response_does_not_disclose_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        $known = session('status');
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHasNoErrors()->assertSessionHas('status', $known);
    }

    public function test_login_throttles_password_spraying_across_accounts(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['email' => 'unknown'.$i.'@example.test', 'password' => 'bad'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'next@example.test', 'password' => 'bad'])->assertTooManyRequests();
    }

    public function test_html_has_csp_nonce_no_unsafe_eval_and_no_store(): void
    {
        $response = $this->get('/login')->assertOk()->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Cache-Control', 'no-store, private');
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        preg_match("/nonce-([^']+)/", $policy, $nonce);
        $response->assertSee('nonce="'.$nonce[1].'"', false);
    }

    public function test_security_scanner_failure_does_not_persist_a_document(): void
    {
        Storage::fake('local');
        config(['repository.scan_required' => true]);
        $this->mock(MalwareScanner::class)->shouldReceive('scan')->once()->andThrow(ValidationException::withMessages(['file' => 'Ditolak oleh scanner.']));
        $user = User::factory()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $user->id]);
        $this->actingAs($user)->post(route('admin.works.documents', ['publikasi', $work->id]), ['file' => UploadedFile::fake()->createWithContent('paper.pdf', '%PDF-1.4 example'), 'publik' => 1, 'jenis_dokumen' => 'dokumen_utama', 'lock_version' => 0])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('dokumen', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_only_scanned_documents_can_be_downloaded_when_scanning_is_required(): void
    {
        Storage::fake('local');
        config(['repository.scan_required' => true]);
        $this->mock(MalwareScanner::class)->shouldReceive('scan')->once();
        $user = User::factory()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $user->id]);
        $this->actingAs($user)->post(route('admin.works.documents', ['publikasi', $work->id]), ['file' => UploadedFile::fake()->createWithContent('paper.pdf', '%PDF-1.4 example'), 'publik' => 1, 'jenis_dokumen' => 'dokumen_utama', 'lock_version' => 0])->assertSessionHasNoErrors();
        $document = Dokumen::firstOrFail();
        $this->assertSame('clean', $document->scan_status);
        $this->get(route('documents.download', $document))->assertOk();
        $document->update(['scan_status' => 'unscanned']);
        $this->get(route('documents.download', $document))->assertNotFound();
    }

    public function test_missing_scanner_fails_closed(): void
    {
        config(['repository.clamav_host' => '127.0.0.1', 'repository.clamav_port' => 1, 'repository.clamav_timeout' => 1]);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, '%PDF-1.4 test');
        rewind($stream);
        $this->expectException(ValidationException::class);
        try {
            app(MalwareScanner::class)->scan($stream);
        } finally {
            fclose($stream);
        }
    }

    public function test_preflight_rejects_local_configuration_as_production(): void
    {
        $this->artisan('repository:production-check', ['--skip-services' => true])->assertFailed();
    }

    public function test_production_requests_reject_untrusted_hosts_and_missing_csrf(): void
    {
        $this->app['env'] = 'production';
        $this->get('http://attacker.example/login')->assertStatus(400);
        $this->withServerVariables(['HTTP_HOST' => 'localhost'])->post('/login', ['email' => 'user@example.test', 'password' => 'secret'])->assertStatus(419);
    }

    public function test_password_reset_revokes_existing_sessions(): void
    {
        $user = User::factory()->create();
        $oldSession = SessionState::attributes($user);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'long-reset-passphrase', 'password_confirmation' => 'long-reset-passphrase'])->assertSessionHasNoErrors();
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->actingAs($user->fresh())->withSession($oldSession)->get('/admin/dashboard')->assertRedirect('/login');
    }


    public function test_production_preflight_rejects_missing_database_and_redis_credentials(): void
    {
        $originalConnection = config('database.default');

        try {
            $this->app->instance('env', 'production');
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => '',
                'database.connections.mysql.url' => null,
                'database.redis.default.url' => null,
                'database.redis.cache.url' => null,
                'database.redis.default.password' => null,
                'database.redis.cache.password' => null,
            ]);

            $this->artisan('repository:production-check', ['--skip-services' => true])
                ->expectsOutput('[FAIL] Database MySQL terpisah dari akun root')
                ->expectsOutput('[FAIL] Database password nonkosong')
                ->expectsOutput('[FAIL] Redis password terpasang')
                ->assertFailed();

            config([
                'database.connections.mysql.username' => 'repository',
                'database.connections.mysql.password' => 'only-for-tests',
                'database.redis.default.password' => 'only-for-tests',
                'database.redis.cache.password' => 'only-for-tests',
            ]);

            $this->artisan('repository:production-check', ['--skip-services' => true])
                ->expectsOutput('[OK] Database MySQL terpisah dari akun root')
                ->expectsOutput('[OK] Database password nonkosong')
                ->expectsOutput('[OK] Redis password terpasang');
        } finally {
            // RefreshDatabase holds an SQLite transaction for the entire test.
            // Never let a config-only preflight test change the connection used
            // by tearDown and poison all later tests in the same process.
            config(['database.default' => $originalConnection]);
        }
    }

    public function test_production_preflight_rejects_invalid_app_key(): void
    {
        $this->app->instance('env', 'production');
        config(['app.key' => 'short-and-predictable']);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[FAIL] APP_KEY valid 32 byte')
            ->assertFailed();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[OK] APP_KEY valid 32 byte');
    }

    public function test_production_preflight_requires_privileged_mfa(): void
    {
        $this->app->instance('env', 'production');
        config(['repository.mfa_enabled' => false]);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[FAIL] MFA wajib untuk peran istimewa')
            ->assertFailed();

        config(['repository.mfa_enabled' => true, 'repository.mfa_roles' => ['admin', 'operator', 'verifier']]);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[OK] MFA wajib untuk peran istimewa');
    }
    public function test_production_preflight_rejects_queue_driver_mismatch(): void
    {
        $this->app->instance('env', 'production');
        config(['queue.default' => 'redis']);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[FAIL] Queue database sesuai worker Compose')
            ->assertFailed();

        config(['queue.default' => 'database']);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[OK] Queue database sesuai worker Compose');
    }

    public function test_production_preflight_rejects_too_short_queue_retry_after(): void
    {
        $this->app->instance('env', 'production');
        config(['queue.connections.database.retry_after' => 30]);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[FAIL] Queue retry_after lebih besar dari worker timeout 60 detik')
            ->assertFailed();

        config(['queue.connections.database.retry_after' => 90]);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[OK] Queue retry_after lebih besar dari worker timeout 60 detik');
    }

    public function test_production_preflight_rejects_disabled_failed_jobs_and_logs(): void
    {
        $this->app->instance('env', 'production');
        config([
            'queue.failed.driver' => 'null',
            'logging.default' => 'null',
        ]);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[FAIL] Failed jobs disimpan di database')
            ->expectsOutput('[FAIL] Logging production tidak dinonaktifkan')
            ->assertFailed();

        config([
            'queue.failed.driver' => 'database-uuids',
            'logging.default' => 'stderr',
        ]);

        $this->artisan('repository:production-check', ['--skip-services' => true])
            ->expectsOutput('[OK] Failed jobs disimpan di database')
            ->expectsOutput('[OK] Logging production tidak dinonaktifkan');
    }

}
