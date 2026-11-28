<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MfaEncryptionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_checks_decryptable_mfa_secret_without_exposing_value(): void
    {
        $user = User::factory()->admin()->create();
        $secret = app(Totp::class)->generateSecret();
        $user->forceFill(['mfa_secret' => $secret])->save();

        $this->artisan('repository:check-mfa-encryption')
            ->expectsOutput('Encrypted MFA records checked: 1')
            ->expectsOutput('MFA decryption failures: 0')
            ->assertSuccessful();

        $this->assertSame($secret, $user->fresh()->mfa_secret);
    }

    public function test_audit_fails_on_corrupted_ciphertext_without_changing_any_record(): void
    {
        $user = User::factory()->admin()->create();
        DB::table('users')->where('id', $user->id)->update(['mfa_secret' => 'invalid-ciphertext']);

        $this->artisan('repository:check-mfa-encryption')
            ->expectsOutput('Encrypted MFA records checked: 1')
            ->expectsOutput('MFA decryption failures: 1')
            ->assertFailed();

        $this->assertSame('invalid-ciphertext', DB::table('users')->where('id', $user->id)->value('mfa_secret'));
    }

    public function test_audit_succeeds_without_any_mfa_records(): void
    {
        User::factory()->create();

        $this->artisan('repository:check-mfa-encryption')
            ->expectsOutput('Encrypted MFA records checked: 0')
            ->expectsOutput('MFA decryption failures: 0')
            ->assertSuccessful();
    }
}
