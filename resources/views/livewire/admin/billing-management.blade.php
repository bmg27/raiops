<div>
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-credit-card me-2"></i>Billing Management
            </h4>
            <p class="text-muted mb-0">Manage tenant subscriptions, billing cycles, and payments</p>
        </div>
        <div>
            @canRaiOps('billing.edit')
                <button 
                    type="button" 
                    class="btn btn-primary btn-sm"
                    wire:click="openModal"
                >
                    <i class="bi bi-plus-lg me-1"></i> Add Billing Record
                </button>
            @endcanRaiOps
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 text-white-50">Total MRR</h6>
                            <h3 class="mb-0">${{ number_format($stats['total_mrr'], 2) }}</h3>
                        </div>
                        <i class="bi bi-currency-dollar fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 text-muted">Total Billings</h6>
                            <h3 class="mb-0">{{ number_format($stats['total_billings']) }}</h3>
                        </div>
                        <i class="bi bi-receipt fs-1 text-muted opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 text-white-50">Past Due</h6>
                            <h3 class="mb-0">{{ number_format($stats['past_due']) }}</h3>
                        </div>
                        <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0 text-dark opacity-75">Upcoming (7d)</h6>
                            <h3 class="mb-0">{{ number_format($stats['upcoming_7_days']) }}</h3>
                        </div>
                        <i class="bi bi-clock fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input 
                            type="text" 
                            class="form-control" 
                            placeholder="Tenant, email, Stripe ID..."
                            wire:model.live.debounce.300ms="search"
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Plan</label>
                    <select class="form-select" wire:model.live="planFilter">
                        <option value="all">All Plans</option>
                        @foreach($this->plans as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select class="form-select" wire:model.live="statusFilter">
                        <option value="all">All Status</option>
                        <option value="past_due">Past Due</option>
                        <option value="upcoming">Upcoming (7d)</option>
                        <option value="current">Current</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Billing Cycle</label>
                    <select class="form-select" wire:model.live="billingCycleFilter">
                        <option value="all">All Cycles</option>
                        <option value="monthly">Monthly</option>
                        <option value="annual">Annual</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Session Messages --}}
    <livewire:admin.flash-message />

    {{-- Billing Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tenant</th>
                            <th>Plan</th>
                            <th class="text-end">MRR</th>
                            <th>Billing Cycle</th>
                            <th>Next Billing</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($billings as $billing)
                            <tr wire:key="billing-{{ $billing->id }}">
                                <td>
                                    <div class="fw-medium">{{ $billing->tenant?->name ?? 'N/A' }}</div>
                                    @if($billing->billing_email)
                                        <small class="text-muted">{{ $billing->billing_email }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($billing->subscriptionPlan)
                                        <span class="badge bg-light text-dark">
                                            {{ $billing->subscriptionPlan->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <strong>${{ number_format($billing->mrr, 2) }}</strong>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $billing->billing_cycle === 'annual' ? 'success' : 'info' }}">
                                        {{ ucfirst($billing->billing_cycle) }}
                                    </span>
                                </td>
                                <td>
                                    @if($billing->next_billing_date)
                                        <div>{{ $billing->next_billing_date->format('M d, Y') }}</div>
                                        @if($billing->isPastDue())
                                            <small class="text-danger">
                                                {{ abs($billing->daysUntilBilling()) }} days overdue
                                            </small>
                                        @else
                                            <small class="text-muted">
                                                {{ $billing->daysUntilBilling() }} days
                                            </small>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($billing->payment_method)
                                        <span class="badge bg-secondary">{{ ucfirst($billing->payment_method) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($billing->isPastDue())
                                        <span class="badge bg-danger">Past Due</span>
                                    @elseif($billing->next_billing_date && $billing->next_billing_date->between(now(), now()->addDays(7)))
                                        <span class="badge bg-warning text-dark">Upcoming</span>
                                    @else
                                        <span class="badge bg-success">Current</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @canRaiOps('billing.edit')
                                        <button
                                            wire:click="openModal({{ $billing->id }})"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    @endcanRaiOps
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No billing records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($billings->hasPages())
            <div class="card-footer">
                {{ $billings->links() }}
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-credit-card me-2"></i>
                            {{ $editingBillingId ? 'Edit' : 'Create' }} Billing Record
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="save">
                            <div class="row g-3">
                                {{-- Tenant --}}
                                <div class="col-md-6">
                                    <label class="form-label">Tenant <span class="text-danger">*</span></label>
                                    <select 
                                        class="form-select @error('formData.tenant_master_id') is-invalid @enderror"
                                        wire:model="formData.tenant_master_id"
                                        @if($editingBillingId) disabled @endif
                                    >
                                        <option value="">Select Tenant</option>
                                        @foreach($this->tenants as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('formData.tenant_master_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Subscription Plan --}}
                                <div class="col-md-6">
                                    <label class="form-label">Subscription Plan</label>
                                    <select 
                                        class="form-select @error('formData.subscription_plan_id') is-invalid @enderror"
                                        wire:model.live="formData.subscription_plan_id"
                                    >
                                        <option value="">No Plan</option>
                                        @foreach($this->plans as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('formData.subscription_plan_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- MRR --}}
                                <div class="col-md-4">
                                    <label class="form-label">Monthly Recurring Revenue (MRR) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input 
                                            type="number" 
                                            step="0.01"
                                            class="form-control @error('formData.mrr') is-invalid @enderror"
                                            wire:model="formData.mrr"
                                            placeholder="0.00"
                                        >
                                    </div>
                                    @error('formData.mrr')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Billing Cycle --}}
                                <div class="col-md-4">
                                    <label class="form-label">Billing Cycle <span class="text-danger">*</span></label>
                                    <select 
                                        class="form-select @error('formData.billing_cycle') is-invalid @enderror"
                                        wire:model.live="formData.billing_cycle"
                                    >
                                        <option value="monthly">Monthly</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                    @error('formData.billing_cycle')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Next Billing Date --}}
                                <div class="col-md-4">
                                    <label class="form-label">Next Billing Date</label>
                                    <input 
                                        type="date" 
                                        class="form-control @error('formData.next_billing_date') is-invalid @enderror"
                                        wire:model="formData.next_billing_date"
                                    >
                                    @error('formData.next_billing_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Billing Email --}}
                                <div class="col-md-6">
                                    <label class="form-label">Billing Email</label>
                                    <input 
                                        type="email" 
                                        class="form-control @error('formData.billing_email') is-invalid @enderror"
                                        wire:model="formData.billing_email"
                                        placeholder="billing@example.com"
                                    >
                                    @error('formData.billing_email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Payment Method --}}
                                <div class="col-md-6">
                                    <label class="form-label">Payment Method</label>
                                    <select 
                                        class="form-select @error('formData.payment_method') is-invalid @enderror"
                                        wire:model="formData.payment_method"
                                    >
                                        <option value="">Select Method</option>
                                        <option value="stripe">Stripe</option>
                                        <option value="invoice">Invoice</option>
                                        <option value="ach">ACH</option>
                                        <option value="wire">Wire Transfer</option>
                                        <option value="check">Check</option>
                                    </select>
                                    @error('formData.payment_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Stripe Customer ID --}}
                                <div class="col-md-6">
                                    <label class="form-label">Stripe Customer ID</label>
                                    <input 
                                        type="text" 
                                        class="form-control @error('formData.stripe_customer_id') is-invalid @enderror"
                                        wire:model="formData.stripe_customer_id"
                                        placeholder="cus_..."
                                    >
                                    @error('formData.stripe_customer_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Stripe Subscription ID --}}
                                <div class="col-md-6">
                                    <label class="form-label">Stripe Subscription ID</label>
                                    <input 
                                        type="text" 
                                        class="form-control @error('formData.stripe_subscription_id') is-invalid @enderror"
                                        wire:model="formData.stripe_subscription_id"
                                        placeholder="sub_..."
                                    >
                                    @error('formData.stripe_subscription_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Notes --}}
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea 
                                        class="form-control @error('formData.notes') is-invalid @enderror"
                                        wire:model="formData.notes"
                                        rows="3"
                                        placeholder="Additional notes..."
                                    ></textarea>
                                    @error('formData.notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeModal">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="save">
                            <i class="bi bi-check-lg me-1"></i> Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

