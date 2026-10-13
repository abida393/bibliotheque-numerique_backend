<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ResourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'authors' => fake()->name(),
            'type' => fake()->randomElement(['pdf', 'word', 'powerpoint']),
            'category_id' => Category::factory(),
            'user_id' => User::factory(),
            'parent_resource_id' => null,
            'language' => 'fr',
            'publication_date' => fake()->date(),
            'description' => fake()->paragraph(),
            'file_path' => 'resources/test-' . Str::random(10) . '.pdf',
            'file_hash' => hash('sha256', Str::random(40)),
            'status' => 'validated',
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'rejected']);
    }
}
