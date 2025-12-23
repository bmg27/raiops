<?php

namespace App\Services;

use App\Models\RdsInstance;
use App\Models\TenantMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service for creating tenants on RDS instances from RAIOPS
 * 
 * "The Garden" - Creating new tenants without invitation flow
 */
class TenantCreationService
{
    protected RdsConnectionService $rdsService;

    public function __construct(RdsConnectionService $rdsService)
    {
        $this->rdsService = $rdsService;
    }

    /**
     * Create a new tenant on a specific RDS instance
     * 
     * @param RdsInstance $rdsInstance The RDS instance to create the tenant on
     * @param array $data Tenant data:
     *   - name (required)
     *   - primary_contact_name (required)
     *   - primary_contact_email (required)
     *   - password (required) - will be hashed
     *   - status (default: 'trial')
     *   - trial_ends_at (optional, defaults to 30 days from now)
     *   - plan_name (optional, for subscription)
     *   - location_count (optional, for subscription)
     *   - location_name (required) - name of the first location
     *   - location_alias (optional) - alias for the first location
     *   - location_address (optional)
     *   - location_city (optional)
     *   - location_state (optional)
     *   - location_country (optional, defaults to 'US')
     *   - location_toast_location (optional) - Toast location ID
     * 
     * @return array ['success' => bool, 'tenant_master_id' => int|null, 'remote_tenant_id' => int|null, 'message' => string]
     */
    public function createTenant(RdsInstance $rdsInstance, array $data): array
    {
        // Validate required fields
        $required = ['name', 'primary_contact_name', 'primary_contact_email', 'password', 'location_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => "Missing required field: {$field}",
                ];
            }
        }

        // Check if tenant with same name already exists on this RDS
        $existingTenant = $this->rdsService->query($rdsInstance)
            ->table('tenants')
            ->where('name', $data['name'])
            ->first();

        if ($existingTenant) {
            return [
                'success' => false,
                'message' => "A tenant with name '{$data['name']}' already exists on {$rdsInstance->name}",
            ];
        }

        // Check if user email already exists in user_email_routing (on master RDS)
        $masterRds = RdsInstance::where('is_master', true)->first();
        if ($masterRds) {
            $existingRouting = $this->rdsService->query($masterRds)
                ->table('user_email_routing')
                ->where('email', $data['primary_contact_email'])
                ->where('status', 'Active')
                ->first();

            if ($existingRouting) {
                return [
                    'success' => false,
                    'message' => "A user with email '{$data['primary_contact_email']}' already exists in the system",
                ];
            }
        }

        DB::beginTransaction();

        try {
            $connection = $this->rdsService->getConnection($rdsInstance);
            $db = DB::connection($connection);

            // Set default trial end date (30 days from now)
            $trialEndsAt = $data['trial_ends_at'] ?? now()->addDays(30);
            if (is_string($trialEndsAt)) {
                $trialEndsAt = Carbon::parse($trialEndsAt);
            }

            // Create tenant
            $tenantId = $db->table('tenants')->insertGetId([
                'name' => $data['name'],
                'primary_contact_name' => $data['primary_contact_name'],
                'primary_contact_email' => $data['primary_contact_email'],
                'status' => $data['status'] ?? 'trial',
                'trial_ends_at' => $trialEndsAt,
                'subscription_started_at' => null,
                'settings' => json_encode([
                    'plan' => $data['plan_name'] ?? 'basic',
                    'features' => $data['features'] ?? [],
                    'requested_locations' => $data['location_count'] ?? 1,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create user (tenant owner)
            $userId = $db->table('users')->insertGetId([
                'name' => $data['primary_contact_name'],
                'email' => $data['primary_contact_email'],
                'password' => Hash::make($data['password']),
                'tenant_id' => $tenantId,
                'is_tenant_owner' => true,
                'is_super_admin' => false,
                'status' => 'Active',
                'location_access' => 'All',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create Account Owner Primary role for this tenant
            $this->createTenantAdminRole($db, $tenantId, $userId);

            // Create first location (tenant must have at least one location)
            $locationId = $this->createLocation($db, $tenantId, $data);

            // Create subscription if plan info provided
            if (isset($data['plan_name'])) {
                $db->table('tenant_subscriptions')->insert([
                    'tenant_id' => $tenantId,
                    'plan_name' => $data['plan_name'],
                    'base_price' => $data['base_price'] ?? 0,
                    'location_count' => $data['location_count'] ?? 1,
                    'price_per_location' => $data['price_per_location'] ?? 0,
                    'total_monthly_price' => $data['total_monthly_price'] ?? 0,
                    'billing_cycle' => 'monthly',
                    'status' => 'trial',
                    'next_billing_date' => $trialEndsAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Sync to tenant_master in RAIOPS first (we need the ID for user_email_routing_cache)
            $userCount = 1; // We just created the owner
            $locationCount = 1; // We just created the first location

            $tenantMaster = TenantMaster::updateOrCreate(
                [
                    'rds_instance_id' => $rdsInstance->id,
                    'remote_tenant_id' => $tenantId,
                ],
                [
                    'name' => $data['name'],
                    'primary_contact_name' => $data['primary_contact_name'],
                    'primary_contact_email' => $data['primary_contact_email'],
                    'status' => $data['status'] ?? 'trial',
                    'trial_ends_at' => $trialEndsAt,
                    'cached_user_count' => $userCount,
                    'cached_location_count' => $locationCount,
                    'cache_refreshed_at' => now(),
                ]
            );

            // Create user_email_routing entry on master RDS (RAI table - no tenant_master_id)
            if ($masterRds) {
                $masterConnection = $this->rdsService->getConnection($masterRds);
                $masterDb = DB::connection($masterConnection);

                // Check if entry already exists (email + tenant_id unique constraint)
                $existingRouting = $masterDb->table('user_email_routing')
                    ->where('email', $data['primary_contact_email'])
                    ->where('tenant_id', $tenantId)
                    ->first();

                if (!$existingRouting) {
                    $masterDb->table('user_email_routing')->insert([
                        'email' => $data['primary_contact_email'],
                        'tenant_id' => $tenantId,
                        'rds_instance_id' => $rdsInstance->id,
                        'password_hash' => Hash::make($data['password']),
                        'status' => 'Active',
                        'user_name' => $data['primary_contact_name'],
                        'user_id' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    // Update existing entry
                    $masterDb->table('user_email_routing')
                        ->where('id', $existingRouting->id)
                        ->update([
                            'rds_instance_id' => $rdsInstance->id,
                            'password_hash' => Hash::make($data['password']),
                            'status' => 'Active',
                            'user_name' => $data['primary_contact_name'],
                            'user_id' => $userId,
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::commit();

            Log::info('Tenant created successfully from RAIOPS', [
                'tenant_master_id' => $tenantMaster->id,
                'remote_tenant_id' => $tenantId,
                'rds_instance_id' => $rdsInstance->id,
                'rds_name' => $rdsInstance->name,
                'email' => $data['primary_contact_email'],
            ]);

            return [
                'success' => true,
                'tenant_master_id' => $tenantMaster->id,
                'remote_tenant_id' => $tenantId,
                'message' => "Tenant '{$data['name']}' created successfully on {$rdsInstance->name}",
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create tenant from RAIOPS', [
                'rds_instance_id' => $rdsInstance->id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create tenant: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create Account Owner Primary role for a tenant on an RDS instance
     * 
     * @param \Illuminate\Database\Connection $db The database connection
     * @param int $tenantId The tenant ID
     * @param int $userId The user ID to assign the role to
     */
    protected function createTenantAdminRole($db, int $tenantId, int $userId): void
    {
        // Check if role already exists
        $existingRole = $db->table('roles')
            ->where('name', 'Account Owner Primary')
            ->where('tenant_id', $tenantId)
            ->where('guard_name', 'web')
            ->first();

        if ($existingRole) {
            $roleId = $existingRole->id;
        } else {
            // Create the role
            $roleId = $db->table('roles')->insertGetId([
                'name' => 'Account Owner Primary',
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Get all non-super-admin permissions
            $permissions = $db->table('permissions')
                ->where('super_admin_only', false)
                ->pluck('id');

            // Assign permissions to role
            if ($permissions->isNotEmpty()) {
                $rolePermissions = $permissions->map(function ($permissionId) use ($roleId) {
                    return [
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ];
                })->toArray();

                $db->table('role_has_permissions')->insert($rolePermissions);
            }
        }

        // Assign role to user
        $db->table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => 'App\\Models\\User',
            'model_id' => $userId,
        ]);
    }

    /**
     * Create the first location for a tenant
     * 
     * @param \Illuminate\Database\Connection $db The database connection
     * @param int $tenantId The tenant ID
     * @param array $data Location data from tenant creation
     * @return int The location ID
     */
    protected function createLocation($db, int $tenantId, array $data): int
    {
        // Get the next location ID
        $locationMax = $db->table('locations')->max('id') ?? 0;
        $locationId = $locationMax + 1;

        // Create location
        $db->table('locations')->insert([
            'id' => $locationId,
            'name' => $data['location_name'],
            'address' => $data['location_address'] ?? null,
            'city' => $data['location_city'] ?? null,
            'state' => $data['location_state'] ?? null,
            'country' => $data['location_country'] ?? 'US',
            'has_grouped_tips' => false,
            'is_active' => true,
            'tenant_id' => $tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create location alias if provided
        if (!empty($data['location_alias'])) {
            $db->table('location_aliases')->insert([
                'name' => $data['location_alias'],
                'location_id' => $locationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create location map for Toast if provided
        if (!empty($data['location_toast_location'])) {
            // Get Toast provider ID
            $toastProvider = $db->table('providers')
                ->where('classname', 'App\\Classes\\Providers\\ToastProvider')
                ->first();

            if ($toastProvider) {
                $db->table('location_maps')->insert([
                    'external_id' => $data['location_toast_location'],
                    'location_id' => $locationId,
                    'provider_id' => $toastProvider->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $locationId;
    }
}

