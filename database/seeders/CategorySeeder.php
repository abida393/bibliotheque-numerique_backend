<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $structure = [
            'Informatique' => ['Intelligence Artificielle', 'Réseaux', 'Développement Web', 'Bases de Données'],
            'Mathématiques' => ['Algèbre', 'Analyse', 'Probabilités et Statistiques'],
            'Physique' => ['Mécanique', 'Électromagnétisme'],
            'Gestion' => ['Comptabilité', 'Marketing', 'Management'],
            'Langues' => [], // catégorie racine sans enfants
        ];

        foreach ($structure as $parentName => $children) {
            $parent = Category::firstOrCreate(['name' => $parentName]);

            foreach ($children as $childName) {
                Category::firstOrCreate([
                    'name' => $childName,
                    'parent_id' => $parent->id,
                ]);
            }
        }
    }
}
