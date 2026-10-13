<?php

namespace Tests\Feature;

use App\Jobs\IndexResourceForAI;
use App\Models\Category;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IndexResourceForAIJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRoles();
        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->user->assignRole('user');
        $this->category = Category::factory()->create();
    }

    public function test_uploading_resource_dispatches_indexing_job(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');

        $this->actingAs($this->user, 'sanctum')->postJson('/api/resources', [
            'title' => 'Doc IA',
            'category_id' => $this->category->id,
            'file' => $file,
        ])->assertStatus(201);

        Queue::assertPushed(IndexResourceForAI::class);
    }

    public function test_job_updates_resource_with_summary_on_success(): void
    {
        Http::fake([
            '*/analyze' => Http::response([
                'summary' => 'Resume genere.',
                'suggested_category' => 'Informatique',
                'suggested_tags' => ['tag1'],
                'chunks' => [
                    ['embedding_ref' => 'r-1-c-0', 'text' => 'texte', 'chunk_index' => 0],
                ],
            ], 200),
        ]);

        $resource = Resource::factory()->create(['category_id' => $this->category->id]);

        (new IndexResourceForAI($resource))->handle();

        $resource->refresh();
        $this->assertEquals('Resume genere.', $resource->ai_summary);
        $this->assertFalse($resource->ai_indexing_failed);
        $this->assertDatabaseCount('document_chunks', 1);
    }

    public function test_job_marks_resource_as_failed_when_service_down(): void
    {
        Http::fake([
            '*/analyze' => Http::response([], 500),
        ]);

        $resource = Resource::factory()->create(['category_id' => $this->category->id]);

        (new IndexResourceForAI($resource))->handle();

        $this->assertTrue($resource->fresh()->ai_indexing_failed);
    }
}
