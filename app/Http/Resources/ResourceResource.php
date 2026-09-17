<?php

namespace App\Http\Resources;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'authors' => $this->authors,
            'type' => $this->type,
            'language' => $this->language,
            'publication_date' => $this->publication_date?->format('Y-m-d'),
            'description' => $this->description,
            'status' => $this->status,
            'file_url' => Storage::url($this->file_path),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'author_user' => new UserResource($this->whenLoaded('user')),
            'reviews_count' => $this->when($this->relationLoaded('reviews'), fn() => $this->reviews->count()),
            'average_rating' => $this->when(
                $this->relationLoaded('reviews'),
                fn() => round($this->reviews->avg('rating'), 1)
            ),
            'created_at' => $this->created_at->format('Y-m-d'),
        ];
    }
}
