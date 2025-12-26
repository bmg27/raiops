<?php

namespace App\Livewire\Permissions;

use App\Notifications\UserActivated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
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
    public ?string $password = null;
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
            ->when($this->userStatus, fn($q) => $q->where('status', $this->userStatus))
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

    /**
     * Generate a secure random password
     */
    public function generatePassword()
    {
        // Generate a password that meets requirements:
        // - At least 8 characters
        // - Contains uppercase and lowercase letters
        // - Contains at least one number
        // - Contains at least one special character
        
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*';
        
        // Ensure at least one of each required type
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        // Fill the rest randomly from all character sets
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = strlen($password); $i < 12; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Shuffle the password to randomize character positions
        $password = str_shuffle($password);
        
        $this->password = $password;
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
            $this->status = $user->status ?? 'Active';
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

        // Password validation for new users or when password is provided
        if (!$this->userId) {
            // New user - password required
            $validationRules['password'] = [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^a-zA-Z0-9]/',
            ];
        } elseif ($this->password) {
            // Editing existing user - password optional but must meet requirements if provided
            $validationRules['password'] = [
                'nullable',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^a-zA-Z0-9]/',
            ];
        }

        $this->validate($validationRules);

        if ($this->userId) {
            // Edit mode
            $user = User::findOrFail($this->userId);
            $user->name = $this->name;
            $user->email = $this->email;
            $user->status = $this->status;
            
            // Update password if provided
            if ($this->password) {
                $user->password = Hash::make($this->password);
            }
            
            $user->save();
        } else {
            // Create mode
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'status' => $this->status,
                'password' => Hash::make($this->password),
                'email_verified_at' => now(),
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
            $user->status = 'Disabled';
            $user->save();
            $this->dispatch('notify', type: 'success', message: 'User disabled!');
        }
    }

    private function resetForm()
    {
        $this->reset(['userId', 'name', 'email', 'password', 'selectedRoles', 'status', 'modalRoles']);
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
