<?php

namespace App\Livewire\Permissions;

use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use App\Models\Permission;
use Illuminate\Validation\Rule;

class PermissionsIndex extends Component
{
    use WithPagination, WithoutUrlPagination;

    protected $paginationTheme = 'bootstrap';

    // Searching & Sorting
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    // Modal flags
    public bool $showPermissionModal = false;
    public bool $confirmingDelete = false;

    // Form fields
    public ?int $permissionId = null;
    public string $permissionName = '';
    public string $permissionDescription = '';
    public string $permissionGuard = 'web';
    public bool $superAdminOnly = false;

    // For deleting
    public ?int $deleteId = null;

    // Pagination
    public string|int $perPage = 25;

    // Toggle filters
    public bool $showSuperAdminOnly = false;

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedShowSuperAdminOnly()
    {
        $this->resetPage();
    }

    public function render()
    {
        $permissions = $this->queryPermissions();
        
        if ($this->perPage === 'all') {
            $permissions = $permissions->get();
        } else {
            $permissions = $permissions->paginate($this->perPage);
        }

        return view('livewire.permissions.permissions-index', [
            'permissions' => $permissions,
        ]);
    }

    private function queryPermissions()
    {
        return Permission::with(['roles'])
            ->when($this->search, fn($q) => $q->where('name','like','%'.$this->search.'%'))
            ->when($this->showSuperAdminOnly, fn($q) => $q->where('super_admin_only', true))
            ->orderBy($this->sortField, $this->sortDirection);
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

    // Open modal for Create/Edit
    public function openPermissionModal($id = null)
    {
        $this->resetForm();

        if ($id) {
            $perm = Permission::findOrFail($id);
            $this->permissionId = $perm->id;
            $this->permissionName = $perm->name;
            $this->permissionDescription = $perm->description ?? '';
            $this->permissionGuard = $perm->guard_name;
            $this->superAdminOnly = $perm->super_admin_only ?? false;
        }

        $this->showPermissionModal = true;
    }

    public function savePermission()
    {
        // Clear cache for the current admin
        $cacheKey = 'menu-items-' . (auth()->check() ? auth()->id() : 'guest');
        Cache::forget($cacheKey);
        
        // Clear permission cache for all users (since permission changes affect all users)
        $this->clearAllUserPermissionCaches();
        
        $this->validate([
            'permissionName'  => 'required|string|max:255',
            'permissionGuard' => 'required|string|max:255',
        ]);

        if ($this->permissionId) {
            // Update
            $perm = Permission::findOrFail($this->permissionId);
            $perm->name = $this->permissionName;
            $perm->description = $this->permissionDescription;
            $perm->guard_name = $this->permissionGuard;
            $perm->super_admin_only = $this->superAdminOnly;
            $perm->save();
        } else {
            // Create
            Permission::create([
                'name' => $this->permissionName,
                'description' => $this->permissionDescription,
                'guard_name' => $this->permissionGuard,
                'super_admin_only' => $this->superAdminOnly,
            ]);
        }

        $this->showPermissionModal = false;
    }

    // Delete Confirmation
    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    // Delete
    public function delete()
    {
        if ($this->deleteId) {
            Permission::destroy($this->deleteId);
        }
        $this->confirmingDelete = false;
    }

    private function resetForm()
    {
        $this->reset([
            'permissionId','permissionName','permissionDescription','permissionGuard','superAdminOnly'
        ]);
        // Ensure defaults to false for new items
        $this->superAdminOnly = false;
    }

    private function clearAllUserPermissionCaches()
    {
        // Get all user IDs and clear their permission caches
        $userIds = \App\Models\User::pluck('id');
        foreach ($userIds as $userId) {
            $userPermKey = "user_permissions_{$userId}";
            Cache::forget($userPermKey);
        }
    }
}
