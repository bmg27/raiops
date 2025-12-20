<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemAdminSeeder extends Seeder
{
    /**
     * Create the initial system admin user.
     */
    public function run(): void
    {
        // Create default system admin if not exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@rainbo.local'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'), // Change in production!
                'role' => 'system_admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("System Admin created/verified: {$admin->email}");
        $this->command->warn("Default password is 'password' - CHANGE THIS IN PRODUCTION!");
    }
}

