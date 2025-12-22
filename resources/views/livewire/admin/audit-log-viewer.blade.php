<div>
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-journal-text me-2"></i>Audit Logs
            </h4>
            <p class="text-muted mb-0">Track all administrative actions across RAINBO</p>
        </div>
        <div>
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearFilters">
                <i class="bi bi-x-circle me-1"></i> Clear Filters
            </button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 text-white-50">Total Events</h6>
                            <h3 class="mb-0">{{ number_format($stats['total']) }}</h3>
                        </div>
                        <i class="bi bi-activity fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        @foreach(array_slice($stats['by_action'], 0, 3) as $action => $count)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-muted">{{ $this->formatAction($action) }}</h6>
                                <h3 class="mb-0">{{ number_format($count) }}</h3>
                            </div>
                            <span class="badge {{ $this->getActionBadgeClass($action) }} fs-5 p-2">
                                @switch($action)
                                    @case('created')
                                        <i class="bi bi-plus-circle"></i>
                                        @break
                                    @case('updated')
                                        <i class="bi bi-pencil"></i>
                                        @break
                                    @case('deleted')
                                        <i class="bi bi-trash"></i>
                                        @break
                                    @case('impersonation_launched')
                                        <i class="bi bi-box-arrow-up-right"></i>
                                        @break
                                    @default
                                        <i class="bi bi-record-circle"></i>
                                @endswitch
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                {{-- Date Range Picker --}}
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label small text-muted">Date Range</label>
                    <livewire:components.date-range-picker 
                        wire:model.live="dateRange"
                        :max-span="365"
                        :show-nav-buttons="true"
                        :preset-ranges="['Today', 'Yesterday', 'Last 7 Days', 'Last 30 Days', 'This Week', 'Last Week', 'This Month', 'Last Month']"
                        opens="right"
                        placeholder="Select date range"
                    />
                </div>

                {{-- Search --}}
                <div class="col-12 col-md-3 col-lg-2">
                    <label class="form-label small text-muted">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input 
                            type="text" 
                            class="form-control" 
                            placeholder="Search logs..."
                            wire:model.live.debounce.300ms="search"
                        >
                    </div>
                </div>

                {{-- Action Filter --}}
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label small text-muted">Action</label>
                    <select class="form-select" wire:model.live="actionFilter">
                        <option value="all">All Actions</option>
                        @foreach($this->actions as $action)
                            <option value="{{ $action }}">{{ $this->formatAction($action) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- User Filter --}}
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label small text-muted">User</label>
                    <select class="form-select" wire:model.live="userFilter">
                        <option value="all">All Users</option>
                        @foreach($this->users as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Model Type Filter --}}
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label small text-muted">Model</label>
                    <select class="form-select" wire:model.live="modelFilter">
                        <option value="all">All Models</option>
                        @foreach($this->modelTypes as $modelType)
                            <option value="{{ $modelType }}">{{ $modelType }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Logs Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 160px;">Timestamp</th>
                            <th style="width: 120px;">Action</th>
                            <th>User</th>
                            <th>Model</th>
                            <th>Context</th>
                            <th style="width: 100px;">Source</th>
                            <th style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr wire:key="log-{{ $log->id }}">
                                <td class="text-muted small">
                                    <span title="{{ $log->created_at?->format('Y-m-d H:i:s') }}">
                                        {{ $log->created_at?->diffForHumans() }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $log->getActionBadgeClass() }}">
                                        {{ $this->formatAction($log->action) }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->user)
                                        <div class="fw-medium">{{ $log->user->name }}</div>
                                        <small class="text-muted">{{ $log->user->email }}</small>
                                    @else
                                        <span class="text-muted">System</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->model_type)
                                        <code class="small">{{ $log->model_type }}</code>
                                        @if($log->model_id)
                                            <span class="text-muted">#{{ $log->model_id }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->tenant)
                                        <span class="badge bg-light text-dark me-1">
                                            <i class="bi bi-building me-1"></i>{{ $log->tenant->name }}
                                        </span>
                                    @endif
                                    @if($log->rdsInstance)
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-database me-1"></i>{{ $log->rdsInstance->name }}
                                        </span>
                                    @endif
                                    @if(!$log->tenant && !$log->rdsInstance)
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $log->source === 'rainbo' ? 'bg-primary' : 'bg-secondary' }}">
                                        {{ strtoupper($log->source) }}
                                    </span>
                                </td>
                                <td>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-primary"
                                        wire:click="viewDetails({{ $log->id }})"
                                        title="View Details"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                                    <span class="text-muted">No audit logs found for the selected filters.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
            <div class="card-footer">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- Details Modal --}}
    @if($selectedLogDetails)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-journal-text me-2"></i>
                            Audit Log Details
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeDetails"></button>
                    </div>
                    <div class="modal-body">
                        {{-- Summary --}}
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Action</h6>
                                <span class="badge {{ $selectedLogDetails['action_badge_class'] }} fs-6">
                                    {{ $this->formatAction($selectedLogDetails['action']) }}
                                </span>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Timestamp</h6>
                                <span>{{ $selectedLogDetails['created_at'] }}</span>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">User</h6>
                                <div class="fw-medium">{{ $selectedLogDetails['user_name'] }}</div>
                                @if($selectedLogDetails['user_email'])
                                    <small class="text-muted">{{ $selectedLogDetails['user_email'] }}</small>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Source</h6>
                                <span class="badge {{ $selectedLogDetails['source'] === 'rainbo' ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ strtoupper($selectedLogDetails['source']) }}
                                </span>
                            </div>
                        </div>

                        @if($selectedLogDetails['model_type'])
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Model</h6>
                                    <code>{{ $selectedLogDetails['model_type'] }}</code>
                                    @if($selectedLogDetails['model_id'])
                                        <span class="text-muted">#{{ $selectedLogDetails['model_id'] }}</span>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">Context</h6>
                                    @if($selectedLogDetails['tenant_name'])
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-building me-1"></i>{{ $selectedLogDetails['tenant_name'] }}
                                        </span>
                                    @endif
                                    @if($selectedLogDetails['rds_name'])
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-database me-1"></i>{{ $selectedLogDetails['rds_name'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <hr>

                        {{-- Changes Diff --}}
                        @if(!empty($selectedLogDetails['changes']))
                            <h6 class="text-muted mb-3"><i class="bi bi-file-diff me-2"></i>Changes</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 25%;">Field</th>
                                            <th style="width: 37.5%;">Old Value</th>
                                            <th style="width: 37.5%;">New Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($selectedLogDetails['changes'] as $field => $values)
                                            <tr>
                                                <td><code>{{ $field }}</code></td>
                                                <td class="text-danger">
                                                    @if($values['old'] !== null)
                                                        <del>{{ is_array($values['old']) ? json_encode($values['old']) : $values['old'] }}</del>
                                                    @else
                                                        <em class="text-muted">null</em>
                                                    @endif
                                                </td>
                                                <td class="text-success">
                                                    @if($values['new'] !== null)
                                                        {{ is_array($values['new']) ? json_encode($values['new']) : $values['new'] }}
                                                    @else
                                                        <em class="text-muted">null</em>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif($selectedLogDetails['new_values'])
                            <h6 class="text-muted mb-3"><i class="bi bi-code-square me-2"></i>Data</h6>
                            <pre class="bg-light p-3 rounded small"><code>{{ json_encode($selectedLogDetails['new_values'], JSON_PRETTY_PRINT) }}</code></pre>
                        @else
                            <p class="text-muted text-center py-3">No detailed change data available.</p>
                        @endif

                        <hr>

                        {{-- Request Info --}}
                        <h6 class="text-muted mb-3"><i class="bi bi-globe me-2"></i>Request Info</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <small class="text-muted">IP Address</small>
                                <div><code>{{ $selectedLogDetails['ip_address'] ?? 'N/A' }}</code></div>
                            </div>
                            <div class="col-md-8">
                                <small class="text-muted">User Agent</small>
                                <div class="small text-truncate" title="{{ $selectedLogDetails['user_agent'] }}">
                                    {{ $selectedLogDetails['user_agent'] ?? 'N/A' }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeDetails">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


