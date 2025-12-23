<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename tables
        if (Schema::hasTable('rainbo_permissions')) {
            DB::statement('RENAME TABLE `rainbo_permissions` TO `raiops_permissions`');
        }
        
        if (Schema::hasTable('rainbo_role_permissions')) {
            DB::statement('RENAME TABLE `rainbo_role_permissions` TO `raiops_role_permissions`');
        }

        // Rename column in audit_logs
        if (Schema::hasColumn('audit_logs', 'rainbo_user_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->renameColumn('rainbo_user_id', 'raiops_user_id');
            });
        }

        // Update enum values in audit_logs.source
        // First update the data
        DB::table('audit_logs')
            ->where('source', 'rainbo')
            ->update(['source' => 'raiops']);

        // Then alter the enum column
        if (Schema::hasColumn('audit_logs', 'source')) {
            DB::statement("ALTER TABLE `audit_logs` MODIFY COLUMN `source` ENUM('raiops', 'rai_push') DEFAULT 'raiops'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename tables back
        if (Schema::hasTable('raiops_permissions')) {
            DB::statement('RENAME TABLE `raiops_permissions` TO `rainbo_permissions`');
        }
        
        if (Schema::hasTable('raiops_role_permissions')) {
            DB::statement('RENAME TABLE `raiops_role_permissions` TO `rainbo_role_permissions`');
        }

        // Rename column back
        if (Schema::hasColumn('audit_logs', 'raiops_user_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->renameColumn('raiops_user_id', 'rainbo_user_id');
            });
        }

        // Update enum values back
        DB::table('audit_logs')
            ->where('source', 'raiops')
            ->update(['source' => 'rainbo']);

        // Restore enum column
        if (Schema::hasColumn('audit_logs', 'source')) {
            DB::statement("ALTER TABLE `audit_logs` MODIFY COLUMN `source` ENUM('rainbo', 'rai_push') DEFAULT 'rainbo'");
        }
    }
};
