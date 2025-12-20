<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CopyPermissionsSeeder extends Seeder
{
    /**
     * Copy permissions from RAI database
     * Only copies permissions marked as super_admin_only
     */
    public function run(): void
    {
        $raiDbHost = config('database.connections.rai.host', env('RAI_DB_HOST', '127.0.0.1'));
        $raiDbDatabase = config('database.connections.rai.database', env('RAI_DB_DATABASE', 'rai'));
        $raiDbUsername = config('database.connections.rai.username', env('RAI_DB_USERNAME', 'root'));
        $raiDbPassword = config('database.connections.rai.password', env('RAI_DB_PASSWORD', ''));

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
            DB::connection('rai')->getPdo();
            $this->command->info("✓ Connected to RAI database: {$raiDbDatabase}");

            // Get all permissions from RAI (we'll filter super_admin_only ones)
            $raiPermissions = DB::connection('rai')
                ->table('permissions')
                ->get();

            $this->command->info("Found {$raiPermissions->count()} permission(s) in RAI database.");

            $copied = 0;
            $skipped = 0;

            foreach ($raiPermissions as $raiPerm) {
                // Check if permission already exists
                $existing = Permission::where('name', $raiPerm->name)
                    ->where('guard_name', $raiPerm->guard_name)
                    ->first();

                if ($existing) {
                    // Update existing permission
                    $existing->update([
                        'super_admin_only' => $raiPerm->super_admin_only ?? false,
                        'description' => $raiPerm->description ?? null,
                    ]);
                    $skipped++;
                    continue;
                }

                // Create new permission
                Permission::create([
                    'name' => $raiPerm->name,
                    'guard_name' => $raiPerm->guard_name,
                    'super_admin_only' => $raiPerm->super_admin_only ?? false,
                    'description' => $raiPerm->description ?? null,
                ]);

                $copied++;
            }

            $this->command->info("✅ Copied {$copied} permission(s), updated {$skipped} existing permission(s).");
        } catch (\Exception $e) {
            $this->command->error("❌ Error copying permissions: " . $e->getMessage());
            $this->command->warn("⚠️  Skipping permission copy.");
        }
    }
}

