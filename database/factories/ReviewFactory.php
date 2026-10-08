<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'review_assignment_id' => ReviewAssignment::factory(),
            'material_version_id' => MaterialVersion::factory(),
            'reviewer_id' => User::factory(),
            'status' => ReviewStatus::Draft,
            'general_notes' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state([
            'status' => ReviewStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
