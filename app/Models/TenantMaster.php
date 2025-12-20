<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantMaster extends Model
{
    protected $table = 'tenant_master';

    protected $fillable = [
        'rds_instance_id',
        'remote_tenant_id',
        'name',
        'primary_contact_name',
        'primary_contact_email',
        'status',
        'trial_ends_at',
        'subscription_started_at',
        'cached_user_count',
        'cached_location_count',
        'cached_last_activity_at',
        'cache_refreshed_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_started_at' => 'datetime',
        'cached_last_activity_at' => 'datetime',
        'cache_refreshed_at' => 'datetime',
        'cached_user_count' => 'integer',
        'cached_location_count' => 'integer',
    ];

    /**
     * Relationship: RDS Instance this tenant is on
     */
    public function rdsInstance(): BelongsTo
    {
        return $this->belongsTo(RdsInstance::class, 'rds_instance_id');
    }

    /**
     * Relationship: Billing information
     */
    public function billing(): HasOne
    {
        return $this->hasOne(TenantBilling::class, 'tenant_master_id');
    }

    /**
     * Relationship: User routing entries
     */
    public function userRoutingCache(): HasMany
    {
        return $this->hasMany(UserEmailRoutingCache::class, 'tenant_master_id');
    }

    /**
     * Check if tenant is on trial
     */
    public function isOnTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if trial has expired
     */
    public function trialExpired(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isPast();
    }

    /**
     * Check if tenant is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' || $this->isOnTrial();
    }

    /**
     * Get trial days remaining
     */
    public function trialDaysRemaining(): int
    {
        if (!$this->isOnTrial()) {
            return 0;
        }

        return now()->diffInDays($this->trial_ends_at, false);
    }

    /**
     * Check if tenant can access the system
     */
    public function canAccess(): bool
    {
        if ($this->status === 'active') {
            return true;
        }

        if ($this->status === 'trial') {
            return $this->isOnTrial();
        }

        return false;
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'active' => 'bg-success',
            'trial' => $this->trialExpired() ? 'bg-warning' : 'bg-info',
            'suspended' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    /**
     * Get display status (includes trial info)
     */
    public function getDisplayStatus(): string
    {
        if ($this->status === 'trial') {
            if ($this->trialExpired()) {
                return 'Trial Expired';
            }
            $days = $this->trialDaysRemaining();
            return "Trial ({$days} days left)";
        }

        return ucfirst($this->status);
    }

    /**
     * Check if cached data is stale (older than threshold)
     */
    public function isCacheStale(int $minutes = 30): bool
    {
        if (!$this->cache_refreshed_at) {
            return true;
        }

        return $this->cache_refreshed_at->diffInMinutes(now()) > $minutes;
    }

    /**
     * Scope: Active tenants only
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'trial']);
    }

    /**
     * Scope: By RDS instance
     */
    public function scopeOnRds($query, int $rdsInstanceId)
    {
        return $query->where('rds_instance_id', $rdsInstanceId);
    }

    /**
     * Scope: Search by name or email
     */
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('primary_contact_email', 'like', "%{$search}%")
                ->orWhere('primary_contact_name', 'like', "%{$search}%");
        });
    }
}

