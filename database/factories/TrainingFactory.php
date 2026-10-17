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
            'title' => 'Pelatihan '.fake()->words(3, true),
            'year' => (int) now()->format('Y'),
            'status' => 'Sedang Review',
            'created_by' => User::factory(),
        ];
    }
}
