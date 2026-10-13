<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
        $this->moderator = User::factory()->create();
        $this->moderator->assignRole('moderator');
    }

    public function test_can_create_root_category(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/categories', ['name' => 'Informatique']);

        $response->assertStatus(201)->assertJsonPath('data.parent_id', null);
    }

    public function test_create_subcategory_with_invalid_parent_fails(): void
    {
        $response = $this->actingAs($this->moderator, 'sanctum')
            ->postJson('/api/categories', ['name' => 'IA', 'parent_id' => 99999]);

        $response->assertStatus(422);
    }

    public function test_update_accepts_partial_payload(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->moderator, 'sanctum')
            ->putJson("/api/categories/{$category->id}", ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_cannot_delete_category_with_resources(): void
    {
        $category = Category::factory()->create();
        Resource::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->moderator, 'sanctum')
            ->deleteJson("/api/categories/{$category->id}")
            ->assertStatus(409);
    }

    public function test_deleting_parent_nullifies_children_parent_id(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->moderator, 'sanctum')
            ->deleteJson("/api/categories/{$parent->id}")
            ->assertStatus(200);

        $this->assertNull($child->fresh()->parent_id);
    }
}
