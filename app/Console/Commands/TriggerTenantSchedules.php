<?php

namespace App\Console\Commands;

use App\Models\CommandExecution;
use App\Models\TenantMaster;
use App\Services\RdsConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerTenantSchedules extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'raiops:trigger-tenant-schedules 
                            {--tenant= : Specific tenant_master_id to run (optional)}
                            {--frequency= : Only run commands with this frequency (optional)}
                            {--dry-run : Show what would run without executing}';

    /**
     * The console command description.
     */
    protected $description = 'Trigger scheduled commands for tenants via webhook based on frequency';

    /**
     * Frequency to hours mapping for determining which commands should run
     */
    private const FREQUENCY_HOURS = [
        'hourly'  => [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23],
        '2hours'  => [0,2,4,6,8,10,12,14,16,18,20,22],
        '4hours'  => [0,4,8,12,16,20],
        '6hours'  => [0,6,12,18],
        '12hours' => [2,14],  // 2am and 2pm
        'daily'   => [2],     // 2am
        'weekly'  => [2],     // 2am (check day separately)
    ];

    private RdsConnectionService $rdsService;

    public function __construct(RdsConnectionService $rdsService)
    {
        parent::__construct();
        $this->rdsService = $rdsService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $currentHour = (int) now()->format('G'); // 0-23
        $currentDayOfWeek = (int) now()->format('w'); // 0=Sunday, 6=Saturday
        $isDryRun = $this->option('dry-run');
        $specificTenantId = $this->option('tenant');
        $specificFrequency = $this->option('frequency');

        $this->info("🕐 Running tenant schedules at hour {$currentHour}");
        
        if ($isDryRun) {
            $this->warn("🔍 DRY RUN - no commands will be executed");
        }

        // Get frequencies that should run this hour
        $frequenciesToRun = $this->getFrequenciesForHour($currentHour, $currentDayOfWeek);
        
        if ($specificFrequency) {
            if (!in_array($specificFrequency, $frequenciesToRun)) {
                $this->info("Frequency '{$specificFrequency}' not scheduled for hour {$currentHour}");
                return Command::SUCCESS;
            }
            $frequenciesToRun = [$specificFrequency];
        }

        $this->info("📋 Frequencies to run: " . implode(', ', $frequenciesToRun));

        // Get tenants
        $query = TenantMaster::with('rdsInstance')
            ->whereHas('rdsInstance');
            
        if ($specificTenantId) {
            $query->where('id', $specificTenantId);
        }
        
        $tenants = $query->get();
        
        if ($tenants->isEmpty()) {
            $this->warn("No tenants found with RDS instances");
            return Command::SUCCESS;
        }

        $this->info("👥 Processing {$tenants->count()} tenant(s)");

        $totalTriggered = 0;

        foreach ($tenants as $tenant) {
            $this->line("");
            $this->info("━━━ {$tenant->name} ━━━");
            
            // Get commands for this tenant filtered by frequency
            $commands = $this->getCommandsForTenant($tenant, $frequenciesToRun);
            
            if ($commands->isEmpty()) {
                $this->line("  No commands due for this tenant");
                continue;
            }

            $this->line("  📦 {$commands->count()} command(s) to run:");
            foreach ($commands as $cmd) {
                $this->line("     - {$cmd->display_name} ({$cmd->schedule_frequency})");
            }

            if ($isDryRun) {
                continue;
            }

            // Trigger webhook
            $result = $this->triggerWebhook($tenant, $commands);
            
            if ($result) {
                $totalTriggered++;
                $this->info("  ✅ Triggered successfully");
            } else {
                $this->error("  ❌ Failed to trigger");
            }
        }

        $this->line("");
        $this->info("🏁 Complete. Triggered {$totalTriggered} tenant(s).");

        return Command::SUCCESS;
    }

    /**
     * Get which frequencies should run at the given hour
     */
    private function getFrequenciesForHour(int $hour, int $dayOfWeek): array
    {
        $frequencies = [];

        foreach (self::FREQUENCY_HOURS as $freq => $hours) {
            if (in_array($hour, $hours)) {
                // For weekly, only run on Sunday (day 0)
                if ($freq === 'weekly' && $dayOfWeek !== 0) {
                    continue;
                }
                $frequencies[] = $freq;
            }
        }

        return $frequencies;
    }

    /**
     * Get commands for a tenant filtered by frequencies
     */
    private function getCommandsForTenant(TenantMaster $tenant, array $frequencies): \Illuminate\Support\Collection
    {
        try {
            $db = $this->rdsService->query($tenant->rdsInstance);

            // Get provider IDs this tenant has
            $tenantProviderIds = $this->rdsService->getTenantProviderIds($tenant->rdsInstance, $tenant->remote_tenant_id);

            $query = $db->table('scheduled_commands')
                ->leftJoin('providers', 'scheduled_commands.provider_id', '=', 'providers.id')
                ->where('scheduled_commands.is_active', true)
                ->where('scheduled_commands.schedule_enabled', true)
                ->whereIn('scheduled_commands.schedule_frequency', $frequencies)
                ->where(function ($q) use ($tenantProviderIds) {
                    $q->whereNull('scheduled_commands.provider_id')
                      ->orWhereIn('scheduled_commands.provider_id', $tenantProviderIds);
                })
                ->select(
                    'scheduled_commands.id',
                    'scheduled_commands.command_name',
                    'scheduled_commands.display_name',
                    'scheduled_commands.default_params',
                    'scheduled_commands.schedule_frequency',
                    'scheduled_commands.requires_tenant'
                );

            return $query->orderBy('scheduled_commands.sort_order')->get();

        } catch (\Exception $e) {
            Log::error("Failed to get scheduled commands for tenant", [
                'tenant_master_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Trigger webhook to RAI instance
     */
    private function triggerWebhook(TenantMaster $tenant, $commands): bool
    {
        try {
            // Build command data
            $commandsData = [];
            foreach ($commands as $cmd) {
                $params = $cmd->default_params ? json_decode($cmd->default_params, true) : [];
                
                // Build command string
                $commandString = $cmd->command_name;
                foreach ($params as $key => $value) {
                    $cleanKey = ltrim($key, '-');
                    $commandString .= " --{$cleanKey}={$value}";
                }
                
                // Add tenant param
                if ($cmd->requires_tenant && $tenant->remote_tenant_id) {
                    $commandString .= " --tenant={$tenant->remote_tenant_id}";
                }
                
                $commandsData[] = [
                    'command' => $commandString,
                    'retry' => true,
                ];
            }

            // Create execution record
            $execution = CommandExecution::create([
                'command_name' => 'scheduled:auto',
                'tenant_master_id' => $tenant->id,
                'rds_instance_id' => $tenant->rdsInstance->id,
                'triggered_by' => 'scheduled',
                'status' => 'pending',
                'started_at' => now(),
                'total_steps' => count($commandsData),
                'completed_steps' => 0,
                'current_step' => $commands->first()->display_name ?? 'Starting...',
            ]);

            // Build webhook URL
            $webhookUrl = rtrim($tenant->rdsInstance->app_url, '/') . '/api/webhook/schedule';
            $callbackUrl = rtrim(config('services.rai.callback_base_url') ?: config('app.url'), '/') . '/api/webhook/schedule-callback';

            // Build payload
            $payload = [
                'execution_id' => $execution->id,
                'tenant_id' => $tenant->remote_tenant_id,
                'commands' => $commandsData,
                'is_chain' => false,
                'callback_url' => $callbackUrl,
            ];

            // Sign and send
            $response = $this->signAndSend($webhookUrl, $payload);

            if ($response->successful()) {
                $execution->update(['status' => 'running']);
                
                Log::info("Triggered scheduled commands for tenant", [
                    'tenant_master_id' => $tenant->id,
                    'execution_id' => $execution->id,
                    'command_count' => count($commandsData),
                ]);
                
                return true;
            } else {
                $execution->update([
                    'status' => 'failed',
                    'error' => "Webhook failed: " . $response->body(),
                    'completed_at' => now(),
                ]);
                
                Log::error("Scheduled webhook failed", [
                    'tenant_master_id' => $tenant->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return false;
            }

        } catch (\Exception $e) {
            Log::error("Failed to trigger scheduled commands", [
                'tenant_master_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sign payload and send to webhook
     */
    private function signAndSend(string $url, array $payload)
    {
        $secret = config('services.rai.webhook_secret');
        $timestamp = time();
        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp . '.' . $jsonPayload, $secret);

        return Http::withHeaders([
            'X-Webhook-Signature' => "{$timestamp}.{$signature}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url, $payload);
    }
}
