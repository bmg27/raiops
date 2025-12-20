<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class CreateSuperAdminRoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates the Super Admin role and tenant.manage permission
     */
    public function run(): void
    {
        // Create Super Admin role (global, no tenant_id)
        $superAdminRole = Role::firstOrCreate(
            [
                'name' => 'Super Admin',
                'guard_name' => 'web',
                'tenant_id' => null,
            ]
        );

        $this->command->info("✓ Super Admin role created/found (ID: {$superAdminRole->id})");

        // Create tenant.manage permission
        $tenantManagePermission = Permission::firstOrCreate(
            [
                'name' => 'tenant.manage',
                'guard_name' => 'web',
            ],
            [
                'super_admin_only' => true,
                'description' => 'Manage tenants in the back office',
            ]
        );

        $this->command->info("✓ tenant.manage permission created/found (ID: {$tenantManagePermission->id})");

        // Assign permission to Super Admin role
        if (!$superAdminRole->hasPermissionTo($tenantManagePermission)) {
            $superAdminRole->givePermissionTo($tenantManagePermission);
            $this->command->info("✓ Assigned tenant.manage permission to Super Admin role");
        }

        $this->command->info("\n✅ Super Admin role and permissions configured!");
    }
}

