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
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('deleted')->default(0)->after('remember_token');
            $table->string('status', 20)->default('Active')->after('deleted');
            
            $table->index('deleted');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deleted']);
            $table->dropIndex(['status']);
            $table->dropColumn(['deleted', 'status']);
        });
    }
};
