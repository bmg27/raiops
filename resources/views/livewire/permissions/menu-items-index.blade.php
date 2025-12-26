<!-- Menu Items Tab -->
<div>
    <div class="tab-pane " id="menu-items" role="tabpanel">
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
                <input type="text" wire:model.live.debounce.500ms="search" class="form-control"
                       placeholder="Search Menu Items..."/>
            </div>
            <div class="col-sm-8 text-end">
                <div class="form-check form-switch d-inline-block me-3">
                    <input class="form-check-input" type="checkbox" id="showInactive" wire:model.live="showInactive">
                    <label class="form-check-label" for="showInactive">
                        Show Inactive
                    </label>
                </div>
                <div class="form-check form-switch d-inline-block me-3">
                    <input class="form-check-input" type="checkbox" id="showSuperAdminOnly" wire:model.live="showSuperAdminOnly">
                    <label class="form-check-label" for="showSuperAdminOnly">
                        Super Admin Only
                    </label>
                </div>
                <a href="#" class="btn btn-primary btn-sm" id="contextualAddBtn" wire:click="openModal">
                    <i class="bi bi-plus-lg me-1"></i> Menu Item
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
                            <th wire:click="sortBy('id')" style="cursor:pointer;">ID</th>
                            <th class="sticky-col" wire:click="sortBy('title')" style="cursor:pointer;">Menu Item</th>
                            <th wire:click="sortBy('url')" style="cursor:pointer;">Path</th>
                            <th wire:click="sortBy('route')" style="cursor:pointer;">Route</th>
                            <th wire:click="sortBy('active')" style="cursor:pointer;">Active</th>
                            <th wire:click="sortBy('parent')" style="cursor:pointer;">Parent</th>
                            <th wire:click="sortBy('permission')" style="cursor:pointer;">Permission</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($items as $item)
                            @php
                                $badge = $item->active ? 'bi-check-circle text-success' : 'bi-slash-circle text-danger';
                                $item->active ? $tLabel = "Yes" : $tLabel = "No";
                            @endphp
                            <tr>
                                <td>{{ $item->id }}</td>
                                <td class="sticky-col">
                                    {{ $item->title }}
                                    @if($item->super_admin_only)
                                        <span class="badge bg-danger ms-2">
                                            <i class="bi bi-shield-lock"></i>
                                        </span>
                                    @endif
                                    @if(auth()->user()?->isSuperAdmin() && !empty($item->super_admin_append))
                                        <span class="text-muted"> [{{ $item->super_admin_append }}]</span>
                                    @endif
                                </td>
                                <td>{{ $item->url }}</td>
                                <td>
                                    @if($item->route)
                                        <code class="small">{{ $item->route }}</code>
                                    @else
                                        <small class="text-muted">—</small>
                                    @endif
                                </td>
                                <td>
                                <span
                                    class="badge rounded-pill border border-secondary text-secondary bg-white fw-normal">
                                        <i class="bi {{ $badge }} me-1"></i>
                                        {{ $tLabel }}
                                    </span>
                                </td>
                                <td>
                                    @if($item->parent)
                                        {{ $item->parent->title }}
                                    @else
                                        <small class="text-muted">None</small>
                                    @endif
                                </td>
                                <td>
                                    @if($item->permission)
                                        <span class="badge bg-primary">{{ $item->permission->name }}</span>
                                    @else
                                        <small class="text-muted">None</small>
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
                                                       wire:click="openModal({{ $item->id }})">Edit</a></li>
                                                <li><a class="dropdown-item" href="#"
                                                       wire:click="confirmDelete({{ $item->id }})">Delete</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">No menu items found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        @if($items instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            {{ $items->links() }}
        @endif

        <!-- CREATE/EDIT MODAL -->
        <div class="modal fade @if($showModal) show d-block @endif"
             tabindex="-1"
             @if($showModal) style="background: rgba(0,0,0,0.5);" @endif>
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <form wire:submit.prevent="save">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                {{ $menuItemId ? 'Edit Menu Item' : 'Create Menu Item' }}
                            </h5>
                            <button type="button" class="btn-close"
                                    wire:click="$set('showModal', false)">
                            </button>
                        </div>
                        <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">

                            <!-- Row 1: Title (Full Width) -->
                            @error('title')
                            <div class="text-danger">{{ $message }}</div> @enderror
                            <div class="mb-3">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       wire:model="title"
                                       placeholder="Menu Item Title"/>
                            </div>

                            <!-- Row 2: Super Admin Append (Half Width, if super admin) -->
                            @if(auth()->user()?->isSuperAdmin() ?? false)
                            @error('super_admin_append')
                            <div class="text-danger">{{ $message }}</div> @enderror
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Super Admin Append</label>
                                    <input type="text"
                                           class="form-control @error('super_admin_append') is-invalid @enderror"
                                           wire:model="super_admin_append"
                                           maxlength="20"
                                           placeholder="Text to append (max 20 chars)"/>
                                    <small class="form-text text-muted">Optional text appended for Super Admins only</small>
                                </div>
                            </div>
                            @endif

                            <!-- Row 3: URL (2/3) + Route (1/3) -->
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    @error('url')
                                    <div class="text-danger">{{ $message }}</div> @enderror
                                    <label class="form-label">URL <span class="text-danger">*</span></label>
                                    <input type="text"
                                           class="form-control"
                                           wire:model="url"
                                           placeholder="/path/to/page"/>
                                    <small class="form-text text-muted">The URL path for this menu item</small>
                                </div>
                                <div class="col-md-4">
                                    @error('route')
                                    <div class="text-danger">{{ $message }}</div> @enderror
                                    <label class="form-label">Route (Optional)</label>
                                    <input type="text"
                                           class="form-control"
                                           wire:model="route"
                                           placeholder="route.name"/>
                                    <small class="form-text text-muted">Laravel route name</small>
                                </div>
                            </div>

                            <!-- Row 4: Parent Menu Item (Full Width) -->
                            <div class="mb-3">
                                <label class="form-label">Parent Menu Item</label>
                                <select class="form-select" wire:model="parent_id">
                                    <option value="">No Parent (Top Level)</option>
                                    @foreach(\App\Models\MenuItem::orderBy('title')->get() as $possibleParent)
                                        <option value="{{ $possibleParent->id }}"
                                                @if($possibleParent->id === $menuItemId) disabled @endif>
                                            {{ $possibleParent->title }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Select a parent menu item to create a submenu</small>
                            </div>

                            <!-- Row 5: Icon (1/3) + Container Type (1/3) + Order (1/6) + Active (1/6) -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Icon</label>
                                    <input type="text"
                                           class="form-control"
                                           wire:model="icon"
                                           placeholder="bi bi-house"/>
                                    <small class="form-text text-muted">Bootstrap icon class</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Container Type</label>
                                    <select class="form-select" wire:model="containerType">
                                        <option value="Standard">Standard</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Order</label>
                                    <input type="number"
                                           class="form-control"
                                           wire:model="order"
                                           placeholder="0"/>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Active</label>
                                    <select class="form-select" wire:model="active">
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Row 6: Permission (Full Width) -->
                            <div class="mb-3">
                                <label class="form-label">Permission</label>
                                <div class="input-group">
                                    <select class="form-select" wire:model.live="permission_id" wire:key="permission-select-{{ $permissionRefreshKey }}">
                                        <option value="">No Permission</option>
                                        @foreach($allPermissions as $perm)
                                            <option value="{{ $perm->id }}">
                                                {{ $perm->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" wire:click="openPermissionModal">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">Click the + button to create a new permission</small>
                            </div>

                            <!-- Super Admin Only -->
                            @if(auth()->user()?->isSuperAdmin() ?? false)
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="super_admin_only" wire:model="super_admin_only">
                                    <label class="form-check-label" for="super_admin_only">
                                        <strong>Super Admin Only</strong>
                                    </label>
                                </div>
                                <small class="form-text text-muted d-block mt-1">
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                    When checked, this menu item will only be visible to Super Admin users. Regular admins and tenant owners will never see this item, regardless of permissions.
                                </small>
                            </div>
                            @endif


                        </div> <!-- end .modal-body -->

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                    wire:click="$set('showModal', false)">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                {{ $menuItemId ? 'Update' : 'Create' }}
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        @if($showModal)
            <div class="modal-backdrop fade show"></div>
        @endif

        <!-- DELETE CONFIRMATION MODAL -->
        <div class="modal fade @if($confirmingDelete) show d-block @endif"
             tabindex="-1"
             @if($confirmingDelete) style="background: rgba(0,0,0,0.5);" @endif>
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
                            <p>Are you sure you want to delete this menu item?</p>
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

        <!-- CREATE PERMISSION MODAL -->
        <div class="modal fade @if($showPermissionModal) show d-block @endif"
             tabindex="-1"
             @if($showPermissionModal) style="background: rgba(0,0,0,0.5);" @endif>
            <div class="modal-dialog">
                <div class="modal-content">
                    <form wire:submit.prevent="createPermission">
                        <div class="modal-header">
                            <h5 class="modal-title">Create New Permission</h5>
                            <button type="button" class="btn-close"
                                    wire:click="closePermissionModal">
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Permission Name</label>
                                <input type="text"
                                       class="form-control @error('newPermissionName') is-invalid @enderror"
                                       wire:model="newPermissionName"
                                       placeholder="Enter permission name (e.g., 'Manage Users')"/>
                                @error('newPermissionName')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary"
                                    wire:click="closePermissionModal">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                Create Permission
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @if($showPermissionModal)
            <div class="modal-backdrop fade show"></div>
        @endif

    </div>
</div>
