<?php

namespace Database\Seeders;

use App\Models\RdsInstance;
use App\Models\TenantMaster;
use App\Services\RdsConnectionService;
use Illuminate\Database\Seeder;

class TenantMasterSyncSeeder extends Seeder
{
    /**
     * Sync tenants from all RDS instances to tenant_master table.
     */
    public function run(): void
    {
        $rdsService = app(RdsConnectionService::class);
        $totalSynced = 0;

        $rdsInstances = RdsInstance::active()->get();

        if ($rdsInstances->isEmpty()) {
            $this->command->warn('No active RDS instances found. Run RdsInstanceSeeder first.');
            return;
        }

        foreach ($rdsInstances as $rds) {
            $this->command->info("Syncing tenants from: {$rds->name}...");

            try {
                $tenants = $rdsService->getTenants($rds);

                if ($tenants->isEmpty()) {
                    $this->command->warn("  No tenants found on {$rds->name}");
                    continue;
                }

                foreach ($tenants as $tenant) {
                    // Get user and location counts
                    $userCount = $rdsService->getUserCount($rds, $tenant->id);
                    $locationCount = $rdsService->getLocationCount($rds, $tenant->id);

                    TenantMaster::updateOrCreate(
                        [
                            'rds_instance_id' => $rds->id,
                            'remote_tenant_id' => $tenant->id,
                        ],
                        [
                            'name' => $tenant->name,
                            'primary_contact_name' => $tenant->primary_contact_name ?? null,
                            'primary_contact_email' => $tenant->primary_contact_email ?? null,
                            'status' => $tenant->status ?? 'active',
                            'trial_ends_at' => $tenant->trial_ends_at ?? null,
                            'subscription_started_at' => $tenant->subscription_started_at ?? null,
                            'cached_user_count' => $userCount,
                            'cached_location_count' => $locationCount,
                            'cache_refreshed_at' => now(),
                        ]
                    );

                    $totalSynced++;
                }

                $this->command->info("  ✅ Synced {$tenants->count()} tenants from {$rds->name}");

            } catch (\Exception $e) {
                $this->command->error("  ❌ Failed to sync from {$rds->name}: {$e->getMessage()}");
            }
        }

        $this->command->info("Total tenants synced: {$totalSynced}");
    }
}

