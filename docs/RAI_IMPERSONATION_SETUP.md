# RAI Impersonation Endpoint Setup

**"Roll the bones!"** 🎲 - Rush

This document describes what needs to be implemented on the RAI (tenant) side to support RAIOPS impersonation.

---

## Overview

When a RAIOPS admin clicks "Manage in RAI" on a tenant, RAIOPS generates a signed JWT token and redirects to the RAI instance. RAI needs to:

1. Validate the JWT token
2. Create or retrieve a "ghost admin" user
3. Establish an authenticated session
4. Display a visual indicator showing it's a RAIOPS session
5. Provide a "Return to RAIOPS" button

---

## Step 1: Environment Configuration

Add these to RAI's `.env`:

```env
# RAIOPS Integration
RAIOPS_IMPERSONATION_SECRET=<same-64-char-secret-as-raiops>
RAIOPS_APP_URL=https://raiops.example.com

# This RAI instance's RDS ID (must match rds_instances.id in RAIOPS)
APP_RDS_INSTANCE_ID=1
```

Add config file (`config/raiops.php`):

```php
<?php

return [
    'impersonation_secret' => env('RAIOPS_IMPERSONATION_SECRET'),
    'app_url' => env('RAIOPS_APP_URL', 'https://raiops.example.com'),
    'rds_instance_id' => env('APP_RDS_INSTANCE_ID'),
];
```

---

## Step 2: Add Ghost Admin Flag to Users Table

Create migration:

```bash
php artisan make:migration add_ghost_admin_to_users_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_ghost_admin')->default(false)->after('is_super_admin');
            $table->unsignedBigInteger('raiops_admin_id')->nullable()->after('is_ghost_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_ghost_admin', 'raiops_admin_id']);
        });
    }
};
```

---

## Step 3: Install JWT Library

```bash
composer require firebase/php-jwt
```

---

## Step 4: Create Impersonation Controller

File: `app/Http/Controllers/RaiOpsImpersonationController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RaiOpsImpersonationController extends Controller
{
    public function handle(Request $request)
    {
        $token = $request->query('token');
        
        if (!$token) {
            abort(400, 'Missing impersonation token');
        }

        try {
            // Decode and validate JWT
            $payload = JWT::decode(
                $token, 
                new Key(config('raiops.impersonation_secret'), 'HS256')
            );
            
            // Validate this is the correct RDS instance
            $expectedRdsId = (int) config('raiops.rds_instance_id');
            if ($payload->rds_instance_id !== $expectedRdsId) {
                Log::warning('RAIOPS impersonation: wrong RDS instance', [
                    'expected' => $expectedRdsId,
                    'received' => $payload->rds_instance_id,
                    'admin_id' => $payload->raiops_admin_id ?? null,
                ]);
                abort(403, 'Invalid RDS instance for this deployment');
            }
            
            // Find ghost admin user (must be pre-created via sync:ghost-users)
            $ghostUser = $this->findGhostAdmin($payload);
            
            if (!$ghostUser) {
                Log::error('RAIOPS impersonation: ghost user not found', [
                    'raiops_admin_id' => $payload->raiops_admin_id,
                    'rds_instance_id' => $payload->rds_instance_id ?? null,
                ]);
                
                // Return user-friendly error page instead of 500 error
                return view('errors.ghost-user-missing', [
                    'raiops_admin_id' => $payload->raiops_admin_id,
                    'rds_instance_id' => $payload->rds_instance_id ?? null,
                    'return_url' => $payload->return_url ?? config('raiops.app_url'),
                ])->with('error', 'Ghost user not found');
            }
            
            // Log them in
            Auth::login($ghostUser);
            
            // Store RAIOPS session context
            session([
                'is_raiops_session' => true,
                'raiops_admin_id' => $payload->raiops_admin_id,
                'raiops_admin_email' => $payload->raiops_admin_email,
                'raiops_admin_name' => $payload->raiops_admin_name ?? 'RAIOPS Admin',
                'raiops_permissions' => $payload->permissions ?? [],
                'raiops_return_url' => $payload->return_url,
                'impersonated_tenant_id' => $payload->remote_tenant_id,
                'selected_tenant_id' => $payload->remote_tenant_id,
            ]);
            
            // Log successful impersonation
            Log::info('RAIOPS impersonation successful', [
                'raiops_admin_id' => $payload->raiops_admin_id,
                'raiops_admin_email' => $payload->raiops_admin_email,
                'tenant_id' => $payload->remote_tenant_id,
                'ghost_user_id' => $ghostUser->id,
                'ip' => $request->ip(),
            ]);
            
            // Redirect to dashboard or tenant-specific page
            return redirect('/dashboard')
                ->with('success', 'Welcome, RAIOPS Admin. You are managing tenant ID: ' . $payload->remote_tenant_id);
            
        } catch (ExpiredException $e) {
            Log::warning('RAIOPS impersonation: expired token', [
                'ip' => $request->ip(),
            ]);
            abort(403, 'Impersonation token has expired. Please try again from RAIOPS.');
            
        } catch (\UnexpectedValueException $e) {
            Log::warning('RAIOPS impersonation: invalid token', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            abort(403, 'Invalid impersonation token');
            
        } catch (\Exception $e) {
            Log::error('RAIOPS impersonation failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            abort(500, 'Impersonation failed. Please try again.');
        }
    }
    
    /**
     * End RAIOPS session and return to RAIOPS
     */
    public function returnToRaiOps(Request $request)
    {
        $returnUrl = session('raiops_return_url', config('raiops.app_url'));
        
        // Log the session end
        if (session('is_raiops_session')) {
            Log::info('RAIOPS session ended', [
                'raiops_admin_id' => session('raiops_admin_id'),
                'duration_seconds' => now()->diffInSeconds(session('raiops_session_started_at', now())),
            ]);
        }
        
        // Clear RAIOPS session data
        session()->forget([
            'is_raiops_session',
            'raiops_admin_id',
            'raiops_admin_email',
            'raiops_admin_name',
            'raiops_permissions',
            'raiops_return_url',
            'raiops_session_started_at',
            'impersonated_tenant_id',
        ]);
        
        // Log out the ghost user
        Auth::logout();
        
        return redirect($returnUrl);
    }
    
    /**
     * Find ghost admin user for RAIOPS impersonation
     * 
     * Ghost users must be pre-created using: php artisan sync:ghost-users
     * This method only finds existing users, it does not create them.
     */
    protected function findGhostAdmin(object $payload): ?User
    {
        // Use a predictable email pattern for ghost admins
        $email = "raiops-admin-{$payload->raiops_admin_id}@system.internal";
        
        $user = User::where('email', $email)
            ->where('is_ghost_admin', true)
            ->where('raiops_admin_id', $payload->raiops_admin_id)
            ->first();
        
        if ($user) {
            // Update the ghost user's tenant_id for this impersonation session
            if (isset($payload->remote_tenant_id)) {
                $user->update(['tenant_id' => $payload->remote_tenant_id]);
            }
            
            // Ensure correct settings
            $updates = [];
            if (!$user->is_super_admin) {
                $updates['is_super_admin'] = true;
            }
            if ($user->status !== 'Active') {
                $updates['status'] = 'Active';
            }
            if ($user->location_access !== 'All') {
                $updates['location_access'] = 'All';
            }
            
            if (!empty($updates)) {
                $user->update($updates);
            }
            
            // Touch updated_at for cleanup tracking
            $user->touch();
        }
        
        return $user;
    }
}
```

---

## Step 5: Create Error View for Missing Ghost Users

Create a user-friendly error page that explains what needs to be done.

File: `resources/views/errors/ghost-user-missing.blade.php`

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ghost User Not Found - RAIOPS Impersonation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Ghost User Not Found
                        </h4>
                    </div>
                    <div class="card-body">
                        <p class="lead">
                            The ghost user for RAIOPS admin impersonation has not been created on this RDS instance.
                        </p>
                        
                        <div class="alert alert-info">
                            <h5><i class="bi bi-info-circle me-2"></i>What This Means</h5>
                            <p class="mb-0">
                                Before RAIOPS admins can impersonate into this RAI instance, ghost users must be 
                                created for each RAIOPS admin account. This is a one-time setup step.
                            </p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h5><i class="bi bi-tools me-2"></i>How to Fix This</h5>
                            <p><strong>On the RAIOPS server, run:</strong></p>
                            <pre class="bg-dark text-light p-3 rounded"><code>php artisan sync:ghost-users</code></pre>
                            
                            <p class="mt-3">Or to sync a specific admin to this RDS instance:</p>
                            <pre class="bg-dark text-light p-3 rounded"><code>php artisan sync:ghost-users --admin={{ $raiops_admin_id ?? 'ID' }} --rds={{ $rds_instance_id ?? 'ID' }}</code></pre>
                            
                            <p class="mt-3 mb-0">
                                <small>
                                    <strong>Admin ID:</strong> {{ $raiops_admin_id ?? 'N/A' }}<br>
                                    <strong>RDS Instance ID:</strong> {{ $rds_instance_id ?? 'N/A' }}
                                </small>
                            </p>
                        </div>
                        
                        <div class="d-grid gap-2">
                            @if(isset($return_url))
                            <a href="{{ $return_url }}" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-2"></i>
                                Return to RAIOPS
                            </a>
                            @endif
                            <a href="/" class="btn btn-outline-secondary">
                                <i class="bi bi-house me-2"></i>
                                Go to Homepage
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center text-muted">
                    <small>
                        <i class="bi bi-shield-lock me-1"></i>
                        This is a security feature to ensure proper setup before allowing impersonation.
                    </small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
```

---

## Step 6: Add Routes

In `routes/web.php`:

```php
use App\Http\Controllers\RaiOpsImpersonationController;

// RAIOPS Impersonation
Route::get('/raiops-impersonate', [RaiOpsImpersonationController::class, 'handle'])
    ->middleware('throttle:10,1') // Rate limit: 10 requests per minute
    ->name('raiops.impersonate');

Route::post('/raiops-return', [RaiOpsImpersonationController::class, 'returnToRaiOps'])
    ->middleware('auth')
    ->name('raiops.return');
```

---

## Step 6: Create RAIOPS Session Indicator Component

Create a Livewire component or Blade partial that shows when in a RAIOPS session.

File: `resources/views/components/raiops-session-bar.blade.php`

```blade
@if(session('is_raiops_session'))
<div class="raiops-session-bar bg-warning text-dark py-2 px-3 d-flex justify-content-between align-items-center">
    <div>
        <i class="bi bi-lightning-charge-fill me-2"></i>
        <strong>RAIOPS Session</strong> 
        <span class="ms-2">
            Logged in as {{ session('raiops_admin_name', 'RAIOPS Admin') }}
            ({{ session('raiops_admin_email') }})
        </span>
        <span class="badge bg-dark ms-2">
            Tenant ID: {{ session('impersonated_tenant_id') }}
        </span>
    </div>
    <form action="{{ route('raiops.return') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-dark btn-sm">
            <i class="bi bi-arrow-left me-1"></i>
            Return to RAIOPS
        </button>
    </form>
</div>

<style>
.raiops-session-bar {
    position: sticky;
    top: 0;
    z-index: 1050;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
@endif
```

Include in your main layout:

```blade
<body>
    <x-raiops-session-bar />
    
    {{-- Rest of your layout --}}
</body>
```

---

## Step 7: Permission Service (Optional Enhancement)

If you want to enforce RAIOPS-specific permissions within RAI:

File: `app/Services/RaiOpsPermissionService.php`

```php
<?php

namespace App\Services;

class RaiOpsPermissionService
{
    public function isRaiOpsSession(): bool
    {
        return session('is_raiops_session', false);
    }
    
    public function canDo(string $permission): bool
    {
        if (!$this->isRaiOpsSession()) {
            return true; // Not a RAIOPS session, normal permissions apply
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
    
    public function getAdminEmail(): ?string
    {
        return session('raiops_admin_email');
    }
    
    public function getReturnUrl(): ?string
    {
        return session('raiops_return_url');
    }
}
```

---

## Step 8: Ghost User Cleanup Command

File: `app/Console/Commands/CleanupGhostAdmins.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CleanupGhostAdmins extends Command
{
    protected $signature = 'raiops:cleanup-ghost-admins {--days=90 : Days of inactivity before cleanup}';
    protected $description = 'Clean up inactive RAIOPS ghost admin users';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        
        $count = User::where('is_ghost_admin', true)
            ->where('updated_at', '<', now()->subDays($days))
            ->count();
        
        if ($count === 0) {
            $this->info('No ghost admin users to clean up.');
            return self::SUCCESS;
        }
        
        if (!$this->confirm("Found {$count} ghost admin users inactive for {$days}+ days. Delete them?")) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }
        
        $deleted = User::where('is_ghost_admin', true)
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();
        
        $this->info("Deleted {$deleted} ghost admin users.");
        
        return self::SUCCESS;
    }
}
```

Schedule it in `app/Console/Kernel.php`:

```php
$schedule->command('raiops:cleanup-ghost-admins --days=90')
    ->weekly()
    ->runInBackground();
```

---

## Step 9: Security Checklist

- [ ] **Shared Secret**: Ensure `RAIOPS_IMPERSONATION_SECRET` is identical in both RAIOPS and RAI
- [ ] **HTTPS**: Both RAIOPS and RAI must use HTTPS in production
- [ ] **RDS Instance ID**: Verify `APP_RDS_INSTANCE_ID` matches the ID in RAIOPS's `rds_instances` table
- [ ] **Rate Limiting**: The impersonation endpoint should be rate-limited
- [ ] **Audit Logging**: Log all impersonation events for security review
- [ ] **Ghost User Pattern**: Ghost users use `@system.internal` domain - ensure this doesn't conflict

---

## JWT Token Payload Reference

When RAI decodes the token, it will contain:

```json
{
    "raiops_admin_id": 1,
    "raiops_admin_email": "admin@example.com",
    "raiops_admin_name": "Admin Name",
    "tenant_master_id": 42,
    "remote_tenant_id": 5,
    "rds_instance_id": 2,
    "permissions": [
        "tenant.view",
        "tenant.edit",
        "user.view"
    ],
    "return_url": "https://raiops.example.com/admin/tenants?viewDetails=42",
    "nonce": "random32charstring",
    "iat": 1734567890,
    "exp": 1734568190
}
```

---

## Testing

1. Set up environment variables
2. Run migration
3. Try impersonating from RAIOPS
4. Verify ghost user is created
5. Verify session bar appears
6. Test "Return to RAIOPS" button
7. Verify cleanup command works

---

*"Living in the limelight, the universal dream"* - Rush, "Limelight"

Happy impersonating! 🎸

