<?php

namespace App\Jobs;

use App\Models\DocumentChunk;
use App\Models\Resource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class IndexResourceForAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(protected Resource $resource)
    {
    }

    public function handle(): void
    {
        $existingCategories = \App\Models\Category::pluck('name')->toArray();

        try {
            $response = Http::withToken(config('services.ai.token'))
                ->timeout(120)
                ->retry(2, 3000)
                ->post(config('services.ai.url') . '/analyze', [
                    'resource_id' => $this->resource->id,
                    'file_path' => Storage::disk('local')->path($this->resource->file_path),
                    'file_type' => $this->resource->type,
                    'existing_categories' => $existingCategories,
                ]);

            $response->throw();
            $data = $response->json();

            $this->resource->update([
                'ai_summary' => $data['summary'] ?? null,
            ]);

            foreach ($data['chunks'] ?? [] as $chunk) {
                DocumentChunk::create([
                    'resource_id' => $this->resource->id,
                    'text' => $chunk['text'],
                    'embedding_ref' => $chunk['embedding_ref'],
                    'page_or_section' => $chunk['chunk_index'] !== null ? "Section {$chunk['chunk_index']}" : null,
                ]);
            }

            Log::info("Indexation IA réussie pour la ressource #{$this->resource->id}");
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error("Échec de l'indexation IA pour la ressource #{$this->resource->id}: " . $e->getMessage());
            $this->resource->update(['ai_indexing_failed' => true]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job IndexResourceForAI définitivement échoué pour la ressource #{$this->resource->id}: " . $exception->getMessage());
        $this->resource->update(['ai_indexing_failed' => true]);
    }
}
