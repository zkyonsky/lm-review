<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property int|null $year
 * @property string $status
 * @property int $created_by
 */
#[Fillable(['title', 'year', 'status', 'created_by'])]
class Training extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SEDANG_REVIEW = 'Sedang Review';
    public const STATUS_SELESAI_REVIEW = 'Selesai Review';

    public const STATUSES = [
        self::STATUS_SEDANG_REVIEW,
        self::STATUS_SELESAI_REVIEW,
    ];

    protected static function booted(): void
    {
        static::deleting(function (Training $training) {
            if ($training->isForceDeleting()) {
                foreach ($training->subjects()->withTrashed()->get() as $subject) {
                    $subject->forceDelete();
                }
            } else {
                foreach ($training->subjects as $subject) {
                    $subject->delete();
                }
            }
        });

        static::restoring(function (Training $training) {
            foreach ($training->subjects()->withTrashed()->get() as $subject) {
                $subject->restore();
            }
        });
    }

    /** @return HasMany<Subject, $this> */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class)->orderBy('sort_order');
    }

    /** @return HasManyThrough<Material, Subject, $this> */
    public function materials(): HasManyThrough
    {
        return $this->hasManyThrough(Material::class, Subject::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<ReviewPeriod, $this> */
    public function periods(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ReviewPeriod::class, 'review_period_training')
            ->withTimestamps();
    }

    public function scopeInActivePeriod(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereHas('periods', fn ($q) => $q->where('status', \App\Enums\ReviewPeriodStatus::Active));
    }

    public function getReviewStatus(?int $periodId = null): string
    {
        $total = $this->materials_count ?? $this->materials()->count();
        if ($total === 0) {
            return 'Belum Ada Materi';
        }

        $reviewed = $this->reviewed_materials_count ?? $this->materials()->reviewed($periodId)->count();
        if ($reviewed >= $total) {
            return 'Selesai Reviu';
        }

        if ($reviewed > 0) {
            return 'Sedang Direviu';
        }

        return 'Belum Direviu';
    }
}
