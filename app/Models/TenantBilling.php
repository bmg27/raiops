<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBilling extends Model
{
    protected $table = 'tenant_billing';

    protected $fillable = [
        'tenant_master_id',
        'subscription_plan_id',
        'mrr',
        'billing_email',
        'billing_cycle',
        'next_billing_date',
        'payment_method',
        'stripe_customer_id',
        'stripe_subscription_id',
        'notes',
    ];

    protected $casts = [
        'mrr' => 'decimal:2',
        'next_billing_date' => 'date',
    ];

    /**
     * Relationship: Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantMaster::class, 'tenant_master_id');
    }

    /**
     * Relationship: Subscription Plan
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Check if billing is past due
     */
    public function isPastDue(): bool
    {
        if (!$this->next_billing_date) {
            return false;
        }

        return $this->next_billing_date->isPast();
    }

    /**
     * Get days until next billing
     */
    public function daysUntilBilling(): ?int
    {
        if (!$this->next_billing_date) {
            return null;
        }

        return now()->diffInDays($this->next_billing_date, false);
    }

    /**
     * Calculate annual revenue from this tenant
     */
    public function getAnnualRevenue(): float
    {
        return (float) $this->mrr * 12;
    }
}

