<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Illuminate\Database\Eloquent\Builder;

class Permission extends SpatiePermission
{
    protected $fillable = ['name', 'guard_name', 'super_admin_only', 'description'];

    protected $casts = [
        'super_admin_only' => 'boolean',
    ];

    /**
     * Get the tenants that have access to this permission
     */
    public function tenants()
    {
        return $this->belongsToMany(
            Tenant::class,
            'tenant_permissions',
            'permission_id',
            'tenant_id'
        )->withTimestamps();
    }

    /**
     * Scope to filter permissions for tenant admins (exclude super admin only)
     */
    public function scopeForTenantAdmin(Builder $query): Builder
    {
        return $query->where('super_admin_only', false);
    }

    /**
     * Scope to get super admin only permissions
     */
    public function scopeSuperAdminOnly(Builder $query): Builder
    {
        return $query->where('super_admin_only', true);
    }
}

