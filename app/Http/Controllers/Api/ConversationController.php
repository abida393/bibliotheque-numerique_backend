<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConversationController extends Controller
{
    public function show(Request $request, Resource $resource)
{
    $this->authorize('view', $resource);

    $conversation = Conversation::firstOrCreate([
        'user_id' => $request->user()->id,
        'resource_id' => $resource->id,
    ]);

    if ($conversation->wasRecentlyCreated) {
        $conversation->refresh();
        $conversation->wasRecentlyCreated = false;

    }

    return new ConversationResource($conversation->load('messages'));
}

public function ask(Request $request, Resource $resource)
{
    $this->authorize('view', $resource);

    $validated = $request->validate([
        'question' => 'required|string|max:1000',
    ]);

    if ($resource->ai_indexing_failed || is_null($resource->ai_summary)) {
        return response()->json([
            'message' => 'Ce document n\'a pas encore été analysé par l\'IA ou son indexation a échoué.'
        ], 422);
    }

    $conversation = Conversation::firstOrCreate([
        'user_id' => $request->user()->id,
        'resource_id' => $resource->id,
    ]);

    if ($conversation->wasRecentlyCreated) {
        $conversation->refresh();
    }

    try {
        $response = Http::withToken(config('services.ai.token'))
            ->timeout(60)
            ->retry(2, 2000)
            ->post(config('services.ai.url') . '/ask', [
                'resource_id' => $resource->id,
                'question' => $validated['question'],
            ]);

        $response->throw();
        $data = $response->json();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'question' => $validated['question'],
            'answer' => $data['answer'],
            'cited_sources' => $data['sources'] ?? [],
        ]);

        return (new MessageResource($message->fresh()))
            ->response()
            ->setStatusCode(201);
    } catch (\Illuminate\Http\Client\RequestException $e) {
        return response()->json([
            'message' => 'Le service IA est momentanément indisponible. Réessayez dans quelques instants.'
        ], 503);
    }
}
}
