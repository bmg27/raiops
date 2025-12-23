# RAIOPS Multi-RDS Architecture Specification

**Version:** 1.0  
**Date:** December 19, 2025  
**Status:** Planning / Pre-Development

---

## Executive Summary

RAIOPS (RAI Operations) is evolving from a single-database admin tool to a **Multi-RDS Command Central** capable of managing tenants, users, and operations across multiple RDS instances. This document captures all architectural decisions and provides a detailed implementation roadmap.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Key Decisions](#key-decisions)
3. [Database Schema](#database-schema)
4. [Authentication & Authorization](#authentication--authorization)
5. [Impersonation Flow](#impersonation-flow)
6. [Data Sync Strategy](#data-sync-strategy)
7. [Implementation Phases](#implementation-phases)
8. [API Endpoints](#api-endpoints)
9. [Services & Classes](#services--classes)
10. [Configuration Requirements](#configuration-requirements)
11. [UI Components](#ui-components)
12. [Security Considerations](#security-considerations)

---

## Architecture Overview

### System Topology

```
┌─────────────────────────────────────────────────────────────────────┐
│                           RAIOPS                                     │
│                    (raiops.example.com)                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                  │
│  │   Admin     │  │   Tenant    │  │    RDS      │                  │
│  │   Auth      │  │   Manager   │  │   Manager   │                  │
│  └─────────────┘  └─────────────┘  └─────────────┘                  │
│         │                │                │                          │
│         └────────────────┼────────────────┘                          │
│                          │                                           │
│                ┌─────────▼─────────┐                                │
│                │  RAIOPS Database  │                                │
│                │  (Source of Truth)│                                │
│                └─────────┬─────────┘                                │
└──────────────────────────┼──────────────────────────────────────────┘
                           │
           ┌───────────────┼───────────────┐
           │               │               │
           ▼               ▼               ▼
    ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
    │   RDS 1     │ │   RDS 2     │ │   RDS N     │
    │  (Master)   │ │  (Remote)   │ │  (Remote)   │
    │             │ │             │ │             │
    │ ┌─────────┐ │ │ ┌─────────┐ │ │ ┌─────────┐ │
    │ │ RAI App │ │ │ │ RAI App │ │ │ │ RAI App │ │
    │ │ Instance│ │ │ │ Instance│ │ │ │ Instance│ │
    │ └─────────┘ │ │ └─────────┘ │ │ └─────────┘ │
    │             │ │             │ │             │
    │ Tenants:    │ │ Tenants:    │ │ Tenants:    │
    │ - Tenant A  │ │ - Tenant X  │ │ - Tenant P  │
    │ - Tenant B  │ │ - Tenant Y  │ │ - Tenant Q  │
    └─────────────┘ └─────────────┘ └─────────────┘
```

### Key Principles

1. **RAIOPS is the Source of Truth** for platform-level data (RDS configs, tenant registry, admin users, billing)
2. **RAI instances are the Source of Truth** for tenant operational data (users, locations, tips, reports)
3. **Hybrid Data Strategy** - Cache summaries in RAIOPS, query live for details
4. **Secure Impersonation** - JWT-based cross-app authentication with granular permissions

---

## Key Decisions

| Topic | Decision | Rationale |
|-------|----------|-----------|
| App Architecture | Separate Laravel app | Clean separation, independent deployment |
| Authentication | Own users table, username/password | Simple for development, SSO/2FA later |
| Database | Own `raiops` database | Single source of truth for platform data |
| Data Strategy | Hybrid (cached + live) | Balance between performance and accuracy |
| Impersonation | JWT token, ghost users | Secure, auditable, cross-domain compatible |
| Audit Logging | Both apps | RAI local + push to RAIOPS central |
| Permissions | Granular per-admin | Different admin roles need different access |
| Ghost Users | Keep with flag, 90-day cleanup | Maintain audit trail associations |
| UI for No-Permission | Hide elements | Cleaner UX, less confusion |

---

## Database Schema

### RAIOPS Database Tables

#### `raiops_users` (System Administrators)

```sql
CREATE TABLE raiops_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('system_admin', 'support_admin', 'billing_admin', 'read_only') DEFAULT 'read_only',
    is_active TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

#### `rds_instances`

```sql
CREATE TABLE rds_instances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT DEFAULT 3306,
    username VARCHAR(255) NOT NULL,
    password TEXT NOT NULL,  -- Laravel Crypt encrypted
    rai_database VARCHAR(255) NOT NULL,
    providers_database VARCHAR(255) NULL,
    app_url VARCHAR(255) NOT NULL,  -- URL to RAI instance for this RDS
    is_active TINYINT(1) DEFAULT 1,
    is_master TINYINT(1) DEFAULT 0,
    health_status ENUM('healthy', 'degraded', 'down', 'unknown') DEFAULT 'unknown',
    last_health_check_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_is_active (is_active),
    INDEX idx_is_master (is_master)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

#### `tenant_master` (Registry of ALL Tenants)

```sql
CREATE TABLE tenant_master (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rds_instance_id BIGINT UNSIGNED NOT NULL,
    remote_tenant_id BIGINT UNSIGNED NOT NULL,  -- tenant.id on the RDS
    name VARCHAR(255) NOT NULL,
    primary_contact_name VARCHAR(255) NULL,
    primary_contact_email VARCHAR(255) NULL,
    status ENUM('active', 'trial', 'suspended', 'cancelled') DEFAULT 'trial',
    trial_ends_at TIMESTAMP NULL,
    subscription_started_at TIMESTAMP NULL,
    
    -- Cached summary data (refreshed periodically)
    cached_user_count INT DEFAULT 0,
    cached_location_count INT DEFAULT 0,
    cached_last_activity_at TIMESTAMP NULL,
    cache_refreshed_at TIMESTAMP NULL,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY unique_rds_tenant (rds_instance_id, remote_tenant_id),
    INDEX idx_status (status),
    INDEX idx_rds_instance (rds_instance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

#### `tenant_billing`

```sql
CREATE TABLE tenant_billing (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_master_id BIGINT UNSIGNED NOT NULL,
    subscription_plan_id BIGINT UNSIGNED NULL,
    mrr DECIMAL(10, 2) DEFAULT 0.00,  -- Monthly Recurring Revenue
    billing_email VARCHAR(255) NULL,
    billing_cycle ENUM('monthly', 'annual') DEFAULT 'monthly',
    next_billing_date DATE NULL,
    payment_method VARCHAR(50) NULL,  -- 'stripe', 'invoice', etc.
    stripe_customer_id VARCHAR(255) NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_tenant_master (tenant_master_id),
    INDEX idx_next_billing (next_billing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

#### `subscription_plans`

```sql
CREATE TABLE subscription_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    monthly_price DECIMAL(10, 2) NOT NULL,
    annual_price DECIMAL(10, 2) NULL,
    max_users INT NULL,  -- NULL = unlimited
    max_locations INT NULL,
    features JSON NULL,  -- Feature flags for this plan
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

#### `raiops_permissions`

```sql
CREATE TABLE raiops_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    group_name VARCHAR(100) NULL,  -- For UI grouping
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed data
INSERT INTO raiops_permissions (name, display_name, group_name) VALUES
('tenant.view', 'View Tenants', 'Tenants'),
('tenant.create', 'Create Tenants', 'Tenants'),
('tenant.edit', 'Edit Tenants', 'Tenants'),
('tenant.delete', 'Delete Tenants', 'Tenants'),
('tenant.impersonate', 'Impersonate Tenants', 'Tenants'),
('user.view', 'View Users', 'Users'),
('user.edit', 'Edit Users', 'Users'),
('user.password-reset', 'Reset User Passwords', 'Users'),
('billing.view', 'View Billing', 'Billing'),
('billing.edit', 'Edit Billing', 'Billing'),
('rds.view', 'View RDS Instances', 'System'),
('rds.manage', 'Manage RDS Instances', 'System'),
('audit.view', 'View Audit Logs', 'System'),
('reports.view', 'View Reports', 'Reports'),
('reports.export', 'Export Reports', 'Reports');
```

#### `raiops_role_permissions`

```sql
CREATE TABLE raiops_role_permissions (
    role VARCHAR(50) NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    
    PRIMARY KEY (role, permission_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed: System Admin gets all permissions
INSERT INTO raiops_role_permissions (role, permission_id, created_at)
SELECT 'system_admin', id, NOW() FROM raiops_permissions;

-- Seed: Support Admin
INSERT INTO raiops_role_permissions (role, permission_id, created_at)
SELECT 'support_admin', id, NOW() FROM raiops_permissions 
WHERE name IN ('tenant.view', 'tenant.edit', 'tenant.impersonate', 'user.view', 'user.edit', 'user.password-reset', 'audit.view', 'rds.view');

-- Seed: Billing Admin
INSERT INTO raiops_role_permissions (role, permission_id, created_at)
SELECT 'billing_admin', id, NOW() FROM raiops_permissions 
WHERE name IN ('tenant.view', 'billing.view', 'billing.edit', 'reports.view', 'reports.export');

-- Seed: Read Only
INSERT INTO raiops_role_permissions (role, permission_id, created_at)
SELECT 'read_only', id, NOW() FROM raiops_permissions 
WHERE name IN ('tenant.view', 'user.view', 'billing.view', 'rds.view', 'audit.view', 'reports.view');
```

#### `audit_logs`

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    raiops_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,  -- 'created', 'updated', 'deleted', 'impersonated', etc.
    model_type VARCHAR(255) NULL,  -- 'TenantMaster', 'RdsInstance', etc.
    model_id BIGINT UNSIGNED NULL,
    rds_instance_id BIGINT UNSIGNED NULL,
    tenant_master_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    source ENUM('raiops', 'rai_push') DEFAULT 'raiops',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (raiops_user_id),
    INDEX idx_action (action),
    INDEX idx_model (model_type, model_id),
    INDEX idx_tenant (tenant_master_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

#### `user_email_routing_cache`

```sql
-- Synced copy from master RDS for quick lookups
CREATE TABLE user_email_routing_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    rds_instance_id BIGINT UNSIGNED NOT NULL,
    tenant_master_id BIGINT UNSIGNED NOT NULL,
    remote_user_id BIGINT UNSIGNED NOT NULL,
    user_name VARCHAR(255) NULL,
    status VARCHAR(50) DEFAULT 'Active',
    synced_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_tenant (tenant_master_id),
    INDEX idx_rds (rds_instance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

---

## Authentication & Authorization

### RAIOPS Admin Authentication

RAIOPS uses its own `users` table, completely separate from RAI users.

**Login Flow:**
1. Admin visits `raiops.example.com/login`
2. Enters email/password
3. Laravel authenticates against `raiops_users` table
4. Session created with admin's role and permissions

**Guard Configuration** (`config/auth.php`):

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'raiops_users',
    ],
],

'providers' => [
    'raiops_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\RaiOpsUser::class,
    ],
],
```

### Role-Based Access Control

| Role | Description | Key Permissions |
|------|-------------|-----------------|
| `system_admin` | Full access | All permissions |
| `support_admin` | Customer support | View/edit tenants, impersonate, view users, reset passwords |
| `billing_admin` | Finance team | View tenants, billing management, reports |
| `read_only` | Observers | View-only access to all areas |

### Permission Checking

```php
// In RaiOpsUser model
public function hasPermission(string $permission): bool
{
    return DB::table('raiops_role_permissions')
        ->join('raiops_permissions', 'raiops_permissions.id', '=', 'raiops_role_permissions.permission_id')
        ->where('raiops_role_permissions.role', $this->role)
        ->where('raiops_permissions.name', $permission)
        ->exists();
}

public function getPermissions(): array
{
    return DB::table('raiops_role_permissions')
        ->join('raiops_permissions', 'raiops_permissions.id', '=', 'raiops_role_permissions.permission_id')
        ->where('raiops_role_permissions.role', $this->role)
        ->pluck('raiops_permissions.name')
        ->toArray();
}
```

---

## Impersonation Flow

### Overview

When a RAIOPS admin needs to access a tenant's RAI instance:

1. **RAIOPS generates signed JWT** with admin ID, tenant ID, RDS ID, permissions
2. **Redirects to RAI** impersonation endpoint with token
3. **RAI validates token**, creates/retrieves ghost user, establishes session
4. **Admin operates in RAI** with their RAIOPS permissions enforced
5. **Return to RAIOPS** via button in RAI UI

### JWT Token Structure

```json
{
  "raiops_admin_id": 1,
  "raiops_admin_email": "admin@example.com",
  "tenant_master_id": 42,
  "remote_tenant_id": 5,
  "rds_instance_id": 2,
  "permissions": [
    "tenant.view",
    "tenant.edit",
    "user.view"
  ],
  "return_url": "https://raiops.example.com/tenants/42",
  "iat": 1734567890,
  "exp": 1734568190
}
```

### RAIOPS Side (Token Generation)

```php
// App\Services\ImpersonationTokenService

class ImpersonationTokenService
{
    public function generateToken(RaiOpsUser $admin, TenantMaster $tenant): string
    {
        $rds = $tenant->rdsInstance;
        
        $payload = [
            'raiops_admin_id' => $admin->id,
            'raiops_admin_email' => $admin->email,
            'tenant_master_id' => $tenant->id,
            'remote_tenant_id' => $tenant->remote_tenant_id,
            'rds_instance_id' => $rds->id,
            'permissions' => $admin->getPermissions(),
            'return_url' => route('tenants.show', $tenant),
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
        ];
        
        return JWT::encode($payload, config('raiops.impersonation_secret'), 'HS256');
    }
    
    public function getImpersonationUrl(RaiOpsUser $admin, TenantMaster $tenant): string
    {
        $token = $this->generateToken($admin, $tenant);
        $rds = $tenant->rdsInstance;
        
        return $rds->app_url . '/raiops-impersonate?token=' . $token;
    }
}
```

### RAI Side (Token Validation)

```php
// routes/web.php (in RAI)
Route::get('/raiops-impersonate', [RaiOpsImpersonationController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('raiops.impersonate');

// App\Http\Controllers\RaiOpsImpersonationController (in RAI)

class RaiOpsImpersonationController extends Controller
{
    public function handle(Request $request)
    {
        $token = $request->query('token');
        
        if (!$token) {
            abort(400, 'Missing token');
        }
        
        try {
            $payload = JWT::decode(
                $token, 
                new Key(config('raiops.impersonation_secret'), 'HS256')
            );
            
            // Validate RDS instance matches this RAI deployment
            if ($payload->rds_instance_id !== (int) config('app.rds_instance_id')) {
                Log::warning('RAIOPS impersonation: wrong RDS', [
                    'expected' => config('app.rds_instance_id'),
                    'received' => $payload->rds_instance_id,
                ]);
                abort(403, 'Invalid RDS instance');
            }
            
            // Find or create ghost admin user
            $ghostUser = $this->findOrCreateGhostAdmin($payload);
            
            // Log them in
            Auth::login($ghostUser);
            
            // Set session context
            session([
                'is_raiops_session' => true,
                'raiops_admin_id' => $payload->raiops_admin_id,
                'raiops_admin_email' => $payload->raiops_admin_email,
                'raiops_permissions' => $payload->permissions,
                'raiops_return_url' => $payload->return_url,
                'impersonated_tenant_id' => $payload->remote_tenant_id,
                'selected_tenant_id' => $payload->remote_tenant_id,
            ]);
            
            // Switch RDS connection if needed
            if (app(RdsConnectionService::class)->isEnabled()) {
                app(RdsConnectionService::class)->switchToRdsByTenant($payload->remote_tenant_id);
            }
            
            // Log the impersonation
            Log::info('RAIOPS impersonation successful', [
                'raiops_admin_id' => $payload->raiops_admin_id,
                'tenant_id' => $payload->remote_tenant_id,
                'ghost_user_id' => $ghostUser->id,
            ]);
            
            return redirect('/dashboard');
            
        } catch (ExpiredException $e) {
            abort(403, 'Token expired');
        } catch (\Exception $e) {
            Log::error('RAIOPS impersonation failed', [
                'error' => $e->getMessage(),
            ]);
            abort(403, 'Invalid token');
        }
    }
    
    protected function findOrCreateGhostAdmin(object $payload): User
    {
        $email = "raiops-admin-{$payload->raiops_admin_id}@system.internal";
        
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => "RAIOPS Admin #{$payload->raiops_admin_id}",
                'password' => Hash::make(Str::random(64)),
                'is_super_admin' => true,
                'is_ghost_admin' => true,
                'tenant_id' => null,
                'status' => 'Active',
            ]
        );
    }
}
```

### RAI Permission Enforcement

```php
// App\Services\RaiOpsPermissionService (in RAI)

class RaiOpsPermissionService
{
    public function isRaiOpsSession(): bool
    {
        return session('is_raiops_session', false);
    }
    
    public function canDo(string $permission): bool
    {
        // If not a RAIOPS session, allow (normal RAI permissions apply)
        if (!$this->isRaiOpsSession()) {
            return true;
        }
        
        $permissions = session('raiops_permissions', []);
        return in_array($permission, $permissions);
    }
    
    public function denyUnlessAllowed(string $permission): void
    {
        if (!$this->canDo($permission)) {
            abort(403, 'RAIOPS permission denied: ' . $permission);
        }
    }
}
```

### Ghost User Cleanup Command

```php
// App\Console\Commands\CleanupGhostAdmins (in RAI)

class CleanupGhostAdmins extends Command
{
    protected $signature = 'raiops:cleanup-ghost-admins {--days=90}';
    
    public function handle()
    {
        $days = $this->option('days');
        
        $deleted = User::where('is_ghost_admin', true)
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();
        
        $this->info("Deleted {$deleted} ghost admin users inactive for {$days}+ days");
    }
}
```

---

## Data Sync Strategy

### Cached Data (Background Jobs)

| Data | Table | Sync Frequency | Source |
|------|-------|----------------|--------|
| Tenant summary | `tenant_master` | Every 15 min | Each RDS |
| User counts | `tenant_master.cached_user_count` | Every 15 min | Each RDS |
| Location counts | `tenant_master.cached_location_count` | Every 15 min | Each RDS |
| User routing | `user_email_routing_cache` | Every 15 min | Master RDS |
| Last activity | `tenant_master.cached_last_activity_at` | Every 15 min | Each RDS |

### Live Queries (On-Demand)

| Data | When Queried | Why |
|------|--------------|-----|
| User list for tenant | Admin clicks into tenant | Must be current |
| Location details | Admin views locations | Must be current |
| Provider settings | Admin edits settings | Critical accuracy |
| Subscription/billing | Any billing operation | Financial accuracy |

### Sync Commands

```bash
# Scheduled (cron)
*/15 * * * * php artisan raiops:sync-tenant-summaries
*/15 * * * * php artisan raiops:sync-user-routing
*/5  * * * * php artisan raiops:health-check-rds

# Manual
php artisan raiops:refresh-tenant {tenant_master_id}
php artisan raiops:refresh-all-tenants
php artisan raiops:sync-from-rds {rds_instance_id}
```

### Sync Service

```php
// App\Services\TenantSyncService

class TenantSyncService
{
    public function syncAllTenants(): void
    {
        $rdsInstances = RdsInstance::active()->get();
        
        foreach ($rdsInstances as $rds) {
            $this->syncTenantsFromRds($rds);
        }
    }
    
    public function syncTenantsFromRds(RdsInstance $rds): void
    {
        $connection = $this->connectToRds($rds);
        
        $tenants = DB::connection($connection)
            ->table('tenants')
            ->select([
                'id',
                'name',
                'primary_contact_name',
                'primary_contact_email',
                'status',
                'trial_ends_at',
                'subscription_started_at',
            ])
            ->get();
        
        foreach ($tenants as $tenant) {
            // Get user count
            $userCount = DB::connection($connection)
                ->table('users')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'Active')
                ->count();
            
            // Get location count
            $locationCount = DB::connection($connection)
                ->table('locations')
                ->where('tenant_id', $tenant->id)
                ->count();
            
            // Get last activity
            $lastActivity = DB::connection($connection)
                ->table('user_sessions')
                ->join('users', 'users.id', '=', 'user_sessions.user_id')
                ->where('users.tenant_id', $tenant->id)
                ->max('user_sessions.login_at');
            
            // Upsert to tenant_master
            TenantMaster::updateOrCreate(
                [
                    'rds_instance_id' => $rds->id,
                    'remote_tenant_id' => $tenant->id,
                ],
                [
                    'name' => $tenant->name,
                    'primary_contact_name' => $tenant->primary_contact_name,
                    'primary_contact_email' => $tenant->primary_contact_email,
                    'status' => $tenant->status,
                    'trial_ends_at' => $tenant->trial_ends_at,
                    'subscription_started_at' => $tenant->subscription_started_at,
                    'cached_user_count' => $userCount,
                    'cached_location_count' => $locationCount,
                    'cached_last_activity_at' => $lastActivity,
                    'cache_refreshed_at' => now(),
                ]
            );
        }
    }
}
```

---

## Implementation Phases

### Phase 1: Foundation (Multi-RDS Awareness)

**Goal:** RAIOPS can connect to and manage multiple RDS instances

**Tasks:**

1. **Database Migrations**
   - [ ] Create `rds_instances` table
   - [ ] Create `tenant_master` table
   - [ ] Create `audit_logs` table
   - [ ] Add `is_ghost_admin` to RAI users table

2. **Models**
   - [ ] `RdsInstance` model with encrypted password
   - [ ] `TenantMaster` model
   - [ ] `AuditLog` model

3. **Services**
   - [ ] `RdsConnectionService` for RAIOPS
   - [ ] `AuditService` for logging actions

4. **UI Components**
   - [ ] RDS Instance list page
   - [ ] RDS Instance add/edit modal
   - [ ] Connection test button
   - [ ] RDS health status indicators

5. **Seeders**
   - [ ] Seed RDS instances from RAI config
   - [ ] Initial tenant_master population

**Deliverables:**
- Admin can view/add/edit RDS instances
- Admin can test connections
- Health status displayed per RDS

---

### Phase 2: Cross-RDS Operations

**Goal:** RAIOPS can query and manage data across all RDS instances

**Tasks:**

1. **Tenant Management**
   - [ ] Tenant list with RDS indicator
   - [ ] Tenant detail page with live data from correct RDS
   - [ ] Tenant status changes (active/suspended/etc.)

2. **User Routing**
   - [ ] View `user_email_routing` entries
   - [ ] Search users across all tenants
   - [ ] Edit routing entries

3. **Sync Jobs**
   - [ ] `raiops:sync-tenant-summaries` command
   - [ ] `raiops:sync-user-routing` command
   - [ ] Schedule sync jobs

4. **Cross-RDS Commands**
   - [ ] "Run migration on all RDS" framework
   - [ ] "Run seeder on all RDS" framework

**Deliverables:**
- Full tenant management across RDS instances
- User routing visibility and management
- Automated sync jobs running

---

### Phase 3: Impersonation Flow

**Goal:** RAIOPS admins can securely access RAI tenant instances

**Tasks:**

1. **RAIOPS Side**
   - [ ] `ImpersonationTokenService`
   - [ ] "Manage in RAI" button on tenant detail
   - [ ] Audit logging for impersonation

2. **RAI Side**
   - [ ] `/raiops-impersonate` endpoint
   - [ ] `RaiOpsImpersonationController`
   - [ ] Ghost user creation
   - [ ] Session setup with permissions

3. **RAI UI Changes**
   - [ ] RAIOPS session indicator bar
   - [ ] "Return to RAIOPS" button
   - [ ] Permission-based UI hiding

4. **Shared Configuration**
   - [ ] Impersonation secret in both apps
   - [ ] RDS instance ID in RAI config

**Deliverables:**
- Seamless jump from RAIOPS to RAI tenant
- Visual indicator of RAIOPS session in RAI
- Return path back to RAIOPS

---

### Phase 4: Audit & Permissions

**Goal:** Full audit trail and granular permission control

**Tasks:**

1. **RAIOPS Permissions**
   - [ ] `raiops_permissions` table and seeder
   - [ ] `raiops_role_permissions` table and seeder
   - [ ] Permission checking middleware
   - [ ] UI permission checks

2. **Audit Logging**
   - [ ] Automatic audit on model changes
   - [ ] Manual audit log entries
   - [ ] Audit log viewer UI

3. **RAI → RAIOPS Event Push**
   - [ ] Webhook endpoint in RAIOPS
   - [ ] Event dispatch from RAI on RAIOPS session actions
   - [ ] Retry logic for failed pushes

4. **Ghost User Cleanup**
   - [ ] Add `is_ghost_admin` column to RAI
   - [ ] Cleanup command
   - [ ] Schedule cleanup job

**Deliverables:**
- Role-based access in RAIOPS
- Full audit trail of all actions
- Ghost user lifecycle management

---

### Phase 5: Reports & Polish

**Goal:** Analytics, billing management, and production readiness

**Tasks:**

1. **Billing Management**
   - [ ] `subscription_plans` table and UI
   - [ ] `tenant_billing` table and UI
   - [ ] MRR dashboard
   - [ ] Billing reports

2. **Analytics & Reports**
   - [ ] Tenant growth report
   - [ ] User activity report
   - [ ] Revenue report
   - [ ] Export to CSV/Excel

3. **System Health Dashboard**
   - [ ] RDS health monitoring
   - [ ] Sync job status
   - [ ] Error log viewer

4. **Polish**
   - [ ] UI/UX improvements
   - [ ] Performance optimization
   - [ ] Documentation
   - [ ] Deployment guide

**Deliverables:**
- Complete billing management
- Comprehensive reports
- Production-ready system

---

## API Endpoints

### Internal API (RAIOPS)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/rds-instances` | List all RDS instances |
| POST | `/api/rds-instances` | Create RDS instance |
| PUT | `/api/rds-instances/{id}` | Update RDS instance |
| DELETE | `/api/rds-instances/{id}` | Delete RDS instance |
| POST | `/api/rds-instances/{id}/test` | Test connection |
| GET | `/api/tenants` | List all tenants (paginated) |
| GET | `/api/tenants/{id}` | Tenant detail (live query) |
| PUT | `/api/tenants/{id}` | Update tenant |
| GET | `/api/tenants/{id}/users` | List users for tenant (live) |
| GET | `/api/user-routing` | Search user routing |
| GET | `/api/audit-logs` | List audit logs |

### Webhook Endpoint (RAIOPS receives from RAI)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/webhooks/rai-audit` | Receive audit events from RAI |

---

## Services & Classes

### RAIOPS Services

| Service | Purpose |
|---------|---------|
| `RdsConnectionService` | Manage connections to RDS instances |
| `TenantSyncService` | Sync tenant data from RDS instances |
| `UserRoutingSyncService` | Sync user routing table |
| `ImpersonationTokenService` | Generate JWT tokens for impersonation |
| `AuditService` | Log admin actions |
| `HealthCheckService` | Monitor RDS health |

### RAI Services (Additions)

| Service | Purpose |
|---------|---------|
| `RaiOpsPermissionService` | Check RAIOPS session permissions |
| `RaiOpsAuditPushService` | Push audit events to RAIOPS |

---

## Configuration Requirements

### RAIOPS `.env`

```env
# Application
APP_NAME=RAIOPS
APP_URL=https://raiops.example.com

# Database (RAIOPS's own database)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=raiops
DB_USERNAME=raiops_user
DB_PASSWORD=secure_password

# Impersonation
RAIOPS_IMPERSONATION_SECRET=your-64-character-secret-key-shared-with-all-rai-instances

# Webhook (for receiving audit events from RAI)
RAIOPS_WEBHOOK_SECRET=another-secret-for-webhook-validation
```

### RAI `.env` (Additions)

```env
# RAIOPS Integration
RAIOPS_IMPERSONATION_SECRET=your-64-character-secret-key-shared-with-raiops
RAIOPS_APP_URL=https://raiops.example.com
RAIOPS_WEBHOOK_URL=https://raiops.example.com/api/webhooks/rai-audit
RAIOPS_WEBHOOK_SECRET=another-secret-for-webhook-validation

# This RAI instance's RDS ID (must match rds_instances.id in RAIOPS)
APP_RDS_INSTANCE_ID=1
```

---

## UI Components

### RDS Management

- **RDS List Page** (`/admin/rds`)
  - Table: Name, Host, Status, Tenant Count, Last Health Check
  - Actions: Edit, Test Connection, View Tenants
  - Add New button

- **RDS Edit Modal**
  - Fields: Name, Host, Port, Username, Password, RAI DB, Providers DB, App URL
  - Test Connection button
  - Save/Cancel

### Tenant Management

- **Tenant List Page** (`/admin/tenants`)
  - Filters: RDS Instance, Status, Search
  - Table: Name, RDS, Status, Users, Locations, Last Activity
  - Actions: View, Edit, Impersonate

- **Tenant Detail Page** (`/admin/tenants/{id}`)
  - Summary card with live data
  - Users tab (live query)
  - Locations tab (live query)
  - Billing tab
  - Activity log tab
  - "Manage in RAI" button

### User Routing

- **User Search** (`/admin/users`)
  - Search by email
  - Shows all routing entries for email
  - Can edit routing

### Audit Logs

- **Audit Log Viewer** (`/admin/audit`)
  - Filters: Admin, Action, Date Range, Tenant
  - Table: Date, Admin, Action, Target, Details
  - Detail modal with old/new values

---

## Security Considerations

1. **Impersonation Tokens**
   - Short expiry (5 minutes)
   - Single use recommended (add nonce)
   - Rate limited endpoint

2. **RDS Passwords**
   - Encrypted with Laravel Crypt
   - Never logged or displayed
   - Rotate periodically

3. **Shared Secrets**
   - 64+ character random strings
   - Store securely (not in git)
   - Rotate periodically

4. **Audit Trail**
   - All admin actions logged
   - Cannot be deleted by admins
   - Include IP and user agent

5. **Ghost Users**
   - Clearly marked (email pattern, flag)
   - Cannot login directly
   - Periodic cleanup

6. **Permission Enforcement**
   - Server-side checks always
   - Client-side hiding for UX only
   - Deny by default

---

## Appendix: Migration from Current RAIOPS

The existing RAIOPS project has:
- Basic tenant management (single RDS)
- Permission management
- User management

**Migration Steps:**

1. Run new migrations (additive, no breaking changes)
2. Seed RDS instance for current RAI database
3. Populate `tenant_master` from existing tenants
4. Update UI to show RDS context
5. Deploy RAI changes (impersonation endpoint)
6. Test impersonation flow
7. Enable sync jobs

---

**Document Version History:**

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-12-19 | AI Assistant | Initial specification |

---

*"Begin the day with a friendly voice, a companion unobtrusive"* - Rush, "The Spirit of Radio"

Ready to build Command Central! 🎸

