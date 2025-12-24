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
        Schema::create('scheduled_commands', function (Blueprint $table) {
            $table->id();
            $table->string('command_name'); // e.g., 'toast:fetch-orders' (can repeat with different params)
            $table->string('display_name')->unique(); // e.g., 'Fetch Orders' (unique identifier)
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // e.g., 'Toast', 'Seven Shifts', 'Reservations', 'Tips', 'Caching'
            $table->string('required_integration')->nullable(); // 'toast', 'seven_shifts', 'resy', or null
            $table->json('default_params')->nullable(); // Default parameters as JSON
            $table->boolean('requires_tenant')->default(true); // Whether command supports --tenant flag
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); // For ordering in UI
            $table->boolean('default_enabled')->default(true); // Include in default schedule
            $table->timestamps();
            
            $table->index('command_name');
            $table->index('required_integration');
            $table->index('category');
            $table->index('is_active');
            $table->index('default_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_commands');
    }
};
