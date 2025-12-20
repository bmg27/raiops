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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rainbo_user_id')->nullable();
            $table->string('action', 100); // 'created', 'updated', 'deleted', 'impersonated', etc.
            $table->string('model_type')->nullable(); // 'TenantMaster', 'RdsInstance', etc.
            $table->unsignedBigInteger('model_id')->nullable();
            $table->unsignedBigInteger('rds_instance_id')->nullable();
            $table->unsignedBigInteger('tenant_master_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('source', ['rainbo', 'rai_push'])->default('rainbo');
            $table->timestamp('created_at')->useCurrent();

            $table->index('rainbo_user_id');
            $table->index('action');
            $table->index(['model_type', 'model_id']);
            $table->index('tenant_master_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
