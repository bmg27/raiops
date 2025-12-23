<?php

namespace App\Console\Commands;

use App\Models\RdsInstance;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sync Ghost Users Command
 * 
 * Creates a ghost user on each RDS instance for each RAIOPS admin.
 * Ghost users enable RAIOPS admins to impersonate into RAI without 
 * needing a real user account. They are identified by:
 * - is_ghost_admin = 1
 * - raiops_admin_id = the RAIOPS admin's ID
 * - email = raiops-admin-{id}@system.internal
 */
class SyncGhostUsers extends Command
{
    protected $signature = 'sync:ghost-users 
                            {--admin= : Sync only for a specific RAIOPS admin ID}
                            {--rds= : Sync only to a specific RDS instance ID}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Sync ghost users to all RDS instances for RAIOPS admin impersonation';

    public function handle(): int
    {
        $this->info('🔄 Starting ghost user sync...');
        
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        // Get RAIOPS admins to sync
        $adminQuery = User::where('status', 'Active')
            ->where(function ($q) {
                $q->where('is_super_admin', true)
                  ->orWhere('role', 'system_admin')
                  ->orWhere('role', 'support_admin');
            });
        
        if ($this->option('admin')) {
            $adminQuery->where('id', $this->option('admin'));
        }
        
        $admins = $adminQuery->get();
        
        if ($admins->isEmpty()) {
            $this->error('No RAIOPS admins found to sync');
            return 1;
        }
        
        $this->info("Found {$admins->count()} RAIOPS admin(s) to sync");
        
        // Get RDS instances to sync to
        $rdsQuery = RdsInstance::where('is_active', true);
        
        if ($this->option('rds')) {
            $rdsQuery->where('id', $this->option('rds'));
        }
        
        $rdsInstances = $rdsQuery->get();
        
        if ($rdsInstances->isEmpty()) {
            $this->error('No active RDS instances found');
            return 1;
        }
        
        $this->info("Found {$rdsInstances->count()} RDS instance(s) to sync to");
        $this->newLine();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($rdsInstances as $rds) {
            $this->info("📦 RDS: {$rds->name} ({$rds->host})");
            
            try {
                // Configure dynamic connection
                $connectionName = "rds_sync_{$rds->id}";
                Config::set("database.connections.{$connectionName}", [
                    'driver' => 'mysql',
                    'host' => $rds->host,
                    'port' => $rds->port,
                    'database' => $rds->rai_database,
                    'username' => $rds->username,
                    'password' => $rds->password,
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_general_ci',
                ]);
                
                // Test connection
                DB::connection($connectionName)->getPdo();
                
                foreach ($admins as $admin) {
                    $result = $this->syncGhostUser($admin, $rds, $connectionName, $dryRun);
                    
                    if ($result) {
                        $successCount++;
                        $this->line("   ✅ {$admin->name} (id={$admin->id})");
                    } else {
                        $errorCount++;
                        $this->line("   ❌ {$admin->name} (id={$admin->id}) - FAILED");
                    }
                }
                
                // Clean up connection
                DB::purge($connectionName);
                
            } catch (\Exception $e) {
                $this->error("   Failed to connect: {$e->getMessage()}");
                $errorCount += $admins->count();
            }
            
            $this->newLine();
        }
        
        $this->newLine();
        $this->info("✨ Ghost user sync complete!");
        $this->info("   Success: {$successCount}");
        if ($errorCount > 0) {
            $this->warn("   Errors: {$errorCount}");
        }
        
        return $errorCount > 0 ? 1 : 0;
    }
    
    /**
     * Create or update a ghost user on an RDS instance
     */
    protected function syncGhostUser(User $admin, RdsInstance $rds, string $connectionName, bool $dryRun): bool
    {
        $email = "raiops-admin-{$admin->id}@system.internal";
        $name = "RAIOPS: {$admin->name}";
        
        try {
            // Check if ghost user already exists
            $existing = DB::connection($connectionName)
                ->table('users')
                ->where('email', $email)
                ->first();
            
            if ($existing) {
                // Update existing
                if (!$dryRun) {
                    DB::connection($connectionName)
                        ->table('users')
                        ->where('id', $existing->id)
                        ->update([
                            'name' => $name,
                            'is_super_admin' => true,
                            'is_ghost_admin' => true,
                            'raiops_admin_id' => $admin->id,
                            'location_access' => 'All',
                            'status' => 'Active',
                            'updated_at' => now(),
                        ]);
                }
                return true;
            }
            
            // Create new ghost user
            if (!$dryRun) {
                DB::connection($connectionName)
                    ->table('users')
                    ->insert([
                        'email' => $email,
                        'name' => $name,
                        'password' => Hash::make(Str::random(64)), // Random unguessable password
                        'email_verified_at' => now(),
                        'is_super_admin' => true,
                        'is_ghost_admin' => true,
                        'raiops_admin_id' => $admin->id,
                        'tenant_id' => null, // Will be set during impersonation
                        'location_access' => 'All',
                        'status' => 'Active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->error("      Error: {$e->getMessage()}");
            return false;
        }
    }
}

