<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasRoles;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

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
            'is_super_admin' => 'boolean',
            'is_tenant_owner' => 'boolean',
        ];
    }

    // RAIOPS users don't have tenant or location relationships
    // All users are RAIOPS admins

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isTenantOwner(): bool
    {
        return (bool) $this->is_tenant_owner;
    }

    /**
     * Check if user is a system admin (highest level in RAIOPS)
     */
    public function isSystemAdmin(): bool
    {
        return $this->role === 'system_admin';
    }

    /**
     * Check if user has a specific RAIOPS permission
     */
    public function hasRaiOpsPermission(string $permission): bool
    {
        // System admins have all permissions
        if ($this->isSystemAdmin()) {
            return true;
        }

        return RaiOpsPermission::roleHasPermission($this->role ?? 'read_only', $permission);
    }

    /**
     * Get all permissions for this user's role
     */
    public function getRaiOpsPermissions(): array
    {
        return RaiOpsPermission::forRole($this->role ?? 'read_only');
    }

    /**
     * Check if user can perform an action (alias for hasRaiOpsPermission)
     */
    public function can($ability, $arguments = []): bool
    {
        // Super admins can do everything (like RAI)
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check RAIOPS permissions
        if ($this->hasRaiOpsPermission($ability)) {
            return true;
        }

        // Fall back to Spatie permissions
        return parent::can($ability, $arguments);
    }

    /**
     * Get role display name
     */
    public function getRoleDisplayName(): string
    {
        return match ($this->role) {
            'system_admin' => 'System Admin',
            'support_admin' => 'Support Admin',
            'billing_admin' => 'Billing Admin',
            'read_only' => 'Read Only',
            default => 'Unknown',
        };
    }

    /**
     * Get role badge class for UI
     */
    public function getRoleBadgeClass(): string
    {
        return match ($this->role) {
            'system_admin' => 'bg-danger',
            'support_admin' => 'bg-primary',
            'billing_admin' => 'bg-success',
            'read_only' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }
}
