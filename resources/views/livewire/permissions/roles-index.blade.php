<!-- Roles Tab -->
<div>
    <!-- SEARCH & CREATE -->
    <div class="tab-pane" id="roles" role="tabpanel">
        <!-- Filter Row -->
        <div class="row g-2 align-items-end mb-3">
            <!-- TENANT DROPDOWN (Super Admin Only) -->
            @if($isSuperAdmin)
            <div class="col-sm-2">
                <label class="form-label small text-muted mb-1">Tenant</label>
                <select wire:model.live="selectedTenant"
                        class="form-select">
                    <option value="">All Tenants</option>
                    @foreach($allTenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            
            <div class="col-sm-{{ $isSuperAdmin ? '4' : '6' }}">
                <label class="form-label small text-muted mb-1">Search</label>
                <input type="text" class="form-control" placeholder="Search Roles..."
                       wire:model.live.debounce.500ms="search"/>
            </div>
            <div class="col-sm-{{ $isSuperAdmin ? '6' : '6' }} text-end">
                <a href="#" class="btn btn-primary btn-sm" id="contextualAddBtn" wire:click="openRoleModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Role
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
                            <th class="sticky-col" wire:click="sortBy('name')" style="cursor:pointer;"> Role Name</th>
                            <th wire:click="sortBy('tenant')" style="cursor:pointer;"> Tenant</th>
                            <th>Permissions</th>
                            <th class="text-center">&nbsp;</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php
                            // Eager-load permissions if you want to see them.
                            // If you're not eager-loading in the query, each role->permissions can cause N+1 queries.
                            // But for small usage, it's fine. For bigger usage, do ->with('permissions') in query.
                        @endphp

                        @foreach($roles as $r)
                            <tr>
                                <td>{{ $r->id }}</td>
                                <td class="sticky-col">{{ $r->name }}</td>
                                <td>{{ $r->tenant ? $r->tenant->name : 'Global' }}</td>
                                <td>
                                    @php
                                        $firstFew = $r->permissions->pluck('name')->take(3)->join(', ');
                                        $countMore = $r->permissions->count() - 3;
                                    @endphp
                                    {{ $firstFew }}
                                    @if($countMore > 0)
                                        &amp; {{ $countMore }} more...
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
                                                <li><a class="dropdown-item" href="#"
                                                       wire:click="openRoleModal({{ $r->id }})">Edit</a></li>
                                                <li><a class="dropdown-item" href="#"
                                                       wire:click="confirmDelete({{ $r->id }})">Delete</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PAGINATION -->
        @if($roles instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            {{ $roles->links() }}
        @endif

        <!-- CREATE/EDIT MODAL -->
        <div class="modal fade @if($showRoleModal) show d-block @endif"
             style="@if($showRoleModal) background: rgba(0,0,0,0.5); @endif" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form wire:submit.prevent="saveRole">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                {{ $roleId ? 'Edit Role' : 'Create Role' }}
                            </h5>
                            <button type="button" class="btn-close"
                                    wire:click="$set('showRoleModal', false)">
                            </button>
                        </div>
                        <div class="modal-body">

                            @error('roleName')
                            <div class="text-danger">{{ $message }}</div>
                            @enderror
                            
                            @if($isSuperAdmin && !$roleId)
                            <div class="mb-3">
                                <label class="form-label">Tenant <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.live="modalTenant" required>
                                    <option value="">-- Select Tenant --</option>
                                    @foreach($allTenants as $tenant)
                                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                    @endforeach
                                </select>
                                @error('modalTenant')
                                <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </div>
                            @endif
                            
                            <div class="mb-3">
                                <label class="form-label">Role Name</label>
                                <input type="text"
                                       class="form-control"
                                       wire:model="roleName"/>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Permissions</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="showUncheckedPermissions" 
                                               wire:model.live="showUncheckedPermissions">
                                        <label class="form-check-label" for="showUncheckedPermissions">
                                            <small>Show unchecked</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           wire:model.live.debounce.300ms="permissionSearch"
                                           placeholder="Search permissions by name or description..."/>
                                </div>
                                <div class="form-check" style="max-height:500px; overflow:auto;">
                                    @forelse($modalPermissions as $perm)
                                        @php
                                            $isChecked = in_array((string)$perm->id, $selectedPermissions);
                                            $shouldShow = $showUncheckedPermissions || $isChecked;
                                        @endphp
                                        @if($shouldShow)
                                            <div class="d-flex align-items-start mb-2">
                                                <input type="checkbox"
                                                       wire:model="selectedPermissions"
                                                       value="{{ $perm->id }}"
                                                       class="form-check-input mt-1"
                                                       id="checkRolePerm-{{ $perm->id }}">
                                                <label for="checkRolePerm-{{ $perm->id }}"
                                                       class="form-check-label flex-grow-1 ms-2">
                                                    @if($perm->description)
                                                        <strong>{!! $this->highlightSearch($perm->description, $permissionSearch) !!}</strong>
                                                        <br>
                                                    @endif
                                                    <small class="text-muted">{!! $this->highlightSearch($perm->name, $permissionSearch) !!}</small>
                                                </label>
                                                @if($perm->super_admin_only ?? false)
                                                    <span class="badge bg-danger ms-2" title="Super Admin Only">
                                                        <i class="bi bi-shield-lock"></i> SA
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    @empty
                                        <div class="text-muted text-center py-3">
                                            @if(!empty($permissionSearch))
                                                No permissions found matching "{{ $permissionSearch }}"
                                            @else
                                                No permissions available
                                            @endif
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button"
                                    class="btn btn-secondary"
                                    wire:click="$set('showRoleModal', false)">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="btn btn-primary">
                                {{ $roleId ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @if($showRoleModal)
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
                            <p>Are you sure you want to delete this role?</p>
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
