<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmailRoutingCache extends Model
{
    protected $table = 'user_email_routing_cache';

    protected $fillable = [
        'email',
        'rds_instance_id',
        'tenant_master_id',
        'remote_user_id',
        'user_name',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    /**
     * Relationship: RDS Instance
     */
    public function rdsInstance(): BelongsTo
    {
        return $this->belongsTo(RdsInstance::class, 'rds_instance_id');
    }

    /**
     * Relationship: Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantMaster::class, 'tenant_master_id');
    }

    /**
     * Check if this routing is active
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'Active' => 'bg-success',
            'Inactive' => 'bg-secondary',
            'Pending' => 'bg-warning',
            default => 'bg-secondary',
        };
    }

    /**
     * Check if cache is stale
     */
    public function isStale(int $minutes = 30): bool
    {
        if (!$this->synced_at) {
            return true;
        }

        return $this->synced_at->diffInMinutes(now()) > $minutes;
    }

    /**
     * Scope: By email
     */
    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope: Active only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope: Search
     */
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('email', 'like', "%{$search}%")
                ->orWhere('user_name', 'like', "%{$search}%");
        });
    }

    /**
     * Static: Find all routing entries for an email
     */
    public static function findAllByEmail(string $email)
    {
        return static::where('email', $email)->get();
    }
}

