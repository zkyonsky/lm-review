<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $nip
 * @property string|null $unit_kerja
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'nip', 'unit_kerja', 'is_active', 'password', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    public function isDeveloper(): bool
    {
        return $this->hasRole(Role::Developer->value);
    }

    public function isReviewer(): bool
    {
        return $this->hasRole(Role::Reviewer->value);
    }

    /**
     * Admin & pengembang materi dapat mengelola konten.
     */
    public function canManageContent(): bool
    {
        return $this->hasAnyRole([Role::Admin->value, Role::Developer->value]);
    }

    /** @return HasMany<ReviewAssignment, $this> */
    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class, 'reviewer_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Subject, $this> */
    public function assignedSubjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_user')
            ->withPivot(['id', 'role', 'assigned_by'])
            ->withTimestamps();
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Subject, $this> */
    public function developerSubjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_user')
            ->wherePivot('role', 'pengembang')
            ->withPivot(['id', 'role', 'assigned_by'])
            ->withTimestamps();
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Subject, $this> */
    public function reviewerSubjects(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_user')
            ->wherePivot('role', 'reviewer')
            ->withPivot(['id', 'role', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
