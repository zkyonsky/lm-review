<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $material_id
 * @property int $reviewer_id
 * @property int $assigned_by
 * @property Carbon|null $due_date
 * @property AssignmentStatus $status
 * @property-read Material $material
 * @property-read User $reviewer
 */
#[Fillable(['material_id', 'reviewer_id', 'assigned_by', 'due_date', 'status'])]
class ReviewAssignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'status' => AssignmentStatus::class,
        ];
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== AssignmentStatus::Submitted
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }
}
