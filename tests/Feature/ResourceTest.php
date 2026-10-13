<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherUser;
    private User $moderator;
    private User $admin;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');

        $this->owner = User::factory()->create();
        $this->owner->assignRole('user');

        $this->otherUser = User::factory()->create();
        $this->otherUser->assignRole('user');

        $this->moderator = User::factory()->create();
        $this->moderator->assignRole('moderator');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->category = Category::factory()->create();
    }

    public function test_store_requires_file(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/resources', [
                'title' => 'No File',
                'category_id' => $this->category->id,
            ])->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_store_rejects_disallowed_mime_type(): void
    {
        $file = UploadedFile::fake()->create('malware.exe', 10);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/resources', [
                'title' => 'Bad File',
                'category_id' => $this->category->id,
                'file' => $file,
            ])->assertStatus(422);
    }

    public function test_store_creates_resource_and_contribution_request(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/resources', [
                'title' => 'Mon Document',
                'category_id' => $this->category->id,
                'file' => $file,
            ]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'pending');

        $resource = Resource::where('title', 'Mon Document')->firstOrFail();
        $this->assertDatabaseHas('resources', ['title' => 'Mon Document']);
        Storage::disk('local')->assertExists($resource->file_path);
        Storage::disk('public')->assertMissing($resource->file_path);
        $this->assertStringContainsString(
            "/api/resources/{$resource->id}/download",
            $response->json('data.file_url')
        );
        $this->assertDatabaseHas('contribution_requests', ['status' => 'pending']);
    }

    public function test_store_notifies_moderators_and_admins(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf');

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/resources', [
            'title' => 'Ressource à modérer',
            'category_id' => $this->category->id,
            'file' => $file,
        ])->assertStatus(201);

        $moderatorNotification = $this->moderator->notifications()->firstOrFail();
        $adminNotification = $this->admin->notifications()->firstOrFail();

        $this->assertSame('Ressource à modérer', $moderatorNotification->data['title']);
        $this->assertSame($moderatorNotification->data['contribution_request_id'], $adminNotification->data['contribution_request_id']);
        $this->assertSame('/admin/moderation', $adminNotification->data['url']);
        $this->assertSame(0, $this->owner->notifications()->count());
    }

    public function test_duplicate_file_upload_returns_409(): void
    {
        $file1 = UploadedFile::fake()->createWithContent('a.pdf', 'identical-content');
        $file2 = UploadedFile::fake()->createWithContent('b.pdf', 'identical-content');

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/resources', [
            'title' => 'First',
            'category_id' => $this->category->id,
            'file' => $file1,
        ])->assertStatus(201);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/resources', [
            'title' => 'Second (same content)',
            'category_id' => $this->category->id,
            'file' => $file2,
        ])->assertStatus(409);
    }

    public function test_pending_resource_not_visible_in_public_index(): void
    {
        Resource::factory()->pending()->create(['category_id' => $this->category->id]);

        $response = $this->actingAs($this->owner, 'sanctum')->getJson('/api/resources');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_validated_resource_is_visible_in_public_index(): void
    {
        Resource::factory()->create(['category_id' => $this->category->id]);

        $response = $this->actingAs($this->owner, 'sanctum')->getJson('/api/resources');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_show_returns_pending_resource_to_authenticated_user(): void
    {
        $resource = Resource::factory()->pending()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/resources/{$resource->id}")
            ->assertStatus(200);
    }

    public function test_show_records_consultations_only_after_an_hour(): void
    {
        $resource = Resource::factory()->pending()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
        ]);
        $firstVisit = Carbon::parse('2026-10-03 10:00:00');
        Carbon::setTestNow($firstVisit);

        try {
            $this->actingAs($this->owner, 'sanctum')
                ->getJson("/api/resources/{$resource->id}")
                ->assertOk();
            $this->assertDatabaseCount('consultations', 1);

            Carbon::setTestNow($firstVisit->copy()->addHour());
            $this->actingAs($this->owner, 'sanctum')
                ->getJson("/api/resources/{$resource->id}")
                ->assertOk();
            $this->assertDatabaseCount('consultations', 1);

            Carbon::setTestNow($firstVisit->copy()->addHour()->addSecond());
            $this->actingAs($this->owner, 'sanctum')
                ->getJson("/api/resources/{$resource->id}")
                ->assertOk();
            $this->assertDatabaseCount('consultations', 2);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_my_history_returns_unique_resources_ordered_by_latest_visit(): void
    {
        $olderResource = Resource::factory()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
            'title' => 'Consultée plusieurs fois',
        ]);
        $newerResource = Resource::factory()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
            'title' => 'Dernière consultation récente',
        ]);

        $this->owner->consultations()->createMany([
            ['resource_id' => $olderResource->id, 'consulted_at' => now()->subMinutes(5)],
            ['resource_id' => $newerResource->id, 'consulted_at' => now()->subMinutes(3)],
            ['resource_id' => $olderResource->id, 'consulted_at' => now()->subMinute()],
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/my-history')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $olderResource->id)
            ->assertJsonPath('data.1.id', $newerResource->id)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_other_user_cannot_view_pending_resource(): void
    {
        $resource = Resource::factory()->pending()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->otherUser, 'sanctum')
            ->getJson("/api/resources/{$resource->id}")
            ->assertStatus(403);
    }

    public function test_other_user_cannot_download_pending_resource(): void
    {
        $resource = Resource::factory()->pending()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->otherUser, 'sanctum')
            ->getJson("/api/resources/{$resource->id}/download")
            ->assertStatus(403);
    }

    public function test_owner_can_download_private_pending_resource(): void
    {
        $resource = Resource::factory()->pending()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
        ]);
        Storage::disk('local')->put($resource->file_path, 'private contents');

        $this->actingAs($this->owner, 'sanctum')
            ->get("/api/resources/{$resource->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_show_unknown_id_returns_404(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/resources/99999')
            ->assertStatus(404);
    }

    public function test_owner_can_update_own_pending_resource(): void
    {
        $resource = Resource::factory()->pending()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/resources/{$resource->id}", ['title' => 'Updated'])
            ->assertStatus(200);
    }

    public function test_other_user_cannot_update_resource_returns_403_not_500(): void
    {
        $resource = Resource::factory()->pending()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->otherUser, 'sanctum')
            ->putJson("/api/resources/{$resource->id}", ['title' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_owner_cannot_update_once_validated(): void
    {
        $resource = Resource::factory()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
            'status' => 'validated',
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/resources/{$resource->id}", ['title' => 'Nope'])
            ->assertStatus(403);
    }

    public function test_moderator_can_update_any_validated_resource(): void
    {
        $resource = Resource::factory()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
            'status' => 'validated',
        ]);

        $this->actingAs($this->moderator, 'sanctum')
            ->putJson("/api/resources/{$resource->id}", ['title' => 'Moderated'])
            ->assertStatus(200);
    }

    public function test_other_user_cannot_delete_resource_returns_403(): void
    {
        $resource = Resource::factory()->pending()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->otherUser, 'sanctum')
            ->deleteJson("/api/resources/{$resource->id}")
            ->assertStatus(403);
    }

    public function test_pagination_returns_correct_page_size(): void
    {
        Resource::factory()->count(25)->create(['category_id' => $this->category->id]);

        $response = $this->actingAs($this->owner, 'sanctum')->getJson('/api/resources');

        $response->assertStatus(200)->assertJsonCount(20, 'data');
    }

    public function test_search_with_special_sql_characters_does_not_crash(): void
    {
        Resource::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Normal Title',
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/resources?search=' . urlencode('100%_test'))
            ->assertStatus(200);
    }
}
