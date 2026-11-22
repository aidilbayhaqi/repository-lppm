<?php

namespace App\Services;

use InvalidArgumentException;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        $raw = random_bytes(20);
        $binary = '';
        foreach (str_split($raw) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 5) as $chunk) {
            $result .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $result;
    }

    public function provisioningUri(string $secret, string $email): string
    {
        $issuer = 'OSDJ LPPM';

        return 'otpauth://totp/'.rawurlencode($issuer.':'.$email)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    public function verifyStep(string $secret, string $code, ?int $now = null): ?int
    {
        if (! preg_match('/^\\d{6}$/D', $code)) {
            return null;
        }

        $step = intdiv($now ?? time(), 30);
        foreach ([$step - 1, $step, $step + 1] as $candidate) {
            if ($candidate >= 0 && hash_equals($this->codeForStep($secret, $candidate), $code)) {
                return $candidate;
            }
        }

        return null;
    }

    public function codeForStep(string $secret, int $step): string
    {
        $binary = $this->decode($secret);
        $counter = pack('N2', intdiv($step, 4294967296), $step % 4294967296);
        $hash = hash_hmac('sha1', $counter, $binary, true);
        $offset = ord($hash[19]) & 0x0F;
        $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function decode(string $secret): string
    {
        if ($secret === '' || preg_match('/[^A-Z2-7]/', $secret)) {
            throw new InvalidArgumentException('Invalid TOTP secret.');
        }

        $bits = '';
        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8) as $chunk) {
            $result .= chr(bindec($chunk));
        }

        return $result;
    }
}
