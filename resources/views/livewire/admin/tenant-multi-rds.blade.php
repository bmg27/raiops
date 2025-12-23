<div>
    {{-- Impersonation handler script --}}
    @script
    <script>
        $wire.on('open-impersonation', ({ url }) => {
            // Open in new tab
            window.open(url, '_blank');
        });
    </script>
    @endscript

    @if($selectedTenant)
        {{-- TENANT DETAIL VIEW --}}
        @include('livewire.admin.partials.tenant-detail', ['tenant' => $selectedTenant, 'liveData' => $liveData])
    @else
        {{-- TENANT LIST VIEW --}}
        <x-page-header title="Tenant Management" subtitle="Multi-RDS Command Central" />

        {{-- FILTERS ROW --}}
        <div class="row g-2 align-items-center mb-3">
            <div class="col-sm-4 col-md-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        class="form-control"
                        placeholder="Search tenants..."
                    />
                </div>
            </div>

            <div class="col-sm-4 col-md-2">
                <select wire:model.live="statusFilter" class="form-select">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="trial">Trial</option>
                    <option value="suspended">Suspended</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="col-sm-4 col-md-2">
                <select wire:model.live="rdsFilter" class="form-select">
                    <option value="all">All RDS</option>
                    @foreach($rdsOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-5 text-end">
                @canRaiOps('tenant.edit')
                    <button 
                        type="button" 
                        class="btn btn-primary btn-sm me-2"
                        wire:click="openCreateModal"
                    >
                        <i class="bi bi-plus-circle me-1"></i> Create Tenant
                    </button>
                    <button 
                        type="button" 
                        class="btn btn-outline-primary btn-sm"
                        wire:click="syncAllTenants"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="syncAllTenants">
                            <i class="bi bi-arrow-repeat me-1"></i> Sync All from RDS
                        </span>
                        <span wire:loading wire:target="syncAllTenants">
                            <span class="spinner-border spinner-border-sm me-1"></span> Syncing...
                        </span>
                    </button>
                @endcanRaiOps
            </div>
        </div>

        {{-- SESSION MESSAGES --}}
        <livewire:admin.flash-message />

        {{-- TENANT TABLE --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-hover">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>RDS</th>
                                <th class="text-center">Status</th>
                                <th class="text-center d-none d-md-table-cell">Users</th>
                                <th class="text-center d-none d-md-table-cell">Locations</th>
                                <th class="d-none d-lg-table-cell">Contact</th>
                                <th class="d-none d-xl-table-cell">Last Synced</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tenants as $tenant)
                                <tr wire:key="tenant-{{ $tenant->id }}">
                                    <td>
                                        <a 
                                            href="#" 
                                            wire:click.prevent="viewDetails({{ $tenant->id }})"
                                            class="text-decoration-none fw-semibold"
                                        >
                                            {{ $tenant->name }}
                                        </a>
                                        <br>
                                        <small class="text-muted">ID: {{ $tenant->remote_tenant_id }}</small>
                                    </td>
                                    <td>
                                        @if($tenant->rdsInstance)
                                            <span class="badge {{ $tenant->rdsInstance->is_master ? 'bg-warning text-dark' : 'bg-secondary' }}">
                                                <i class="bi bi-database me-1"></i>
                                                {{ $tenant->rdsInstance->name }}
                                            </span>
                                            @if($tenant->rdsInstance->is_master)
                                                <br><small class="text-muted">Master</small>
                                            @endif
                                        @else
                                            <span class="badge bg-danger">No RDS</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $tenant->getStatusBadgeClass() }}">
                                            {{ $tenant->getDisplayStatus() }}
                                        </span>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-people me-1"></i>
                                            {{ $tenant->cached_user_count ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <a href="#" 
                                           wire:click.prevent="viewDetails({{ $tenant->id }})"
                                           class="badge bg-light text-dark border text-decoration-none cursor-pointer"
                                           title="Click to manage locations">
                                            <i class="bi bi-building me-1"></i>
                                            {{ $tenant->cached_location_count ?? '—' }}
                                        </a>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        @if($tenant->primary_contact_email)
                                            <small>
                                                {{ $tenant->primary_contact_name ?? '' }}<br>
                                                <span class="text-muted">{{ $tenant->primary_contact_email }}</span>
                                            </small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="d-none d-xl-table-cell">
                                        @if($tenant->cache_refreshed_at)
                                            <small class="text-muted" title="{{ $tenant->cache_refreshed_at }}">
                                                {{ $tenant->cache_refreshed_at->diffForHumans() }}
                                            </small>
                                            @if($tenant->isCacheStale())
                                                <br>
                                                <span class="badge bg-warning text-dark">Stale</span>
                                            @endif
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button
                                            wire:click="viewDetails({{ $tenant->id }})"
                                            class="btn btn-sm btn-outline-primary"
                                            title="View Details"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @canRaiOps('tenant.edit')
                                            <button
                                                wire:click="syncTenant({{ $tenant->id }})"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Sync from RDS"
                                                wire:loading.attr="disabled"
                                                wire:target="syncTenant({{ $tenant->id }})"
                                            >
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        @endcanRaiOps
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-building-x fs-1 d-block mb-2"></i>
                                        No tenants found.
                                        @canRaiOps('tenant.edit')
                                            <br>
                                            <button wire:click="syncAllTenants" class="btn btn-primary btn-sm mt-2">
                                                <i class="bi bi-arrow-repeat me-1"></i> Sync Tenants from RDS
                                            </button>
                                        @endcanRaiOps
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- PAGINATION --}}
        {{ $tenants->links() }}

        {{-- SUMMARY STATS --}}
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-muted mb-2">
                            <i class="bi bi-database me-2"></i>RDS Overview
                        </h6>
                        <ul class="list-unstyled mb-0 small">
                            @foreach($rdsOptions as $id => $name)
                                @php
                                    $count = \App\Models\TenantMaster::where('rds_instance_id', $id)->count();
                                @endphp
                                <li class="d-flex justify-content-between">
                                    <span>{{ $name }}</span>
                                    <span class="badge bg-secondary">{{ $count }} tenants</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title text-muted mb-2">
                            <i class="bi bi-pie-chart me-2"></i>Status Breakdown
                        </h6>
                        <ul class="list-unstyled mb-0 small">
                            @php
                                $statusCounts = \App\Models\TenantMaster::selectRaw('status, count(*) as count')
                                    ->groupBy('status')
                                    ->pluck('count', 'status');
                            @endphp
                            @foreach(['active', 'trial', 'suspended', 'cancelled'] as $status)
                                <li class="d-flex justify-content-between">
                                    <span>{{ ucfirst($status) }}</span>
                                    <span class="badge bg-secondary">{{ $statusCounts[$status] ?? 0 }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- CREATE TENANT MODAL --}}
    @if($showCreateModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Tenant</h5>
                        <button type="button" class="btn-close" wire:click="closeCreateModal"></button>
                    </div>
                    <div class="modal-body">
                        @if($createError)
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>{{ $createError }}
                            </div>
                        @endif

                        <form wire:submit.prevent="createTenant">
                            <div class="mb-3">
                                <label class="form-label">RDS Instance <span class="text-danger">*</span></label>
                                <select class="form-select @error('createRdsInstanceId') is-invalid @enderror" wire:model="createRdsInstanceId" required>
                                    <option value="">-- Select RDS Instance --</option>
                                    @foreach($rdsInstances as $rds)
                                        <option value="{{ $rds->id }}">{{ $rds->name }} ({{ $rds->host }})</option>
                                    @endforeach
                                </select>
                                @error('createRdsInstanceId') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tenant Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('createName') is-invalid @enderror" 
                                       wire:model="createName" required>
                                @error('createName') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('createContactName') is-invalid @enderror" 
                                           wire:model="createContactName" required>
                                    @error('createContactName') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('createContactEmail') is-invalid @enderror" 
                                           wire:model="createContactEmail" required>
                                    @error('createContactEmail') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control @error('createPassword') is-invalid @enderror" 
                                           wire:model="createPassword" required>
                                    <small class="text-muted">Must contain uppercase, lowercase, number, and special character</small>
                                    @error('createPassword') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control @error('createPasswordConfirmation') is-invalid @enderror" 
                                           wire:model="createPasswordConfirmation" required>
                                    @error('createPasswordConfirmation') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select @error('createStatus') is-invalid @enderror" wire:model="createStatus" required>
                                        <option value="trial">Trial</option>
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                    @error('createStatus') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Trial Ends At</label>
                                    <input type="date" class="form-control @error('createTrialEndsAt') is-invalid @enderror" 
                                           wire:model="createTrialEndsAt">
                                    @error('createTrialEndsAt') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Plan Name</label>
                                    <input type="text" class="form-control @error('createPlanName') is-invalid @enderror" 
                                           wire:model="createPlanName" placeholder="starter">
                                    @error('createPlanName') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location Count</label>
                                    <input type="number" class="form-control @error('createLocationCount') is-invalid @enderror" 
                                           wire:model="createLocationCount" min="1" value="1">
                                    @error('createLocationCount') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <hr class="my-4">
                            <h6 class="mb-3">First Location <span class="text-danger">*</span> <small class="text-muted">(Tenant must have at least one location)</small></h6>

                            <div class="mb-3">
                                <label class="form-label">Location Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('createLocationName') is-invalid @enderror" 
                                       wire:model="createLocationName" required placeholder="Main Location">
                                @error('createLocationName') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location Alias</label>
                                    <input type="text" class="form-control @error('createLocationAlias') is-invalid @enderror" 
                                           wire:model="createLocationAlias" placeholder="Optional alias">
                                    @error('createLocationAlias') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Toast Location ID</label>
                                    <input type="text" class="form-control @error('createLocationToastLocation') is-invalid @enderror" 
                                           wire:model="createLocationToastLocation" placeholder="Optional Toast location ID">
                                    @error('createLocationToastLocation') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control @error('createLocationAddress') is-invalid @enderror" 
                                       wire:model="createLocationAddress" placeholder="Street address">
                                @error('createLocationAddress') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control @error('createLocationCity') is-invalid @enderror" 
                                           wire:model="createLocationCity" placeholder="City">
                                    @error('createLocationCity') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" class="form-control @error('createLocationState') is-invalid @enderror" 
                                           wire:model="createLocationState" placeholder="State">
                                    @error('createLocationState') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" class="form-control @error('createLocationCountry') is-invalid @enderror" 
                                           wire:model="createLocationCountry" value="US" placeholder="US">
                                    @error('createLocationCountry') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" wire:click="closeCreateModal">Cancel</button>
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="createTenant">
                                        <i class="bi bi-check-circle me-1"></i> Create Tenant
                                    </span>
                                    <span wire:loading wire:target="createTenant">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Creating...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- LOCATION MODAL --}}
    @if($showLocationModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $editingLocationId ? 'Edit Location' : 'Add Location' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeLocationModal"></button>
                    </div>
                    <div class="modal-body">
                        @if($locationError)
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>{{ $locationError }}
                            </div>
                        @endif

                        <form wire:submit.prevent="saveLocation">
                            <div class="mb-3">
                                <label class="form-label">Location Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control @error('locationName') is-invalid @enderror" 
                                       wire:model="locationName" 
                                       required>
                                @error('locationName') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Location Alias</label>
                                <input type="text" 
                                       class="form-control @error('locationAlias') is-invalid @enderror" 
                                       wire:model="locationAlias">
                                @error('locationAlias') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" 
                                       class="form-control @error('locationAddress') is-invalid @enderror" 
                                       wire:model="locationAddress">
                                @error('locationAddress') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" 
                                           class="form-control @error('locationCity') is-invalid @enderror" 
                                           wire:model="locationCity">
                                    @error('locationCity') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" 
                                           class="form-control @error('locationState') is-invalid @enderror" 
                                           wire:model="locationState">
                                    @error('locationState') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zip Code</label>
                                    <input type="text" 
                                           class="form-control @error('locationZip') is-invalid @enderror" 
                                           wire:model="locationZip">
                                    @error('locationZip') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" 
                                           class="form-control @error('locationCountry') is-invalid @enderror" 
                                           wire:model="locationCountry" 
                                           value="US">
                                    @error('locationCountry') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Timezone</label>
                                <input type="text" 
                                       class="form-control @error('locationTimezone') is-invalid @enderror" 
                                       wire:model="locationTimezone" 
                                       placeholder="America/New_York">
                                @error('locationTimezone') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Toast Location ID <small class="text-muted">(Optional)</small></label>
                                <input type="text" 
                                       class="form-control @error('locationToastLocation') is-invalid @enderror" 
                                       wire:model="locationToastLocation" 
                                       placeholder="e.g., UUID from Toast">
                                @error('locationToastLocation') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               wire:model="locationIsActive" 
                                               id="locationIsActive">
                                        <label class="form-check-label" for="locationIsActive">
                                            Active
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               wire:model="locationHasGroupedTips" 
                                               id="locationHasGroupedTips">
                                        <label class="form-check-label" for="locationHasGroupedTips">
                                            Has Grouped Tips
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" wire:click="closeLocationModal">Cancel</button>
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="saveLocation">
                                        <i class="bi bi-check-circle me-1"></i> {{ $editingLocationId ? 'Update' : 'Create' }} Location
                                    </span>
                                    <span wire:loading wire:target="saveLocation">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Saving...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

