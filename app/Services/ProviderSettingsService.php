<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Stub ProviderSettingsService
 * TODO: Copy full implementation from RAI when needed
 */
class ProviderSettingsService
{
    public static function get(string $providerName, string $key, $default = null, ?int $tenantId = null)
    {
        return $default;
    }

    public static function all(string $providerName, ?int $tenantId = null): array
    {
        return [];
    }

    public static function allForLocation(string $providerName, int $locationId): array
    {
        return [];
    }

    public static function getForLocation(string $providerName, string $key, $default = null, int $locationId = null)
    {
        return $default;
    }

    public static function clearCache(string $providerName, ?int $tenantId = null): void
    {
        // Stub
    }

    public static function clearCacheForLocation(string $providerName, int $locationId): void
    {
        // Stub
    }
}

