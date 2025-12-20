<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'code',
        'monthly_price',
        'annual_price',
        'max_users',
        'max_locations',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'max_users' => 'integer',
        'max_locations' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relationship: Tenants on this plan
     */
    public function tenantBillings(): HasMany
    {
        return $this->hasMany(TenantBilling::class, 'subscription_plan_id');
    }

    /**
     * Check if plan has unlimited users
     */
    public function hasUnlimitedUsers(): bool
    {
        return $this->max_users === null;
    }

    /**
     * Check if plan has unlimited locations
     */
    public function hasUnlimitedLocations(): bool
    {
        return $this->max_locations === null;
    }

    /**
     * Check if plan has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Get annual savings amount
     */
    public function getAnnualSavings(): float
    {
        if (!$this->annual_price) {
            return 0;
        }

        $monthlyTotal = (float) $this->monthly_price * 12;
        return $monthlyTotal - (float) $this->annual_price;
    }

    /**
     * Get annual savings percentage
     */
    public function getAnnualSavingsPercent(): int
    {
        if (!$this->annual_price || !$this->monthly_price) {
            return 0;
        }

        $monthlyTotal = (float) $this->monthly_price * 12;
        $savings = $monthlyTotal - (float) $this->annual_price;

        return (int) round(($savings / $monthlyTotal) * 100);
    }

    /**
     * Get formatted user limit
     */
    public function getUserLimitDisplay(): string
    {
        return $this->hasUnlimitedUsers() ? 'Unlimited' : (string) $this->max_users;
    }

    /**
     * Get formatted location limit
     */
    public function getLocationLimitDisplay(): string
    {
        return $this->hasUnlimitedLocations() ? 'Unlimited' : (string) $this->max_locations;
    }

    /**
     * Scope: Active plans only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}

