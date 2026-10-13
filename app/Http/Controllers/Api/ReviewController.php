<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Resource;
use App\Models\Review;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Resource $resource)
    {
        $reviews = $resource->reviews()->with('user')->latest('created_at')->paginate(20);
        return ReviewResource::collection($reviews);
    }

    public function store(Request $request, Resource $resource)
    {
        // M5: only allow reviews on validated resources
        if ($resource->status !== 'validated') {
            return response()->json(['message' => 'Vous ne pouvez noter que les ressources validées.'], 403);
        }

        // Block self-review
        if ($resource->user_id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas noter votre propre ressource.'], 403);
        }

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $review = Review::create([
                'user_id'     => $request->user()->id,
                'resource_id' => $resource->id,
                'created_at'  => now(),
                ...$validated,
            ]);
        } catch (QueryException $e) {
            // Unique constraint violation — race condition or duplicate
            return response()->json(['message' => 'Vous avez déjà noté cette ressource.'], 409);
        }

        return new ReviewResource($review->load('user'));
    }

    public function destroy(Request $request, Review $review)
    {
        if ($review->user_id !== $request->user()->id && !$request->user()->hasAnyRole(['moderator', 'admin'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Avis supprimé'], 200);
    }

    public function myReviews(Request $request)
    {
        $reviews = Review::with('resource.category')
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->paginate($request->input('per_page', 20));
        
        return ReviewResource::collection($reviews);
    }
}
