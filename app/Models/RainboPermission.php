<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RainboPermission extends Model
{
    protected $table = 'rainbo_permissions';

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
        return \DB::table('rainbo_role_permissions')
            ->join('rainbo_permissions', 'rainbo_permissions.id', '=', 'rainbo_role_permissions.permission_id')
            ->where('rainbo_role_permissions.role', $role)
            ->pluck('rainbo_permissions.name')
            ->toArray();
    }

    /**
     * Check if a role has a specific permission
     */
    public static function roleHasPermission(string $role, string $permission): bool
    {
        return \DB::table('rainbo_role_permissions')
            ->join('rainbo_permissions', 'rainbo_permissions.id', '=', 'rainbo_role_permissions.permission_id')
            ->where('rainbo_role_permissions.role', $role)
            ->where('rainbo_permissions.name', $permission)
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

