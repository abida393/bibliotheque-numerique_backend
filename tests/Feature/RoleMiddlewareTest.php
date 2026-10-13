<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_no_token_returns_401_on_protected_route(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
    }

    public function test_normal_user_is_forbidden_on_admin_only_route(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_moderator_is_forbidden_on_admin_only_route(): void
    {
        $moderator = $this->userWithRole('moderator');

        $this->actingAs($moderator, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_admin_can_access_admin_only_route(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertStatus(200);
    }

    public function test_moderator_can_access_moderator_or_admin_route(): void
    {
        $moderator = $this->userWithRole('moderator');

        $this->actingAs($moderator, 'sanctum')
            ->getJson('/api/contribution-requests')
            ->assertStatus(200);
    }

    public function test_normal_user_is_forbidden_on_moderator_route(): void
    {
        $user = $this->userWithRole('user');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/categories', ['name' => 'Test'])
            ->assertStatus(403);
    }

    public function test_invalid_role_filter_returns_422_not_500(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?role=superadmin')
            ->assertStatus(422);
    }
}
