<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\RdsInstance;
use App\Models\TenantMaster;
use App\Models\UserEmailRoutingCache;
use App\Services\RdsConnectionService;
use Livewire\Component;

/**
 * SystemHealthDashboard Component
 * 
 * RAINBO Command Central's system health monitoring dashboard.
 * Shows RDS health, sync status, and key metrics at a glance.
 */
class SystemHealthDashboard extends Component
{
    public bool $isRefreshing = false;
    public ?string $lastRefreshed = null;

    public function mount(): void
    {
        $this->lastRefreshed = now()->toDateTimeString();
    }

    /**
     * Refresh all RDS health checks
     */
    public function refreshAllHealth(): void
    {
        $this->isRefreshing = true;

        try {
            $service = app(RdsConnectionService::class);
            $service->runHealthChecks();
            
            $this->lastRefreshed = now()->toDateTimeString();
            session()->flash('success', 'All RDS health checks completed.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to refresh health: ' . $e->getMessage());
        } finally {
            $this->isRefreshing = false;
        }
    }

    /**
     * Refresh single RDS health
     */
    public function refreshRdsHealth(int $rdsId): void
    {
        try {
            $rds = RdsInstance::findOrFail($rdsId);
            $rds->updateHealthStatus();
            session()->flash('success', "Health check completed for {$rds->name}.");
        } catch (\Exception $e) {
            session()->flash('error', 'Health check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get overall system health status
     */
    public function getOverallHealthProperty(): string
    {
        $rdsInstances = RdsInstance::active()->get();
        
        if ($rdsInstances->isEmpty()) {
            return 'unknown';
        }

        $healthStatuses = $rdsInstances->pluck('health_status');

        if ($healthStatuses->contains('down')) {
            return 'critical';
        }
        
        if ($healthStatuses->contains('degraded')) {
            return 'warning';
        }
        
        if ($healthStatuses->every(fn($s) => $s === 'healthy')) {
            return 'healthy';
        }

        return 'unknown';
    }

    /**
     * Get RDS instances with health info
     */
    public function getRdsInstancesProperty()
    {
        return RdsInstance::active()
            ->withCount(['tenants'])
            ->orderBy('is_master', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get platform metrics
     */
    public function getMetricsProperty(): array
    {
        return [
            'total_rds' => RdsInstance::active()->count(),
            'total_tenants' => TenantMaster::count(),
            'active_tenants' => TenantMaster::where('status', 'active')->count(),
            'trial_tenants' => TenantMaster::where('status', 'trial')->count(),
            'suspended_tenants' => TenantMaster::where('status', 'suspended')->count(),
            'total_users_cached' => TenantMaster::sum('cached_user_count'),
            'total_locations_cached' => TenantMaster::sum('cached_location_count'),
            'routing_entries' => UserEmailRoutingCache::count(),
            'audit_events_today' => AuditLog::whereDate('created_at', today())->count(),
            'audit_events_week' => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * Get sync status info
     */
    public function getSyncStatusProperty(): array
    {
        $lastTenantSync = TenantMaster::max('cache_refreshed_at');
        $lastRoutingSync = UserEmailRoutingCache::max('synced_at');
        
        $staleTenantsCount = TenantMaster::where(function ($q) {
            $q->whereNull('cache_refreshed_at')
              ->orWhere('cache_refreshed_at', '<', now()->subHours(1));
        })->count();

        return [
            'last_tenant_sync' => $lastTenantSync,
            'last_routing_sync' => $lastRoutingSync,
            'stale_tenants' => $staleTenantsCount,
            'tenant_sync_healthy' => $lastTenantSync && $lastTenantSync > now()->subHours(1),
            'routing_sync_healthy' => $lastRoutingSync && $lastRoutingSync > now()->subHours(1),
        ];
    }

    /**
     * Get recent audit activity
     */
    public function getRecentActivityProperty()
    {
        return AuditLog::with('user')
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.system-health-dashboard')
            ->layout('layouts.rai');
    }
}

