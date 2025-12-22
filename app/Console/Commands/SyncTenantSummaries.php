<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\RdsInstance;
use App\Models\TenantMaster;
use App\Services\RdsConnectionService;
use Illuminate\Console\Command;

class SyncTenantSummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rainbo:sync-tenant-summaries
                            {--rds= : Specific RDS instance ID to sync from}
                            {--force : Force sync even if cache is fresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync tenant summaries from all RDS instances to the tenant_master table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting tenant sync from RDS instances...');
        $this->newLine();

        $service = app(RdsConnectionService::class);
        
        // Get RDS instances to sync
        $query = RdsInstance::active();
        if ($rdsId = $this->option('rds')) {
            $query->where('id', $rdsId);
        }
        $rdsInstances = $query->get();

        if ($rdsInstances->isEmpty()) {
            $this->error('No active RDS instances found.');
            return Command::FAILURE;
        }

        $this->info("Found {$rdsInstances->count()} RDS instance(s) to sync.");
        $this->newLine();

        $totalSynced = 0;
        $totalErrors = 0;
        $totalSkipped = 0;

        foreach ($rdsInstances as $rds) {
            $this->info("📦 Syncing from: {$rds->name} ({$rds->host})");
            
            // Test connection first
            $connectionTest = $rds->testConnection();
            if (!$connectionTest['success']) {
                $this->error("   ❌ Connection failed: {$connectionTest['message']}");
                $totalErrors++;
                continue;
            }
            
            $this->line("   ✓ Connected ({$connectionTest['latency_ms']}ms)");

            try {
                $remoteTenants = $service->getTenants($rds);
                $this->line("   Found {$remoteTenants->count()} tenants");

                $bar = $this->output->createProgressBar($remoteTenants->count());
                $bar->start();

                foreach ($remoteTenants as $remoteTenant) {
                    try {
                        // Check if we should skip (cache is fresh and no --force)
                        $existing = TenantMaster::where('rds_instance_id', $rds->id)
                            ->where('remote_tenant_id', $remoteTenant->id)
                            ->first();

                        if ($existing && !$this->option('force') && !$existing->isCacheStale()) {
                            $totalSkipped++;
                            $bar->advance();
                            continue;
                        }

                        // Get counts from RDS
                        $userCount = $service->getUserCount($rds, $remoteTenant->id);
                        $locationCount = $service->getLocationCount($rds, $remoteTenant->id);

                        // Upsert the tenant
                        TenantMaster::updateOrCreate(
                            [
                                'rds_instance_id' => $rds->id,
                                'remote_tenant_id' => $remoteTenant->id,
                            ],
                            [
                                'name' => $remoteTenant->name,
                                'primary_contact_name' => $remoteTenant->primary_contact_name ?? null,
                                'primary_contact_email' => $remoteTenant->primary_contact_email ?? null,
                                'status' => $remoteTenant->status ?? 'active',
                                'trial_ends_at' => $remoteTenant->trial_ends_at ?? null,
                                'subscription_started_at' => $remoteTenant->subscription_started_at ?? null,
                                'cached_user_count' => $userCount,
                                'cached_location_count' => $locationCount,
                                'cache_refreshed_at' => now(),
                            ]
                        );

                        $totalSynced++;
                    } catch (\Exception $e) {
                        $this->newLine();
                        $this->warn("   ⚠️  Error syncing tenant {$remoteTenant->id}: {$e->getMessage()}");
                        $totalErrors++;
                    }

                    $bar->advance();
                }

                $bar->finish();
                $this->newLine(2);

            } catch (\Exception $e) {
                $this->error("   ❌ Error fetching tenants: {$e->getMessage()}");
                $totalErrors++;
            }
        }

        // Log the sync
        AuditLog::log('command_sync', 'TenantMaster', null, null, [
            'command' => 'rainbo:sync-tenant-summaries',
            'tenants_synced' => $totalSynced,
            'tenants_skipped' => $totalSkipped,
            'errors' => $totalErrors,
            'rds_count' => $rdsInstances->count(),
        ]);

        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('  Sync Complete!');
        $this->info("  ✓ Synced: {$totalSynced}");
        $this->info("  ○ Skipped (fresh cache): {$totalSkipped}");
        if ($totalErrors > 0) {
            $this->warn("  ✗ Errors: {$totalErrors}");
        }
        $this->info('═══════════════════════════════════════');

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

