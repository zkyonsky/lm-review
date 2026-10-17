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
            'title' => fake()->sentence(4),
            'sort_order' => 0,
        ];
    }
}
