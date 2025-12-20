<?php

namespace App\Livewire\Permissions;

use App\Models\SevenLocation;
use App\Models\Tenant;
use App\Notifications\UserActivated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Role;
use Illuminate\Validation\Rule;

class UsersIndex extends Component
{
    use WithPagination, WithoutUrlPagination;

    protected $paginationTheme = 'bootstrap';

    // Search & Sort
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public $userNotified = false;
    public $showEmailButton = false;
    public $user;
    public array $selectedLocation = [];

    // Tenant filter (super admin only)
    public ?int $selectedTenant = null;

    // Modal flags
    public bool $showUserModal = false;
    public bool $confirmingDelete = false;

    // Form fields
    public ?int $userId = null;
    public string $name = '';
    public string $email = '';
    public array $selectedRoles = [];

    // For deleting
    public ?int $deleteId = null;

    public $status = 'Active';
    public $userStatus = 'Active';
    public $statuses = ['Active', 'Pending', 'Disabled', 'Archived', 'Deleted'];
    public $selectedLocations = [];   // For pivot table data when access is "Some"

    // Collection of all available locations
    public $locations = [];
    public $locationAccess = 'None';
    public string|int $perPage = 25;

    // Roles for the modal (filtered by user's tenant when editing)
    public $modalRoles = [];

    // Tenant selection in modal (for super admins when creating users)
    public ?int $modalTenant = null;

    public function mount($userId = null)
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        // Load selectedTenant from cookie (super admin only)
        if ($isSuperAdmin) {
            if ($cookie = Cookie::get('users_index_selected_tenant')) {
                // Validate tenant exists
                if (Tenant::withoutGlobalScopes()->find($cookie)) {
                    $this->selectedTenant = (int)$cookie;
                }
            }
        }

        // Load selectedLocation from cookie
        if ($cookie = Cookie::get('users_index_selected_location')) {
            $decoded = json_decode($cookie, true);
            if (is_array($decoded) && !empty($decoded)) {
                // Check if "Any" or "All" is in the array
                if (in_array('Any', $decoded)) {
                    $this->selectedLocation = ['Any'];
                } elseif (in_array('All', $decoded)) {
                    $this->selectedLocation = ['All'];
                } else {
                    // Validate locations exist and belong to tenant
                    // Filter out non-numeric values and convert to integers
                    $locationIds = array_filter($decoded, function($val) {
                        return is_numeric($val) || (is_string($val) && ctype_digit($val));
                    });

                    if (!empty($locationIds)) {
                        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
                        $currentTenantId = auth()->user()?->tenant_id;

                        $validLocationIds = [];
                        if ($isSuperAdmin) {
                            $validLocationIds = SevenLocation::withoutGlobalScopes()
                                ->whereIn('id', $locationIds)
                                ->pluck('id')
                                ->toArray();
                        } else {
                            $validLocationIds = SevenLocation::where('tenant_id', $currentTenantId)
                                ->whereIn('id', $locationIds)
                                ->pluck('id')
                                ->toArray();
                        }

                        if (!empty($validLocationIds)) {
                            $this->selectedLocation = $validLocationIds;
                        }
                    }
                }
            }
        }

        // If a user ID is provided, execute openModal.
        if ($userId) {
            $this->openModal($userId);
        }
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $currentTenantId = auth()->user()?->tenant_id;

        // Filter locations based on selected tenant (for super admins)
        if ($isSuperAdmin && $this->selectedTenant) {
            // Super admin selected a specific tenant - show only that tenant's locations
            $allLocations = SevenLocation::withoutGlobalScopes()
                ->where('tenant_id', $this->selectedTenant)
                ->orderBy('name')
                ->get();
        } elseif ($isSuperAdmin) {
            // Super admin with no tenant selected - show all locations
            $allLocations = SevenLocation::withoutGlobalScopes()->orderBy('name')->get();
        } else {
            // Regular users only see their tenant's locations
            $allLocations = SevenLocation::orderBy('name')->get();
        }

        // Get roles: super admins see all, regular users see their tenant's roles + global roles (excluding Account Owner Primary)
        $allRoles = $isSuperAdmin
            ? Role::orderBy('name')->get()
            : Role::where(function($q) use ($currentTenantId) {
                $q->where('tenant_id', $currentTenantId)
                  ->orWhereNull('tenant_id'); // Include global roles (Super Admin)
            })->excludeAccountOwnerPrimary()->orderBy('name')->get();

        return view('livewire.permissions.users-index', [
            'users' => $this->query()->paginate($this->perPage),
            'allRoles' => $allRoles,
            'allLocations' => $allLocations,
            'allTenants' => $isSuperAdmin ? Tenant::withoutGlobalScopes()->orderBy('name')->get() : collect(),
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    private function query()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $currentTenantId = auth()->user()?->tenant_id;

        $query = User::query();

        // Super admins can see all users (bypass tenant scope)
        if ($isSuperAdmin) {
            $query->withoutGlobalScopes();
        } else {
            // Non-super-admin users can only see users from their own tenant
            $query->where('tenant_id', $currentTenantId);
        }

        $query->with(['locations', 'roles', 'tenant'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->where('deleted', '<>', 1)
            ->when($this->userStatus, fn($q) => $q->where('status', $this->userStatus))
            // Tenant filter (super admin only)
            ->when($isSuperAdmin && $this->selectedTenant, function ($q) {
                $q->where('tenant_id', $this->selectedTenant);
            })
            ->when(!empty($this->selectedLocation), function ($q) {
                // If "Any" is selected, don't filter by location at all (show all users)
                if (is_array($this->selectedLocation) && in_array('Any', $this->selectedLocation)) {
                    // Don't apply any location filter - show all users
                    return;
                }

                if (is_array($this->selectedLocation) && in_array('All', $this->selectedLocation)) {
                    // "All locations" selected — include only users with location_access = 'All'
                    $q->where('location_access', 'All');
                } else {
                    // Specific locations only
                    $q->whereHas('locations', function ($locQuery) {
                        $locQuery->whereIn('seven_locations.id', $this->selectedLocation);
                    });
                }
            })
            ->orderBy($this->sortField, $this->sortDirection);

        return $query;
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = ($this->sortDirection === 'asc') ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function clearLocationFilter()
    {
        $this->selectedLocation = [];
        Cookie::queue('users_index_selected_location', json_encode([]), 60 * 24 * 30); // 30 days
    }

    public function clearTenantFilter()
    {
        $this->selectedTenant = null;
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        if ($isSuperAdmin) {
            Cookie::queue('users_index_selected_tenant', '', 60 * 24 * 30); // 30 days
        }
    }

    public function updatedSelectedTenant()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        if ($isSuperAdmin) {
            Cookie::queue('users_index_selected_tenant', $this->selectedTenant ?? '', 60 * 24 * 30); // 30 days

            // Clear location filter when tenant changes (since available locations will change)
            $this->selectedLocation = [];
            Cookie::queue('users_index_selected_location', json_encode([]), 60 * 24 * 30);
        }
        $this->resetPage();
    }

    public function updatedSelectedLocation()
    {
        Cookie::queue('users_index_selected_location', json_encode($this->selectedLocation), 60 * 24 * 30); // 30 days
        $this->resetPage();
    }

    public function openModal($id = null)
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $currentTenantId = auth()->user()?->tenant_id;

        $this->resetForm();
        if ($id) {
            // Edit mode
            $userQuery = User::query();

            // Super admins can access any user (bypass tenant scope)
            if ($isSuperAdmin) {
                $userQuery->withoutGlobalScopes();
            } else {
                // Non-super-admin users can only access users from their own tenant
                $userQuery->where('tenant_id', $currentTenantId);
            }

            $user = $userQuery->with('tenant')->findOrFail($id);
            $this->user = $user;
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->status = $user->status;
            $this->selectedRoles = $user->roles->pluck('id')->map(fn($id) => (string)$id)->toArray();
            $this->locationAccess = $user->location_access; // 'None', 'All', or 'Some'

            // Set modal tenant (disabled in edit mode for super admins)
            if ($isSuperAdmin) {
                $this->modalTenant = $user->tenant_id;
            }

            // Filter locations based on user's tenant
            $userTenantId = $user->tenant_id;
            if ($isSuperAdmin) {
                $this->locations = SevenLocation::withoutGlobalScopes()
                    ->where('tenant_id', $userTenantId)
                    ->orderBy('name')
                    ->get();
            } else {
                $this->locations = SevenLocation::where('tenant_id', $userTenantId)
                    ->orderBy('name')
                    ->get();
            }

            if ($this->locationAccess === 'Some') {
                // Load the user's specific locations from the pivot table
                $this->selectedLocations = $user->locations()->pluck('id')->toArray();
            }

            // Filter roles to only show roles for this user's tenant (plus global roles)
            // Super admins can see and assign Account Owner Primary, tenants cannot
            $this->modalRoles = $isSuperAdmin
                ? Role::where(function($q) use ($userTenantId) {
                    $q->where('tenant_id', $userTenantId)
                      ->orWhereNull('tenant_id'); // Include global roles (Super Admin)
                })->orderBy('name')->get()
                : Role::where(function($q) use ($userTenantId) {
                    $q->where('tenant_id', $userTenantId)
                      ->orWhereNull('tenant_id'); // Include global roles (Super Admin)
                })->excludeAccountOwnerPrimary()->orderBy('name')->get();
        } else {
            // Create mode
            // Initialize modal tenant for super admins (use selectedTenant filter if available)
            if ($isSuperAdmin) {
                $this->modalTenant = $this->selectedTenant;
            } else {
                $this->modalTenant = $currentTenantId;
            }

            // Load roles only if tenant is selected (for super admins) or always for regular users
            $this->loadModalRoles();

            // Load locations based on modal tenant
            $this->loadModalLocations();
        }
        $this->showUserModal = true;
    }

    public function updatedModalTenant()
    {
        // When tenant changes in create mode, reload roles and locations
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        if (!$isSuperAdmin) {
            return; // Regular users can't change tenant
        }

        $this->loadModalRoles();
        $this->loadModalLocations();
    }

    private function loadModalRoles()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $currentTenantId = auth()->user()?->tenant_id;

        // Only load roles if tenant is selected (for super admins in create mode)
        if ($isSuperAdmin && !$this->modalTenant) {
            $this->modalRoles = collect();
            return;
        }

        $tenantIdForRoles = $isSuperAdmin ? $this->modalTenant : $currentTenantId;

        $this->modalRoles = $isSuperAdmin
            ? Role::where(function($q) use ($tenantIdForRoles) {
                $q->where('tenant_id', $tenantIdForRoles)
                  ->orWhereNull('tenant_id'); // Include global roles (Super Admin)
            })->orderBy('name')->get()
            : Role::where(function($q) use ($currentTenantId) {
                $q->where('tenant_id', $currentTenantId)
                  ->orWhereNull('tenant_id'); // Include global roles (Super Admin)
            })->excludeAccountOwnerPrimary()->orderBy('name')->get();
    }

    private function loadModalLocations()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $currentTenantId = auth()->user()?->tenant_id;

        $tenantIdForLocations = $isSuperAdmin && $this->modalTenant
            ? $this->modalTenant
            : $currentTenantId;

        if ($isSuperAdmin) {
            if ($tenantIdForLocations) {
                $this->locations = SevenLocation::withoutGlobalScopes()
                    ->where('tenant_id', $tenantIdForLocations)
                    ->orderBy('name')
                    ->get();
            } else {
                $this->locations = collect();
            }
        } else {
            $this->locations = SevenLocation::where('tenant_id', $currentTenantId)
                ->orderBy('name')
                ->get();
        }
    }

    public function save()
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        $currentTenantId = auth()->user()?->tenant_id;

        // Clear cache for the user being modified (not the admin making the change)
        $targetUserId = $this->userId;

        // Clear menu cache for current admin
        $cacheKey = 'menu-items-' . (auth()->check() ? auth()->id() : 'guest');
        Cache::forget($cacheKey);

        // Clear permission cache for the user being modified
        if ($targetUserId) {
            $userPermKey = "user_permissions_{$targetUserId}";
            Cache::forget($userPermKey);
        }
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],
            'status' => 'required|in:Active,Pending,Disabled,Archived',
            'locationAccess' => 'required|in:None,All,Some',
        ];

        // Require tenant selection when creating a user (for super admins)
        if ($isSuperAdmin && !$this->userId) {
            $validationRules['modalTenant'] = 'required|exists:tenants,id';
        }

        $this->validate($validationRules);

        if ($this->userId) {
            // Edit mode - ensure non-super-admins can only edit users from their tenant
            $userQuery = User::query();
            if ($isSuperAdmin) {
                $userQuery->withoutGlobalScopes();
            } else {
                $userQuery->where('tenant_id', $currentTenantId);
            }

            $user = $userQuery->findOrFail($this->userId);
            $user->name = $this->name;
            $user->email = $this->email;
            $user->status = $this->status;
            $user->location_access = $this->locationAccess;
            $user->save();
        } else {
            // Create mode - assign new user to selected tenant (for super admins) or current user's tenant
            $tenantIdForNewUser = $isSuperAdmin && $this->modalTenant
                ? $this->modalTenant
                : $currentTenantId;

            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'status' => $this->status,
                'location_access' => $this->locationAccess,
                'password' => bcrypt('password'),
                'tenant_id' => $tenantIdForNewUser,
            ]);
        }

        // Convert IDs to role names or role models
        // Ensure we only sync roles that belong to the user's tenant (or global roles)
        // Non-super-admins cannot assign Account Owner Primary role, but we preserve it if they already have it
        $userTenantId = $user->tenant_id;

        // Check if user already has Account Owner Primary role (to preserve it for tenant owners)
        $accountOwnerPrimaryRole = null;
        if (!$isSuperAdmin) {
            $accountOwnerPrimaryRole = $user->roles()
                ->where('name', 'Account Owner Primary')
                ->where('tenant_id', $userTenantId)
                ->first();
        }

        $roleQuery = Role::whereIn('id', $this->selectedRoles)
            ->where(function($q) use ($userTenantId) {
                $q->where('tenant_id', $userTenantId)
                  ->orWhereNull('tenant_id'); // Allow global roles
            });

        // Exclude Account Owner Primary for non-super-admins (it's hidden from them)
        if (!$isSuperAdmin) {
            $roleQuery->excludeAccountOwnerPrimary();
        }

        $rolesToSync = $roleQuery->get();

        // If user already had Account Owner Primary and they're not a super admin, preserve it
        if ($accountOwnerPrimaryRole && !$isSuperAdmin) {
            $rolesToSync->push($accountOwnerPrimaryRole);
        }

        $user->syncRoles($rolesToSync);

        if ($this->locationAccess === 'Some') {
            // Sync the pivot table with the selected locations
            $user->locations()->sync($this->selectedLocations);
        } else {
            // If access is 'None' or 'All', clear any previously assigned locations
            $user->locations()->detach();
        }

        $this->showUserModal = false;
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function deleteUser($id)
    {
        if ($id) {
            $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
            $currentTenantId = auth()->user()?->tenant_id;

            $userQuery = User::query();
            if ($isSuperAdmin) {
                $userQuery->withoutGlobalScopes();
            } else {
                // Non-super-admin users can only delete users from their own tenant
                $userQuery->where('tenant_id', $currentTenantId);
            }

            $user = $userQuery->findOrFail($id);
            $user->status = "Deleted";
            $user->deleted = 1;
            $user->save();
            $this->dispatch('notify', type: 'success', message: 'User deleted!');
        }
    }

    private function resetForm()
    {
        $this->reset(['userId', 'name', 'email', 'selectedRoles', 'status', 'selectedLocations', 'locationAccess', 'modalRoles', 'modalTenant']);
    }

    public function updatedStatus($value)
    {
        // Whenever the status changes, reset user notification flag
        if ($value == "Active") {
            $this->showEmailButton = true;
            $this->userNotified = false;
            //dump($this->showEmailButton,$this->userNotified);
        }
        /*
        if ($this->status === 'Active') {
            $this->userNotified = false; // Allow sending notification when status is Active
        }
        */
    }


    public function notifyUser()
    {
        if (! $this->user) {
            //session()->flash('message', 'User not found.');
            $this->dispatch('notify', type: 'success', message: 'User not found');
            return;
        }

        $activatedUser = $this->user;

        // 1) Fetch all users in the Admin & Super Admin roles
        $wanted = ['kdmin','Super Admin'];

// Grab only the roles that actually exist
        $roles = Role::whereIn('name', $wanted)->get();

// If none exist, short-circuit to an empty collection
        if ($roles->isEmpty()) {
            $roleRecipients = collect();
        } else {
            // Pull users who have any of those role IDs
            $roleRecipients = User::whereHas('roles', function($q) use ($roles) {
                $q->whereIn('id', $roles->pluck('id'));
            })->get();
        }

        // 2) Make sure we include the activated user too (and dedupe in case they also have one of those roles)
        $allRecipients = $roleRecipients
            ->push($activatedUser)
            ->unique('id')    // remove any duplicate user IDs
            ->values();       // reindex the collection

        //dd($allRecipients);
        // 3) Send the notification to everyone in that list
        Notification::send($allRecipients, new UserActivated($activatedUser));

        // 4) Your existing UI flags
        $this->userNotified    = true;
        $this->showEmailButton = false;
        //session()->flash('message', 'User has been notified.');
        $this->dispatch('notify', type: 'success', message: 'User has been notified.');
    }
}

