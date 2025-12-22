<?php

namespace App\Livewire\Admin;

use App\Models\TenantBilling;
use App\Models\TenantMaster;
use App\Models\SubscriptionPlan;
use App\Models\AuditLog;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * BillingManagement Component
 * 
 * RAINBO Command Central's billing management interface.
 * Manage tenant billing, subscriptions, and payment tracking.
 */
class BillingManagement extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Filters
    public string $search = '';
    public string $planFilter = 'all';
    public string $statusFilter = 'all';
    public string $billingCycleFilter = 'all';
    public int $perPage = 25;

    // Modal state
    public bool $showModal = false;
    public ?int $editingBillingId = null;
    public array $formData = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'planFilter' => ['except' => 'all'],
        'statusFilter' => ['except' => 'all'],
        'billingCycleFilter' => ['except' => 'all'],
    ];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPlanFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBillingCycleFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Open modal to create/edit billing
     */
    public function openModal(?int $billingId = null): void
    {
        $this->editingBillingId = $billingId;
        $this->showModal = true;

        if ($billingId) {
            $billing = TenantBilling::with(['tenant', 'subscriptionPlan'])->findOrFail($billingId);
            $this->formData = [
                'tenant_master_id' => $billing->tenant_master_id,
                'subscription_plan_id' => $billing->subscription_plan_id,
                'mrr' => $billing->mrr,
                'billing_email' => $billing->billing_email,
                'billing_cycle' => $billing->billing_cycle,
                'next_billing_date' => $billing->next_billing_date?->format('Y-m-d'),
                'payment_method' => $billing->payment_method,
                'stripe_customer_id' => $billing->stripe_customer_id,
                'stripe_subscription_id' => $billing->stripe_subscription_id,
                'notes' => $billing->notes,
            ];
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
        $this->editingBillingId = null;
        $this->resetForm();
    }

    /**
     * Reset form data
     */
    public function resetForm(): void
    {
        $this->formData = [
            'tenant_master_id' => '',
            'subscription_plan_id' => '',
            'mrr' => '',
            'billing_email' => '',
            'billing_cycle' => 'monthly',
            'next_billing_date' => '',
            'payment_method' => '',
            'stripe_customer_id' => '',
            'stripe_subscription_id' => '',
            'notes' => '',
        ];
    }

    /**
     * Save billing record
     */
    public function save(): void
    {
        $validated = $this->validate([
            'formData.tenant_master_id' => 'required|exists:tenant_master,id',
            'formData.subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'formData.mrr' => 'required|numeric|min:0',
            'formData.billing_email' => 'nullable|email',
            'formData.billing_cycle' => 'required|in:monthly,annual',
            'formData.next_billing_date' => 'nullable|date',
            'formData.payment_method' => 'nullable|string|max:50',
            'formData.stripe_customer_id' => 'nullable|string|max:255',
            'formData.stripe_subscription_id' => 'nullable|string|max:255',
            'formData.notes' => 'nullable|string',
        ]);

        try {
            $data = $validated['formData'];
            
            if ($this->editingBillingId) {
                $billing = TenantBilling::findOrFail($this->editingBillingId);
                $oldValues = $billing->toArray();
                $billing->update($data);
                $newValues = $billing->fresh()->toArray();

                AuditLog::log('updated', 'TenantBilling', $billing->id, $oldValues, $newValues);
                session()->flash('success', 'Billing record updated successfully.');
            } else {
                $billing = TenantBilling::create($data);
                AuditLog::log('created', 'TenantBilling', $billing->id, null, $billing->toArray());
                session()->flash('success', 'Billing record created successfully.');
            }

            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to save billing: ' . $e->getMessage());
        }
    }

    /**
     * Delete billing record
     */
    public function delete(int $billingId): void
    {
        try {
            $billing = TenantBilling::findOrFail($billingId);
            $oldValues = $billing->toArray();
            $billing->delete();

            AuditLog::log('deleted', 'TenantBilling', $billingId, $oldValues, null);
            session()->flash('success', 'Billing record deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete billing: ' . $e->getMessage());
        }
    }

    /**
     * Get available tenants for dropdown
     */
    public function getTenantsProperty()
    {
        return TenantMaster::where('status', '!=', 'cancelled')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get available subscription plans
     */
    public function getPlansProperty()
    {
        return SubscriptionPlan::active()
            ->ordered()
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Update MRR when plan is selected
     */
    public function updatedFormDataSubscriptionPlanId($planId): void
    {
        if ($planId && isset($this->formData['billing_cycle'])) {
            $plan = SubscriptionPlan::find($planId);
            if ($plan) {
                $this->formData['mrr'] = $this->formData['billing_cycle'] === 'annual' && $plan->annual_price
                    ? $plan->annual_price / 12
                    : $plan->monthly_price;
            }
        }
    }

    /**
     * Update MRR when billing cycle changes
     */
    public function updatedFormDataBillingCycle($cycle): void
    {
        if ($cycle && isset($this->formData['subscription_plan_id'])) {
            $plan = SubscriptionPlan::find($this->formData['subscription_plan_id']);
            if ($plan) {
                $this->formData['mrr'] = $cycle === 'annual' && $plan->annual_price
                    ? $plan->annual_price / 12
                    : $plan->monthly_price;
            }
        }
    }

    public function render()
    {
        $query = TenantBilling::with(['tenant.rdsInstance', 'subscriptionPlan'])
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->whereHas('tenant', function ($tq) {
                        $tq->where('name', 'like', "%{$this->search}%");
                    })
                    ->orWhere('billing_email', 'like', "%{$this->search}%")
                    ->orWhere('stripe_customer_id', 'like', "%{$this->search}%");
                });
            })
            ->when($this->planFilter !== 'all', function ($q) {
                $q->where('subscription_plan_id', $this->planFilter);
            })
            ->when($this->statusFilter !== 'all', function ($q) {
                if ($this->statusFilter === 'past_due') {
                    $q->where('next_billing_date', '<', now());
                } elseif ($this->statusFilter === 'upcoming') {
                    $q->whereBetween('next_billing_date', [now(), now()->addDays(7)]);
                } elseif ($this->statusFilter === 'current') {
                    $q->where('next_billing_date', '>=', now()->addDays(7));
                }
            })
            ->when($this->billingCycleFilter !== 'all', function ($q) {
                $q->where('billing_cycle', $this->billingCycleFilter);
            })
            ->orderBy('next_billing_date', 'asc');

        $billings = $query->paginate($this->perPage);

        // Stats
        $stats = [
            'total_mrr' => TenantBilling::sum('mrr'),
            'total_billings' => TenantBilling::count(),
            'past_due' => TenantBilling::where('next_billing_date', '<', now())->count(),
            'upcoming_7_days' => TenantBilling::whereBetween('next_billing_date', [now(), now()->addDays(7)])->count(),
        ];

        return view('livewire.admin.billing-management', [
            'billings' => $billings,
            'stats' => $stats,
        ])->layout('layouts.rai');
    }
}

