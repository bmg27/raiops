<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CopyRolesSeeder extends Seeder
{
    /**
     * Copy roles from RAI database
     * Only copies Super Admin role and other global roles (tenant_id is null)
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

            // Get global roles (tenant_id is null) from RAI
            $raiRoles = DB::connection('rai')
                ->table('roles')
                ->whereNull('tenant_id')
                ->get();

            $this->command->info("Found {$raiRoles->count()} global role(s) in RAI database.");

            $copied = 0;
            $skipped = 0;

            foreach ($raiRoles as $raiRole) {
                // Check if role already exists
                $existing = Role::where('name', $raiRole->name)
                    ->where('guard_name', $raiRole->guard_name)
                    ->whereNull('tenant_id')
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                // Create new role
                $role = Role::create([
                    'name' => $raiRole->name,
                    'guard_name' => $raiRole->guard_name,
                    'tenant_id' => null, // Global role
                ]);

                // Copy role-permission relationships
                $raiRolePermissions = DB::connection('rai')
                    ->table('role_has_permissions')
                    ->where('role_id', $raiRole->id)
                    ->pluck('permission_id');

                foreach ($raiRolePermissions as $permissionId) {
                    // Get permission name from RAI
                    $raiPermission = DB::connection('rai')
                        ->table('permissions')
                        ->where('id', $permissionId)
                        ->first();

                    if ($raiPermission) {
                        // Find permission in RAINBO by name
                        $permission = \App\Models\Permission::where('name', $raiPermission->name)
                            ->where('guard_name', $raiPermission->guard_name)
                            ->first();

                        if ($permission) {
                            $role->givePermissionTo($permission);
                        }
                    }
                }

                $copied++;
                $this->command->info("  ✓ Copied role: {$role->name}");
            }

            $this->command->info("✅ Copied {$copied} role(s), skipped {$skipped} existing role(s).");
        } catch (\Exception $e) {
            $this->command->error("❌ Error copying roles: " . $e->getMessage());
            $this->command->warn("⚠️  Skipping role copy.");
        }
    }
}

