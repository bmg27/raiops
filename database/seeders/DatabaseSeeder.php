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
        // RAINBO Multi-RDS Setup
        // =====================================================================
        
        // Step 1: Create system admin user (for RAINBO access)
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
        // Legacy Seeders (Copy from RAI - optional)
        // =====================================================================
        
        // Copy permissions from RAI database (Spatie permissions)
        // $this->call([
        //     CopyPermissionsSeeder::class,
        // ]);

        // Copy roles from RAI database
        // $this->call([
        //     CopyRolesSeeder::class,
        // ]);

        // Copy super admin users from RAI database
        // $this->call([
        //     CopySuperAdminUsersSeeder::class,
        // ]);

        // Copy menu items from RAI database
        // $this->call([
        //     CopyMenuItemsSeeder::class,
        // ]);

        // Copy tenant data from RAI database
        // $this->call([
        //     CopyTenantDataSeeder::class,
        // ]);
    }
}
