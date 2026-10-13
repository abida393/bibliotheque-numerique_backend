<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Resource $indexedResource;
    private Resource $notIndexedResource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();

        $this->user = User::factory()->create();
        $this->user->assignRole('user');

        $category = Category::factory()->create();

        $this->indexedResource = Resource::factory()->create([
            'category_id' => $category->id,
            'ai_summary' => 'Un resume genere par IA.',
            'ai_indexing_failed' => false,
        ]);

        $this->notIndexedResource = Resource::factory()->create([
            'category_id' => $category->id,
            'ai_summary' => null,
        ]);
    }

    public function test_asking_on_non_indexed_resource_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->notIndexedResource->id}/ask", [
                'question' => 'De quoi parle ce document ?',
            ])->assertStatus(422);
    }

    public function test_ask_requires_question_field(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->indexedResource->id}/ask", [])
            ->assertStatus(422);
    }

    public function test_ask_creates_message_and_returns_answer(): void
    {
        Http::fake([
            '*/ask' => Http::response([
                'answer' => 'Voici la reponse generee.',
                'sources' => [
                    ['embedding_ref' => 'resource-1-chunk-0', 'chunk_index' => 0],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->indexedResource->id}/ask", [
                'question' => 'De quoi parle ce document ?',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.answer', 'Voici la reponse generee.');

        $this->assertDatabaseHas('messages', [
            'question' => 'De quoi parle ce document ?',
        ]);
    }

    public function test_ai_service_unavailable_returns_503(): void
    {
        Http::fake([
            '*/ask' => Http::response([], 500),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->indexedResource->id}/ask", [
                'question' => 'Question test',
            ])->assertStatus(503);
    }

    public function test_second_question_reuses_same_conversation(): void
    {
        Http::fake([
            '*/ask' => Http::response(['answer' => 'Reponse', 'sources' => []], 200),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->indexedResource->id}/ask", ['question' => 'Q1']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/resources/{$this->indexedResource->id}/ask", ['question' => 'Q2']);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_conversation_show_creates_empty_conversation(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/resources/{$this->indexedResource->id}/conversation");

        $response->assertStatus(200)->assertJsonCount(0, 'data.messages');
    }
}
