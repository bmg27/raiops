<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * AuditLogViewer Component
 * 
 * RAINBO Command Central's audit log viewer.
 * Shows all admin actions with filtering, search, and detail views.
 */
class AuditLogViewer extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Filters
    public string $search = '';
    public string $actionFilter = 'all';
    public string $userFilter = 'all';
    public string $modelFilter = 'all';
    public string $sourceFilter = 'all';
    public string $dateRange = '';  // Format: "YYYY-MM-DD - YYYY-MM-DD"
    public int $perPage = 25;

    // Detail modal
    public ?int $selectedLogId = null;
    public ?array $selectedLogDetails = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'actionFilter' => ['except' => 'all'],
        'userFilter' => ['except' => 'all'],
        'modelFilter' => ['except' => 'all'],
        'sourceFilter' => ['except' => 'all'],
        'dateRange' => ['except' => ''],
    ];

    public function mount(): void
    {
        // Default to showing last 7 days
        if (empty($this->dateRange)) {
            $this->dateRange = now()->subDays(7)->format('Y-m-d') . ' - ' . now()->format('Y-m-d');
        }
    }

    /**
     * Parse date range into start and end dates
     */
    protected function parseDateRange(): array
    {
        if (empty($this->dateRange)) {
            return [null, null];
        }

        $dates = explode(' - ', $this->dateRange);
        $dateFrom = $dates[0] ?? null;
        $dateTo = $dates[1] ?? $dateFrom;

        return [$dateFrom, $dateTo];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingUserFilter(): void
    {
        $this->resetPage();
    }

    public function updatingModelFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateRange(): void
    {
        $this->resetPage();
    }

    /**
     * View log details
     */
    public function viewDetails(int $logId): void
    {
        $log = AuditLog::with(['user', 'rdsInstance', 'tenant'])->find($logId);
        
        if (!$log) {
            session()->flash('error', 'Audit log not found.');
            return;
        }

        $this->selectedLogId = $logId;
        $this->selectedLogDetails = [
            'id' => $log->id,
            'action' => $log->action,
            'action_badge_class' => $log->getActionBadgeClass(),
            'model_type' => $log->model_type,
            'model_id' => $log->model_id,
            'user_name' => $log->user?->name ?? 'System',
            'user_email' => $log->user?->email,
            'rds_name' => $log->rdsInstance?->name,
            'tenant_name' => $log->tenant?->name,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'source' => $log->source,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'changes' => $log->getChanges(),
        ];

        $this->dispatch('show-details-modal');
    }

    /**
     * Close details modal
     */
    public function closeDetails(): void
    {
        $this->selectedLogId = null;
        $this->selectedLogDetails = null;
    }

    /**
     * Clear all filters
     */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->actionFilter = 'all';
        $this->userFilter = 'all';
        $this->modelFilter = 'all';
        $this->sourceFilter = 'all';
        $this->dateRange = now()->subDays(7)->format('Y-m-d') . ' - ' . now()->format('Y-m-d');
        $this->resetPage();
    }

    /**
     * Get available actions for filter dropdown
     */
    public function getActionsProperty(): array
    {
        return AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->toArray();
    }

    /**
     * Get available model types for filter dropdown
     */
    public function getModelTypesProperty(): array
    {
        return AuditLog::select('model_type')
            ->whereNotNull('model_type')
            ->distinct()
            ->orderBy('model_type')
            ->pluck('model_type')
            ->toArray();
    }

    /**
     * Get available users for filter dropdown
     */
    public function getUsersProperty(): array
    {
        return User::orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Format action name for display
     */
    public function formatAction(string $action): string
    {
        return ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Get badge class for an action
     */
    public function getActionBadgeClass(string $action): string
    {
        return match ($action) {
            'created' => 'bg-success',
            'updated' => 'bg-info',
            'deleted' => 'bg-danger',
            'impersonation_launched' => 'bg-warning text-dark',
            'synced', 'bulk_synced' => 'bg-primary',
            'logged_in' => 'bg-primary',
            'logged_out' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    public function render()
    {
        [$dateFrom, $dateTo] = $this->parseDateRange();

        $query = AuditLog::with(['user', 'rdsInstance', 'tenant'])
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('action', 'like', "%{$this->search}%")
                        ->orWhere('model_type', 'like', "%{$this->search}%")
                        ->orWhere('ip_address', 'like', "%{$this->search}%")
                        ->orWhereHas('user', function ($uq) {
                            $uq->where('name', 'like', "%{$this->search}%")
                                ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->actionFilter !== 'all', function ($q) {
                $q->where('action', $this->actionFilter);
            })
            ->when($this->userFilter !== 'all', function ($q) {
                $q->where('rainbo_user_id', $this->userFilter);
            })
            ->when($this->modelFilter !== 'all', function ($q) {
                $q->where('model_type', $this->modelFilter);
            })
            ->when($this->sourceFilter !== 'all', function ($q) {
                $q->where('source', $this->sourceFilter);
            })
            ->when($dateFrom, function ($q) use ($dateFrom) {
                $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                $q->whereDate('created_at', '<=', $dateTo);
            })
            ->orderBy('created_at', 'desc');

        $logs = $query->paginate($this->perPage);

        // Get stats for the current filter period
        $statsQuery = AuditLog::query()
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo));

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'by_action' => (clone $statsQuery)
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(5)
                ->pluck('count', 'action')
                ->toArray(),
        ];

        return view('livewire.admin.audit-log-viewer', [
            'logs' => $logs,
            'stats' => $stats,
        ])->layout('layouts.rai');
    }
}

