<?php

namespace Database\Factories;

use App\Enums\CommentAnchor;
use App\Enums\CommentStatus;
use App\Models\MaterialVersion;
use App\Models\ReviewComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewComment>
 */
class ReviewCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'material_version_id' => MaterialVersion::factory(),
            'review_id' => null,
            'parent_id' => null,
            'user_id' => User::factory(),
            'anchor_type' => CommentAnchor::General,
            'body' => fake()->paragraph(),
            'status' => CommentStatus::Open,
        ];
    }
}
