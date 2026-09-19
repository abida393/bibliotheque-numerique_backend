<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceResource;
use App\Models\Favorite;
use App\Models\Resource;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $resources = Resource::whereHas('favorites', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->with('category')->paginate(20);

        return ResourceResource::collection($resources);
    }

    public function store(Request $request, Resource $resource)
    {
        $favorite = Favorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'resource_id' => $resource->id,
        ]);

        return response()->json(['message' => 'Ajouté aux favoris'], 201);
    }

    public function destroy(Request $request, Resource $resource)
    {
        Favorite::where('user_id', $request->user()->id)
            ->where('resource_id', $resource->id)
            ->delete();

        return response()->json(['message' => 'Retiré des favoris'], 200);
    }
}
