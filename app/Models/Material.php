<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\MaterialStatus;
use App\Enums\MaterialType;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $subject_id
 * @property MaterialType $type
 * @property MaterialStatus $status
 * @property int|null $current_version_id
 * @property int $sort_order
 * @property int $created_by
 * @property-read string $title
 * @property-read int $order
 * @property-read Subject $subject
 * @property-read MaterialVersion|null $currentVersion
 */
#[Fillable(['subject_id', 'type', 'status', 'current_version_id', 'sort_order', 'created_by'])]
class Material extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $appends = ['order', 'title'];

    protected function order(): Attribute
    {
        return Attribute::get(fn () => $this->sort_order);
    }

    protected function title(): Attribute
    {
        return Attribute::get(function () {
            $typeLabel = $this->type instanceof MaterialType ? $this->type->label() : strtoupper((string) ($this->type ?? 'Materi'));
            return 'Materi ' . $typeLabel . ($this->sort_order ? " #{$this->sort_order}" : '');
        });
    }

    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'status' => MaterialStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'current_version_id', 'type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return HasMany<MaterialVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(MaterialVersion::class)->orderByDesc('version_number');
    }

    /** @return BelongsTo<MaterialVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(MaterialVersion::class, 'current_version_id');
    }

    /** @return HasMany<ReviewAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'review_assignments', 'material_id', 'reviewer_id')
            ->withPivot(['id', 'status', 'due_date'])
            ->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Materi yang ditugaskan ke reviewer tertentu.
     *
     * @param  Builder<Material>  $query
     */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->whereHas('assignments', fn (Builder $q) => $q->where('reviewer_id', $user->id));
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignments()->where('reviewer_id', $user->id)->exists();
    }

    /**
     * Scope query untuk materi yang telah direviu.
     *
     * @param  Builder<Material>  $query
     * @param  int|null  $periodId
     */
    public function scopeReviewed(Builder $query, ?int $periodId = null): void
    {
        $query->where(function (Builder $sub) use ($periodId) {
            if ($periodId) {
                $sub->whereHas('assignments', function (Builder $a) use ($periodId) {
                    $a->where('status', AssignmentStatus::Submitted)
                        ->where('review_period_id', $periodId);
                })->orWhereHas('versions.reviews', function (Builder $r) use ($periodId) {
                    $r->where('status', ReviewStatus::Submitted)
                        ->whereHas('assignment', fn (Builder $a) => $a->where('review_period_id', $periodId));
                });
            } else {
                $sub->whereIn('materials.status', [MaterialStatus::Reviewed, MaterialStatus::Completed])
                    ->orWhereHas('assignments', fn (Builder $a) => $a->where('status', AssignmentStatus::Submitted))
                    ->orWhereHas('versions.reviews', fn (Builder $r) => $r->where('status', ReviewStatus::Submitted));
            }
        });
    }
}
