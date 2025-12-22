<?php

namespace App\Http\Middleware;

use App\Services\RdsConnectionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DynamicDatabaseRouter
{
    protected RdsConnectionService $rdsService;

    public function __construct(RdsConnectionService $rdsService)
    {
        $this->rdsService = $rdsService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // If multi-RDS is disabled, skip
        if (!$this->rdsService->isEnabled()) {
            return $next($request);
        }

        // Only act for authenticated users
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Super admins: always reconfigure each request
        if ($user->isSuperAdmin()) {
            return $this->handleSuperAdmin($request, $next, $user);
        }

        // Non-super-admins: fall back to default behavior for now
        return $next($request);
    }

    /**
     * Super admin routing: reconfigure connection on every request.
     */
    protected function handleSuperAdmin(Request $request, Closure $next, $user): Response
    {
        // First, try to switch using the stored RDS instance id
        $currentRdsInstanceId = session('current_rds_instance_id');
        if ($currentRdsInstanceId) {
            $switched = $this->rdsService->switchToRdsByInstanceId($currentRdsInstanceId);
            if ($switched) {
                Log::info('[Multi-RDS] Super Admin - reconfigured RDS from session', [
                    'super_admin_id' => $user->id,
                    'rds_instance_id' => $currentRdsInstanceId,
                    'connection_name' => session('current_rds_connection'),
                ]);
                return $next($request);
            }
            Log::warning('[Multi-RDS] Super Admin - failed reconfigure from session', [
                'super_admin_id' => $user->id,
                'rds_instance_id' => $currentRdsInstanceId,
            ]);
        }

        // Next, try by tenant selection / impersonation
        $selectedTenantId = session('selected_tenant_id') ?? session('impersonated_tenant_id');
        if ($selectedTenantId) {
            $switched = $this->rdsService->switchToRdsByTenant($selectedTenantId);
            if ($switched) {
                Log::info('[Multi-RDS] Super Admin viewing tenant - routed to tenant RDS', [
                    'super_admin_id' => $user->id,
                    'tenant_id' => $selectedTenantId,
                    'rds_instance_id' => session('current_rds_instance_id'),
                ]);
                return $next($request);
            }
            Log::warning('[Multi-RDS] Super Admin - failed switch by tenant', [
                'super_admin_id' => $user->id,
                'tenant_id' => $selectedTenantId,
            ]);
        }

        // If nothing else, stay on master (default connection)
        Log::info('[Multi-RDS] Super Admin using Master RDS (no switch)', [
            'super_admin_id' => $user->id,
            'connection' => config('database.default'),
        ]);

        return $next($request);
    }
}

