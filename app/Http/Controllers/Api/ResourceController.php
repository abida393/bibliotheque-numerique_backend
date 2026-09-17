<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Models\ContributionRequest;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResourceController extends Controller
{
    public function index(Request $request)
    {
        $resources = Resource::with('category')
            ->validated()
            ->latest()
            ->paginate(20);

        return ResourceResource::collection($resources);
    }

    public function store(StoreResourceRequest $request)
    {
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        $existing = Resource::where('file_hash', $hash)->first();
        if ($existing) {
            return response()->json([
                'message' => 'Ce document existe déjà dans la bibliothèque.',
                'resource' => new ResourceResource($existing),
            ], 409);
        }

        $type = match ($file->getClientOriginalExtension()) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'word',
            'ppt', 'pptx' => 'powerpoint',
            default => 'autre',
        };

        $path = $file->store('resources', 'public');

        $resource = Resource::create([
            'title' => $request->title,
            'authors' => $request->authors,
            'type' => $type,
            'category_id' => $request->category_id,
            'user_id' => $request->user()->id,
            'language' => $request->language,
            'publication_date' => $request->publication_date,
            'description' => $request->description,
            'file_path' => $path,
            'file_hash' => $hash,
            'status' => 'pending',
        ]);

        ContributionRequest::create([
            'resource_id' => $resource->id,
            'status' => 'pending',
        ]);

        // IndexResourceForAI::dispatch($resource); // ajouté en Phase 8

        return new ResourceResource($resource->load('category', 'user'));
    }

    public function show(Resource $resource)
    {
        $resource->load('category', 'user', 'reviews');

        return new ResourceResource($resource);
    }

    public function update(Request $request, Resource $resource)
    {
        $this->authorize('update', $resource);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'authors' => 'nullable|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'language' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $resource->update($validated);

        return new ResourceResource($resource->load('category'));
    }

    public function destroy(Resource $resource)
    {
        $this->authorize('delete', $resource);

        Storage::disk('public')->delete($resource->file_path);
        $resource->delete();

        return response()->json(['message' => 'Ressource supprimée'], 200);
    }

    public function download(Resource $resource)
    {
        if (!Storage::disk('public')->exists($resource->file_path)) {
            return response()->json(['message' => 'Fichier introuvable'], 404);
        }

        return Storage::disk('public')->download(
            $resource->file_path,
            $resource->title . '.' . pathinfo($resource->file_path, PATHINFO_EXTENSION)
        );
    }
}
