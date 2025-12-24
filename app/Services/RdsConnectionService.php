<?php

namespace App\Services;

use App\Models\RdsInstance;
use App\Models\TenantMaster;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RdsConnectionService
{
    /**
     * Current active connections by RDS instance ID
     */
    protected array $activeConnections = [];

    /**
     * Get or create a connection to a specific RDS instance
     */
    public function getConnection(RdsInstance $rdsInstance): string
    {
        $connectionName = $this->getConnectionName($rdsInstance->id);

        // Check if connection is already configured
        if (!isset($this->activeConnections[$rdsInstance->id])) {
            $this->configureConnection($rdsInstance);
        }

        return $connectionName;
    }

    /**
     * Configure a database connection for an RDS instance
     */
    protected function configureConnection(RdsInstance $rdsInstance): void
    {
        $connectionName = $this->getConnectionName($rdsInstance->id);

        // Set up the connection configuration
        Config::set("database.connections.{$connectionName}", $rdsInstance->getRaiConnectionConfig());

        // Also configure providers connection if needed
        $providersConnectionName = $this->getProvidersConnectionName($rdsInstance->id);
        if ($rdsInstance->providers_database) {
            Config::set("database.connections.{$providersConnectionName}", $rdsInstance->getProvidersConnectionConfig());
        }

        $this->activeConnections[$rdsInstance->id] = true;

        Log::debug("RdsConnectionService: Configured connection for RDS {$rdsInstance->id} ({$rdsInstance->name})");
    }

    /**
     * Get the RAI database connection name for an RDS instance
     */
    public function getConnectionName(int $rdsInstanceId): string
    {
        return "rds_{$rdsInstanceId}";
    }

    /**
     * Get the providers database connection name for an RDS instance
     */
    public function getProvidersConnectionName(int $rdsInstanceId): string
    {
        return "rds_{$rdsInstanceId}_providers";
    }

    /**
     * Execute a query on a specific RDS instance
     */
    public function query(RdsInstance $rdsInstance): \Illuminate\Database\Connection
    {
        $connectionName = $this->getConnection($rdsInstance);
        return DB::connection($connectionName);
    }

    /**
     * Execute a callback with a specific RDS connection
     */
    public function withConnection(RdsInstance $rdsInstance, callable $callback)
    {
        $connectionName = $this->getConnection($rdsInstance);
        return $callback(DB::connection($connectionName));
    }

    /**
     * Test connection to an RDS instance
     */
    public function testConnection(RdsInstance $rdsInstance): array
    {
        return $rdsInstance->testConnection();
    }

    /**
     * Run health checks on all active RDS instances
     */
    public function runHealthChecks(): array
    {
        $results = [];

        $instances = RdsInstance::active()->get();

        foreach ($instances as $instance) {
            $result = $instance->testConnection();
            
            $instance->update([
                'health_status' => $result['success'] ? 'healthy' : 'down',
                'last_health_check_at' => now(),
            ]);

            $results[$instance->id] = [
                'name' => $instance->name,
                'success' => $result['success'],
                'message' => $result['message'],
                'latency_ms' => $result['latency_ms'],
            ];

            Log::info("RDS Health Check: {$instance->name}", $result);
        }

        return $results;
    }

    /**
     * Get tenant count from an RDS instance
     */
    public function getTenantCount(RdsInstance $rdsInstance): int
    {
        try {
            return $this->query($rdsInstance)
                ->table('tenants')
                ->count();
        } catch (\Exception $e) {
            Log::error("Failed to get tenant count from RDS {$rdsInstance->id}", [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get user count for a tenant from an RDS instance
     */
    public function getUserCount(RdsInstance $rdsInstance, int $tenantId): int
    {
        try {
            return $this->query($rdsInstance)
                ->table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 'Active')
                ->count();
        } catch (\Exception $e) {
            Log::error("Failed to get user count from RDS {$rdsInstance->id}", [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get location count for a tenant from an RDS instance
     */
    public function getLocationCount(RdsInstance $rdsInstance, int $tenantId): int
    {
        try {
            return $this->query($rdsInstance)
                ->table('locations')
                ->where('tenant_id', $tenantId)
                ->count();
        } catch (\Exception $e) {
            Log::error("Failed to get location count from RDS {$rdsInstance->id}", [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get locations for a tenant from an RDS instance
     */
    public function getLocations(RdsInstance $rdsInstance, int $tenantId): \Illuminate\Support\Collection
    {
        try {
            $db = $this->query($rdsInstance);
            
            $locations = $db->table('locations')
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get();

            // Add alias to each location
            foreach ($locations as $location) {
                $alias = $db->table('location_aliases')
                    ->where('location_id', $location->id)
                    ->first();
                $location->alias = $alias->name ?? null;
            }

            return $locations;
        } catch (\Exception $e) {
            Log::error("Failed to get locations from RDS {$rdsInstance->id}", [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get a single location by ID from an RDS instance
     */
    public function getLocation(RdsInstance $rdsInstance, int $locationId): ?object
    {
        try {
            return $this->query($rdsInstance)
                ->table('locations')
                ->where('id', $locationId)
                ->first();
        } catch (\Exception $e) {
            Log::error("Failed to get location from RDS {$rdsInstance->id}", [
                'location_id' => $locationId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a location on an RDS instance
     */
    public function createLocation(RdsInstance $rdsInstance, int $tenantId, array $data): ?int
    {
        try {
            $db = $this->query($rdsInstance);
            
            // Get the next location ID
            $locationMax = $db->table('locations')->max('id') ?? 0;
            $locationId = $locationMax + 1;

            // Create location
            $db->table('locations')->insert([
                'id' => $locationId,
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip' => $data['zip'] ?? null,
                'country' => $data['country'] ?? 'US',
                'timezone' => $data['timezone'] ?? 'America/New_York',
                'has_grouped_tips' => $data['has_grouped_tips'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create location alias if provided
            if (!empty($data['alias'])) {
                $db->table('location_aliases')->insert([
                    'name' => $data['alias'],
                    'location_id' => $locationId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Create location map for Toast if provided
            if (!empty($data['toast_location'])) {
                $toastProvider = $db->table('providers')
                    ->where('classname', 'App\\Classes\\Providers\\ToastProvider')
                    ->first();

                if ($toastProvider) {
                    $db->table('location_maps')->insert([
                        'external_id' => $data['toast_location'],
                        'location_id' => $locationId,
                        'provider_id' => $toastProvider->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $locationId;
        } catch (\Exception $e) {
            Log::error("Failed to create location on RDS {$rdsInstance->id}", [
                'tenant_id' => $tenantId,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update a location on an RDS instance
     */
    public function updateLocation(RdsInstance $rdsInstance, int $locationId, array $data): bool
    {
        try {
            $db = $this->query($rdsInstance);
            
            // Update location
            $updateData = [
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip' => $data['zip'] ?? null,
                'country' => $data['country'] ?? 'US',
                'timezone' => $data['timezone'] ?? 'America/New_York',
                'has_grouped_tips' => $data['has_grouped_tips'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'updated_at' => now(),
            ];

            $db->table('locations')
                ->where('id', $locationId)
                ->update($updateData);

            // Update or create location alias
            if (isset($data['alias'])) {
                $existingAlias = $db->table('location_aliases')
                    ->where('location_id', $locationId)
                    ->first();

                if ($existingAlias) {
                    if (!empty($data['alias'])) {
                        $db->table('location_aliases')
                            ->where('location_id', $locationId)
                            ->update([
                                'name' => $data['alias'],
                                'updated_at' => now(),
                            ]);
                    } else {
                        $db->table('location_aliases')
                            ->where('location_id', $locationId)
                            ->delete();
                    }
                } elseif (!empty($data['alias'])) {
                    $db->table('location_aliases')->insert([
                        'name' => $data['alias'],
                        'location_id' => $locationId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update or create Toast location map
            if (isset($data['toast_location'])) {
                $toastProvider = $db->table('providers')
                    ->where('classname', 'App\\Classes\\Providers\\ToastProvider')
                    ->first();

                if ($toastProvider) {
                    $existingMap = $db->table('location_maps')
                        ->where('location_id', $locationId)
                        ->where('provider_id', $toastProvider->id)
                        ->first();

                    if ($existingMap) {
                        if (!empty($data['toast_location'])) {
                            $db->table('location_maps')
                                ->where('location_id', $locationId)
                                ->where('provider_id', $toastProvider->id)
                                ->update([
                                    'external_id' => $data['toast_location'],
                                    'updated_at' => now(),
                                ]);
                        } else {
                            $db->table('location_maps')
                                ->where('location_id', $locationId)
                                ->where('provider_id', $toastProvider->id)
                                ->delete();
                        }
                    } elseif (!empty($data['toast_location'])) {
                        $db->table('location_maps')->insert([
                            'external_id' => $data['toast_location'],
                            'location_id' => $locationId,
                            'provider_id' => $toastProvider->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update location on RDS {$rdsInstance->id}", [
                'location_id' => $locationId,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete a location from an RDS instance
     */
    public function deleteLocation(RdsInstance $rdsInstance, int $locationId): bool
    {
        try {
            $db = $this->query($rdsInstance);
            
            // Delete related records first
            $db->table('location_aliases')->where('location_id', $locationId)->delete();
            $db->table('location_maps')->where('location_id', $locationId)->delete();
            
            // Delete location
            $db->table('locations')->where('id', $locationId)->delete();
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete location from RDS {$rdsInstance->id}", [
                'location_id' => $locationId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get tenants from an RDS instance
     */
    public function getTenants(RdsInstance $rdsInstance): \Illuminate\Support\Collection
    {
        try {
            return $this->query($rdsInstance)
                ->table('tenants')
                ->get();
        } catch (\Exception $e) {
            Log::error("Failed to get tenants from RDS {$rdsInstance->id}", [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get a specific tenant from an RDS instance
     */
    public function getTenant(RdsInstance $rdsInstance, int $tenantId): ?object
    {
        try {
            return $this->query($rdsInstance)
                ->table('tenants')
                ->where('id', $tenantId)
                ->first();
        } catch (\Exception $e) {
            Log::error("Failed to get tenant from RDS {$rdsInstance->id}", [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get users for a tenant from an RDS instance
     */
    public function getUsers(RdsInstance $rdsInstance, int $tenantId): \Illuminate\Support\Collection
    {
        try {
            return $this->query($rdsInstance)
                ->table('users')
                ->where('tenant_id', $tenantId)
                ->get();
        } catch (\Exception $e) {
            Log::error("Failed to get users from RDS {$rdsInstance->id}", [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get user email routing entries from master RDS
     */
    public function getUserEmailRouting(RdsInstance $masterRds): \Illuminate\Support\Collection
    {
        try {
            return $this->query($masterRds)
                ->table('user_email_routing')
                ->get();
        } catch (\Exception $e) {
            Log::error("Failed to get user_email_routing from master RDS", [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get provider IDs that a tenant has active integrations for
     * Checks both tenant-level and location-level integrations
     * 
     * @param RdsInstance $rdsInstance
     * @param int $tenantId RAI tenant_id (not tenant_master_id)
     * @return array Array of provider IDs
     */
    public function getTenantProviderIds(RdsInstance $rdsInstance, int $tenantId): array
    {
        $providerIds = [];

        try {
            $db = $this->query($rdsInstance);

            // Get tenant-level integrations
            $tenantIntegrations = $db->table('integrations')
                ->where('integrated_type', 'App\\Models\\Rai\\Tenant')
                ->where('integrated_id', $tenantId)
                ->where('is_active', true)
                ->pluck('provider_id')
                ->toArray();

            $providerIds = array_merge($providerIds, $tenantIntegrations);

            // Get location IDs for this tenant
            $locationIds = $db->table('locations')
                ->where('tenant_id', $tenantId)
                ->pluck('id')
                ->toArray();

            // Get location-level integrations
            if (!empty($locationIds)) {
                $locationIntegrations = $db->table('integrations')
                    ->where('integrated_type', 'App\\Models\\Rai\\Location')
                    ->whereIn('integrated_id', $locationIds)
                    ->where('is_active', true)
                    ->pluck('provider_id')
                    ->toArray();

                $providerIds = array_merge($providerIds, $locationIntegrations);
            }

            $providerIds = array_unique(array_filter($providerIds));

            Log::debug("Got tenant provider IDs from RDS", [
                'rds_instance_id' => $rdsInstance->id,
                'tenant_id' => $tenantId,
                'provider_ids' => $providerIds,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get tenant provider IDs from RDS {$rdsInstance->id}", [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        return $providerIds;
    }

    /**
     * Get scheduled commands for a tenant from an RDS instance
     * Returns commands where provider_id matches tenant's integrations OR provider_id is null
     * 
     * @param RdsInstance $rdsInstance
     * @param int $tenantId RAI tenant_id (not tenant_master_id)
     * @return \Illuminate\Support\Collection
     */
    public function getScheduledCommandsForTenant(RdsInstance $rdsInstance, int $tenantId): \Illuminate\Support\Collection
    {
        try {
            $db = $this->query($rdsInstance);

            // Get provider IDs this tenant has
            $providerIds = $this->getTenantProviderIds($rdsInstance, $tenantId);

            // Build query for scheduled commands
            $query = $db->table('scheduled_commands')
                ->leftJoin('providers', 'scheduled_commands.provider_id', '=', 'providers.id')
                ->where('scheduled_commands.is_active', true)
                ->where('scheduled_commands.default_enabled', true)
                ->select(
                    'scheduled_commands.id',
                    'scheduled_commands.command_name',
                    'scheduled_commands.display_name',
                    'scheduled_commands.description',
                    'scheduled_commands.category',
                    'scheduled_commands.provider_id',
                    'scheduled_commands.default_params',
                    'scheduled_commands.requires_tenant',
                    'scheduled_commands.sort_order',
                    'providers.name as provider_name'
                );

            // Filter: provider_id is NULL OR in tenant's provider list
            $query->where(function ($q) use ($providerIds) {
                $q->whereNull('scheduled_commands.provider_id');
                if (!empty($providerIds)) {
                    $q->orWhereIn('scheduled_commands.provider_id', $providerIds);
                }
            });

            $commands = $query->orderBy('scheduled_commands.sort_order')
                ->orderBy('scheduled_commands.display_name')
                ->get();

            // Decode default_params JSON
            foreach ($commands as $command) {
                $command->default_params = $command->default_params 
                    ? json_decode($command->default_params, true) 
                    : [];
            }

            Log::debug("Got scheduled commands for tenant from RDS", [
                'rds_instance_id' => $rdsInstance->id,
                'tenant_id' => $tenantId,
                'command_count' => $commands->count(),
            ]);

            return $commands;

        } catch (\Exception $e) {
            Log::error("Failed to get scheduled commands from RDS {$rdsInstance->id}", [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get all providers from an RDS instance
     * 
     * @param RdsInstance $rdsInstance
     * @return \Illuminate\Support\Collection
     */
    public function getProviders(RdsInstance $rdsInstance): \Illuminate\Support\Collection
    {
        try {
            return $this->query($rdsInstance)
                ->table('providers')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            Log::error("Failed to get providers from RDS {$rdsInstance->id}", [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Get all scheduled commands from an RDS instance (unfiltered)
     * 
     * @param RdsInstance $rdsInstance
     * @return \Illuminate\Support\Collection
     */
    public function getAllScheduledCommands(RdsInstance $rdsInstance): \Illuminate\Support\Collection
    {
        try {
            $db = $this->query($rdsInstance);

            $commands = $db->table('scheduled_commands')
                ->leftJoin('providers', 'scheduled_commands.provider_id', '=', 'providers.id')
                ->where('scheduled_commands.is_active', true)
                ->select(
                    'scheduled_commands.id',
                    'scheduled_commands.command_name',
                    'scheduled_commands.display_name',
                    'scheduled_commands.description',
                    'scheduled_commands.category',
                    'scheduled_commands.provider_id',
                    'scheduled_commands.default_params',
                    'scheduled_commands.requires_tenant',
                    'scheduled_commands.default_enabled',
                    'scheduled_commands.sort_order',
                    'providers.name as provider_name'
                )
                ->orderBy('scheduled_commands.category')
                ->orderBy('scheduled_commands.sort_order')
                ->orderBy('scheduled_commands.display_name')
                ->get();

            // Decode default_params JSON
            foreach ($commands as $command) {
                $command->default_params = $command->default_params 
                    ? json_decode($command->default_params, true) 
                    : [];
            }

            return $commands;

        } catch (\Exception $e) {
            Log::error("Failed to get all scheduled commands from RDS {$rdsInstance->id}", [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    /**
     * Check if a table exists in the database
     */
    /**
     * Purge a connection to force reconnection
     */
    public function purgeConnection(RdsInstance $rdsInstance): void
    {
        $connectionName = $this->getConnectionName($rdsInstance->id);
        DB::purge($connectionName);
        unset($this->activeConnections[$rdsInstance->id]);
    }

    /**
     * Purge all RDS connections
     */
    public function purgeAllConnections(): void
    {
        foreach (array_keys($this->activeConnections) as $rdsId) {
            $connectionName = $this->getConnectionName($rdsId);
            DB::purge($connectionName);
        }

        $this->activeConnections = [];
    }

    /**
    * Switch to a specific RDS instance by ID (sets default connection)
    */
    public function switchToRdsByInstanceId(int $rdsInstanceId): bool
    {
        $instance = RdsInstance::find($rdsInstanceId);
        if (!$instance) {
            Log::warning('RdsConnectionService: RDS instance not found', ['rds_instance_id' => $rdsInstanceId]);
            return false;
        }

        // Configure connections
        $this->configureConnection($instance);

        $connectionName = $this->getConnectionName($instance->id);
        DB::setDefaultConnection($connectionName);
        Config::set('database.default', $connectionName);

        // Store in session for later requests
        session(['current_rds_instance_id' => $instance->id]);
        session(['current_rds_connection' => $connectionName]);

        Log::info('RdsConnectionService: switched default connection', [
            'rds_instance_id' => $instance->id,
            'connection_name' => $connectionName,
        ]);

        return true;
    }

    /**
    * Switch to RDS instance by tenant_master id
    */
    public function switchToRdsByTenant(int $tenantMasterId): bool
    {
        $tenant = TenantMaster::find($tenantMasterId);
        if (!$tenant || !$tenant->rds_instance_id) {
            Log::warning('RdsConnectionService: tenant or rds_instance_id not found', [
                'tenant_master_id' => $tenantMasterId,
            ]);
            return false;
        }

        return $this->switchToRdsByInstanceId($tenant->rds_instance_id);
    }
}

