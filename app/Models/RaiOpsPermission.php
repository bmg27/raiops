<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaiOpsPermission extends Model
{
    protected $table = 'raiops_permissions';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'group_name',
    ];

    /**
     * Get permissions for a specific role
     */
    public static function forRole(string $role): array
    {
        return \DB::table('raiops_role_permissions')
            ->join('raiops_permissions', 'raiops_permissions.id', '=', 'raiops_role_permissions.permission_id')
            ->where('raiops_role_permissions.role', $role)
            ->pluck('raiops_permissions.name')
            ->toArray();
    }

    /**
     * Check if a role has a specific permission
     */
    public static function roleHasPermission(string $role, string $permission): bool
    {
        return \DB::table('raiops_role_permissions')
            ->join('raiops_permissions', 'raiops_permissions.id', '=', 'raiops_role_permissions.permission_id')
            ->where('raiops_role_permissions.role', $role)
            ->where('raiops_permissions.name', $permission)
            ->exists();
    }

    /**
     * Get all permissions grouped by group_name
     */
    public static function grouped(): array
    {
        return static::orderBy('group_name')
            ->orderBy('name')
            ->get()
            ->groupBy('group_name')
            ->toArray();
    }

    /**
     * Scope: By group
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group_name', $group);
    }
}

