<?php

namespace Database\Seeders;

use App\Models\Favorite;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $validatedResources = Resource::where('status', 'validated')->get();
        $users = User::role('user')->get();

        if ($validatedResources->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Aucune ressource validée ou aucun utilisateur trouvé — vérifie l\'ordre des seeders.');
            return;
        }

        foreach ($users as $user) {
            // Chaque utilisateur met entre 0 et 5 ressources aléatoires en favori
            $favorites = $validatedResources->random(min(rand(0, 5), $validatedResources->count()));

            foreach ($favorites as $resource) {
                Favorite::firstOrCreate([
                    'user_id' => $user->id,
                    'resource_id' => $resource->id,
                ]);
            }
        }
    }
}
