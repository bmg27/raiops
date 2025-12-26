<div>
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-tags me-2"></i>Subscription Plans
            </h4>
            <p class="text-muted mb-0">Manage subscription plan tiers and pricing</p>
        </div>
        <div>
            @canRaiOps('billing.edit')
                <button 
                    type="button" 
                    class="btn btn-primary btn-sm"
                    wire:click="openModal"
                >
                    <i class="bi bi-plus-lg me-1"></i> Add Plan
                </button>
            @endcanRaiOps
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input 
                            type="text" 
                            class="form-control" 
                            placeholder="Plan name or code..."
                            wire:model.live.debounce.300ms="search"
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select class="form-select" wire:model.live="statusFilter">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Session Messages --}}
    <livewire:admin.flash-message />

    {{-- Plans Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Order</th>
                            <th>Plan</th>
                            <th class="text-end">Monthly</th>
                            <th class="text-end">Annual</th>
                            <th class="text-center">Users</th>
                            <th class="text-center">Locations</th>
                            <th class="text-center">Subscribers</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr wire:key="plan-{{ $plan->id }}">
                                <td>
                                    <span class="badge bg-secondary">{{ $plan->sort_order }}</span>
                                </td>
                                <td>
                                    <div class="fw-medium">{{ $plan->name }}</div>
                                    <small class="text-muted">{{ $plan->code }}</small>
                                    @if($plan->features && count($plan->features) > 0)
                                        <div class="mt-1">
                                            @foreach(array_slice($plan->features, 0, 2) as $feature)
                                                <span class="badge bg-secondary-subtle text-body small">{{ $feature }}</span>
                                            @endforeach
                                            @if(count($plan->features) > 2)
                                                <span class="badge bg-secondary-subtle text-body small">+{{ count($plan->features) - 2 }} more</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <strong>${{ number_format($plan->monthly_price, 2) }}</strong>
                                </td>
                                <td class="text-end">
                                    @if($plan->annual_price)
                                        <strong>${{ number_format($plan->annual_price, 2) }}</strong>
                                        @if($plan->getAnnualSavingsPercent() > 0)
                                            <br>
                                            <small class="text-success">Save {{ $plan->getAnnualSavingsPercent() }}%</small>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    {{ $plan->getUserLimitDisplay() }}
                                </td>
                                <td class="text-center">
                                    {{ $plan->getLocationLimitDisplay() }}
                                </td>
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
                                <td class="text-end">
                                    @canRaiOps('billing.edit')
                                        <button
                                            wire:click="toggleActive({{ $plan->id }})"
                                            class="btn btn-sm btn-outline-{{ $plan->is_active ? 'warning' : 'success' }}"
                                            title="{{ $plan->is_active ? 'Deactivate' : 'Activate' }}"
                                        >
                                            <i class="bi bi-{{ $plan->is_active ? 'pause' : 'play' }}-fill"></i>
                                        </button>
                                        <button
                                            wire:click="openModal({{ $plan->id }})"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @if($plan->tenant_billings_count === 0)
                                            <button
                                                wire:click="delete({{ $plan->id }})"
                                                wire:confirm="Are you sure you want to delete this plan?"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    @endcanRaiOps
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No subscription plans found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($plans->hasPages())
            <div class="card-footer">
                {{ $plans->links() }}
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
                            <i class="bi bi-tags me-2"></i>
                            {{ $editingPlanId ? 'Edit' : 'Create' }} Subscription Plan
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="save">
                            <div class="row g-3">
                                {{-- Name --}}
                                <div class="col-md-6">
                                    <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        class="form-control @error('formData.name') is-invalid @enderror"
                                        wire:model="formData.name"
                                        placeholder="e.g., Professional"
                                    >
                                    @error('formData.name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Code --}}
                                <div class="col-md-6">
                                    <label class="form-label">Plan Code <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        class="form-control @error('formData.code') is-invalid @enderror"
                                        wire:model="formData.code"
                                        placeholder="e.g., professional"
                                    >
                                    @error('formData.code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Unique identifier (lowercase, no spaces)</small>
                                </div>

                                {{-- Monthly Price --}}
                                <div class="col-md-6">
                                    <label class="form-label">Monthly Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input 
                                            type="number" 
                                            step="0.01"
                                            class="form-control @error('formData.monthly_price') is-invalid @enderror"
                                            wire:model="formData.monthly_price"
                                            placeholder="0.00"
                                        >
                                    </div>
                                    @error('formData.monthly_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Annual Price --}}
                                <div class="col-md-6">
                                    <label class="form-label">Annual Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input 
                                            type="number" 
                                            step="0.01"
                                            class="form-control @error('formData.annual_price') is-invalid @enderror"
                                            wire:model="formData.annual_price"
                                            placeholder="0.00"
                                        >
                                    </div>
                                    @error('formData.annual_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Leave empty if annual billing not available</small>
                                </div>

                                {{-- Max Users --}}
                                <div class="col-md-4">
                                    <label class="form-label">Max Users</label>
                                    <input 
                                        type="number" 
                                        class="form-control @error('formData.max_users') is-invalid @enderror"
                                        wire:model="formData.max_users"
                                        placeholder="Leave empty for unlimited"
                                    >
                                    @error('formData.max_users')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Max Locations --}}
                                <div class="col-md-4">
                                    <label class="form-label">Max Locations</label>
                                    <input 
                                        type="number" 
                                        class="form-control @error('formData.max_locations') is-invalid @enderror"
                                        wire:model="formData.max_locations"
                                        placeholder="Leave empty for unlimited"
                                    >
                                    @error('formData.max_locations')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Sort Order --}}
                                <div class="col-md-4">
                                    <label class="form-label">Sort Order</label>
                                    <input 
                                        type="number" 
                                        class="form-control @error('formData.sort_order') is-invalid @enderror"
                                        wire:model="formData.sort_order"
                                        placeholder="0"
                                    >
                                    @error('formData.sort_order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Active Status --}}
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-4">
                                        <input 
                                            class="form-check-input" 
                                            type="checkbox" 
                                            wire:model="formData.is_active"
                                            id="is_active"
                                        >
                                        <label class="form-check-label" for="is_active">
                                            Plan is active
                                        </label>
                                    </div>
                                </div>

                                {{-- Features --}}
                                <div class="col-12">
                                    <label class="form-label">Features</label>
                                    @foreach($features as $index => $feature)
                                        <div class="input-group mb-2">
                                            <input 
                                                type="text" 
                                                class="form-control"
                                                wire:model="features.{{ $index }}"
                                                placeholder="Feature name"
                                            >
                                            <button 
                                                type="button" 
                                                class="btn btn-outline-danger"
                                                wire:click="removeFeature({{ $index }})"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                    <button 
                                        type="button" 
                                        class="btn btn-outline-secondary btn-sm"
                                        wire:click="addFeature"
                                    >
                                        <i class="bi bi-plus me-1"></i> Add Feature
                                    </button>
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

