<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSelf  = $request->user()?->id === $this->id;
        $isStaff = $request->user()?->hasAnyRole(['moderator', 'admin']);

        return [
            'id'         => $this->id,
            'name'       => $this->name,
            // Own email is always visible to the owner; admin/moderator sees all emails
            'email'      => $this->when($isSelf || $isStaff, $this->email),
            'department' => $this->department,
            'roles'      => $this->getRoleNames(),
            'resources_count' => $this->whenCounted('resources'),
            'favorites_count' => $this->whenCounted('favorites'),
            'reviews_count'   => $this->whenCounted('reviews'),
        ];
    }
}
