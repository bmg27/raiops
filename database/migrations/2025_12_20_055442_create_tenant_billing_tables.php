<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Subscription plans table
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->decimal('monthly_price', 10, 2);
            $table->decimal('annual_price', 10, 2)->nullable();
            $table->integer('max_users')->nullable(); // NULL = unlimited
            $table->integer('max_locations')->nullable();
            $table->json('features')->nullable(); // Feature flags for this plan
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Tenant billing table
        Schema::create('tenant_billing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_master_id');
            $table->unsignedBigInteger('subscription_plan_id')->nullable();
            $table->decimal('mrr', 10, 2)->default(0.00); // Monthly Recurring Revenue
            $table->string('billing_email')->nullable();
            $table->enum('billing_cycle', ['monthly', 'annual'])->default('monthly');
            $table->date('next_billing_date')->nullable();
            $table->string('payment_method', 50)->nullable(); // 'stripe', 'invoice', etc.
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tenant_master_id');
            $table->index('next_billing_date');
        });

        // Seed some default subscription plans
        $now = now();
        \DB::table('subscription_plans')->insert([
            [
                'name' => 'Starter',
                'code' => 'starter',
                'monthly_price' => 99.00,
                'annual_price' => 990.00,
                'max_users' => 5,
                'max_locations' => 1,
                'features' => json_encode(['tips', 'basic_reports']),
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Professional',
                'code' => 'professional',
                'monthly_price' => 199.00,
                'annual_price' => 1990.00,
                'max_users' => 25,
                'max_locations' => 3,
                'features' => json_encode(['tips', 'advanced_reports', 'integrations']),
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Enterprise',
                'code' => 'enterprise',
                'monthly_price' => 499.00,
                'annual_price' => 4990.00,
                'max_users' => null, // Unlimited
                'max_locations' => null, // Unlimited
                'features' => json_encode(['tips', 'advanced_reports', 'integrations', 'api_access', 'priority_support']),
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_billing');
        Schema::dropIfExists('subscription_plans');
    }
};
