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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_email')->nullable();
            $table->string('status')->default('trial'); // trial, active, suspended, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_started_at')->nullable();
            $table->json('settings')->nullable();
            $table->string('mindwave_vector_store_id')->nullable();
            $table->string('mindwave_memory_session_id')->nullable();
            $table->timestamp('mindwave_vector_last_synced_at')->nullable();
            $table->timestamp('mindwave_memory_last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

