<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alter enum to include 'pending' status
        DB::statement("ALTER TABLE command_executions MODIFY COLUMN status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum (update any 'pending' to 'running' first)
        DB::statement("UPDATE command_executions SET status = 'running' WHERE status = 'pending'");
        DB::statement("ALTER TABLE command_executions MODIFY COLUMN status ENUM('running', 'completed', 'failed') DEFAULT 'running'");
    }
};
