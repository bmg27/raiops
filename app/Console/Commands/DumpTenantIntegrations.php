<?php

namespace App\Console\Commands;

use App\Models\TenantMaster;
use App\Services\RdsConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DumpTenantIntegrations extends Command
{
    protected $signature = 'raiops:dump-integrations 
                            {tenant_id : The RAIOPS tenant_master ID}
                            {--decrypt : Decrypt and display settings/access_token}
                            {--raw : Show raw encrypted values}';

    protected $description = 'Dump all integrations for a tenant from the RDS instance';

    public function handle()
    {
        $tenantId = (int) $this->argument('tenant_id');
        $decrypt = $this->option('decrypt');
        $raw = $this->option('raw');

        // Find tenant in RAIOPS
        $tenant = TenantMaster::with('rdsInstance')->find($tenantId);
        
        if (!$tenant) {
            $this->error("Tenant ID {$tenantId} not found in tenant_master");
            return 1;
        }

        if (!$tenant->rdsInstance) {
            $this->error("RDS instance not found for tenant {$tenantId}");
            return 1;
        }

        $this->info("Tenant: {$tenant->name} (RAIOPS ID: {$tenant->id})");
        $this->info("RDS: {$tenant->rdsInstance->name} (ID: {$tenant->rdsInstance->id})");
        $this->info("Remote Tenant ID: {$tenant->remote_tenant_id}");
        $this->line('');

        try {
            $service = app(RdsConnectionService::class);
            $rds = $tenant->rdsInstance;
            
            // Connect to RAI database
            $raiConn = $service->getConnection($rds);
            $providersConn = $service->getProvidersConnectionName($rds->id);

            // Get tenant-level integrations
            $this->info("=== TENANT-LEVEL INTEGRATIONS ===");
            $tenantIntegrations = DB::connection($raiConn)
                ->table('integrations')
                ->where('integrated_type', 'App\Models\Rai\Tenant')
                ->where('integrated_id', $tenant->remote_tenant_id)
                ->get();

            if ($tenantIntegrations->isEmpty()) {
                $this->warn("No tenant-level integrations found");
            } else {
                $this->info("Found {$tenantIntegrations->count()} tenant-level integration(s)");
                $this->line('');
                
                foreach ($tenantIntegrations as $integration) {
                    $this->displayIntegration($integration, $providersConn, $decrypt, $raw, 'Tenant', $raiConn);
                }
            }

            $this->line('');
            $this->info("=== LOCATION-LEVEL INTEGRATIONS ===");
            
            // Get locations for this tenant
            $locations = DB::connection($raiConn)
                ->table('locations')
                ->where('tenant_id', $tenant->remote_tenant_id)
                ->get(['id', 'name']);

            if ($locations->isEmpty()) {
                $this->warn("No locations found for tenant");
            } else {
                $locationIds = $locations->pluck('id')->toArray();
                $locationMap = $locations->keyBy('id');
                
                $locationIntegrations = DB::connection($raiConn)
                    ->table('integrations')
                    ->where('integrated_type', 'App\Models\Rai\Location')
                    ->whereIn('integrated_id', $locationIds)
                    ->get();

                if ($locationIntegrations->isEmpty()) {
                    $this->warn("No location-level integrations found");
                } else {
                    $this->info("Found {$locationIntegrations->count()} location-level integration(s)");
                    $this->line('');
                    
                    foreach ($locationIntegrations as $integration) {
                        $locationName = $locationMap[$integration->integrated_id]->name ?? "Location {$integration->integrated_id}";
                        $this->displayIntegration($integration, $providersConn, $decrypt, $raw, "Location: {$locationName}", $raiConn);
                    }
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    protected function displayIntegration($integration, $providersConn, $decrypt, $raw, $context, $raiConn)
    {
        // Get provider name - try providers database first, then RAI database as fallback
        $provider = null;
        try {
            $provider = DB::connection($providersConn)
                ->table('providers')
                ->where('id', $integration->provider_id)
                ->first(['name', 'has_location', 'classname']);
        } catch (\Exception $e) {
            // Fallback: try RAI database
            try {
                $provider = DB::connection($raiConn)
                    ->table('providers')
                    ->where('id', $integration->provider_id)
                    ->first(['name', 'has_location', 'classname']);
            } catch (\Exception $e2) {
                $this->warn("Could not query providers from either database");
            }
        }

        $providerName = $provider->name ?? "Unknown Provider (ID: {$integration->provider_id})";
        
        $this->line("─────────────────────────────────────────────────");
        $this->info("{$context} - {$providerName}");
        $this->line("─────────────────────────────────────────────────");
        $this->line("Integration ID: {$integration->id}");
        $this->line("Provider ID: {$integration->provider_id}");
        if ($provider) {
            $this->line("Provider Class: {$provider->classname}");
            $this->line("Has Location: " . ($provider->has_location ? 'Yes' : 'No'));
        }
        $this->line("Integrated Type: {$integration->integrated_type}");
        $this->line("Integrated ID: {$integration->integrated_id}");
        $this->line("Is Active: " . ($integration->is_active ? 'Yes' : 'No'));
        $this->line("Created: {$integration->created_at}");
        $this->line("Updated: {$integration->updated_at}");

        // Display settings
        if ($raw) {
            $this->line("Settings (encrypted): " . substr($integration->settings ?? '', 0, 100) . '...');
            $this->line("Access Token (encrypted): " . ($integration->access_token ? substr($integration->access_token, 0, 50) . '...' : 'null'));
        } elseif ($decrypt && $integration->settings) {
            try {
                $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($integration->settings);
                $settings = json_decode($decrypted, true);
                $this->line("Settings (decrypted):");
                $this->line(json_encode($settings, JSON_PRETTY_PRINT));
            } catch (\Exception $e) {
                $this->warn("Could not decrypt settings: " . $e->getMessage());
            }
        } else {
            $this->line("Settings: [encrypted - use --decrypt to view]");
        }

        if ($decrypt && $integration->access_token) {
            try {
                $decryptedToken = \Illuminate\Support\Facades\Crypt::decryptString($integration->access_token);
                $this->line("Access Token (decrypted): " . substr($decryptedToken, 0, 50) . '...');
            } catch (\Exception $e) {
                $this->warn("Could not decrypt access_token: " . $e->getMessage());
            }
        } elseif ($integration->access_token && !$raw) {
            $this->line("Access Token: [encrypted - use --decrypt to view]");
        }

        $this->line('');
    }
}

