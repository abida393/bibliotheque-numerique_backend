<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Resource;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
    }

    public function test_health_check_route_is_up(): void
    {
        $this->get('/up')->assertStatus(200);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'newuser@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)->assertJsonStructure(['user', 'token']);
        $this->assertDatabaseHas('users', ['email' => 'newuser@test.com']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@test.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test',
            'email' => 'dup@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_with_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test',
            'email' => 'mismatch@test.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user', 'token']);
    }

    public function test_login_returns_profile_counters_without_a_followup_refresh(): void
    {
        $user = User::factory()->create([
            'email' => 'profile@test.com',
            'password' => bcrypt('password123'),
        ]);
        $category = Category::factory()->create();
        $resource = Resource::factory()->create([
            'category_id' => $category->id,
            'user_id' => $user->id,
        ]);
        Favorite::create(['user_id' => $user->id, 'resource_id' => $resource->id]);
        Review::create([
            'user_id' => $user->id,
            'resource_id' => $resource->id,
            'rating' => 5,
        ]);

        $this->postJson('/api/login', [
            'email' => 'profile@test.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.resources_count', 1)
            ->assertJsonPath('user.favorites_count', 1)
            ->assertJsonPath('user.reviews_count', 1);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'wrongpass@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrongpass@test.com',
            'password' => 'incorrect',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_logout_invalidates_token(): void
{
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout')
        ->assertStatus(200);

    // Force Laravel à oublier le guard mis en cache, pour que la requête
    // suivante ré-authentifie réellement depuis la base de données,
    // au lieu de réutiliser l'utilisateur résolu au premier appel.
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me')
        ->assertStatus(401);
}
}
