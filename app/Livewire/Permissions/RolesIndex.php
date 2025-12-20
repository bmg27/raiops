<?php

namespace App\Livewire\Permissions;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Validation\Rule;

class RolesIndex extends Component
{
    use WithPagination, WithoutUrlPagination;

    protected $paginationTheme = 'bootstrap';

    // Searching & Sorting
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    // Modal flags
    public bool $showRoleModal = false;
    public bool $confirmingDelete = false;

    // Form fields
    public ?int $roleId = null;
    public string $roleName = '';
    public array $selectedPermissions = [];
    public bool $showUncheckedPermissions = false; // Default to hide unchecked permissions
    public string $permissionSearch = ''; // Search filter for permissions in modal

    // Tenant selection in modal (for super admins when creating roles)
    public ?int $modalTenant = null;

    // For deleting
    public ?int $deleteId = null;

    // Pagination
    public string|int $perPage = 25;

    // Tenant filter (super admin only)
    public ?int $selectedTenant = null;

    public function mount()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        // Load selectedTenant from cookie (super admin only)
        if ($isSuperAdmin) {
            if ($cookie = Cookie::get('roles_index_selected_tenant')) {
                // Validate tenant exists
                if (Tenant::withoutGlobalScopes()->find($cookie)) {
                    $this->selectedTenant = (int)$cookie;
                }
            }
        }
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedTenant()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        if ($isSuperAdmin) {
            Cookie::queue('roles_index_selected_tenant', $this->selectedTenant ?? '', 60 * 24 * 30); // 30 days
        }
        $this->resetPage();
    }

    public function render()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $currentTenantId = auth()->user()?->tenant_id;

        // Get permissions: super admins see all, regular users see non-super-admin permissions
        $allPermissions = $isSuperAdmin
            ? Permission::orderBy('name')->get()
            : Permission::forTenantAdmin()->orderBy('name')->get();

        // Get permissions for modal: filter based on modalTenant for super admins
        $modalPermissions = $allPermissions;
        if ($isSuperAdmin && $this->showRoleModal) {
            // When creating: filter based on modalTenant (tenant roles can't have super-admin permissions)
            // When editing: filter based on role's tenant_id
            $shouldFilterPermissions = false;

            if ($this->roleId) {
                // Editing - check the role's tenant_id
                $role = Role::find($this->roleId);
                if ($role && $role->tenant_id !== null) {
                    $shouldFilterPermissions = true;
                }
            } else {
                // Creating - check modalTenant
                if ($this->modalTenant !== null) {
                    $shouldFilterPermissions = true;
                }
            }

            if ($shouldFilterPermissions) {
                // Tenant-specific role - show non-super-admin permissions
                $modalPermissions = Permission::forTenantAdmin()->orderBy('name')->get();
            }
        }
        
        // Filter permissions by search term if provided
        if (!empty($this->permissionSearch) && $this->showRoleModal) {
            $searchTerm = strtolower($this->permissionSearch);
            $modalPermissions = $modalPermissions->filter(function($perm) use ($searchTerm) {
                $nameMatch = str_contains(strtolower($perm->name), $searchTerm);
                $descMatch = $perm->description && str_contains(strtolower($perm->description), $searchTerm);
                return $nameMatch || $descMatch;
            });
        }

        $roles = $this->queryRoles();
        if ($this->perPage === 'all') {
            $roles = $roles->get();
        } else {
            $roles = $roles->paginate($this->perPage);
        }

        return view('livewire.permissions.roles-index', [
            'roles' => $roles,
            'allPermissions' => $allPermissions,
            'modalPermissions' => $modalPermissions,
            'allTenants' => $isSuperAdmin ? Tenant::withoutGlobalScopes()->orderBy('name')->get() : collect(),
            'isSuperAdmin' => $isSuperAdmin,
            'currentTenantId' => $currentTenantId,
        ]);
    }

    private function queryRoles()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $currentTenantId = auth()->user()?->tenant_id;

        $query = Role::query();

        // Handle tenant sorting first - need to join tenants table if sorting by tenant
        $needsJoin = ($this->sortField === 'tenant_id');

        if ($needsJoin) {
            $query->leftJoin('tenants', 'roles.tenant_id', '=', 'tenants.id');
        }

        // Super admins can filter by tenant, regular users see their tenant's roles + global roles
        if ($isSuperAdmin) {
            if ($this->selectedTenant) {
                // Super admin selected a specific tenant - show that tenant's roles + global roles
                $query->where(function($q) {
                    $q->where('roles.tenant_id', $this->selectedTenant)
                      ->orWhereNull('roles.tenant_id'); // Include global roles
                });
            }
            // If no tenant selected, show all roles (no filter)
        } elseif ($currentTenantId) {
            // Regular users see their tenant's roles + global roles, but exclude Account Owner Primary
            $query->where(function($q) use ($currentTenantId) {
                $q->where('roles.tenant_id', $currentTenantId)
                  ->orWhereNull('roles.tenant_id'); // Include global roles (Super Admin)
            })->where('roles.name', '!=', 'Account Owner Primary'); // Hide Account Owner Primary from tenants
        }

        // Apply search filter
        $query->when($this->search, function($q) {
            $q->where('roles.name','like','%'.$this->search.'%');
        });

        // Apply sorting
        if ($needsJoin) {
            $query->select('roles.*')
                  ->orderBy('tenants.name', $this->sortDirection);
        } else {
            $query->orderBy('roles.' . $this->sortField, $this->sortDirection);
        }

        return $query->with('tenant'); // Eager load tenant relationship
    }

    // Sorting
    public function sortBy($field)
    {
        // Handle tenant sorting
        if ($field === 'tenant') {
            $field = 'tenant_id';
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // Open Create/Edit Modal
    public function openRoleModal($id = null)
    {
        $this->resetForm();

        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        // Initialize modalTenant for super admins
        if ($isSuperAdmin) {
            // Default to selectedTenant from filter, or null for global role
            $this->modalTenant = $this->selectedTenant;
        }

        if ($id) {
            $currentTenantId = auth()->user()?->tenant_id;

            $query = Role::query();
            if (!$isSuperAdmin && $currentTenantId) {
                $query->where(function($q) use ($currentTenantId) {
                    $q->where('tenant_id', $currentTenantId)
                      ->orWhereNull('tenant_id');
                });
            }

            $role = $query->findOrFail($id);
            $this->roleId = $role->id;
            $this->roleName = $role->name;
            // Convert permission IDs to string for wire:model checkboxes
            $this->selectedPermissions = $role->permissions->pluck('id')->map(fn($p) => (string)$p)->toArray();

            // Set modalTenant to the role's tenant (for super admins)
            if ($isSuperAdmin) {
                $this->modalTenant = $role->tenant_id;
            }
        }

        $this->showRoleModal = true;
    }

    public function updatedModalTenant()
    {
        // When tenant changes in modal, clear selected permissions (they may not be valid for new tenant)
        // Actually, permissions are global, so we don't need to clear them
        // But we might want to filter which permissions are shown based on tenant
    }

    // Create/Update
    public function saveRole()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $currentTenantId = auth()->user()?->tenant_id;

        $validationRules = [
            'roleName' => 'required|string|max:255',
        ];

        // Require tenant selection when creating a role (for super admins)
        if ($isSuperAdmin && !$this->roleId) {
            $validationRules['modalTenant'] = 'required|exists:tenants,id';
        }

        $this->validate($validationRules);

        // Prevent non-super-admins from creating or editing roles named "Account Owner Primary"
        if (!$isSuperAdmin && $this->roleName === 'Account Owner Primary') {
            $this->dispatch('notify', type: 'error', message: 'You cannot create or modify the Account Owner Primary role.');
            return;
        }

        if ($this->roleId) {
            // Update - ensure user can only edit their tenant's roles (or global if super admin)
            $query = Role::query();
            if (!$isSuperAdmin && $currentTenantId) {
                $query->where(function($q) use ($currentTenantId) {
                    $q->where('tenant_id', $currentTenantId)
                      ->orWhereNull('tenant_id');
                });
            }

            $role = $query->findOrFail($this->roleId);

            // Don't allow non-super-admins to modify global roles
            if (!$isSuperAdmin && is_null($role->tenant_id)) {
                $this->dispatch('notify', type: 'error', message: 'You cannot modify global roles.');
                return;
            }

            $role->name = $this->roleName;
            $role->save();
        } else {
            // Create new - assign to tenant selected in modal (for super admins) or current tenant
            $tenantIdForNewRole = null;
            if ($isSuperAdmin) {
                // Super admin must select a tenant (no global roles allowed)
                $tenantIdForNewRole = $this->modalTenant;
            } else {
                // Regular users create roles for their tenant
                $tenantIdForNewRole = $currentTenantId;
            }

            // Check if role already exists for this tenant
            $existingRole = Role::where('name', $this->roleName)
                ->where('guard_name', 'web')
                ->where('tenant_id', $tenantIdForNewRole)
                ->first();
            
            if ($existingRole) {
                $this->dispatch('notify', type: 'error', message: "A role '{$this->roleName}' already exists for this tenant.");
                return;
            }

            // Create the role - use DB facade to bypass any model-level validation
            try {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $this->roleName,
                    'guard_name' => 'web',
                    'tenant_id' => $tenantIdForNewRole,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $role = Role::find($roleId);
            } catch (\Illuminate\Database\QueryException $e) {
                // Catch unique constraint violations
                if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'already exists')) {
                    $this->dispatch('notify', type: 'error', message: "A role '{$this->roleName}' already exists for this tenant.");
                    return;
                }
                throw $e; // Re-throw if it's a different error
            }
        }

        // Filter permissions: non-super-admins can only assign non-super-admin permissions
        $permissionQuery = Permission::whereIn('id', $this->selectedPermissions);
        if (!$isSuperAdmin) {
            $permissionQuery->forTenantAdmin();
        }
        $permissionsToSync = $permissionQuery->get();
        $role->syncPermissions($permissionsToSync);

        // Clear cache for the current admin
        $cacheKey = 'menu-items-' . (auth()->check() ? auth()->id() : 'guest');
        Cache::forget($cacheKey);

        // Clear permission cache for all users who have this role
        $usersWithRole = $role->users;
        foreach ($usersWithRole as $user) {
            $userPermKey = "user_permissions_{$user->id}";
            Cache::forget($userPermKey);
        }

        $this->showRoleModal = false;
    }

    // Delete Confirmation
    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    // Delete Action
    public function delete()
    {
        if ($this->deleteId) {
            $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
            $currentTenantId = auth()->user()?->tenant_id;

            $query = Role::query();
            if (!$isSuperAdmin && $currentTenantId) {
                $query->where(function($q) use ($currentTenantId) {
                    $q->where('tenant_id', $currentTenantId)
                      ->orWhereNull('tenant_id');
                });
            }

            $role = $query->findOrFail($this->deleteId);

            // Don't allow non-super-admins to delete global roles
            if (!$isSuperAdmin && is_null($role->tenant_id)) {
                $this->dispatch('notify', type: 'error', message: 'You cannot delete global roles.');
                $this->confirmingDelete = false;
                return;
            }

            // Don't allow deletion of the Admin role
            if ($role->name === 'Admin' && !is_null($role->tenant_id)) {
                $this->dispatch('notify', type: 'error', message: 'The Admin role cannot be deleted.');
                $this->confirmingDelete = false;
                return;
            }

            // Don't allow deletion of the Account Owner Primary role
            if ($role->name === 'Account Owner Primary') {
                $this->dispatch('notify', type: 'error', message: 'The Account Owner Primary role cannot be deleted.');
                $this->confirmingDelete = false;
                return;
            }

            $role->delete();
            $this->dispatch('notify', type: 'success', message: 'Role deleted successfully.');
        }
        $this->confirmingDelete = false;
    }

    private function resetForm()
    {
        $this->reset([
            'roleId','roleName','selectedPermissions','modalTenant','showUncheckedPermissions','permissionSearch'
        ]);
        // Reset to default: hide unchecked permissions
        $this->showUncheckedPermissions = false;
    }
    
    /**
     * Highlight search term in text
     */
    public function highlightSearch($text, $search)
    {
        if (empty($search) || empty($text)) {
            return htmlspecialchars($text);
        }
        
        $highlighted = preg_replace(
            '/(' . preg_quote($search, '/') . ')/i',
            '<mark class="bg-warning">$1</mark>',
            htmlspecialchars($text)
        );
        
        return $highlighted;
    }
}
