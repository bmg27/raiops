<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rainbo:sync-all
                            {--force : Force sync even if cache is fresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all RAINBO sync commands (tenants + user routing)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('');
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║     RAINBO Multi-RDS Sync - All Systems Go! 🚀    ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        $options = $this->option('force') ? ['--force' => true] : [];

        // Sync tenant summaries
        $this->info('📋 Step 1/2: Syncing tenant summaries...');
        $this->newLine();
        $tenantResult = $this->call('rainbo:sync-tenant-summaries', $options);
        $this->newLine();

        // Sync user routing
        $this->info('📧 Step 2/2: Syncing user routing...');
        $this->newLine();
        $routingResult = $this->call('rainbo:sync-user-routing');
        $this->newLine();

        // Summary
        $this->info('');
        $this->info('╔═══════════════════════════════════════════════════╗');
        if ($tenantResult === Command::SUCCESS && $routingResult === Command::SUCCESS) {
            $this->info('║          All syncs completed successfully! ✅       ║');
        } else {
            $this->warn('║          Some syncs had issues. Check above. ⚠️     ║');
        }
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        return ($tenantResult === Command::SUCCESS && $routingResult === Command::SUCCESS) 
            ? Command::SUCCESS 
            : Command::FAILURE;
    }
}

