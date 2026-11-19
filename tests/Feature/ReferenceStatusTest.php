<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_reference_status_without_work_type_resolution(): void
    {
        $admin = User::factory()->admin()->create();
        $faculty = Fakultas::factory()->create(['aktif' => true]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reference.status', ['fakultas', $faculty->id]))
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('id', $faculty->id)
            ->assertJsonPath('active', true);

        $directives = array_map(
            'trim',
            explode(',', strtolower((string) $response->headers->get('Cache-Control')))
        );

        $this->assertContains('private', $directives);
        $this->assertContains('no-store', $directives);
    }

    public function test_non_reference_manager_and_guest_cannot_read_master_status(): void
    {
        $faculty = Fakultas::factory()->create();
        $route = route('admin.reference.status', ['fakultas', $faculty->id]);

        $this->get($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'mahasiswa']))
            ->get($route)->assertForbidden();
    }

    public function test_unknown_reference_entity_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reference.status', ['does-not-exist', 1]))
            ->assertNotFound();
    }
}
