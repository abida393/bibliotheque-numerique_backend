<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Jobs\IndexResourceForAI;
use App\Models\Consultation;
use App\Models\ContributionRequest;
use App\Models\Resource;
use App\Models\User;
use App\Notifications\ResourceSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ResourceController extends Controller
{
    public function index(Request $request)
    {
        $query = Resource::with('category')
            ->withAvg('reviews', 'rating')
            ->validated();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('authors', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('publication_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('publication_date', '<=', $request->input('date_to'));
        }

        $sort = $request->input('sort', 'recent');

        match ($sort) {
            'recent'  => $query->latest(),
            'oldest'  => $query->oldest(),
            'popular' => $query->withCount('consultations')->orderByDesc('consultations_count'),
            'rating'  => $query->orderByDesc('reviews_avg_rating'),
            default   => $query->latest(),
        };

        $resources = $query->paginate($request->input('per_page', 20));
        return ResourceResource::collection($resources);
    }

    public function store(StoreResourceRequest $request)
    {
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        $existing = Resource::where('file_hash', $hash)->first();
        if ($existing) {
            return response()->json([
                'message'  => 'Ce document existe déjà dans la bibliothèque.',
                'resource' => new ResourceResource($existing),
            ], 409);
        }

        if ($request->filled('parent_resource_id')) {
            $parent = Resource::find($request->parent_resource_id);
            if ($parent && $parent->parent_resource_id !== null) {
                return response()->json([
                    'message' => 'Impossible de lier une version à une ressource qui est elle-même une version.'
                ], 422);
            }
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $type = match ($extension) {
            'pdf'         => 'pdf',
            'doc', 'docx' => 'word',
            'ppt', 'pptx' => 'powerpoint',
            default       => 'autre',
        };

        $path = $file->store('resources', 'local');

        $resource = Resource::create([
            'title'              => $request->title,
            'authors'            => $request->authors,
            'type'               => $type,
            'category_id'        => $request->category_id,
            'user_id'            => $request->user()->id,
            'parent_resource_id' => $request->parent_resource_id,
            'language'           => $request->language,
            'publication_date'   => $request->publication_date,
            'description'        => $request->description,
            'file_path'          => $path,
            'file_hash'          => $hash,
            'status'             => 'pending',
        ]);

        $contributionRequest = ContributionRequest::create([
            'resource_id' => $resource->id,
            'status'      => 'pending',
        ]);

        Notification::send(
            User::role(['admin', 'moderator'])->get(),
            new ResourceSubmittedNotification($resource, $contributionRequest),
        );

        IndexResourceForAI::dispatch($resource);

        return new ResourceResource($resource->load('category', 'user'));
    }

    public function show(Request $request, Resource $resource)
    {
        $this->authorize('view', $resource);

        $resource->load([
            'category',
            'user',
            'reviews.user',
            'parentResource' => fn ($query) => $query->where('status', 'validated'),
            'versions' => fn ($query) => $query->where('status', 'validated'),
        ]);

        $recentlyConsulted = Consultation::where('user_id', $request->user()->id)
            ->where('resource_id', $resource->id)
            ->where('consulted_at', '>=', now()->subHour())
            ->exists();

        if (!$recentlyConsulted) {
            Consultation::create([
                'user_id' => $request->user()->id,
                'resource_id' => $resource->id,
                'consulted_at' => now(),
            ]);
        }

        return new ResourceResource($resource);
    }

    public function update(Request $request, Resource $resource)
    {
        $this->authorize('update', $resource);

        $validated = $request->validate([
            'title'            => 'sometimes|required|string|max:255',
            'authors'          => 'nullable|string|max:255',
            'category_id'      => 'sometimes|required|exists:categories,id',
            'language'         => 'nullable|string|max:50',
            'description'      => 'nullable|string',
            'publication_date' => 'nullable|date',
        ]);

        $resource->update($validated);

        return new ResourceResource($resource->load('category', 'user'));
    }

    public function destroy(Resource $resource)
    {
        $this->authorize('delete', $resource);

        Storage::disk('local')->delete($resource->file_path);
        Storage::disk('public')->delete($resource->file_path);

        // B6 FIX: wrap AI call in try/catch so a downed service doesn't block deletion
        try {
            Http::withToken(config('services.ai.token'))
                ->timeout(5)
                ->delete(config('services.ai.url') . "/resources/{$resource->id}");
        } catch (\Exception $e) {
            Log::warning("AI service unavailable during resource deletion (id={$resource->id}): " . $e->getMessage());
        }

        $resource->delete();

        return response()->json(['message' => 'Ressource supprimée'], 200);
    }

    public function download(Resource $resource)
    {
        $this->authorize('view', $resource);

        $disk = Storage::disk('local');
        if (!$disk->exists($resource->file_path)) {
            return response()->json(['message' => 'Fichier introuvable'], 404);
        }

        return $disk->download(
            $resource->file_path,
            $resource->title . '.' . pathinfo($resource->file_path, PATHINFO_EXTENSION)
        );
    }

    public function myHistory(Request $request)
    {
        $pagination = Consultation::where('user_id', $request->user()->id)
            ->selectRaw('resource_id, MAX(consulted_at) as last_consulted_at')
            ->groupBy('resource_id')
            ->orderByDesc('last_consulted_at')
            ->paginate(20);

        $resourceIds = $pagination->getCollection()->pluck('resource_id');
        $resources = Resource::with('category')
            ->whereIn('id', $resourceIds)
            ->get()
            ->keyBy('id');

        $pagination->setCollection(
            $resourceIds
                ->map(fn ($resourceId) => $resources->get($resourceId))
                ->filter()
                ->values()
        );

        return ResourceResource::collection($pagination);
    }

    public function generateDescription(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,ppt,pptx|max:51200', // 50 MB
        ]);

        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $type      = match ($extension) {
            'pdf'         => 'pdf',
            'doc', 'docx' => 'word',
            'ppt', 'pptx' => 'powerpoint',
            default       => 'pdf',
        };

        $path         = $file->store('temp', 'local');
        $absolutePath = Storage::disk('local')->path($path);

        try {
            $response = Http::withToken(config('services.ai.token'))
                ->timeout(120)
                ->post(config('services.ai.url') . '/summarize', [
                    'file_path' => $absolutePath,
                    'file_type' => $type,
                ]);

            if ($response->successful()) {
                return response()->json([
                    'title'   => $response->json('title'),
                    'summary' => $response->json('summary')
                ]);
            }
            return response()->json([
                'message' => 'Erreur du service IA : ' . $response->json('detail', $response->body())
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Service IA indisponible : ' . $e->getMessage()], 503);
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public function generateDescriptionFromExisting(\Illuminate\Http\Request $request, Resource $resource)
    {
        if ($resource->user_id !== $request->user()->id && !$request->user()->hasAnyRole(['moderator', 'admin'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $disk = Storage::disk($resource->file_disk);
        if (!$disk->exists($resource->file_path)) {
            return response()->json(['message' => 'Fichier introuvable'], 404);
        }

        try {
            $response = Http::withToken(config('services.ai.token'))
                ->timeout(120)
                ->post(config('services.ai.url') . '/summarize', [
                    'file_path' => $disk->path($resource->file_path),
                    'file_type' => $resource->type,
                ]);

            if ($response->successful()) {
                return response()->json([
                    'title'   => $response->json('title'),
                    'summary' => $response->json('summary')
                ]);
            }
            return response()->json([
                'message' => 'Erreur du service IA : ' . $response->json('detail', $response->body())
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Service IA indisponible : ' . $e->getMessage()], 503);
        }
    }
}
