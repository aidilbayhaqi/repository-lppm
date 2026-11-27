<?php

namespace Tests\Feature\Auth;

use App\Models\Dosen;
use App\Models\Penulis;
use App\Models\ProgramStudi;
use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk()->assertSee('Buat akun repositori');
    }

    public function test_new_users_are_inactive_and_cannot_login_before_approval(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('login'));
        $this->assertGuest();

        $user = User::where('email', 'student@example.test')->firstOrFail();
        $this->assertFalse($user->is_active);
        $this->assertSame('mahasiswa', $user->role);
        $this->assertSame('pending', $user->registrationRequest->status);
        $this->assertNull($user->penulis);

        $this->post('/login', ['email' => $user->email, 'password' => 'LongPassword123!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_role_escalation_and_duplicate_email_are_rejected(): void
    {
        $this->post('/register', $this->payload([
            'requested_role' => 'admin',
            'role' => 'admin',
            'is_active' => true,
        ]))->assertSessionHasErrors('requested_role');
        $this->assertDatabaseMissing('users', ['email' => 'student@example.test']);

        $this->post('/register', $this->payload())->assertRedirect(route('login'));
        $this->post('/register', $this->payload())->assertSessionHasErrors('email');
    }

    public function test_superadmin_approves_mahasiswa_and_creates_author_reference(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('login'));
        $registration = RegistrationRequest::firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.registrations.approve', $registration), [
                'role' => 'mahasiswa',
                'identifier' => '20260001',
            ])->assertSessionHasNoErrors()->assertRedirect(route('admin.registrations.index'));

        $user = $registration->user->fresh();
        $this->assertTrue($user->is_active);
        $this->assertSame('mahasiswa', $user->role);
        $this->assertSame('approved', $registration->fresh()->status);
        $this->assertDatabaseHas('penulis', [
            'user_id' => $user->id,
            'jenis_penulis' => 'mahasiswa',
            'identitas' => '20260001',
        ]);
    }

    public function test_lecturer_registration_needs_active_program_and_creates_two_reference_records(): void
    {
        $program = ProgramStudi::factory()->create();
        $this->post('/register', $this->payload([
            'requested_role' => 'dosen',
            'identifier' => '0123456789',
            'program_studi_id' => $program->id,
        ]))->assertRedirect(route('login'));

        $registration = RegistrationRequest::firstOrFail();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.registrations.approve', $registration), [
                'role' => 'dosen',
                'identifier' => '0123456789',
                'program_studi_id' => $program->id,
            ])->assertSessionHasNoErrors();

        $lecturer = Dosen::where('nidn', '0123456789')->firstOrFail();
        $this->assertSame($program->id, $lecturer->program_studi_id);
        $this->assertDatabaseHas('penulis', [
            'user_id' => $registration->user_id, 'dosen_id' => $lecturer->id, 'jenis_penulis' => 'dosen',
        ]);
    }

    public function test_non_admin_cannot_review_or_assign_privileged_role(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('login'));
        $registration = RegistrationRequest::firstOrFail();
        $ordinary = User::factory()->create(['role' => 'mahasiswa']);

        $this->actingAs($ordinary)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.registrations.approve', $registration), [
                'role' => 'operator', 'identifier' => '20260001',
            ])->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.registrations.approve', $registration), [
                'role' => 'admin', 'identifier' => '20260001',
            ])->assertSessionHasErrors('role');

        $this->assertSame('pending', $registration->fresh()->status);
        $this->assertFalse($registration->user->fresh()->is_active);
    }

    public function test_reject_keeps_user_inactive_and_prevents_repeat_decision(): void
    {
        $this->post('/register', $this->payload())->assertRedirect(route('login'));
        $registration = RegistrationRequest::firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.registrations.reject', $registration), [
                'review_note' => 'Identitas belum dapat diverifikasi.',
            ])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $registration->fresh()->status);
        $this->assertFalse($registration->user->fresh()->is_active);

        $this->post(route('admin.registrations.approve', $registration), [
            'role' => 'mahasiswa', 'identifier' => '20260001',
        ])->assertSessionHasErrors('approval');
    }

    public function test_existing_reference_cannot_be_stolen(): void
    {
        $other = User::factory()->create();
        Penulis::factory()->mahasiswa()->create([
            'nama' => 'Test User',
            'nama_normalisasi' => 'test user',
            'identitas' => '20260001',
            'user_id' => $other->id,
        ]);
        $this->post('/register', $this->payload())->assertRedirect(route('login'));
        $registration = RegistrationRequest::firstOrFail();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.registrations.approve', $registration), [
                'role' => 'mahasiswa',
                'identifier' => '20260001',
                'confirm_existing_reference' => 1,
            ])->assertSessionHasErrors('identifier');

        $this->assertSame('pending', $registration->fresh()->status);
        $this->assertFalse($registration->user->fresh()->is_active);
    }

    public function test_registration_shows_two_cards_and_staff_role_requests(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('registration-identity-title')
            ->assertSee('registration-security-title')
            ->assertSee('NIM/NIDN/No Identitas')
            ->assertSee('value="reviewer"', false)
            ->assertSee('value="verifier"', false)
            ->assertDontSee('value="admin"', false);
    }

    public function test_reviewers_can_request_access_but_require_superadmin_approval(): void
    {
        $this->post('/register', $this->payload([
            'requested_role' => 'reviewer',
            'identifier' => null,
        ]))->assertRedirect(route('login'));

        $registration = RegistrationRequest::firstOrFail();
        $user = $registration->user;
        $this->assertSame('reviewer', $registration->requested_role);
        $this->assertFalse($user->is_active);
        $this->assertGuest();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.registrations.approve', $registration), [
                'role' => 'reviewer',
                'identifier' => null,
            ])->assertSessionHasNoErrors();

        $this->assertSame('reviewer', $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame('approved', $registration->fresh()->status);
        $this->assertSame('reviewer', $registration->fresh()->requested_role);
    }

    public function test_staff_role_request_cannot_activate_account_using_injected_fields(): void
    {
        $this->post('/register', $this->payload([
            'requested_role' => 'verifier',
            'identifier' => null,
            'role' => 'admin',
            'is_active' => 1,
        ]))->assertRedirect(route('login'));

        $registration = RegistrationRequest::firstOrFail();
        $this->assertSame('verifier', $registration->requested_role);
        $this->assertFalse($registration->user->is_active);
        $this->assertNotSame('admin', $registration->user->role);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'nama' => 'Test User',
            'email' => 'student@example.test',
            'requested_role' => 'mahasiswa',
            'identifier' => '20260001',
            'password' => 'LongPassword123!',
            'password_confirmation' => 'LongPassword123!',
        ], $override);
    }
}
