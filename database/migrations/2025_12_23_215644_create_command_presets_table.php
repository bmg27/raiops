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
        Schema::create('command_presets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('commands');
            $table->boolean('is_chain')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index(); // RAIOPS user ID
            $table->unsignedBigInteger('tenant_master_id')->nullable()->index(); // RAIOPS tenant_master ID
            $table->timestamps();
            
            $table->index(['tenant_master_id', 'archived_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_presets');
    }
};
