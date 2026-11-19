<?php

namespace Tests\Feature;

use App\Models\Penulis;
use App\Models\Publikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_verifier_cannot_edit_a_work_created_before_their_role_changed(): void
    {
        $user = User::factory()->create(['role' => 'verifier']);
        $work = Publikasi::factory()->draft()->create(['created_by' => $user->id]);
        $this->actingAs($user)->get(route('admin.works.show', ['publikasi', $work->id]))->assertOk();
        $this->get(route('admin.works.edit', ['publikasi', $work->id]))->assertForbidden();
    }

    public static function roles(): array
    {
        return [['admin', true, true, true], ['operator', true, false, false], ['verifier', false, false, true], ['dosen', false, false, false], ['mahasiswa', false, false, false], ['peneliti_eksternal', false, false, false]];
    }

    #[DataProvider('roles')]
    public function test_permission_matrix_respects_role_and_lifecycle(string $role, bool $staff, bool $manageUsers, bool $verify): void
    {
        $user = User::factory()->create(['role' => $role]);
        $work = Publikasi::factory()->draft()->create(['repository_status' => 'pending_verification']);
        $this->assertSame($staff, Gate::forUser($user)->allows('manage-reference'));
        $this->assertSame($manageUsers, Gate::forUser($user)->allows('manage-users'));
        $this->assertSame($verify, Gate::forUser($user)->allows('verify', $work));
        $this->assertFalse(Gate::forUser($user)->allows('update', $work));
        $this->assertFalse(Gate::forUser($user)->allows('publish', $work));
    }

    public function test_guest_contributor_and_inactive_account_boundaries(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $user = User::factory()->create(['role' => 'mahasiswa']);
        $this->actingAs($user)->get('/admin/dashboard')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/master/fakultas')->assertForbidden();
        $user->update(['is_active' => false]);
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_different_contributors_cannot_read_or_modify_each_others_drafts(): void
    {
        $owner = User::factory()->create(['role' => 'dosen']);
        $other = User::factory()->create(['role' => 'dosen']);
        $work = Publikasi::factory()->draft()->create(['judul' => 'Confidential manuscript', 'created_by' => $owner->id]);
        $this->actingAs($other)->get(route('admin.works.show', ['publikasi', $work->id]))->assertForbidden();
        $this->get(route('admin.works.edit', ['publikasi', $work->id]))->assertForbidden();
        $this->get('/admin/karya/publikasi')->assertOk()->assertDontSee('Confidential manuscript');
        $this->get('/admin/dashboard')->assertOk()->assertDontSee('Confidential manuscript');
    }

    public function test_corresponding_author_ownership_works_without_nested_pivot_errors(): void
    {
        $user = User::factory()->create(['role' => 'dosen']);
        $author = Penulis::factory()->create(['user_id' => $user->id]);
        $work = Publikasi::factory()->draft()->create();
        $work->penulis()->attach($author, ['urutan_penulis' => 1, 'penulis_korespondensi' => true]);
        $this->actingAs($user)->get(route('admin.works.edit', ['publikasi', $work->id]))->assertOk();
        $work->penulis()->updateExistingPivot($author, ['penulis_korespondensi' => false]);
        $this->get(route('admin.works.edit', ['publikasi', $work->id]))->assertForbidden();
    }

    public function test_registration_cannot_inject_admin_role_or_disable_account(): void
    {
        $this->post('/register', ['nama' => 'New Student', 'email' => 'student@example.test', 'password' => 'LongPassword123!', 'password_confirmation' => 'LongPassword123!', 'role' => 'admin', 'is_active' => false])->assertRedirect('/dashboard');
        $user = User::where('email', 'student@example.test')->firstOrFail();
        $this->assertSame('mahasiswa', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->penulis);
    }

    public function test_inactive_users_cannot_login_even_with_correct_password(): void
    {
        $user = User::factory()->inactive()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_user_management_links_author_without_stealing_an_identity(): void
    {
        $admin = User::factory()->admin()->create();
        $author = Penulis::factory()->create();
        $payload = ['nama' => 'Verifier Baru', 'email' => 'verify@example.test', 'password' => 'LongPassword123!', 'password_confirmation' => 'LongPassword123!', 'role' => 'verifier', 'is_active' => 1, 'penulis_id' => $author->id];
        $this->actingAs($admin)->post('/admin/users', $payload)->assertSessionHasNoErrors();
        $user = User::where('email', 'verify@example.test')->firstOrFail();
        $this->assertSame($user->id, $author->fresh()->user_id);
        $payload['email'] = 'steal@example.test';
        $this->post('/admin/users', $payload)->assertSessionHasErrors('penulis_id');
        $this->assertDatabaseMissing('users', ['email' => 'steal@example.test']);
        $this->put(route('admin.users.update', $admin), ['lock_version' => 0, 'nama' => $admin->nama, 'email' => $admin->email, 'role' => 'mahasiswa', 'is_active' => 1])->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
    }
}
