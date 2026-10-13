<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Resource $resource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();

        $this->user = User::factory()->create();
        $this->user->assignRole('user');

        $category = Category::factory()->create();
        $this->resource = Resource::factory()->create(['category_id' => $category->id]);
    }

    public function test_adding_favorite_twice_does_not_duplicate(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->resource->id}/favorite")
            ->assertStatus(201);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->resource->id}/favorite")
            ->assertStatus(200);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/favorites')
            ->assertJsonCount(1, 'data');
    }

    public function test_removing_nonexistent_favorite_does_not_error(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/resources/{$this->resource->id}/favorite")
            ->assertStatus(200);
    }

    public function test_rating_out_of_bounds_fails_validation(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->resource->id}/reviews", ['rating' => 0])
            ->assertStatus(422);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->resource->id}/reviews", ['rating' => 6])
            ->assertStatus(422);
    }

    public function test_cannot_review_same_resource_twice(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->resource->id}/reviews", ['rating' => 4])
            ->assertStatus(201);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->resource->id}/reviews", ['rating' => 5])
            ->assertStatus(409);
    }

    public function test_other_user_cannot_delete_someone_elses_review(): void
    {
        $review = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->resource->id}/reviews", ['rating' => 4])
            ->json('data.id');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/reviews/{$review}")
            ->assertStatus(403);
    }

    public function test_reviews_list_empty_for_resource_without_reviews(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/resources/{$this->resource->id}/reviews")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
