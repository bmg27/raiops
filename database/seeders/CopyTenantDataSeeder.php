<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CopyTenantDataSeeder extends Seeder
{
    /**
     * Copy tenant data from RAI database to RAIOPS database
     */
    public function run(): void
    {
        $this->command->info('🔄 Starting tenant data copy from RAI to RAIOPS...');

        // Ensure RAI connection is configured
        $raiDbHost = env('RAI_DB_HOST');
        $raiDbDatabase = env('RAI_DB_DATABASE');
        $raiDbUsername = env('RAI_DB_USERNAME');
        $raiDbPassword = env('RAI_DB_PASSWORD');

        if (!$raiDbHost || !$raiDbDatabase) {
            $this->command->error('❌ RAI database connection not configured in .env');
            $this->command->warn('Please add these to your .env file:');
            $this->command->warn('  RAI_DB_HOST=127.0.0.1');
            $this->command->warn('  RAI_DB_DATABASE=rai');
            $this->command->warn('  RAI_DB_USERNAME=root');
            $this->command->warn('  RAI_DB_PASSWORD=your_password');
            return;
        }

        // Dynamically add the 'rai' connection if not already defined
        config(['database.connections.rai' => [
            'driver' => 'mysql',
            'host' => $raiDbHost,
            'database' => $raiDbDatabase,
            'username' => $raiDbUsername,
            'password' => $raiDbPassword,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        try {
            // Test connection
            DB::connection('rai')->getPdo();
            $this->command->info('✅ Connected to RAI database');

            // Copy tenants
            $this->copyTenants();

            // Copy tenant subscriptions
            $this->copyTenantSubscriptions();

            // Copy tenant invitations
            $this->copyTenantInvitations();

            // Copy locations (seven_locations with tenant_id)
            $this->copyLocations();

            $this->command->info('✅ Tenant data copy completed successfully!');

        } catch (\Exception $e) {
            $this->command->error("❌ Database connection error: " . $e->getMessage());
            $this->command->warn("⚠️  Skipping tenant data copy. You can run this seeder later after configuring the connection.");
        }
    }

    /**
     * Copy all tenants from RAI to RAIOPS
     */
    private function copyTenants(): void
    {
        $this->command->info('📋 Copying tenants...');

        try {
            $raiTenants = DB::connection('rai')
                ->table('tenants')
                ->get();

            if ($raiTenants->isEmpty()) {
                $this->command->warn('   No tenants found in RAI database');
                return;
            }

            $copied = 0;
            $skipped = 0;

            foreach ($raiTenants as $raiTenant) {
                // Check if tenant already exists
                $exists = DB::table('tenants')
                    ->where('id', $raiTenant->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Insert tenant
                DB::table('tenants')->insert([
                    'id' => $raiTenant->id,
                    'name' => $raiTenant->name,
                    'primary_contact_name' => $raiTenant->primary_contact_name,
                    'primary_contact_email' => $raiTenant->primary_contact_email,
                    'status' => $raiTenant->status ?? 'trial',
                    'trial_ends_at' => $raiTenant->trial_ends_at,
                    'subscription_started_at' => $raiTenant->subscription_started_at,
                    'settings' => $raiTenant->settings,
                    'mindwave_vector_store_id' => $raiTenant->mindwave_vector_store_id,
                    'mindwave_memory_session_id' => $raiTenant->mindwave_memory_session_id,
                    'mindwave_vector_last_synced_at' => $raiTenant->mindwave_vector_last_synced_at,
                    'mindwave_memory_last_synced_at' => $raiTenant->mindwave_memory_last_synced_at,
                    'created_at' => $raiTenant->created_at ?? now(),
                    'updated_at' => $raiTenant->updated_at ?? now(),
                ]);

                $copied++;
            }

            $this->command->info("   ✅ Copied {$copied} tenants, skipped {$skipped} existing");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Error copying tenants: " . $e->getMessage());
        }
    }

    /**
     * Copy tenant subscriptions from RAI to RAIOPS
     */
    private function copyTenantSubscriptions(): void
    {
        $this->command->info('💳 Copying tenant subscriptions...');

        try {
            // Check if table exists in RAI
            if (!Schema::connection('rai')->hasTable('tenant_subscriptions')) {
                $this->command->warn('   tenant_subscriptions table not found in RAI database');
                return;
            }

            $raiSubscriptions = DB::connection('rai')
                ->table('tenant_subscriptions')
                ->get();

            if ($raiSubscriptions->isEmpty()) {
                $this->command->warn('   No subscriptions found in RAI database');
                return;
            }

            $copied = 0;
            $skipped = 0;

            foreach ($raiSubscriptions as $raiSubscription) {
                // Check if subscription already exists
                $exists = DB::table('tenant_subscriptions')
                    ->where('id', $raiSubscription->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Insert subscription
                DB::table('tenant_subscriptions')->insert([
                    'id' => $raiSubscription->id,
                    'tenant_id' => $raiSubscription->tenant_id,
                    'plan_name' => $raiSubscription->plan_name,
                    'base_price' => $raiSubscription->base_price ?? 0.00,
                    'location_count' => $raiSubscription->location_count ?? 0,
                    'price_per_location' => $raiSubscription->price_per_location ?? 0.00,
                    'total_monthly_price' => $raiSubscription->total_monthly_price ?? 0.00,
                    'billing_cycle' => $raiSubscription->billing_cycle ?? 'monthly',
                    'next_billing_date' => $raiSubscription->next_billing_date,
                    'stripe_subscription_id' => $raiSubscription->stripe_subscription_id,
                    'stripe_customer_id' => $raiSubscription->stripe_customer_id,
                    'status' => $raiSubscription->status ?? 'active',
                    'created_at' => $raiSubscription->created_at ?? now(),
                    'updated_at' => $raiSubscription->updated_at ?? now(),
                ]);

                $copied++;
            }

            $this->command->info("   ✅ Copied {$copied} subscriptions, skipped {$skipped} existing");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Error copying subscriptions: " . $e->getMessage());
        }
    }

    /**
     * Copy tenant invitations from RAI to RAIOPS
     */
    private function copyTenantInvitations(): void
    {
        $this->command->info('📧 Copying tenant invitations...');

        try {
            // Check if table exists in RAI
            if (!Schema::connection('rai')->hasTable('tenant_invitations')) {
                $this->command->warn('   tenant_invitations table not found in RAI database');
                return;
            }

            $raiInvitations = DB::connection('rai')
                ->table('tenant_invitations')
                ->get();

            if ($raiInvitations->isEmpty()) {
                $this->command->warn('   No invitations found in RAI database');
                return;
            }

            $copied = 0;
            $skipped = 0;

            foreach ($raiInvitations as $raiInvitation) {
                // Check if invitation already exists
                $exists = DB::table('tenant_invitations')
                    ->where('id', $raiInvitation->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Insert invitation
                DB::table('tenant_invitations')->insert([
                    'id' => $raiInvitation->id,
                    'tenant_id' => $raiInvitation->tenant_id,
                    'email' => $raiInvitation->email,
                    'invitation_token' => $raiInvitation->invitation_token,
                    'first_name' => $raiInvitation->first_name,
                    'last_name' => $raiInvitation->last_name,
                    'expires_at' => $raiInvitation->expires_at,
                    'accepted_at' => $raiInvitation->accepted_at,
                    'response_data' => $raiInvitation->response_data,
                    'status' => $raiInvitation->status ?? 'pending',
                    'created_by' => $raiInvitation->created_by,
                    'notes' => $raiInvitation->notes,
                    'created_at' => $raiInvitation->created_at ?? now(),
                    'updated_at' => $raiInvitation->updated_at ?? now(),
                ]);

                $copied++;
            }

            $this->command->info("   ✅ Copied {$copied} invitations, skipped {$skipped} existing");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Error copying invitations: " . $e->getMessage());
        }
    }

    /**
     * Copy locations (seven_locations) that have tenant_id set
     */
    private function copyLocations(): void
    {
        $this->command->info('📍 Copying locations...');

        try {
            // Check if table exists in RAI
            if (!Schema::connection('rai')->hasTable('seven_locations')) {
                $this->command->warn('   seven_locations table not found in RAI database');
                return;
            }

            // Check if tenant_id column exists
            if (!Schema::connection('rai')->hasColumn('seven_locations', 'tenant_id')) {
                $this->command->warn('   tenant_id column not found in seven_locations table');
                return;
            }

            // Get locations with tenant_id
            $raiLocations = DB::connection('rai')
                ->table('seven_locations')
                ->whereNotNull('tenant_id')
                ->get();

            if ($raiLocations->isEmpty()) {
                $this->command->warn('   No locations with tenant_id found in RAI database');
                return;
            }

            // Check if seven_locations table exists in RAIOPS
            if (!Schema::hasTable('seven_locations')) {
                $this->command->warn('   seven_locations table does not exist in RAIOPS database');
                $this->command->warn('   Creating table structure...');
                
                // Create the table with basic structure
                Schema::create('seven_locations', function ($table) {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('api_location_id')->nullable();
                    $table->bigInteger('location_id')->unique();
                    $table->string('name', 191)->index('name');
                    $table->string('alias', 50)->nullable();
                    $table->string('address', 191)->nullable();
                    $table->string('city', 191)->nullable();
                    $table->string('state', 191)->nullable();
                    $table->string('country', 191)->nullable();
                    $table->boolean('hasResy')->default(false);
                    $table->boolean('groupTips')->default(false);
                    $table->boolean('active')->default(true);
                    $table->string('resy_url', 191)->nullable();
                    $table->string('resy_api_key', 191)->nullable();
                    $table->string('toast_location', 100)->nullable();
                    $table->string('toast_sftp_id', 100)->nullable();
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                    $table->timestamps();
                });
            }

            $copied = 0;
            $skipped = 0;

            foreach ($raiLocations as $raiLocation) {
                // Check if location already exists
                $exists = DB::table('seven_locations')
                    ->where('id', $raiLocation->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Insert location
                DB::table('seven_locations')->insert([
                    'id' => $raiLocation->id,
                    'api_location_id' => $raiLocation->api_location_id,
                    'location_id' => $raiLocation->location_id,
                    'name' => $raiLocation->name,
                    'alias' => $raiLocation->alias,
                    'address' => $raiLocation->address,
                    'city' => $raiLocation->city,
                    'state' => $raiLocation->state,
                    'country' => $raiLocation->country,
                    'hasResy' => $raiLocation->hasResy ?? false,
                    'groupTips' => $raiLocation->groupTips ?? false,
                    'active' => $raiLocation->active ?? true,
                    'resy_url' => $raiLocation->resy_url,
                    'resy_api_key' => $raiLocation->resy_api_key,
                    'toast_location' => $raiLocation->toast_location,
                    'toast_sftp_id' => $raiLocation->toast_sftp_id,
                    'tenant_id' => $raiLocation->tenant_id,
                    'created_at' => $raiLocation->created_at ?? now(),
                    'updated_at' => $raiLocation->updated_at ?? now(),
                ]);

                $copied++;
            }

            $this->command->info("   ✅ Copied {$copied} locations, skipped {$skipped} existing");

        } catch (\Exception $e) {
            $this->command->error("   ❌ Error copying locations: " . $e->getMessage());
        }
    }
}

