<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedRaiOpsPermissions extends Seeder
{
    /**
     * Clear all existing permissions and create RAIOPS-specific permissions
     */
    public function run(): void
    {
        $this->command->info('🗑️  Clearing existing permissions...');
        
        // Delete all existing permissions (these are from RAI)
        DB::table('role_has_permissions')->delete();
        DB::table('model_has_permissions')->delete();
        Permission::query()->delete();
        
        $this->command->info('✅ Cleared existing permissions');
        
        $this->command->info('📝 Creating RAIOPS-specific permissions...');
        
        // RAIOPS-specific permissions based on routes
        $permissions = [
            // Tenants
            ['name' => 'tenant.view', 'description' => 'View tenant list and details'],
            ['name' => 'tenant.create', 'description' => 'Create new tenants'],
            ['name' => 'tenant.edit', 'description' => 'Edit tenant information'],
            ['name' => 'tenant.delete', 'description' => 'Delete tenants'],
            ['name' => 'tenant.impersonate', 'description' => 'Impersonate tenants'],
            
            // Users (RAIOPS admins)
            ['name' => 'user.view', 'description' => 'View RAIOPS admin users'],
            ['name' => 'user.edit', 'description' => 'Edit RAIOPS admin users'],
            ['name' => 'user.manage', 'description' => 'Manage users, roles, permissions, and menu items'],
            ['name' => 'user.delete', 'description' => 'Delete RAIOPS admin users'],
            
            // Billing
            ['name' => 'billing.view', 'description' => 'View billing information'],
            ['name' => 'billing.edit', 'description' => 'Edit billing and subscription plans'],
            
            // RDS Management
            ['name' => 'rds.view', 'description' => 'View RDS instances'],
            ['name' => 'rds.manage', 'description' => 'Manage RDS instances'],
            
            // System
            ['name' => 'audit.view', 'description' => 'View audit logs'],
            ['name' => 'reports.view', 'description' => 'View analytics and reports'],
            ['name' => 'reports.export', 'description' => 'Export reports'],
            
            // Schedule Management
            ['name' => 'schedule.runner', 'description' => 'Run and manage scheduled commands'],
        ];
        
        $created = 0;
        foreach ($permissions as $perm) {
            Permission::create([
                'name' => $perm['name'],
                'guard_name' => 'web',
                'description' => $perm['description'],
                'super_admin_only' => false, // All RAIOPS permissions are available to appropriate roles
            ]);
            $created++;
        }
        
        $this->command->info("✅ Created {$created} RAIOPS permissions");
        
        // Assign all permissions to Super Admin role (if it exists)
        $superAdminRole = \App\Models\Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $allPerms = Permission::all();
            $superAdminRole->syncPermissions($allPerms);
            $this->command->info("✅ Assigned all permissions to Super Admin role");
        }
    }
}

