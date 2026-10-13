<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::whereNotNull('parent_id')->get(); // sous-catégories seulement
        $users = User::role('user')->get();
        $types = ['pdf', 'word', 'powerpoint'];
        $languages = ['fr', 'en', 'ar'];

        if ($categories->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Aucune catégorie ou aucun utilisateur trouvé — lance CategorySeeder et UserSeeder avant ResourceSeeder.');
            return;
        }

        $titles = [
            'Introduction aux algorithmes',
            'Cours complet de bases de données relationnelles',
            'Réseaux informatiques : principes et pratique',
            'Machine Learning pour débutants',
            'Analyse mathématique - Tome 1',
            'Statistiques appliquées à la gestion',
            'Mécanique du point matériel',
            'Électromagnétisme - Cours et exercices',
            'Comptabilité générale : les fondamentaux',
            'Techniques de marketing digital',
            'Développement Web avec React',
            'Sécurité des réseaux : bonnes pratiques',
            'Probabilités : cours et exercices corrigés',
            'Management d\'équipe et leadership',
            'Algèbre linéaire appliquée',
        ];

        // On répartit volontairement les statuts pour avoir des données de test variées :
        // 60% validées, 25% en attente, 15% rejetées
        $statusPool = array_merge(
            array_fill(0, 12, 'validated'),
            array_fill(0, 5, 'pending'),
            array_fill(0, 3, 'rejected'),
        );

        foreach ($titles as $index => $title) {
            $status = $statusPool[$index % count($statusPool)];
            $fakeContent = Str::random(40) . $title; // garantit un hash unique par ressource
            $type = fake()->randomElement($types);
            $extension = match ($type) {
                'pdf' => 'pdf',
                'word' => 'docx',
                'powerpoint' => 'pptx',
            };

            Resource::create([
                'title' => $title,
                'authors' => fake('fr_FR')->name(),
                'type' => $type,
                'category_id' => $categories->random()->id,
                'user_id' => $users->random()->id,
                'language' => fake()->randomElement($languages),
                'publication_date' => fake()->dateTimeBetween('-3 years', 'now'),
                'description' => fake('fr_FR')->paragraph(3),
                'file_path' => "resources/seed-{$index}.{$extension}",
                'file_hash' => hash('sha256', $fakeContent),
                'status' => $status,
            ]);
        }
    }
}
