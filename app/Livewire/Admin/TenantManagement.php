<?php

namespace App\Livewire\Admin;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\SevenLocation;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\RaiProviderSetting;
use App\Mail\TenantInvitationMail;
use App\Mail\TenantWelcomeMail;
use App\Services\ProviderSettingsService;
use App\Services\TenantRoleService;
use App\Models\Role;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

/**
 * TenantManagement Component
 *
 * Super Admin-only component for managing multi-tenant system tenants.
 * Provides comprehensive tenant administration including:
 *
 * - Tenant CRUD operations (create, read, update, delete)
 * - Location management per tenant
 * - User management and role assignment
 * - Subscription management
 * - Integration/Provider settings configuration
 *
 * Features:
 * - Search and filter tenants by name, email, status
 * - Pagination support
 * - Modal-based UI for all operations
 * - Integration with ProviderSettingsService for API credentials
 *
 * @package App\Livewire\Admin
 */
class TenantManagement extends Component
{
    use WithPagination, WithoutUrlPagination;

    protected $paginationTheme = 'bootstrap';

    // Search & Sort
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public string $statusFilter = 'all';
    public string|int $perPage = 25;
    
    // Active tab
    public string $activeTab = 'tenants'; // 'tenants', 'pending', 'invitations'
    
    // Active section badge (when tenant is selected)
    public string $activeSection = 'subscription'; // 'subscription', 'locations', 'providers'

    // Modal flags
    public bool $showDetailsModal = false;
    public bool $showLocationModal = false;
    public bool $showUserModal = false;
    public bool $showSubscriptionModal = false;
    public bool $showSettingsModal = false;
    public ?int $selectedTenantId = null;

    // Invitation management
    public bool $showInviteModal = false;
    public bool $showReviewInvitationModal = false;
    public ?int $reviewingInvitationId = null;
    public ?array $reviewData = null;
    
    // Invitation form fields
    public string $invite_email = '';
    public ?string $invite_first_name = null;
    public ?string $invite_last_name = null;
    public int $invite_days = 7;

    // Location management
    public ?int $locationId = null;
    public string $locationName = '';
    public ?string $locationAlias = null;
    public ?string $locationAddress = null;
    public ?string $locationCity = null;
    public ?string $locationState = null;
    public ?string $locationCountry = null;
    public ?string $locationToastLocation = null;
    public bool $locationActive = true;

    // User management
    public ?int $userId = null;
    public string $userName = '';
    public string $userEmail = '';
    public string $userStatus = 'Active';
    public array $selectedRoles = [];
    public string $userLocationAccess = 'All';
    public array $selectedUserLocations = [];

    // Subscription management
    public string $subscriptionPlan = 'starter';
    public int $subscriptionLocationCount = 1;

    // Tenant settings
    public string $tenantName = '';
    public string $tenantStatus = 'active';
    public ?string $tenantContactName = null;
    public ?string $tenantContactEmail = null;
    public ?string $tenantTrialEndsAt = null;

    // Provider settings (Integrations) management
    public bool $showProviderSettingsModal = false;
    public ?string $selectedProviderName = null; // integration_slug
    public string $providerSettingsText = '';
    public bool $providerActive = true;
    public array $integrationFields = []; // dynamic key => value for selected provider
    public ?int $selectedLocationId = null; // For location-level integrations
    public array $tenantLocations = []; // Available locations for the selected tenant

    // Available providers (catalog) slug => display_name
    public array $availableProviders = [];

    public function mount()
    {
        // Only super admins can access this
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can access tenant management.');
        }
    }

    // ========== INVITATION MANAGEMENT ==========

    public function openInviteModal()
    {
        $this->resetInviteForm();
        $this->showInviteModal = true;
    }

    public function closeInviteModal()
    {
        $this->showInviteModal = false;
        $this->resetInviteForm();
    }

    public function resetInviteForm()
    {
        $this->invite_email = '';
        $this->invite_first_name = null;
        $this->invite_last_name = null;
        $this->invite_days = 7;
    }

    public function sendInvitation()
    {
        $this->validate([
            'invite_email' => 'required|email|max:255',
            'invite_first_name' => 'nullable|string|max:128',
            'invite_last_name' => 'nullable|string|max:128',
            'invite_days' => 'required|integer|min:1|max:30',
        ]);

        // Check if user already exists
        $existingUser = User::where('email', $this->invite_email)->first();
        if ($existingUser) {
            $this->addError('invite_email', 'A user with this email address already exists in the system.');
            return;
        }

        // Check if invitation already exists for this email (any status except rejected)
        $existingInvitation = TenantInvitation::where('email', $this->invite_email)
            ->whereIn('status', ['pending', 'submitted', 'approved'])
            ->first();

        if ($existingInvitation) {
            $statusLabel = match($existingInvitation->status) {
                'pending' => 'pending',
                'submitted' => 'submitted and awaiting review',
                'approved' => 'already approved',
                default => $existingInvitation->status,
            };
            $this->addError('invite_email', "An invitation for this email is {$statusLabel}.");
            return;
        }

        $invitation = TenantInvitation::create([
            'email' => $this->invite_email,
            'first_name' => $this->invite_first_name,
            'last_name' => $this->invite_last_name,
            'expires_at' => now()->addDays($this->invite_days),
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        // Send invitation email
        try {
            Mail::to($this->invite_email)->send(new TenantInvitationMail($invitation));
            $this->dispatch('notify', type: 'success', message: "Invitation sent successfully to {$this->invite_email}!");
        } catch (\Exception $e) {
            \Log::error('Failed to send tenant invitation email', [
                'invitation_id' => $invitation->id,
                'email' => $this->invite_email,
                'error' => $e->getMessage(),
            ]);
            $this->addError('invite_email', 'Invitation created but email failed to send. Please try resending.');
        }

        $this->closeInviteModal();
    }

    public function openReviewInvitationModal($invitationId)
    {
        $invitation = TenantInvitation::findOrFail($invitationId);
        
        if ($invitation->status !== 'submitted') {
            $this->dispatch('notify', type: 'error', message: 'This invitation is not in submitted status.');
            return;
        }

        $this->reviewingInvitationId = $invitationId;
        $this->reviewData = $invitation->response_data ?? [];
        $this->showReviewInvitationModal = true;
    }

    public function closeReviewInvitationModal()
    {
        $this->showReviewInvitationModal = false;
        $this->reviewingInvitationId = null;
        $this->reviewData = null;
    }

    public function approveInvitation()
    {
        if (!$this->reviewingInvitationId) {
            $this->dispatch('notify', type: 'error', message: 'No invitation selected for approval.');
            return;
        }

        try {
            $invitation = TenantInvitation::findOrFail($this->reviewingInvitationId);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Invitation not found.');
            $this->closeReviewInvitationModal();
            return;
        }
        
        if ($invitation->status !== 'submitted') {
            $this->dispatch('notify', type: 'error', message: 'This invitation is not in submitted status. Current status: ' . $invitation->status);
            $this->closeReviewInvitationModal();
            return;
        }

        $data = $invitation->response_data;
        if (!$data || !is_array($data)) {
            $this->dispatch('notify', type: 'error', message: 'No registration data found for this invitation.');
            $this->closeReviewInvitationModal();
            return;
        }

        // Validate required fields
        $required = ['company_name', 'contact_name', 'email', 'password', 'selected_plan', 'location_count', 'trial_ends_at'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->dispatch('notify', type: 'error', message: "Missing required field in registration data: {$field}");
                $this->closeReviewInvitationModal();
                return;
            }
        }

        // Check if user already exists (double-check before creating)
        $existingUser = User::where('email', $data['email'])->first();
        if ($existingUser) {
            $this->dispatch('notify', type: 'error', message: "A user with email {$data['email']} already exists in the system. Cannot create duplicate account.");
            $this->closeReviewInvitationModal();
            return;
        }

        // Check if tenant with same name already exists
        $existingTenant = Tenant::where('name', $data['company_name'])->first();
        if ($existingTenant) {
            $this->dispatch('notify', type: 'error', message: "A tenant with name '{$data['company_name']}' already exists. Please use a different company name.");
            $this->closeReviewInvitationModal();
            return;
        }

        DB::beginTransaction();

        try {
            // Create tenant
            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'primary_contact_name' => $data['contact_name'],
                'primary_contact_email' => $data['email'],
                'status' => 'active',
                'trial_ends_at' => \Carbon\Carbon::parse($data['trial_ends_at']),
                'settings' => [
                    'plan' => $data['selected_plan'],
                    'features' => $data['plan_config']['features'] ?? [],
                    'requested_locations' => $data['location_count'],
                ],
            ]);

            // Create user with the hashed password from response_data
            $user = User::create([
                'name' => $data['contact_name'],
                'email' => $data['email'],
                'password' => $data['password'], // Already hashed
                'tenant_id' => $tenant->id,
                'is_tenant_owner' => true,
                'status' => 'Active',
                'location_access' => 'All',
                'email_verified_at' => now(),
            ]);

            // Create tenant-specific Admin role with all non-super-admin permissions
            $adminRole = TenantRoleService::createOrGetTenantAdminRole($tenant->id);
            $user->assignRole($adminRole);

            // Create subscription
            TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_name' => $data['selected_plan'],
                'base_price' => $data['base_price'] ?? 0,
                'location_count' => $data['location_count'],
                'price_per_location' => $data['per_location_price'] ?? 0,
                'total_monthly_price' => $data['total_monthly_price'] ?? 0,
                'billing_cycle' => 'monthly',
                'status' => 'trial',
                'next_billing_date' => \Carbon\Carbon::parse($data['trial_ends_at']),
            ]);

            // Update invitation status
            $invitation->status = 'approved';
            $invitation->tenant_id = $tenant->id;
            $invitation->save();

            // Send welcome email
            try {
                Mail::to($user->email)->send(new TenantWelcomeMail($tenant, $user));
            } catch (\Exception $e) {
                \Log::error('Failed to send tenant welcome email', [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            DB::commit();
            $this->dispatch('notify', type: 'success', message: "Tenant account approved and created successfully! Welcome email sent to {$user->email}.");
            
            // Reset the active tab to show updated data
            if ($this->activeTab === 'pending') {
                // Stay on pending tab, data will refresh
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Failed to approve tenant invitation', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data_keys' => $data ? array_keys($data) : 'no data',
            ]);
            $this->dispatch('notify', type: 'error', message: 'Failed to approve invitation: ' . $e->getMessage());
        }

        $this->closeReviewInvitationModal();
    }

    public function rejectInvitation()
    {
        if (!$this->reviewingInvitationId) {
            return;
        }

        $invitation = TenantInvitation::findOrFail($this->reviewingInvitationId);
        $invitation->status = 'rejected';
        $invitation->save();

        $this->dispatch('notify', type: 'success', message: 'Invitation rejected.');
        $this->closeReviewInvitationModal();
    }

    public function resendInvitation($invitationId)
    {
        $invitation = TenantInvitation::findOrFail($invitationId);
        
        if ($invitation->status !== 'pending') {
            $this->dispatch('notify', type: 'error', message: 'Can only resend pending invitations.');
            return;
        }

        // Extend expiration by 7 days
        $invitation->expires_at = now()->addDays(7);
        $invitation->save();

        try {
            Mail::to($invitation->email)->send(new TenantInvitationMail($invitation));
            $this->dispatch('notify', type: 'success', message: "Invitation resent to {$invitation->email}!");
        } catch (\Exception $e) {
            \Log::error('Failed to resend tenant invitation email', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('notify', type: 'error', message: 'Failed to resend invitation email.');
        }
    }

    public function deleteInvitation($invitationId)
    {
        $invitation = TenantInvitation::findOrFail($invitationId);
        
        if (in_array($invitation->status, ['approved', 'submitted'])) {
            $this->dispatch('notify', type: 'error', message: 'Cannot delete approved or submitted invitations.');
            return;
        }

        $invitation->delete();
        $this->dispatch('notify', type: 'success', message: 'Invitation deleted.');
    }

    public function getPendingInvitations()
    {
        return TenantInvitation::where('status', 'submitted')
            ->orderBy('accepted_at', 'desc')
            ->get();
    }

    public function getAllInvitations()
    {
        return TenantInvitation::orderBy('created_at', 'desc')
            ->with('createdBy')
            ->get();
    }

    public function render()
    {
        // Ensure catalog is loaded so the providers section can list providers
        $this->loadAvailableProviders();

        $tenants = $this->query()->paginate($this->perPage);

        // Load provider settings for selected tenant
        $providerSettingsByTenant = [];
        if ($this->selectedTenantId) {
            $providerSettingsByTenant[$this->selectedTenantId] = $this->getProviderSettings($this->selectedTenantId);
        }

        // Load invitations
        $pendingInvitations = $this->getPendingInvitations();
        $allInvitations = $this->getAllInvitations();

        return view('livewire.admin.tenant-management', [
            'tenants' => $tenants,
            'providerSettingsByTenant' => $providerSettingsByTenant,
            'availableProviders' => $this->availableProviders,
            'pendingInvitations' => $pendingInvitations,
            'allInvitations' => $allInvitations,
        ])->layout('layouts.rai');
    }

    private function query()
    {
        // Super admins should see all tenants (bypass tenant scope)
        $query = Tenant::withoutGlobalScopes()
            ->with(['subscription', 'owner', 'users'])
            ->withCount(['users'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('primary_contact_email', 'like', '%' . $this->search . '%')
                        ->orWhere('primary_contact_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->orderBy($this->sortField, $this->sortDirection);

        // Add locations count without global scopes
        $query->withCount([
            'locations' => function ($q) {
                $q->withoutGlobalScopes();
            }
        ]);

        return $query;
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

    public function viewDetails($tenantId)
    {
        $this->selectedTenantId = $tenantId;
        $this->activeSection = 'subscription'; // Default to subscription section
        $this->showDetailsModal = false; // No longer using modal
        $this->loadTenantSettingsForEdit($tenantId);
    }
    
    private function loadTenantSettingsForEdit($tenantId)
    {
        $tenant = Tenant::withoutGlobalScopes()->with('subscription')->findOrFail($tenantId);
        $this->tenantName = $tenant->name;
        $this->tenantStatus = $tenant->status;
        $this->tenantContactName = $tenant->primary_contact_name;
        $this->tenantContactEmail = $tenant->primary_contact_email;
        $this->tenantTrialEndsAt = $tenant->trial_ends_at ? $tenant->trial_ends_at->format('Y-m-d\TH:i') : null;
        
        // Load subscription data
        if ($tenant->subscription) {
            $planKey = strtolower($tenant->subscription->plan_name);
            if (in_array($planKey, ['starter', 'professional', 'enterprise'])) {
                $this->subscriptionPlan = $planKey;
            } else {
                $this->subscriptionPlan = 'starter';
            }
            $this->subscriptionLocationCount = $tenant->subscription->location_count;
        } else {
            $this->subscriptionPlan = 'starter';
            $this->subscriptionLocationCount = $tenant->locations()->withoutGlobalScopes()->count() ?: 1;
        }
    }
    
    public function saveTenantAndSubscription()
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage tenant settings.');
        }

        // Validate tenant settings
        $rules = [
            'tenantName' => 'required|string|max:255',
            'tenantStatus' => 'required|in:trial,active,suspended,cancelled',
            'tenantContactName' => 'nullable|string|max:255',
            'tenantContactEmail' => 'nullable|email|max:255',
            'subscriptionPlan' => 'required|in:starter,professional,enterprise',
            'subscriptionLocationCount' => 'required|integer|min:1',
        ];

        // Require trial_ends_at when status is trial
        if ($this->tenantStatus === 'trial') {
            $rules['tenantTrialEndsAt'] = 'required|date';
        }

        $this->validate($rules);

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($this->selectedTenantId);
        $planConfig = TenantSubscription::getPlanConfig($this->subscriptionPlan);

        // Check if location count exceeds plan max
        if ($this->subscriptionLocationCount > $planConfig['max_locations']) {
            $this->dispatch('notify', type: 'error', message: "Location count ({$this->subscriptionLocationCount}) exceeds plan maximum ({$planConfig['max_locations']}).");
            return;
        }

        // Get actual location count
        $actualLocationCount = $tenant->locations()->withoutGlobalScopes()->count();
        if ($actualLocationCount > $this->subscriptionLocationCount) {
            $this->dispatch('notify', type: 'error', message: "Cannot set location count to {$this->subscriptionLocationCount}. Tenant has {$actualLocationCount} locations. Delete locations first.");
            return;
        }

        // Update tenant settings
        $updateData = [
            'name' => $this->tenantName,
            'status' => $this->tenantStatus,
            'primary_contact_name' => $this->tenantContactName,
            'primary_contact_email' => $this->tenantContactEmail,
        ];

        // Only update trial_ends_at if status is trial
        if ($this->tenantStatus === 'trial' && $this->tenantTrialEndsAt) {
            $updateData['trial_ends_at'] = \Carbon\Carbon::parse($this->tenantTrialEndsAt);
        } elseif ($this->tenantStatus !== 'trial') {
            // Clear trial_ends_at if status is not trial
            $updateData['trial_ends_at'] = null;
        }

        $tenant->update($updateData);

        // Update or create subscription
        if ($tenant->subscription) {
            $tenant->subscription->update([
                'plan_name' => $this->subscriptionPlan,
                'base_price' => $planConfig['base_price'],
                'price_per_location' => $planConfig['price_per_location'],
                'location_count' => $this->subscriptionLocationCount,
            ]);
            $tenant->subscription->updateTotal();
            $tenant->subscription->save();
            $this->dispatch('notify', type: 'success', message: "Tenant settings and subscription updated successfully!");
        } else {
            $subscription = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_name' => $this->subscriptionPlan,
                'base_price' => $planConfig['base_price'],
                'price_per_location' => $planConfig['price_per_location'],
                'location_count' => $this->subscriptionLocationCount,
                'billing_cycle' => 'monthly',
                'status' => 'active',
            ]);
            $subscription->updateTotal();
            $subscription->save();
            $this->dispatch('notify', type: 'success', message: "Tenant settings and subscription created successfully!");
        }
    }

    public function backToTenantList()
    {
        $this->selectedTenantId = null;
        $this->activeSection = 'subscription';
    }
    
    public function setActiveSection($section)
    {
        $this->activeSection = $section;
        // Reload tenant settings when switching to subscription section
        if ($section === 'subscription' && $this->selectedTenantId) {
            $this->loadTenantSettingsForEdit($this->selectedTenantId);
        }
    }


    // ========== LOCATION MANAGEMENT ==========

    public function openLocationModal($tenantId, $locationId = null)
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage locations.');
        }

        $this->selectedTenantId = $tenantId;
        $this->locationId = $locationId;
        $this->resetLocationForm();

        if ($locationId) {
            // Edit mode - load location data
            $location = SevenLocation::withoutGlobalScopes()->findOrFail($locationId);
            $this->locationName = $location->name;
            $this->locationAlias = $location->alias;
            $this->locationAddress = $location->address;
            $this->locationCity = $location->city;
            $this->locationState = $location->state;
            $this->locationCountry = $location->country;
            $this->locationToastLocation = $location->toast_location;
            $this->locationActive = $location->active ?? true;
        }

        $this->showLocationModal = true;
    }

    public function closeLocationModal()
    {
        $this->showLocationModal = false;
        $this->locationId = null;
        $this->resetLocationForm();
    }

    public function saveLocation()
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage locations.');
        }

        $this->validate([
            'locationName' => 'required|string|max:255',
            'locationAlias' => 'nullable|string|max:255',
            'locationAddress' => 'nullable|string|max:255',
            'locationCity' => 'nullable|string|max:255',
            'locationState' => 'nullable|string|max:255',
            'locationCountry' => 'nullable|string|max:255',
            'locationToastLocation' => 'required|string|max:100',
        ]);

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($this->selectedTenantId);

        if ($this->locationId) {
            // Update existing location
            $location = SevenLocation::withoutGlobalScopes()->findOrFail($this->locationId);
            $location->update([
                'name' => $this->locationName,
                'alias' => $this->locationAlias,
                'address' => $this->locationAddress,
                'city' => $this->locationCity,
                'state' => $this->locationState,
                'country' => $this->locationCountry,
                'toast_location' => $this->locationToastLocation,
                'active' => $this->locationActive,
            ]);
            $this->dispatch('notify', type: 'success', message: "Location '{$location->name}' updated successfully!");
        } else {
            // Create new location - generate location_id and api_location_id
            $maxLocationId = SevenLocation::withoutGlobalScopes()->max('location_id') ?? 0;
            $newLocationId = max(1000, $maxLocationId + 1);
            $apiLocationId = $newLocationId;

            $location = SevenLocation::withoutGlobalScopes()->create([
                'api_location_id' => $apiLocationId,
                'location_id' => $newLocationId,
                'tenant_id' => $tenant->id,
                'name' => $this->locationName,
                'alias' => $this->locationAlias,
                'address' => $this->locationAddress,
                'city' => $this->locationCity,
                'state' => $this->locationState,
                'country' => $this->locationCountry ?? 'US',
                'toast_location' => $this->locationToastLocation,
                'hasResy' => false,
                'groupTips' => false,
                'active' => $this->locationActive,
            ]);

            $this->dispatch('notify', type: 'success', message: "Location '{$location->name}' created successfully!");
        }

        // Update subscription location count after location change
        $tenant->refresh();
        if ($tenant->subscription) {
            $actualLocationCount = $tenant->locations()->withoutGlobalScopes()->count();
            $tenant->subscription->location_count = $actualLocationCount;
            $tenant->subscription->updateTotal();
            $tenant->subscription->save();
        }

        $this->closeLocationModal();
    }

    public function deleteLocation($locationId)
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can delete locations.');
        }

        $location = SevenLocation::withoutGlobalScopes()->findOrFail($locationId);
        $locationName = $location->name;
        $tenant = $location->tenant;

        $location->delete();

        // Update subscription location count after deletion
        if ($tenant) {
            $tenant->refresh();
            if ($tenant->subscription) {
                $actualLocationCount = $tenant->locations()->withoutGlobalScopes()->count();
                $tenant->subscription->location_count = $actualLocationCount;
                $tenant->subscription->updateTotal();
                $tenant->subscription->save();
            }
        }

        $this->dispatch('notify', type: 'success', message: "Location '{$locationName}' deleted successfully!");
    }

    private function resetLocationForm()
    {
        $this->locationName = '';
        $this->locationAlias = null;
        $this->locationAddress = null;
        $this->locationCity = null;
        $this->locationState = null;
        $this->locationCountry = null;
        $this->locationToastLocation = null;
        $this->locationActive = true;
    }

    // ========== USER MANAGEMENT ==========

    public function openUserModal($tenantId, $userId = null)
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage users.');
        }

        $this->selectedTenantId = $tenantId;
        $this->userId = $userId;
        $this->resetUserForm();

        if ($userId) {
            // Edit mode - load user data
            $user = User::withoutGlobalScopes()->findOrFail($userId);
            $this->userName = $user->name;
            $this->userEmail = $user->email;
            $this->userStatus = $user->status;
            $this->selectedRoles = $user->roles->pluck('id')->map(fn($id) => (string)$id)->toArray();
            $this->userLocationAccess = $user->location_access ?? 'All';
            if ($this->userLocationAccess === 'Some') {
                $this->selectedUserLocations = $user->locations()->withoutGlobalScopes()->pluck('id')->toArray();
            }
        }

        $this->showUserModal = true;
    }

    public function closeUserModal()
    {
        $this->showUserModal = false;
        $this->userId = null;
        $this->resetUserForm();
    }

    public function saveUser()
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage users.');
        }

        $this->validate([
            'userName' => 'required|string|max:255',
            'userEmail' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->userId)->withoutGlobalScopes(),
            ],
            'userStatus' => 'required|in:Active,Pending,Disabled,Archived',
            'userLocationAccess' => 'required|in:None,All,Some',
            'selectedUserLocations' => 'required_if:userLocationAccess,Some|array',
        ]);

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($this->selectedTenantId);

        // Clear permission cache
        if ($this->userId) {
            $userPermKey = "user_permissions_{$this->userId}";
            Cache::forget($userPermKey);
        }

        if ($this->userId) {
            // Update existing user
            $user = User::withoutGlobalScopes()->findOrFail($this->userId);
            $user->update([
                'name' => $this->userName,
                'email' => $this->userEmail,
                'status' => $this->userStatus,
                'location_access' => $this->userLocationAccess,
                'tenant_id' => $tenant->id, // Ensure user belongs to tenant
            ]);
            $this->dispatch('notify', type: 'success', message: "User '{$user->name}' updated successfully!");
        } else {
            // Create new user
            $user = User::withoutGlobalScopes()->create([
                'name' => $this->userName,
                'email' => $this->userEmail,
                'status' => $this->userStatus,
                'location_access' => $this->userLocationAccess,
                'tenant_id' => $tenant->id,
                'password' => bcrypt('password'), // Temporary password - user should change on first login
                'email_verified_at' => now(), // Auto-verify for super admin created users
            ]);
            $this->dispatch('notify', type: 'success', message: "User '{$user->name}' created successfully!");
        }

        // Sync roles
        if (!empty($this->selectedRoles)) {
            $roles = Role::whereIn('id', $this->selectedRoles)->get();
            $user->syncRoles($roles);
        } else {
            $user->syncRoles([]);
        }

        // Sync locations
        if ($this->userLocationAccess === 'Some') {
            $user->locations()->withoutGlobalScopes()->sync($this->selectedUserLocations);
        } else {
            $user->locations()->withoutGlobalScopes()->detach();
        }

        $this->closeUserModal();
    }

    public function deleteUser($userId)
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can delete users.');
        }

        $user = User::withoutGlobalScopes()->findOrFail($userId);
        $userName = $user->name;

        // Don't delete tenant owners
        if ($user->is_tenant_owner) {
            $this->dispatch('notify', type: 'error', message: "Cannot delete tenant owner '{$userName}'. Remove tenant owner status first.");
            return;
        }

        $user->status = 'Deleted';
        $user->deleted = 1;
        $user->save();

        $this->dispatch('notify', type: 'success', message: "User '{$userName}' deleted successfully!");
    }

    private function resetUserForm()
    {
        $this->userName = '';
        $this->userEmail = '';
        $this->userStatus = 'Active';
        $this->selectedRoles = [];
        $this->userLocationAccess = 'All';
        $this->selectedUserLocations = [];
    }

    // ========== SUBSCRIPTION MANAGEMENT ==========

    public function openSubscriptionModal($tenantId)
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage subscriptions.');
        }

        $this->selectedTenantId = $tenantId;
        $tenant = Tenant::withoutGlobalScopes()->with('subscription')->findOrFail($tenantId);

        if ($tenant->subscription) {
            // Plan name is stored as key (starter, professional, enterprise) in database
            $planKey = strtolower($tenant->subscription->plan_name);
            // Ensure it's a valid key
            if (in_array($planKey, ['starter', 'professional', 'enterprise'])) {
                $this->subscriptionPlan = $planKey;
            } else {
                // Fallback to starter if invalid
                $this->subscriptionPlan = 'starter';
            }
            $this->subscriptionLocationCount = $tenant->subscription->location_count;
        } else {
            // Default to starter plan with current location count
            $this->subscriptionPlan = 'starter';
            $this->subscriptionLocationCount = $tenant->locations()->withoutGlobalScopes()->count() ?: 1;
        }

        $this->showSubscriptionModal = true;
    }

    public function closeSubscriptionModal()
    {
        $this->showSubscriptionModal = false;
        $this->subscriptionPlan = 'starter';
        $this->subscriptionLocationCount = 1;
    }

    public function saveSubscription()
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage subscriptions.');
        }

        $this->validate([
            'subscriptionPlan' => 'required|in:starter,professional,enterprise',
            'subscriptionLocationCount' => 'required|integer|min:1',
        ]);

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($this->selectedTenantId);
        $planConfig = TenantSubscription::getPlanConfig($this->subscriptionPlan);

        // Check if location count exceeds plan max
        if ($this->subscriptionLocationCount > $planConfig['max_locations']) {
            $this->dispatch('notify', type: 'error', message: "Location count ({$this->subscriptionLocationCount}) exceeds plan maximum ({$planConfig['max_locations']}).");
            return;
        }

        // Get actual location count
        $actualLocationCount = $tenant->locations()->withoutGlobalScopes()->count();
        if ($actualLocationCount > $this->subscriptionLocationCount) {
            $this->dispatch('notify', type: 'error', message: "Cannot set location count to {$this->subscriptionLocationCount}. Tenant has {$actualLocationCount} locations. Delete locations first.");
            return;
        }

        if ($tenant->subscription) {
            // Update existing subscription - store plan key (starter, professional, enterprise)
            $tenant->subscription->update([
                'plan_name' => $this->subscriptionPlan, // Store key, not name
                'base_price' => $planConfig['base_price'],
                'price_per_location' => $planConfig['price_per_location'],
                'location_count' => $this->subscriptionLocationCount,
            ]);
            $tenant->subscription->updateTotal();
            $tenant->subscription->save();
            $this->dispatch('notify', type: 'success', message: "Subscription updated to {$planConfig['name']} plan!");
        } else {
            // Create new subscription - store plan key (starter, professional, enterprise)
            $subscription = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_name' => $this->subscriptionPlan, // Store key, not name
                'base_price' => $planConfig['base_price'],
                'price_per_location' => $planConfig['price_per_location'],
                'location_count' => $this->subscriptionLocationCount,
                'billing_cycle' => 'monthly',
                'status' => 'active',
            ]);
            $subscription->updateTotal();
            $subscription->save();
            $this->dispatch('notify', type: 'success', message: "Subscription created: {$planConfig['name']} plan!");
        }

        $this->closeSubscriptionModal();
    }

    // ========== TENANT SETTINGS MANAGEMENT ==========

    public function openSettingsModal($tenantId)
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage tenant settings.');
        }

        $this->selectedTenantId = $tenantId;
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);

        $this->tenantName = $tenant->name;
        $this->tenantStatus = $tenant->status;
        $this->tenantContactName = $tenant->primary_contact_name;
        $this->tenantContactEmail = $tenant->primary_contact_email;
        $this->tenantTrialEndsAt = $tenant->trial_ends_at ? $tenant->trial_ends_at->format('Y-m-d\TH:i') : null;

        $this->showSettingsModal = true;
    }

    public function closeSettingsModal()
    {
        $this->showSettingsModal = false;
        $this->tenantName = '';
        $this->tenantStatus = 'active';
        $this->tenantContactName = null;
        $this->tenantContactEmail = null;
        $this->tenantTrialEndsAt = null;
    }

    public function saveTenantSettings()
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage tenant settings.');
        }

        $rules = [
            'tenantName' => 'required|string|max:255',
            'tenantStatus' => 'required|in:trial,active,suspended,cancelled',
            'tenantContactName' => 'nullable|string|max:255',
            'tenantContactEmail' => 'nullable|email|max:255',
        ];

        // Require trial_ends_at when status is trial
        if ($this->tenantStatus === 'trial') {
            $rules['tenantTrialEndsAt'] = 'required|date';
        }

        $this->validate($rules);

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($this->selectedTenantId);

        $updateData = [
            'name' => $this->tenantName,
            'status' => $this->tenantStatus,
            'primary_contact_name' => $this->tenantContactName,
            'primary_contact_email' => $this->tenantContactEmail,
        ];

        // Only update trial_ends_at if status is trial
        if ($this->tenantStatus === 'trial' && $this->tenantTrialEndsAt) {
            $updateData['trial_ends_at'] = \Carbon\Carbon::parse($this->tenantTrialEndsAt);
        } elseif ($this->tenantStatus !== 'trial') {
            // Clear trial_ends_at if status is not trial
            $updateData['trial_ends_at'] = null;
        }

        $tenant->update($updateData);

        $this->dispatch('notify', type: 'success', message: "Tenant '{$tenant->name}' updated successfully!");

        $this->closeSettingsModal();
    }

    // ========== PROVIDER SETTINGS MANAGEMENT ==========

    public $isEditingIntegration = false;

    public function openProviderSettingsModal($tenantId, $providerName = null, $locationId = null)
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage provider settings.');
        }

        // Load catalog on demand
        $this->loadAvailableProviders();

        $this->selectedTenantId = $tenantId;
        $this->selectedLocationId = $locationId;
        $this->resetProviderSettingsForm();
        $this->isEditingIntegration = false;
        
        // Load tenant locations for location-level integrations
        $this->loadTenantLocations($tenantId);
        
        // If editing existing provider setting, load it
        if ($providerName) {
            $this->selectedProviderName = $providerName;
            $isLocationLevel = $this->isLocationLevelIntegration($providerName);
            
            if ($isLocationLevel) {
                // Load from location integrations
                if ($locationId) {
                    $setting = DB::table('rai_location_integrations')
                        ->where('location_id', $locationId)
                        ->where('integration_slug', $providerName)
                        ->first();

                    if ($setting) {
                        $this->isEditingIntegration = true;
                        $this->selectedLocationId = $locationId;
                        $decoded = is_string($setting->settings)
                            ? json_decode($setting->settings, true)
                            : ($setting->settings ?? []);
                        $this->providerSettingsText = json_encode($decoded ?? [], JSON_PRETTY_PRINT);
                        $this->providerActive = (($setting->status ?? 'active') === 'active');
                        $this->populateIntegrationFieldsFromSettings($providerName, is_array($decoded) ? $decoded : []);
                    } else {
                        $this->populateIntegrationFieldsFromSettings($providerName, []);
                    }
                } else {
                    // Location-level integration but no location selected - initialize empty
                    $this->populateIntegrationFieldsFromSettings($providerName, []);
                }
            } else {
                // Load from tenant integrations
                $setting = DB::table('rai_tenant_integrations')
                    ->where('tenant_id', $tenantId)
                    ->where('integration_slug', $providerName)
                    ->first();

                if ($setting) {
                    $this->isEditingIntegration = true;
                    $decoded = is_string($setting->settings)
                        ? json_decode($setting->settings, true)
                        : ($setting->settings ?? []);
                    $this->providerSettingsText = json_encode($decoded ?? [], JSON_PRETTY_PRINT);
                    $this->providerActive = (($setting->status ?? 'active') === 'active');
                    $this->populateIntegrationFieldsFromSettings($providerName, is_array($decoded) ? $decoded : []);
                } else {
                    $this->populateIntegrationFieldsFromSettings($providerName, []);
                }
            }
        } else {
            // New provider - no selection yet
            $this->selectedProviderName = null;
        }

        $this->showProviderSettingsModal = true;
    }
    
    private function isLocationLevelIntegration(string $providerSlug): bool
    {
        // Check database to see if this integration is marked as location-level
        if (!Schema::hasTable('rai_integrations')) {
            return false;
        }
        
        try {
            $integration = DB::table('rai_integrations')
                ->where('slug', $providerSlug)
                ->where('is_active', 1)
                ->first(['is_location_level']);
                
            return $integration && ($integration->is_location_level ?? false);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function loadTenantLocations(int $tenantId): void
    {
        $this->tenantLocations = SevenLocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->pluck('name', 'id')
            ->toArray();
    }

    public function closeProviderSettingsModal()
    {
        $this->showProviderSettingsModal = false;
        $this->selectedProviderName = null;
        $this->resetProviderSettingsForm();
    }

    public function updatedSelectedProviderName($providerSlug)
    {
        // When a provider is selected in the modal, initialize empty fields based on schema
        if ($providerSlug) {
            $this->populateIntegrationFieldsFromSettings($providerSlug, []);
        } else {
            $this->integrationFields = [];
        }
    }

    public function resetProviderSettingsForm()
    {
        $this->providerSettingsText = '';
        $this->providerActive = true;
        $this->integrationFields = [];
        $this->isEditingIntegration = false;
        $this->selectedLocationId = null;
        $this->tenantLocations = [];
    }

    public function saveProviderSettings()
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can manage provider settings.');
        }

        $this->loadAvailableProviders();
        $validKeys = array_keys($this->availableProviders);
        $isLocationLevel = $this->isLocationLevelIntegration($this->selectedProviderName);
        
        $validationRules = [
            'selectedProviderName' => ['required','string', Rule::in($validKeys)],
            'providerActive' => 'boolean',
        ];
        
        // Require location selection for location-level integrations
        if ($isLocationLevel) {
            $validationRules['selectedLocationId'] = ['required', 'integer', 'exists:seven_locations,id'];
        }
        
        $this->validate($validationRules);
        
        // Validate all required fields for this provider
        $fields = $this->getIntegrationFieldSchema($this->selectedProviderName);
        foreach ($fields as $fieldDef) {
            $key = $fieldDef['key'] ?? null;
            if (!$key) {
                continue;
            }
            if (!isset($this->integrationFields[$key]) || trim((string)$this->integrationFields[$key]) === '') {
                $label = $fieldDef['label'] ?? $key;
                $this->addError("integrationFields.{$key}", "{$label} is required.");
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        $decoded = [];
        foreach ($fields as $fieldDef) {
            $key = $fieldDef['key'] ?? null;
            if ($key) {
                $decoded[$key] = $this->integrationFields[$key] ?? '';
            }
        }

        $status = $this->providerActive ? 'active' : 'disabled';
        
        if ($isLocationLevel) {
            // Save to location integrations
            $existing = DB::table('rai_location_integrations')
                ->where('location_id', $this->selectedLocationId)
                ->where('integration_slug', $this->selectedProviderName)
                ->first();

            if ($existing) {
                DB::table('rai_location_integrations')
                    ->where('id', $existing->id)
                    ->update([
                        'settings' => json_encode($decoded),
                        'status' => $status,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('rai_location_integrations')->insert([
                    'location_id' => $this->selectedLocationId,
                    'integration_slug' => $this->selectedProviderName,
                    'settings' => json_encode($decoded),
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Clear cache for this provider/location combination
            ProviderSettingsService::clearCacheForLocation($this->selectedProviderName, $this->selectedLocationId);
        } else {
            // Save to tenant integrations
            $existing = DB::table('rai_tenant_integrations')
                ->where('tenant_id', $this->selectedTenantId)
                ->where('integration_slug', $this->selectedProviderName)
                ->first();

            if ($existing) {
                DB::table('rai_tenant_integrations')
                    ->where('id', $existing->id)
                    ->update([
                        'settings' => json_encode($decoded),
                        'status' => $status,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('rai_tenant_integrations')->insert([
                    'tenant_id' => $this->selectedTenantId,
                    'integration_slug' => $this->selectedProviderName,
                    'settings' => json_encode($decoded),
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Clear cache for this provider/tenant combination
            ProviderSettingsService::clearCache($this->selectedProviderName, $this->selectedTenantId);
        }

        $locationText = $isLocationLevel && isset($this->tenantLocations[$this->selectedLocationId]) 
            ? " for location '{$this->tenantLocations[$this->selectedLocationId]}'"
            : '';
        
        $this->dispatch('notify', type: 'success', message: "Integration settings for '{$this->availableProviders[$this->selectedProviderName]}'{$locationText} saved successfully!");

        $this->closeProviderSettingsModal();
    }

    public function deleteProviderSettings($tenantId, $providerName, $locationId = null)
    {
        if (!auth()->user()?->isSuperAdmin()) {
            abort(403, 'Only super admins can delete provider settings.');
        }

        $isLocationLevel = $this->isLocationLevelIntegration($providerName);
        
        if ($isLocationLevel && $locationId) {
            // Delete from location integrations
            DB::table('rai_location_integrations')
                ->where('location_id', $locationId)
                ->where('integration_slug', $providerName)
                ->delete();
            ProviderSettingsService::clearCacheForLocation($providerName, $locationId);
        } else {
            // Delete from tenant integrations
            DB::table('rai_tenant_integrations')
                ->where('tenant_id', $tenantId)
                ->where('integration_slug', $providerName)
                ->delete();
            ProviderSettingsService::clearCache($providerName, $tenantId);
        }

        // Clear cache
        ProviderSettingsService::clearCache($providerName, $tenantId);

        $this->loadAvailableProviders();
        $label = $this->availableProviders[$providerName] ?? $providerName;
        $this->dispatch('notify', type: 'success', message: "Integration settings for '{$label}' deleted successfully!");
    }

    public function getProviderSettings($tenantId)
    {
        // Return keyed by integration_slug with status/created_at
        // For location-level integrations, include location info
        
        // Check if tables exist
        if (!Schema::hasTable('rai_tenant_integrations')) {
            return [];
        }
        
        try {
            $rows = DB::table('rai_tenant_integrations')
                ->where('tenant_id', $tenantId)
                ->orderBy('integration_slug')
                ->get();
        } catch (\Exception $e) {
            return [];
        }
        
        // Also get location-level integrations for this tenant
        // Get list of location-level integration slugs from database
        if (!Schema::hasTable('rai_integrations')) {
            // Return tenant-level integrations only
            return $rows->keyBy('integration_slug')->toArray();
        }
        
        try {
            $locationLevelSlugs = DB::table('rai_integrations')
                ->where('is_location_level', true)
                ->where('is_active', 1)
                ->pluck('slug')
                ->toArray();
        } catch (\Exception $e) {
            $locationLevelSlugs = [];
        }
        
        // Get location-level integrations if table exists
        $locationRows = collect();
        if (Schema::hasTable('rai_location_integrations') && !empty($locationLevelSlugs)) {
            try {
                $locationRows = DB::table('rai_location_integrations')
                    ->join('seven_locations', 'rai_location_integrations.location_id', '=', 'seven_locations.id')
                    ->where('seven_locations.tenant_id', $tenantId)
                    ->whereIn('rai_location_integrations.integration_slug', $locationLevelSlugs)
                    ->select('rai_location_integrations.*', 'seven_locations.id as location_id', 'seven_locations.name as location_name')
                    ->orderBy('rai_location_integrations.integration_slug')
                    ->orderBy('seven_locations.name')
                    ->get();
            } catch (\Exception $e) {
                // Table doesn't exist or error - continue with empty collection
            }
        }
        
        // Combine tenant and location integrations
        $result = $rows->keyBy('integration_slug');
        
        // Add location integrations with a composite key
        foreach ($locationRows as $row) {
            $key = $row->integration_slug . '_location_' . $row->location_id;
            $result[$key] = (object) [
                'integration_slug' => $row->integration_slug,
                'status' => $row->status,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'location_id' => $row->location_id,
                'location_name' => $row->location_name,
                'is_location_level' => true,
            ];
        }
        
        return $result;
    }

    private function loadAvailableProviders(): void
    {
        if (!empty($this->availableProviders)) {
            return;
        }
        
        // Check if table exists before querying
        if (!Schema::hasTable('rai_integrations')) {
            $this->availableProviders = [];
            return;
        }
        
        try {
            $catalog = DB::table('rai_integrations')
                ->where('is_active', 1)
                ->orderBy('display_name')
                ->get(['slug','display_name']);
            $this->availableProviders = $catalog->pluck('display_name', 'slug')->toArray();
        } catch (\Exception $e) {
            // Table doesn't exist or other error - return empty array
            $this->availableProviders = [];
        }
    }

    /**
     * Get field schema for an integration from the database
     */
    private function getIntegrationFieldSchema(string $providerSlug): array
    {
        if (!Schema::hasTable('rai_integrations')) {
            return [];
        }
        
        try {
            $integration = DB::table('rai_integrations')
                ->where('slug', $providerSlug)
                ->first(['field_schema']);

            if (!$integration || !$integration->field_schema) {
                return [];
            }

            $schema = is_string($integration->field_schema)
                ? json_decode($integration->field_schema, true)
                : $integration->field_schema;

            return is_array($schema) ? $schema : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function populateIntegrationFieldsFromSettings(string $providerSlug, array $settings): void
    {
        $defs = $this->getIntegrationFieldSchema($providerSlug);
        $this->integrationFields = [];
        foreach ($defs as $def) {
            $key = $def['key'] ?? null;
            if ($key) {
                $this->integrationFields[$key] = $settings[$key] ?? '';
            }
        }
    }
}

