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
            // The rds_instance_id in the payload is routing information (which RDS database to connect to),
            // not a security boundary. Security is provided by JWT signature validation and expiration.
            $payload = JWT::decode(
                $token, 
                new Key(config('rainbo.impersonation_secret'), 'HS256')
            );
            
            // Find or create ghost admin user
            $ghostUser = $this->findOrCreateGhostAdmin($payload);
            
            // Log them in
            Auth::login($ghostUser);
            
            // Store RDS instance ID from token; middleware will configure connection on each request
            if (isset($payload->rds_instance_id)) {
                session(['current_rds_instance_id' => $payload->rds_instance_id]);
                Log::info('✅ RAINBO impersonation stored RDS instance ID in session', [
                    'rds_instance_id' => $payload->rds_instance_id,
                ]);
            }
            
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
            
            // Log successful impersonation with full context
            Log::info('✅ RAINBO impersonation successful', [
                'rainbo_admin_id' => $payload->rainbo_admin_id,
                'rainbo_admin_email' => $payload->rainbo_admin_email,
                'remote_tenant_id' => $payload->remote_tenant_id,
                'rds_instance_id' => $payload->rds_instance_id ?? null,
                'ghost_user_id' => $ghostUser->id,
                'ghost_user_email' => $ghostUser->email,
                'ghost_is_super_admin' => $ghostUser->is_super_admin,
                'ghost_tenant_id' => $ghostUser->tenant_id,
                'ghost_location_access' => $ghostUser->location_access,
                'session_impersonated_tenant_id' => session('impersonated_tenant_id'),
                'session_selected_tenant_id' => session('selected_tenant_id'),
                'session_is_rainbo_session' => session('is_rainbo_session'),
                'connection_name' => \DB::getDefaultConnection(),
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
            'current_rds_instance_id',
            'current_rds_connection',
            'rds_routing_complete',
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
                'email_verified_at' => now(), // Auto-verify ghost admin emails
                'is_super_admin' => true,
                'is_ghost_admin' => true,
                'rainbo_admin_id' => $payload->rainbo_admin_id,
                'tenant_id' => null, // Super admins don't need tenant_id - routing uses session
                'location_access' => 'All', // Give access to all locations (super admin should bypass anyway)
                'status' => 'Active',
            ]
        );
        
        // Ensure email is verified and correct settings for existing ghost users
        $updates = [];
        if (!$user->email_verified_at) {
            $updates['email_verified_at'] = now();
        }
        if (!$user->is_super_admin) {
            $updates['is_super_admin'] = true;
        }
        if ($user->location_access !== 'All') {
            $updates['location_access'] = 'All';
        }
        if ($user->rainbo_admin_id !== $payload->rainbo_admin_id) {
            $updates['rainbo_admin_id'] = $payload->rainbo_admin_id;
        }
        // Ensure tenant_id is null for ghost admins (shouldn't be set)
        if ($user->tenant_id !== null) {
            $updates['tenant_id'] = null;
        }
        
        if (!empty($updates)) {
            $user->update($updates);
        }
        
        // Touch updated_at for cleanup tracking
        $user->touch();
        
        return $user;
    }
}

