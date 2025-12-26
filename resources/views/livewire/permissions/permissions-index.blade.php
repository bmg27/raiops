<!-- Permissions Tab -->
<div>
    <div class="tab-pane " id="permissions" role="tabpanel">
        {{-- SESSION MESSAGES --}}
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('message') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Filter Row -->
        <div class="row g-2 align-items-center mb-3">
            <div class="col-sm-4">
                <input type="text" class="form-control" wire:model.live.debounce.500ms="search"
                       placeholder="Search Permissions..."/>
            </div>
            <div class="col-sm-8 text-end">
                <div class="form-check form-switch d-inline-block me-3">
                    <input class="form-check-input" type="checkbox" id="showSuperAdminOnly" wire:model.live="showSuperAdminOnly">
                    <label class="form-check-label" for="showSuperAdminOnly">
                        Super Admin Only
                    </label>
                </div>
                <a href="#" class="btn btn-primary btn-sm" id="contextualAddBtn"
                   wire:click="openPermissionModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Permission
                </a>
            </div>
        </div>

        <x-per-page />

        <!-- TABLE -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-nowrap table-hover">
                        <thead>
                        <tr>
                            <th wire:click="sortBy('id')" style="cursor:pointer;"> ID</th>
                            <th wire:click="sortBy('name')" style="cursor:pointer;"> Name</th>
                            <th class='sticky-col' wire:click="sortBy('name')" style="cursor:pointer;"> Permission</th>
                            <th>Assigned Roles</th>
                            <th class="text-center">&nbsp;</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($permissions as $p)
                            <tr>
                                <td>{{ $p->id }}</td>
                                <td>
                                    {{ $p->name }}
                                    @if($p->super_admin_only ?? false)
                                        <span class="badge bg-danger ms-2">
                                            <i class="bi bi-shield-lock"></i>
                                        </span>
                                    @endif
                                </td>
                                <td class='sticky-col' title="{{ $p->name }}">
                                    {{ $p->description ?? $p->name }}
                                </td>
                                <td style="white-space: normal;">
                                    {{ $p->roles->pluck('name')->join(', ') ?: '-' }}
                                </td>
                                <td class="text-center position-static">
                                    <div class="text-end">
                                        <div class="dropdown position-static">
                                            <button class="btn btn-sm p-0 bg-transparent border-0 text-secondary"
                                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end position-absolute">
                                                <li><a class="dropdown-item" href="#"
                                                       wire:click="openPermissionModal({{ $p->id }})">Edit</a></li>
                                                <li><a class="dropdown-item" href="#"
                                                       wire:click="confirmDelete({{ $p->id }})">Delete</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No permissions found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PAGINATION -->
        @if($permissions instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            {{ $permissions->links() }}
        @endif

        <!-- CREATE/EDIT MODAL -->
        <div class="modal fade @if($showPermissionModal) show d-block @endif"
             style="@if($showPermissionModal) background: rgba(0,0,0,0.5); @endif"
             tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form wire:submit.prevent="savePermission">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                {{ $permissionId ? 'Edit Permission' : 'Create Permission' }}
                            </h5>
                            <button type="button" class="btn-close"
                                    wire:click="$set('showPermissionModal', false)">
                            </button>
                        </div>
                        <div class="modal-body">

                            @error('permissionName')
                            <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="mb-3">
                                <label class="form-label">Permission Name</label>
                                <input type="text"
                                       class="form-control"
                                       wire:model="permissionName"/>
                            </div>

                            @error('permissionDescription')
                            <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control"
                                          rows="3"
                                          wire:model="permissionDescription"
                                          placeholder="Optional description for this permission"></textarea>
                            </div>

                            @error('permissionGuard')
                            <div class="text-danger">{{ $message }}</div>
                            @enderror
                            <div class="mb-3">
                                <label class="form-label">Guard Name</label>
                                <input type="text"
                                       class="form-control"
                                       wire:model="permissionGuard"/>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="superAdminOnly"
                                           wire:model="superAdminOnly">
                                    <label class="form-check-label" for="superAdminOnly">
                                        Super Admin Only
                                    </label>
                                </div>
                                <small class="form-text text-muted">
                                    If checked, this permission will only be available to Super Admin roles and will not be included in tenant Admin roles.
                                </small>
                            </div>


                        </div>
                        <div class="modal-footer">
                            <button type="button"
                                    class="btn btn-secondary"
                                    wire:click="$set('showPermissionModal', false)">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="btn btn-primary">
                                {{ $permissionId ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @if($showPermissionModal)
            <div class="modal-backdrop fade show"></div>
        @endif

        <!-- DELETE CONFIRMATION MODAL -->
        <div class="modal fade @if($confirmingDelete) show d-block @endif"
             style="@if($confirmingDelete) background: rgba(0,0,0,0.5); @endif"
             tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form wire:submit.prevent="delete">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirm Delete</h5>
                            <button type="button" class="btn-close"
                                    wire:click="$set('confirmingDelete', false)">
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this permission?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button"
                                    class="btn btn-secondary"
                                    wire:click="$set('confirmingDelete', false)">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="btn btn-danger">
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
</div>
