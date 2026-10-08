<?php

namespace Database\Factories;

use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Training>
 */
class TrainingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'PLT-'.fake()->unique()->numerify('####'),
            'title' => 'Pelatihan '.fake()->words(3, true),
            'description' => fake()->paragraph(),
            'year' => (int) now()->format('Y'),
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }
}
