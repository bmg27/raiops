<?php

namespace App\Livewire\Admin;

use App\Models\RdsInstance;
use App\Models\SubscriptionPlan;
use App\Models\TenantBilling;
use App\Models\TenantMaster;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * AnalyticsDashboard Component
 * 
 * RAINBO Command Central's analytics and reporting dashboard.
 * Shows MRR, tenant growth, billing overview, and key metrics.
 */
class AnalyticsDashboard extends Component
{
    public string $dateRange = '';

    public function mount(): void
    {
        // Default to last 30 days
        $this->dateRange = now()->subDays(30)->format('Y-m-d') . ' - ' . now()->format('Y-m-d');
    }

    /**
     * Get MRR metrics
     */
    public function getMrrMetricsProperty(): array
    {
        $totalMrr = TenantBilling::sum('mrr');
        $activeBillings = TenantBilling::whereHas('tenant', function ($q) {
            $q->where('status', 'active');
        })->count();

        // MRR by plan
        $mrrByPlan = TenantBilling::select('subscription_plan_id', DB::raw('SUM(mrr) as total_mrr'), DB::raw('COUNT(*) as tenant_count'))
            ->whereNotNull('subscription_plan_id')
            ->groupBy('subscription_plan_id')
            ->with('subscriptionPlan:id,name,code')
            ->get()
            ->map(function ($item) {
                return [
                    'plan' => $item->subscriptionPlan?->name ?? 'Unknown',
                    'code' => $item->subscriptionPlan?->code ?? 'unknown',
                    'mrr' => (float) $item->total_mrr,
                    'tenants' => $item->tenant_count,
                ];
            });

        // MRR by billing cycle
        $mrrByCycle = TenantBilling::select('billing_cycle', DB::raw('SUM(mrr) as total_mrr'), DB::raw('COUNT(*) as tenant_count'))
            ->groupBy('billing_cycle')
            ->get()
            ->pluck('total_mrr', 'billing_cycle')
            ->toArray();

        return [
            'total_mrr' => (float) $totalMrr,
            'arr' => (float) $totalMrr * 12,
            'active_billings' => $activeBillings,
            'avg_mrr' => $activeBillings > 0 ? (float) $totalMrr / $activeBillings : 0,
            'by_plan' => $mrrByPlan,
            'by_cycle' => $mrrByCycle,
        ];
    }

    /**
     * Get tenant metrics
     */
    public function getTenantMetricsProperty(): array
    {
        $statusCounts = TenantMaster::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $rdsCounts = TenantMaster::select('rds_instance_id', DB::raw('COUNT(*) as count'))
            ->groupBy('rds_instance_id')
            ->get()
            ->map(function ($item) {
                $rds = RdsInstance::find($item->rds_instance_id);
                return [
                    'rds_name' => $rds?->name ?? 'Unknown',
                    'count' => $item->count,
                ];
            });

        // Trials expiring soon (within 7 days)
        $expiringTrials = TenantMaster::where('status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->count();

        // Recently created tenants (last 30 days)
        $recentTenants = TenantMaster::where('created_at', '>=', now()->subDays(30))->count();

        return [
            'total' => array_sum($statusCounts),
            'by_status' => $statusCounts,
            'by_rds' => $rdsCounts,
            'expiring_trials' => $expiringTrials,
            'recent_tenants' => $recentTenants,
        ];
    }

    /**
     * Get subscription plan metrics
     */
    public function getPlansProperty()
    {
        return SubscriptionPlan::active()
            ->ordered()
            ->withCount(['tenantBillings'])
            ->get();
    }

    /**
     * Get top tenants by user count
     */
    public function getTopTenantsProperty()
    {
        return TenantMaster::with('rdsInstance')
            ->where('status', 'active')
            ->orderByDesc('cached_user_count')
            ->limit(10)
            ->get();
    }

    /**
     * Get billing alerts (upcoming, past due)
     */
    public function getBillingAlertsProperty(): array
    {
        $upcomingBillings = TenantBilling::with('tenant')
            ->whereBetween('next_billing_date', [now(), now()->addDays(7)])
            ->orderBy('next_billing_date')
            ->limit(5)
            ->get();

        $pastDueBillings = TenantBilling::with('tenant')
            ->where('next_billing_date', '<', now())
            ->orderBy('next_billing_date')
            ->limit(5)
            ->get();

        return [
            'upcoming' => $upcomingBillings,
            'past_due' => $pastDueBillings,
            'past_due_count' => TenantBilling::where('next_billing_date', '<', now())->count(),
        ];
    }

    /**
     * Export analytics data to CSV
     */
    public function exportAnalytics(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'rainbo-analytics-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // Headers
            fputcsv($handle, [
                'Metric',
                'Value',
                'Details'
            ]);

            // MRR Metrics
            fputcsv($handle, ['Total MRR', '$' . number_format($this->mrrMetrics['total_mrr'], 2), '']);
            fputcsv($handle, ['ARR', '$' . number_format($this->mrrMetrics['arr'], 2), '']);
            fputcsv($handle, ['Active Billings', $this->mrrMetrics['active_billings'], '']);
            fputcsv($handle, ['Average MRR', '$' . number_format($this->mrrMetrics['avg_mrr'], 2), '']);

            // Tenant Metrics
            fputcsv($handle, ['Total Tenants', $this->tenantMetrics['total'], '']);
            fputcsv($handle, ['Active Tenants', $this->tenantMetrics['by_status']['active'] ?? 0, '']);
            fputcsv($handle, ['Trial Tenants', $this->tenantMetrics['by_status']['trial'] ?? 0, '']);
            fputcsv($handle, ['Recent Tenants (30d)', $this->tenantMetrics['recent_tenants'], '']);

            // MRR by Plan
            fputcsv($handle, ['', '', '']);
            fputcsv($handle, ['MRR by Plan', '', '']);
            foreach ($this->mrrMetrics['by_plan'] as $plan) {
                fputcsv($handle, [
                    $plan['plan'],
                    '$' . number_format($plan['mrr'], 2),
                    $plan['tenants'] . ' tenant(s)'
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.analytics-dashboard')
            ->layout('layouts.rai');
    }
}

