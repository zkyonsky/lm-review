<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Training;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'training_id' => Training::factory(),
            'code' => 'MP-'.fake()->numerify('##'),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'jp' => fake()->numberBetween(2, 12),
            'sort_order' => 0,
        ];
    }
}
