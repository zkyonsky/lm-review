<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $training_id
 * @property string $title
 * @property int $sort_order
 * @property-read int $order
 * @property-read Training $training
 */
#[Fillable(['training_id', 'title', 'sort_order'])]
class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['order'];

    protected function order(): Attribute
    {
        return Attribute::get(fn () => $this->sort_order);
    }

    protected static function booted(): void
    {
        static::deleting(function (Subject $subject) {
            if ($subject->isForceDeleting()) {
                foreach ($subject->materials()->withTrashed()->get() as $material) {
                    $material->forceDelete();
                }
            } else {
                foreach ($subject->materials as $material) {
                    $material->delete();
                }
            }
        });

        static::restoring(function (Subject $subject) {
            foreach ($subject->materials()->withTrashed()->get() as $material) {
                $material->restore();
            }
        });
    }

    /** @return BelongsTo<Training, $this> */
    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    /** @return HasMany<Material, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_user')
            ->withPivot(['id', 'role', 'assigned_by'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function developers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_user')
            ->wherePivot('role', 'pengembang')
            ->withPivot(['id', 'role', 'assigned_by'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_user')
            ->wherePivot('role', 'reviewer')
            ->withPivot(['id', 'role', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * Scope query untuk mata pelatihan pada pelatihan yang berada di periode reviu aktif.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Subject>  $query
     */
    public function scopeInActivePeriod(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereHas('training.periods', fn ($q) => $q->where('status', \App\Enums\ReviewPeriodStatus::Active));
    }
}
