<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // --- Comptes fixes, faciles à réutiliser dans Postman ---
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin Principal',
                'password' => Hash::make('password123'),
                'department' => 'Direction',
            ]
        );
        $admin->syncRoles(['admin']);

        $moderator = User::firstOrCreate(
            ['email' => 'moderateur@test.com'],
            [
                'name' => 'Modérateur Test',
                'password' => Hash::make('password123'),
                'department' => 'Bibliothèque',
            ]
        );
        $moderator->syncRoles(['moderator']);

        $user1 = User::firstOrCreate(
            ['email' => 'yassine@test.com'],
            [
                'name' => 'Yassine El Amrani',
                'password' => Hash::make('password123'),
                'department' => 'Informatique',
            ]
        );
        $user1->syncRoles(['user']);

        $user2 = User::firstOrCreate(
            ['email' => 'sara@test.com'],
            [
                'name' => 'Sara Bennani',
                'password' => Hash::make('password123'),
                'department' => 'Mathématiques',
            ]
        );
        $user2->syncRoles(['user']);

        // --- Utilisateurs supplémentaires générés aléatoirement ---
        $departments = ['Informatique', 'Mathématiques', 'Physique', 'Réseaux', 'Gestion'];

        for ($i = 1; $i <= 10; $i++) {
            $u = User::firstOrCreate(
                ['email' => "etudiant{$i}@test.com"],
                [
                    'name' => fake('fr_FR')->name(),
                    'password' => Hash::make('password123'),
                    'department' => fake()->randomElement($departments),
                ]
            );
            $u->syncRoles(['user']);
        }
    }
}
