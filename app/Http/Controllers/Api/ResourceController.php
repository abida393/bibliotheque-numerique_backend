<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Models\Consultation;
use App\Models\ContributionRequest;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResourceController extends Controller
{
   public function index(Request $request)
{
    $query = Resource::with('category')->validated();

    // --- Recherche texte ---
    if ($request->filled('search')) {
        $search = $request->input('search');
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
            ->orWhere('authors', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // --- Filtres ---
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

    // --- Tri ---
    $sort = $request->input('sort', 'recent'); // valeur par défaut

    match ($sort) {
        'recent' => $query->latest(),
        'oldest' => $query->oldest(),
        'popular' => $query->withCount('consultations')->orderByDesc('consultations_count'),
        'rating' => $query->withAvg('reviews', 'rating')->orderByDesc('reviews_avg_rating'),
        default => $query->latest(),
    };

    $resources = $query->paginate(20);

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

    public function show(Request $request, Resource $resource)
{
    $resource->load('category', 'user', 'reviews');

    Consultation::create([
        'user_id' => $request->user()->id,
        'resource_id' => $resource->id,
        'consulted_at' => now(),
    ]);

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
    public function myHistory(Request $request)
{
    $consultations = Consultation::where('user_id', $request->user()->id)
        ->with('resource.category')
        ->latest('consulted_at')
        ->paginate(20);

    return ResourceResource::collection(
        $consultations->through(fn($c) => $c->resource)
    );
}
}
