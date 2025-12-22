# RAI Impersonation Endpoint Setup

**"Roll the bones!"** 🎲 - Rush

This document describes what needs to be implemented on the RAI (tenant) side to support RAINBO impersonation.

---

## Overview

When a RAINBO admin clicks "Manage in RAI" on a tenant, RAINBO generates a signed JWT token and redirects to the RAI instance. RAI needs to:

1. Validate the JWT token
2. Create or retrieve a "ghost admin" user
3. Establish an authenticated session
4. Display a visual indicator showing it's a RAINBO session
5. Provide a "Return to RAINBO" button

---

## Step 1: Environment Configuration

Add these to RAI's `.env`:

```env
# RAINBO Integration
RAINBO_IMPERSONATION_SECRET=<same-64-char-secret-as-rainbo>
RAINBO_APP_URL=https://rainbo.example.com

# This RAI instance's RDS ID (must match rds_instances.id in RAINBO)
APP_RDS_INSTANCE_ID=1
```

Add config file (`config/rainbo.php`):

```php
<?php

return [
    'impersonation_secret' => env('RAINBO_IMPERSONATION_SECRET'),
    'app_url' => env('RAINBO_APP_URL', 'https://rainbo.example.com'),
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
            $table->unsignedBigInteger('rainbo_admin_id')->nullable()->after('is_ghost_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_ghost_admin', 'rainbo_admin_id']);
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

File: `app/Http/Controllers/RainboImpersonationController.php`

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

class RainboImpersonationController extends Controller
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
                new Key(config('rainbo.impersonation_secret'), 'HS256')
            );
            
            // Validate this is the correct RDS instance
            $expectedRdsId = (int) config('rainbo.rds_instance_id');
            if ($payload->rds_instance_id !== $expectedRdsId) {
                Log::warning('RAINBO impersonation: wrong RDS instance', [
                    'expected' => $expectedRdsId,
                    'received' => $payload->rds_instance_id,
                    'admin_id' => $payload->rainbo_admin_id ?? null,
                ]);
                abort(403, 'Invalid RDS instance for this deployment');
            }
            
            // Find or create ghost admin user
            $ghostUser = $this->findOrCreateGhostAdmin($payload);
            
            // Log them in
            Auth::login($ghostUser);
            
            // Store RAINBO session context
            session([
                'is_rainbo_session' => true,
                'rainbo_admin_id' => $payload->rainbo_admin_id,
                'rainbo_admin_email' => $payload->rainbo_admin_email,
                'rainbo_admin_name' => $payload->rainbo_admin_name ?? 'RAINBO Admin',
                'rainbo_permissions' => $payload->permissions ?? [],
                'rainbo_return_url' => $payload->return_url,
                'impersonated_tenant_id' => $payload->remote_tenant_id,
                'selected_tenant_id' => $payload->remote_tenant_id,
            ]);
            
            // Log successful impersonation
            Log::info('RAINBO impersonation successful', [
                'rainbo_admin_id' => $payload->rainbo_admin_id,
                'rainbo_admin_email' => $payload->rainbo_admin_email,
                'tenant_id' => $payload->remote_tenant_id,
                'ghost_user_id' => $ghostUser->id,
                'ip' => $request->ip(),
            ]);
            
            // Redirect to dashboard or tenant-specific page
            return redirect('/dashboard')
                ->with('success', 'Welcome, RAINBO Admin. You are managing tenant ID: ' . $payload->remote_tenant_id);
            
        } catch (ExpiredException $e) {
            Log::warning('RAINBO impersonation: expired token', [
                'ip' => $request->ip(),
            ]);
            abort(403, 'Impersonation token has expired. Please try again from RAINBO.');
            
        } catch (\UnexpectedValueException $e) {
            Log::warning('RAINBO impersonation: invalid token', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            abort(403, 'Invalid impersonation token');
            
        } catch (\Exception $e) {
            Log::error('RAINBO impersonation failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            abort(500, 'Impersonation failed. Please try again.');
        }
    }
    
    /**
     * End RAINBO session and return to RAINBO
     */
    public function returnToRainbo(Request $request)
    {
        $returnUrl = session('rainbo_return_url', config('rainbo.app_url'));
        
        // Log the session end
        if (session('is_rainbo_session')) {
            Log::info('RAINBO session ended', [
                'rainbo_admin_id' => session('rainbo_admin_id'),
                'duration_seconds' => now()->diffInSeconds(session('rainbo_session_started_at', now())),
            ]);
        }
        
        // Clear RAINBO session data
        session()->forget([
            'is_rainbo_session',
            'rainbo_admin_id',
            'rainbo_admin_email',
            'rainbo_admin_name',
            'rainbo_permissions',
            'rainbo_return_url',
            'rainbo_session_started_at',
            'impersonated_tenant_id',
        ]);
        
        // Log out the ghost user
        Auth::logout();
        
        return redirect($returnUrl);
    }
    
    /**
     * Find or create a ghost admin user for RAINBO impersonation
     */
    protected function findOrCreateGhostAdmin(object $payload): User
    {
        // Use a predictable email pattern for ghost admins
        $email = "rainbo-admin-{$payload->rainbo_admin_id}@system.internal";
        
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => "RAINBO Admin #{$payload->rainbo_admin_id}",
                'password' => Hash::make(Str::random(64)), // Unguessable password
                'is_super_admin' => true,
                'is_ghost_admin' => true,
                'rainbo_admin_id' => $payload->rainbo_admin_id,
                'tenant_id' => null, // Super admin, no tenant restriction
                'status' => 'Active',
            ]
        );
        
        // Update the ghost user's rainbo_admin_id if it changed
        if ($user->rainbo_admin_id !== $payload->rainbo_admin_id) {
            $user->update(['rainbo_admin_id' => $payload->rainbo_admin_id]);
        }
        
        // Touch updated_at for cleanup tracking
        $user->touch();
        
        return $user;
    }
}
```

---

## Step 5: Add Routes

In `routes/web.php`:

```php
use App\Http\Controllers\RainboImpersonationController;

// RAINBO Impersonation
Route::get('/rainbo-impersonate', [RainboImpersonationController::class, 'handle'])
    ->middleware('throttle:10,1') // Rate limit: 10 requests per minute
    ->name('rainbo.impersonate');

Route::post('/rainbo-return', [RainboImpersonationController::class, 'returnToRainbo'])
    ->middleware('auth')
    ->name('rainbo.return');
```

---

## Step 6: Create RAINBO Session Indicator Component

Create a Livewire component or Blade partial that shows when in a RAINBO session.

File: `resources/views/components/rainbo-session-bar.blade.php`

```blade
@if(session('is_rainbo_session'))
<div class="rainbo-session-bar bg-warning text-dark py-2 px-3 d-flex justify-content-between align-items-center">
    <div>
        <i class="bi bi-lightning-charge-fill me-2"></i>
        <strong>RAINBO Session</strong> 
        <span class="ms-2">
            Logged in as {{ session('rainbo_admin_name', 'RAINBO Admin') }}
            ({{ session('rainbo_admin_email') }})
        </span>
        <span class="badge bg-dark ms-2">
            Tenant ID: {{ session('impersonated_tenant_id') }}
        </span>
    </div>
    <form action="{{ route('rainbo.return') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-dark btn-sm">
            <i class="bi bi-arrow-left me-1"></i>
            Return to RAINBO
        </button>
    </form>
</div>

<style>
.rainbo-session-bar {
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
    <x-rainbo-session-bar />
    
    {{-- Rest of your layout --}}
</body>
```

---

## Step 7: Permission Service (Optional Enhancement)

If you want to enforce RAINBO-specific permissions within RAI:

File: `app/Services/RainboPermissionService.php`

```php
<?php

namespace App\Services;

class RainboPermissionService
{
    public function isRainboSession(): bool
    {
        return session('is_rainbo_session', false);
    }
    
    public function canDo(string $permission): bool
    {
        if (!$this->isRainboSession()) {
            return true; // Not a RAINBO session, normal permissions apply
        }
        
        $permissions = session('rainbo_permissions', []);
        return in_array($permission, $permissions);
    }
    
    public function denyUnlessAllowed(string $permission): void
    {
        if (!$this->canDo($permission)) {
            abort(403, 'RAINBO permission denied: ' . $permission);
        }
    }
    
    public function getAdminEmail(): ?string
    {
        return session('rainbo_admin_email');
    }
    
    public function getReturnUrl(): ?string
    {
        return session('rainbo_return_url');
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
    protected $signature = 'rainbo:cleanup-ghost-admins {--days=90 : Days of inactivity before cleanup}';
    protected $description = 'Clean up inactive RAINBO ghost admin users';

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
$schedule->command('rainbo:cleanup-ghost-admins --days=90')
    ->weekly()
    ->runInBackground();
```

---

## Step 9: Security Checklist

- [ ] **Shared Secret**: Ensure `RAINBO_IMPERSONATION_SECRET` is identical in both RAINBO and RAI
- [ ] **HTTPS**: Both RAINBO and RAI must use HTTPS in production
- [ ] **RDS Instance ID**: Verify `APP_RDS_INSTANCE_ID` matches the ID in RAINBO's `rds_instances` table
- [ ] **Rate Limiting**: The impersonation endpoint should be rate-limited
- [ ] **Audit Logging**: Log all impersonation events for security review
- [ ] **Ghost User Pattern**: Ghost users use `@system.internal` domain - ensure this doesn't conflict

---

## JWT Token Payload Reference

When RAI decodes the token, it will contain:

```json
{
    "rainbo_admin_id": 1,
    "rainbo_admin_email": "admin@example.com",
    "rainbo_admin_name": "Admin Name",
    "tenant_master_id": 42,
    "remote_tenant_id": 5,
    "rds_instance_id": 2,
    "permissions": [
        "tenant.view",
        "tenant.edit",
        "user.view"
    ],
    "return_url": "https://rainbo.example.com/admin/tenants?viewDetails=42",
    "nonce": "random32charstring",
    "iat": 1734567890,
    "exp": 1734568190
}
```

---

## Testing

1. Set up environment variables
2. Run migration
3. Try impersonating from RAINBO
4. Verify ghost user is created
5. Verify session bar appears
6. Test "Return to RAINBO" button
7. Verify cleanup command works

---

*"Living in the limelight, the universal dream"* - Rush, "Limelight"

Happy impersonating! 🎸

