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
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property int|null $year
 * @property string $status
 * @property int $created_by
 */
#[Fillable(['code', 'title', 'description', 'year', 'status', 'created_by'])]
class Training extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SEDANG_REVIEW = 'Sedang Review';
    public const STATUS_SELESAI_REVIEW = 'Selesai Review';

    public const STATUSES = [
        self::STATUS_SEDANG_REVIEW,
        self::STATUS_SELESAI_REVIEW,
    ];

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
}
