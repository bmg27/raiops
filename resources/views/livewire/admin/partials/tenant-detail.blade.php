{{-- Tenant Detail View - Live RDS Data --}}

{{-- COMPACT HEADER --}}
<div class="d-flex justify-content-between align-items-start mb-2">
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="backToList">
            <i class="bi bi-arrow-left"></i>
        </button>
        <h5 class="mb-0">{{ $tenant->name }}</h5>
        @if($tenant->rdsInstance)
            <span class="badge {{ $tenant->rdsInstance->is_master ? 'bg-warning text-dark' : 'bg-info' }}">
                <i class="bi bi-database me-1"></i>{{ $tenant->rdsInstance->name }}
            </span>
        @endif
        <span class="badge {{ $tenant->getStatusBadgeClass() }}">{{ $tenant->getDisplayStatus() }}</span>
    </div>
    <div class="d-flex gap-2">
        @if($this->canImpersonate && $tenant->rdsInstance)
            <button type="button" class="btn btn-success btn-sm" wire:click="impersonateTenant({{ $tenant->id }})" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="impersonateTenant"><i class="bi bi-box-arrow-up-right me-1"></i> RAI</span>
                <span wire:loading wire:target="impersonateTenant"><span class="spinner-border spinner-border-sm"></span></span>
            </button>
        @endif
        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="refreshLiveData" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="refreshLiveData"><i class="bi bi-arrow-clockwise"></i></span>
            <span wire:loading wire:target="refreshLiveData"><span class="spinner-border spinner-border-sm"></span></span>
        </button>
    </div>
</div>

{{-- SESSION MESSAGES --}}
<livewire:admin.flash-message />

{{-- IMPERSONATION ERROR --}}
@if($impersonationError ?? false)
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-2" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ $impersonationError }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- TABS NAVIGATION --}}
<div class="nav nav-tabs mb-3 flex-nowrap overflow-x-auto" role="tablist">
    <button class="nav-link {{ $activeSection === 'overview' ? 'active' : '' }} flex-shrink-0"
            wire:click="setActiveSection('overview')"
            type="button">
        <i class="bi bi-info-circle me-1"></i> Overview
    </button>
    <button class="nav-link {{ $activeSection === 'users' ? 'active' : '' }} flex-shrink-0"
            wire:click="setActiveSection('users')"
            type="button">
        <i class="bi bi-people me-1"></i> Users
    </button>
    <button class="nav-link {{ $activeSection === 'locations' ? 'active' : '' }} flex-shrink-0"
            wire:click="setActiveSection('locations')"
            type="button">
        <i class="bi bi-building me-1"></i> Locations
    </button>
    <button class="nav-link {{ $activeSection === 'integrations' ? 'active' : '' }} flex-shrink-0"
            wire:click="setActiveSection('integrations')"
            type="button">
        <i class="bi bi-plug me-1"></i> Integrations
    </button>
</div>

{{-- COMPACT SUMMARY ROW --}}
<div class="card mb-2">
    <div class="card-body py-1 px-2">
        <div class="d-flex flex-wrap align-items-center gap-3 small">
            <span><span class="text-muted">Contact:</span> {{ $tenant->primary_contact_name ?? 'N/A' }} <span class="text-muted">({{ $tenant->primary_contact_email ?? 'no email' }})</span></span>
            @if($tenant->rdsInstance)
                <span><span class="text-muted">RDS:</span> <strong>{{ $tenant->rdsInstance->name }}</strong> @if($tenant->rdsInstance->is_master)<span class="badge bg-warning text-dark ms-1">Master</span>@endif</span>
                <span><span class="text-muted">Host:</span> <code>{{ $tenant->rdsInstance->host }}:{{ $tenant->rdsInstance->port }}</code></span>
                <span><span class="text-muted">Database:</span> <code>{{ $tenant->rdsInstance->rai_database }}</code></span>
                <span><span class="text-muted">Health:</span> 
                    <span class="badge {{ $tenant->rdsInstance->getHealthBadgeClass() }} small">
                        {{ ucfirst($tenant->rdsInstance->health_status) }}
                    </span>
                </span>
                <span><span class="text-muted">App URL:</span> 
                    <a href="{{ $tenant->rdsInstance->app_url }}" target="_blank" class="text-decoration-none">
                        {{ $tenant->rdsInstance->app_url }} <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </span>
            @endif
        </div>
    </div>
</div>

{{-- OVERVIEW SECTION --}}
@if($activeSection === 'overview')
@if($loadingLiveData)
    <div class="card mb-2">
        <div class="card-body text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <small class="d-block mt-2 text-muted">Loading tenant data...</small>
        </div>
    </div>
@elseif($liveData && isset($liveData['error']))
    <div class="alert alert-danger mb-2">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ $liveData['error'] }}
        <button type="button" class="btn btn-primary btn-sm mt-2" wire:click="refreshLiveData">
            <i class="bi bi-arrow-clockwise me-1"></i> Retry
        </button>
    </div>
@elseif($liveData && isset($liveData['success']))
    {{-- Tenant Details and RDS Connection Cards in Row --}}
    <div class="row g-2 mb-2">
        {{-- Tenant Details Card --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong class="small"><i class="bi bi-info-circle me-1"></i>Tenant Information</strong>
                </div>
                <div class="card-body py-2">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="small fw-bold text-muted mb-2">Contact Information</h6>
                            <div class="small">
                                <div class="mb-1"><strong>Name:</strong> {{ $tenant->name }}</div>
                                <div class="mb-1"><strong>Contact:</strong> {{ $tenant->primary_contact_name ?? 'N/A' }}</div>
                                <div class="mb-1"><strong>Email:</strong> {{ $tenant->primary_contact_email ?? 'N/A' }}</div>
                                <div class="mb-1"><strong>Status:</strong> 
                                    <span class="badge {{ $tenant->getStatusBadgeClass() }}">{{ $tenant->getDisplayStatus() }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="small fw-bold text-muted mb-2">Subscription</h6>
                            <div class="small">
                                @if($liveData['tenant']->trial_ends_at ?? null)
                                    <div class="mb-1"><strong>Trial Ends:</strong> {{ \Carbon\Carbon::parse($liveData['tenant']->trial_ends_at)->format('M d, Y') }}</div>
                                @endif
                                @if($liveData['tenant']->subscription_started_at ?? null)
                                    <div class="mb-1"><strong>Subscription Started:</strong> {{ \Carbon\Carbon::parse($liveData['tenant']->subscription_started_at)->format('M d, Y') }}</div>
                                @endif
                                <div class="mb-1"><strong>Total Users:</strong> {{ $liveData['total_users'] ?? 0 }}</div>
                                <div class="mb-1"><strong>Active Users:</strong> {{ $liveData['user_count'] ?? 0 }}</div>
                                <div class="mb-1"><strong>Locations:</strong> {{ $liveData['location_count'] ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- RDS Connection Details Card --}}
        @if($tenant->rdsInstance)
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2">
                    <strong class="small"><i class="bi bi-database me-1"></i>RDS Connection</strong>
                </div>
                <div class="card-body py-2">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small">
                                <div class="mb-1"><strong>RDS Instance:</strong> {{ $tenant->rdsInstance->name }}
                                    @if($tenant->rdsInstance->is_master)
                                        <span class="badge bg-warning text-dark ms-1">Master</span>
                                    @endif
                                </div>
                                <div class="mb-1"><strong>Host:</strong> <code>{{ $tenant->rdsInstance->host }}:{{ $tenant->rdsInstance->port }}</code></div>
                                <div class="mb-1"><strong>Database:</strong> <code>{{ $tenant->rdsInstance->rai_database }}</code></div>
                                @if($tenant->rdsInstance->providers_database)
                                    <div class="mb-1"><strong>Providers DB:</strong> <code>{{ $tenant->rdsInstance->providers_database }}</code></div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="small">
                                <div class="mb-1"><strong>Health Status:</strong> 
                                    <span class="badge {{ $tenant->rdsInstance->getHealthBadgeClass() }}">
                                        {{ ucfirst($tenant->rdsInstance->health_status) }}
                                    </span>
                                </div>
                                <div class="mb-1"><strong>App URL:</strong> 
                                    <a href="{{ $tenant->rdsInstance->app_url }}" target="_blank" class="text-decoration-none">
                                        {{ $tenant->rdsInstance->app_url }} <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </div>
                                @if($liveData['fetched_at'] ?? null)
                                    <div class="mb-1"><strong>Last Synced:</strong> {{ $liveData['fetched_at'] }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
@else
    <div class="card mb-2">
        <div class="card-body text-center py-4">
            <p class="text-muted mb-3">No tenant data loaded</p>
            <button type="button" class="btn btn-primary btn-sm" wire:click="refreshLiveData">
                <i class="bi bi-arrow-clockwise me-1"></i> Load Live Data
            </button>
        </div>
    </div>
@endif
@endif

{{-- USERS SECTION --}}
@if($activeSection === 'users')
@if($loadingLiveData || !$liveData || !isset($liveData['success']))
    <div class="card mb-2">
        <div class="card-body text-center py-4">
            @if($loadingLiveData)
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <small class="d-block mt-2 text-muted">Loading users...</small>
            @else
                <p class="text-muted mb-3">No user data available</p>
                <button type="button" class="btn btn-primary btn-sm" wire:click="refreshLiveData">
                    <i class="bi bi-arrow-clockwise me-1"></i> Load Live Data
                </button>
            @endif
        </div>
    </div>
@else
    {{-- Search and Filter Row --}}
    <div class="row g-2 align-items-center mb-3">
        <div class="col-sm-6 col-md-4">
            <input type="text" 
                   wire:model.live.debounce.500ms="userSearch" 
                   class="form-control" 
                   placeholder="Search by name or email...">
        </div>
        <div class="col-sm-6 col-md-3">
            <x-per-page />
        </div>
    </div>

    {{-- Users Table --}}
    <div class="card mb-2">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-nowrap table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th class="sticky-col">Name</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->users as $user)
                            @php
                                $userStatus = $user->status ?? 'Unknown';
                                $statusClass = match($userStatus) {
                                    'Active' => 'bg-success',
                                    'Pending' => 'bg-warning text-dark',
                                    'Disabled', 'Archived' => 'bg-secondary',
                                    default => 'bg-light text-dark border',
                                };
                            @endphp
                            <tr>
                                <td>{{ $user->id ?? '—' }}</td>
                                <td class="sticky-col">{{ $user->name ?? '—' }}</td>
                                <td>{{ $user->email ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $statusClass }}">{{ $userStatus }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No users found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    {{-- Pagination --}}
    {{ $this->users->links() }}
@endif
@endif

{{-- LOCATIONS SECTION --}}
@if($activeSection === 'locations')
@if($loadingLiveData || !$liveData || !isset($liveData['success']))
    <div class="card mb-2">
        <div class="card-body text-center py-4">
            @if($loadingLiveData)
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <small class="d-block mt-2 text-muted">Loading locations...</small>
            @else
                <p class="text-muted mb-3">No location data available</p>
                <button type="button" class="btn btn-primary btn-sm" wire:click="refreshLiveData">
                    <i class="bi bi-arrow-clockwise me-1"></i> Load Live Data
                </button>
            @endif
        </div>
    </div>
@else
    {{-- Search and Filter Row --}}
    <div class="row g-2 align-items-center mb-3">
        <div class="col-sm-6 col-md-4">
            <input type="text" 
                   wire:model.live.debounce.500ms="locationSearch" 
                   class="form-control" 
                   placeholder="Search by name, city, or state...">
        </div>
        <div class="col-sm-6 col-md-3">
            <x-per-page />
        </div>
        <div class="col-sm-6 col-md-5 text-end">
            <button type="button" class="btn btn-primary btn-sm" wire:click="openLocationModal">
                <i class="bi bi-plus-lg me-1"></i> Add Location
            </button>
        </div>
    </div>

    {{-- Locations Table --}}
    <div class="card mb-2">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-nowrap table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th class="sticky-col">Name</th>
                            <th class="d-none d-md-table-cell">City</th>
                            <th class="d-none d-md-table-cell">State</th>
                            <th>Status</th>
                            <th class="text-center">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->locations as $location)
                            @php
                                $isActive = isset($location->is_active) ? (bool)$location->is_active : true;
                                $statusClass = $isActive ? 'bg-success' : 'bg-secondary';
                            @endphp
                            <tr>
                                <td>{{ $location->id ?? '—' }}</td>
                                <td class="sticky-col"><strong>{{ $location->name ?? '—' }}</strong></td>
                                <td class="d-none d-md-table-cell">{{ $location->city ?? '—' }}</td>
                                <td class="d-none d-md-table-cell">{{ $location->state ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $statusClass }}">{{ $isActive ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="text-center position-static">
                                    <div class="text-end">
                                        <div class="dropdown position-static">
                                            <button class="btn btn-sm p-0 bg-transparent border-0 text-secondary"
                                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end position-absolute">
                                                <li>
                                                    <a wire:click="openLocationModal({{ $location->id }})" 
                                                       class="dropdown-item" href="#">Edit</a>
                                                </li>
                                                <li>
                                                    <a wire:click="deleteLocation({{ $location->id }})" 
                                                       wire:confirm="Delete this location?" 
                                                       class="dropdown-item text-danger" href="#">Delete</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No locations found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    {{-- Pagination --}}
    {{ $this->locations->links() }}
@endif
@endif

{{-- INTEGRATIONS SECTION --}}
@if($activeSection === 'integrations')
<div class="mb-3">
    <button type="button" class="btn btn-sm btn-primary" wire:click="openProviderSettingsModal()">
        <i class="bi bi-plus-circle me-1"></i>Configure Integration
    </button>
</div>

@php
    $integrations = $this->getIntegrations();
    $tenantIntegrations = collect($integrations)->where('is_location_level', false);
    $locationIntegrations = collect($integrations)->where('is_location_level', true);
@endphp

@if($tenantIntegrations->isEmpty() && $locationIntegrations->isEmpty())
    <div class="card mb-2">
        <div class="card-body text-center py-4">
            <i class="bi bi-inbox fs-4 d-block mb-2 text-muted"></i>
            <small class="text-muted">No integrations configured</small>
        </div>
    </div>
@else
    <div class="row g-3">
        {{-- Tenant-Level Integrations Card --}}
        <div class="col-12 {{ $locationIntegrations->isNotEmpty() ? 'col-lg-6' : '' }}">
            <div class="card mb-0 h-100">
                <div class="card-header py-2">
                    <strong class="small"><i class="bi bi-plugin me-1"></i>Tenant-Level Integrations</strong>
                </div>
                <div class="card-body py-2">
                    @if($tenantIntegrations->isEmpty())
                        <div class="text-center text-muted py-3">
                            <small>No tenant-level integrations</small>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="small">Provider</th>
                                        <th class="small">Status</th>
                                        <th class="small d-none d-md-table-cell">Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tenantIntegrations as $integration)
                                        <tr>
                                            <td class="small">
                                                <i class="bi bi-plugin me-1"></i>
                                                <a href="#" 
                                                   wire:click.prevent="openProviderSettingsModal({{ $integration['provider_id'] }})"
                                                   class="text-decoration-none fw-bold text-primary"
                                                   title="Click to edit">
                                                    {{ $integration['provider_name'] }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge {{ $integration['is_active'] ? 'bg-success' : 'bg-secondary' }} small">
                                                    {{ $integration['is_active'] ? 'Active' : 'Disabled' }}
                                                </span>
                                            </td>
                                            <td class="small text-muted d-none d-md-table-cell">
                                                {{ $integration['updated_at'] ? \Carbon\Carbon::parse($integration['updated_at'])->diffForHumans() : 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Location-Level Integrations Card --}}
        <div class="col-12 {{ $tenantIntegrations->isNotEmpty() ? 'col-lg-6' : '' }}">
            <div class="card mb-0 h-100">
                <div class="card-header py-2">
                    <strong class="small"><i class="bi bi-plugin me-1"></i>Location-Level Integrations</strong>
                </div>
                <div class="card-body py-2">
                    @if($locationIntegrations->isEmpty())
                        <div class="text-center text-muted py-3">
                            <small>No location-level integrations</small>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="small">Provider</th>
                                        <th class="small">Status</th>
                                        <th class="small d-none d-md-table-cell">Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($locationIntegrations as $integration)
                                        <tr>
                                            <td class="small">
                                                <i class="bi bi-plugin me-1"></i>
                                                <a href="#" 
                                                   wire:click.prevent="openProviderSettingsModal({{ $integration['provider_id'] }}, {{ $integration['location_id'] }})"
                                                   class="text-decoration-none fw-bold text-primary"
                                                   title="Click to edit">
                                                    {{ $integration['provider_name'] }}
                                                </a>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-geo-alt me-1"></i>Location: {{ $integration['location_name'] }}
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge {{ $integration['is_active'] ? 'bg-success' : 'bg-secondary' }} small">
                                                    {{ $integration['is_active'] ? 'Active' : 'Disabled' }}
                                                </span>
                                            </td>
                                            <td class="small text-muted d-none d-md-table-cell">
                                                {{ $integration['updated_at'] ? \Carbon\Carbon::parse($integration['updated_at'])->diffForHumans() : 'N/A' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

{{-- PROVIDER SETTINGS MODAL --}}
@if($showProviderSettingsModal && $selectedTenantId)
    @php
        $tenant = \App\Models\TenantMaster::find($selectedTenantId);
    @endphp
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; right: 0; bottom: 0; overflow-y: auto; z-index: 9999;">
        <div class="modal-dialog modal-lg" style="z-index: 10000;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        @if($selectedProviderLabel)
                            Configure {{ $selectedProviderLabel }} - {{ $tenant->name }}
                        @else
                            Configure API Integration - {{ $tenant->name }}
                        @endif
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeProviderSettingsModal"></button>
                </div>
                <div class="modal-body">
                    <!-- Provider Selection -->
                    @error('selectedProviderId')
                    <div class="text-danger">{{ $message }}</div>
                    @enderror
                    <div class="mb-3">
                        <label class="form-label">Provider <span class="text-danger">*</span></label>
                        <select class="form-select" wire:model.live="selectedProviderId" @if($selectedProviderId) disabled @endif>
                            <option value="">-- Select Provider --</option>
                            @foreach($availableProviders as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @if($selectedProviderId)
                            <small class="text-muted">Provider cannot be changed after creation. Delete and recreate to change.</small>
                        @endif
                    </div>

                    @if($selectedProviderId)
                        @php
                            $isLocationLevel = $selectedProviderHasLocation;
                            $fields = $selectedProviderFieldSchema;
                            $providerKey = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::snake($selectedProviderLabel ?? ''));
                        @endphp

                        @if($isLocationLevel)
                            <!-- Location Selection for Location-Level Integrations -->
                            <div class="mb-3">
                                <label class="form-label">
                                    Location <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('selectedLocationId') is-invalid @enderror"
                                        wire:model.live="selectedLocationId"
                                        @if($isEditingIntegration && $selectedLocationId) disabled @endif>
                                    <option value="">-- Select Location --</option>
                                    @foreach($tenantLocations as $locId => $locName)
                                        <option value="{{ $locId }}">{{ $locName }}</option>
                                    @endforeach
                                </select>
                                @error('selectedLocationId')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror
                                @if($isEditingIntegration && $selectedLocationId)
                                    <small class="text-muted">Location cannot be changed after creation. Delete and recreate to change location.</small>
                                @endif
                                @if(empty($tenantLocations))
                                    <small class="text-warning">No locations found for this tenant. Please add locations first.</small>
                                @endif
                            </div>
                        @endif

                        @if(!empty($fields) && is_array($fields))
                            <!-- Dynamic Fields based on Schema -->
                            @foreach($fields as $fieldDef)
                                @php
                                    $key = $fieldDef['key'] ?? null;
                                    $label = $fieldDef['label'] ?? $key;
                                    $type = $fieldDef['type'] ?? 'text';
                                    $placeholder = $fieldDef['placeholder'] ?? '';
                                    $help = $fieldDef['help'] ?? '';
                                @endphp
                                @if($key)
                                    <div class="mb-3">
                                        <label class="form-label">
                                            {{ $label }}
                                            @if(($fieldDef['required'] ?? true))
                                                <span class="text-danger">*</span>
                                            @endif
                                        </label>
                                            @if($type === 'textarea' || ($fieldDef['multiline'] ?? false))
                                                <textarea
                                                    class="form-control @error('integrationFields.' . $key) is-invalid @enderror"
                                                    wire:model="integrationFields.{{ $key }}"
                                                    rows="{{ $fieldDef['rows'] ?? 3 }}"
                                                    placeholder="{{ $placeholder }}"
                                                ></textarea>
                                            @elseif($type === 'password')
                                                <input
                                                    type="password"
                                                    class="form-control @error('integrationFields.' . $key) is-invalid @enderror"
                                                    wire:model="integrationFields.{{ $key }}"
                                                    placeholder="{{ $placeholder }}"
                                                    autocomplete="new-password"
                                                />
                                            @else
                                                <input
                                                    type="{{ $type }}"
                                                    class="form-control @error('integrationFields.' . $key) is-invalid @enderror"
                                                    wire:model="integrationFields.{{ $key }}"
                                                    placeholder="{{ $placeholder }}"
                                                />
                                            @endif
                                            @error('integrationFields.' . $key)
                                            <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                            @if($help)
                                                <small class="text-muted">{{ $help }}</small>
                                            @endif
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <!-- Fallback to JSON if no schema defined -->
                            @error('providerSettingsText')
                            <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="mb-3">
                                <label class="form-label">Settings (JSON) <span class="text-danger">*</span></label>
                                <textarea
                                    class="form-control font-monospace"
                                    wire:model="providerSettingsText"
                                    rows="12"
                                    placeholder='{
  "TOAST_BASE_URL": "https://api.toasttab.com",
  "TOAST_CLIENT_ID": "your-client-id",
  "TOAST_CLIENT_SECRET": "your-client-secret"
}'></textarea>
                                <small class="text-muted">
                                    Enter provider settings as JSON. Example keys:
                                    @if($providerKey === 'TOAST_API')
                                        TOAST_BASE_URL, TOAST_CLIENT_ID, TOAST_CLIENT_SECRET
                                    @elseif($providerKey === 'SEVEN_SHIFTS_API')
                                        SEVENSHIFTS_API_BASE_URL, SEVENSHIFTS_API_TOKEN, SEVENSHIFTS_COMPANY_ID, SEVENSHIFTS_PUNCHES_DAYS_BACK
                                    @elseif($providerKey === 'TOAST_SFTP')
                                        SFTP_HOST, SFTP_PORT, SFTP_USERNAME, SFTP_PRIVATE_KEY, SFTP_PASSPHRASE
                                    @elseif($providerKey === 'OPENAPI')
                                        OPENAI_API_KEY
                                    @endif
                                </small>
                            </div>
                        @endif

                        <!-- Active Status -->
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" wire:model="providerActive" id="providerActive">
                                <label class="form-check-label" for="providerActive">
                                    Active (use these settings for this tenant)
                                </label>
                            </div>
                            <small class="text-muted">Only one active setting per provider per tenant is allowed.</small>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeProviderSettingsModal">Cancel</button>
                    @if($selectedProviderId)
                        <button type="button" class="btn btn-primary" wire:click="saveProviderSettings">
                            <i class="bi bi-check-lg me-1"></i> Save Provider Settings
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
@endif
