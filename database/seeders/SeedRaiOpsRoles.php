<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class SeedRaiOpsRoles extends Seeder
{
    /**
     * Populate roles for RAIOPS
     */
    public function run(): void
    {
        $this->command->info('🗑️  Clearing existing roles...');
        Role::query()->delete();
        $this->command->info('✅ Cleared existing roles');
        
        $this->command->info('📝 Creating RAIOPS roles...');
        
        // System Admin - Full access to everything
        $systemAdmin = Role::create([
            'name' => 'system_admin',
            'guard_name' => 'web',
        ]);
        $this->command->info("  ✓ Created role: {$systemAdmin->name}");
        
        // Support Admin - Can manage tenants, users, schedule runner, but not billing
        $supportAdmin = Role::create([
            'name' => 'support_admin',
            'guard_name' => 'web',
        ]);
        $this->command->info("  ✓ Created role: {$supportAdmin->name}");
        
        // Billing Admin - Can manage billing and view reports, limited tenant access
        $billingAdmin = Role::create([
            'name' => 'billing_admin',
            'guard_name' => 'web',
        ]);
        $this->command->info("  ✓ Created role: {$billingAdmin->name}");
        
        // Read Only - Can view but not modify anything
        $readOnly = Role::create([
            'name' => 'read_only',
            'guard_name' => 'web',
        ]);
        $this->command->info("  ✓ Created role: {$readOnly->name}");
        
        $this->command->info('✅ Created 4 RAIOPS roles');
        $this->command->info('   - system_admin: Full access');
        $this->command->info('   - support_admin: Tenant and user management');
        $this->command->info('   - billing_admin: Billing and reports');
        $this->command->info('   - read_only: View-only access');
    }
}

