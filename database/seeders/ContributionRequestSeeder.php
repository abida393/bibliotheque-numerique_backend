<?php

namespace Database\Seeders;

use App\Models\ContributionRequest;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContributionRequestSeeder extends Seeder
{
    public function run(): void
    {
        $moderators = User::role(['moderator', 'admin'])->get();

        if ($moderators->isEmpty()) {
            $this->command->warn('Aucun modérateur/admin trouvé — lance UserSeeder avant ContributionRequestSeeder.');
            return;
        }

        Resource::all()->each(function (Resource $resource) use ($moderators) {
            // Évite les doublons si le seeder est relancé
            if ($resource->contributionRequest()->exists()) {
                return;
            }

            $isProcessed = $resource->status !== 'pending';

            ContributionRequest::create([
                'resource_id' => $resource->id,
                'moderator_id' => $isProcessed ? $moderators->random()->id : null,
                'status' => $resource->status, // reste cohérent avec resources.status
                'comment' => $resource->status === 'rejected'
                    ? 'Contenu jugé redondant avec une ressource existante dans la même catégorie.'
                    : null,
                'processed_at' => $isProcessed ? now()->subDays(rand(1, 30)) : null,
            ]);
        });
    }
}
