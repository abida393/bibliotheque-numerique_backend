<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConsultationSeeder extends Seeder
{
    public function run(): void
    {
        $validatedResources = Resource::where('status', 'validated')->get();
        $users = User::role('user')->get();

        if ($validatedResources->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Aucune ressource validée ou aucun utilisateur trouvé — vérifie l\'ordre des seeders.');
            return;
        }

        foreach ($validatedResources as $resource) {
            // Nombre de consultations volontairement inégal, pour que le tri "popularité" ait du sens
            $consultationCount = rand(0, 25);

            for ($i = 0; $i < $consultationCount; $i++) {
                Consultation::create([
                    'user_id' => $users->random()->id,
                    'resource_id' => $resource->id,
                    'consulted_at' => now()->subDays(rand(0, 60))->subHours(rand(0, 23)),
                ]);
            }
        }
    }
}
