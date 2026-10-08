<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $review_assignment_id
 * @property int $material_version_id
 * @property int $reviewer_id
 * @property-read int $user_id
 * @property ReviewStatus $status
 * @property string|null $general_notes
 * @property Carbon|null $submitted_at
 * @property-read ReviewAssignment $assignment
 * @property-read MaterialVersion $materialVersion
 * @property-read User $reviewer
 * @property-read User $user
 */
#[Fillable(['review_assignment_id', 'material_version_id', 'reviewer_id', 'status', 'general_notes', 'submitted_at'])]
class Review extends Model
{
    use HasFactory;

    protected $appends = ['user_id'];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    protected function userId(): Attribute
    {
        return Attribute::get(fn () => $this->reviewer_id);
    }

    /** @return BelongsTo<ReviewAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewAssignment::class, 'review_assignment_id');
    }

    /** @return BelongsTo<MaterialVersion, $this> */
    public function materialVersion(): BelongsTo
    {
        return $this->belongsTo(MaterialVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return HasMany<ReviewComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }

    public function isSubmitted(): bool
    {
        return $this->status === ReviewStatus::Submitted;
    }
}
