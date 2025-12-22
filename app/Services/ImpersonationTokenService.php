<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TenantMaster;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

/**
 * ImpersonationTokenService
 * 
 * Generates secure JWT tokens for RAINBO admins to impersonate
 * into RAI tenant instances. The token is validated by RAI's
 * /rainbo-impersonate endpoint.
 */
class ImpersonationTokenService
{
    /**
     * Token expiry in minutes (short-lived for security)
     */
    protected int $tokenExpiryMinutes = 5;

    /**
     * Generate a JWT token for impersonation
     */
    public function generateToken(User $admin, TenantMaster $tenant): string
    {
        $rds = $tenant->rdsInstance;
        
        if (!$rds) {
            throw new \InvalidArgumentException('Tenant has no associated RDS instance');
        }

        // Get admin's RAINBO permissions
        $permissions = $admin->getRainboPermissions();

        $payload = [
            // Admin identification
            'rainbo_admin_id' => $admin->id,
            'rainbo_admin_email' => $admin->email,
            'rainbo_admin_name' => $admin->name,
            
            // Tenant/RDS targeting
            'tenant_master_id' => $tenant->id,
            'remote_tenant_id' => $tenant->remote_tenant_id,
            'rds_instance_id' => $rds->id,
            
            // Permissions to enforce in RAI
            'permissions' => $permissions,
            
            // Return path after session ends
            'return_url' => route('admin.tenants') . '?viewDetails=' . $tenant->id,
            
            // Token metadata
            'nonce' => Str::random(32), // Prevents replay attacks
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes($this->tokenExpiryMinutes)->timestamp,
        ];

        return JWT::encode($payload, $this->getSecret(), 'HS256');
    }

    /**
     * Get the full impersonation URL for a tenant
     */
    public function getImpersonationUrl(User $admin, TenantMaster $tenant): string
    {
        $token = $this->generateToken($admin, $tenant);
        $rds = $tenant->rdsInstance;
        
        // Build the URL to RAI's impersonation endpoint
        $baseUrl = rtrim($rds->app_url, '/');
        
        return $baseUrl . '/rainbo-impersonate?token=' . urlencode($token);
    }

    /**
     * Launch impersonation - generates URL and logs the action
     */
    public function launchImpersonation(User $admin, TenantMaster $tenant): array
    {
        // Check permission
        if (!$admin->hasRainboPermission('tenant.impersonate')) {
            throw new \Exception('You do not have permission to impersonate tenants.');
        }

        // Generate the URL
        $url = $this->getImpersonationUrl($admin, $tenant);

        // Log the impersonation attempt
        AuditLog::log(
            'impersonation_launched',
            'TenantMaster',
            $tenant->id,
            null,
            [
                'tenant_name' => $tenant->name,
                'rds_instance' => $tenant->rdsInstance->name,
                'remote_tenant_id' => $tenant->remote_tenant_id,
                'target_url' => $tenant->rdsInstance->app_url,
            ]
        );

        return [
            'success' => true,
            'url' => $url,
            'tenant' => $tenant->name,
            'rds' => $tenant->rdsInstance->name,
        ];
    }

    /**
     * Get the shared secret for JWT signing
     */
    protected function getSecret(): string
    {
        $secret = config('rainbo.impersonation_secret');
        
        if (empty($secret) || strlen($secret) < 32) {
            throw new \RuntimeException(
                'RAINBO_IMPERSONATION_SECRET must be set in .env and be at least 32 characters.'
            );
        }

        return $secret;
    }

    /**
     * Set custom token expiry (for testing)
     */
    public function setTokenExpiry(int $minutes): self
    {
        $this->tokenExpiryMinutes = $minutes;
        return $this;
    }
}

