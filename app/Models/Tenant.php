<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'primary_contact_name',
        'primary_contact_email',
        'status',
        'trial_ends_at',
        'subscription_started_at',
        'settings',
        'mindwave_vector_store_id',
        'mindwave_memory_session_id',
        'mindwave_vector_last_synced_at',
        'mindwave_memory_last_synced_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_started_at' => 'datetime',
        'settings' => 'array',
        'mindwave_vector_last_synced_at' => 'datetime',
        'mindwave_memory_last_synced_at' => 'datetime',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(SevenLocation::class, 'tenant_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class, 'tenant_id');
    }

    public function owner()
    {
        return $this->hasOne(User::class, 'tenant_id')->where('is_tenant_owner', true);
    }

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'tenant_permissions',
            'tenant_id',
            'permission_id'
        )->withTimestamps();
    }

    public function menuItems()
    {
        return $this->belongsToMany(
            MenuItem::class,
            'tenant_menu_items',
            'tenant_id',
            'menu_item_id'
        )->withTimestamps();
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function trialExpired(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->isOnTrial();
    }

    public function trialDaysRemaining(): int
    {
        if (! $this->isOnTrial()) {
            return 0;
        }

        return now()->diffInDays($this->trial_ends_at, false);
    }

    /**
     * Check if tenant can access the system
     * Returns true if tenant is active or on valid trial
     * Returns false if suspended, cancelled, or trial expired
     */
    public function canAccess(): bool
    {
        // Active status always allows access
        if ($this->status === 'active') {
            return true;
        }

        // Trial status - check if trial is still valid
        if ($this->status === 'trial') {
            return $this->isOnTrial(); // Returns false if expired or no trial_ends_at
        }

        // Suspended or cancelled - no access
        if (in_array($this->status, ['suspended', 'cancelled'])) {
            return false;
        }

        // Unknown status - default to no access for safety
        return false;
    }

    /**
     * Get user-friendly message explaining why tenant cannot access
     */
    public function getAccessDeniedMessage(): string
    {
        if ($this->status === 'suspended') {
            return 'Your account has been suspended. Please contact support for assistance.';
        }

        if ($this->status === 'cancelled') {
            return 'Your account has been cancelled. Please contact support if you believe this is an error.';
        }

        if ($this->status === 'trial' && $this->trialExpired()) {
            $expiredDate = $this->trial_ends_at ? $this->trial_ends_at->format('F j, Y') : 'recently';
            return "Your trial period expired on {$expiredDate}. Please contact support to activate your account.";
        }

        return 'Your account is not active. Please contact support for assistance.';
    }
}

