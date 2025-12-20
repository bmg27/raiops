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
        Schema::create('tenant_master', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rds_instance_id');
            $table->unsignedBigInteger('remote_tenant_id'); // tenant.id on the RDS
            $table->string('name');
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_email')->nullable();
            $table->enum('status', ['active', 'trial', 'suspended', 'cancelled'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_started_at')->nullable();
            
            // Cached summary data (refreshed periodically)
            $table->integer('cached_user_count')->default(0);
            $table->integer('cached_location_count')->default(0);
            $table->timestamp('cached_last_activity_at')->nullable();
            $table->timestamp('cache_refreshed_at')->nullable();
            
            $table->timestamps();

            $table->unique(['rds_instance_id', 'remote_tenant_id'], 'unique_rds_tenant');
            $table->index('status');
            $table->index('rds_instance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_master');
    }
};
