<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CopySuperAdminUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder copies all users with is_super_admin = true from the RAI database
     * to the RAINBO database. It connects to the RAI database, retrieves super admin
     * users, and creates them in the RAINBO database.
     */
    public function run(): void
    {
        // RAI database connection config (update these in .env or config)
        $raiDbHost = config('database.connections.rai.host', env('RAI_DB_HOST', '127.0.0.1'));
        $raiDbDatabase = config('database.connections.rai.database', env('RAI_DB_DATABASE', 'rai'));
        $raiDbUsername = config('database.connections.rai.username', env('RAI_DB_USERNAME', 'root'));
        $raiDbPassword = config('database.connections.rai.password', env('RAI_DB_PASSWORD', ''));

        // Connect to RAI database
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
            // Test connection first
            DB::connection('rai')->getPdo();
            $this->command->info("✓ Connected to RAI database: {$raiDbDatabase}");

            // Get super admin users from RAI database using is_super_admin column
            $superAdminUsers = DB::connection('rai')
                ->table('users')
                ->where('is_super_admin', 1)
                ->where('status', 'Active')
                ->where('deleted', 0)
                ->get();

            if ($superAdminUsers->isEmpty()) {
                $this->command->warn('  ⚠ No super admin users found in RAI database.');
                return;
            }

            $this->command->info("Found {$superAdminUsers->count()} super admin user(s) to copy.");

            // Ensure Super Admin role exists
            $superAdminRole = Role::firstOrCreate(
                ['name' => 'Super Admin', 'guard_name' => 'web'],
                ['tenant_id' => null] // Global role
            );

            $copied = 0;
            $skipped = 0;

            foreach ($superAdminUsers as $raiUser) {
                // Check if user already exists by email
                $existingUser = User::where('email', $raiUser->email)->first();

                if ($existingUser) {
                    $this->command->warn("  ⚠ User {$raiUser->email} already exists. Skipping...");
                    $skipped++;
                    continue;
                }

                // Create user in RAINBO database
                $userData = [
                    'name' => $raiUser->name,
                    'email' => $raiUser->email,
                    'password' => $raiUser->password, // Copy hashed password
                    'email_verified_at' => $raiUser->email_verified_at ?? null,
                    'is_super_admin' => true,
                    'is_tenant_owner' => false,
                    'tenant_id' => null, // Super admins don't belong to a tenant
                    'created_at' => $raiUser->created_at ?? now(),
                    'updated_at' => $raiUser->updated_at ?? now(),
                ];

                // Only add status if the column exists (RAINBO users table may not have it)
                if (isset($raiUser->status)) {
                    // Check if status column exists in RAINBO users table
                    $hasStatusColumn = \Schema::hasColumn('users', 'status');
                    if ($hasStatusColumn) {
                        $userData['status'] = $raiUser->status;
                    }
                }

                $user = User::create($userData);

                // Assign Super Admin role
                $user->assignRole($superAdminRole);

                $this->command->info("  ✓ Copied user: {$user->name} ({$user->email})");
                $copied++;
            }

            $this->command->info("\n✅ Successfully copied {$copied} super admin user(s).");
            if ($skipped > 0) {
                $this->command->warn("⚠ Skipped {$skipped} user(s) (already exist).");
            }
        } catch (\PDOException $e) {
            $this->command->error("❌ Database connection error: " . $e->getMessage());
            $this->command->error("Make sure RAI database connection is configured in .env:");
            $this->command->error("  RAI_DB_HOST=127.0.0.1");
            $this->command->error("  RAI_DB_DATABASE=rai");
            $this->command->error("  RAI_DB_USERNAME=root");
            $this->command->error("  RAI_DB_PASSWORD=your_password");
            $this->command->warn("⚠️  Skipping user copy. You can manually create super admin users or configure the connection later.");
        } catch (\Exception $e) {
            $this->command->error("❌ Error copying super admin users: " . $e->getMessage());
            $this->command->error("Stack trace: " . $e->getTraceAsString());
            $this->command->warn("⚠️  Skipping user copy. You can manually create super admin users or configure the connection later.");
        }
    }
}

