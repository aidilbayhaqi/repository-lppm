<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

/**
 * Read-only compatibility check for persisted encrypted MFA secrets.
 * Never outputs plaintext, user identities, ciphertext, or exception text.
 */
class CheckMfaEncryption extends Command
{
    protected $signature = 'repository:check-mfa-encryption';

    protected $description = 'Check whether persisted TOTP secrets decrypt with the current key configuration.';

    public function handle(): int
    {
        $checked = 0;
        $failed = 0;

        User::query()
            ->whereNotNull('mfa_secret')
            ->select(['id', 'mfa_secret'])
            ->chunkById(100, function ($users) use (&$checked, &$failed): void {
                foreach ($users as $user) {
                    $checked++;
                    try {
                        $value = $user->mfa_secret;
                        if (! is_string($value) || $value === '') {
                            $failed++;
                        }
                        unset($value);
                    } catch (Throwable) {
                        // Decryption exceptions can include sensitive context.
                        $failed++;
                    }
                }
            });

        $this->line('Encrypted MFA records checked: '.$checked);
        $this->line('MFA decryption failures: '.$failed);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
