<?php

namespace App\Services;

use App\Models\TenantMaster;
use App\Models\RdsInstance;
use App\Services\RdsConnectionService;
use App\Console\Commands\Traits\IntegrationEncryptionTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service to retrieve integration settings from RDS instances
 * Replaces ProviderSettingsService for RAIOPS commands
 */
class IntegrationSettingsService
{
    use IntegrationEncryptionTrait;

    /**
     * Map provider slugs to provider classnames
     */
    private static array $slugToProviderClass = [
        'SEVEN_SHIFTS_API' => 'App\Classes\Providers\SevenProvider',
        'TOAST_API' => 'App\Classes\Providers\ToastProvider',
        'RESY_API' => 'App\Classes\Providers\ResyProvider',
    ];

    /**
     * Get all settings for a provider from the RDS instance
     * 
     * @param int $tenantMasterId RAIOPS tenant_master_id
     * @param string $providerSlug Provider slug (e.g., 'TOAST_API', 'SEVEN_SHIFTS_API')
     * @param int|null $locationId Optional location ID for location-level integrations
     * @return array Settings array
     */
    public static function getAll(int $tenantMasterId, string $providerSlug, ?int $locationId = null): array
    {
        $tenant = TenantMaster::with('rdsInstance')->find($tenantMasterId);
        if (!$tenant || !$tenant->rdsInstance) {
            Log::warning("TenantMaster or RDS instance not found for ID: {$tenantMasterId}");
            return [];
        }

        $rdsInstance = $tenant->rdsInstance;
        $rdsConnectionService = app(RdsConnectionService::class);

        try {
            $raiConn = $rdsConnectionService->getConnection($rdsInstance);
            
            // Get remote tenant ID
            $remoteTenantId = $tenant->remote_tenant_id;
            
            // Map slug to provider classname
            $providerClass = self::$slugToProviderClass[$providerSlug] ?? null;
            if (!$providerClass) {
                Log::warning("Unknown provider slug: {$providerSlug}");
                return [];
            }

            // Find provider by classname
            $provider = DB::connection($raiConn)
                ->table('providers')
                ->where('classname', $providerClass)
                ->first();

            if (!$provider) {
                Log::warning("Provider not found for classname: {$providerClass}");
                return [];
            }

            // Query integration
            $query = DB::connection($raiConn)
                ->table('integrations')
                ->where('provider_id', $provider->id)
                ->where('is_active', true);

            if ($locationId) {
                // Location-level integration
                $query->where('integrated_type', 'App\Models\Rai\Location')
                      ->where('integrated_id', $locationId);
            } else {
                // Tenant-level integration
                $query->where('integrated_type', 'App\Models\Rai\Tenant')
                      ->where('integrated_id', $remoteTenantId);
            }

            $integration = $query->first();

            if (!$integration || !$integration->settings) {
                return [];
            }

            // Decrypt settings
            $service = new self();
            $settings = $service->decryptWithRaiKey($integration->settings);
            
            return is_array($settings) ? $settings : [];

        } catch (\Exception $e) {
            Log::error("Failed to get integration settings", [
                'tenant_master_id' => $tenantMasterId,
                'provider_slug' => $providerSlug,
                'location_id' => $locationId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get a single setting value for a provider
     * 
     * @param int $tenantMasterId RAIOPS tenant_master_id
     * @param string $providerSlug Provider slug (e.g., 'TOAST_API')
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @param int|null $locationId Optional location ID for location-level integrations
     * @return mixed
     */
    public static function get(int $tenantMasterId, string $providerSlug, string $key, $default = null, ?int $locationId = null)
    {
        $all = self::getAll($tenantMasterId, $providerSlug, $locationId);
        return $all[$key] ?? $default;
    }

    /**
     * Get provider ID from slug
     * 
     * @param string $providerSlug Provider slug (e.g., 'TOAST_API')
     * @param string $connectionName RDS database connection name
     * @return int|null Provider ID
     */
    public static function getProviderId(string $providerSlug, string $connectionName): ?int
    {
        $providerClass = self::$slugToProviderClass[$providerSlug] ?? null;
        if (!$providerClass) {
            return null;
        }

        $provider = DB::connection($connectionName)
            ->table('providers')
            ->where('classname', $providerClass)
            ->first();

        return $provider ? $provider->id : null;
    }
}

