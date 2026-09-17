<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContributionRequestResource;
use App\Models\ContributionRequest;
use Illuminate\Http\Request;

class ContributionRequestController extends Controller
{
    // Liste des demandes en attente (pour les modérateurs)
    public function index()
    {
        $requests = ContributionRequest::with(['resource.category', 'resource.user'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return ContributionRequestResource::collection($requests);
    }

    // Suivi de ses propres contributions (pour n'importe quel utilisateur)
    public function myContributions(Request $request)
    {
        $requests = ContributionRequest::with('resource.category')
            ->whereHas('resource', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->latest()
            ->paginate(20);

        return ContributionRequestResource::collection($requests);
    }

    public function validate(Request $request, ContributionRequest $contributionRequest)
    {
        if ($contributionRequest->status !== 'pending') {
            return response()->json(['message' => 'Cette demande a déjà été traitée.'], 409);
        }

        $contributionRequest->update([
            'status' => 'validated',
            'moderator_id' => $request->user()->id,
            'processed_at' => now(),
        ]);

        $contributionRequest->resource->update(['status' => 'validated']);

        return new ContributionRequestResource($contributionRequest->load('resource', 'moderator'));
    }

    public function reject(Request $request, ContributionRequest $contributionRequest)
    {
        $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        if ($contributionRequest->status !== 'pending') {
            return response()->json(['message' => 'Cette demande a déjà été traitée.'], 409);
        }

        $contributionRequest->update([
            'status' => 'rejected',
            'moderator_id' => $request->user()->id,
            'comment' => $request->comment,
            'processed_at' => now(),
        ]);

        $contributionRequest->resource->update(['status' => 'rejected']);

        return new ContributionRequestResource($contributionRequest->load('resource', 'moderator'));
    }
}
