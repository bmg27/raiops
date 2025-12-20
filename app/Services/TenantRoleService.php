<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantRoleService
{
    /**
     * Create or get the Account Owner Primary role for a tenant with all non-super-admin permissions
     *
     * @param int $tenantId
     * @return Role
     */
    public static function createOrGetTenantAdminRole(int $tenantId): Role
    {
        $roleName = 'Account Owner Primary';
        
        // Check if Account Owner Primary role already exists for this tenant
        $adminRole = Role::where('name', $roleName)
            ->where('tenant_id', $tenantId)
            ->where('guard_name', 'web')
            ->first();

        if ($adminRole) {
            // Ensure permissions are up to date
            $permissions = Permission::where('super_admin_only', false)->get();
            if ($permissions->isNotEmpty()) {
                $adminRole->syncPermissions($permissions);
            }
            return $adminRole;
        }

        // Create the Account Owner Primary role for this tenant
        $adminRole = Role::firstOrCreate(
            [
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
            ]
        );

        // Get all permissions that are NOT super admin only
        $permissions = Permission::where('super_admin_only', false)->get();

        // Assign all non-super-admin permissions to the Account Owner Primary role
        if ($permissions->isNotEmpty()) {
            $adminRole->syncPermissions($permissions);
            Log::info("Created Account Owner Primary role for tenant {$tenantId} with {$permissions->count()} permissions");
        } else {
            Log::warning("Created Account Owner Primary role for tenant {$tenantId} but no permissions were found");
        }

        return $adminRole;
    }

    /**
     * Update permissions for an existing tenant Account Owner Primary role
     * Useful if new permissions are added to the system
     *
     * @param int $tenantId
     * @return Role
     */
    public static function updateTenantAdminPermissions(int $tenantId): Role
    {
        $adminRole = Role::where('name', 'Account Owner Primary')
            ->where('tenant_id', $tenantId)
            ->where('guard_name', 'web')
            ->firstOrFail();

        // Get all permissions that are NOT super admin only
        $permissions = Permission::where('super_admin_only', false)->get();

        // Sync permissions (add new ones, remove ones that became super-admin-only)
        $adminRole->syncPermissions($permissions);

        Log::info("Updated Account Owner Primary role permissions for tenant {$tenantId} to {$permissions->count()} permissions");

        return $adminRole;
    }
}

