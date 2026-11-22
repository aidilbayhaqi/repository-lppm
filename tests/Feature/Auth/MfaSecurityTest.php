<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MfaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_privileged_login_requires_second_factor_when_enabled(): void
    {
        config(['repository.mfa_enabled' => true]);
        $admin = User::factory()->admin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'))
            ->assertSessionMissing('auth.password_confirmed_at');

        $this->get('/admin/dashboard')->assertRedirect(route('mfa.challenge'));
        $this->getJson('/admin/dashboard')->assertForbidden();
        $this->get(route('mfa.challenge'))->assertOk()->assertSee('Keamanan dua faktor');
    }

    public function test_enrollment_requires_password_and_valid_totp_and_issues_one_time_recovery_codes(): void
    {
        config(['repository.mfa_enabled' => true]);
        $admin = User::factory()->admin()->create();
        $totp = app(Totp::class);
        $this->actingAs($admin)->get(route('mfa.challenge'))->assertOk();
        $secret = session('security.mfa_setup_secret');
        $this->assertIsString($secret);

        $this->post(route('mfa.enroll'), [
            'current_password' => 'incorrect', 'code' => $totp->codeForStep($secret, intdiv(time(), 30)),
        ])->assertSessionHasErrors('current_password');
        $this->assertNull($admin->fresh()->mfa_secret);

        $this->post(route('mfa.enroll'), [
            'current_password' => 'password', 'code' => $totp->codeForStep($secret, intdiv(time(), 30)),
        ])->assertRedirect(route('mfa.recovery'))->assertSessionHasNoErrors();
        $this->assertSame($secret, $admin->fresh()->mfa_secret);
        $this->assertCount(8, $admin->fresh()->mfa_recovery_codes);
        $this->assertTrue(session('security.mfa_verified'));

        $codes = session('mfa_recovery_display');
        $this->assertIsArray($codes);
        $this->assertTrue(Hash::check($codes[0], $admin->fresh()->mfa_recovery_codes[0]));
        $this->get(route('mfa.recovery'))->assertOk()->assertSee($codes[0]);
        $this->get(route('mfa.recovery'))->assertNotFound();
    }

    public function test_totp_replay_is_rejected_and_recovery_code_is_consumed_once(): void
    {
        config(['repository.mfa_enabled' => true]);
        $admin = User::factory()->admin()->create();
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $recovery = 'ABCD1234EFGH5678';
        $admin->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => [Hash::make($recovery)],
        ])->save();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $code = $totp->codeForStep($secret, intdiv(time(), 30));
        $this->post(route('mfa.verify'), ['code' => $code])->assertSessionHasNoErrors();
        $this->assertTrue(session('security.mfa_verified'));
        $this->post('/logout')->assertRedirect('/');

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->post(route('mfa.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->post(route('mfa.verify'), ['code' => $recovery])->assertSessionHasNoErrors();
        $this->assertCount(0, $admin->fresh()->mfa_recovery_codes);

        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->post(route('mfa.verify'), ['code' => $recovery])->assertSessionHasErrors('code');
    }

    public function test_mandatory_mfa_cannot_be_disabled_by_privileged_user(): void
    {
        config(['repository.mfa_enabled' => true]);
        $admin = User::factory()->admin()->create();
        $secret = app(Totp::class)->generateSecret();
        $admin->forceFill(['mfa_secret' => $secret])->save();
        $this->actingAs($admin)->withSession(['security.mfa_verified' => true])
            ->post(route('mfa.disable'), [
                'current_password' => 'password',
                'code' => app(Totp::class)->codeForStep($secret, intdiv(time(), 30)),
            ])->assertForbidden();
        $this->assertNotNull($admin->fresh()->mfa_secret);
    }

    public function test_sensitive_admin_pages_require_fresh_password_confirmation(): void
    {
        config(['repository.mfa_enabled' => false]);
        $admin = User::factory()->admin()->create();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/dashboard')
            ->assertSessionMissing('auth.password_confirmed_at');
        $this->get(route('admin.users.index'))->assertRedirect(route('password.confirm'));
        $this->post(route('password.confirm'), ['password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->get(route('admin.users.index'))->assertOk();
        $this->withSession(['auth.password_confirmed_at' => now()->subMinutes(6)->timestamp])
            ->get(route('admin.users.index'))->assertRedirect(route('password.confirm'));
    }

    public function test_revoke_other_sessions_requires_password_and_keeps_only_current_browser(): void
    {
        config(['repository.mfa_enabled' => false]);
        $user = User::factory()->create(['role' => 'mahasiswa']);
        $original = \App\Support\SessionState::attributes($user);
        $this->actingAs($user)->post(route('profile.sessions.revoke'), ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame(0, $user->fresh()->auth_version);
        $this->post(route('profile.sessions.revoke'), ['current_password' => 'password'])
            ->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->get(route('profile.edit'))->assertOk();
        $this->actingAs($user->fresh())->withSession($original)
            ->getJson('/admin/dashboard')->assertUnauthorized();
    }

    public function test_non_privileged_login_is_not_forced_through_mfa(): void
    {
        config(['repository.mfa_enabled' => true]);
        $student = User::factory()->create(['role' => 'mahasiswa']);
        $this->post('/login', ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->get(route('admin.dashboard'))->assertOk();
    }
}
