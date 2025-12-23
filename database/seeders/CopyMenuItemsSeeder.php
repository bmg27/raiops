<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CopyMenuItemsSeeder extends Seeder
{
    /**
     * Copy menu and menu items data from RAI database to RAIOPS database
     */
    public function run(): void
    {
        $this->command->info('🔄 Starting menu items copy from RAI to RAIOPS...');

        // Ensure RAI connection is configured
        $raiDbHost = env('RAI_DB_HOST');
        $raiDbDatabase = env('RAI_DB_DATABASE');
        $raiDbUsername = env('RAI_DB_USERNAME');
        $raiDbPassword = env('RAI_DB_PASSWORD');

        if (!$raiDbHost || !$raiDbDatabase) {
            $this->command->error('❌ RAI database connection not configured in .env');
            $this->command->warn('Please add these to your .env file:');
            $this->command->warn('  RAI_DB_HOST=127.0.0.1');
            $this->command->warn('  RAI_DB_DATABASE=rai');
            $this->command->warn('  RAI_DB_USERNAME=root');
            $this->command->warn('  RAI_DB_PASSWORD=your_password');
            return;
        }

        // Dynamically add the 'rai' connection if not already defined
        config(['database.connections.rai' => [
            'driver' => 'mysql',
            'host' => $raiDbHost,
            'database' => $raiDbDatabase,
            'username' => $raiDbUsername,
            'password' => $raiDbPassword,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        try {
            // Test connection
            DB::connection('rai')->getPdo();
            $this->command->info('✅ Connected to RAI database');

            // Copy menus first
            $this->copyMenus();

            // Copy menu items
            $this->copyMenuItems();

            // Copy tenant menu item relationships (if table exists)
            $this->copyTenantMenuItems();

            $this->command->info('✅ Menu items copy completed successfully!');

        } catch (\Exception $e) {
            $this->command->error("❌ Database connection error: " . $e->getMessage());
            $this->command->warn("⚠️  Skipping menu items copy. You can run this seeder later after configuring the connection.");
        }
    }

    /**
     * Copy all menus from RAI to RAIOPS
     */
    private function copyMenus(): void
    {
        $this->command->info('📋 Copying menus...');

        try {
            // Check if table exists in RAI
            if (!Schema::connection('rai')->hasTable('menus')) {
                $this->command->warn('   menus table not found in RAI database');
                return;
            }

            $raiMenus = DB::connection('rai')
                ->table('menus')
                ->get();

            if ($raiMenus->isEmpty()) {
                $this->command->warn('   No menus found in RAI database');
                return;
            }

            $copied = 0;
            $skipped = 0;

            foreach ($raiMenus as $raiMenu) {
                // Check if menu already exists
                $exists = DB::table('menus')
                    ->where('id', $raiMenu->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Insert menu
                DB::table('menus')->insert([
                    'id' => $raiMenu->id,
                    'name' => $raiMenu->name,
                    'created_at' => $raiMenu->created_at ?? now(),
                    'updated_at' => $raiMenu->updated_at ?? now(),
                ]);

                $copied++;
            }

            $this->command->info("   ✅ Copied {$copied} menus, skipped {$skipped} existing");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Error copying menus: " . $e->getMessage());
        }
    }

    /**
     * Copy all menu items from RAI to RAIOPS
     */
    private function copyMenuItems(): void
    {
        $this->command->info('🍔 Copying menu items...');

        try {
            // Check if table exists in RAI
            if (!Schema::connection('rai')->hasTable('menu_items')) {
                $this->command->warn('   menu_items table not found in RAI database');
                return;
            }

            $raiMenuItems = DB::connection('rai')
                ->table('menu_items')
                ->get();

            if ($raiMenuItems->isEmpty()) {
                $this->command->warn('   No menu items found in RAI database');
                return;
            }

            $copied = 0;
            $skipped = 0;

            foreach ($raiMenuItems as $raiMenuItem) {
                // Check if menu item already exists
                $exists = DB::table('menu_items')
                    ->where('id', $raiMenuItem->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Prepare insert data with all possible fields
                $insertData = [
                    'id' => $raiMenuItem->id,
                    'menu_id' => $raiMenuItem->menu_id,
                    'title' => $raiMenuItem->title,
                    'url' => $raiMenuItem->url,
                    'parent_id' => $raiMenuItem->parent_id,
                    'icon' => $raiMenuItem->icon ?? null,
                    'containerType' => $raiMenuItem->containerType ?? 'Standard',
                    'route' => $raiMenuItem->route ?? null,
                    'order' => $raiMenuItem->order ?? 0,
                    'active' => $raiMenuItem->active ?? 1,
                    'permission_id' => $raiMenuItem->permission_id ?? null,
                    'created_at' => $raiMenuItem->created_at ?? now(),
                    'updated_at' => $raiMenuItem->updated_at ?? now(),
                ];

                // Add optional fields if they exist in RAI
                if (property_exists($raiMenuItem, 'super_admin_only')) {
                    $insertData['super_admin_only'] = $raiMenuItem->super_admin_only ?? false;
                }

                if (property_exists($raiMenuItem, 'tenant_specific')) {
                    $insertData['tenant_specific'] = $raiMenuItem->tenant_specific ?? false;
                }

                if (property_exists($raiMenuItem, 'super_admin_append')) {
                    $insertData['super_admin_append'] = $raiMenuItem->super_admin_append ?? null;
                }

                // Insert menu item
                DB::table('menu_items')->insert($insertData);

                $copied++;
            }

            $this->command->info("   ✅ Copied {$copied} menu items, skipped {$skipped} existing");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Error copying menu items: " . $e->getMessage());
        }
    }

    /**
     * Copy tenant menu item relationships from RAI to RAIOPS
     */
    private function copyTenantMenuItems(): void
    {
        $this->command->info('🔗 Copying tenant menu item relationships...');

        try {
            // Check if table exists in RAI
            if (!Schema::connection('rai')->hasTable('tenant_menu_items')) {
                $this->command->warn('   tenant_menu_items table not found in RAI database');
                return;
            }

            // Check if table exists in RAIOPS
            if (!Schema::hasTable('tenant_menu_items')) {
                $this->command->warn('   tenant_menu_items table does not exist in RAIOPS database');
                $this->command->warn('   Creating table structure...');
                
                // Create the pivot table
                Schema::create('tenant_menu_items', function ($table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('tenant_id');
                    $table->unsignedBigInteger('menu_item_id');
                    $table->timestamps();
                    
                    $table->unique(['tenant_id', 'menu_item_id']);
                    $table->index('tenant_id');
                    $table->index('menu_item_id');
                });
            }

            $raiTenantMenuItems = DB::connection('rai')
                ->table('tenant_menu_items')
                ->get();

            if ($raiTenantMenuItems->isEmpty()) {
                $this->command->warn('   No tenant menu item relationships found in RAI database');
                return;
            }

            $copied = 0;
            $skipped = 0;

            foreach ($raiTenantMenuItems as $raiTenantMenuItem) {
                // Check if relationship already exists
                $exists = DB::table('tenant_menu_items')
                    ->where('tenant_id', $raiTenantMenuItem->tenant_id)
                    ->where('menu_item_id', $raiTenantMenuItem->menu_item_id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Insert relationship
                DB::table('tenant_menu_items')->insert([
                    'tenant_id' => $raiTenantMenuItem->tenant_id,
                    'menu_item_id' => $raiTenantMenuItem->menu_item_id,
                    'created_at' => $raiTenantMenuItem->created_at ?? now(),
                    'updated_at' => $raiTenantMenuItem->updated_at ?? now(),
                ]);

                $copied++;
            }

            $this->command->info("   ✅ Copied {$copied} tenant menu relationships, skipped {$skipped} existing");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Error copying tenant menu relationships: " . $e->getMessage());
        }
    }
}

