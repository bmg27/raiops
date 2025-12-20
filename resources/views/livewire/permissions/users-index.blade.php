<!-- Users Tab -->
<div>
    <div class="tab-pane fade show active" id="users" role="tabpanel">
        <!-- Filter Row -->
        <div class="row g-2 align-items-center mb-3">
            <!-- SEARCH -->
            <div class="row g-2 align-items-start mb-3">
                <!-- TENANT DROPDOWN (Super Admin Only) -->
                @if($isSuperAdmin)
                    <div class="col-sm-2">
                        <label class="form-label small text-muted">Tenant</label>
                        <div class="d-flex gap-2">
                            <select wire:model.live="selectedTenant"
                                    class="form-select">
                                <option value="">All Tenants</option>
                                @foreach($allTenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                            {{--@if($selectedTenant)
                            <button wire:click="clearTenantFilter"
                                    class="btn btn-sm btn-outline-secondary"
                                    style="height: fit-content;"
                                    title="Clear tenant filter">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            @endif--}}
                        </div>
                    </div>
                @endif

                <!-- LOCATIONS MULTI-SELECT -->
                <div class="col-sm-{{ $isSuperAdmin ? '2' : '3' }}">
                    <label class="form-label small text-muted">Locations</label>
                    <div class="d-flex gap-2">
                        <select wire:model.live="selectedLocation"
                                class="form-select"
                                size="4"
                                multiple>
                            <option value="Any">Any</option>
                            <option value="All">All</option>
                            @foreach($allLocations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        {{--<button wire:click="clearLocationFilter"
                                class="btn btn-sm btn-outline-secondary"
                                style="height: fit-content;"
                                title="Clear location filter"
                                @if(empty($selectedLocation)) disabled @endif>
                            <i class="bi bi-x-lg"></i>
                        </button>--}}
                    </div>
                    @if(!empty($selectedLocation))
                        <small class="text-muted d-block mt-1">
                            {{ count($selectedLocation) }} selected
                        </small>
                    @endif
                </div>
                <div class="col-sm-{{ $isSuperAdmin ? '3' : '4' }}">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text"
                           wire:model.live.debounce.500ms="search"
                           class="form-control"
                           placeholder="Search by name, email, or role…">
                </div>

                <!-- STATUS -->
                <div class="col-sm-2">
                    <label class="form-label small text-muted">Status</label>
                    <div class="d-flex flex-column">
                        <select wire:model.live="userStatus" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Disabled">Disabled</option>
                            <option value="Archived">Archived</option>
                        </select>
                    </div>
                </div>

                <!-- ADD BUTTON -->
                <div class="col-sm-{{ $isSuperAdmin ? '3' : '3' }} text-end">
                    <label class="form-label small text-muted d-block">&nbsp;</label>
                    <a href="#" class="btn btn-outline-primary btn-sm" wire:click="openModal">
                        <i class="bi bi-plus-lg me-1"></i> Add User
                    </a>
                </div>
            </div>
        </div>


        <livewire:admin.flash-message fade="no" modal="true"/>


        @php
            $statusMap = [
                'Pending'  => ['icon' => 'bi-clock text-warning',      'label' => 'Pending'],
                'Active'   => ['icon' => 'bi-check-circle text-success','label' => 'Active'],
                'Disabled' => ['icon' => 'bi-slash-circle text-danger', 'label' => 'Disabled'],
                'Archived' => ['icon' => 'bi-archive text-info',       'label' => 'Archived'],
            ];
        @endphp

        <x-per-page/>

        <!-- Table -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-nowrap table-hover">
                        <thead>
                        <tr>
                            <th wire:click="sortBy('id')" style="cursor:pointer;">ID</th>
                            <th class="sticky-col" wire:click="sortBy('name')" style="cursor:pointer;">Name</th>
                            <th wire:click="sortBy('status')" style="cursor:pointer;">Status</th>
                            <th wire:click="sortBy('email')" style="cursor:pointer;">Email</th>
                            <th wire:click="sortBy('email_verified_at')" style="cursor:pointer;">Email Verified</th>
                            @if($isSuperAdmin)
                                <th wire:click="sortBy('tenant_id')" style="cursor:pointer;">Tenant</th>
                            @endif
                            <th>Roles</th>
                            <th>Location</th>
                            <th class="text-center">&nbsp;</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($users as $u)
                            @php
                                // Grab the right icon/label, or fall back to a “?” for anything unexpected
                                $badge = $statusMap[$u->status]
                                    ?? ['icon' => 'bi-question-circle text-secondary', 'label' => $u->status];
                            @endphp

                            <tr>
                                <td>{{ $u->id }}</td>
                                <td class="sticky-col">{{ $u->name }}</td>
                                <td>
                                    <livewire:common.badge :key="uniqid()" :text="$badge['label']"
                                                           :icon="$badge['icon']">
                                </td>


                                <td>{{ $u->email }}</td>
                                <td>{{ $u->email_verified_at }}</td>
                                @if($isSuperAdmin)
                                    <td>
                                        @if($u->tenant)
                                            <span class="badge badge-theme">{{ $u->tenant->name }}</span>
                                        @elseif($u->is_super_admin)
                                            <span class="badge bg-warning text-dark">Super Admin</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-wrap">{{ $u->roles->pluck('name')->join(', ') }}</td>
                                <td>
                                    @if($u->location_access === 'None' || $u->location_access === 'All')
                                        {{ $u->location_access }}
                                    @elseif($u->location_access === 'Some')
                                        @php
                                            $locationNames = $u->locations->pluck('name')->toArray();
                                            $fullText = implode(', ', $locationNames);
                                            $truncatedText = strlen($fullText) > 30 ? substr($fullText, 0, 30) . '...' : $fullText;
                                        @endphp
                                        <span title="{{ $fullText }}">{{ $truncatedText }}</span>
                                    @else
                                        {{ $u->location_access }}
                                    @endif
                                </td>
                                <td class="text-center position-static">
                                    <div class="text-end">
                                        <div class="dropdown position-static">
                                            <button class="btn btn-sm p-0 bg-transparent border-0 text-secondary"
                                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end position-absolute">
                                                <li><a wire:click="openModal({{ $u->id }})" class="dropdown-item"
                                                       href="#">Edit</a></li>
                                                <li>
                                                    @if( $u->status === 'Active' && $u->email_verified_at && $u->id !== auth()->id() && ! $u->hasRole('Super Admin') )
                                                        <form method="POST"
                                                              action="{{ route('admin.users.impersonate', $u ) }}"
                                                              class="d-inlink">
                                                            @csrf
                                                            <a href="#" class="dropdown-item"
                                                               onclick="event.preventDefault(); this.closest('form').submit();">
                                                                Impersonate
                                                            </a>
                                                        </form>
                                                    @endif
                                                </li>
                                                @can('user.delete')
                                                    <li>
                                                        <a wire:click="deleteUser({{ $u->id }})" class="dropdown-item"
                                                           wire:confirm='Delete User' href="#">Delete</a></li>
                                                    <li>
                                                @endcan
                                            </ul>
                                        </div>
                                    </div>
                                </td>


                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isSuperAdmin ? '9' : '8' }}">No users found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Pagination -->
        {{ $users->links() }}
    </div>

    <!-- User Modal -->
    <div class="modal fade @if($showUserModal) show d-block @endif"
         style="@if($showUserModal) background: rgba(0,0,0,0.5); @endif" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $userId ? 'Edit User' : 'Create User' }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('showUserModal', false)"></button>
                    </div>
                    <div class="modal-body">
                        @if(session()->has('message'))
                            <div class="mb-3 alert alert-success">
                                {{ session('message') }}
                            </div>
                        @endif

                        @error('name')
                        <div class="text-danger">{{ $message }}</div> @enderror
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" wire:model="name" class="form-control">
                        </div>

                        @error('email')
                        <div class="text-danger">{{ $message }}</div> @enderror
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" wire:model="email" class="form-control">
                        </div>

                        @if($isSuperAdmin)
                            <div class="mb-3">
                                <label class="form-label">Tenant
                                    @if(!$userId)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <select class="form-select"
                                        wire:model.live="modalTenant"
                                        @if($userId) disabled @endif
                                        @if(!$userId) required @endif>
                                    <option value="">-- Select Tenant --</option>
                                    @foreach($allTenants as $tenant)
                                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                    @endforeach
                                </select>
                                @error('modalTenant')
                                <div class="text-danger small">{{ $message }}</div>
                                @enderror
                                @if($userId)
                                    <small class="text-muted">Tenant cannot be changed when editing a user.</small>
                                @endif
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" wire:model.live="status">
                                @foreach($statuses as $statusOption)
                                    <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                                @endforeach
                            </select>
                            @error('status') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        @if($showEmailButton && !$userNotified)
                            <div class="mb-3">
                                <a class="btn btn-small btn-success" wire:click="notifyUser"
                                   wire:loading.class="opacity-50 pointer-events-none" wire:confirm="Send email?">Send
                                    User Activated Email</a>
                            </div>
                        @endif

                        @if($userId || ($isSuperAdmin && $modalTenant) || (!$isSuperAdmin))
                            <div class="mb-3">
                                <label>Roles</label>
                                <div class="form-check" style="max-height:150px; overflow:auto;">
                                    @if(count($modalRoles) > 0)
                                        @foreach($modalRoles as $r)
                                            <div>
                                                <input type="checkbox" class="form-check-input"
                                                       wire:model="selectedRoles"
                                                       value="{{ $r->id }}"
                                                       id="role-{{ $r->id }}">
                                                <label for="role-{{ $r->id }}" class="form-check-label">
                                                    {{ $r->name }}
                                                </label>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="text-muted small">
                                            @if($isSuperAdmin && !$modalTenant)
                                                Please select a tenant first to see available roles.
                                            @else
                                                No roles available.
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                        <div class="mb-3">
                            <label for="location_access" class="form-label">Location Access</label>
                            <select id="location_access" wire:model.live="locationAccess" class="form-control">
                                <option value="None">None</option>
                                <option value="All">All</option>
                                <option value="Some">Some</option>
                            </select>
                            @error('locationAccess') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <!-- Multi-select dropdown for specific locations (only shown if 'Some' is selected) -->
                        @if($locationAccess === 'Some')
                            <div class="mb-3">
                                <label for="locations" class="form-label">Select Locations</label>
                                <select id="locations" multiple class="form-control" wire:model="selectedLocations">
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                                @error('selectedLocations') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        @endif


                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                                wire:click="$set('showUserModal', false)">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            {{ $userId ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @if($showUserModal)
        <div class="modal-backdrop fade show"></div>
    @endif

    <!-- Delete Confirmation Modal -->
    <div class="modal fade @if($confirmingDelete) show d-block @endif"
         style="@if($confirmingDelete) background: rgba(0,0,0,0.5); @endif" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="delete">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" wire:click="$set('confirmingDelete', false)"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this user?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                                wire:click="$set('confirmingDelete', false)">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            Yes, Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @if($confirmingDelete)
        <div class="modal-backdrop fade show"></div>
    @endif

</div>

