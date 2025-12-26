<?php

namespace App\Livewire\Permissions;

use App\Notifications\UserActivated;
use Illuminate\Support\Facades\Cache;
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
    public string|int $perPage = 25;

    // Roles for the modal
    public $modalRoles = [];

    public function mount($userId = null)
    {
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
        // Get all roles (RAIOPS doesn't have tenant-scoped roles)
        $allRoles = Role::orderBy('name')->get();

        return view('livewire.permissions.users-index', [
            'users' => $this->query()->paginate($this->perPage),
            'allRoles' => $allRoles,
        ]);
    }

    private function query()
    {
        $query = User::query();

        $query->with(['roles'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->userStatus, fn($q) => $q->where('is_active', $this->userStatus === 'Active' ? 1 : 0))
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

    public function openModal($id = null)
    {
        $this->resetForm();
        
        if ($id) {
            // Edit mode
            $user = User::with('roles')->findOrFail($id);
            $this->user = $user;
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->status = $user->is_active ? 'Active' : 'Disabled';
            $this->selectedRoles = $user->roles->pluck('id')->map(fn($id) => (string)$id)->toArray();
        }

        // Load all roles for selection
        $this->modalRoles = Role::orderBy('name')->get();
        
        $this->showUserModal = true;
    }

    public function save()
    {
        // Clear menu cache for current admin
        $cacheKey = 'menu-items-' . (auth()->check() ? auth()->id() : 'guest');
        Cache::forget($cacheKey);

        // Clear permission cache for the user being modified
        if ($this->userId) {
            $userPermKey = "user_permissions_{$this->userId}";
            Cache::forget($userPermKey);
        }

        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],
            'status' => 'required|in:Active,Disabled',
        ];

        $this->validate($validationRules);

        if ($this->userId) {
            // Edit mode
            $user = User::findOrFail($this->userId);
            $user->name = $this->name;
            $user->email = $this->email;
            $user->is_active = $this->status === 'Active';
            $user->save();
        } else {
            // Create mode
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'is_active' => $this->status === 'Active',
                'password' => bcrypt('password'), // Default password, user should change on first login
            ]);
        }

        // Sync Spatie roles
        $rolesToSync = Role::whereIn('id', $this->selectedRoles)->get();
        $user->syncRoles($rolesToSync);

        $this->showUserModal = false;
        $this->dispatch('notify', type: 'success', message: 'User saved successfully!');
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function deleteUser($id)
    {
        if ($id) {
            $user = User::findOrFail($id);
            $user->is_active = false;
            $user->save();
            $this->dispatch('notify', type: 'success', message: 'User disabled!');
        }
    }

    private function resetForm()
    {
        $this->reset(['userId', 'name', 'email', 'selectedRoles', 'status', 'modalRoles']);
    }

    public function updatedStatus($value)
    {
        // Whenever the status changes, reset user notification flag
        if ($value == "Active") {
            $this->showEmailButton = true;
            $this->userNotified = false;
        }
    }

    public function notifyUser()
    {
        if (! $this->user) {
            $this->dispatch('notify', type: 'error', message: 'User not found');
            return;
        }

        $activatedUser = $this->user;

        // Fetch all users in the Admin & Super Admin roles
        $wanted = ['Admin', 'Super Admin'];
        $roles = Role::whereIn('name', $wanted)->get();

        if ($roles->isEmpty()) {
            $roleRecipients = collect();
        } else {
            $roleRecipients = User::whereHas('roles', function($q) use ($roles) {
                $q->whereIn('id', $roles->pluck('id'));
            })->get();
        }

        // Include the activated user too (and dedupe)
        $allRecipients = $roleRecipients
            ->push($activatedUser)
            ->unique('id')
            ->values();

        // Send the notification
        Notification::send($allRecipients, new UserActivated($activatedUser));

        $this->userNotified = true;
        $this->showEmailButton = false;
        $this->dispatch('notify', type: 'success', message: 'User has been notified.');
    }
}
