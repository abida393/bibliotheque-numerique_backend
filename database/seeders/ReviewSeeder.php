<?php

namespace Database\Seeders;

use App\Models\Resource;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $validatedResources = Resource::where('status', 'validated')->get();
        $users = User::role('user')->get();

        if ($validatedResources->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Aucune ressource validée ou aucun utilisateur trouvé — vérifie l\'ordre des seeders.');
            return;
        }

        $comments = [
            'Très clair et bien structuré, merci !',
            'Contenu utile mais un peu dense par endroits.',
            'Exactement ce que je cherchais pour mon cours.',
            'Bonne ressource, quelques exemples supplémentaires seraient bienvenus.',
            null, // certains avis n'ont pas de commentaire, juste une note
            null,
        ];

        foreach ($validatedResources as $resource) {
            // Entre 0 et 4 avis par ressource, jamais deux fois le même utilisateur sur la même ressource
            $reviewers = $users->random(min(rand(0, 4), $users->count()));

            foreach ($reviewers as $reviewer) {
                Review::firstOrCreate(
                    [
                        'user_id' => $reviewer->id,
                        'resource_id' => $resource->id,
                    ],
                    [
                        'rating' => rand(2, 5),
                        'comment' => fake()->randomElement($comments),
                    ]
                );
            }
        }
    }
}
