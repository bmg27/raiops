<div>
    {{-- SESSION MESSAGES --}}
    {{-- @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif --}}

    <!-- Header -->
    <div class="mb-3">
        <h5>
            <i class="bi bi-columns-gap me-2"></i>
            Menu Organization
        </h5>
    </div>

    <!-- 3 Column Layout -->
    <div class="row g-3">

        {{-- COLUMN 1: PARENTS (Top-Level) --}}
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        Top-Level Items
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="column-list">
                        @forelse($parents as $index => $parent)
                            <div class="list-item {{ $selectedParentId == $parent->id ? 'active' : '' }} cursor-pointer"
                                 wire:click="selectParent({{ $parent->id }})">
                                <div class="d-flex flex-row flex-nowrap align-items-center px-3 py-1 gap-2">
                                    <i class="bi bi-grip-vertical drag-handle flex-shrink-0 text-muted"></i>
                                    @if($parent->icon)
                                        <i class="bi {{ $parent->icon }} flex-shrink-0"></i>
                                    @endif
                                    <div class="flex-grow-1 text-truncate">
                                        <strong>{{ $parent->title }}</strong>
                                        @php
                                            $childCount = \App\Models\MenuItem::where('parent_id', $parent->id)->where('active', 1)->count();
                                        @endphp
                                        @if($childCount > 0)
                                            <span class="badge bg-secondary ms-2">{{ $childCount }}</span>
                                        @endif
                                        <small class="text-muted ms-2">{{ $parent->url }}</small>
                                    </div>
                                    <div class="btn-group btn-group-sm flex-shrink-0 ms-auto">
                                        @if($index > 0)
                                            <button wire:click.stop="moveUp({{ $parent->id }})"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="Move Up">
                                                <i class="bi bi-arrow-up"></i>
                                            </button>
                                        @else
                                            <button class="btn btn-sm btn-outline-secondary invisible"
                                                    disabled>
                                                <i class="bi bi-arrow-up"></i>
                                            </button>
                                        @endif
                                        @if($index < $parents->count() - 1)
                                            <button wire:click.stop="moveDown({{ $parent->id }})"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="Move Down">
                                                <i class="bi bi-arrow-down"></i>
                                            </button>
                                        @else
                                            <button class="btn btn-sm btn-outline-secondary invisible"
                                                    disabled>
                                                <i class="bi bi-arrow-down"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-3"></i>
                                <p class="mt-2">No top-level items</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- COLUMN 2: CHILDREN --}}
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-arrow-return-right me-2"></i>
                        Children
                        @if($selectedParentId)
                            <small>({{ $parents->firstWhere('id', $selectedParentId)->title ?? '' }})</small>
                        @endif
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="column-list">
                        @if($selectedParentId)
                            @forelse($children as $index => $child)
                                <div class="list-item {{ $selectedChildId == $child->id ? 'active' : '' }} cursor-pointer"
                                     wire:click="selectChild({{ $child->id }})">
                                    <div class="d-flex flex-row flex-nowrap align-items-center px-3 py-1 gap-2">
                                        <i class="bi bi-grip-vertical drag-handle flex-shrink-0 text-muted"></i>
                                        @if($child->icon)
                                            <i class="bi {{ $child->icon }} flex-shrink-0"></i>
                                        @endif
                                        <div class="flex-grow-1 text-truncate">
                                            <strong>{{ $child->title }}</strong>
                                            @php
                                                $grandchildCount = \App\Models\MenuItem::where('parent_id', $child->id)->where('active', 1)->count();
                                            @endphp
                                            @if($grandchildCount > 0)
                                                <span class="badge bg-secondary ms-2">{{ $grandchildCount }}</span>
                                            @endif
                                            <small class="text-muted ms-2">{{ $child->url }}</small>
                                        </div>
                                        <div class="btn-group btn-group-sm flex-shrink-0 ms-auto">
                                            @if($index > 0)
                                                <button wire:click.stop="moveUp({{ $child->id }})"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Move Up">
                                                    <i class="bi bi-arrow-up"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary invisible"
                                                        disabled>
                                                    <i class="bi bi-arrow-up"></i>
                                                </button>
                                            @endif
                                            @if($index < $children->count() - 1)
                                                <button wire:click.stop="moveDown({{ $child->id }})"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Move Down">
                                                    <i class="bi bi-arrow-down"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary invisible"
                                                        disabled>
                                                    <i class="bi bi-arrow-down"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3"></i>
                                    <p class="mt-2">No children</p>
                                </div>
                            @endforelse
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-arrow-left fs-3"></i>
                                <p class="mt-2">Select a parent item</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- COLUMN 3: GRANDCHILDREN --}}
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-arrow-return-right me-2"></i>
                        <i class="bi bi-arrow-return-right me-2"></i>
                        Grandchildren
                        @if($selectedChildId)
                            <small>({{ $children->firstWhere('id', $selectedChildId)->title ?? '' }})</small>
                        @endif
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="column-list">
                        @if($selectedChildId)
                            @forelse($grandchildren as $index => $grandchild)
                                <div class="list-item cursor-pointer">
                                    <div class="d-flex flex-row flex-nowrap align-items-center px-3 py-1 gap-2">
                                        <i class="bi bi-grip-vertical drag-handle flex-shrink-0 text-muted"></i>
                                        @if($grandchild->icon)
                                            <i class="bi {{ $grandchild->icon }} flex-shrink-0"></i>
                                        @endif
                                        <div class="flex-grow-1 text-truncate">
                                            <strong>{{ $grandchild->title }}</strong>
                                            <small class="text-muted ms-2">{{ $grandchild->url }}</small>
                                        </div>
                                        <div class="btn-group btn-group-sm flex-shrink-0 ms-auto">
                                            @if($index > 0)
                                                <button wire:click.stop="moveUp({{ $grandchild->id }})"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Move Up">
                                                    <i class="bi bi-arrow-up"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary invisible"
                                                        disabled>
                                                    <i class="bi bi-arrow-up"></i>
                                                </button>
                                            @endif
                                            @if($index < $grandchildren->count() - 1)
                                                <button wire:click.stop="moveDown({{ $grandchild->id }})"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Move Down">
                                                    <i class="bi bi-arrow-down"></i>
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary invisible"
                                                        disabled>
                                                    <i class="bi bi-arrow-down"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3"></i>
                                    <p class="mt-2">No grandchildren</p>
                                </div>
                            @endforelse
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-arrow-left fs-3"></i>
                                <p class="mt-2">Select a child item</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
    
    {{-- TODO: Add SortableJS initialization here when ready --}}
    
</div>

@push('styles')
<style>
    .column-list {
        max-height: 600px;
        overflow-y: auto;
        min-height: 200px;
    }

    .cursor-pointer {
        cursor: pointer !important;
    }

    .list-item {
        border-bottom: 1px solid #e9ecef;
        transition: all 0.2s ease;
    }

    .list-item:hover {
        background-color: #f8f9fa;
    }

    .list-item.active {
        background-color: #e7f1ff;
        border-left: 4px solid #0d6efd;
    }

    .btn-group {
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }

    .list-item:hover .btn-group {
        opacity: 1;
    }

    /* Drag & Drop Styles */
    .list-item.sortable-ghost {
        opacity: 0.4;
        background-color: #dee2e6;
    }

    .list-item.sortable-drag {
        opacity: 0.8;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        transform: rotate(2deg);
        cursor: grabbing !important;
    }

    .column-list.sortable-drag-over {
        background-color: #f0f8ff;
        border: 2px dashed #0d6efd;
    }
    
    /* Drag Handle - visual only for now */
    .drag-handle {
        cursor: grab;
        opacity: 0.3;
        transition: opacity 0.2s ease;
    }
    
    .list-item:hover .drag-handle {
        opacity: 0.6;
    }
</style>
@endpush

