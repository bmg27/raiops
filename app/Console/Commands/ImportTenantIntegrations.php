<?php

namespace App\Console\Commands;

use App\Models\TenantMaster;
use App\Services\RdsConnectionService;
use App\Console\Commands\Traits\IntegrationEncryptionTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTenantIntegrations extends Command
{
    use IntegrationEncryptionTrait;

    protected $signature = 'raiops:import-integrations 
                            {file : Path to JSON export file}
                            {--tenant-id= : Map to specific tenant_master ID (optional, uses tenant_master_id from export)}
                            {--dry-run : Show what would be imported without actually importing}';

    protected $description = 'Import integrations from JSON export file into destination RAI database';

    public function handle()
    {
        $file = $this->argument('file');
        $dryRun = $this->option('dry-run');
        $targetTenantId = $this->option('tenant-id');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $exportData = json_decode(file_get_contents($file), true);
        if (!$exportData || !isset($exportData['integrations'])) {
            $this->error("Invalid export file format");
            return 1;
        }

        $this->info("Found " . count($exportData['integrations']) . " integration(s) in export file");
        if (isset($exportData['exported_at'])) {
            $this->info("Exported at: {$exportData['exported_at']}");
        }
        $this->line('');

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No changes will be made");
            $this->line('');
        }

        try {
            $service = app(RdsConnectionService::class);
            $imported = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($exportData['integrations'] as $integrationData) {
                try {
                    // Determine target tenant
                    $tenantMasterId = $targetTenantId ? (int) $targetTenantId : $integrationData['tenant_master_id'];
                    $tenant = TenantMaster::with('rdsInstance')->find($tenantMasterId);

                    if (!$tenant) {
                        $this->warn("Skipping integration - tenant_master ID {$tenantMasterId} not found");
                        $skipped++;
                        continue;
                    }

                    if (!$tenant->rdsInstance) {
                        $this->warn("Skipping integration - tenant {$tenantMasterId} has no RDS instance");
                        $skipped++;
                        continue;
                    }

                    // Verify RDS instance matches (optional check)
                    if (isset($integrationData['rds_instance_id']) && $tenant->rdsInstance->id != $integrationData['rds_instance_id']) {
                        $this->warn("Warning: RDS instance ID mismatch (export: {$integrationData['rds_instance_id']}, target: {$tenant->rdsInstance->id})");
                    }

                    $raiConn = $service->getConnection($tenant->rdsInstance);
                    
                    // Determine integrated_id
                    $integratedId = null;
                    $integratedType = null;

                    if ($integrationData['level'] === 'tenant') {
                        $integratedType = 'App\Models\Rai\Tenant';
                        $integratedId = $tenant->remote_tenant_id;
                    } else {
                        // Location level - need to find location by name or ID
                        $integratedType = 'App\Models\Rai\Location';
                        
                        if (isset($integrationData['location_name'])) {
                            // Try to find location by name
                            $location = DB::connection($raiConn)
                                ->table('locations')
                                ->where('tenant_id', $tenant->remote_tenant_id)
                                ->where('name', $integrationData['location_name'])
                                ->first();
                            
                            if ($location) {
                                $integratedId = $location->id;
                            } else {
                                $this->warn("Skipping integration - location '{$integrationData['location_name']}' not found for tenant {$tenantMasterId}");
                                $skipped++;
                                continue;
                            }
                        } else {
                            // Use original integrated_id
                            $integratedId = $integrationData['integrated_id'];
                        }
                    }

                    // Encrypt settings and access_token using destination's RAI_APP_KEY
                    $encryptedSettings = null;
                    if (!empty($integrationData['settings'])) {
                        $encryptedSettings = $this->encryptWithRaiKey($integrationData['settings']);
                    }

                    $encryptedAccessToken = null;
                    if (!empty($integrationData['access_token'])) {
                        $encryptedAccessToken = $this->encryptTokenWithRaiKey($integrationData['access_token']);
                    }

                    // Check if integration already exists
                    $existing = DB::connection($raiConn)
                        ->table('integrations')
                        ->where('integrated_type', $integratedType)
                        ->where('integrated_id', $integratedId)
                        ->where('provider_id', $integrationData['provider_id'])
                        ->first();

                    if ($dryRun) {
                        $this->line("Would " . ($existing ? "update" : "create") . " integration:");
                        $this->line("  Provider: {$integrationData['provider_name']} (ID: {$integrationData['provider_id']})");
                        $this->line("  Level: {$integrationData['level']}");
                        $this->line("  Tenant: {$tenant->name} (ID: {$tenantMasterId})");
                        if ($integrationData['level'] === 'location') {
                            $this->line("  Location: {$integrationData['location_name']} (ID: {$integratedId})");
                        }
                        $this->line("  Active: " . ($integrationData['is_active'] ? 'Yes' : 'No'));
                        $this->line('');
                        $imported++;
                        continue;
                    }

                    // Import the integration
                    $integrationData_db = [
                        'integrated_type' => $integratedType,
                        'integrated_id' => $integratedId,
                        'provider_id' => $integrationData['provider_id'],
                        'is_active' => $integrationData['is_active'] ?? true,
                        'updated_at' => now(),
                    ];

                    if ($encryptedSettings !== null) {
                        $integrationData_db['settings'] = $encryptedSettings;
                    }

                    if ($encryptedAccessToken !== null) {
                        $integrationData_db['access_token'] = $encryptedAccessToken;
                    }

                    if ($existing) {
                        // Update existing
                        DB::connection($raiConn)
                            ->table('integrations')
                            ->where('id', $existing->id)
                            ->update($integrationData_db);
                        
                        $this->info("Updated integration: {$integrationData['provider_name']} for tenant {$tenant->name}");
                    } else {
                        // Create new
                        $integrationData_db['created_at'] = $integrationData['created_at'] ?? now();
                        DB::connection($raiConn)
                            ->table('integrations')
                            ->insert($integrationData_db);
                        
                        $this->info("Created integration: {$integrationData['provider_name']} for tenant {$tenant->name}");
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $this->error("Error importing integration: " . $e->getMessage());
                    $errors++;
                }
            }

            $this->line('');
            $this->info("Import complete!");
            $this->info("  Imported: {$imported}");
            $this->info("  Skipped: {$skipped}");
            if ($errors > 0) {
                $this->error("  Errors: {$errors}");
            }

            return $errors > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}

