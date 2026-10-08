<?php

namespace App\Models;

use App\Enums\CommentAnchor;
use App\Enums\CommentStatus;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $material_version_id
 * @property int|null $review_id
 * @property int|null $parent_id
 * @property int $user_id
 * @property CommentAnchor $anchor_type
 * @property int|null $page_number
 * @property int|null $timestamp_seconds
 * @property int|null $scorm_sco_id
 * @property string $body
 * @property CommentStatus $status
 * @property int|null $addressed_by
 * @property Carbon|null $addressed_at
 * @property-read Review|null $review
 * @property-read MaterialVersion $materialVersion
 * @property-read ReviewComment|null $parent
 */
#[Fillable(['material_version_id', 'review_id', 'parent_id', 'user_id', 'anchor_type', 'page_number', 'timestamp_seconds', 'scorm_sco_id', 'body', 'status', 'addressed_by', 'addressed_at'])]
class ReviewComment extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'anchor_type' => CommentAnchor::class,
            'status' => CommentStatus::class,
            'addressed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MaterialVersion, $this> */
    public function materialVersion(): BelongsTo
    {
        return $this->belongsTo(MaterialVersion::class);
    }

    /** @return BelongsTo<Review, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /** @return BelongsTo<ReviewComment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ReviewComment::class, 'parent_id');
    }

    /** @return HasMany<ReviewComment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(ReviewComment::class, 'parent_id')->oldest();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ScormSco, $this> */
    public function sco(): BelongsTo
    {
        return $this->belongsTo(ScormSco::class, 'scorm_sco_id');
    }

    /** @return BelongsTo<User, $this> */
    public function addressedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressed_by');
    }

    /**
     * Komentar induk (bukan balasan).
     *
     * @param  Builder<ReviewComment>  $query
     */
    public function scopeRoot(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Komentar yang boleh dilihat user: komentar dari reviu yang sudah disubmit,
     * atau komentar milik user itu sendiri (draft).
     *
     * @param  Builder<ReviewComment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->whereNull('review_id')
                ->orWhere('user_id', $user->id)
                ->orWhereHas('review', fn (Builder $r) => $r->where('status', ReviewStatus::Submitted->value));
        });
    }
}
