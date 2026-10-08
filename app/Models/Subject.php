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
 * @property string|null $code
 * @property string $title
 * @property string|null $description
 * @property int|null $jp
 * @property int $sort_order
 * @property-read int $order
 * @property-read Training $training
 */
#[Fillable(['training_id', 'code', 'title', 'description', 'jp', 'sort_order'])]
class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['order'];

    protected function order(): Attribute
    {
        return Attribute::get(fn () => $this->sort_order);
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
}
