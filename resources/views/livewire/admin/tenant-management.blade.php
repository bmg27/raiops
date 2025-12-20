<div>
    <x-page-header title="Tenant Management" />

    {{-- TABS --}}
    <div class="nav nav-tabs mb-4 flex-nowrap overflow-x-auto tabs-no-scrollbar" role="tablist">
        <button class="nav-link {{ $activeTab === 'tenants' ? 'active' : '' }} flex-shrink-0"
                wire:click="$set('activeTab', 'tenants')"
                type="button"
                role="tab">
            <i class="bi bi-building me-1"></i> Tenants
        </button>
        <button class="nav-link {{ $activeTab === 'pending' ? 'active' : '' }} flex-shrink-0"
                wire:click="$set('activeTab', 'pending')"
                type="button"
                role="tab">
            <i class="bi bi-clock-history me-1"></i> Pending Apps
            @if($pendingInvitations->count() > 0)
                <span class="badge bg-warning text-dark ms-1">{{ $pendingInvitations->count() }}</span>
            @endif
        </button>
        <button class="nav-link {{ $activeTab === 'invitations' ? 'active' : '' }} flex-shrink-0"
                wire:click="$set('activeTab', 'invitations')"
                type="button"
                role="tab">
            <i class="bi bi-envelope me-1"></i> Invites
        </button>
    </div>

    {{-- TENANTS TAB --}}
    @if($activeTab === 'tenants')
        @if($selectedTenantId)
            {{-- SELECTED TENANT VIEW --}}
            @php
                $tenant = \App\Models\Tenant::withoutGlobalScopes()
                    ->with(['subscription', 'owner'])
                    ->find($selectedTenantId);
                // Load locations without global scopes
                $tenant->setRelation('locations', \App\Models\SevenLocation::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get());
            @endphp

            {{-- BACK BUTTON --}}
            <div class="mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="backToTenantList">
                    <i class="bi bi-arrow-left me-1"></i> Back to Tenant List
                </button>
            </div>

            {{-- TENANT INFO HEADER --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-12 col-md-8 mb-2 mb-md-0">
                            <h5 class="mb-1">{{ $tenant->name }}</h5>
                            <div class="small text-muted">
                                <div class="mb-1">
                                    <strong>Contact:</strong> {{ $tenant->primary_contact_name ?? 'N/A' }}
                                </div>
                                <div>
                                    <strong>Email:</strong> {{ $tenant->primary_contact_email ?? 'N/A' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 text-start text-md-end">
                            @php
                                $statusBadge = match($tenant->status) {
                                    'trial' => ['class' => 'bg-warning-subtle border-warning text-warning', 'icon' => 'bi-clock-history', 'label' => 'Trial'],
                                    'active' => ['class' => 'bg-success-subtle border-success text-success', 'icon' => 'bi-check-circle', 'label' => 'Active'],
                                    'suspended' => ['class' => 'bg-danger-subtle border-danger text-danger', 'icon' => 'bi-x-circle', 'label' => 'Suspended'],
                                    'cancelled' => ['class' => 'bg-secondary-subtle border-secondary text-secondary', 'icon' => 'bi-slash-circle', 'label' => 'Cancelled'],
                                    default => ['class' => 'bg-secondary-subtle border-secondary text-secondary', 'icon' => 'bi-question-circle', 'label' => ucfirst($tenant->status)],
                                };
                            @endphp
                            <span class="badge rounded-pill fw-normal {{ $statusBadge['class'] }}">
                                <i class="bi {{ $statusBadge['icon'] }} me-1"></i>
                                {{ $statusBadge['label'] }}
                            </span>
                            @if($tenant->isOnTrial())
                                <br>
                                <small class="text-muted">
                                    {{ $tenant->trialDaysRemaining() }} days remaining
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION BADGE TABS --}}
            <div class="nav nav-pills mb-4 flex-nowrap overflow-x-auto tabs-no-scrollbar" role="tablist">
                <button class="nav-link {{ $activeSection === 'subscription' ? 'active' : '' }} flex-shrink-0"
                        wire:click="setActiveSection('subscription')"
                        type="button"
                        role="tab">
                    <i class="bi bi-credit-card me-1"></i> <span class="d-none d-sm-inline">Subscription </span>Settings
                </button>
                <button class="nav-link {{ $activeSection === 'locations' ? 'active' : '' }} flex-shrink-0"
                        wire:click="setActiveSection('locations')"
                        type="button"
                        role="tab">
                    <i class="bi bi-building me-1"></i> Locations
                </button>
                <button class="nav-link {{ $activeSection === 'providers' ? 'active' : '' }} flex-shrink-0"
                        wire:click="setActiveSection('providers')"
                        type="button"
                        role="tab">
                    <i class="bi bi-plugin me-1"></i> Providers
                </button>
            </div>

            {{-- SESSION MESSAGES AND ERRORS --}}
            <livewire:admin.flash-message />

            {{-- SUBSCRIPTION SECTION --}}
            @if($activeSection === 'subscription')
                @php
                    $planConfigs = [
                        'starter' => \App\Models\TenantSubscription::getPlanConfig('starter'),
                        'professional' => \App\Models\TenantSubscription::getPlanConfig('professional'),
                        'enterprise' => \App\Models\TenantSubscription::getPlanConfig('enterprise'),
                    ];
                    $selectedPlanConfig = $planConfigs[$subscriptionPlan] ?? $planConfigs['starter'];
                    $calculatedPrice = $selectedPlanConfig['base_price'] + ($subscriptionLocationCount * $selectedPlanConfig['price_per_location']);
                @endphp

                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Tenant Settings & Subscription</strong>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            {{-- LEFT COLUMN --}}
                            <div class="col-12 col-md-6 mb-4 mb-md-0">
                                {{-- TENANT SETTINGS SECTION --}}
                                <h6 class="mb-3 text-muted">Tenant Information</h6>

                                <!-- Tenant Name -->
                                @error('tenantName')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                                <div class="mb-3">
                                    <label class="form-label">Tenant Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" wire:model="tenantName" placeholder="Enter tenant name">
                                </div>

                                <!-- Tenant Status -->
                                @error('tenantStatus')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                                <div class="mb-3">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" wire:model.live="tenantStatus">
                                        <option value="trial">Trial</option>
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>

                                <!-- Trial Ends At (shown only when status is trial) -->
                                @if($tenantStatus === 'trial')
                                    @error('tenantTrialEndsAt')
                                    <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                    <div class="mb-3">
                                        <label class="form-label">Trial Ends At <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" wire:model="tenantTrialEndsAt">
                                        <small class="form-text text-muted">Required when status is "Trial". Users will lose access after this date.</small>
                                    </div>
                                @endif

                                <!-- Contact Name -->
                                @error('tenantContactName')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                                <div class="mb-3">
                                    <label class="form-label">Primary Contact Name <span class="text-muted small">(Optional)</span></label>
                                    <input type="text" class="form-control" wire:model="tenantContactName" placeholder="Enter contact name">
                                </div>

                                <!-- Contact Email -->
                                @error('tenantContactEmail')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                                <div class="mb-3">
                                    <label class="form-label">Primary Contact Email <span class="text-muted small">(Optional)</span></label>
                                    <input type="email" class="form-control" wire:model="tenantContactEmail" placeholder="Enter contact email">
                                </div>
                            </div>

                            {{-- RIGHT COLUMN --}}
                            <div class="col-12 col-md-6">
                                {{-- SUBSCRIPTION SECTION --}}
                                <h6 class="mb-3 text-muted">Subscription</h6>

                                <!-- Subscription Plan -->
                                @error('subscriptionPlan')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                                <div class="mb-3">
                                    <label class="form-label">Subscription Plan <span class="text-danger">*</span></label>
                                    <select class="form-select" wire:model.live="subscriptionPlan">
                                        @foreach($planConfigs as $key => $config)
                                            <option value="{{ $key }}">{{ $config['name'] }} - ${{ number_format($config['base_price'], 2) }}/month base + ${{ number_format($config['price_per_location'], 2) }}/location (Max: {{ $config['max_locations'] }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Location Count -->
                                @error('subscriptionLocationCount')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                                <div class="mb-3">
                                    <label class="form-label">Location Count <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" wire:model.live="subscriptionLocationCount" min="1" max="{{ $selectedPlanConfig['max_locations'] }}">
                                    <small class="form-text text-muted">Maximum {{ $selectedPlanConfig['max_locations'] }} locations for {{ $selectedPlanConfig['name'] }} plan.</small>
                                </div>

                                <!-- Price Summary -->
                                <div class="border rounded p-3 bg-light mb-3">
                                    <strong>Price Summary:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Base Price: ${{ number_format($selectedPlanConfig['base_price'], 2) }}/month</li>
                                        <li>Price per Location: ${{ number_format($selectedPlanConfig['price_per_location'], 2) }}/month</li>
                                        <li>Location Count: {{ $subscriptionLocationCount }}</li>
                                        <li><strong>Total Monthly Price: ${{ number_format($calculatedPrice, 2) }}/month</strong></li>
                                    </ul>
                                </div>

                                <!-- Plan Features -->
                                <div class="mb-3">
                                    <label class="form-label">Plan Features:</label>
                                    <ul class="list-unstyled mb-0">
                                        @foreach($selectedPlanConfig['features'] as $feature)
                                            <li><i class="bi bi-check-circle text-success me-2"></i>{{ $feature }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- SAVE BUTTON --}}
                        <div class="row">
                            <div class="col-12">
                                <hr class="my-3">
                                <div class="d-grid d-md-flex justify-content-md-end">
                                    <button type="button" class="btn btn-primary" wire:click="saveTenantAndSubscription">
                                        <i class="bi bi-check-lg me-1"></i> Save All Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- LOCATIONS SECTION --}}
            @if($activeSection === 'locations')
                <div class="card mb-4">
                    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                        <strong>Locations ({{ $tenant->locations->count() }})</strong>
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openLocationModal({{ $tenant->id }})">
                            <i class="bi bi-plus-lg me-1"></i> Add Location
                        </button>
                    </div>
                    <div class="card-body">
                        @if($tenant->locations->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-nowrap table-hover mb-0">
                                    <thead>
                                    <tr>
                                        <th class="sticky-col">ID / Name</th>
                                        <th class="d-none d-md-table-cell">Alias</th>
                                        <th class="d-none d-lg-table-cell">City</th>
                                        <th class="d-none d-lg-table-cell">State</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($tenant->locations as $location)
                                        <tr>
                                            <td class="sticky-col">
                                                <i class="bi bi-building me-2"></i>
                                                {{-- Mobile: truncated --}}
                                                <span class="d-inline-block d-md-none text-truncate" style="max-width: 120px;">
                                                    <strong><a class="link-secondary text-decoration-none cursor-pointer" wire:click="openLocationModal({{ $tenant->id }}, {{ $location->id }})">{{ $location->id }} - {{ $location->name }}</a></strong>
                                                </span>
                                                {{-- Desktop: full --}}
                                                <span class="d-none d-md-inline">
                                                    <strong><a class="link-secondary text-decoration-none cursor-pointer" wire:click="openLocationModal({{ $tenant->id }}, {{ $location->id }})">{{ $location->id }} - {{ $location->name }}</a></strong>
                                                </span>
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                <small class="text-muted">{{ $location->alias ?? '—' }}</small>
                                            </td>
                                            <td class="d-none d-lg-table-cell">{{ $location->city ?? '—' }}</td>
                                            <td class="d-none d-lg-table-cell">{{ $location->state ?? '—' }}</td>
                                            <td>
                                                @if($location->active)
                                                    <span class="badge rounded-pill border border-secondary text-secondary fw-normal">
                                                        <i class="bi bi-check-circle text-success me-1"></i>
                                                        <span class="d-none d-sm-inline">Active</span>
                                                    </span>
                                                @else
                                                    <span class="badge rounded-pill border border-secondary text-secondary fw-normal">
                                                        <i class="bi bi-slash-circle text-danger me-1"></i>
                                                        <span class="d-none d-sm-inline">Inactive</span>
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted mb-0">No locations yet.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- PROVIDERS SECTION --}}
            @if($activeSection === 'providers')
                <div class="card mb-4">
                    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                        <strong>API Provider Settings</strong>
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openProviderSettingsModal({{ $tenant->id }})">
                            <i class="bi bi-plus-lg me-1"></i> Configure Provider
                        </button>
                    </div>
                    <div class="card-body">
                        @php
                            $tenantSettings = $providerSettingsByTenant[$tenant->id] ?? collect();
                        @endphp
                        @if($tenantSettings->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                    <tr>
                                        <th class="sticky-col">Provider</th>
                                        <th>Status</th>
                                        <th class="d-none d-md-table-cell">Configured</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($tenantSettings as $providerKey => $setting)
                                        @php
                                            // Check if this is a location-level integration
                                            $isLocationLevel = isset($setting->is_location_level) && $setting->is_location_level;
                                            $actualProviderKey = $isLocationLevel ? $setting->integration_slug : $providerKey;
                                            $providerName = $availableProviders[$actualProviderKey] ?? $actualProviderKey;
                                            $locationId = $setting->location_id ?? null;
                                            $locationName = $setting->location_name ?? null;
                                        @endphp
                                        <tr>
                                            <td class="sticky-col">
                                                <i class="bi bi-plugin me-2"></i>
                                                {{-- Mobile: truncated --}}
                                                <span class="d-inline-block d-md-none text-truncate" style="max-width: 150px;">
                                                    <strong><a class="link-secondary text-decoration-none cursor-pointer" wire:click="openProviderSettingsModal({{ $tenant->id }}, '{{ $actualProviderKey }}', {{ $locationId ?? 'null' }})">{{ $providerName }}</a></strong>
                                                </span>
                                                {{-- Desktop: full --}}
                                                <span class="d-none d-md-inline">
                                                    <strong><a class="link-secondary text-decoration-none cursor-pointer" wire:click="openProviderSettingsModal({{ $tenant->id }}, '{{ $actualProviderKey }}', {{ $locationId ?? 'null' }})">{{ $providerName }}</a></strong>
                                                </span>
                                                @if($isLocationLevel && $locationName)
                                                    <br class="d-none d-md-inline"><small class="text-muted d-block d-md-inline">
                                                        <i class="bi bi-geo-alt me-1"></i>Location: {{ $locationName }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>
                                                @if(($setting->status ?? null) === 'active')
                                                    <span class="badge rounded-pill border border-secondary text-secondary fw-normal">
                                                        <i class="bi bi-check-circle text-success me-1"></i>
                                                        Active
                                                    </span>
                                                @else
                                                    <span class="badge rounded-pill border border-secondary text-secondary fw-normal">
                                                        <i class="bi bi-slash-circle text-danger me-1"></i>
                                                        Inactive
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="d-none d-md-table-cell">
                                                @if($setting->created_at)
                                                    <small class="text-muted">
                                                        {{ \Carbon\Carbon::parse($setting->created_at)->format('M d, Y') }}
                                                    </small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted mb-0">No provider settings configured yet. Click "Configure Provider" to add API credentials.</p>
                        @endif
                    </div>
                </div>
            @endif

        @else
            {{-- TENANT LIST VIEW --}}
            <!--FILTERS ROW-->
            <div class="row g-2 align-items-center mb-3">
                <div class="col-12 col-sm-6 col-md-4">
                    <input type="text" wire:model.live.debounce.500ms="search" class="form-control"
                           placeholder="Search tenants by name or email..."/>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <select class="form-select" wire:model.live="statusFilter">
                        <option value="all">All Statuses</option>
                        <option value="trial">Trial</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-12 col-md-5 text-start text-md-end">
                    @if(config('app.rai_url'))
                        <a href="{{ config('app.rai_url') }}/tenant/register" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-eye me-1"></i> Review Registration Screen
                    </a>
                    @endif
                </div>
            </div>

            {{-- SESSION MESSAGES AND ERRORS --}}
            <livewire:admin.flash-message  />

            <x-per-page />
            {{-- DATA TABLE --}}
            <div class="card mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-nowrap table-hover">
                        <thead>
                        <tr>
                            <th class="d-none d-md-table-cell" wire:click="sortBy('id')" style="cursor:pointer;">ID</th>
                            <th class="sticky-col" wire:click="sortBy('name')" style="cursor:pointer;">Tenant Name</th>
                            <th wire:click="sortBy('status')" style="cursor:pointer;">Status</th>
                            <th class="d-none d-lg-table-cell">Contact</th>
                            <th>Locations</th>
                            <th class="d-none d-md-table-cell">Users</th>
                            <th class="d-none d-lg-table-cell">Trial</th>
                            <th class="d-none d-xl-table-cell">Subscription</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($tenants as $tenant)
                            @php
                                $statusBadge = match($tenant->status) {
                                    'trial' => ['class' => 'bg-warning-subtle border-warning text-warning', 'icon' => 'bi-clock-history', 'label' => 'Trial'],
                                    'active' => ['class' => 'bg-success-subtle border-success text-success', 'icon' => 'bi-check-circle', 'label' => 'Active'],
                                    'suspended' => ['class' => 'bg-danger-subtle border-danger text-danger', 'icon' => 'bi-x-circle', 'label' => 'Suspended'],
                                    'cancelled' => ['class' => 'bg-secondary-subtle border-secondary text-secondary', 'icon' => 'bi-slash-circle', 'label' => 'Cancelled'],
                                    default => ['class' => 'bg-secondary-subtle border-secondary text-secondary', 'icon' => 'bi-question-circle', 'label' => $tenant->status],
                                };
                            @endphp
                            <tr>
                                <td class="d-none d-md-table-cell">{{ $tenant->id }}</td>
                                <td class="sticky-col">
                                    {{-- Mobile: truncated --}}
                                    <span class="d-inline-block d-md-none text-truncate" style="max-width: 150px;">
                                        <strong><a class="link-secondary text-decoration-none cursor-pointer" wire:click="viewDetails({{ $tenant->id }})">{{ $tenant->name }}</a></strong>
                                    </span>
                                    {{-- Desktop: full --}}
                                    <span class="d-none d-md-inline">
                                        <strong><a class="link-secondary text-decoration-none cursor-pointer" wire:click="viewDetails({{ $tenant->id }})">{{ $tenant->name }}</a></strong>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill fw-normal {{ $statusBadge['class'] }}">
                                        <i class="bi {{ $statusBadge['icon'] }} me-1"></i>
                                        <span class="d-none d-sm-inline">{{ $statusBadge['label'] }}</span>
                                    </span>
                                    @if($tenant->isOnTrial())
                                        <br class="d-none d-md-inline">
                                        <small class="text-muted d-block d-md-inline">
                                            {{ $tenant->trialDaysRemaining() }} days left
                                        </small>
                                    @endif
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <div class="small">
                                        <strong>{{ $tenant->primary_contact_name ?? 'N/A' }}</strong>
                                        <br>
                                        <span class="text-muted">{{ $tenant->primary_contact_email ?? 'N/A' }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill border border-secondary text-secondary fw-normal">
                                        {{ $tenant->locations_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <span class="badge rounded-pill border border-secondary text-secondary fw-normal">
                                        <i class="bi bi-people me-1"></i>
                                        {{ $tenant->users_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    @if($tenant->trial_ends_at)
                                        <small class="text-muted">
                                            Ends: {{ $tenant->trial_ends_at->format('M d, Y') }}
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="d-none d-xl-table-cell">
                                    @if($tenant->subscription)
                                        <div class="small">
                                            <strong>{{ $tenant->subscription->plan_name }}</strong>
                                            <br>
                                            <span class="text-muted">${{ number_format($tenant->subscription->total_monthly_price, 2) }}/mo</span>
                                        </div>
                                    @else
                                        <span class="text-muted">No subscription</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">No tenants found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

            {{-- PAGINATION --}}
            {{ $tenants->links() }}
        @endif
    @endif

    {{-- PENDING APPLICATIONS TAB --}}
    @if($activeTab === 'pending')
        {{-- SESSION MESSAGES AND ERRORS --}}
        <livewire:admin.flash-message  />

        @if($pendingInvitations->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>Pending Tenant Applications ({{ $pendingInvitations->count() }})
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Company Name</th>
                                <th>Submitted</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($pendingInvitations as $invitation)
                                <tr>
                                    <td>{{ $invitation->email }}</td>
                                    <td>{{ trim(($invitation->first_name ?? '') . ' ' . ($invitation->last_name ?? '')) ?: 'N/A' }}</td>
                                    <td>{{ $invitation->response_data['company_name'] ?? 'N/A' }}</td>
                                    <td>{{ $invitation->accepted_at ? $invitation->accepted_at->format('M d, Y g:i A') : 'N/A' }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-primary" wire:click="openReviewInvitationModal({{ $invitation->id }})">
                                            <i class="bi bi-eye me-1"></i> Review
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card mb-4">
                <div class="card-body text-center py-5">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">No Pending Applications</h5>
                    <p class="text-muted">All applications have been reviewed.</p>
                </div>
            </div>
        @endif
    @endif

    {{-- INVITATIONS TAB --}}
    @if($activeTab === 'invitations')
        {{-- SESSION MESSAGES AND ERRORS --}}
        <livewire:admin.flash-message  />

        <div class="row g-2 align-items-center mb-3">
            <div class="col-12 text-start text-md-end">
                <button type="button" class="btn btn-primary btn-sm w-100 w-md-auto" wire:click="openInviteModal">
                    <i class="bi bi-plus-lg me-1"></i> Invite New Tenant
                </button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-envelope me-2"></i>All Tenant Invitations
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Sent</th>
                            <th>Expires</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($allInvitations as $invitation)
                            @php
                                $statusBadge = match($invitation->status) {
                                    'pending' => ['class' => 'bg-warning-subtle text-warning', 'label' => 'Pending'],
                                    'submitted' => ['class' => 'bg-info-subtle text-info', 'label' => 'Submitted'],
                                    'approved' => ['class' => 'bg-success-subtle text-success', 'label' => 'Approved'],
                                    'rejected' => ['class' => 'bg-danger-subtle text-danger', 'label' => 'Rejected'],
                                    default => ['class' => 'bg-secondary-subtle text-secondary', 'label' => $invitation->status],
                                };
                            @endphp
                            <tr>
                                <td>{{ $invitation->email }}</td>
                                <td>{{ trim(($invitation->first_name ?? '') . ' ' . ($invitation->last_name ?? '')) ?: 'N/A' }}</td>
                                <td>
                                    <span class="badge {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
                                </td>
                                <td>{{ $invitation->created_at->format('M d, Y') }}</td>
                                <td>{{ $invitation->expires_at->format('M d, Y') }}</td>
                                <td class="text-end">
                                    @if($invitation->status === 'pending')
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="resendInvitation({{ $invitation->id }})">
                                            <i class="bi bi-arrow-clockwise me-1"></i> Resend
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteInvitation({{ $invitation->id }})" wire:confirm="Are you sure you want to delete this invitation?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @elseif($invitation->status === 'submitted')
                                        <span class="text-muted small">See Pending Applications tab</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No invitations found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif


    {{-- LOCATION MODAL --}}
    @if($showLocationModal && $selectedTenantId)
        @php
            $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($selectedTenantId);
        @endphp
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; right: 0; bottom: 0; overflow-y: auto; z-index: 9999;">
            <div class="modal-dialog" style="z-index: 10000;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $locationId ? 'Edit Location' : 'Add Location' }} - {{ $tenant->name }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeLocationModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Location Name -->
                        @error('locationName')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Location Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" wire:model="locationName" placeholder="Enter location name">
                        </div>

                        <!-- Location Alias -->
                        @error('locationAlias')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Alias <span class="text-muted small">(Optional)</span></label>
                            <input type="text" class="form-control" wire:model="locationAlias" placeholder="Enter alias">
                        </div>

                        <!-- Address -->
                        @error('locationAddress')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Address <span class="text-muted small">(Optional)</span></label>
                            <input type="text" class="form-control" wire:model="locationAddress" placeholder="Enter street address">
                        </div>

                        <!-- City, State, Country -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                @error('locationCity')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                                <label class="form-label">City <span class="text-muted small">(Optional)</span></label>
                                <input type="text" class="form-control" wire:model="locationCity" placeholder="Enter city">
                            </div>
                            <div class="col-md-3 mb-3">
                                @error('locationState')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                                <label class="form-label">State <span class="text-muted small">(Optional)</span></label>
                                <input type="text" class="form-control" wire:model="locationState" placeholder="Enter state">
                            </div>
                            <div class="col-md-3 mb-3">
                                @error('locationCountry')
                                <div class="text-danger">{{ $message }}</div>
                                @enderror
                                <label class="form-label">Country <span class="text-muted small">(Optional)</span></label>
                                <input type="text" class="form-control" wire:model="locationCountry" placeholder="US" value="{{ $locationCountry ?? 'US' }}">
                            </div>
                        </div>

                        <!-- Toast Location Guid -->
                        @error('locationToastLocation')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Toast Location Guid <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" wire:model="locationToastLocation" placeholder="Enter Toast location GUID (e.g., cd2a7d2a-1ca6-4b38-95d9-a83ccebb7e27)">
                            <small class="form-text text-muted">The Toast API location GUID for this location. Required for Toast integration.</small>
                        </div>

                        <!-- Active Status -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="locationActive" wire:model="locationActive">
                                <label class="form-check-label" for="locationActive">
                                    <strong>Active</strong>
                                </label>
                            </div>
                            <small class="form-text text-muted">Inactive locations will not appear in location lists for users.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeLocationModal">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="saveLocation">
                            <i class="bi bi-check-lg me-1"></i> {{ $locationId ? 'Update' : 'Create' }} Location
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- USER MODAL --}}
    @if($showUserModal && $selectedTenantId)
        @php
            $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($selectedTenantId);
            $roles = \Spatie\Permission\Models\Role::where('name', '!=', 'Super Admin')->orderBy('name')->get();
            $locations = $tenant->locations()->withoutGlobalScopes()->orderBy('name')->get();
        @endphp
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; right: 0; bottom: 0; overflow-y: auto; z-index: 9999;">
            <div class="modal-dialog modal-lg" style="z-index: 10000;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $userId ? 'Edit User' : 'Add User' }} - {{ $tenant->name }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeUserModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- User Name -->
                        @error('userName')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" wire:model="userName" placeholder="Enter user name">
                        </div>

                        <!-- User Email -->
                        @error('userEmail')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" wire:model="userEmail" placeholder="Enter user email">
                        </div>

                        <!-- User Status -->
                        @error('userStatus')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="userStatus">
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Disabled">Disabled</option>
                                <option value="Archived">Archived</option>
                            </select>
                        </div>

                        <!-- Roles -->
                        @error('selectedRoles')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Roles</label>
                            <select class="form-select" wire:model="selectedRoles" multiple size="5">
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple roles.</small>
                        </div>

                        <!-- Location Access -->
                        @error('userLocationAccess')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Location Access <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model.live="userLocationAccess">
                                <option value="All">All Locations</option>
                                <option value="Some">Some Locations</option>
                                <option value="None">No Location Access</option>
                            </select>
                        </div>

                        <!-- Selected Locations (if Some) -->
                        @if($userLocationAccess === 'Some')
                            @error('selectedUserLocations')
                            <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="mb-3">
                                <label class="form-label">Select Locations <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="selectedUserLocations" multiple size="5">
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple locations.</small>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeUserModal">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="saveUser">
                            <i class="bi bi-check-lg me-1"></i> {{ $userId ? 'Update' : 'Create' }} User
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- SUBSCRIPTION MODAL --}}
    @if($showSubscriptionModal && $selectedTenantId)
        @php
            $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($selectedTenantId);
            $planConfigs = [
                'starter' => \App\Models\TenantSubscription::getPlanConfig('starter'),
                'professional' => \App\Models\TenantSubscription::getPlanConfig('professional'),
                'enterprise' => \App\Models\TenantSubscription::getPlanConfig('enterprise'),
            ];
        @endphp
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; right: 0; bottom: 0; overflow-y: auto; z-index: 9999;">
            <div class="modal-dialog modal-lg" style="z-index: 10000;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Manage Subscription - {{ $tenant->name }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeSubscriptionModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Subscription Plan -->
                        @error('subscriptionPlan')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Subscription Plan <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model.live="subscriptionPlan">
                                @foreach($planConfigs as $key => $config)
                                    <option value="{{ $key }}">{{ $config['name'] }} - ${{ number_format($config['base_price'], 2) }}/month base + ${{ number_format($config['price_per_location'], 2) }}/location (Max: {{ $config['max_locations'] }})</option>
                                @endforeach
                            </select>
                        </div>

                        @php
                            $selectedPlanConfig = $planConfigs[$subscriptionPlan] ?? $planConfigs['starter'];
                            $calculatedPrice = $selectedPlanConfig['base_price'] + ($subscriptionLocationCount * $selectedPlanConfig['price_per_location']);
                        @endphp

                        <!-- Location Count -->
                        @error('subscriptionLocationCount')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Location Count <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" wire:model.live="subscriptionLocationCount" min="1" max="{{ $selectedPlanConfig['max_locations'] }}">
                            <small class="form-text text-muted">Maximum {{ $selectedPlanConfig['max_locations'] }} locations for {{ $selectedPlanConfig['name'] }} plan.</small>
                        </div>

                        <!-- Price Summary -->
                        <div class="border rounded p-3 bg-light">
                            <strong>Price Summary:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Base Price: ${{ number_format($selectedPlanConfig['base_price'], 2) }}/month</li>
                                <li>Price per Location: ${{ number_format($selectedPlanConfig['price_per_location'], 2) }}/month</li>
                                <li>Location Count: {{ $subscriptionLocationCount }}</li>
                                <li><strong>Total Monthly Price: ${{ number_format($calculatedPrice, 2) }}/month</strong></li>
                            </ul>
                        </div>

                        <!-- Plan Features -->
                        <div class="mb-3">
                            <label class="form-label">Plan Features:</label>
                            <ul class="list-unstyled mb-0">
                                @foreach($selectedPlanConfig['features'] as $feature)
                                    <li><i class="bi bi-check-circle text-success me-2"></i>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeSubscriptionModal">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="saveSubscription">
                            <i class="bi bi-check-lg me-1"></i> Save Subscription
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- TENANT SETTINGS MODAL --}}
    @if($showSettingsModal && $selectedTenantId)
        @php
            $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($selectedTenantId);
        @endphp
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; right: 0; bottom: 0; overflow-y: auto; z-index: 9999;">
            <div class="modal-dialog" style="z-index: 10000;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Edit Tenant Settings - {{ $tenant->name }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeSettingsModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Tenant Name -->
                        @error('tenantName')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Tenant Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" wire:model="tenantName" placeholder="Enter tenant name">
                        </div>

                        <!-- Tenant Status -->
                        @error('tenantStatus')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model.live="tenantStatus">
                                <option value="trial">Trial</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <!-- Trial Ends At (shown only when status is trial) -->
                        @if($tenantStatus === 'trial')
                            @error('tenantTrialEndsAt')
                            <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="mb-3">
                                <label class="form-label">Trial Ends At <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" wire:model="tenantTrialEndsAt">
                                <small class="form-text text-muted">Required when status is "Trial". Users will lose access after this date.</small>
                            </div>
                        @endif

                        <!-- Contact Name -->
                        @error('tenantContactName')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Primary Contact Name <span class="text-muted small">(Optional)</span></label>
                            <input type="text" class="form-control" wire:model="tenantContactName" placeholder="Enter contact name">
                        </div>

                        <!-- Contact Email -->
                        @error('tenantContactEmail')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Primary Contact Email <span class="text-muted small">(Optional)</span></label>
                            <input type="email" class="form-control" wire:model="tenantContactEmail" placeholder="Enter contact email">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeSettingsModal">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="saveTenantSettings">
                            <i class="bi bi-check-lg me-1"></i> Save Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- PROVIDER SETTINGS MODAL --}}
    @if($showProviderSettingsModal && $selectedTenantId)
        @php
            $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($selectedTenantId);
        @endphp
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; right: 0; bottom: 0; overflow-y: auto; z-index: 9999;">
            <div class="modal-dialog modal-lg" style="z-index: 10000;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            @if($selectedProviderName)
                                Configure {{ $availableProviders[$selectedProviderName] ?? $selectedProviderName }} - {{ $tenant->name }}
                            @else
                                Configure API Provider - {{ $tenant->name }}
                            @endif
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeProviderSettingsModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Provider Selection -->
                        @error('selectedProviderName')
                        <div class="text-danger">{{ $message }}</div>
                        @enderror
                        <div class="mb-3">
                            <label class="form-label">Provider <span class="text-danger">*</span></label>
                            @php
                                // When creating new (no selectedProviderName), filter out already configured providers
                                $configuredProviderKeys = [];
                                if (!$selectedProviderName && $selectedTenantId) {
                                    $tenantSettings = $providerSettingsByTenant[$selectedTenantId] ?? collect();
                                    // Convert Collection to array if needed
                                    $configuredProviderKeys = $tenantSettings instanceof \Illuminate\Support\Collection
                                        ? $tenantSettings->keys()->toArray()
                                        : array_keys($tenantSettings);
                                }
                                $filteredProviders = $selectedProviderName
                                    ? $availableProviders
                                    : array_diff_key($availableProviders, array_flip($configuredProviderKeys));
                            @endphp
                            <select class="form-select" wire:model.live="selectedProviderName" @if($selectedProviderName) disabled @endif>
                                <option value="">-- Select Provider --</option>
                                @foreach($filteredProviders as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @if($selectedProviderName)
                                <small class="text-muted">Provider cannot be changed after creation. Delete and recreate to change.</small>
                            @elseif(count($filteredProviders) === 0)
                                <small class="text-muted text-warning">All available providers are already configured for this tenant.</small>
                            @endif
                        </div>

                        @if($selectedProviderName)
                            @php
                                // Check if this is a location-level integration from database
                                $integration = \DB::table('rai_integrations')
                                    ->where('slug', $selectedProviderName)
                                    ->where('is_active', 1)
                                    ->first(['is_location_level']);
                                $isLocationLevel = $integration && ($integration->is_location_level ?? false);

                                // Get field schema for dynamic fields
                                $fieldSchema = \DB::table('rai_integrations')
                                    ->where('slug', $selectedProviderName)
                                    ->value('field_schema');
                                $fields = $fieldSchema ? json_decode($fieldSchema, true) : [];
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
                                        @if($selectedProviderName === 'TOAST_API')
                                            TOAST_BASE_URL, TOAST_CLIENT_ID, TOAST_CLIENT_SECRET
                                        @elseif($selectedProviderName === 'SEVEN_SHIFTS_API')
                                            SEVENSHIFTS_API_BASE_URL, SEVENSHIFTS_API_TOKEN, SEVENSHIFTS_COMPANY_ID, SEVENSHIFTS_PUNCHES_DAYS_BACK
                                        @elseif($selectedProviderName === 'TOAST_SFTP')
                                            SFTP_HOST, SFTP_PORT, SFTP_USERNAME, SFTP_PRIVATE_KEY, SFTP_PASSPHRASE
                                        @elseif($selectedProviderName === 'OPENAPI')
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
                        @if($selectedProviderName)
                            <button type="button" class="btn btn-primary" wire:click="saveProviderSettings">
                                <i class="bi bi-check-lg me-1"></i> Save Provider Settings
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif


    {{-- INVITE MODAL --}}
    @if($showInviteModal)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Invite New Tenant</h5>
                        <button type="button" class="btn-close" wire:click="closeInviteModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" wire:model="invite_email" placeholder="tenant@example.com">
                            @error('invite_email') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" wire:model="invite_first_name" placeholder="John">
                                @error('invite_first_name') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" wire:model="invite_last_name" placeholder="Doe">
                                @error('invite_last_name') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expires In (Days) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" wire:model="invite_days" min="1" max="30" value="7">
                            @error('invite_days') <div class="text-danger small">{{ $message }}</div> @enderror
                            <small class="text-muted">Invitation will expire after this many days.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeInviteModal">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="sendInvitation">
                            <i class="bi bi-send me-1"></i> Send Invitation
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- REVIEW INVITATION MODAL --}}
    @if($showReviewInvitationModal && $reviewData)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Review Tenant Application</h5>
                        <button type="button" class="btn-close" wire:click="closeReviewInvitationModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Email:</strong> {{ $reviewData['email'] ?? 'N/A' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Contact Name:</strong> {{ $reviewData['contact_name'] ?? 'N/A' }}
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <strong>Company Name:</strong> {{ $reviewData['company_name'] ?? 'N/A' }}
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Selected Plan:</strong> {{ ucfirst($reviewData['selected_plan'] ?? 'N/A') }}
                            </div>
                            <div class="col-md-6">
                                <strong>Location Count:</strong> {{ $reviewData['location_count'] ?? 'N/A' }}
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Monthly Price:</strong> ${{ number_format($reviewData['total_monthly_price'] ?? 0, 2) }}
                            </div>
                            <div class="col-md-6">
                                <strong>Trial Ends:</strong> {{ isset($reviewData['trial_ends_at']) ? \Carbon\Carbon::parse($reviewData['trial_ends_at'])->format('M d, Y') : 'N/A' }}
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <strong>Note:</strong> Approving this application will create the tenant account, user account, and subscription. A welcome email will be sent to the user.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeReviewInvitationModal">Cancel</button>
                        <button type="button" class="btn btn-danger" wire:click="rejectInvitation" wire:confirm="⚠️ WARNING: Are you sure you want to REJECT this application? This action cannot be undone and the applicant will not receive an account. Type 'REJECT' to confirm.">
                            <i class="bi bi-x-circle me-1"></i> Reject
                        </button>
                        <button type="button" class="btn btn-success" wire:click="approveInvitation" wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                <i class="bi bi-check-circle me-1"></i> Approve & Create Account
                            </span>
                            <span wire:loading>
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Processing...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@assets
<style>
    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 2;
        background-color: var(--bs-body-bg, #fff);
        box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
    }
    
    /* Hide scrollbar but keep scroll functionality */
    .tabs-no-scrollbar {
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE and Edge */
    }
    
    .tabs-no-scrollbar::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
    }
    
    /* Mobile responsive adjustments */
    @media (max-width: 767.98px) {
        .sticky-col {
            max-width: 150px;
            min-width: 120px;
        }
        
        /* Ensure tables scroll properly on mobile */
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }
        
        /* Better spacing on mobile */
        .card-body {
            padding: 1rem;
        }
    }
</style>
@endassets

