<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // =====================================================================
        // RAIOPS Multi-RDS Setup
        // =====================================================================
        
        // Step 1: Create system admin user (for RAIOPS access)
        $this->call([
            SystemAdminSeeder::class,
        ]);

        // Step 2: Set up RDS instances (reads from RAI_DB_* env vars)
        $this->call([
            RdsInstanceSeeder::class,
        ]);

        // Step 3: Sync tenants from RDS instances to tenant_master
        // This requires RDS connection to work
        $this->call([
            TenantMasterSyncSeeder::class,
        ]);

        // =====================================================================
        // RAIOPS Permissions & Menu System
        // =====================================================================
        
        // Seed RAIOPS roles (system_admin, support_admin, billing_admin, read_only)
        $this->call([
            \Database\Seeders\SeedRaiOpsRoles::class,
        ]);
        
        // Seed RAIOPS-specific Spatie permissions (replaces RAI permissions)
        $this->call([
            \Database\Seeders\SeedRaiOpsPermissions::class,
        ]);

        // Seed RAIOPS menu items with proper hierarchy
        $this->call([
            \Database\Seeders\SeedRaiOpsMenuItems::class,
        ]);
    }
}
