<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionCheck extends Command
{
    protected $signature = 'repository:production-check {--skip-services : Validate configuration without contacting services}';

    protected $description = 'Check production configuration; exits nonzero when a requirement is missing';

    public function handle(): int
    {
        $disk = config('repository.documents_disk');

        // Validate effective runtime config without ever printing secret values.
        $db = config('database.connections.mysql', []);
        $dbUrl = filled($db['url'] ?? null) ? parse_url((string) $db['url']) : null;
        $dbUser = is_array($dbUrl) ? ($dbUrl['user'] ?? null) : ($db['username'] ?? null);
        $dbPassword = is_array($dbUrl) ? ($dbUrl['pass'] ?? null) : ($db['password'] ?? null);
        $redisConnections = [
            config('database.redis.default', []),
            config('database.redis.cache', []),
        ];

        $checks = [
            'APP_ENV=production dan APP_DEBUG=false' => app()->environment('production') && ! config('app.debug'),
            'APP_KEY valid 32 byte' => $this->hasValidKey(config('app.key')),
            'Database MySQL terpisah dari akun root' => config('database.default') === 'mysql' && is_string($dbUser) && $dbUser !== '' && strtolower($dbUser) !== 'root',
            'Database password nonkosong' => is_string($dbPassword) && $dbPassword !== '',
            'Redis password terpasang' => collect($redisConnections)->every(function ($connection) {
                if (! is_array($connection)) {
                    return false;
                }
                if (filled($connection['url'] ?? null)) {
                    $parsed = parse_url((string) $connection['url']);

                    return is_array($parsed) && filled($parsed['pass'] ?? null);
                }

                return filled($connection['password'] ?? null);
            }),
            'APP_URL HTTPS' => str_starts_with(config('app.url'), 'https://'),
            'Cookie Secure, HttpOnly, SameSite=Lax/Strict' => config('session.secure') && config('session.http_only') && in_array(config('session.same_site'), ['lax', 'strict']),
            'Payload session terenkripsi' => (bool) config('session.encrypt'),
            'Idle session 1–30 menit; absolute 1–480 menit' => config('session.lifetime') >= 1 && config('session.lifetime') <= 30 && config('repository.session_absolute_minutes') >= 1 && config('repository.session_absolute_minutes') <= 480,
            'Session dan cache Redis bersama' => config('session.driver') === 'redis' && config('cache.default') === 'redis',
            'Session blocking Redis aktif' => config('session.block') && config('session.block_store') === 'redis',
            // Production Compose worker explicitly runs queue:work database;
            // a different QUEUE_CONNECTION would strand publication/email jobs.
            'Queue database sesuai worker Compose' => config('queue.default') === 'database',
            'Queue retry_after lebih besar dari worker timeout 60 detik' => (int) config('queue.connections.database.retry_after', 0) > 60,
            // The security monitor reads failed_jobs; null/file drivers break triage.
            'Failed jobs disimpan di database' => config('queue.failed.driver') === 'database-uuids',
            'Logging production tidak dinonaktifkan' => filled(config('logging.default'))
                && config('logging.default') !== 'null'
                && array_key_exists((string) config('logging.default'), config('logging.channels', [])),
            'Email menggunakan SMTP/provider' => ! in_array(config('mail.default'), ['log', 'array']),
            'Alamat pengirim dan host SMTP terisi' => filled(config('mail.from.address')) && (config('mail.default') !== 'smtp' || filled(config('mail.mailers.smtp.host'))),
            'Verifikasi email aktif' => (bool) config('repository.require_verified_email'),
            'MFA wajib untuk peran istimewa' => (bool) config('repository.mfa_enabled') && count(array_intersect(['admin', 'operator', 'verifier'], config('repository.mfa_roles', []))) === 3,
            'Pemindaian dokumen wajib' => (bool) config('repository.scan_required'),
            'Disk dokumen privat' => config('filesystems.disks.'.$disk.'.driver') === 'local' && ! str_starts_with(rtrim((string) config('filesystems.disks.'.$disk.'.root'), '/').'/', public_path().'/') && $disk !== 'public' && ! config('filesystems.disks.'.$disk.'.serve') && config('filesystems.disks.'.$disk.'.visibility') !== 'public',
            'Trusted proxy tidak menggunakan wildcard' => ! in_array('*', config('repository.trusted_proxies'), true),
        ];
        if (! $this->option('skip-services')) {
            try {
                DB::select('select 1');
                $checks['Migrasi hardening terpasang'] = Schema::hasColumn('users', 'auth_version') && Schema::hasColumn('dokumen', 'scan_status');
                $checks['Migrasi form dan referensi 1.6 terpasang'] = Schema::hasColumns('publikasi', ['isbn', 'issn_cetak', 'issn_elektronik']) && Schema::hasColumn('penulis', 'entry_key') && Schema::hasColumn('jurnal', 'entry_key') && Schema::hasColumn('penerbit', 'entry_key');
                $key = 'repository:preflight:'.bin2hex(random_bytes(8));
                Cache::put($key, true, 10);
                $checks['Cache dapat ditulis dan dibaca'] = Cache::get($key) === true;
                Cache::forget($key);
                $checks['Penyimpanan privat dapat ditulis'] = config('filesystems.disks.'.$disk.'.driver') === 'local' && is_writable(config('filesystems.disks.'.$disk.'.root'));
                $socket = @stream_socket_client('tcp://'.config('repository.clamav_host').':'.config('repository.clamav_port'), $errno, $error, 3);
                $checks['ClamAV tersedia'] = false;
                if ($socket) {
                    stream_set_timeout($socket, 3);
                    fwrite($socket, "zPING\0");
                    $checks['ClamAV tersedia'] = trim((string) stream_get_line($socket, 128, "\0")) === 'PONG';
                    fclose($socket);
                }
            } catch (Throwable $exception) {
                $checks['Koneksi layanan'] = false;
                report($exception);
            }
        }
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? '[OK] ' : '[FAIL] ').$label);
        }
        $this->comment('Pemeriksaan ini tidak menggantikan uji SMTP, restore backup, load test, dan review akses server.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function hasValidKey(mixed $key): bool
    {
        if (! is_string($key)) {
            return false;
        }
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return $decoded !== false && strlen($decoded) === 32;
        }

        return strlen($key) === 32;
    }
}
