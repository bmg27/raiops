<?php

namespace App\Livewire\Admin;

use App\Models\RdsInstance;
use App\Models\TenantMaster;
use App\Models\AuditLog;
use App\Services\RdsConnectionService;
use App\Services\ImpersonationTokenService;
use App\Services\TenantCreationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Encryption\Encrypter;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

/**
 * TenantMultiRds Component
 * 
 * RAIOPS Command Central's multi-RDS tenant management.
 * Displays tenants from the tenant_master registry with RDS context,
 * and can fetch live data from the correct RDS when drilling down.
 */
class TenantMultiRds extends Component
{
    use WithPagination, WithoutUrlPagination;

    protected $paginationTheme = 'bootstrap';

    // Search & Filter
    public string $search = '';
    public string $statusFilter = 'all';
    public string $rdsFilter = 'all';
    public int $perPage = 25;

    // Selected tenant for detail view
    public ?int $selectedTenantId = null;
    
    // Active section/tab in tenant detail view
    public string $activeSection = 'overview';
    
    // Live data from RDS (populated when viewing details)
    public ?array $liveData = null;
    public bool $loadingLiveData = false;
    
    // Users tab - search and pagination
    public string $userSearch = '';
    
    // Locations tab - search and pagination
    public string $locationSearch = '';

    // Integration/Provider Management
    public bool $showProviderSettingsModal = false;
    public ?int $selectedProviderId = null;
    public string $providerSettingsText = '';
    public bool $providerActive = true;
    public array $integrationFields = []; // dynamic key => value for selected provider
    public ?int $selectedLocationId = null; // For location-level integrations
    public array $tenantLocations = []; // Available locations for the selected tenant (id => name)
    public array $locationMaps = []; // Location map external_ids keyed by location_id
    public bool $isEditingIntegration = false;
    
    // Available providers (catalog) id => name (cached per RDS)
    public array $availableProviders = [];
    public bool $selectedProviderHasLocation = false;
    public array $selectedProviderFieldSchema = [];
    public ?string $selectedProviderLabel = null;

    // Sync status
    public ?string $lastSyncMessage = null;

    // Impersonation
    public ?string $impersonationUrl = null;
    public ?string $impersonationError = null;

    // Create Tenant Modal
    public bool $showCreateModal = false;
    public ?int $createRdsInstanceId = null;
    public string $createName = '';
    public string $createContactName = '';
    public string $createContactEmail = '';
    public string $createPassword = '';
    public string $createPasswordConfirmation = '';
    public string $createStatus = 'trial';
    public ?string $createTrialEndsAt = null;
    public string $createPlanName = 'starter';
    public int $createLocationCount = 1;
    
    // Location fields (first location - required)
    public string $createLocationName = '';
    public ?string $createLocationAlias = null;
    public ?string $createLocationAddress = null;
    public ?string $createLocationCity = null;
    public ?string $createLocationState = null;
    public string $createLocationCountry = 'US';
    public ?string $createLocationToastLocation = null;
    
    public ?string $createError = null;

    // Location Management Modal
    public bool $showLocationModal = false;
    public ?int $editingLocationId = null;
    public string $locationName = '';
    public ?string $locationAlias = null;
    public ?string $locationAddress = null;
    public ?string $locationCity = null;
    public ?string $locationState = null;
    public ?string $locationZip = null;
    public string $locationCountry = 'US';
    public ?string $locationTimezone = 'America/New_York';
    public ?string $locationToastLocation = null;
    public bool $locationIsActive = true;
    public bool $locationHasGroupedTips = false;
    public ?string $locationError = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'rdsFilter' => ['except' => 'all'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRdsFilter(): void
    {
        $this->resetPage();
    }

    /**
     * View tenant details - fetches live data from the RDS
     */
    public function viewDetails(int $tenantId): void
    {
        $this->selectedTenantId = $tenantId;
        $this->loadLiveData();
    }

    /**
     * Load live data from the tenant's RDS
     */
    public function loadLiveData(): void
    {
        if (!$this->selectedTenantId) {
            return;
        }

        $this->loadingLiveData = true;
        $this->liveData = null;

        try {
            $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
            
            if (!$tenant || !$tenant->rdsInstance) {
                $this->liveData = ['error' => 'Tenant or RDS instance not found'];
                return;
            }

            $rds = $tenant->rdsInstance;
            $service = app(RdsConnectionService::class);

            // Fetch live data from the RDS
            $remoteTenant = $service->getTenant($rds, $tenant->remote_tenant_id);
            $users = $service->getUsers($rds, $tenant->remote_tenant_id);
            $locations = $service->getLocations($rds, $tenant->remote_tenant_id);
            $locationCount = $service->getLocationCount($rds, $tenant->remote_tenant_id);
            $userCount = $service->getUserCount($rds, $tenant->remote_tenant_id);

            $this->liveData = [
                'success' => true,
                'tenant' => $remoteTenant,
                'users' => $users, // All users for pagination
                'total_users' => $users->count(),
                'user_count' => $userCount,
                'locations' => $locations, // All locations for pagination
                'location_count' => $locationCount,
                'fetched_at' => now()->toDateTimeString(),
            ];

            // Update cached counts in tenant_master
            $tenant->update([
                'cached_user_count' => $userCount,
                'cached_location_count' => $locationCount,
                'cache_refreshed_at' => now(),
            ]);

        } catch (\Exception $e) {
            $this->liveData = [
                'error' => 'Failed to fetch live data: ' . $e->getMessage(),
            ];
        } finally {
            $this->loadingLiveData = false;
        }
    }

    /**
     * Refresh live data
     */
    public function refreshLiveData(): void
    {
        $this->loadLiveData();
        session()->flash('success', 'Live data refreshed from RDS.');
    }

    /**
     * Go back to tenant list
     */
    public function backToList(): void
    {
        $this->selectedTenantId = null;
        $this->liveData = null;
        $this->activeSection = 'overview';
    }

    /**
     * Set active section/tab
     */
    public function setActiveSection(string $section): void
    {
        $this->activeSection = $section;
        $this->resetPage(); // Reset pagination when switching tabs
    }

    /**
     * Reset page when search changes
     */
    public function updatingUserSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLocationSearch(): void
    {
        $this->resetPage();
    }


    /**
     * Get paginated users for the selected tenant
     */
    public function getUsersProperty()
    {
        if (!$this->selectedTenantId || !$this->liveData || !isset($this->liveData['users'])) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $this->perPage,
                1,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        $users = collect($this->liveData['users']);
        
        // Apply search filter
        if (!empty($this->userSearch)) {
            $search = strtolower($this->userSearch);
            $users = $users->filter(function ($user) use ($search) {
                return str_contains(strtolower($user->name ?? ''), $search) ||
                       str_contains(strtolower($user->email ?? ''), $search);
            });
        }

        // Convert to paginated collection
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = $this->perPage;
        $items = $users->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $users->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Get paginated locations for the selected tenant
     */
    public function getLocationsProperty()
    {
        if (!$this->selectedTenantId || !$this->liveData || !isset($this->liveData['locations'])) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $this->perPage,
                1,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        $locations = collect($this->liveData['locations']);
        
        // Apply search filter
        if (!empty($this->locationSearch)) {
            $search = strtolower($this->locationSearch);
            $locations = $locations->filter(function ($location) use ($search) {
                return str_contains(strtolower($location->name ?? ''), $search) ||
                       str_contains(strtolower($location->city ?? ''), $search) ||
                       str_contains(strtolower($location->state ?? ''), $search);
            });
        }

        // Convert to paginated collection
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $perPage = $this->perPage;
        $items = $locations->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $locations->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Get integrations for the selected tenant
     * Returns array of integration data with provider names
     */
    public function getIntegrations(): array
    {
        if (!$this->selectedTenantId) {
            return [];
        }

        $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
        if (!$tenant || !$tenant->rdsInstance) {
            return [];
        }

        try {
            $rds = $tenant->rdsInstance;
            $service = app(RdsConnectionService::class);
            $raiConn = $service->getConnection($rds);

            // Get remote location IDs from live data
            $remoteLocationIds = [];
            $locationMap = [];
            if ($this->liveData && isset($this->liveData['locations'])) {
                $remoteLocationIds = collect($this->liveData['locations'])->pluck('id')->toArray();
                foreach ($this->liveData['locations'] as $location) {
                    $locationMap[$location->id] = $location->name ?? "Location {$location->id}";
                }
            }

            // Query tenant-level integrations
            $tenantIntegrations = DB::connection($raiConn)
                ->table('integrations')
                ->where('integrated_type', 'App\Models\Rai\Tenant')
                ->where('integrated_id', $tenant->remote_tenant_id)
                ->get();

            // Query location-level integrations
            $locationIntegrations = collect();
            if (!empty($remoteLocationIds)) {
                $locationIntegrations = DB::connection($raiConn)
                    ->table('integrations')
                    ->where('integrated_type', 'App\Models\Rai\Location')
                    ->whereIn('integrated_id', $remoteLocationIds)
                    ->get();
            }

            // Get all unique provider IDs
            $providerIds = collect($tenantIntegrations)
                ->merge($locationIntegrations)
                ->pluck('provider_id')
                ->unique()
                ->filter()
                ->values()
                ->toArray();

            // Query providers from RAI database (providers table is in RAI DB)
            $providers = [];
            if (!empty($providerIds)) {
                $providersData = DB::connection($raiConn)
                    ->table('providers')
                    ->whereIn('id', $providerIds)
                    ->get()
                    ->keyBy('id');
                
                foreach ($providersData as $provider) {
                    $providers[$provider->id] = [
                        'name' => $provider->name,
                        'has_location' => (bool) $provider->has_location,
                        'classname' => $provider->classname ?? null,
                    ];
                }
            }

            $result = [];
            
            // Add tenant-level integrations
            foreach ($tenantIntegrations as $integration) {
                $provider = $providers[$integration->provider_id] ?? null;
                $result[] = [
                    'id' => $integration->id,
                    'provider_id' => $integration->provider_id,
                    'provider_name' => $provider['name'] ?? 'Unknown Provider',
                    'provider_classname' => $provider['classname'] ?? null,
                    'is_location_level' => false,
                    'location_id' => null,
                    'location_name' => null,
                    'is_active' => (bool) $integration->is_active,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ];
            }

            // Add location-level integrations
            foreach ($locationIntegrations as $integration) {
                $provider = $providers[$integration->provider_id] ?? null;
                $result[] = [
                    'id' => $integration->id,
                    'provider_id' => $integration->provider_id,
                    'provider_name' => $provider['name'] ?? 'Unknown Provider',
                    'provider_classname' => $provider['classname'] ?? null,
                    'is_location_level' => true,
                    'location_id' => $integration->integrated_id,
                    'location_name' => $locationMap[$integration->integrated_id] ?? "Location {$integration->integrated_id}",
                    'is_active' => (bool) $integration->is_active,
                    'created_at' => $integration->created_at,
                    'updated_at' => $integration->updated_at,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            \Log::error('Failed to get integrations', [
                'tenant_id' => $this->selectedTenantId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ========== PROVIDER/INTEGRATION MANAGEMENT ==========

    /**
     * Load available providers catalog from providers database (cached per RDS)
     */
    protected function loadAvailableProviders(): void
    {
        if (!$this->selectedTenantId) {
            $this->availableProviders = [];
            return;
        }

        $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
        if (!$tenant || !$tenant->rdsInstance) {
            $this->availableProviders = [];
            return;
        }

        $rds = $tenant->rdsInstance;
        $cacheKey = "providers_catalog_rds_{$rds->id}";

        try {
            $service = app(RdsConnectionService::class);
            $raiConn = $service->getConnection($rds);
            
            // Providers table is in RAI database
            $providers = DB::connection($raiConn)
                ->table('providers')
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'has_location']);

            $this->availableProviders = $providers->pluck('name', 'id')->toArray();
            
            // Cache the result for future use
            if (!empty($this->availableProviders)) {
                Cache::put($cacheKey, $this->availableProviders, now()->addHours(24));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to load providers catalog', [
                'rds_id' => $rds->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->availableProviders = [];
        }
    }

    /**
     * Open provider settings modal
     */
    public function openProviderSettingsModal(?int $providerId = null, ?int $locationId = null): void
    {
        if (!auth()->user()?->hasRaiOpsPermission('tenant.manage')) {
            abort(403, 'You do not have permission to manage integrations.');
        }

        $this->loadAvailableProviders();
        $this->resetProviderSettingsForm();
        $this->selectedLocationId = $locationId;
        
        // Load tenant locations
        if ($this->selectedTenantId) {
            $this->loadTenantLocations();
        }

        if ($providerId !== null) {
            $this->selectedProviderId = $providerId;
            $this->refreshSelectedProviderMeta($providerId);
            $this->loadLocationMaps();

            $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
            if (!$tenant || !$tenant->rdsInstance) {
                $this->dispatch('notify', type: 'error', message: 'RDS instance not found.');
                return;
            }

            $rds = $tenant->rdsInstance;
            $service = app(RdsConnectionService::class);
            $raiConn = $service->getConnection($rds);

            $provider = DB::connection($raiConn)
                ->table('providers')
                ->where('id', $providerId)
                ->first();

            if (!$provider) {
                $this->dispatch('notify', type: 'error', message: 'Provider not found.');
                return;
            }

            if ($provider->has_location) {
                if ($locationId !== null) {
                    // Query integrations from RAI database
                    $integration = DB::connection($raiConn)
                        ->table('integrations')
                        ->where('provider_id', $providerId)
                        ->where('integrated_type', 'App\Models\Rai\Location')
                        ->where('integrated_id', $locationId)
                        ->first();

                    if ($integration) {
                        $this->isEditingIntegration = true;
                        $this->selectedLocationId = $locationId;
                        $settings = $this->decryptSettings($integration->settings);
                        $this->providerSettingsText = json_encode($settings, JSON_PRETTY_PRINT);
                        $this->providerActive = (bool) $integration->is_active;
                        $this->populateIntegrationFieldsFromSettings($providerId, $settings, $raiConn);
                    } else {
                        $this->populateIntegrationFieldsFromSettings($providerId, [], $raiConn);
                    }
                } else {
                    $this->populateIntegrationFieldsFromSettings($providerId, [], $raiConn);
                }
            } else {
                // Query integrations from RAI database
                $integration = DB::connection($raiConn)
                    ->table('integrations')
                    ->where('provider_id', $providerId)
                    ->where('integrated_type', 'App\Models\Rai\Tenant')
                    ->where('integrated_id', $tenant->remote_tenant_id)
                    ->first();

                if ($integration) {
                    $this->isEditingIntegration = true;
                    $settings = $this->decryptSettings($integration->settings);
                    $this->providerSettingsText = json_encode($settings, JSON_PRETTY_PRINT);
                    $this->providerActive = (bool) $integration->is_active;
                    $this->populateIntegrationFieldsFromSettings($providerId, $settings, $raiConn);
                } else {
                    $this->populateIntegrationFieldsFromSettings($providerId, [], $raiConn);
                }
            }
        }

        $this->showProviderSettingsModal = true;
    }

    /**
     * Load tenant locations from live data
     */
    protected function loadTenantLocations(): void
    {
        $this->tenantLocations = [];

        if ($this->liveData && isset($this->liveData['locations'])) {
            foreach ($this->liveData['locations'] as $location) {
                $this->tenantLocations[$location->id] = $location->name ?? "Location {$location->id}";
            }
        }
    }

    /**
     * Load location maps for the selected provider
     */
    protected function loadLocationMaps(): void
    {
        $this->locationMaps = [];

        if (!$this->selectedProviderId || empty($this->tenantLocations)) {
            return;
        }

        $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
        if (!$tenant || !$tenant->rdsInstance) {
            return;
        }

        try {
            $rds = $tenant->rdsInstance;
            $service = app(RdsConnectionService::class);
            $raiConn = $service->getConnection($rds);

            $locationIds = array_keys($this->tenantLocations);

            // Query location_maps from RAI database
            $maps = DB::connection($raiConn)
                ->table('location_maps')
                ->where('provider_id', $this->selectedProviderId)
                ->whereIn('location_id', $locationIds)
                ->get();

            foreach ($maps as $map) {
                $this->locationMaps[$map->location_id] = $map->external_id ?? '';
            }

            // Initialize empty values for locations without maps
            foreach ($locationIds as $locationId) {
                if (!isset($this->locationMaps[$locationId])) {
                    $this->locationMaps[$locationId] = '';
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to load location maps', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Refresh selected provider metadata
     */
    protected function refreshSelectedProviderMeta(?int $providerId = null): void
    {
        if (!$providerId || !$this->selectedTenantId) {
            $this->selectedProviderLabel = null;
            $this->selectedProviderHasLocation = false;
            $this->selectedProviderFieldSchema = [];
            return;
        }

        $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
        if (!$tenant || !$tenant->rdsInstance) {
            return;
        }

        try {
            $rds = $tenant->rdsInstance;
            $service = app(RdsConnectionService::class);
            $raiConn = $service->getConnection($rds);

            $provider = DB::connection($raiConn)
                ->table('providers')
                ->where('id', $providerId)
                ->first();

            if ($provider) {
                $this->selectedProviderLabel = $provider->name;
                $this->selectedProviderHasLocation = (bool) $provider->has_location;
                $this->selectedProviderFieldSchema = is_array($provider->field_schema) 
                    ? $provider->field_schema 
                    : (json_decode($provider->field_schema, true) ?: []);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to refresh provider meta', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Populate integration fields from settings
     */
    protected function populateIntegrationFieldsFromSettings(int $providerId, array $settings, string $raiConn): void
    {
        if (!$this->selectedTenantId) {
            $this->integrationFields = [];
            return;
        }

        $fields = $this->getIntegrationFieldSchema($providerId, $raiConn);
        $this->integrationFields = [];

        \Log::debug('Populating integration fields', [
            'provider_id' => $providerId,
            'fields_count' => count($fields),
            'settings_keys' => array_keys($settings),
            'settings_count' => count($settings),
        ]);

        foreach ($fields as $fieldDef) {
            $key = $fieldDef['key'] ?? null;
            if ($key) {
                $this->integrationFields[$key] = $settings[$key] ?? '';
                \Log::debug('Set field', [
                    'key' => $key,
                    'has_value' => isset($settings[$key]) && $settings[$key] !== '',
                ]);
            }
        }
        
        \Log::debug('Final integrationFields', [
            'keys' => array_keys($this->integrationFields),
            'count' => count($this->integrationFields),
        ]);
    }

    /**
     * Get integration field schema for a provider
     */
    protected function getIntegrationFieldSchema(int $providerId, string $raiConn): array
    {
        try {
            $provider = DB::connection($raiConn)
                ->table('providers')
                ->where('id', $providerId)
                ->first();

            if (!$provider) {
                return [];
            }

            return is_array($provider->field_schema) 
                ? $provider->field_schema 
                : (json_decode($provider->field_schema, true) ?: []);
        } catch (\Exception $e) {
            \Log::error('Failed to get integration field schema', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Decrypt settings from database using RAI's encryption key
     */
    protected function decryptSettings(?string $encryptedSettings): array
    {
        if (empty($encryptedSettings)) {
            return [];
        }

        try {
            // Try with RAI's encryption key first
            // Try config first, then env directly as fallback
            $raiKey = config('raiops.rai_encryption_key') ?: env('RAI_APP_KEY');
            \Log::debug('Checking RAI encryption key', [
                'has_rai_key' => !empty($raiKey),
                'rai_key_length' => $raiKey ? strlen($raiKey) : 0,
                'from_config' => config('raiops.rai_encryption_key') ? 'SET' : 'NOT SET',
                'from_env' => env('RAI_APP_KEY') ? 'SET' : 'NOT SET',
            ]);
            if ($raiKey) {
                try {
                    // Use Laravel's Encrypter with RAI's key
                    $key = $this->parseKey($raiKey);
                    $encrypter = new Encrypter($key, 'AES-256-CBC');
                    $decrypted = $encrypter->decryptString($encryptedSettings);
                    $decoded = json_decode($decrypted, true);
                    if (is_array($decoded)) {
                        \Log::debug('Successfully decrypted with RAI key', [
                            'keys_count' => count($decoded),
                        ]);
                        return $decoded;
                    }
                } catch (\Exception $e) {
                    \Log::debug('Failed to decrypt with RAI key, trying RAIOPS key', [
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                \Log::debug('RAI encryption key not configured, trying RAIOPS key');
            }
            
            // Fallback to RAIOPS's key (in case they're the same)
            $decrypted = Crypt::decryptString($encryptedSettings);
            $decoded = json_decode($decrypted, true);
            if (is_array($decoded)) {
                \Log::debug('Successfully decrypted with RAIOPS key', [
                    'keys_count' => count($decoded),
                ]);
            }
            return is_array($decoded) ? $decoded : [];
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt integration settings', [
                'error' => $e->getMessage(),
                'has_rai_key' => !empty($raiKey ?? null),
                'encrypted_length' => strlen($encryptedSettings),
            ]);
            return [];
        }
    }


    /**
     * Parse encryption key (handles base64: prefix)
     */
    protected function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }
        return $key;
    }

    /**
     * Encrypt settings for database using RAI's encryption key
     */
    protected function encryptSettings(array $settings): string
    {
        $raiKey = config('raiops.rai_encryption_key') ?: env('RAI_APP_KEY');
        if ($raiKey) {
            try {
                // Use Laravel's Encrypter with RAI's key
                $key = $this->parseKey($raiKey);
                $encrypter = new Encrypter($key, 'AES-256-CBC');
                return $encrypter->encryptString(json_encode($settings));
            } catch (\Exception $e) {
                \Log::warning('Failed to encrypt with RAI key, using RAIOPS key', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Fallback to RAIOPS's key
        return Crypt::encryptString(json_encode($settings));
    }


    /**
     * Updated selected provider ID
     */
    public function updatedSelectedProviderId($value): void
    {
        $providerId = $value !== null && $value !== '' ? (int) $value : null;
        $this->selectedProviderId = $providerId;
        $this->refreshSelectedProviderMeta($providerId);

        if ($providerId && $this->selectedTenantId) {
            $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
            if ($tenant && $tenant->rdsInstance) {
                $rds = $tenant->rdsInstance;
                $service = app(RdsConnectionService::class);
                $raiConn = $service->getConnection($rds);
                $this->populateIntegrationFieldsFromSettings($providerId, [], $raiConn);
                $this->loadLocationMaps();
            }
        } else {
            $this->integrationFields = [];
            $this->providerSettingsText = '';
            $this->locationMaps = [];
        }
    }

    /**
     * Reset provider settings form
     */
    public function resetProviderSettingsForm(): void
    {
        $this->providerSettingsText = '';
        $this->providerActive = true;
        $this->integrationFields = [];
        $this->isEditingIntegration = false;
        $this->selectedLocationId = null;
        $this->tenantLocations = [];
        $this->locationMaps = [];
        $this->selectedProviderId = null;
        $this->refreshSelectedProviderMeta(null);
    }

    /**
     * Close provider settings modal
     */
    public function closeProviderSettingsModal(): void
    {
        $this->showProviderSettingsModal = false;
        $this->resetProviderSettingsForm();
    }

    /**
     * Save provider settings
     */
    public function saveProviderSettings(): void
    {
        if (!auth()->user()?->hasRaiOpsPermission('tenant.manage')) {
            abort(403, 'You do not have permission to manage integrations.');
        }

        $this->loadAvailableProviders();
        $validProviderIds = array_map('intval', array_keys($this->availableProviders));

        $this->validate([
            'selectedProviderId' => ['required', 'integer', Rule::in($validProviderIds)],
            'providerActive' => 'boolean',
        ]);

        $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
        if (!$tenant || !$tenant->rdsInstance) {
            $this->dispatch('notify', type: 'error', message: 'RDS instance not found.');
            return;
        }

        $rds = $tenant->rdsInstance;
        $service = app(RdsConnectionService::class);
        $raiConn = $service->getConnection($rds);

        $provider = DB::connection($raiConn)
            ->table('providers')
            ->where('id', $this->selectedProviderId)
            ->first();

        if (!$provider) {
            $this->addError('selectedProviderId', 'Selected provider could not be found.');
            return;
        }

        if ($provider->has_location) {
            $this->validate([
                'selectedLocationId' => [
                    'required',
                    'integer',
                ],
            ]);
        }

        // Validate field schema fields
        $fields = $this->getIntegrationFieldSchema($this->selectedProviderId, $raiConn);
        foreach ($fields as $fieldDef) {
            $key = $fieldDef['key'] ?? null;
            if (!$key) {
                continue;
            }
            if (!isset($this->integrationFields[$key]) || trim((string) $this->integrationFields[$key]) === '') {
                $label = $fieldDef['label'] ?? $key;
                $this->addError("integrationFields.{$key}", "{$label} is required.");
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        // Build settings array from field schema
        $settings = [];
        foreach ($fields as $fieldDef) {
            $key = $fieldDef['key'] ?? null;
            if ($key) {
                $settings[$key] = $this->integrationFields[$key] ?? '';
            }
        }
        
        // Only use JSON fallback if no field schema defined (shouldn't happen normally)
        if (empty($fields) && !empty($this->providerSettingsText)) {
            $decodedJson = json_decode($this->providerSettingsText, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
                $settings = $decodedJson;
            }
        }

        if (empty($fields) && empty($settings)) {
            if (trim($this->providerSettingsText) === '') {
                $this->addError('providerSettingsText', 'Settings are required.');
                return;
            }
            $decodedJson = json_decode($this->providerSettingsText, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedJson)) {
                $this->addError('providerSettingsText', 'Settings JSON is invalid: ' . json_last_error_msg());
                return;
            }
            $settings = $decodedJson;
        }

        // Find existing integration (from RAI database)
        if ($provider->has_location) {
            $integration = DB::connection($raiConn)
                ->table('integrations')
                ->where('integrated_type', 'App\Models\Rai\Location')
                ->where('integrated_id', $this->selectedLocationId)
                ->where('provider_id', $this->selectedProviderId)
                ->first();
        } else {
            $integration = DB::connection($raiConn)
                ->table('integrations')
                ->where('integrated_type', 'App\Models\Rai\Tenant')
                ->where('integrated_id', $tenant->remote_tenant_id)
                ->where('provider_id', $this->selectedProviderId)
                ->first();
        }

        // Update or create integration (in RAI database)
        $encryptedSettings = $this->encryptSettings($settings);
        
        if ($integration) {
            DB::connection($raiConn)
                ->table('integrations')
                ->where('id', $integration->id)
                ->update([
                    'settings' => $encryptedSettings,
                    'is_active' => $this->providerActive,
                    'updated_at' => now(),
                ]);
        } else {
            DB::connection($raiConn)
                ->table('integrations')
                ->insert([
                    'settings' => $encryptedSettings,
                    'is_active' => $this->providerActive,
                    'integrated_type' => $provider->has_location 
                        ? 'App\Models\Rai\Location' 
                        : 'App\Models\Rai\Tenant',
                    'integrated_id' => $provider->has_location 
                        ? $this->selectedLocationId 
                        : $tenant->remote_tenant_id,
                    'provider_id' => $this->selectedProviderId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        // Log audit
        AuditLog::log(
            'integration_saved',
            'TenantMaster',
            $tenant->id,
            null,
            [
                'provider_id' => $this->selectedProviderId,
                'provider_name' => $provider->name,
                'is_location_level' => (bool) $provider->has_location,
                'location_id' => $this->selectedLocationId,
            ]
        );

        $locationText = $provider->has_location && isset($this->tenantLocations[$this->selectedLocationId]) 
            ? " for location '{$this->tenantLocations[$this->selectedLocationId]}'"
            : '';

        $this->dispatch('notify', type: 'success', message: "Integration settings for '{$provider->name}'{$locationText} saved successfully!");
        $this->closeProviderSettingsModal();
        
        // Refresh integrations display
        $this->loadLiveData();
    }

    /**
     * Delete provider settings
     */
    public function deleteProviderSettings(int $providerId, ?int $locationId = null): void
    {
        if (!auth()->user()?->hasRaiOpsPermission('tenant.manage')) {
            abort(403, 'You do not have permission to delete integrations.');
        }

        $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
        if (!$tenant || !$tenant->rdsInstance) {
            $this->dispatch('notify', type: 'error', message: 'RDS instance not found.');
            return;
        }

        $rds = $tenant->rdsInstance;
        $service = app(RdsConnectionService::class);
        $raiConn = $service->getConnection($rds);

        $provider = DB::connection($raiConn)
            ->table('providers')
            ->where('id', $providerId)
            ->first();

        if (!$provider) {
            $this->dispatch('notify', type: 'error', message: 'Provider not found.');
            return;
        }

        // Delete from RAI database
        if ($provider->has_location && $locationId !== null) {
            DB::connection($raiConn)
                ->table('integrations')
                ->where('provider_id', $providerId)
                ->where('integrated_type', 'App\Models\Rai\Location')
                ->where('integrated_id', $locationId)
                ->delete();
        } else {
            DB::connection($raiConn)
                ->table('integrations')
                ->where('provider_id', $providerId)
                ->where('integrated_type', 'App\Models\Rai\Tenant')
                ->where('integrated_id', $tenant->remote_tenant_id)
                ->delete();
        }

        // Log audit
        AuditLog::log(
            'integration_deleted',
            'TenantMaster',
            $tenant->id,
            null,
            [
                'provider_id' => $providerId,
                'provider_name' => $provider->name,
                'is_location_level' => (bool) $provider->has_location,
                'location_id' => $locationId,
            ]
        );

        $locationText = $locationId && isset($this->tenantLocations[$locationId]) 
            ? " for location '{$this->tenantLocations[$locationId]}'"
            : '';

        $this->dispatch('notify', type: 'success', message: "Integration for '{$provider->name}'{$locationText} deleted successfully!");
        
        // Refresh integrations display
        $this->loadLiveData();
    }

    /**
     * Sync a single tenant's cached data from RDS
     */
    public function syncTenant(int $tenantId): void
    {
        try {
            $tenant = TenantMaster::with('rdsInstance')->find($tenantId);
            
            if (!$tenant || !$tenant->rdsInstance) {
                session()->flash('error', 'Tenant or RDS instance not found.');
                return;
            }

            $rds = $tenant->rdsInstance;
            $service = app(RdsConnectionService::class);

            // Fetch fresh data
            $remoteTenant = $service->getTenant($rds, $tenant->remote_tenant_id);
            $userCount = $service->getUserCount($rds, $tenant->remote_tenant_id);
            $locationCount = $service->getLocationCount($rds, $tenant->remote_tenant_id);

            // Update cached data
            $tenant->update([
                'name' => $remoteTenant->name ?? $tenant->name,
                'primary_contact_name' => $remoteTenant->primary_contact_name ?? $tenant->primary_contact_name,
                'primary_contact_email' => $remoteTenant->primary_contact_email ?? $tenant->primary_contact_email,
                'status' => $remoteTenant->status ?? $tenant->status,
                'cached_user_count' => $userCount,
                'cached_location_count' => $locationCount,
                'cache_refreshed_at' => now(),
            ]);

            AuditLog::log('synced', 'TenantMaster', $tenant->id, null, [
                'source_rds' => $rds->name,
                'user_count' => $userCount,
                'location_count' => $locationCount,
            ]);

            session()->flash('success', "Synced data for '{$tenant->name}' from {$rds->name}.");

        } catch (\Exception $e) {
            session()->flash('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync all tenants from all RDS instances
     */
    public function syncAllTenants(): void
    {
        try {
            $service = app(RdsConnectionService::class);
            $rdsInstances = RdsInstance::active()->get();
            
            $synced = 0;
            $errors = 0;

            foreach ($rdsInstances as $rds) {
                try {
                    $remoteTenants = $service->getTenants($rds);

                    foreach ($remoteTenants as $remoteTenant) {
                        $userCount = $service->getUserCount($rds, $remoteTenant->id);
                        $locationCount = $service->getLocationCount($rds, $remoteTenant->id);

                        TenantMaster::updateOrCreate(
                            [
                                'rds_instance_id' => $rds->id,
                                'remote_tenant_id' => $remoteTenant->id,
                            ],
                            [
                                'name' => $remoteTenant->name,
                                'primary_contact_name' => $remoteTenant->primary_contact_name ?? null,
                                'primary_contact_email' => $remoteTenant->primary_contact_email ?? null,
                                'status' => $remoteTenant->status ?? 'active',
                                'trial_ends_at' => $remoteTenant->trial_ends_at ?? null,
                                'cached_user_count' => $userCount,
                                'cached_location_count' => $locationCount,
                                'cache_refreshed_at' => now(),
                            ]
                        );
                        $synced++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    \Log::error("Failed to sync tenants from RDS {$rds->name}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            AuditLog::log('bulk_synced', 'TenantMaster', null, null, [
                'tenants_synced' => $synced,
                'errors' => $errors,
            ]);

            session()->flash('success', "Synced {$synced} tenants from " . $rdsInstances->count() . " RDS instances." . ($errors ? " ({$errors} errors)" : ''));

        } catch (\Exception $e) {
            session()->flash('error', 'Bulk sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the list of RDS instances for the filter dropdown
     */
    public function getRdsOptionsProperty(): array
    {
        return RdsInstance::active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Launch impersonation into the tenant's RAI instance
     */
    public function impersonateTenant(int $tenantId): void
    {
        $this->impersonationUrl = null;
        $this->impersonationError = null;

        try {
            $tenant = TenantMaster::with('rdsInstance')->findOrFail($tenantId);
            $admin = auth()->user();

            // Check permission
            if (!$admin->hasRaiOpsPermission('tenant.impersonate')) {
                $this->impersonationError = 'You do not have permission to impersonate tenants.';
                return;
            }

            // Check if RDS is healthy
            if (!$tenant->rdsInstance) {
                $this->impersonationError = 'Tenant has no associated RDS instance.';
                return;
            }

            if ($tenant->rdsInstance->health_status === 'down') {
                $this->impersonationError = "Cannot impersonate: RDS '{$tenant->rdsInstance->name}' is currently down.";
                return;
            }

            // Check if impersonation secret is configured
            if (empty(config('raiops.impersonation_secret'))) {
                $this->impersonationError = 'Impersonation is not configured. Set RAIOPS_IMPERSONATION_SECRET in .env';
                return;
            }

            // Generate impersonation URL
            $service = app(ImpersonationTokenService::class);
            $result = $service->launchImpersonation($admin, $tenant);

            $this->impersonationUrl = $result['url'];

            // Dispatch browser event to open in new tab
            $this->dispatch('open-impersonation', url: $result['url']);

            session()->flash('success', "Launching RAI for '{$tenant->name}'...");

        } catch (\Exception $e) {
            $this->impersonationError = 'Failed to launch impersonation: ' . $e->getMessage();
            \Log::error('Impersonation failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if current user can impersonate
     */
    public function getCanImpersonateProperty(): bool
    {
        return auth()->user()?->hasRaiOpsPermission('tenant.impersonate') ?? false;
    }

    /**
     * Open create tenant modal
     */
    public function openCreateModal(): void
    {
        $this->resetCreateForm();
        $this->showCreateModal = true;
    }

    /**
     * Close create tenant modal
     */
    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetCreateForm();
    }

    /**
     * Reset create tenant form
     */
    protected function resetCreateForm(): void
    {
        $this->createRdsInstanceId = null;
        $this->createName = '';
        $this->createContactName = '';
        $this->createContactEmail = '';
        $this->createPassword = '';
        $this->createPasswordConfirmation = '';
        $this->createStatus = 'trial';
        $this->createTrialEndsAt = now()->addDays(30)->format('Y-m-d');
        $this->createPlanName = 'starter';
        $this->createLocationCount = 1;
        $this->createLocationName = '';
        $this->createLocationAlias = null;
        $this->createLocationAddress = null;
        $this->createLocationCity = null;
        $this->createLocationState = null;
        $this->createLocationCountry = 'US';
        $this->createLocationToastLocation = null;
        $this->createError = null;
    }

    /**
     * Create a new tenant
     */
    public function createTenant(): void
    {
        // Validate
        $this->validate([
            'createRdsInstanceId' => 'required|exists:rds_instances,id',
            'createName' => 'required|string|max:255',
            'createContactName' => 'required|string|max:255',
            'createContactEmail' => 'required|email|max:255',
            'createPassword' => 'required|string|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[^a-zA-Z0-9]/',
            'createPasswordConfirmation' => 'required|same:createPassword',
            'createStatus' => 'required|in:active,trial,suspended,cancelled',
            'createTrialEndsAt' => 'nullable|date|after:today',
            'createPlanName' => 'nullable|string|max:255',
            'createLocationCount' => 'nullable|integer|min:1',
            'createLocationName' => 'required|string|max:255',
            'createLocationAlias' => 'nullable|string|max:255',
            'createLocationAddress' => 'nullable|string|max:255',
            'createLocationCity' => 'nullable|string|max:255',
            'createLocationState' => 'nullable|string|max:255',
            'createLocationCountry' => 'nullable|string|max:255',
            'createLocationToastLocation' => 'nullable|string|max:100',
        ], [
            'createPassword.regex' => 'Password must contain uppercase, lowercase, number, and special character.',
            'createPasswordConfirmation.same' => 'Password confirmation does not match.',
        ]);

        $this->createError = null;

        try {
            $rdsInstance = RdsInstance::findOrFail($this->createRdsInstanceId);

            $service = app(TenantCreationService::class);

            $result = $service->createTenant($rdsInstance, [
                'name' => $this->createName,
                'primary_contact_name' => $this->createContactName,
                'primary_contact_email' => $this->createContactEmail,
                'password' => $this->createPassword,
                'status' => $this->createStatus,
                'trial_ends_at' => $this->createTrialEndsAt ? \Carbon\Carbon::parse($this->createTrialEndsAt) : null,
                'plan_name' => $this->createPlanName,
                'location_count' => $this->createLocationCount,
                'location_name' => $this->createLocationName,
                'location_alias' => $this->createLocationAlias,
                'location_address' => $this->createLocationAddress,
                'location_city' => $this->createLocationCity,
                'location_state' => $this->createLocationState,
                'location_country' => $this->createLocationCountry,
                'location_toast_location' => $this->createLocationToastLocation,
            ]);

            if ($result['success']) {
                AuditLog::log('created', 'TenantMaster', $result['tenant_master_id'], null, [
                    'rds_instance' => $rdsInstance->name,
                    'remote_tenant_id' => $result['remote_tenant_id'],
                    'name' => $this->createName,
                ]);

                session()->flash('success', $result['message']);
                $this->closeCreateModal();
                $this->resetPage(); // Refresh the list
            } else {
                $this->createError = $result['message'];
            }

        } catch (\Exception $e) {
            $this->createError = 'Failed to create tenant: ' . $e->getMessage();
            \Log::error('Tenant creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Open location modal for adding or editing
     */
    public function openLocationModal(?int $locationId = null): void
    {
        $this->locationError = null;
        $this->editingLocationId = $locationId;
        
        if ($locationId) {
            // Edit mode - load location data
            $this->loadLocationForEdit($locationId);
        } else {
            // Add mode - reset form
            $this->resetLocationForm();
        }
        
        $this->showLocationModal = true;
    }

    /**
     * Load location data for editing
     */
    protected function loadLocationForEdit(int $locationId): void
    {
        if (!$this->selectedTenantId) {
            return;
        }

        try {
            $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
            if (!$tenant || !$tenant->rdsInstance) {
                $this->locationError = 'Tenant or RDS instance not found';
                return;
            }

            $service = app(RdsConnectionService::class);
            $location = $service->getLocation($tenant->rdsInstance, $locationId);

            if (!$location) {
                $this->locationError = 'Location not found';
                return;
            }

            // Load location data
            $this->locationName = $location->name ?? '';
            $this->locationAddress = $location->address ?? null;
            $this->locationCity = $location->city ?? null;
            $this->locationState = $location->state ?? null;
            $this->locationZip = $location->zip ?? null;
            $this->locationCountry = $location->country ?? 'US';
            $this->locationTimezone = $location->timezone ?? 'America/New_York';
            $this->locationIsActive = isset($location->is_active) ? (bool)$location->is_active : true;
            $this->locationHasGroupedTips = isset($location->has_grouped_tips) ? (bool)$location->has_grouped_tips : false;

            // Load alias
            $db = $service->query($tenant->rdsInstance);
            $alias = $db->table('location_aliases')
                ->where('location_id', $locationId)
                ->first();
            $this->locationAlias = $alias->name ?? null;

            // Load Toast location map
            $toastProvider = $db->table('providers')
                ->where('classname', 'App\\Classes\\Providers\\ToastProvider')
                ->first();

            if ($toastProvider) {
                $toastMap = $db->table('location_maps')
                    ->where('location_id', $locationId)
                    ->where('provider_id', $toastProvider->id)
                    ->first();
                $this->locationToastLocation = $toastMap->external_id ?? null;
            }

        } catch (\Exception $e) {
            $this->locationError = 'Failed to load location: ' . $e->getMessage();
            \Log::error('Failed to load location for edit', [
                'location_id' => $locationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset location form
     */
    protected function resetLocationForm(): void
    {
        $this->locationName = '';
        $this->locationAlias = null;
        $this->locationAddress = null;
        $this->locationCity = null;
        $this->locationState = null;
        $this->locationZip = null;
        $this->locationCountry = 'US';
        $this->locationTimezone = 'America/New_York';
        $this->locationToastLocation = null;
        $this->locationIsActive = true;
        $this->locationHasGroupedTips = false;
    }

    /**
     * Close location modal
     */
    public function closeLocationModal(): void
    {
        $this->showLocationModal = false;
        $this->editingLocationId = null;
        $this->locationError = null;
        $this->resetLocationForm();
    }

    /**
     * Save location (create or update)
     */
    public function saveLocation(): void
    {
        $this->locationError = null;

        if (!$this->selectedTenantId) {
            $this->locationError = 'No tenant selected';
            return;
        }

        $this->validate([
            'locationName' => 'required|string|max:255',
            'locationAlias' => 'nullable|string|max:255',
            'locationAddress' => 'nullable|string|max:255',
            'locationCity' => 'nullable|string|max:255',
            'locationState' => 'nullable|string|max:255',
            'locationZip' => 'nullable|string|max:20',
            'locationCountry' => 'nullable|string|max:255',
            'locationTimezone' => 'nullable|string|max:100',
            'locationToastLocation' => 'nullable|string|max:100',
        ]);

        try {
            $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
            if (!$tenant || !$tenant->rdsInstance) {
                $this->locationError = 'Tenant or RDS instance not found';
                return;
            }

            $service = app(RdsConnectionService::class);
            $rds = $tenant->rdsInstance;

            $data = [
                'name' => $this->locationName,
                'alias' => $this->locationAlias,
                'address' => $this->locationAddress,
                'city' => $this->locationCity,
                'state' => $this->locationState,
                'zip' => $this->locationZip,
                'country' => $this->locationCountry,
                'timezone' => $this->locationTimezone,
                'toast_location' => $this->locationToastLocation,
                'is_active' => $this->locationIsActive,
                'has_grouped_tips' => $this->locationHasGroupedTips,
            ];

            if ($this->editingLocationId) {
                // Update existing location
                $success = $service->updateLocation($rds, $this->editingLocationId, $data);
                if ($success) {
                    session()->flash('success', 'Location updated successfully.');
                    AuditLog::log('updated', 'Location', $this->editingLocationId, null, [
                        'tenant_id' => $tenant->id,
                        'location_name' => $this->locationName,
                    ]);
                } else {
                    $this->locationError = 'Failed to update location';
                    return;
                }
            } else {
                // Create new location
                $locationId = $service->createLocation($rds, $tenant->remote_tenant_id, $data);
                if ($locationId) {
                    session()->flash('success', 'Location created successfully.');
                    AuditLog::log('created', 'Location', $locationId, null, [
                        'tenant_id' => $tenant->id,
                        'location_name' => $this->locationName,
                    ]);
                } else {
                    $this->locationError = 'Failed to create location';
                    return;
                }
            }

            // Refresh live data and update cached location count
            $this->loadLiveData();

            $this->closeLocationModal();

        } catch (\Exception $e) {
            $this->locationError = 'Failed to save location: ' . $e->getMessage();
            \Log::error('Location save failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Delete a location
     */
    public function deleteLocation(int $locationId): void
    {
        if (!$this->selectedTenantId) {
            session()->flash('error', 'No tenant selected');
            return;
        }

        try {
            $tenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
            if (!$tenant || !$tenant->rdsInstance) {
                session()->flash('error', 'Tenant or RDS instance not found');
                return;
            }

            $service = app(RdsConnectionService::class);
            $success = $service->deleteLocation($tenant->rdsInstance, $locationId);

            if ($success) {
                session()->flash('success', 'Location deleted successfully.');
                AuditLog::log('deleted', 'Location', $locationId, null, [
                    'tenant_id' => $tenant->id,
                ]);
                
                // Refresh live data
                $this->loadLiveData();
            } else {
                session()->flash('error', 'Failed to delete location');
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete location: ' . $e->getMessage());
            \Log::error('Location delete failed', [
                'location_id' => $locationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        $query = TenantMaster::with('rdsInstance')
            ->search($this->search)
            ->when($this->statusFilter !== 'all', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->rdsFilter !== 'all', function ($q) {
                $q->where('rds_instance_id', $this->rdsFilter);
            })
            ->orderBy('name');

        $tenants = $query->paginate($this->perPage);

        // Get selected tenant if viewing details
        $selectedTenant = null;
        if ($this->selectedTenantId) {
            $selectedTenant = TenantMaster::with('rdsInstance')->find($this->selectedTenantId);
        }

        return view('livewire.admin.tenant-multi-rds', [
            'tenants' => $tenants,
            'selectedTenant' => $selectedTenant,
            'rdsOptions' => $this->rdsOptions,
            'rdsInstances' => RdsInstance::active()->orderBy('name')->get(),
        ])->layout('layouts.rai');
    }
}

