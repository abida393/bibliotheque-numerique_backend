<?php

namespace App\Http\Resources;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'authors'         => $this->authors,
            'type'            => $this->type,
            'language'        => $this->language,
            'publication_date'=> $this->publication_date?->format('Y-m-d'),
            'description'     => $this->description,
            'status'          => $this->status,
            'file_url'        => route('resources.download', ['resource' => $this->id]),
            // Expose author_user including its id so the frontend can check ownership
            'author_user'     => new UserResource($this->whenLoaded('user')),
            'category'        => new CategoryResource($this->whenLoaded('category')),
            'parent_resource' => new ResourceResource($this->whenLoaded('parentResource')),
            'versions'        => ResourceResource::collection($this->whenLoaded('versions')),
            'reviews_count'   => $this->when(
                $this->relationLoaded('reviews'),
                fn() => $this->reviews->count()
            ),
            'average_rating'  => $this->when(
                $this->relationLoaded('reviews'),
                fn() => $this->reviews->count() > 0
                    ? round($this->reviews->avg('rating'), 1)
                    : null
            ),
            // Also expose pre-computed aggregates when loaded via withAvg/withCount
            'reviews_avg_rating' => $this->when(
                isset($this->reviews_avg_rating),
                fn() => $this->reviews_avg_rating ? round($this->reviews_avg_rating, 1) : null
            ),
            'consultations_count' => $this->when(
                isset($this->consultations_count),
                $this->consultations_count
            ),
            'created_at'      => $this->created_at?->format('Y-m-d'),
            'ai_summary'      => $this->ai_summary,
            'ai_indexing_failed' => $this->ai_indexing_failed,
        ];
    }
}
