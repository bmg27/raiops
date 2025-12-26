<div>
    <x-page-header title="Schedule Management" />

    <livewire:admin.flash-message fade="true" />

    {{-- RDS SELECTION & FILTERS --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted">RDS Instance</label>
                    <select class="form-select form-select-sm" wire:model.live="selectedRdsId">
                        @foreach($rdsInstances as $rds)
                            <option value="{{ $rds->id }}">{{ $rds->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Search Commands</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" placeholder="Search by name or command..." wire:model.live.debounce.300ms="searchQuery">
                        @if($searchQuery)
                            <button class="btn btn-outline-secondary" type="button" wire:click="$set('searchQuery', '')">
                                <i class="bi bi-x"></i>
                            </button>
                        @endif
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Category</label>
                    <select class="form-select form-select-sm" wire:model.live="filterCategory">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Frequency</label>
                    <select class="form-select form-select-sm" wire:model.live="filterFrequency">
                        <option value="">All Frequencies</option>
                        @foreach($frequencies as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <div class="btn-group btn-group-sm me-2">
                        <button class="btn btn-outline-secondary" wire:click="expandAll" title="Expand All">
                            <i class="bi bi-arrows-expand"></i>
                        </button>
                        <button class="btn btn-outline-secondary" wire:click="collapseAll" title="Collapse All">
                            <i class="bi bi-arrows-collapse"></i>
                        </button>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input" id="showDisabled" wire:model.live="showDisabled">
                        <label class="form-check-label small" for="showDisabled">Disabled</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- FREQUENCY LEGEND & SEARCH INFO --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <span class="text-muted small me-2">Frequencies:</span>
                    <span class="badge bg-danger">Hourly</span>
                    <span class="badge bg-warning text-dark">2 Hours</span>
                    <span class="badge bg-info text-dark">4 Hours</span>
                    <span class="badge bg-primary">6 Hours</span>
                    <span class="badge bg-secondary">12 Hours</span>
                    <span class="badge bg-success">Daily</span>
                    <span class="badge bg-dark">Weekly</span>
                </div>
                @if($searchQuery)
                    <div class="text-muted small">
                        <i class="bi bi-filter me-1"></i>
                        Showing {{ $this->getFilteredCommands()->count() }} of {{ count($commands) }} commands
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- COMMANDS TABLE --}}
    <div class="card">
        <div class="card-body">
            @php $filteredCommands = $this->getFilteredCommands(); @endphp
            @if($filteredCommands->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-4"></i>
                    <p class="mt-2">{{ $searchQuery ? 'No commands match your search' : 'No commands found' }}</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">On</th>
                                <th>Command</th>
                                <th>Provider</th>
                                <th style="width: 150px;">Frequency</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $currentCategory = null; @endphp
                            @foreach($filteredCommands as $cmd)
                                @if($currentCategory !== $cmd->category)
                                    @php 
                                        $currentCategory = $cmd->category;
                                        $isCollapsed = in_array($currentCategory, $collapsedCategories);
                                        $categoryCommands = $filteredCommands->where('category', $currentCategory);
                                    @endphp
                                    <tr class="table-secondary" style="cursor: pointer;" wire:click="toggleCategory('{{ $currentCategory }}')">
                                        <td colspan="5" class="fw-bold small text-uppercase py-2">
                                            <i class="bi bi-chevron-{{ $isCollapsed ? 'right' : 'down' }} me-1"></i>
                                            <i class="bi bi-folder{{ $isCollapsed ? '' : '-open' }} me-1"></i>
                                            {{ $currentCategory ?? 'Uncategorized' }}
                                            <span class="badge bg-secondary ms-2">{{ $categoryCommands->count() }}</span>
                                        </td>
                                    </tr>
                                @endif
                                @if(!in_array($cmd->category, $collapsedCategories))
                                <tr class="{{ !$cmd->schedule_enabled ? 'text-muted' : '' }}">
                                    <td>
                                        <div class="form-check form-switch">
                                            <input 
                                                type="checkbox" 
                                                class="form-check-input" 
                                                {{ $cmd->schedule_enabled ? 'checked' : '' }}
                                                wire:click="quickToggle({{ $cmd->id }})"
                                                title="{{ $cmd->schedule_enabled ? 'Disable' : 'Enable' }} scheduled runs"
                                            >
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold {{ !$cmd->schedule_enabled ? 'text-decoration-line-through' : '' }}">
                                            {{ $cmd->display_name }}
                                        </div>
                                        <code class="small text-muted">{{ $cmd->command_name }}</code>
                                        @if($cmd->description)
                                            <div class="small text-muted">{{ Str::limit($cmd->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($cmd->provider_name)
                                            <span class="badge bg-light text-dark">{{ $cmd->provider_name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <select 
                                            class="form-select form-select-sm"
                                            wire:change="quickSetFrequency({{ $cmd->id }}, $event.target.value)"
                                            {{ !$cmd->schedule_enabled ? 'disabled' : '' }}
                                        >
                                            @foreach($frequencies as $key => $label)
                                                <option value="{{ $key }}" {{ $cmd->schedule_frequency === $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <button 
                                            class="btn btn-sm btn-outline-primary"
                                            wire:click="openEditModal({{ $cmd->id }})"
                                            title="Edit details"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Showing {{ count($commands) }} command(s)
                </div>
            @endif
        </div>
    </div>

    {{-- EDIT MODAL --}}
    @if($editingCommand)
        <div class="modal fade show" style="display: block;" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Schedule: {{ $editingCommand->display_name }}</h5>
                        <button type="button" class="btn-close" wire:click="closeEditModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Command</label>
                            <code class="d-block p-2 bg-light rounded">{{ $editingCommand->command_name }}</code>
                        </div>

                        @if($editingCommand->description)
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <p class="text-muted mb-0">{{ $editingCommand->description }}</p>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label">Schedule Frequency</label>
                            <select class="form-select" wire:model="editFrequency">
                                @foreach($frequencies as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                @switch($editFrequency)
                                    @case('hourly')
                                        Runs every hour at :00
                                        @break
                                    @case('2hours')
                                        Runs at 12am, 2am, 4am, 6am, 8am, 10am, 12pm, 2pm, 4pm, 6pm, 8pm, 10pm
                                        @break
                                    @case('4hours')
                                        Runs at 12am, 4am, 8am, 12pm, 4pm, 8pm
                                        @break
                                    @case('6hours')
                                        Runs at 12am, 6am, 12pm, 6pm
                                        @break
                                    @case('12hours')
                                        Runs at 2am and 2pm
                                        @break
                                    @case('daily')
                                        Runs once daily at 2am
                                        @break
                                    @case('weekly')
                                        Runs once weekly on Sunday at 2am
                                        @break
                                @endswitch
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="editEnabled" wire:model="editEnabled">
                                <label class="form-check-label" for="editEnabled">
                                    Enable scheduled runs
                                </label>
                            </div>
                            <div class="form-text">
                                When disabled, this command will not run automatically but can still be triggered manually.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeEditModal">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="saveCommand">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
