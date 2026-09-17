<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContributionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'comment' => $this->comment,
            'processed_at' => $this->processed_at?->format('Y-m-d H:i'),
            'resource' => new ResourceResource($this->whenLoaded('resource')),
            'moderator' => new UserResource($this->whenLoaded('moderator')),
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
