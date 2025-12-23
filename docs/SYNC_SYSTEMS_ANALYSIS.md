# 🔄 Sync Systems Analysis: user_email_routing, Tenant Sync, Password Sync

*"The Trees" - Understanding how data flows between RAIOPS and RAI*

## Overview

The system uses three main synchronization mechanisms to keep data consistent across multiple RDS instances:

1. **user_email_routing** - Central authentication directory
2. **Tenant Sync** - Keeps tenant summaries in RAIOPS up-to-date
3. **Password Sync** - Keeps passwords synchronized across tenants for multi-tenant users

---

## 1. user_email_routing System

### Purpose
Central directory that maps email addresses to their tenant and RDS instance, enabling authentication without connecting to each RDS individually.

### Architecture

**Master Table (RAI - Master RDS):**
- Table: `user_email_routing` (on master RDS, typically RDS1)
- Contains: email, tenant_id, rds_instance_id, password_hash, user_id, status
- **This is the source of truth for authentication**

**Cache Table (RAIOPS):**
- Table: `user_email_routing_cache` (in RAIOPS's database)
- Contains: email, tenant_master_id, remote_user_id, rds_instance_id, synced_at
- **This is a read-only cache for quick lookups in RAIOPS**

### How It Works

#### In RAI (Authentication):
1. **Login Flow** (`FortifyServiceProvider.php`):
   - User enters email/password
   - System queries `user_email_routing` for all entries with that email
   - Verifies password against each entry's `password_hash`
   - If ONE match → switches to that RDS, loads user, logs in
   - If MULTIPLE matches → stores routing info in session, redirects to tenant selector
   - If NO matches → falls back to legacy local users table

2. **User Observer** (`UserObserver.php`):
   - Automatically syncs user data to `user_email_routing` when:
     - User is created
     - User email, name, status, or tenant_id changes
     - User password changes
   - Uses `UserEmailRouting::updateOrCreateRouting()` to maintain the table

3. **Tenant Creation** (`TenantCreationService.php`):
   - When creating a tenant from RAIOPS, automatically creates entry in `user_email_routing`
   - Stores password hash, user_id, tenant_id, rds_instance_id

#### In RAIOPS (Management):
1. **Sync Command** (`SyncUserRouting.php`):
   - Command: `php artisan raiops:sync-user-routing`
   - Syncs data from master RDS `user_email_routing` → RAIOPS `user_email_routing_cache`
   - Used for quick lookups in RAIOPS UI without querying RDS
   - Can truncate cache with `--truncate` flag

2. **User Routing Management** (`UserRoutingManagement.php`):
   - Livewire component for looking up users by email
   - Checks cache first, then queries master RDS if not found
   - Shows which tenant/RDS the user belongs to

### Current Status
✅ **Working** - Authentication flow uses this correctly
✅ **Working** - UserObserver automatically maintains the table
✅ **Working** - Tenant creation creates routing entries
⚠️ **Note**: Cache sync needs to be run manually or scheduled

---

## 2. Tenant Sync System

### Purpose
Keeps the `tenant_master` table in RAIOPS synchronized with actual tenant data from each RDS instance.

### Architecture

**RAIOPS Table:**
- Table: `tenant_master` (in RAIOPS's database)
- Contains: name, primary_contact_name, primary_contact_email, status, cached_user_count, cached_location_count, cache_refreshed_at
- Links to: `rds_instance_id` + `remote_tenant_id` (unique combination)

**RDS Tables:**
- Table: `tenants` (on each RDS instance)
- Contains: Full tenant data including settings, subscriptions, etc.

### How It Works

#### Sync Command (`SyncTenantSummaries.php`):
- Command: `php artisan raiops:sync-tenant-summaries`
- Options:
  - `--rds=ID` - Sync from specific RDS only
  - `--force` - Force sync even if cache is fresh
- Process:
  1. Connects to each active RDS instance
  2. Fetches all tenants from that RDS
  3. For each tenant:
     - Gets user count and location count
     - Updates or creates entry in `tenant_master`
     - Sets `cache_refreshed_at` timestamp
  4. Skips tenants with fresh cache (unless `--force`)

#### Manual Sync (UI):
- `TenantMultiRds` component has:
  - `syncTenant($tenantId)` - Sync single tenant
  - `syncAllTenants()` - Sync all tenants from all RDS instances
- Updates cached counts and metadata

#### Auto-Sync Triggers:
- When viewing tenant details, `loadLiveData()` fetches fresh data
- Updates `cached_user_count` and `cached_location_count` in `tenant_master`

### Current Status
✅ **Working** - Sync commands function correctly
✅ **Working** - UI sync buttons work
✅ **Working** - Live data refresh updates cache
⚠️ **Note**: Sync needs to be run periodically or scheduled

---

## 3. Password Sync System

### Purpose
Keeps passwords synchronized across all tenants for users who have accounts on multiple tenants.

### Architecture

**Problem:**
- User `corte@hitpath.com` has accounts on Tenant 1 (RDS1) and Tenant 2 (RDS2)
- If they change password on Tenant 1, it should sync to Tenant 2
- Otherwise they'll have different passwords for different tenants (confusing!)

**Solution:**
- `PasswordSyncService` checks if passwords are in sync
- If not, allows user to sync password across all tenants
- Updates both `user_email_routing` entries AND actual user records

### How It Works

#### Password Sync Service (`PasswordSyncService.php`):

1. **Check Sync Status** (`checkPasswordSync()`):
   - Gets all `user_email_routing` entries for an email
   - Gets all user records for that email
   - Compares password hashes
   - Returns sync status and mismatched tenants

2. **Sync Password** (`syncPassword()`):
   - Verifies current password works for at least one tenant
   - Generates new password hash
   - Updates all `user_email_routing` entries
   - Updates all user records across all RDS instances
   - Returns success/error status

3. **Verify for Tenant** (`verifyPasswordForTenant()`):
   - Checks if password works for a specific tenant

#### Integration Points:

1. **Tenant Selector** (`TenantSelector.php`):
   - When user has multiple tenants, checks password sync status
   - Shows modal if passwords are out of sync
   - Allows user to sync passwords before selecting tenant

2. **User Profile** (`Profile.php`):
   - When user changes password, can sync across all tenants
   - Uses `PasswordSyncService` to update all accounts

3. **User Observer** (`UserObserver.php`):
   - When password changes on one tenant, syncs to `user_email_routing`
   - But does NOT sync to other tenant's user records (that's manual)

### Current Status
✅ **Working** - Service functions correctly
✅ **Working** - Tenant selector checks sync status
✅ **Working** - User can sync passwords manually
⚠️ **Note**: Automatic cross-tenant password sync is NOT automatic (by design for security)

---

## Data Flow Diagrams

### Authentication Flow:
```
User Login
    ↓
Query user_email_routing (master RDS)
    ↓
Verify password against routing entries
    ↓
If single match → Switch RDS → Load user → Login
If multiple matches → Store routings → Redirect to tenant selector
If no match → Fall back to legacy local users table
```

### User Creation/Update Flow:
```
User Created/Updated in RAI
    ↓
UserObserver triggered
    ↓
Sync to user_email_routing (master RDS)
    ↓
Entry created/updated with password_hash, user_id, tenant_id, rds_instance_id
```

### Tenant Sync Flow:
```
RAIOPS: Sync Command or UI Button
    ↓
Connect to RDS Instance
    ↓
Fetch tenants from RDS
    ↓
For each tenant:
    - Get user count
    - Get location count
    - Update tenant_master in RAIOPS
```

### Password Sync Flow:
```
User Changes Password
    ↓
PasswordSyncService.checkPasswordSync()
    ↓
If passwords out of sync:
    - Show sync modal
    - User enters current + new password
    - PasswordSyncService.syncPassword()
    - Updates all user_email_routing entries
    - Updates all user records across RDS instances
```

---

## Potential Issues & Recommendations

### 1. user_email_routing Cache Staleness
**Issue**: RAIOPS's `user_email_routing_cache` can become stale
**Solution**: 
- Schedule `raiops:sync-user-routing` to run periodically (e.g., every 15 minutes)
- Or trigger sync when looking up users in RAIOPS UI

### 2. Tenant Cache Staleness
**Issue**: `tenant_master` cached counts can be outdated
**Solution**:
- Schedule `raiops:sync-tenant-summaries` to run periodically (e.g., hourly)
- Or rely on live data refresh when viewing tenant details

### 3. Password Sync Not Automatic
**Issue**: Passwords don't automatically sync across tenants
**Current Behavior**: User must manually sync via Tenant Selector or Profile
**Recommendation**: This is actually good for security - keeps explicit user consent

### 4. UserObserver RDS Detection
**Issue**: `UserObserver::getRdsInstanceId()` may default to RDS1 if tenant not in TenantMaster
**Solution**: Ensure tenant sync runs before user creation, or improve RDS detection logic

### 5. Missing Routing Entries
**Issue**: Legacy users may not have entries in `user_email_routing`
**Solution**: 
- Migration command to backfill routing entries
- Or rely on legacy authentication fallback (already implemented)

---

## Commands Reference

### RAIOPS Commands:
```bash
# Sync user routing cache from master RDS
php artisan raiops:sync-user-routing [--truncate]

# Sync tenant summaries from all RDS instances
php artisan raiops:sync-tenant-summaries [--rds=ID] [--force]

# Sync ghost users to all RDS instances
php artisan sync:ghost-users [--admin=ID] [--rds=ID] [--dry-run]
```

### RAI Commands:
```bash
# Copy password hashes to routing table (one-time migration)
php artisan copy:password-hash-to-routing

# Sync user passwords to routing table
php artisan sync:user-passwords-to-routing
```

---

## Summary

**What's Working:**
- ✅ Authentication via `user_email_routing` 
- ✅ Automatic user sync to routing table via UserObserver
- ✅ Tenant sync commands and UI
- ✅ Password sync service and UI
- ✅ Multi-tenant login flow with tenant selector

**What Needs Attention:**
- ⚠️ Schedule sync commands to run automatically
- ⚠️ Consider backfilling routing entries for legacy users
- ⚠️ Monitor cache staleness in production

**Overall Assessment:**
The sync systems are **mostly working** but rely on manual or scheduled sync commands. The authentication flow is solid, and the password sync provides good UX for multi-tenant users. The main improvement would be automating the sync schedules.

