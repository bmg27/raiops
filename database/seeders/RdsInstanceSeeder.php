<?php

namespace Database\Seeders;

use App\Models\RdsInstance;
use Illuminate\Database\Seeder;

class RdsInstanceSeeder extends Seeder
{
    /**
     * Seed initial RDS instances.
     * 
     * This creates example RDS instances. In production, you would
     * configure these through the UI or import from existing config.
     */
    public function run(): void
    {
        // Create Master RDS (from RAI environment)
        $masterRds = RdsInstance::firstOrCreate(
            ['is_master' => true],
            [
                'name' => 'Master RDS (Local)',
                'host' => env('RAI_DB_HOST', '127.0.0.1'),
                'port' => env('RAI_DB_PORT', 3306),
                'username' => env('RAI_DB_USERNAME', 'root'),
                'password' => env('RAI_DB_PASSWORD', ''),
                'rai_database' => env('RAI_DB_DATABASE', 'linkrg_prod_test'),
                'providers_database' => env('RAI_PROVIDERS_DATABASE', 'providers'),
                'app_url' => env('RAI_URL', 'http://rai.test'),
                'is_active' => true,
                'is_master' => true,
                'health_status' => 'unknown',
                'notes' => 'Primary/Master RDS instance',
            ]
        );

        $this->command->info("Master RDS created/verified: {$masterRds->name}");

        // Test the connection
        $result = $masterRds->testConnection();
        if ($result['success']) {
            $masterRds->update([
                'health_status' => 'healthy',
                'last_health_check_at' => now(),
            ]);
            $this->command->info("✅ Master RDS connection successful ({$result['latency_ms']}ms)");
        } else {
            $masterRds->update([
                'health_status' => 'down',
                'last_health_check_at' => now(),
            ]);
            $this->command->error("❌ Master RDS connection failed: {$result['message']}");
            $this->command->warn("Make sure RAI_DB_* environment variables are configured correctly.");
        }
    }
}

