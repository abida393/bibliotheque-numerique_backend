<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContributionRequest;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;
    private User $author;
    private Resource $resource;
    private ContributionRequest $request_;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();

        $this->moderator = User::factory()->create();
        $this->moderator->assignRole('moderator');

        $this->author = User::factory()->create();
        $this->author->assignRole('user');

        $category = Category::factory()->create();

        $this->resource = Resource::factory()->pending()->create([
            'category_id' => $category->id,
            'user_id' => $this->author->id,
        ]);

        $this->request_ = ContributionRequest::create([
            'resource_id' => $this->resource->id,
            'status' => 'pending',
        ]);
    }

    public function test_normal_user_cannot_list_contribution_requests(): void
    {
        $this->actingAs($this->author, 'sanctum')
            ->getJson('/api/contribution-requests')
            ->assertStatus(403);
    }

    public function test_moderator_can_validate_pending_request(): void
    {
        $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/contribution-requests/{$this->request_->id}/validate")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'validated');

        $this->assertEquals('validated', $this->resource->fresh()->status);
    }

    public function test_validated_resource_becomes_visible_in_catalog(): void
    {
        $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/contribution-requests/{$this->request_->id}/validate");

        $this->actingAs($this->author, 'sanctum')
            ->getJson('/api/resources')
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_validate_twice(): void
    {
        $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/contribution-requests/{$this->request_->id}/validate")
            ->assertStatus(200);

        $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/contribution-requests/{$this->request_->id}/validate")
            ->assertStatus(409);
    }

    public function test_reject_requires_comment(): void
    {
        $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/contribution-requests/{$this->request_->id}/reject", [])
            ->assertStatus(422);
    }

    public function test_reject_sets_status_and_hides_resource(): void
    {
        $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/contribution-requests/{$this->request_->id}/reject", [
                'comment' => 'Doublon',
            ])->assertStatus(200)->assertJsonPath('data.status', 'rejected');

        $this->assertEquals('rejected', $this->resource->fresh()->status);

        $this->actingAs($this->author, 'sanctum')
            ->getJson('/api/resources')
            ->assertJsonCount(0, 'data');
    }

    public function test_my_contributions_only_shows_own_requests(): void
    {
        $otherAuthor = User::factory()->create();
        $otherAuthor->assignRole('user');

        $response = $this->actingAs($this->author, 'sanctum')
            ->getJson('/api/my-contributions');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }
}
