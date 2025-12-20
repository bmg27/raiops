<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    use HasFactory;

    protected $table = 'tenant_subscriptions';

    protected $fillable = [
        'tenant_id',
        'plan_name',
        'base_price',
        'location_count',
        'price_per_location',
        'total_monthly_price',
        'billing_cycle',
        'next_billing_date',
        'stripe_subscription_id',
        'stripe_customer_id',
        'status',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'price_per_location' => 'decimal:2',
        'total_monthly_price' => 'decimal:2',
        'location_count' => 'integer',
        'next_billing_date' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function calculateTotal(): float
    {
        return $this->base_price + ($this->location_count * $this->price_per_location);
    }

    public function updateTotal(): void
    {
        $this->total_monthly_price = $this->calculateTotal();
        $this->save();
    }

    public static function getPlanConfig(string $planName): array
    {
        $plans = [
            'starter' => [
                'name' => 'Starter',
                'base_price' => 99.00,
                'price_per_location' => 20.00,
                'max_locations' => 3,
                'features' => [
                    'Basic reporting',
                    'Email support',
                    'Standard integrations',
                ],
            ],
            'professional' => [
                'name' => 'Professional',
                'base_price' => 199.00,
                'price_per_location' => 15.00,
                'max_locations' => 10,
                'features' => [
                    'Advanced analytics',
                    'API access',
                    'Priority support',
                    'Custom reports',
                ],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'base_price' => 499.00,
                'price_per_location' => 10.00,
                'max_locations' => 999,
                'features' => [
                    'Unlimited locations',
                    'Custom integrations',
                    'Dedicated support',
                    'SLA guarantees',
                    'White label options',
                ],
            ],
        ];

        return $plans[$planName] ?? $plans['starter'];
    }
}

