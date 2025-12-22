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

{{-- LIVE DATA FROM RDS --}}
<div class="card mb-2">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <strong class="small"><i class="bi bi-lightning-charge me-1"></i>Live Data</strong>
        @if($liveData && isset($liveData['fetched_at']))
            <small class="text-muted">{{ $liveData['fetched_at'] }}</small>
        @endif
    </div>
    <div class="card-body py-2">
        @if($loadingLiveData)
            <div class="text-center py-2">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <small class="d-block mt-1 text-muted">Loading...</small>
            </div>
        @elseif($liveData && isset($liveData['error']))
            <div class="alert alert-danger py-2 mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ $liveData['error'] }}
            </div>
        @elseif($liveData && isset($liveData['success']))
            {{-- Compact Live Counts --}}
            <div class="d-flex flex-wrap align-items-center gap-3 mb-2 small">
                <span><span class="text-muted">Active:</span> <strong>{{ $liveData['user_count'] }}</strong></span>
                <span><span class="text-muted">Total Users:</span> <strong>{{ $liveData['total_users'] }}</strong></span>
                <span><span class="text-muted">Locations:</span> <strong>{{ $liveData['location_count'] }}</strong></span>
            </div>

            {{-- Users and Locations Side by Side --}}
            <div class="row g-3">
                {{-- Users Table Card --}}
                <div class="col-12 col-lg-6">
                    <div class="card h-100">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <small class="fw-bold"><i class="bi bi-people me-1"></i>Users ({{ $liveData['users']->count() }} of {{ $liveData['total_users'] }})</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="small">Name</th>
                                            <th class="small">Email</th>
                                            <th class="small">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($liveData['users'] as $user)
                                            <tr>
                                                <td class="small">{{ $user->name ?? '—' }}</td>
                                                <td class="small">{{ $user->email ?? '—' }}</td>
                                                <td>
                                                    @php
                                                        $userStatus = $user->status ?? 'Unknown';
                                                        $statusClass = match($userStatus) {
                                                            'Active' => 'bg-success',
                                                            'Pending' => 'bg-warning text-dark',
                                                            'Disabled', 'Archived' => 'bg-secondary',
                                                            default => 'bg-light text-dark border',
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $statusClass }} small">{{ $userStatus }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-center text-muted small">No users</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Locations Table Card --}}
                <div class="col-12 col-lg-6">
                    <div class="card h-100">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <small class="fw-bold"><i class="bi bi-building me-1"></i>Locations ({{ $liveData['location_count'] }})</small>
                            <button type="button" class="btn btn-primary btn-sm" wire:click="openLocationModal">
                                <i class="bi bi-plus-lg"></i> Add
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="small">ID</th>
                                            <th class="small">Name</th>
                                            <th class="small d-none d-md-table-cell">City</th>
                                            <th class="small">Status</th>
                                            <th class="small text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($liveData['locations'] ?? [] as $location)
                                            <tr>
                                                <td class="small">{{ $location->id ?? '—' }}</td>
                                                <td class="small"><strong>{{ $location->name ?? '—' }}</strong></td>
                                                <td class="small d-none d-md-table-cell">{{ $location->city ?? '—' }}</td>
                                                <td>
                                                    @php
                                                        $isActive = isset($location->is_active) ? (bool)$location->is_active : true;
                                                        $statusClass = $isActive ? 'bg-success' : 'bg-secondary';
                                                    @endphp
                                                    <span class="badge {{ $statusClass }} small">{{ $isActive ? 'Active' : 'Inactive' }}</span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="openLocationModal({{ $location->id }})" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger btn-sm" wire:click="deleteLocation({{ $location->id }})" wire:confirm="Delete this location?" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center text-muted small">No locations</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-2">
                <button type="button" class="btn btn-primary btn-sm" wire:click="refreshLiveData">
                    <i class="bi bi-arrow-clockwise me-1"></i> Load Live Data
                </button>
            </div>
        @endif
    </div>
</div>

{{-- INTEGRATIONS SECTION --}}
@if($liveData && isset($liveData['success']))
<div class="card mb-2">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <strong class="small"><i class="bi bi-plug me-1"></i>Integrations</strong>
        <button type="button" class="btn btn-primary btn-sm" wire:click="openProviderSettingsModal">
            <i class="bi bi-plus-lg me-1"></i> Add Integration
        </button>
    </div>
    <div class="card-body py-2">
        @php
            $integrations = $this->getProviderSettings();
            $tenantIntegrations = collect($integrations)->where('is_location_level', false);
            $locationIntegrations = collect($integrations)->where('is_location_level', true)->groupBy('location_id');
        @endphp

        @if($tenantIntegrations->isEmpty() && $locationIntegrations->isEmpty())
            <div class="text-center text-muted py-3">
                <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                <small>No integrations configured</small>
            </div>
        @else
            {{-- Tenant-level integrations --}}
            @if($tenantIntegrations->isNotEmpty())
                <div class="mb-3">
                    <h6 class="small fw-bold mb-2">Tenant-Level Integrations</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="small">Provider</th>
                                    <th class="small">Status</th>
                                    <th class="small">Last Updated</th>
                                    <th class="small text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tenantIntegrations as $integration)
                                    <tr>
                                        <td class="small">{{ $integration['provider_name'] }}</td>
                                        <td>
                                            <span class="badge {{ $integration['is_active'] ? 'bg-success' : 'bg-secondary' }} small">
                                                {{ $integration['is_active'] ? 'Active' : 'Disabled' }}
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            {{ $integration['updated_at'] ? \Carbon\Carbon::parse($integration['updated_at'])->diffForHumans() : 'N/A' }}
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                    wire:click="openProviderSettingsModal({{ $integration['provider_id'] }})" 
                                                    title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                    wire:click="deleteProviderSettings({{ $integration['provider_id'] }})" 
                                                    wire:confirm="Delete this integration?"
                                                    title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Location-level integrations --}}
            @if($locationIntegrations->isNotEmpty())
                <div>
                    <h6 class="small fw-bold mb-2">Location-Level Integrations</h6>
                    @foreach($locationIntegrations as $locationId => $integrations)
                        <div class="mb-3">
                            <strong class="small text-muted">{{ $integrations->first()['location_name'] ?? "Location {$locationId}" }}</strong>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="small">Provider</th>
                                            <th class="small">Status</th>
                                            <th class="small">Last Updated</th>
                                            <th class="small text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($integrations as $integration)
                                            <tr>
                                                <td class="small">{{ $integration['provider_name'] }}</td>
                                                <td>
                                                    <span class="badge {{ $integration['is_active'] ? 'bg-success' : 'bg-secondary' }} small">
                                                        {{ $integration['is_active'] ? 'Active' : 'Disabled' }}
                                                    </span>
                                                </td>
                                                <td class="small text-muted">
                                                    {{ $integration['updated_at'] ? \Carbon\Carbon::parse($integration['updated_at'])->diffForHumans() : 'N/A' }}
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                            wire:click="openProviderSettingsModal({{ $integration['provider_id'] }}, {{ $integration['location_id'] }})" 
                                                            title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                            wire:click="deleteProviderSettings({{ $integration['provider_id'] }}, {{ $integration['location_id'] }})" 
                                                            wire:confirm="Delete this integration?"
                                                            title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
@endif

{{-- PROVIDER SETTINGS MODAL --}}
@if($showProviderSettingsModal)
<div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);" wire:click.self="closeProviderSettingsModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plug me-2"></i>
                    {{ $isEditingIntegration ? 'Edit' : 'Add' }} Integration
                </h5>
                <button type="button" class="btn-close" wire:click="closeProviderSettingsModal"></button>
            </div>
            <div class="modal-body">
                <form wire:submit.prevent="saveProviderSettings">
                    {{-- Provider Selection --}}
                    <div class="mb-3">
                        <label class="form-label">Provider <span class="text-danger">*</span></label>
                        <select class="form-select @error('selectedProviderId') is-invalid @enderror" 
                            wire:model.live="selectedProviderId"
                            @if($isEditingIntegration) disabled @endif>
                            <option value="">Select a provider...</option>
                            @foreach($availableProviders as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('selectedProviderId')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if($isEditingIntegration)
                            <small class="form-text text-muted">Provider cannot be changed after creation. Delete and recreate to change.</small>
                        @endif
                    </div>

                    {{-- Location Selection (for location-level integrations) --}}
                    @if($selectedProviderHasLocation)
                        <div class="mb-3">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <select class="form-select @error('selectedLocationId') is-invalid @enderror" 
                                wire:model="selectedLocationId"
                                @if($isEditingIntegration && $selectedLocationId) disabled @endif>
                                <option value="">Select a location...</option>
                                @foreach($tenantLocations as $locationId => $locationName)
                                    <option value="{{ $locationId }}">{{ $locationName }}</option>
                                @endforeach
                            </select>
                            @error('selectedLocationId')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if($isEditingIntegration && $selectedLocationId)
                                <small class="form-text text-muted">Location cannot be changed after creation.</small>
                            @endif
                        </div>
                    @endif

                    {{-- Dynamic Fields from Field Schema --}}
                    @if(!empty($selectedProviderFieldSchema))
                        @foreach($selectedProviderFieldSchema as $fieldDef)
                            @php
                                $key = $fieldDef['key'] ?? null;
                                $label = $fieldDef['label'] ?? $key;
                                $type = $fieldDef['type'] ?? 'text';
                                $required = $fieldDef['required'] ?? false;
                            @endphp
                            @if($key)
                                <div class="mb-3">
                                    <label class="form-label">
                                        {{ $label }}
                                        @if($required) <span class="text-danger">*</span> @endif
                                    </label>
                                    @if($type === 'textarea')
                                        <textarea class="form-control @error('integrationFields.' . $key) is-invalid @enderror"
                                            wire:model="integrationFields.{{ $key }}"
                                            rows="3"></textarea>
                                    @else
                                        <input type="{{ $type }}" 
                                            class="form-control @error('integrationFields.' . $key) is-invalid @enderror"
                                            wire:model="integrationFields.{{ $key }}"
                                            @if($type === 'password') autocomplete="new-password" @endif>
                                    @endif
                                    @error('integrationFields.' . $key)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif
                        @endforeach
                    @else
                        {{-- Fallback JSON editor if no field schema --}}
                        <div class="mb-3">
                            <label class="form-label">Settings (JSON) <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('providerSettingsText') is-invalid @enderror" 
                                wire:model="providerSettingsText"
                                rows="8"
                                placeholder='{"key": "value"}'></textarea>
                            @error('providerSettingsText')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Enter settings as JSON</small>
                        </div>
                    @endif

                    {{-- Active Status --}}
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                wire:model="providerActive" 
                                id="providerActive">
                            <label class="form-check-label" for="providerActive">
                                Active
                            </label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" wire:click="closeProviderSettingsModal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <span wire:loading.remove wire:target="saveProviderSettings">Save</span>
                            <span wire:loading wire:target="saveProviderSettings">
                                <span class="spinner-border spinner-border-sm"></span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

