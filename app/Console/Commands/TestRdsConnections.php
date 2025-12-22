<?php

namespace App\Console\Commands;

use App\Models\RdsInstance;
use App\Services\RdsConnectionService;
use Illuminate\Console\Command;

class TestRdsConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rainbo:test-connections
                            {--rds= : Test a specific RDS instance by ID}
                            {--all : Test all RDS instances (default)}
                            {--update-health : Update health status in database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connectivity to RDS instances';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔌 Testing RDS Connections...');
        $this->newLine();

        $rdsId = $this->option('rds');
        $updateHealth = $this->option('update-health');

        // Get RDS instances to test
        if ($rdsId) {
            $rdsInstances = RdsInstance::where('id', $rdsId)->get();
            if ($rdsInstances->isEmpty()) {
                $this->error("RDS instance with ID {$rdsId} not found.");
                return Command::FAILURE;
            }
        } else {
            $rdsInstances = RdsInstance::all();
        }

        if ($rdsInstances->isEmpty()) {
            $this->warn('No RDS instances found.');
            return Command::SUCCESS;
        }

        $this->info("Testing {$rdsInstances->count()} RDS instance(s)...");
        $this->newLine();

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($rdsInstances as $rds) {
            $this->line("Testing: <fg=cyan>{$rds->name}</> ({$rds->host}:{$rds->port})");
            
            $result = $rds->testConnection();
            
            if ($result['success']) {
                $this->line("  ✅ <fg=green>Connected</> - Latency: <fg=yellow>{$result['latency_ms']}ms</>");
                $successCount++;
                
                if ($updateHealth) {
                    $rds->update([
                        'health_status' => 'healthy',
                        'last_health_check_at' => now(),
                    ]);
                }
            } else {
                $this->line("  ❌ <fg=red>Failed</> - {$result['message']}");
                $failureCount++;
                
                if ($updateHealth) {
                    $rds->update([
                        'health_status' => 'down',
                        'last_health_check_at' => now(),
                    ]);
                }
            }
            
            $results[] = [
                'id' => $rds->id,
                'name' => $rds->name,
                'host' => $rds->host,
                'success' => $result['success'],
                'message' => $result['message'],
                'latency_ms' => $result['latency_ms'],
            ];
            
            $this->newLine();
        }

        // Summary
        $this->info('📊 Summary:');
        $this->line("  ✅ Successful: <fg=green>{$successCount}</>");
        if ($failureCount > 0) {
            $this->line("  ❌ Failed: <fg=red>{$failureCount}</>");
        }

        if ($updateHealth) {
            $this->newLine();
            $this->info('💾 Health status updated in database.');
        }

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

