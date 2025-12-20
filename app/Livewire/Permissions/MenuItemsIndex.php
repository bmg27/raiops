<?php

namespace App\Livewire\Permissions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use App\Models\MenuItem;
use App\Models\Menu;
use App\Models\Permission;

class MenuItemsIndex extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $search = '';
    public string $sortField = 'title';
    public string $sortDirection = 'asc';
    public bool $showInactive = false; // Default to hide inactive items
    public bool $showSuperAdminOnly = false; // Default to show all items
    public bool $showTenantSpecific = false; // Default to show all items

    public bool $showModal = false;
    public bool $confirmingDelete = false;

    public ?int $menuItemId = null;
    public ?int $menu_id = null;
    public string $title = '';
    public string $url = '';
    public ?string $route = null;
    public ?int $parent_id = null;
    public ?string $icon = null;
    public string $containerType = 'Standard';
    public int $order = 0;
    public ?int $active = 1;
    public $isActive = false;
    public ?int $permission_id = null;
    public bool $super_admin_only = false;
    public bool $tenant_specific = false;
    public ?string $super_admin_append = null;
    protected $paginationTheme = 'bootstrap';
    public array $perPageOptions = [10, 25, 50, 'all'];
    public string|int $perPage = 10;

    public ?int $deleteId = null;

    // Permission creation properties
    public bool $showPermissionModal = false;
    public string $newPermissionName = '';
    public int $permissionRefreshKey = 0;

    public function mount()
    {
        // Load toggle states from cookies
        if ($cookie = Cookie::get('menu_items_show_inactive')) {
            $this->showInactive = filter_var($cookie, FILTER_VALIDATE_BOOLEAN);
        }
        if ($cookie = Cookie::get('menu_items_show_super_admin_only')) {
            $this->showSuperAdminOnly = filter_var($cookie, FILTER_VALIDATE_BOOLEAN);
        }
        if ($cookie = Cookie::get('menu_items_show_tenant_specific')) {
            $this->showTenantSpecific = filter_var($cookie, FILTER_VALIDATE_BOOLEAN);
        }
    }

    public function render()
    {
        if ($this->perPage === 'all') {
            $items = $this->query()->get();
        } else {
            $items = $this->query()->paginate($this->perPage);
        }
        return view('livewire.permissions.menu-items-index', [
            'items' => $items,
            'allPermissions' => Permission::orderBy('name')->get(),
            'allTenants' => \App\Models\Tenant::withoutGlobalScopes()->orderBy('name')->get(),
        ]);
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    private function query()
    {
        $query = MenuItem::with(['permission', 'parent'])
            ->when(!$this->showInactive, function($q) {
                // Hide inactive items by default (active <> 1 means active != 1 or active is null)
                $q->where('active', 1);
            })
            ->when($this->showSuperAdminOnly, function($q) {
                $q->where('super_admin_only', true);
            })
            ->when($this->showTenantSpecific, function($q) {
                $q->where('tenant_specific', true);
            })
            ->when($this->search, function($q) {
                $q->where('title','like','%'.$this->search.'%')
                  ->orWhere('url','like','%'.$this->search.'%')
                  ->orWhereHas('permission', function($query) {
                      $query->where('name', 'like', '%'.$this->search.'%');
                  });
            });

        // Handle sorting
        $sortDirection = in_array(strtolower($this->sortDirection), ['asc', 'desc']) ? strtolower($this->sortDirection) : 'asc';
        
        if ($this->sortField === 'parent') {
            $query->orderByRaw('(SELECT title FROM menu_items as parent_items WHERE parent_items.id = menu_items.parent_id) ' . $sortDirection);
        } elseif ($this->sortField === 'permission') {
            $query->orderByRaw('(SELECT name FROM permissions WHERE permissions.id = menu_items.permission_id) ' . $sortDirection);
        } else {
            $query->orderBy($this->sortField, $sortDirection);
        }

        return $query;
    }
    
    public function updatedShowInactive()
    {
        Cookie::queue('menu_items_show_inactive', $this->showInactive ? '1' : '0', 60 * 24 * 30); // 30 days
        $this->resetPage();
    }

    public function updatedShowSuperAdminOnly()
    {
        Cookie::queue('menu_items_show_super_admin_only', $this->showSuperAdminOnly ? '1' : '0', 60 * 24 * 30); // 30 days
        $this->resetPage();
    }

    public function updatedShowTenantSpecific()
    {
        Cookie::queue('menu_items_show_tenant_specific', $this->showTenantSpecific ? '1' : '0', 60 * 24 * 30); // 30 days
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        if ($id) {
            $m = MenuItem::findOrFail($id);
            $this->menuItemId  = $m->id;
            $this->menu_id     = $m->menu_id;
            $this->title       = $m->title;
            $this->url         = $m->url;
            $this->route       = $m->route;
            $this->parent_id   = $m->parent_id;
            $this->icon        = $m->icon;
            $this->containerType = $m->containerType;
            $this->order       = $m->order;
            $this->active      = $m->active;
            $this->permission_id = $m->permission_id;
            $this->super_admin_only = $m->super_admin_only ?? false;
            $this->tenant_specific = $m->tenant_specific ?? false;
            $this->super_admin_append = $m->super_admin_append ?? null;
        }
        $this->showModal = true;
    }

    public function save()
    {
        // Clear cache for the current admin
        $cacheKey = 'menu-items-' . (auth()->check() ? auth()->id() : 'guest');
        Cache::forget($cacheKey);
        
        // Clear permission cache for all users (since menu item changes affect all users)
        $this->clearAllUserPermissionCaches();
        
        $validationRules = [
            'title' => 'required|string|max:255',
            'url'   => 'required|string|max:255',
        ];
        
        // Only validate super_admin_append if user is super admin
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        if ($isSuperAdmin) {
            $validationRules['super_admin_append'] = 'nullable|string|max:20';
        }
        
        $this->validate($validationRules);

        // Only super admins can set super_admin_only flag and super_admin_append
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $superAdminOnly = $isSuperAdmin ? ($this->super_admin_only ?? false) : false;
        $superAdminAppend = $isSuperAdmin ? ($this->super_admin_append ?? null) : null;

        if ($this->menuItemId) {
            $m = MenuItem::findOrFail($this->menuItemId);
            $updateData = [
                'title' => $this->title,
                'url'   => $this->url,
                'route' => $this->route,
                'parent_id' => $this->parent_id,
                'icon'  => $this->icon,
                'containerType' => $this->containerType,
                'order' => $this->order,
                'active'=> $this->active,
                'permission_id' => $this->permission_id,
                'super_admin_only' => $superAdminOnly,
                'tenant_specific' => $this->tenant_specific ?? false,
            ];
            if ($isSuperAdmin) {
                $updateData['super_admin_append'] = $superAdminAppend;
            }
            $m->update($updateData);
        } else {
            $createData = [
                'menu_id' => 1,
                'title' => $this->title,
                'url'   => $this->url,
                'route' => $this->route,
                'parent_id' => $this->parent_id,
                'icon'  => $this->icon,
                'containerType' => $this->containerType,
                'order' => $this->order,
                'active'=> $this->active,
                'permission_id' => $this->permission_id,
                'super_admin_only' => $superAdminOnly,
                'tenant_specific' => $this->tenant_specific ?? false,
            ];
            if ($isSuperAdmin) {
                $createData['super_admin_append'] = $superAdminAppend;
            }
            $m = MenuItem::create($createData);
        }

        $this->showModal = false;
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        if ($this->deleteId) {
            MenuItem::destroy($this->deleteId);
        }
        $this->confirmingDelete = false;
    }

    private function resetForm()
    {
        $this->reset([
            'menuItemId','menu_id','title','url','route','parent_id','icon','containerType',
            'order','active','permission_id','super_admin_only','tenant_specific','super_admin_append'
        ]);
        // Ensure defaults to false for new items
        $this->super_admin_only = false;
        $this->tenant_specific = false;
        $this->super_admin_append = null;
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

    public function openPermissionModal()
    {
        $this->newPermissionName = '';
        $this->showPermissionModal = true;
    }

    public function closePermissionModal()
    {
        $this->showPermissionModal = false;
        $this->newPermissionName = '';
    }

    public function createPermission()
    {
        $this->validate([
            'newPermissionName' => 'required|string|max:255|unique:permissions,name'
        ], [
            'newPermissionName.required' => 'Permission name is required.',
            'newPermissionName.unique' => 'A permission with this name already exists.'
        ]);

        $permission = Permission::create([
            'name' => $this->newPermissionName,
            'guard_name' => 'web'
        ]);

        // Set the newly created permission as selected
        $this->permission_id = $permission->id;
        
        // Increment the refresh key to force the select to re-render
        $this->permissionRefreshKey++;

        $this->closePermissionModal();
        
        // Show success message
        session()->flash('message', 'Permission "' . $this->newPermissionName . '" created successfully!');
    }

    // Tenant access management (single)
    public ?int $selectedMenuItemForTenants = null;
    public array $selectedTenants = [];
    public bool $showTenantAccessModal = false;

    public function openTenantAccessModal($menuItemId)
    {
        $this->selectedMenuItemForTenants = $menuItemId;
        $menuItem = MenuItem::findOrFail($menuItemId);
        $this->selectedTenants = $menuItem->tenants->pluck('id')->toArray();
        $this->showTenantAccessModal = true;
    }

    public function closeTenantAccessModal()
    {
        $this->showTenantAccessModal = false;
        $this->selectedMenuItemForTenants = null;
        $this->selectedTenants = [];
    }

    public function saveTenantAccess()
    {
        if (!$this->selectedMenuItemForTenants) {
            return;
        }

        $menuItem = MenuItem::findOrFail($this->selectedMenuItemForTenants);
        $menuItem->tenants()->sync($this->selectedTenants);

        // Clear permission cache for all users
        $this->clearAllUserPermissionCaches();

        $this->closeTenantAccessModal();
        session()->flash('message', 'Tenant access updated successfully!');
    }
}
