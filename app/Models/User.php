<?php

namespace App\Models;


use DateTimeInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */

class User extends Authenticatable
{
    public const RESTRICTED_INTERNAL_BENEFICIARY_ROLES = ['Admin', 'Super Admin'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;
    
    protected $fillable = [
        'fullname',
        'email',
        'password',
        'department_id',
        'is_active'
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    public function hasRole(string $name): bool
    {
        return $this->roles()->where('name', $name)->exists();
    }

    public function isEligibleInternalBeneficiary(): bool
    {
        return ! $this->roles()
            ->whereIn('roles.name', self::RESTRICTED_INTERNAL_BENEFICIARY_ROLES)
            ->exists();
    }

    public function scopeEligibleInternalBeneficiaries(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'roles',
            fn (Builder $roles): Builder => $roles->whereIn(
                'roles.name',
                self::RESTRICTED_INTERNAL_BENEFICIARY_ROLES,
            ),
        );
    }

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
        ];
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('d/m/Y H:i:s');
    }

    // Relationships

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // Relationships with AccessRequest and related models

    public function requests()
    {
        return $this->hasMany(AccessRequest::class, 'requested_by');
    }

    public function internalBeneficiaryRequests()
    {
        return $this->hasMany(AccessRequest::class, 'internal_beneficiary');
    }

    public function comments()
    {
        return $this->hasMany(AccessRequestComment::class, 'commented_by');
    }

    public function approvals()
    {
        return $this->hasMany(AccessRequestApproval::class, 'approver_id');
    }

    public function moduleApprovers()
    {
        return $this->hasMany(ModuleApprover::class, 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_has_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }
}
