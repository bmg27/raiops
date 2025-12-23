<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\RdsInstance;
use App\Models\TenantMaster;
use App\Models\UserEmailRoutingCache;
use App\Services\RdsConnectionService;
use Illuminate\Console\Command;

class SyncUserRouting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'raiops:sync-user-routing
                            {--truncate : Truncate the cache table before syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync user email routing data from the master RDS to local cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting user routing sync from Master RDS...');
        $this->newLine();

        // Get the master RDS
        $masterRds = RdsInstance::getMaster();
        
        if (!$masterRds) {
            $this->error('No master RDS instance configured.');
            $this->line('Set is_master = true on one RDS instance to designate it as master.');
            return Command::FAILURE;
        }

        $this->info("Master RDS: {$masterRds->name} ({$masterRds->host})");

        // Test connection
        $connectionTest = $masterRds->testConnection();
        if (!$connectionTest['success']) {
            $this->error("Connection failed: {$connectionTest['message']}");
            return Command::FAILURE;
        }
        
        $this->line("✓ Connected ({$connectionTest['latency_ms']}ms)");
        $this->newLine();

        $service = app(RdsConnectionService::class);

        try {
            // Fetch user email routing from master
            $routingEntries = $service->getUserEmailRouting($masterRds);
            
            if ($routingEntries->isEmpty()) {
                $this->warn('No user_email_routing entries found in master RDS.');
                return Command::SUCCESS;
            }

            $this->info("Found {$routingEntries->count()} routing entries to sync.");

            // Optionally truncate
            if ($this->option('truncate')) {
                $this->warn('Truncating local cache...');
                UserEmailRoutingCache::truncate();
            }

            $bar = $this->output->createProgressBar($routingEntries->count());
            $bar->start();

            $synced = 0;
            $errors = 0;
            $skippedNoTenant = 0;

            foreach ($routingEntries as $entry) {
                try {
                    // Find the tenant_master record for this tenant
                    $tenantMaster = TenantMaster::where('rds_instance_id', $entry->rds_instance_id ?? $masterRds->id)
                        ->where('remote_tenant_id', $entry->tenant_id)
                        ->first();

                    if (!$tenantMaster) {
                        // If tenant not in tenant_master, we might need to sync tenants first
                        // For now, skip these entries
                        $skippedNoTenant++;
                        $errors++;
                        $bar->advance();
                        continue;
                    }

                    UserEmailRoutingCache::updateOrCreate(
                        [
                            'email' => strtolower($entry->email),
                            'tenant_master_id' => $tenantMaster->id,
                        ],
                        [
                            'rds_instance_id' => $entry->rds_instance_id ?? $masterRds->id,
                            'remote_user_id' => $entry->user_id,
                            'user_name' => $entry->user_name ?? null,
                            'status' => $entry->status ?? 'Active',
                            'synced_at' => now(),
                        ]
                    );

                    $synced++;
                } catch (\Exception $e) {
                    $errors++;
                    if ($this->option('verbose')) {
                        $this->newLine();
                        $this->warn("   ⚠️  Error syncing {$entry->email} (tenant_id: {$entry->tenant_id}): {$e->getMessage()}");
                    }
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // Log the sync
            AuditLog::log('command_sync', 'UserEmailRoutingCache', null, null, [
                'command' => 'raiops:sync-user-routing',
                'entries_synced' => $synced,
                'errors' => $errors,
                'source_rds' => $masterRds->name,
            ]);

            $this->info('═══════════════════════════════════════');
            $this->info('  Sync Complete!');
            $this->info("  ✓ Synced: {$synced}");
            if ($skippedNoTenant > 0) {
                $this->warn("  ○ Skipped (no tenant_master): {$skippedNoTenant}");
                $this->line("    → Run 'php artisan raiops:sync-tenant-summaries' first");
            }
            if ($errors > $skippedNoTenant) {
                $this->warn("  ✗ Errors: " . ($errors - $skippedNoTenant));
            }
            $this->info('═══════════════════════════════════════');

            return $errors > 0 && $synced === 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}

