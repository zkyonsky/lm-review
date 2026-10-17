<?php

namespace Database\Factories;

use App\Enums\ReviewPeriodStatus;
use App\Models\ReviewPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewPeriod>
 */
class ReviewPeriodFactory extends Factory
{
    protected $model = ReviewPeriod::class;

    public function definition(): array
    {
        return [
            'name' => 'Periode Reviu '.fake()->words(2, true),
            'year' => (int) now()->format('Y'),
            'status' => ReviewPeriodStatus::Active,
            'description' => fake()->sentence(),
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'created_by' => User::factory(),
        ];
    }
}
