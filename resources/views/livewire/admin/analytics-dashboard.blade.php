<div>
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-graph-up me-2"></i>Analytics Dashboard
            </h4>
            <p class="text-muted mb-0">Platform metrics, MRR tracking, and tenant insights</p>
        </div>
        <div>
            @canRaiOps('reports.export')
                <a 
                    href="{{ route('admin.analytics.export') }}" 
                    class="btn btn-outline-primary btn-sm"
                    target="_blank"
                >
                    <i class="bi bi-download me-1"></i> Export CSV
                </a>
            @endcanRaiOps
        </div>
    </div>

    {{-- MRR Overview Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-white-50 mb-1">Monthly Recurring Revenue</h6>
                            <h2 class="mb-0">${{ number_format($this->mrrMetrics['total_mrr'], 2) }}</h2>
                        </div>
                        <i class="bi bi-currency-dollar fs-1 opacity-25"></i>
                    </div>
                    <small class="text-white-50">
                        From {{ $this->mrrMetrics['active_billings'] }} billing accounts
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-white-50 mb-1">Annual Recurring Revenue</h6>
                            <h2 class="mb-0">${{ number_format($this->mrrMetrics['arr'], 2) }}</h2>
                        </div>
                        <i class="bi bi-calendar-check fs-1 opacity-25"></i>
                    </div>
                    <small class="text-white-50">
                        Projected annual revenue
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-white-50 mb-1">Average MRR per Tenant</h6>
                            <h2 class="mb-0">${{ number_format($this->mrrMetrics['avg_mrr'], 2) }}</h2>
                        </div>
                        <i class="bi bi-bar-chart fs-1 opacity-25"></i>
                    </div>
                    <small class="text-white-50">
                        Per active billing account
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-dark opacity-75 mb-1">Trials Expiring Soon</h6>
                            <h2 class="mb-0">{{ $this->tenantMetrics['expiring_trials'] }}</h2>
                        </div>
                        <i class="bi bi-hourglass-split fs-1 opacity-25"></i>
                    </div>
                    <small class="text-dark opacity-75">
                        Within next 7 days
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Tenant Status Breakdown --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-building me-2"></i>Tenant Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Total Tenants</span>
                            <h4 class="mb-0">{{ $this->tenantMetrics['total'] }}</h4>
                        </div>
                        <small class="text-success">
                            <i class="bi bi-plus-circle me-1"></i>
                            {{ $this->tenantMetrics['recent_tenants'] }} new in last 30 days
                        </small>
                    </div>

                    @php
                        $statusColors = [
                            'active' => 'success',
                            'trial' => 'warning',
                            'suspended' => 'danger',
                            'cancelled' => 'secondary',
                        ];
                    @endphp

                    @foreach(['active', 'trial', 'suspended', 'cancelled'] as $status)
                        @php
                            $count = $this->tenantMetrics['by_status'][$status] ?? 0;
                            $percentage = $this->tenantMetrics['total'] > 0 
                                ? round(($count / $this->tenantMetrics['total']) * 100) 
                                : 0;
                        @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>
                                    <span class="badge bg-{{ $statusColors[$status] ?? 'secondary' }} me-1">
                                        {{ ucfirst($status) }}
                                    </span>
                                </span>
                                <span class="fw-bold">{{ $count }}</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-{{ $statusColors[$status] ?? 'secondary' }}" 
                                     style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- MRR by Plan --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart me-2"></i>MRR by Plan
                    </h5>
                </div>
                <div class="card-body">
                    @if($this->mrrMetrics['by_plan']->count() > 0)
                        @foreach($this->mrrMetrics['by_plan'] as $plan)
                            @php
                                $percentage = $this->mrrMetrics['total_mrr'] > 0 
                                    ? round(($plan['mrr'] / $this->mrrMetrics['total_mrr']) * 100) 
                                    : 0;
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>
                                        <strong>{{ $plan['plan'] }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $plan['tenants'] }} tenant(s)</small>
                                    </span>
                                    <span class="fw-bold">${{ number_format($plan['mrr'], 2) }}</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No billing data yet
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tenants by RDS --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-database me-2"></i>Tenants by RDS
                    </h5>
                </div>
                <div class="card-body">
                    @if($this->tenantMetrics['by_rds']->count() > 0)
                        @foreach($this->tenantMetrics['by_rds'] as $rds)
                            @php
                                $percentage = $this->tenantMetrics['total'] > 0 
                                    ? round(($rds['count'] / $this->tenantMetrics['total']) * 100) 
                                    : 0;
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>{{ $rds['rds_name'] }}</span>
                                    <span class="fw-bold">{{ $rds['count'] }}</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-info" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No RDS data
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-0">
        {{-- Top Tenants by Users --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-trophy me-2"></i>Top Tenants by Users
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Tenant</th>
                                    <th>RDS</th>
                                    <th class="text-end">Users</th>
                                    <th class="text-end">Locations</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->topTenants as $index => $tenant)
                                    <tr>
                                        <td>
                                            @if($index < 3)
                                                <span class="badge bg-{{ $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : 'danger') }}">
                                                    {{ $index + 1 }}
                                                </span>
                                            @else
                                                {{ $index + 1 }}
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.tenants') }}?viewDetails={{ $tenant->id }}" class="text-decoration-none">
                                                {{ $tenant->name }}
                                            </a>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $tenant->rdsInstance?->name ?? 'N/A' }}</small>
                                        </td>
                                        <td class="text-end">
                                            <strong>{{ number_format($tenant->cached_user_count) }}</strong>
                                        </td>
                                        <td class="text-end">
                                            {{ number_format($tenant->cached_location_count) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No tenant data available
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Billing Alerts --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-bell me-2"></i>Billing Alerts
                    </h5>
                    @if($this->billingAlerts['past_due_count'] > 0)
                        <span class="badge bg-danger">
                            {{ $this->billingAlerts['past_due_count'] }} past due
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    @if($this->billingAlerts['past_due']->count() > 0)
                        <h6 class="text-danger mb-2">
                            <i class="bi bi-exclamation-circle me-1"></i>Past Due
                        </h6>
                        <div class="list-group list-group-flush mb-3">
                            @foreach($this->billingAlerts['past_due'] as $billing)
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $billing->tenant?->name ?? 'Unknown' }}</strong>
                                        <br>
                                        <small class="text-danger">
                                            Due: {{ $billing->next_billing_date?->format('M d, Y') }}
                                            ({{ abs($billing->daysUntilBilling()) }} days overdue)
                                        </small>
                                    </div>
                                    <span class="badge bg-danger">${{ number_format($billing->mrr, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($this->billingAlerts['upcoming']->count() > 0)
                        <h6 class="text-warning mb-2">
                            <i class="bi bi-clock me-1"></i>Upcoming (Next 7 Days)
                        </h6>
                        <div class="list-group list-group-flush">
                            @foreach($this->billingAlerts['upcoming'] as $billing)
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $billing->tenant?->name ?? 'Unknown' }}</strong>
                                        <br>
                                        <small class="text-muted">
                                            {{ $billing->next_billing_date?->format('M d, Y') }}
                                            ({{ $billing->daysUntilBilling() }} days)
                                        </small>
                                    </div>
                                    <span class="badge bg-light text-dark">${{ number_format($billing->mrr, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($this->billingAlerts['past_due']->count() === 0 && $this->billingAlerts['upcoming']->count() === 0)
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                            No billing alerts
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Subscription Plans Overview --}}
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-tags me-2"></i>Subscription Plans
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Plan</th>
                            <th class="text-end">Monthly</th>
                            <th class="text-end">Annual</th>
                            <th class="text-center">Users</th>
                            <th class="text-center">Locations</th>
                            <th class="text-center">Subscribers</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->plans as $plan)
                            <tr>
                                <td>
                                    <strong>{{ $plan->name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $plan->code }}</small>
                                </td>
                                <td class="text-end">${{ number_format($plan->monthly_price, 2) }}</td>
                                <td class="text-end">
                                    @if($plan->annual_price)
                                        ${{ number_format($plan->annual_price, 2) }}
                                        @if($plan->getAnnualSavingsPercent() > 0)
                                            <br>
                                            <small class="text-success">Save {{ $plan->getAnnualSavingsPercent() }}%</small>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $plan->getUserLimitDisplay() }}</td>
                                <td class="text-center">{{ $plan->getLocationLimitDisplay() }}</td>
                                <td class="text-center">
                                    <span class="badge bg-primary">{{ $plan->tenant_billings_count }}</span>
                                </td>
                                <td class="text-center">
                                    @if($plan->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No subscription plans configured
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

