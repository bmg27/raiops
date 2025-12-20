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
        Schema::create('user_email_routing_cache', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->unsignedBigInteger('rds_instance_id');
            $table->unsignedBigInteger('tenant_master_id');
            $table->unsignedBigInteger('remote_user_id');
            $table->string('user_name')->nullable();
            $table->string('status', 50)->default('Active');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('tenant_master_id');
            $table->index('rds_instance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_email_routing_cache');
    }
};
