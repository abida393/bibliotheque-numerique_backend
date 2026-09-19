<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Resource;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Resource $resource)
    {
        $reviews = $resource->reviews()->with('user')->latest()->paginate(20);

        return ReviewResource::collection($reviews);
    }

    public function store(Request $request, Resource $resource)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $existing = Review::where('user_id', $request->user()->id)
            ->where('resource_id', $resource->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Vous avez déjà noté cette ressource.'], 409);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'resource_id' => $resource->id,
            ...$validated,
        ]);

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
}
