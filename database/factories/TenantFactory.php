<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'primary_contact_name' => fake()->name(),
            'primary_contact_email' => fake()->unique()->safeEmail(),
            'status' => fake()->randomElement(['trial', 'active', 'suspended', 'cancelled']),
            'trial_ends_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'subscription_started_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'settings' => json_encode([
                'timezone' => 'America/New_York',
                'currency' => 'USD',
            ]),
        ];
    }

    /**
     * Indicate that the tenant is on trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'subscription_started_at' => null,
        ]);
    }

    /**
     * Indicate that the tenant has an active subscription.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'trial_ends_at' => null,
            'subscription_started_at' => now()->subMonths(3),
        ]);
    }

    /**
     * Indicate that the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Indicate that the tenant is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}

