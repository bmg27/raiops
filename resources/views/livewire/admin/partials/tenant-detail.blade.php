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


