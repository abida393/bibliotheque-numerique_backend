<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceResource;
use App\Models\Favorite;
use App\Models\Resource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        // M5: only return favorites that are still validated
        $resources = Resource::validated()
            ->whereHas('favorites', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with('category')
            ->paginate($request->input('per_page', 9));

        return ResourceResource::collection($resources);
    }

    public function store(Request $request, Resource $resource)
    {
        // M5: only allow favoriting validated resources
        if ($resource->status !== 'validated') {
            return response()->json(['message' => 'Seules les ressources validées peuvent être mises en favori.'], 403);
        }

        try {
            $favorite = Favorite::firstOrCreate(
                [
                    'user_id'     => $request->user()->id,
                    'resource_id' => $resource->id,
                ],
                [
                    'created_at'  => now(),
                ]
            );
            $status = $favorite->wasRecentlyCreated ? 201 : 200;
        } catch (QueryException $e) {
            // Race condition safety net
            $status = 200;
        }

        return response()->json(['message' => 'Ajouté aux favoris'], $status);
    }

    public function destroy(Request $request, Resource $resource)
    {
        Favorite::where('user_id', $request->user()->id)
            ->where('resource_id', $resource->id)
            ->delete();

        return response()->json(['message' => 'Retiré des favoris'], 200);
    }
}
