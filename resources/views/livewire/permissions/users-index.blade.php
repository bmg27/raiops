<!-- Users Tab -->
<div>
    <div class="tab-pane fade show active" id="users" role="tabpanel">
        <!-- Filter Row -->
        <div class="row g-2 align-items-center mb-3">
            <div class="col-sm-6">
                <label class="form-label small text-muted">Search</label>
                <input type="text"
                       wire:model.live.debounce.500ms="search"
                       class="form-control"
                       placeholder="Search by name, email, or role…">
            </div>

            <!-- STATUS -->
            <div class="col-sm-3">
                <label class="form-label small text-muted">Status</label>
                <select wire:model.live="userStatus" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Active">Active</option>
                    <option value="Disabled">Disabled</option>
                </select>
            </div>

            <!-- ADD BUTTON -->
            <div class="col-sm-3 text-end">
                <label class="form-label small text-muted d-block">&nbsp;</label>
                <a href="#" class="btn btn-outline-primary btn-sm" wire:click="openModal">
                    <i class="bi bi-plus-lg me-1"></i> Add User
                </a>
            </div>
        </div>

        <livewire:admin.flash-message fade="no" modal="true"/>

        @php
            $statusMap = [
                'Active'   => ['icon' => 'bi-check-circle text-success', 'label' => 'Active'],
                'Disabled' => ['icon' => 'bi-slash-circle text-danger', 'label' => 'Disabled'],
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
                            <th wire:click="sortBy('email')" style="cursor:pointer;">Email</th>
                            <th wire:click="sortBy('email_verified_at')" style="cursor:pointer;">Email Verified</th>
                            <th>Status</th>
                            <th>Roles</th>
                            <th class="text-center">&nbsp;</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($users as $u)
                            @php
                                $status = $u->is_active ? 'Active' : 'Disabled';
                                $badge = $statusMap[$status] ?? ['icon' => 'bi-question-circle text-secondary', 'label' => $status];
                            @endphp

                            <tr>
                                <td>{{ $u->id }}</td>
                                <td class="sticky-col">{{ $u->name }}</td>
                                <td>{{ $u->email }}</td>
                                <td>
                                    @if($u->email_verified_at)
                                        <span class="badge bg-success">Verified</span>
                                    @else
                                        <span class="badge bg-secondary">Unverified</span>
                                    @endif
                                </td>
                                <td>
                                    <livewire:common.badge :key="uniqid()" :text="$badge['label']"
                                                           :icon="$badge['icon']">
                                </td>
                                <td class="text-wrap">{{ $u->roles->pluck('name')->join(', ') ?: '—' }}</td>
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
                                                @can('user.delete')
                                                    <li>
                                                        <a wire:click="confirmDelete({{ $u->id }})" class="dropdown-item text-danger"
                                                           href="#">Delete</a>
                                                    </li>
                                                @endcan
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">No users found.</td>
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

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" wire:model.live="status">
                                <option value="Active">Active</option>
                                <option value="Disabled">Disabled</option>
                            </select>
                            @error('status') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        @if($showEmailButton && !$userNotified && $userId)
                            <div class="mb-3">
                                <a class="btn btn-small btn-success" wire:click="notifyUser"
                                   wire:loading.class="opacity-50 pointer-events-none" wire:confirm="Send email?">Send
                                    User Activated Email</a>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label>Roles</label>
                            <div class="form-check" style="max-height:200px; overflow:auto;">
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
                                        No roles available.
                                    </div>
                                @endif
                            </div>
                        </div>
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
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" wire:click="$set('confirmingDelete', false)"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to disable this user?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            wire:click="$set('confirmingDelete', false)">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger" wire:click="deleteUser({{ $deleteId }})"
                            wire:click="$set('confirmingDelete', false)">
                        Yes, Disable
                    </button>
                </div>
            </div>
        </div>
    </div>
    @if($confirmingDelete)
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
