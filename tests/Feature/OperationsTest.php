<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Publikasi;
use App\Models\PublishAttempt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 6);
        $this->assertDatabaseCount('publikasi', 6);
        $this->assertNotNull(User::where('role', 'dosen')->first()->penulis);
    }

    public function test_production_seed_does_not_create_demo_accounts(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('publikasi', 0);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_admin_bootstrap_creates_a_unique_account_without_leaking_password_to_audit(): void
    {
        $this->artisan('repository:create-admin', ['--email' => 'bootstrap@example.test', '--name' => 'Admin Bootstrap'])
            ->expectsQuestion('Password admin (minimal 12 karakter)', 'BootstrapPassword!2026')
            ->expectsQuestion('Ulangi password', 'BootstrapPassword!2026')->assertSuccessful();
        $user = User::where('email', 'bootstrap@example.test')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertStringNotContainsString('BootstrapPassword', AuditLog::first()->toJson());
    }

    public function test_last_active_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete('/profile', ['password' => 'password'])->assertSessionHasErrors('account');
        $this->assertModelExists($admin);
    }

    public function test_recovery_fails_legacy_stale_attempt_without_publishing(): void
    {
        $this->freezeTime();
        $work = Publikasi::factory()->draft()->create(['repository_status' => 'publishing']);
        $attempt = PublishAttempt::create([
            'publikasi_id' => $work->id,
            'attempt_number' => 1,
            'status' => 'queued',
            'created_at' => now()->subMinutes(11),
        ]);

        $this->artisan('repository:recover-publishing')->assertSuccessful();

        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertSame('PUBLISH_INTERRUPTED', $attempt->fresh()->error_code);
        $this->assertSame('publish_failed', $work->fresh()->repository_status);
        $this->assertNull($work->fresh()->repository_published_at);
    }

    public function test_recovery_does_not_interrupt_recent_processing_attempt(): void
    {
        $this->freezeTime();
        $work = Publikasi::factory()->draft()->create(['repository_status' => 'publishing']);
        $attempt = PublishAttempt::create([
            'publikasi_id' => $work->id,
            'attempt_number' => 1,
            'status' => 'processing',
            'started_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(11),
        ]);

        $this->artisan('repository:recover-publishing')->assertSuccessful();
        $this->assertSame('processing', $attempt->fresh()->status);
        $this->assertSame('publishing', $work->fresh()->repository_status);
    }

    public function test_document_removal_revokes_access_without_erasing_retained_bytes(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $work = Publikasi::factory()->draft()->create(['created_by' => $user->id]);
        Storage::disk('local')->put('documents/retained.pdf', '%PDF-1.4 retained');
        $doc = $work->dokumen()->create(['nama_file' => 'retained.pdf', 'path_file' => 'documents/retained.pdf']);
        $this->actingAs($user)->delete(route('admin.documents.destroy', $doc), ['lock_version' => $work->fresh()->lock_version])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($doc);
        Storage::disk('local')->assertExists('documents/retained.pdf');
        $this->get(route('documents.download', $doc))->assertNotFound();
    }

    public function test_used_master_data_cannot_be_deleted_and_lost_relations_are_avoided(): void
    {
        $user = User::factory()->admin()->create();
        $work = Publikasi::factory()->draft()->create();
        $this->actingAs($user)->delete(route('admin.reference.destroy', ['jurnal', $work->jurnal_id]), ['lock_version' => 0])->assertSessionHasErrors('delete');
        $this->assertModelExists($work->jurnal);
    }

    public function test_append_only_audit_cannot_be_modified_through_models(): void
    {
        $user = User::factory()->create();
        $audit = AuditLog::record($user, $user, 'test_event');
        $this->expectException(\LogicException::class);
        $audit->update(['action' => 'tampered']);
    }

    public function test_health_check_returns_only_safe_status(): void
    {
        // A container test must not depend on the local document volume
        // already existing or being writable in the image.
        Storage::fake(config('repository.documents_disk'));

        $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }
}
