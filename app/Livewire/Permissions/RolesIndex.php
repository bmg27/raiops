<?php

namespace App\Livewire\Permissions;

use Illuminate\Support\Facades\Cache;
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
    public bool $showUncheckedPermissions = false;
    public string $permissionSearch = '';

    // For deleting
    public ?int $deleteId = null;

    // Pagination
    public string|int $perPage = 25;

    public function mount()
    {
        // No tenant-specific initialization needed
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // Get all permissions (no tenant filtering in RAIOPS)
        $allPermissions = Permission::orderBy('name')->get();

        // Filter permissions by search term if provided
        $modalPermissions = $allPermissions;
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
        ]);
    }

    private function queryRoles()
    {
        $query = Role::query();

        // Apply search filter
        $query->when($this->search, function($q) {
            $q->where('name', 'like', '%' . $this->search . '%');
        });

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query;
    }

    // Sorting
    public function sortBy($field)
    {
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

        if ($id) {
            $role = Role::findOrFail($id);
            $this->roleId = $role->id;
            $this->roleName = $role->name;
            // Convert permission IDs to string for wire:model checkboxes
            $this->selectedPermissions = $role->permissions->pluck('id')->map(fn($p) => (string)$p)->toArray();
        }

        $this->showRoleModal = true;
    }

    // Create/Update
    public function saveRole()
    {
        $validationRules = [
            'roleName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->roleId),
            ],
        ];

        $this->validate($validationRules);

        if ($this->roleId) {
            // Update
            $role = Role::findOrFail($this->roleId);
            $role->name = $this->roleName;
            $role->save();
        } else {
            // Create new role
            $role = Role::create([
                'name' => $this->roleName,
                'guard_name' => 'web',
            ]);
        }

        // Sync permissions
        $permissionsToSync = Permission::whereIn('id', $this->selectedPermissions)->get();
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
        $this->dispatch('notify', type: 'success', message: 'Role saved successfully!');
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
            $role = Role::findOrFail($this->deleteId);

            // Don't allow deletion of system roles
            $protectedRoles = ['Super Admin', 'Admin'];
            if (in_array($role->name, $protectedRoles)) {
                $this->dispatch('notify', type: 'error', message: "The '{$role->name}' role cannot be deleted.");
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
            'roleId', 'roleName', 'selectedPermissions', 'showUncheckedPermissions', 'permissionSearch'
        ]);
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
