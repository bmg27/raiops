<div>
    <x-page-header title="RDS Instance Management" />

    {{-- FILTERS ROW --}}
    <div class="row g-2 align-items-center mb-3">
        <div class="col-sm-4">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    class="form-control"
                    placeholder="Search by name or host..."
                />
            </div>
        </div>

        <div class="text-end col-sm-8">
            <button type="button" class="btn btn-outline-secondary btn-sm me-2" wire:click="refreshAllHealth">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh All Health
            </button>
            <button type="button" class="btn btn-primary btn-sm" wire:click="openModal">
                <i class="bi bi-plus-lg me-1"></i> Add RDS Instance
            </button>
        </div>
    </div>

    {{-- SESSION MESSAGES --}}
    <livewire:admin.flash-message fade="true" />

    {{-- RDS INSTANCES LIST --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Host</th>
                            <th>Database</th>
                            <th class="text-center">Tenants</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Health</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($instances as $rds)
                            <tr>
                                <td>
                                    <strong>{{ $rds->name }}</strong>
                                    @if($rds->is_master)
                                        <span class="badge bg-warning text-dark ms-1">Master</span>
                                    @endif
                                </td>
                                <td>
                                    <code>{{ $rds->host }}:{{ $rds->port }}</code>
                                </td>
                                <td>
                                    <span class="text-muted">{{ $rds->rai_database }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $rds->tenants_count ?? 0 }}</span>
                                </td>
                                <td class="text-center">
                                    @if($rds->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $rds->getHealthBadgeClass() }}">
                                        <i class="bi {{ $rds->getHealthIcon() }} me-1"></i>
                                        {{ ucfirst($rds->health_status) }}
                                    </span>
                                    @if($rds->last_health_check_at)
                                        <br>
                                        <small class="text-muted">
                                            {{ $rds->last_health_check_at->diffForHumans() }}
                                        </small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button
                                        wire:click="refreshHealth({{ $rds->id }})"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Check Health"
                                    >
                                        <i class="bi bi-heart-pulse"></i>
                                    </button>
                                    <button
                                        wire:click="openModal({{ $rds->id }})"
                                        class="btn btn-sm btn-outline-primary"
                                        title="Edit"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    @if(!$rds->is_master)
                                        <button
                                            wire:click="delete({{ $rds->id }})"
                                            wire:confirm="Are you sure you want to delete this RDS instance?"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-database-x fs-1 d-block mb-2"></i>
                                    No RDS instances configured yet.
                                    <br>
                                    <button wire:click="openModal" class="btn btn-primary btn-sm mt-2">
                                        <i class="bi bi-plus-lg me-1"></i> Add First RDS Instance
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- PAGINATION --}}
    {{ $instances->links() }}

    {{-- ADD/EDIT MODAL --}}
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-database me-2"></i>
                            {{ $editId ? 'Edit RDS Instance' : 'Add RDS Instance' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit="save">
                            <div class="row">
                                {{-- Name --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        wire:model="name"
                                        class="form-control @error('name') is-invalid @enderror"
                                        placeholder="e.g., Production RDS 1"
                                    />
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- App URL --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">RAI App URL <span class="text-danger">*</span></label>
                                    <input
                                        type="url"
                                        wire:model="app_url"
                                        class="form-control @error('app_url') is-invalid @enderror"
                                        placeholder="https://app.example.com"
                                    />
                                    @error('app_url')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">URL to the RAI instance for this RDS</small>
                                </div>

                                {{-- Host --}}
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Host <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        wire:model="host"
                                        class="form-control @error('host') is-invalid @enderror"
                                        placeholder="e.g., db.example.com or 127.0.0.1"
                                    />
                                    @error('host')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Port --}}
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Port <span class="text-danger">*</span></label>
                                    <input
                                        type="number"
                                        wire:model="port"
                                        class="form-control @error('port') is-invalid @enderror"
                                        min="1"
                                        max="65535"
                                    />
                                    @error('port')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Username --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        wire:model="username"
                                        class="form-control @error('username') is-invalid @enderror"
                                        placeholder="Database username"
                                    />
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Password --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        Password
                                        @if(!$editId)
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>
                                    <input
                                        type="password"
                                        wire:model="password"
                                        class="form-control @error('password') is-invalid @enderror"
                                        placeholder="{{ $editId ? 'Leave blank to keep current' : 'Database password' }}"
                                    />
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @if($editId)
                                        <small class="text-muted">Leave blank to keep existing password</small>
                                    @endif
                                </div>

                                {{-- RAI Database --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">RAI Database <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        wire:model="rai_database"
                                        class="form-control @error('rai_database') is-invalid @enderror"
                                        placeholder="e.g., rai_production"
                                    />
                                    @error('rai_database')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Providers Database --}}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Providers Database</label>
                                    <input
                                        type="text"
                                        wire:model="providers_database"
                                        class="form-control @error('providers_database') is-invalid @enderror"
                                        placeholder="e.g., providers_production (optional)"
                                    />
                                    @error('providers_database')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Flags --}}
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input
                                            type="checkbox"
                                            wire:model="is_active"
                                            class="form-check-input"
                                            id="isActive"
                                        />
                                        <label class="form-check-label" for="isActive">Active</label>
                                    </div>
                                    <small class="text-muted">Inactive instances won't be used for connections</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input
                                            type="checkbox"
                                            wire:model="is_master"
                                            class="form-check-input"
                                            id="isMaster"
                                        />
                                        <label class="form-check-label" for="isMaster">Master RDS</label>
                                    </div>
                                    <small class="text-muted">Only one RDS can be the master</small>
                                </div>

                                {{-- Notes --}}
                                <div class="col-12 mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea
                                        wire:model="notes"
                                        class="form-control"
                                        rows="2"
                                        placeholder="Optional notes about this RDS instance..."
                                    ></textarea>
                                </div>
                            </div>

                            {{-- Test Connection Result --}}
                            @if($testResult)
                                <div class="alert {{ $testResult['success'] ? 'alert-success' : 'alert-danger' }} mb-3">
                                    <i class="bi {{ $testResult['success'] ? 'bi-check-circle' : 'bi-x-circle' }} me-2"></i>
                                    <strong>{{ $testResult['success'] ? 'Connection Successful' : 'Connection Failed' }}</strong>
                                    <br>
                                    {{ $testResult['message'] }}
                                    @if($testResult['latency_ms'])
                                        <br><small>Latency: {{ $testResult['latency_ms'] }}ms</small>
                                    @endif
                                </div>
                            @endif
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="testConnection">
                            <i class="bi bi-plug me-1"></i> Test Connection
                        </button>
                        <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="save">
                            <i class="bi bi-check-lg me-1"></i> {{ $editId ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

