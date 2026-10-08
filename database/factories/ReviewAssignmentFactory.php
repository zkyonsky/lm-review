<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Material;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewAssignment>
 */
class ReviewAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'reviewer_id' => User::factory(),
            'assigned_by' => User::factory(),
            'due_date' => now()->addWeeks(2)->toDateString(),
            'status' => AssignmentStatus::Pending,
        ];
    }
}
