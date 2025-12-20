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
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('plan_name'); // starter, professional, enterprise
            $table->decimal('base_price', 10, 2)->default(0.00);
            $table->integer('location_count')->default(0);
            $table->decimal('price_per_location', 10, 2)->default(0.00);
            $table->decimal('total_monthly_price', 10, 2)->default(0.00);
            $table->string('billing_cycle')->default('monthly'); // monthly, annual
            $table->timestamp('next_billing_date')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('status')->default('active'); // active, cancelled, past_due
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};

