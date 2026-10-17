<?php

namespace App\Models;

use App\Enums\ReviewPeriodStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $year
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property ReviewPeriodStatus $status
 * @property string|null $description
 * @property int $created_by
 * @property-read User $creator
 */
#[Fillable(['name', 'year', 'start_date', 'end_date', 'status', 'description', 'created_by'])]
class ReviewPeriod extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ReviewPeriodStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<Training, $this> */
    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class, 'review_period_training')
            ->withTimestamps();
    }

    /** @return HasMany<ReviewAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class);
    }

    /**
     * Scope untuk periode yang sedang aktif.
     *
     * @param Builder<ReviewPeriod> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ReviewPeriodStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === ReviewPeriodStatus::Active;
    }
}
