<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();

        $this->moderator = User::factory()->create();
        $this->moderator->assignRole('moderator');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_moderator_can_list_and_mark_own_notifications_read(): void
    {
        $notification = $this->moderator->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ResourceSubmittedNotification',
            'data' => ['title' => 'Resource to review', 'url' => '/admin/moderation'],
        ]);

        $this->actingAs($this->moderator, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.data.title', 'Resource to review');

        $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_users_cannot_read_staff_notifications(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertForbidden();
    }

    public function test_staff_cannot_mark_another_users_notification_read(): void
    {
        $notification = $this->admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ResourceSubmittedNotification',
            'data' => ['title' => 'Resource to review', 'url' => '/admin/moderation'],
        ]);

        $this->actingAs($this->moderator, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}