<?php

namespace App\Console\Commands;

use App\Models\TenantMaster;
use App\Services\RdsConnectionService;
use App\Console\Commands\Traits\IntegrationEncryptionTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DumpTenantIntegrations extends Command
{
    use IntegrationEncryptionTrait;

    protected $signature = 'raiops:dump-integrations 
                            {tenant_id? : The RAIOPS tenant_master ID (optional if using --export --all)}
                            {--decrypt : Decrypt and display settings/access_token}
                            {--raw : Show raw encrypted values}
                            {--export= : Export to JSON file (decrypted values)}
                            {--provider-id= : Filter by provider ID}
                            {--all : Export all integrations from all tenants (requires --export)}';

    protected $description = 'Dump all integrations for a tenant from the RDS instance';

    public function handle()
    {
        $exportFile = $this->option('export');
        $all = $this->option('all');
        $providerIdOption = $this->option('provider-id');
        $providerId = $providerIdOption ? (int) $providerIdOption : null;
        $tenantIdArg = $this->argument('tenant_id');
        
        // Validation
        if ($all && !$exportFile) {
            $this->error("--all requires --export option");
            return 1;
        }
        
        if (!$all && !$tenantIdArg) {
            $this->error("Either provide tenant_id or use --all with --export");
            return 1;
        }

        // Export mode
        if ($exportFile) {
            return $this->exportIntegrations($exportFile, $tenantIdArg, $providerId, $all);
        }

        // Display mode (original behavior)
        $tenantId = (int) $tenantIdArg;
        $decrypt = $this->option('decrypt');
        $raw = $this->option('raw');

        return $this->displayIntegrations($tenantId, $providerId, $decrypt, $raw);
    }

    /**
     * Display integrations (original behavior)
     */
    protected function displayIntegrations(int $tenantId, ?int $providerId, bool $decrypt, bool $raw): int
    {
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
        if ($providerId) {
            $this->info("Provider ID Filter: {$providerId}");
        }
        $this->line('');

        try {
            $service = app(RdsConnectionService::class);
            $rds = $tenant->rdsInstance;
            
            // Connect to RAI database
            $raiConn = $service->getConnection($rds);
            $providersConn = $service->getProvidersConnectionName($rds->id);

            // Get tenant-level integrations
            $this->info("=== TENANT-LEVEL INTEGRATIONS ===");
            $tenantIntegrationsQuery = DB::connection($raiConn)
                ->table('integrations')
                ->where('integrated_type', 'App\Models\Rai\Tenant')
                ->where('integrated_id', $tenant->remote_tenant_id);
            
            if ($providerId) {
                $tenantIntegrationsQuery->where('provider_id', $providerId);
            }
            
            $tenantIntegrations = $tenantIntegrationsQuery->get();

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
                
                $locationIntegrationsQuery = DB::connection($raiConn)
                    ->table('integrations')
                    ->where('integrated_type', 'App\Models\Rai\Location')
                    ->whereIn('integrated_id', $locationIds);
                
                if ($providerId) {
                    $locationIntegrationsQuery->where('provider_id', $providerId);
                }
                
                $locationIntegrations = $locationIntegrationsQuery->get();

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

    /**
     * Export integrations to JSON file
     */
    protected function exportIntegrations(string $exportFile, ?string $tenantIdArg, ?int $providerId, bool $all): int
    {
        $exportData = [
            'exported_at' => now()->toIso8601String(),
            'integrations' => [],
        ];

        try {
            $service = app(RdsConnectionService::class);
            $tenantsToProcess = [];

            if ($all) {
                // Get all tenants
                $tenantsToProcess = TenantMaster::with('rdsInstance')->get();
                $this->info("Exporting integrations from all tenants...");
            } else {
                // Get specific tenant
                $tenantId = (int) $tenantIdArg;
                $tenant = TenantMaster::with('rdsInstance')->find($tenantId);
                if (!$tenant) {
                    $this->error("Tenant ID {$tenantId} not found in tenant_master");
                    return 1;
                }
                $tenantsToProcess = collect([$tenant]);
                $this->info("Exporting integrations from tenant: {$tenant->name}");
            }

            foreach ($tenantsToProcess as $tenant) {
                if (!$tenant->rdsInstance) {
                    $this->warn("Skipping tenant {$tenant->id} - no RDS instance");
                    continue;
                }

                $raiConn = $service->getConnection($tenant->rdsInstance);
                $providersConn = $service->getProvidersConnectionName($tenant->rdsInstance->id);

                // Get tenant-level integrations
                $tenantIntegrationsQuery = DB::connection($raiConn)
                    ->table('integrations')
                    ->where('integrated_type', 'App\Models\Rai\Tenant')
                    ->where('integrated_id', $tenant->remote_tenant_id);
                
                if ($providerId) {
                    $tenantIntegrationsQuery->where('provider_id', $providerId);
                }
                
                $tenantIntegrations = $tenantIntegrationsQuery->get();

                foreach ($tenantIntegrations as $integration) {
                    $integrationData = $this->buildIntegrationData($integration, $providersConn, $raiConn, $tenant, 'tenant');
                    if ($integrationData) {
                        $exportData['integrations'][] = $integrationData;
                    }
                }

                // Get location-level integrations
                $locations = DB::connection($raiConn)
                    ->table('locations')
                    ->where('tenant_id', $tenant->remote_tenant_id)
                    ->get(['id', 'name']);

                if ($locations->isNotEmpty()) {
                    $locationIds = $locations->pluck('id')->toArray();
                    
                    $locationIntegrationsQuery = DB::connection($raiConn)
                        ->table('integrations')
                        ->where('integrated_type', 'App\Models\Rai\Location')
                        ->whereIn('integrated_id', $locationIds);
                    
                    if ($providerId) {
                        $locationIntegrationsQuery->where('provider_id', $providerId);
                    }
                    
                    $locationIntegrations = $locationIntegrationsQuery->get();

                    foreach ($locationIntegrations as $integration) {
                        $location = $locations->firstWhere('id', $integration->integrated_id);
                        $integrationData = $this->buildIntegrationData($integration, $providersConn, $raiConn, $tenant, 'location', $location);
                        if ($integrationData) {
                            $exportData['integrations'][] = $integrationData;
                        }
                    }
                }
            }

            // Write to file
            file_put_contents($exportFile, json_encode($exportData, JSON_PRETTY_PRINT));
            $this->info("Exported " . count($exportData['integrations']) . " integration(s) to: {$exportFile}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Build integration data for export (decrypted)
     */
    protected function buildIntegrationData($integration, $providersConn, $raiConn, $tenant, string $level, $location = null): ?array
    {
        try {
            // Get provider info
            $provider = null;
            try {
                $provider = DB::connection($providersConn)
                    ->table('providers')
                    ->where('id', $integration->provider_id)
                    ->first(['name', 'has_location', 'classname']);
            } catch (\Exception $e) {
                try {
                    $provider = DB::connection($raiConn)
                        ->table('providers')
                        ->where('id', $integration->provider_id)
                        ->first(['name', 'has_location', 'classname']);
                } catch (\Exception $e2) {
                    // Provider not found - skip
                    return null;
                }
            }

            // Decrypt settings
            $settings = [];
            if ($integration->settings) {
                $settings = $this->decryptWithRaiKey($integration->settings);
            }

            // Decrypt access token
            $accessToken = null;
            if ($integration->access_token) {
                $accessToken = $this->decryptTokenWithRaiKey($integration->access_token);
            }

            return [
                'tenant_master_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'remote_tenant_id' => $tenant->remote_tenant_id,
                'rds_instance_id' => $tenant->rdsInstance->id,
                'rds_instance_name' => $tenant->rdsInstance->name,
                'level' => $level,
                'integrated_id' => $integration->integrated_id,
                'location_name' => $location ? $location->name : null,
                'provider_id' => $integration->provider_id,
                'provider_name' => $provider->name ?? null,
                'provider_class' => $provider->classname ?? null,
                'is_active' => (bool) $integration->is_active,
                'settings' => $settings,
                'access_token' => $accessToken,
                'created_at' => $integration->created_at,
                'updated_at' => $integration->updated_at,
            ];
        } catch (\Exception $e) {
            $this->warn("Failed to export integration ID {$integration->id}: " . $e->getMessage());
            return null;
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
                $settings = $this->decryptWithRaiKey($integration->settings);
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
                $decryptedToken = $this->decryptTokenWithRaiKey($integration->access_token);
                if ($decryptedToken) {
                    $this->line("Access Token (decrypted): " . substr($decryptedToken, 0, 50) . '...');
                } else {
                    $this->warn("Could not decrypt access_token");
                }
            } catch (\Exception $e) {
                $this->warn("Could not decrypt access_token: " . $e->getMessage());
            }
        } elseif ($integration->access_token && !$raw) {
            $this->line("Access Token: [encrypted - use --decrypt to view]");
        }

        $this->line('');
    }
}

