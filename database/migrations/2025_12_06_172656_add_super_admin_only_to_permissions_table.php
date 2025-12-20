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
        Schema::table('permissions', function (Blueprint $table) {
            $table->boolean('super_admin_only')->default(false)->after('guard_name');
            $table->string('description')->nullable()->after('super_admin_only');
            $table->index('super_admin_only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['super_admin_only']);
            $table->dropColumn(['super_admin_only', 'description']);
        });
    }
};

