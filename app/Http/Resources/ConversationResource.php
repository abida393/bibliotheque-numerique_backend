<?php
// app/Http/Resources/ConversationResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resource_id' => $this->resource_id,
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
