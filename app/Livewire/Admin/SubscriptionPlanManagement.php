<?php

namespace App\Livewire\Admin;

use App\Models\SubscriptionPlan;
use App\Models\AuditLog;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * SubscriptionPlanManagement Component
 * 
 * RAIOPS Command Central's subscription plan management.
 * Create, edit, and manage subscription plans.
 */
class SubscriptionPlanManagement extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Filters
    public string $search = '';
    public string $statusFilter = 'all';
    public int $perPage = 25;

    // Modal state
    public bool $showModal = false;
    public ?int $editingPlanId = null;
    public array $formData = [];
    public array $features = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Open modal to create/edit plan
     */
    public function openModal(?int $planId = null): void
    {
        $this->editingPlanId = $planId;
        $this->showModal = true;

        if ($planId) {
            $plan = SubscriptionPlan::findOrFail($planId);
            $this->formData = [
                'name' => $plan->name,
                'code' => $plan->code,
                'monthly_price' => $plan->monthly_price,
                'annual_price' => $plan->annual_price,
                'max_users' => $plan->max_users,
                'max_locations' => $plan->max_locations,
                'is_active' => $plan->is_active,
                'sort_order' => $plan->sort_order,
            ];
            $this->features = $plan->features ?? [];
        } else {
            $this->resetForm();
        }
    }

    /**
     * Close modal
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingPlanId = null;
        $this->resetForm();
    }

    /**
     * Reset form data
     */
    public function resetForm(): void
    {
        $this->formData = [
            'name' => '',
            'code' => '',
            'monthly_price' => '',
            'annual_price' => '',
            'max_users' => '',
            'max_locations' => '',
            'is_active' => true,
            'sort_order' => 0,
        ];
        $this->features = [];
    }

    /**
     * Add feature
     */
    public function addFeature(): void
    {
        $this->features[] = '';
    }

    /**
     * Remove feature
     */
    public function removeFeature(int $index): void
    {
        unset($this->features[$index]);
        $this->features = array_values($this->features);
    }

    /**
     * Save plan
     */
    public function save(): void
    {
        $validated = $this->validate([
            'formData.name' => 'required|string|max:255',
            'formData.code' => 'required|string|max:50|unique:subscription_plans,code,' . ($this->editingPlanId ?? 'NULL'),
            'formData.monthly_price' => 'required|numeric|min:0',
            'formData.annual_price' => 'nullable|numeric|min:0',
            'formData.max_users' => 'nullable|integer|min:0',
            'formData.max_locations' => 'nullable|integer|min:0',
            'formData.is_active' => 'boolean',
            'formData.sort_order' => 'integer|min:0',
        ]);

        try {
            $data = $validated['formData'];
            $data['features'] = array_filter($this->features); // Remove empty features

            if ($this->editingPlanId) {
                $plan = SubscriptionPlan::findOrFail($this->editingPlanId);
                $oldValues = $plan->toArray();
                $plan->update($data);
                $newValues = $plan->fresh()->toArray();

                AuditLog::log('updated', 'SubscriptionPlan', $plan->id, $oldValues, $newValues);
                session()->flash('success', 'Subscription plan updated successfully.');
            } else {
                $plan = SubscriptionPlan::create($data);
                AuditLog::log('created', 'SubscriptionPlan', $plan->id, null, $plan->toArray());
                session()->flash('success', 'Subscription plan created successfully.');
            }

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to save plan: ' . $e->getMessage());
        }
    }

    /**
     * Delete plan
     */
    public function delete(int $planId): void
    {
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            
            // Check if plan is in use
            if ($plan->tenantBillings()->count() > 0) {
                session()->flash('error', 'Cannot delete plan: It is currently assigned to ' . $plan->tenantBillings()->count() . ' tenant(s).');
                return;
            }

            $oldValues = $plan->toArray();
            $plan->delete();

            AuditLog::log('deleted', 'SubscriptionPlan', $planId, $oldValues, null);
            session()->flash('success', 'Subscription plan deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete plan: ' . $e->getMessage());
        }
    }

    /**
     * Toggle plan active status
     */
    public function toggleActive(int $planId): void
    {
        try {
            $plan = SubscriptionPlan::findOrFail($planId);
            $plan->update(['is_active' => !$plan->is_active]);
            
            AuditLog::log('updated', 'SubscriptionPlan', $planId, ['is_active' => !$plan->is_active], ['is_active' => $plan->is_active]);
            session()->flash('success', 'Plan status updated.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update status: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = SubscriptionPlan::withCount('tenantBillings')
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== 'all', function ($q) {
                $q->where('is_active', $this->statusFilter === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('name');

        $plans = $query->paginate($this->perPage);

        return view('livewire.admin.subscription-plan-management', [
            'plans' => $plans,
        ])->layout('layouts.rai');
    }
}

