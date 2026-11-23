<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MonitorSecurity extends Command
{
    protected $signature = 'repository:monitor-security';

    protected $description = 'Read-only operational security probes; exits nonzero on degraded dependencies.';

    public function handle(): int
    {
        $checks = [];

        $this->probe($checks, 'database', static function (): bool {
            return count(DB::select('select 1')) === 1;
        });

        $this->probe($checks, 'cache', static function (): bool {
            $token = bin2hex(random_bytes(12));
            $key = 'repository:security-monitor:'.$token;
            if (Cache::put($key, $token, 10) !== true) {
                return false;
            }
            try {
                return Cache::get($key) === $token;
            } finally {
                Cache::forget($key);
            }
        });

        $this->probe($checks, 'storage', static function (): bool {
            $disk = config('repository.documents_disk');
            if (config('filesystems.disks.'.$disk.'.driver') !== 'local') {
                return false; // Custom disks need an explicit read/write monitor.
            }
            $path = Storage::disk($disk)->path('');
            if (! is_dir($path) || ! is_writable($path)) {
                return false;
            }
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            if (! is_numeric($total) || $total <= 0 || ! is_numeric($free)) {
                return false;
            }

            return ($free / $total) * 100 >= config('repository.monitor_min_free_disk_percent');
        });

        $this->probe($checks, 'queue', static function (): bool {
            if (config('queue.default') !== 'database') {
                return false; // Cannot silently pass an unmonitored queue driver.
            }
            $table = config('queue.connections.database.table', 'jobs');
            $threshold = now()->subMinutes(config('repository.monitor_max_queue_wait_minutes'))->timestamp;

            return ! DB::table($table)
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->timestamp)
                ->where('created_at', '<=', $threshold)
                ->exists();
        });

        $this->probe($checks, 'failed_jobs', static function (): bool {
            $table = config('queue.failed.table', 'failed_jobs');

            return DB::table($table)->count() <= config('repository.monitor_max_failed_jobs');
        });

        $this->probe($checks, 'scanner', static function (): bool {
            if (! config('repository.scan_required')) {
                return ! app()->environment('production');
            }
            $socket = @stream_socket_client(
                'tcp://'.config('repository.clamav_host').':'.config('repository.clamav_port'),
                $errno,
                $error,
                3
            );
            if (! $socket) {
                return false;
            }
            try {
                stream_set_timeout($socket, 3);

                return fwrite($socket, "zPING\0") !== false
                    && trim((string) stream_get_line($socket, 128, "\0")) === 'PONG';
            } finally {
                fclose($socket);
            }
        });

        foreach ($checks as $key => $passed) {
            $this->line(($passed ? '[OK] ' : '[FAIL] ').$key);
        }
        $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => ! $ok));
        if ($failed) {
            // Keep source credentials, hostnames, file paths and exception messages
            // out of log context. Alert routing/deduplication belongs to the collector.
            Log::critical('security.monitor_degraded', ['checks' => $failed]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function probe(array &$checks, string $name, callable $check): void
    {
        try {
            $checks[$name] = $check() === true;
        } catch (Throwable $exception) {
            $checks[$name] = false;
            Log::warning('security.monitor_probe_error', [
                'check' => $name,
                'type' => class_basename($exception),
            ]);
        }
    }
}
