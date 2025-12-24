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
        Schema::create('command_executions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('command_name', 100)->index();
            $table->unsignedBigInteger('raiops_user_id')->nullable()->index(); // RAIOPS user who triggered it
            $table->unsignedBigInteger('tenant_master_id')->nullable()->index(); // RAIOPS tenant_master ID
            $table->unsignedBigInteger('rds_instance_id')->nullable()->index(); // RDS instance where command runs
            $table->string('triggered_by', 50)->default('manual'); // 'manual', 'cron', 'api'
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->integer('process_id')->nullable();
            $table->text('current_step')->nullable();
            $table->integer('total_steps')->default(0);
            $table->integer('completed_steps')->default(0);
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('retry_enabled')->default(0);
            $table->boolean('is_retry_attempt')->default(0);
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedBigInteger('original_execution_id')->nullable()->index();
            $table->timestamps();
            
            $table->index(['tenant_master_id', 'status']);
            $table->index(['rds_instance_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_executions');
    }
};
