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
        // Add role column to users table if it doesn't exist
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['system_admin', 'support_admin', 'billing_admin', 'read_only'])
                    ->default('read_only')
                    ->after('password');
            });
        }

        // Create permissions table
        Schema::create('raiops_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('group_name', 100)->nullable(); // For UI grouping
            $table->timestamps();
        });

        // Create role_permissions pivot table
        Schema::create('raiops_role_permissions', function (Blueprint $table) {
            $table->string('role', 50);
            $table->unsignedBigInteger('permission_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['role', 'permission_id']);
            $table->index('role');
        });

        // Seed permissions
        $permissions = [
            ['name' => 'tenant.view', 'display_name' => 'View Tenants', 'group_name' => 'Tenants'],
            ['name' => 'tenant.create', 'display_name' => 'Create Tenants', 'group_name' => 'Tenants'],
            ['name' => 'tenant.edit', 'display_name' => 'Edit Tenants', 'group_name' => 'Tenants'],
            ['name' => 'tenant.delete', 'display_name' => 'Delete Tenants', 'group_name' => 'Tenants'],
            ['name' => 'tenant.impersonate', 'display_name' => 'Impersonate Tenants', 'group_name' => 'Tenants'],
            ['name' => 'user.view', 'display_name' => 'View Users', 'group_name' => 'Users'],
            ['name' => 'user.edit', 'display_name' => 'Edit Users', 'group_name' => 'Users'],
            ['name' => 'user.password-reset', 'display_name' => 'Reset User Passwords', 'group_name' => 'Users'],
            ['name' => 'billing.view', 'display_name' => 'View Billing', 'group_name' => 'Billing'],
            ['name' => 'billing.edit', 'display_name' => 'Edit Billing', 'group_name' => 'Billing'],
            ['name' => 'rds.view', 'display_name' => 'View RDS Instances', 'group_name' => 'System'],
            ['name' => 'rds.manage', 'display_name' => 'Manage RDS Instances', 'group_name' => 'System'],
            ['name' => 'audit.view', 'display_name' => 'View Audit Logs', 'group_name' => 'System'],
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'group_name' => 'Reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'group_name' => 'Reports'],
        ];

        $now = now();
        foreach ($permissions as $permission) {
            DB::table('raiops_permissions')->insert([
                'name' => $permission['name'],
                'display_name' => $permission['display_name'],
                'group_name' => $permission['group_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Get all permission IDs for role assignments
        $allPermissionIds = DB::table('raiops_permissions')->pluck('id')->toArray();
        
        // System Admin gets all permissions
        foreach ($allPermissionIds as $permId) {
            DB::table('raiops_role_permissions')->insert([
                'role' => 'system_admin',
                'permission_id' => $permId,
                'created_at' => $now,
            ]);
        }

        // Support Admin permissions
        $supportPermissions = DB::table('raiops_permissions')
            ->whereIn('name', [
                'tenant.view', 'tenant.edit', 'tenant.impersonate',
                'user.view', 'user.edit', 'user.password-reset',
                'audit.view', 'rds.view'
            ])
            ->pluck('id')
            ->toArray();

        foreach ($supportPermissions as $permId) {
            DB::table('raiops_role_permissions')->insert([
                'role' => 'support_admin',
                'permission_id' => $permId,
                'created_at' => $now,
            ]);
        }

        // Billing Admin permissions
        $billingPermissions = DB::table('raiops_permissions')
            ->whereIn('name', [
                'tenant.view', 'billing.view', 'billing.edit',
                'reports.view', 'reports.export'
            ])
            ->pluck('id')
            ->toArray();

        foreach ($billingPermissions as $permId) {
            DB::table('raiops_role_permissions')->insert([
                'role' => 'billing_admin',
                'permission_id' => $permId,
                'created_at' => $now,
            ]);
        }

        // Read Only permissions
        $readOnlyPermissions = DB::table('raiops_permissions')
            ->whereIn('name', [
                'tenant.view', 'user.view', 'billing.view',
                'rds.view', 'audit.view', 'reports.view'
            ])
            ->pluck('id')
            ->toArray();

        foreach ($readOnlyPermissions as $permId) {
            DB::table('raiops_role_permissions')->insert([
                'role' => 'read_only',
                'permission_id' => $permId,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raiops_role_permissions');
        Schema::dropIfExists('raiops_permissions');
        
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
