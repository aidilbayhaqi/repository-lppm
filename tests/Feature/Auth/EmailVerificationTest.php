<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user, ?string $hash = null, int $minutes = 60): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($minutes),
            ['id' => $user->id, 'hash' => $hash ?? sha1($user->email)]
        );
    }

    public function test_email_verification_notice_can_be_rendered_for_signed_in_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/verify-email')->assertOk();
    }

    public function test_guest_can_verify_then_sees_confirmation_before_login(): void
    {
        $user = User::factory()->unverified()->create();
        Event::fake();

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('verification.success'));

        $this->assertGuest();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatchedTimes(Verified::class, 1);

        $this->get(route('verification.success'))
            ->assertOk()
            ->assertSeeText('Email berhasil diverifikasi')
            ->assertSeeText('Lanjut ke halaman login')
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('href="'.route('dashboard').'"', false);

        $this->assertGuest();
        $this->get(route('login'))->assertOk();
    }

    public function test_success_page_cannot_be_faked_or_replayed_without_valid_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get(route('verification.success'))->assertRedirect(route('login'));
        $this->get($this->verificationUrl($user))->assertRedirect(route('verification.success'));
        $this->get(route('verification.success'))->assertOk();

        // Success confirmation is a one-time navigation, not a public claim.
        $this->get(route('verification.success'))->assertRedirect(route('login'));
    }

    public function test_invalid_email_hash_is_rejected_without_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user, sha1('wrong-email')))->assertForbidden();

        $this->assertGuest();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_signature_is_rejected_without_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user).'&unexpected=parameter')->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user, minutes: 1);

        $this->travel(2)->minutes();
        $this->get($url)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unapproved_account_cannot_be_verified_even_with_valid_link(): void
    {
        $user = User::factory()->unverified()->inactive()->create();

        $this->get($this->verificationUrl($user))->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_reuse_does_not_dispatch_verified_event_twice(): void
    {
        $user = User::factory()->unverified()->create();
        Event::fake();

        $url = $this->verificationUrl($user);

        $this->get($url)->assertRedirect(route('verification.success'));
        $this->get(route('verification.success'))->assertOk();
        $this->get($url)->assertRedirect(route('verification.success'));

        Event::assertDispatchedTimes(Verified::class, 1);
        $this->assertGuest();
    }

    public function test_email_link_cannot_switch_or_create_an_authenticated_session(): void
    {
        $owner = User::factory()->unverified()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get($this->verificationUrl($owner))
            ->assertRedirect(route('verification.success'));

        $this->assertAuthenticatedAs($otherUser);
        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
    }

    public function test_unknown_account_cannot_be_verified(): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => 9999999, 'hash' => sha1('unknown@example.test')]
        );

        $this->get($url)->assertNotFound();
    }
}
