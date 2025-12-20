# RAINBO Multi-RDS Command Central - Handoff Document

**Date:** December 20, 2025  
**Branch:** `master`  
**Status:** Phase 1 Complete - Ready for Phase 2

---

## 🎯 Project Overview

**RAINBO** (RAI Back Office) is the administrative command central for managing the multi-tenant, multi-RDS RAI restaurant management system. It provides system administrators with tools to:

- Manage RDS instances across the infrastructure
- Monitor tenant health and status across all RDS
- Manage billing and subscriptions
- Perform cross-RDS operations
- Impersonate tenants for support (Phase 3)

### Key Technologies
- **Laravel 11** (PHP 8.3)
- **Livewire 3** for interactive components
- **Jetstream** for authentication scaffolding
- **Bootstrap 5.3** for UI
- **MySQL** database

### Project Locations
- **Linux:** `/var/www/html/rainbo`
- **Windows (WSL):** `z:\var\www\html\rainbo`

---

## ✅ What's Been Completed (Phase 1)

### Database Schema

```
rainbo (database)
├── rds_instances           ✅ RDS configurations with encrypted passwords
├── tenant_master           ✅ Central tenant registry across all RDS
├── audit_logs              ✅ Admin action tracking
├── rainbo_permissions      ✅ Permission definitions
├── rainbo_role_permissions ✅ Role-permission mappings
├── subscription_plans      ✅ Plan tiers (Starter, Professional, Enterprise)
├── tenant_billing          ✅ Billing info per tenant
├── user_email_routing_cache ✅ Synced routing data
└── users                   ✅ RAINBO admin users (with role column)
```

### Models Created

| Model | Location | Purpose |
|-------|----------|---------|
| `RdsInstance` | `app/Models/RdsInstance.php` | RDS config with encrypted password, connection testing |
| `TenantMaster` | `app/Models/TenantMaster.php` | Central tenant registry with caching |
| `AuditLog` | `app/Models/AuditLog.php` | Action logging with `AuditLog::log()` helper |
| `TenantBilling` | `app/Models/TenantBilling.php` | Billing information |
| `SubscriptionPlan` | `app/Models/SubscriptionPlan.php` | Plan definitions |
| `UserEmailRoutingCache` | `app/Models/UserEmailRoutingCache.php` | Cached routing data |
| `RainboPermission` | `app/Models/RainboPermission.php` | Permission helper methods |

### Services

| Service | Location | Purpose |
|---------|----------|---------|
| `RdsConnectionService` | `app/Services/RdsConnectionService.php` | Dynamic connections to RDS instances, health checks, queries |

### Livewire Components

| Component | Route | Purpose |
|-----------|-------|---------|
| `RdsManagement` | `/admin/rds` | Full CRUD for RDS instances |

### Seeders

| Seeder | Purpose |
|--------|---------|
| `SystemAdminSeeder` | Creates `admin@rainbo.local` system admin |
| `RdsInstanceSeeder` | Configures Master RDS from `RAI_DB_*` env vars |
| `TenantMasterSyncSeeder` | Syncs tenants from all RDS to `tenant_master` |

---

## 🔐 Authentication & Authorization

### Login Credentials (Development)
- **Email:** `admin@rainbo.local`
- **Password:** `password`
- **Role:** `system_admin`

### Role System

| Role | Permissions |
|------|-------------|
| `system_admin` | All permissions (god mode) |
| `support_admin` | tenant.view/edit/impersonate, user.view/edit/password-reset, audit.view, rds.view |
| `billing_admin` | tenant.view, billing.view/edit, reports.view/export |
| `read_only` | View-only access to all areas |

### Permission Checking

```php
// In User model
$user->hasRainboPermission('tenant.view')  // Check specific permission
$user->getRainboPermissions()              // Get all permissions for role
$user->isSystemAdmin()                     // Check if system admin
```

### Middleware

The `CheckPermission` middleware (`app/Http/Middleware/CheckPermission.php`) handles route-level permission checks:

```php
Route::get('/admin/rds', RdsManagement::class)
    ->middleware('check.permission:rds.manage');
```

---

## 🗄️ RDS Connection Architecture

### How It Works

1. **RdsInstance model** stores encrypted connection details
2. **RdsConnectionService** dynamically configures Laravel database connections
3. Connections are named `rds_{id}` (e.g., `rds_1`, `rds_2`)
4. Each RDS can have a RAI database and optional Providers database

### Key Methods

```php
$service = app(RdsConnectionService::class);

// Get a connection to an RDS
$connection = $service->getConnection($rdsInstance);

// Query directly
$tenants = $service->query($rdsInstance)->table('tenants')->get();

// Run with connection
$service->withConnection($rdsInstance, function ($db) {
    return $db->table('users')->count();
});

// Health checks
$results = $service->runHealthChecks(); // All RDS
$rdsInstance->testConnection();          // Single RDS
```

### Password Encryption

RDS passwords are encrypted using Laravel's `Crypt` facade:
- **Set via Eloquent:** `$rds->password = 'plain-text'` (auto-encrypts)
- **Get via Eloquent:** `$rds->password` (auto-decrypts)
- **Never use MySQL's CRYPT()** - it's incompatible

---

## 📁 Key File Locations

### Configuration
- `.env` - Environment variables (DB connections, RAI connection)
- `config/database.php` - Database connections including `rai` connection

### Routes
- `routes/web.php` - All routes (dashboard redirects to `/admin/rds`)

### Layouts
- `resources/views/layouts/rai.blade.php` - Main admin layout

### Livewire Components
- `app/Livewire/Admin/RdsManagement.php` - RDS CRUD
- `resources/views/livewire/admin/rds-management.blade.php` - RDS UI

### Models
- `app/Models/` - All Eloquent models

### Services
- `app/Services/RdsConnectionService.php` - Multi-RDS connections

---

## 🚀 Getting Started

### Start the Server

```bash
cd /var/www/html/rainbo
php artisan serve --port=8001
```

Visit: `http://localhost:8001`

### Run Migrations (if needed)

```bash
php artisan migrate
```

### Run Seeders (if needed)

```bash
# Full seed (system admin + RDS + tenant sync)
php artisan db:seed

# Individual seeders
php artisan db:seed --class=SystemAdminSeeder
php artisan db:seed --class=RdsInstanceSeeder
php artisan db:seed --class=TenantMasterSyncSeeder
```

### Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

---

## 📋 Implementation Phases

### ✅ Phase 1: Foundation (COMPLETE)
- [x] Database migrations
- [x] Models with relationships
- [x] RdsConnectionService
- [x] RDS Management UI
- [x] RBAC permissions system
- [x] Initial seeders

### ⏳ Phase 2: Cross-RDS Operations (NEXT)
- [ ] Tenant Management page with RDS indicator
- [ ] Tenant detail view with live data from correct RDS
- [ ] User routing management (`user_email_routing` viewer/editor)
- [ ] Sync commands (`rainbo:sync-tenant-summaries`, etc.)
- [ ] Cross-RDS command framework

### 🔜 Phase 3: Impersonation Flow
- [ ] JWT token generation in RAINBO
- [ ] `/rainbo-impersonate` endpoint in RAI
- [ ] Ghost user creation/management
- [ ] "Return to RAINBO" UI in RAI
- [ ] Permission enforcement during impersonation

### 🔜 Phase 4: Audit & Polish
- [ ] Audit log viewer UI
- [ ] Event push from RAI → RAINBO
- [ ] Ghost user cleanup command
- [ ] Full permission enforcement

### 🔜 Phase 5: Reports & Billing
- [ ] Cross-tenant analytics
- [ ] Billing management UI
- [ ] MRR dashboard
- [ ] System health dashboard

---

## 🔗 Related Documents

| Document | Location | Purpose |
|----------|----------|---------|
| Full Architecture Spec | `docs/RAINBO_MULTI_RDS_SPEC.md` | Detailed spec with all schemas, flows, code examples |
| Original RAINBO Handoff | `HANDOFF.md` | Pre-multi-RDS documentation |
| RAI Multi-RDS Handoff | `/var/www/html/rai/HANDOFF_MULTI_RDS.md` | RAI-side multi-RDS status |

---

## ⚠️ Important Notes

### Database Passwords
- RAINBO uses **Laravel Crypt** for RDS passwords
- Never manually insert encrypted passwords
- Always set via Eloquent: `$rds->password = 'plain-text'`

### Separate Auth System
- RAINBO has its **own users table** - not RAI users
- Login to RAINBO with `admin@rainbo.local`, not RAI credentials
- Future: Impersonation will use JWT tokens to RAI

### RDS Health Checks
- Health status: `healthy`, `degraded`, `down`, `unknown`
- Run `$rdsInstance->updateHealthStatus()` to refresh
- UI has "Refresh All Health" button

### Audit Logging
- Use `AuditLog::log()` for tracking actions
- Logs include: user, action, model, old/new values, IP, user agent

---

## 🐛 Known Issues / TODOs

1. **Tenant Management UI** - Exists but needs updating for multi-RDS context
2. **Sidebar Menu** - Currently hardcoded, needs RDS Management link added
3. **User sync** - `user_email_routing_cache` sync not yet implemented
4. **No remote configured** - Git repo is local only (no GitHub remote yet)

---

## 🎸 Quick Reference

### Common Commands

```bash
# Start dev server
php artisan serve --port=8001

# Run migrations
php artisan migrate

# Sync tenants from all RDS
php artisan db:seed --class=TenantMasterSyncSeeder

# Clear all caches
php artisan optimize:clear

# Check RDS health (via tinker)
php artisan tinker
>>> App\Models\RdsInstance::first()->testConnection()
```

### Testing RDS Connection

```php
// In tinker or code
$rds = App\Models\RdsInstance::first();
$result = $rds->testConnection();
// Returns: ['success' => true/false, 'message' => '...', 'latency_ms' => 3]
```

### Adding a New RDS

1. Go to `/admin/rds`
2. Click "Add RDS Instance"
3. Fill in connection details
4. Click "Test Connection" to verify
5. Save

Or via tinker:
```php
App\Models\RdsInstance::create([
    'name' => 'Production RDS 2',
    'host' => 'db2.example.com',
    'port' => 3306,
    'username' => 'rai_user',
    'password' => 'secret',  // Auto-encrypted
    'rai_database' => 'rai_production',
    'app_url' => 'https://app2.example.com',
    'is_active' => true,
]);
```

---

## 📞 Context for Next Session

**What we just finished:**
- Built complete Phase 1 of RAINBO multi-RDS support
- RDS Management UI is working and tested
- Master RDS connected successfully (3ms latency)
- 1 tenant synced from RAI database

**What to work on next (Phase 2):**
1. Update Tenant Management to show RDS context
2. Build tenant detail page with live RDS queries
3. Add sync commands for automated data refresh
4. Build user routing management UI

**Key architectural decisions made:**
- RAINBO has separate auth (own users table)
- RAINBO DB is source of truth for platform data
- Hybrid data strategy (cached summaries + live queries)
- JWT-based impersonation (Phase 3)
- Audit logging in both apps with event push

---

**Last Updated:** December 20, 2025  
**Git Commit:** `504af09` - Phase 1: Multi-RDS Foundation

*"The spaces in between leave room for you and I to grow"* 🎸

