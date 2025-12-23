<div>
    <x-page-header title="User Email Routing" subtitle="Cross-RDS User Lookup & Cache Management" />

    {{-- QUICK LOOKUP CARD --}}
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-search me-2"></i>
            Quick Email Lookup
        </div>
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-6 mb-3 mb-md-0">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <input 
                            type="email" 
                            class="form-control"
                            wire:model="lookupEmail"
                            wire:keydown.enter="lookupEmail"
                            placeholder="user@example.com"
                        />
                        <button 
                            class="btn btn-primary"
                            wire:click="lookupEmail"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="lookupEmail">
                                <i class="bi bi-search me-1"></i> Lookup
                            </span>
                            <span wire:loading wire:target="lookupEmail">
                                <span class="spinner-border spinner-border-sm"></span>
                            </span>
                        </button>
                    </div>
                    <small class="text-muted">Enter an email to find which RDS/tenant it routes to</small>
                </div>
                <div class="col-md-6">
                    @if($lookupResult)
                        @if(isset($lookupResult['error']))
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                {{ $lookupResult['error'] }}
                            </div>
                        @else
                            <div class="alert alert-success mb-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>
                                            <i class="bi bi-check-circle me-1"></i>
                                            Found!
                                        </strong>
                                        <span class="badge bg-{{ $lookupResult['source'] === 'cache' ? 'secondary' : 'info' }} ms-2">
                                            {{ $lookupResult['source'] === 'cache' ? 'From Cache' : 'Live from RDS' }}
                                        </span>
                                        <div class="mt-2 small">
                                            <strong>Email:</strong> {{ $lookupResult['email'] }}<br>
                                            <strong>Tenant:</strong> {{ $lookupResult['tenant'] }}<br>
                                            <strong>RDS:</strong> {{ $lookupResult['rds'] }}<br>
                                            <strong>User ID:</strong> {{ $lookupResult['user_id'] }}
                                            @if(isset($lookupResult['cached_at']))
                                                <br><strong>Cached:</strong> {{ $lookupResult['cached_at'] }}
                                            @endif
                                        </div>
                                        @if(isset($lookupResult['note']))
                                            <div class="mt-2 text-warning small">
                                                <i class="bi bi-info-circle me-1"></i>
                                                {{ $lookupResult['note'] }}
                                            </div>
                                        @endif
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary" wire:click="clearLookup">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- STATS ROW --}}
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card bg-light h-100">
                <div class="card-body text-center">
                    <i class="bi bi-envelope fs-1 text-primary"></i>
                    <h3 class="mb-0">{{ number_format($stats['total']) }}</h3>
                    <small class="text-muted">Cached Routing Entries</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-light h-100">
                <div class="card-body text-center">
                    <i class="bi bi-database fs-1 text-success"></i>
                    <h3 class="mb-0">{{ $stats['by_rds']->count() }}</h3>
                    <small class="text-muted">RDS Instances with Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-light h-100">
                <div class="card-body text-center">
                    @if($masterRds)
                        <i class="bi bi-hdd-network fs-1 text-warning"></i>
                        <h5 class="mb-0">{{ $masterRds->name }}</h5>
                        <small class="text-muted">Master RDS (Source of Truth)</small>
                    @else
                        <i class="bi bi-exclamation-triangle fs-1 text-danger"></i>
                        <h5 class="mb-0">No Master</h5>
                        <small class="text-muted">Configure a Master RDS</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- FILTERS ROW --}}
    <div class="row g-2 align-items-center mb-3">
        <div class="col-sm-5 col-md-4">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    class="form-control"
                    placeholder="Search by email or tenant..."
                />
            </div>
        </div>

        <div class="col-sm-7 col-md-8 text-end">
            @canRaiOps('user.edit')
                <button 
                    type="button" 
                    class="btn btn-outline-primary btn-sm"
                    wire:click="syncFromRds"
                    wire:loading.attr="disabled"
                    @if(!$masterRds) disabled @endif
                >
                    <span wire:loading.remove wire:target="syncFromRds">
                        <i class="bi bi-arrow-repeat me-1"></i> Sync from Master RDS
                    </span>
                    <span wire:loading wire:target="syncFromRds">
                        <span class="spinner-border spinner-border-sm me-1"></span> Syncing...
                    </span>
                </button>
            @endcanRaiOps
        </div>
    </div>

    {{-- SESSION MESSAGES --}}
    <livewire:admin.flash-message />

    {{-- ROUTING TABLE --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Tenant</th>
                            <th>RDS Instance</th>
                            <th class="d-none d-md-table-cell">User ID</th>
                            <th class="d-none d-lg-table-cell">Cached At</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr wire:key="routing-{{ $entry->id }}">
                                <td>
                                    <code>{{ $entry->email }}</code>
                                </td>
                                <td>
                                    @if($entry->tenantMaster)
                                        <a href="{{ route('admin.tenants') }}?search={{ urlencode($entry->tenantMaster->name) }}" class="text-decoration-none">
                                            {{ $entry->tenantMaster->name }}
                                        </a>
                                    @else
                                        <span class="text-muted">Unknown</span>
                                    @endif
                                </td>
                                <td>
                                    @if($entry->tenantMaster?->rdsInstance)
                                        <span class="badge {{ $entry->tenantMaster->rdsInstance->is_master ? 'bg-warning text-dark' : 'bg-secondary' }}">
                                            <i class="bi bi-database me-1"></i>
                                            {{ $entry->tenantMaster->rdsInstance->name }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark border">Unknown</span>
                                    @endif
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <small class="text-muted">{{ $entry->remote_user_id }}</small>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    @if($entry->cached_at)
                                        <small class="text-muted" title="{{ $entry->cached_at }}">
                                            {{ $entry->cached_at->diffForHumans() }}
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @canRaiOps('user.edit')
                                        <button
                                            wire:click="deleteEntry({{ $entry->id }})"
                                            wire:confirm="Remove this email from the routing cache?"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Remove from cache"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endcanRaiOps
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-envelope-x fs-1 d-block mb-2"></i>
                                    No routing entries found.
                                    @canRaiOps('user.edit')
                                        @if($masterRds)
                                            <br>
                                            <button wire:click="syncFromRds" class="btn btn-primary btn-sm mt-2">
                                                <i class="bi bi-arrow-repeat me-1"></i> Sync from Master RDS
                                            </button>
                                        @endif
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
    {{ $entries->links() }}

    {{-- RDS BREAKDOWN --}}
    @if($stats['by_rds']->isNotEmpty())
        <div class="card mt-4">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2"></i>
                Users by RDS Instance
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($stats['by_rds'] as $stat)
                        <div class="col-md-4 col-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                <span>
                                    <i class="bi bi-database me-2"></i>
                                    {{ $stat->rds_name }}
                                </span>
                                <span class="badge bg-primary">
                                    {{ number_format($stat->count) }} users
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

