<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Consultation;
use App\Models\Favorite;
use App\Models\Resource;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_moderator_forbidden_on_all_stats_routes(): void
    {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        foreach (['overview', 'popular-resources', 'resources-by-category', 'activity-trend'] as $route) {
            $this->actingAs($moderator, 'sanctum')
                ->getJson("/api/stats/{$route}")
                ->assertStatus(403);
        }
    }

    public function test_overview_returns_expected_counters(): void
    {
        $category = Category::factory()->create();
        Resource::factory()->count(3)->create(['category_id' => $category->id]);
        Resource::factory()->pending()->count(2)->create(['category_id' => $category->id]);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/stats/overview');

        $response->assertStatus(200)
            ->assertJsonPath('total_resources', 3)
            ->assertJsonPath('total_pending', 2);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->admin->id}/role", ['role' => 'user'])
            ->assertStatus(403);
    }

    public function test_admin_can_reassign_same_role_to_self(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$this->admin->id}/role", ['role' => 'admin'])
            ->assertStatus(200);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$this->admin->id}")
            ->assertStatus(403);
    }

    public function test_invalid_role_in_filter_returns_422(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/users?role=superadmin')
            ->assertStatus(422);
    }

    public function test_deleting_user_cascades_related_data(): void
    {
        $target = User::factory()->create();
        $target->assignRole('user');

        $category = Category::factory()->create();
        $resource = Resource::factory()->create([
            'category_id' => $category->id,
            'user_id' => $target->id,
        ]);
        Review::create(['user_id' => $target->id, 'resource_id' => $resource->id, 'rating' => 4]);
        Favorite::create(['user_id' => $target->id, 'resource_id' => $resource->id]);
        Consultation::create(['user_id' => $target->id, 'resource_id' => $resource->id, 'consulted_at' => now()]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$target->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('resources', ['id' => $resource->id]);
        $this->assertDatabaseMissing('reviews', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('favorites', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('consultations', ['user_id' => $target->id]);
    }
}
