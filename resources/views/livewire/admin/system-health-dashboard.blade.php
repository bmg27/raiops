<div>
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-heart-pulse me-2"></i>System Health Dashboard
            </h4>
            <p class="text-muted mb-0">RAIOPS Command Central Status Monitor</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <small class="text-muted">
                Last refreshed: {{ $lastRefreshed }}
            </small>
            <button 
                type="button" 
                class="btn btn-primary btn-sm"
                wire:click="refreshAllHealth"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="refreshAllHealth">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh All
                </span>
                <span wire:loading wire:target="refreshAllHealth">
                    <span class="spinner-border spinner-border-sm me-1"></span> Checking...
                </span>
            </button>
        </div>
    </div>

    {{-- Session Messages --}}
    <livewire:admin.flash-message />

    {{-- Overall Status Banner --}}
    <div class="alert alert-{{ $this->overallHealth === 'healthy' ? 'success' : ($this->overallHealth === 'warning' ? 'warning' : ($this->overallHealth === 'critical' ? 'danger' : 'secondary')) }} mb-4">
        <div class="d-flex align-items-center">
            @switch($this->overallHealth)
                @case('healthy')
                    <i class="bi bi-check-circle-fill fs-3 me-3"></i>
                    <div>
                        <h5 class="mb-0">All Systems Operational</h5>
                        <small>All RDS instances are healthy and responding normally.</small>
                    </div>
                    @break
                @case('warning')
                    <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                    <div>
                        <h5 class="mb-0">Degraded Performance</h5>
                        <small>Some RDS instances are experiencing issues.</small>
                    </div>
                    @break
                @case('critical')
                    <i class="bi bi-x-octagon-fill fs-3 me-3"></i>
                    <div>
                        <h5 class="mb-0">System Alert</h5>
                        <small>One or more RDS instances are down. Immediate attention required.</small>
                    </div>
                    @break
                @default
                    <i class="bi bi-question-circle-fill fs-3 me-3"></i>
                    <div>
                        <h5 class="mb-0">Status Unknown</h5>
                        <small>Run health checks to determine system status.</small>
                    </div>
            @endswitch
        </div>
    </div>

    {{-- Metrics Cards Row --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card h-100 border-primary">
                <div class="card-body text-center py-3">
                    <i class="bi bi-database fs-2 text-primary mb-2"></i>
                    <h3 class="mb-0">{{ $this->metrics['total_rds'] }}</h3>
                    <small class="text-muted">RDS Instances</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card h-100 border-success">
                <div class="card-body text-center py-3">
                    <i class="bi bi-building fs-2 text-success mb-2"></i>
                    <h3 class="mb-0">{{ $this->metrics['total_tenants'] }}</h3>
                    <small class="text-muted">Total Tenants</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card h-100 border-info">
                <div class="card-body text-center py-3">
                    <i class="bi bi-check-circle fs-2 text-info mb-2"></i>
                    <h3 class="mb-0">{{ $this->metrics['active_tenants'] }}</h3>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card h-100 border-warning">
                <div class="card-body text-center py-3">
                    <i class="bi bi-hourglass-split fs-2 text-warning mb-2"></i>
                    <h3 class="mb-0">{{ $this->metrics['trial_tenants'] }}</h3>
                    <small class="text-muted">On Trial</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <i class="bi bi-people fs-2 text-secondary mb-2"></i>
                    <h3 class="mb-0">{{ number_format($this->metrics['total_users_cached']) }}</h3>
                    <small class="text-muted">Users (Cached)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <i class="bi bi-geo-alt fs-2 text-secondary mb-2"></i>
                    <h3 class="mb-0">{{ number_format($this->metrics['total_locations_cached']) }}</h3>
                    <small class="text-muted">Locations</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- RDS Instances Status --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-database me-2"></i>RDS Instance Status
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Instance</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Tenants</th>
                                    <th>Last Check</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->rdsInstances as $rds)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                @if($rds->is_master)
                                                    <span class="badge bg-warning text-dark me-2">Master</span>
                                                @endif
                                                <div>
                                                    <strong>{{ $rds->name }}</strong>
                                                    <br>
                                                    <small class="text-muted">{{ $rds->host }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @switch($rds->health_status)
                                                @case('healthy')
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Healthy
                                                    </span>
                                                    @break
                                                @case('degraded')
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>Degraded
                                                    </span>
                                                    @break
                                                @case('down')
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-x-circle me-1"></i>Down
                                                    </span>
                                                    @break
                                                @default
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-question-circle me-1"></i>Unknown
                                                    </span>
                                            @endswitch
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-body">
                                                {{ $rds->tenants_count }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($rds->last_health_check_at)
                                                <small class="text-muted" title="{{ $rds->last_health_check_at }}">
                                                    {{ $rds->last_health_check_at->diffForHumans() }}
                                                </small>
                                            @else
                                                <small class="text-muted">Never</small>
                                            @endif
                                        </td>
                                        <td>
                                            <button 
                                                type="button" 
                                                class="btn btn-sm btn-outline-primary"
                                                wire:click="refreshRdsHealth({{ $rds->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="refreshRdsHealth({{ $rds->id }})"
                                                title="Check Health"
                                            >
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No RDS instances configured.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sync Status & Activity --}}
        <div class="col-lg-4">
            {{-- Sync Status Card --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-repeat me-2"></i>Sync Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Tenant Data Sync</span>
                            @if($this->syncStatus['tenant_sync_healthy'])
                                <span class="badge bg-success">Healthy</span>
                            @else
                                <span class="badge bg-warning text-dark">Stale</span>
                            @endif
                        </div>
                        <small class="text-muted">
                            Last sync: 
                            {{ $this->syncStatus['last_tenant_sync'] 
                                ? \Carbon\Carbon::parse($this->syncStatus['last_tenant_sync'])->diffForHumans() 
                                : 'Never' }}
                        </small>
                        @if($this->syncStatus['stale_tenants'] > 0)
                            <br>
                            <small class="text-warning">
                                {{ $this->syncStatus['stale_tenants'] }} tenant(s) have stale data
                            </small>
                        @endif
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>User Routing Sync</span>
                            @if($this->syncStatus['routing_sync_healthy'])
                                <span class="badge bg-success">Healthy</span>
                            @else
                                <span class="badge bg-warning text-dark">Stale</span>
                            @endif
                        </div>
                        <small class="text-muted">
                            Last sync: 
                            {{ $this->syncStatus['last_routing_sync'] 
                                ? \Carbon\Carbon::parse($this->syncStatus['last_routing_sync'])->diffForHumans() 
                                : 'Never' }}
                        </small>
                    </div>

                    <div class="d-flex justify-content-between small text-muted pt-2 border-top">
                        <span>Routing entries:</span>
                        <span>{{ number_format($this->metrics['routing_entries']) }}</span>
                    </div>
                </div>
            </div>

            {{-- Audit Activity Card --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-activity me-2"></i>Audit Activity
                    </h5>
                    <a href="{{ route('admin.audit-logs') }}" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="text-center">
                            <h4 class="mb-0">{{ $this->metrics['audit_events_today'] }}</h4>
                            <small class="text-muted">Today</small>
                        </div>
                        <div class="text-center">
                            <h4 class="mb-0">{{ $this->metrics['audit_events_week'] }}</h4>
                            <small class="text-muted">This Week</small>
                        </div>
                    </div>
                    
                    <h6 class="text-muted small mb-2">Recent Activity</h6>
                    <div class="list-group list-group-flush small">
                        @forelse($this->recentActivity->take(5) as $log)
                            <div class="list-group-item px-0 py-2 d-flex justify-content-between">
                                <div>
                                    <span class="badge {{ $log->getActionBadgeClass() }} me-1">
                                        {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                    @if($log->model_type)
                                        <span class="text-muted">{{ $log->model_type }}</span>
                                    @endif
                                </div>
                                <small class="text-muted">{{ $log->created_at?->diffForHumans() }}</small>
                            </div>
                        @empty
                            <div class="text-center text-muted py-3">
                                No recent activity
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-lightning-charge me-2"></i>Quick Actions
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-auto">
                    <a href="{{ route('admin.rds') }}" class="btn btn-outline-primary">
                        <i class="bi bi-database me-1"></i> Manage RDS
                    </a>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.tenants') }}" class="btn btn-outline-primary">
                        <i class="bi bi-building me-1"></i> View Tenants
                    </a>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.user-routing') }}" class="btn btn-outline-primary">
                        <i class="bi bi-signpost-split me-1"></i> User Routing
                    </a>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.audit-logs') }}" class="btn btn-outline-primary">
                        <i class="bi bi-journal-text me-1"></i> Audit Logs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

