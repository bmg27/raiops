<?php

namespace App\Livewire\Admin;

use App\Models\RdsInstance;
use App\Models\TenantMaster;
use App\Models\UserEmailRoutingCache;
use App\Services\RdsConnectionService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Artisan;

/**
 * UserRoutingManagement Component
 * 
 * Manages the user_email_routing_cache - displays which RDS/tenant
 * each user email routes to. Allows searching and syncing.
 */
class UserRoutingManagement extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Search
    public string $search = '';
    public int $perPage = 25;

    // Lookup result (for quick email lookup)
    public ?string $lookupEmail = '';
    public ?array $lookupResult = null;
    public bool $lookingUp = false;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Lookup a specific email address
     */
    public function lookupEmail(): void
    {
        if (empty($this->lookupEmail)) {
            $this->lookupResult = ['error' => 'Please enter an email address.'];
            return;
        }

        $this->lookingUp = true;
        $this->lookupResult = null;

        $email = strtolower(trim($this->lookupEmail));

        // First check local cache
        $cached = UserEmailRoutingCache::with(['tenantMaster.rdsInstance'])
            ->where('email', $email)
            ->first();

        if ($cached) {
            $this->lookupResult = [
                'source' => 'cache',
                'email' => $cached->email,
                'tenant' => $cached->tenantMaster?->name ?? 'Unknown',
                'rds' => $cached->tenantMaster?->rdsInstance?->name ?? 'Unknown',
                'rds_id' => $cached->tenantMaster?->rds_instance_id,
                'tenant_id' => $cached->tenantMaster?->remote_tenant_id,
                'user_id' => $cached->remote_user_id,
                'cached_at' => $cached->cached_at?->diffForHumans(),
            ];
            $this->lookingUp = false;
            return;
        }

        // If not in cache, try to find in master RDS
        $masterRds = RdsInstance::getMaster();
        if (!$masterRds) {
            $this->lookupResult = [
                'error' => 'Email not found in cache and no master RDS configured.',
            ];
            $this->lookingUp = false;
            return;
        }

        try {
            $service = app(RdsConnectionService::class);
            $routing = $service->query($masterRds)
                ->table('user_email_routing')
                ->where('email', $email)
                ->first();

            if ($routing) {
                // Find the tenant name
                $tenantMaster = TenantMaster::where('rds_instance_id', $routing->rds_instance_id ?? $masterRds->id)
                    ->where('remote_tenant_id', $routing->tenant_id)
                    ->first();

                $this->lookupResult = [
                    'source' => 'live_rds',
                    'email' => $routing->email,
                    'tenant' => $tenantMaster?->name ?? "Tenant #{$routing->tenant_id}",
                    'rds' => $masterRds->name,
                    'rds_id' => $routing->rds_instance_id ?? $masterRds->id,
                    'tenant_id' => $routing->tenant_id,
                    'user_id' => $routing->user_id,
                    'note' => 'Found in RDS but not in local cache. Run sync to update cache.',
                ];
            } else {
                $this->lookupResult = [
                    'error' => "Email '{$email}' not found in routing table.",
                ];
            }
        } catch (\Exception $e) {
            $this->lookupResult = [
                'error' => 'Failed to query RDS: ' . $e->getMessage(),
            ];
        }

        $this->lookingUp = false;
    }

    /**
     * Clear lookup result
     */
    public function clearLookup(): void
    {
        $this->lookupEmail = '';
        $this->lookupResult = null;
    }

    /**
     * Trigger a sync from the master RDS
     */
    public function syncFromRds(): void
    {
        try {
            Artisan::call('raiops:sync-user-routing');
            session()->flash('success', 'User routing sync completed. ' . trim(Artisan::output()));
        } catch (\Exception $e) {
            session()->flash('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete a cached routing entry
     */
    public function deleteEntry(int $id): void
    {
        $entry = UserEmailRoutingCache::find($id);
        if ($entry) {
            $email = $entry->email;
            $entry->delete();
            session()->flash('success', "Removed '{$email}' from routing cache.");
        }
    }

    /**
     * Get summary stats
     */
    public function getStatsProperty(): array
    {
        $total = UserEmailRoutingCache::count();
        $byRds = UserEmailRoutingCache::query()
            ->selectRaw('tenant_master.rds_instance_id, rds_instances.name as rds_name, COUNT(*) as count')
            ->join('tenant_master', 'user_email_routing_cache.tenant_master_id', '=', 'tenant_master.id')
            ->join('rds_instances', 'tenant_master.rds_instance_id', '=', 'rds_instances.id')
            ->groupBy('tenant_master.rds_instance_id', 'rds_instances.name')
            ->get();

        return [
            'total' => $total,
            'by_rds' => $byRds,
        ];
    }

    public function render()
    {
        $entries = UserEmailRoutingCache::with(['tenantMaster.rdsInstance'])
            ->when($this->search, function ($query) {
                $query->where('email', 'like', "%{$this->search}%")
                    ->orWhereHas('tenantMaster', function ($q) {
                        $q->where('name', 'like', "%{$this->search}%");
                    });
            })
            ->orderBy('email')
            ->paginate($this->perPage);

        $masterRds = RdsInstance::getMaster();

        return view('livewire.admin.user-routing-management', [
            'entries' => $entries,
            'masterRds' => $masterRds,
            'stats' => $this->stats,
        ])->layout('layouts.rai');
    }
}

