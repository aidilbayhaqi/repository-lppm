<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'repository.documents_disk' => 'local',
            'repository.scan_required' => false,
            'repository.monitor_max_queue_wait_minutes' => 5,
            'repository.monitor_max_failed_jobs' => 0,
            'repository.monitor_min_free_disk_percent' => 1,
            'queue.default' => 'database',
        ]);
    }

    public function test_healthy_database_cache_storage_and_queue_pass_monitoring(): void
    {
        $this->artisan('repository:monitor-security')
            ->expectsOutput('[OK] database')
            ->expectsOutput('[OK] cache')
            ->expectsOutput('[OK] storage')
            ->expectsOutput('[OK] queue')
            ->expectsOutput('[OK] failed_jobs')
            ->expectsOutput('[OK] scanner')
            ->assertSuccessful();
    }

    public function test_stale_unreserved_queue_job_triggers_failure_without_reprocessing(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subMinutes(10)->timestamp,
            'created_at' => now()->subMinutes(10)->timestamp,
        ]);

        Log::spy();
        $this->artisan('repository:monitor-security')
            ->expectsOutput('[FAIL] queue')
            ->assertFailed();
        $this->assertDatabaseCount('jobs', 1);

        Log::shouldHaveReceived('critical')->once()->withArgs(function ($message, $context) {
            return $message === 'security.monitor_degraded'
                && in_array('queue', $context['checks'] ?? [], true)
                && ! array_key_exists('payload', $context)
                && ! array_key_exists('exception', $context);
        });
    }

    public function test_failed_jobs_trigger_failure_and_are_not_deleted(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'synthetic test failure',
            'failed_at' => now(),
        ]);

        $this->artisan('repository:monitor-security')
            ->expectsOutput('[FAIL] failed_jobs')
            ->assertFailed();

        $this->assertDatabaseCount('failed_jobs', 1);
    }

    public function test_cache_outage_fails_closed_without_disclosing_connection_details(): void
    {
        Cache::shouldReceive('put')->once()->andReturn(false);
        Log::spy();

        $this->artisan('repository:monitor-security')
            ->expectsOutput('[FAIL] cache')
            ->assertFailed();

        Log::shouldHaveReceived('critical')->once()->withArgs(function ($message, $context) {
            return $message === 'security.monitor_degraded'
                && ($context['checks'] ?? []) === ['cache'];
        });
    }

    public function test_missing_scanner_fails_only_when_scan_is_required(): void
    {
        config([
            'repository.scan_required' => true,
            'repository.clamav_host' => '127.0.0.1',
            'repository.clamav_port' => 1,
        ]);

        $this->artisan('repository:monitor-security')
            ->expectsOutput('[FAIL] scanner')
            ->assertFailed();
    }
}
