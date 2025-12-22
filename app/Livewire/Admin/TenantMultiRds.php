<?php

namespace App\Livewire\Admin;

use App\Models\RdsInstance;
use App\Models\TenantMaster;
use App\Models\AuditLog;
use App\Services\RdsConnectionService;
use App\Services\ImpersonationTokenService;
use App\Services\TenantCreationService;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * TenantMultiRds Component
 * 
 * RAINBO Command Central's multi-RDS tenant management.
 * Displays tenants from the tenant_master registry with RDS context,
 * and can fetch live data from the correct RDS when drilling down.
 */
class TenantMultiRds extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Search & Filter
    public string $search = '';
    public string $statusFilter = 'all';
    public string $rdsFilter = 'all';
    public int $perPage = 25;

    // Selected tenant for detail view
    public ?int $selectedTenantId = null;
    
    // Live data from RDS (populated when viewing details)
    public ?array $liveData = null;
    public bool $loadingLiveData = false;

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
                'users' => $users->take(20), // Limit to 20 for display
                'total_users' => $users->count(),
                'user_count' => $userCount,
                'locations' => $locations,
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
            if (!$admin->hasRainboPermission('tenant.impersonate')) {
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
            if (empty(config('rainbo.impersonation_secret'))) {
                $this->impersonationError = 'Impersonation is not configured. Set RAINBO_IMPERSONATION_SECRET in .env';
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
        return auth()->user()?->hasRainboPermission('tenant.impersonate') ?? false;
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

