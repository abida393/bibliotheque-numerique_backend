<?php

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;

class ResourcePolicy
{
    public function update(User $user, Resource $resource): bool
    {
        // Le propriétaire peut modifier sa ressource tant qu'elle est en attente
        if ($resource->user_id === $user->id && $resource->status === 'pending') {
            return true;
        }

        // Les modérateurs/admins peuvent toujours modifier
        return $user->hasAnyRole(['moderator', 'admin']);
    }

    public function delete(User $user, Resource $resource): bool
    {
        if ($resource->user_id === $user->id && $resource->status === 'pending') {
            return true;
        }

        return $user->hasAnyRole(['moderator', 'admin']);
    }
}
