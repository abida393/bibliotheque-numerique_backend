<?php
// app/Http/Resources/MessageResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'cited_sources' => $this->cited_sources,
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
