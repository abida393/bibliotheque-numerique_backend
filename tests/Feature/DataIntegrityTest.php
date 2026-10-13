<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Favorite;
use App\Models\Resource;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_file_hash_is_rejected_at_database_level(): void
    {
        $category = Category::factory()->create();
        $hash = hash('sha256', 'same-content');

        Resource::factory()->create(['category_id' => $category->id, 'file_hash' => $hash]);

        $this->expectException(QueryException::class);
        Resource::factory()->create(['category_id' => $category->id, 'file_hash' => $hash]);
    }

    public function test_duplicate_favorite_pair_is_rejected_at_database_level(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $resource = Resource::factory()->create(['category_id' => $category->id]);

        Favorite::create(['user_id' => $user->id, 'resource_id' => $resource->id]);

        $this->expectException(QueryException::class);
        Favorite::create(['user_id' => $user->id, 'resource_id' => $resource->id]);
    }

    public function test_duplicate_review_pair_is_rejected_at_database_level(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $resource = Resource::factory()->create(['category_id' => $category->id]);

        Review::create(['user_id' => $user->id, 'resource_id' => $resource->id, 'rating' => 5]);

        $this->expectException(QueryException::class);
        Review::create(['user_id' => $user->id, 'resource_id' => $resource->id, 'rating' => 3]);
    }

    public function test_deleting_parent_category_does_not_delete_children(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $parent->delete();

        $this->assertDatabaseHas('categories', ['id' => $child->id]);
    }
}
