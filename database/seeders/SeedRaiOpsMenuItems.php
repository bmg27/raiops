<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class SeedRaiOpsMenuItems extends Seeder
{
    /**
     * Populate menu items for RAIOPS with proper hierarchy
     */
    public function run(): void
    {
        $this->command->info('🗑️  Clearing existing menu items...');
        MenuItem::query()->delete();
        $this->command->info('✅ Cleared existing menu items');
        
        // Get or create the main menu
        $menu = Menu::firstOrCreate([
            'name' => 'Main Menu'
        ]);
        
        $this->command->info('📝 Creating RAIOPS menu items...');
        
        // Helper function to get permission ID by name
        $getPermissionId = function($name) {
            $perm = Permission::where('name', $name)->first();
            return $perm ? $perm->id : null;
        };
        
        // Level 1: Top-level parents
        $adminParent = MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Admin',
            'url' => '#',
            'parent_id' => null,
            'icon' => 'gear',
            'order' => 10,
            'active' => 1,
            'super_admin_only' => false,
        ]);
        
        $tenantsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Tenants',
            'url' => '#',
            'parent_id' => null,
            'icon' => 'building',
            'order' => 20,
            'active' => 1,
            'super_admin_only' => false,
        ]);
        
        $systemParent = MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'System',
            'url' => '#',
            'parent_id' => null,
            'icon' => 'server',
            'order' => 30,
            'active' => 1,
            'super_admin_only' => false,
        ]);
        
        $reportsParent = MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Reports',
            'url' => '#',
            'parent_id' => null,
            'icon' => 'graph-up',
            'order' => 40,
            'active' => 1,
            'super_admin_only' => false,
        ]);
        
        // Level 2: Children under Admin - User Management (direct link)
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'User Management',
            'url' => '/um',
            'route' => 'manage.index',
            'parent_id' => $adminParent->id,
            'icon' => 'people',
            'order' => 10,
            'active' => 1,
            'permission_id' => $getPermissionId('user.manage'),
            'super_admin_only' => false,
        ]);
        
        // Level 2: Direct children under Admin (no grandchildren)
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'System Health',
            'url' => '/admin/health',
            'route' => 'admin.health',
            'parent_id' => $adminParent->id,
            'icon' => 'activity',
            'order' => 20,
            'active' => 1,
            'super_admin_only' => false,
        ]);
        
        // Level 2: Children under Tenants
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Manage Tenants',
            'url' => '/admin/tenants',
            'route' => 'admin.tenants',
            'parent_id' => $tenantsParent->id,
            'icon' => 'building',
            'order' => 10,
            'active' => 1,
            'permission_id' => $getPermissionId('tenant.view'),
            'super_admin_only' => false,
        ]);
        
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'User Routing',
            'url' => '/admin/user-routing',
            'route' => 'admin.user-routing',
            'parent_id' => $tenantsParent->id,
            'icon' => 'envelope',
            'order' => 20,
            'active' => 1,
            'permission_id' => $getPermissionId('user.view'),
            'super_admin_only' => false,
        ]);
        
        // Level 2: Children under System
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'RDS Instances',
            'url' => '/admin/rds',
            'route' => 'admin.rds',
            'parent_id' => $systemParent->id,
            'icon' => 'database',
            'order' => 10,
            'active' => 1,
            'permission_id' => $getPermissionId('rds.view'),
            'super_admin_only' => false,
        ]);
        
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Audit Logs',
            'url' => '/admin/audit-logs',
            'route' => 'admin.audit-logs',
            'parent_id' => $systemParent->id,
            'icon' => 'file-text',
            'order' => 20,
            'active' => 1,
            'permission_id' => $getPermissionId('audit.view'),
            'super_admin_only' => false,
        ]);
        
        // Level 2: Children under System - Schedule Management
        $scheduleChild = MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Schedule Management',
            'url' => '#',
            'parent_id' => $systemParent->id,
            'icon' => 'clock',
            'order' => 30,
            'active' => 1,
            'super_admin_only' => false,
        ]);
        
        // Level 3: Grandchildren under Schedule Management
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Schedule Runner',
            'url' => '/admin/schedule-runner',
            'route' => 'admin.schedule-runner',
            'parent_id' => $scheduleChild->id,
            'icon' => 'play-circle',
            'order' => 10,
            'active' => 1,
            'permission_id' => $getPermissionId('schedule.runner'),
            'super_admin_only' => false,
        ]);
        
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Schedule Config',
            'url' => '/admin/schedule-management',
            'route' => 'admin.schedule-management',
            'parent_id' => $scheduleChild->id,
            'icon' => 'gear',
            'order' => 20,
            'active' => 1,
            'permission_id' => $getPermissionId('schedule.runner'),
            'super_admin_only' => false,
        ]);
        
        // Level 2: Children under Reports
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Analytics',
            'url' => '/admin/analytics',
            'route' => 'admin.analytics',
            'parent_id' => $reportsParent->id,
            'icon' => 'graph-up-arrow',
            'order' => 10,
            'active' => 1,
            'permission_id' => $getPermissionId('reports.view'),
            'super_admin_only' => false,
        ]);
        
        // Level 2: Children under Admin - Billing
        $billingChild = MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Billing',
            'url' => '#',
            'parent_id' => $adminParent->id,
            'icon' => 'credit-card',
            'order' => 30,
            'active' => 1,
            'super_admin_only' => false,
        ]);
        
        // Level 3: Grandchildren under Billing
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Billing Management',
            'url' => '/admin/billing',
            'route' => 'admin.billing',
            'parent_id' => $billingChild->id,
            'icon' => 'wallet',
            'order' => 10,
            'active' => 1,
            'permission_id' => $getPermissionId('billing.view'),
            'super_admin_only' => false,
        ]);
        
        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Subscription Plans',
            'url' => '/admin/subscription-plans',
            'route' => 'admin.subscription-plans',
            'parent_id' => $billingChild->id,
            'icon' => 'tags',
            'order' => 20,
            'active' => 1,
            'permission_id' => $getPermissionId('billing.edit'),
            'super_admin_only' => false,
        ]);
        
        $this->command->info('✅ Created RAIOPS menu items with proper hierarchy');
        $this->command->info('   - Level 1 (Parents): 4 items');
        $this->command->info('   - Level 2 (Children): 8 items');
        $this->command->info('   - Level 3 (Grandchildren): 2 items (Schedule Management, Billing)');
    }
}

