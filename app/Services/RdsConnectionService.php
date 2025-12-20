<?php

namespace App\Services;

use App\Models\RdsInstance;
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
}

