<div>
    {{-- Poll faster (2s) when running, slower (5s) otherwise --}}
    <div wire:poll.{{ $isRunning ? '2s' : '5s' }}="checkStatus">
        <x-page-header title="Schedule Runner"/>

        <livewire:admin.flash-message fade="true"/>

        {{-- WORKING TENANT SELECTION --}}
        @if($isSuperAdmin)
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-1">Working Tenant:</label>
                            <select class="form-select form-select-sm" wire:model.live="workingTenantMasterId">
                                <option value="">Select Tenant...</option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            @if($workingTenantMasterId)
                                @php $workingTenant = $tenants->find($workingTenantMasterId); @endphp
                                <div class="d-flex align-items-center h-100 pt-3">
                                    <span class="text-muted small">
                                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                                        Working with: <strong>{{ $workingTenant->name ?? 'Unknown' }}</strong>
                                        @if($workingTenant && $workingTenant->rdsInstance)
                                            <span class="ms-2 badge bg-secondary">{{ $workingTenant->rdsInstance->name }}</span>
                                        @endif
                                    </span>
                                </div>
                            @else
                                <div class="d-flex align-items-center h-100 pt-3">
                                    <span class="text-warning small">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Select a tenant to view commands, executions, and presets
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card mb-3">
                <div class="card-body py-2">
                    <span class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Working with tenant: <strong>{{ auth()->user()->tenantMaster->name ?? 'Your Tenant' }}</strong>
                    </span>
                </div>
            </div>
        @endif

        {{-- ACTION BUTTONS --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small">Command: </span>
                        <code class="fw-bold">raiops:run-schedule</code>
                    </div>
                    <div>
                        @if(count($selectedCommands) > 0)
                            <button
                                wire:click="openPresetFormModal"
                                class="btn btn-outline-primary btn-sm me-2">
                                <i class="bi bi-bookmark-plus me-1"></i>
                                Save Preset
                            </button>
                        @endif

                        @if($canRun)
                            <button
                                wire:click="startCommand"
                                wire:confirm="Are you sure you want to run the selected commands?"
                                class="btn btn-primary"
                                wire:loading.attr="disabled"
                                @if(!$workingTenantMasterId) disabled @endif>
                                <i class="bi bi-play-circle me-2"></i>
                                Start Command
                            </button>
                        @else
                            <button
                                wire:click="cancelExecution"
                                wire:confirm="Are you sure you want to stop the running command?"
                                class="btn btn-danger">
                                <i class="bi bi-stop-circle me-2"></i>
                                Stop Command
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB NAVIGATION --}}
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button
                    wire:click="switchTab('executions')"
                    class="nav-link @if($activeTab === 'executions') active @endif"
                    type="button">
                    <i class="bi bi-clock-history me-1"></i>
                    Executions
                    @if($isRunning)
                        <span class="badge bg-primary ms-1">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                        </span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    wire:click="switchTab('commands')"
                    class="nav-link @if($activeTab === 'commands') active @endif"
                    type="button">
                    <i class="bi bi-list-check me-1"></i>
                    Select Commands
                    <span class="badge @if($activeTab === 'commands') bg-primary @else bg-secondary @endif ms-1">
                        {{ count($selectedCommands) }}/{{ count($availableCommands) }}
                    </span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    wire:click="switchTab('presets')"
                    class="nav-link @if($activeTab === 'presets') active @endif"
                    type="button">
                    <i class="bi bi-bookmark-star me-1"></i>
                    Presets
                    @if(count($presets) > 0)
                        <span class="badge @if($activeTab === 'presets') bg-primary @else bg-secondary @endif ms-1">
                            {{ count($presets) }}
                        </span>
                    @endif
                </button>
            </li>
        </ul>

        {{-- EXECUTIONS TAB --}}
        @if($activeTab === 'executions')
            <div class="card">
                <div class="card-body">
                    @if($isRunning && $execution)
                        <div class="alert alert-info mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                <h5 class="mb-0">Command Running</h5>
                            </div>
                            
                            {{-- Progress Bar --}}
                            @php
                                $runTotal = $execution->total_steps ?: 1;
                                $runCompleted = $execution->completed_steps ?: 0;
                                $runPercent = min(100, round(($runCompleted / $runTotal) * 100));
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><strong>Progress:</strong></span>
                                    <span>{{ $runCompleted }} / {{ $execution->total_steps }} ({{ $runPercent }}%)</span>
                                </div>
                                <div class="progress" style="height: 24px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" 
                                         style="width: {{ $runPercent }}%;">
                                        {{ $runPercent }}%
                                    </div>
                                </div>
                            </div>

                            @if($execution->current_step)
                                <div class="bg-body-secondary rounded p-2 mb-2">
                                    <i class="bi bi-arrow-right-circle text-primary me-1"></i>
                                    <strong>Running:</strong> <code>{{ $execution->current_step }}</code>
                                </div>
                            @endif

                            @if($execution->output)
                                <div class="mt-3">
                                    <button wire:click="toggleOutput" class="btn btn-sm btn-outline-secondary mb-2">
                                        <i class="bi bi-{{ $outputExpanded ? 'chevron-up' : 'chevron-down' }} me-1"></i>
                                        Output Log ({{ $outputExpanded ? 'click to collapse' : 'click to expand' }})
                                    </button>
                                    @if($outputExpanded)
                                        <pre class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow-y: auto; font-size: 0.85em;">{{ $execution->output }}</pre>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    <h5>Execution History</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tenant</th>
                                    <th>RDS</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th>Started</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($executionHistory as $exec)
                                    <tr class="@if($exec->id === $latestExecutionId) table-info @endif">
                                        <td>{{ $exec->id }}</td>
                                        <td>{{ $exec->tenantMaster->name ?? 'N/A' }}</td>
                                        <td>{{ $exec->rdsInstance->name ?? 'N/A' }}</td>
                                        <td>
                                            @if($exec->status === 'running')
                                                <span class="badge bg-primary">Running</span>
                                            @elseif($exec->status === 'completed')
                                                <span class="badge bg-success">Completed</span>
                                            @else
                                                <span class="badge bg-danger">Failed</span>
                                            @endif
                                        </td>
                                        <td>{{ $exec->completed_steps }} / {{ $exec->total_steps }}</td>
                                        <td>{{ $exec->started_at?->format('Y-m-d H:i:s') }}</td>
                                        <td>
                                            <button wire:click="viewExecution({{ $exec->id }})" class="btn btn-sm btn-outline-primary">
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No executions yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- COMMANDS TAB --}}
        @if($activeTab === 'commands')
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>
                            Select & Configure Commands
                        </h5>
                        <div class="d-flex align-items-center gap-2">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" placeholder="Search commands..." wire:model.live.debounce.300ms="commandSearchQuery">
                                @if($commandSearchQuery)
                                    <button class="btn btn-outline-secondary" type="button" wire:click="$set('commandSearchQuery', '')">
                                        <i class="bi bi-x"></i>
                                    </button>
                                @endif
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" wire:click="expandAllCategories" title="Expand All">
                                    <i class="bi bi-arrows-expand"></i>
                                </button>
                                <button class="btn btn-outline-secondary" wire:click="collapseAllCategories" title="Collapse All">
                                    <i class="bi bi-arrows-collapse"></i>
                                </button>
                            </div>
                            <button wire:click="selectAllCommands" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-check-all"></i> All
                            </button>
                            <button wire:click="deselectAllCommands" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-lg"></i> None
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            wire:click="@if(count($selectedCommands) === count($availableCommands)) deselectAllCommands @else selectAllCommands @endif"
                                            @if(count($selectedCommands) === count($availableCommands)) checked @endif>
                                    </th>
                                    <th>Command</th>
                                    <th>Description</th>
                                    <th>Parameters</th>
                                    <th style="width: 80px;" class="text-center">Retry?</th>
                                    <th style="width: 100px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $filteredCommands = $this->getFilteredAvailableCommands();
                                    $categories = $filteredCommands->map(function($cmd, $idx) use ($availableCommands) {
                                        // Find original index in availableCommands
                                        $originalIdx = collect($availableCommands)->search(function($item) use ($cmd) {
                                            return ($item['command'] ?? '') === ($cmd['command'] ?? '');
                                        });
                                        return array_merge($cmd, ['original_index' => $originalIdx !== false ? $originalIdx : $idx]);
                                    })->groupBy('category');
                                @endphp

                                @if($filteredCommands->isEmpty())
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-search me-2"></i>No commands match "{{ $commandSearchQuery }}"
                                        </td>
                                    </tr>
                                @endif

                                @foreach($categories as $category => $commands)
                                    @php $isCollapsed = in_array($category, $collapsedCategories); @endphp
                                    <tr class="table-secondary" style="cursor: pointer;" wire:click="toggleCommandCategory('{{ $category }}')">
                                        <td colspan="6" class="fw-bold py-2">
                                            <i class="bi bi-chevron-{{ $isCollapsed ? 'right' : 'down' }} me-1"></i>
                                            <i class="bi bi-folder{{ $isCollapsed ? '' : '-open' }} me-2"></i>
                                            {{ $category ?: 'General' }}
                                            <span class="badge bg-secondary ms-2">{{ $commands->count() }}</span>
                                            @php
                                                $selectedInCategory = $commands->filter(function($cmd) use ($selectedCommands) {
                                                    return in_array($cmd['original_index'], $selectedCommands);
                                                })->count();
                                            @endphp
                                            @if($selectedInCategory > 0)
                                                <span class="badge bg-primary ms-1">{{ $selectedInCategory }} selected</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if(!$isCollapsed)
                                    @foreach($commands as $cmd)
                                        @php
                                            $cmdIndex = $cmd['original_index'];
                                        @endphp
                                        <tr>
                                            <td>
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    wire:model.live="selectedCommands"
                                                    value="{{ $cmdIndex }}"
                                                    id="cmd-{{ $cmdIndex }}">
                                            </td>
                                            <td>
                                                <code class="text-primary">{{ $cmd['command'] }}</code>
                                            </td>
                                            <td>{{ Str::limit($cmd['description'] ?? '', 50) }}</td>
                                            <td>
                                                @if(isset($cmd['params']) && !empty($cmd['params']))
                                                    @foreach($cmd['params'] as $key => $value)
                                                        <span class="badge bg-info me-1">
                                                            {{ ltrim($key, '-') }}@if(!is_bool($value))={{ Str::limit($value, 15) }}@endif
                                                        </span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    wire:model.live="availableCommands.{{ $cmdIndex }}.retry"
                                                    title="Retry on failure">
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary"
                                                        type="button"
                                                        wire:click="openParameterModal({{ $cmdIndex }})"
                                                        title="Customize Parameters">
                                                    <i class="bi bi-sliders"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                    @endif
                                @endforeach
                                @if(count($availableCommands) === 0 && !$commandSearchQuery)
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox me-2"></i>No commands available for this tenant
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- PRESETS TAB --}}
        @if($activeTab === 'presets')
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">
                        <i class="bi bi-bookmark-star me-2"></i>
                        Saved Presets
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Commands</th>
                                    <th>Chain</th>
                                    <th>Last Run</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($presets as $preset)
                                    <tr>
                                        <td>{{ $preset->name }}</td>
                                        <td>{{ $preset->description }}</td>
                                        <td>{{ count($preset->commands) }}</td>
                                        <td>@if($preset->is_chain) <span class="badge bg-info">Yes</span> @else <span class="badge bg-secondary">No</span> @endif</td>
                                        <td>{{ $preset->last_run_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                                        <td>
                                            <button wire:click="loadPreset({{ $preset->id }})" class="btn btn-sm btn-outline-primary">
                                                Load
                                            </button>
                                            <button wire:click="archivePreset({{ $preset->id }})" class="btn btn-sm btn-outline-warning">
                                                Archive
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No presets yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- EXECUTION MODAL --}}
        @if($showExecutionModal && $modalExecution)
            <div class="modal fade show" style="display: block;" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Execution #{{ $modalExecution->id }}</h5>
                            <button wire:click="closeExecutionModal" class="btn-close"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Status:</strong> 
                                @if($modalExecution->status === 'pending')
                                    <span class="badge bg-secondary">Pending</span>
                                @elseif($modalExecution->status === 'running')
                                    <span class="badge bg-primary">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        Running
                                    </span>
                                @elseif($modalExecution->status === 'completed')
                                    <span class="badge bg-success">Completed</span>
                                @else
                                    <span class="badge bg-danger">Failed</span>
                                @endif
                            </p>

                            {{-- Progress Bar --}}
                            @php
                                $total = $modalExecution->total_steps ?: 1;
                                $completed = $modalExecution->completed_steps ?: 0;
                                $percent = min(100, round(($completed / $total) * 100));
                            @endphp
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong>Progress:</strong>
                                    <span>{{ $completed }} / {{ $modalExecution->total_steps }} ({{ $percent }}%)</span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar @if($modalExecution->status === 'running') progress-bar-striped progress-bar-animated @endif @if($modalExecution->status === 'failed') bg-danger @elseif($modalExecution->status === 'completed') bg-success @endif" 
                                         role="progressbar" 
                                         style="width: {{ $percent }}%;" 
                                         aria-valuenow="{{ $percent }}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>

                            {{-- Current Step (only shown when running) --}}
                            @if($modalExecution->status === 'running' && $modalExecution->current_step)
                                <div class="alert alert-info py-2 mb-3">
                                    <i class="bi bi-arrow-right-circle me-2"></i>
                                    <strong>Currently running:</strong> 
                                    <code>{{ $modalExecution->current_step }}</code>
                                </div>
                            @endif

                            @if($modalExecution->output)
                                <div class="mt-3">
                                    <strong>Output:</strong>
                                    <pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto; font-size: 0.85em;">{{ $modalExecution->output }}</pre>
                                </div>
                            @endif
                            @if($modalExecution->error)
                                <div class="mt-3">
                                    <strong>Error:</strong>
                                    <pre class="bg-danger text-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">{{ $modalExecution->error }}</pre>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button wire:click="closeExecutionModal" class="btn btn-secondary">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        @endif

        {{-- PRESET FORM MODAL --}}
        @if($showPresetFormModal)
            <div class="modal fade show" style="display: block;" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $editingPresetId ? 'Edit' : 'Create' }} Preset</h5>
                            <button wire:click="closePresetFormModal" class="btn-close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" wire:model="presetName">
                                @error('presetName') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" wire:model="presetDescription" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" wire:model="isChain">
                                    Stop on first error (Chain Mode)
                                </label>
                            </div>
                            <p class="text-muted small">Selected Commands: {{ count($selectedCommands) }}</p>
                        </div>
                        <div class="modal-footer">
                            <button wire:click="closePresetFormModal" class="btn btn-secondary">Cancel</button>
                            <button wire:click="savePreset" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        @endif

        {{-- PARAMETER CUSTOMIZATION MODAL --}}
        @if($showParameterModal)
            <div class="modal fade show" style="display: block;" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Customize Parameters: <code>{{ $customParameters['command'] ?? '' }}</code></h5>
                            <button wire:click="closeParameterModal" class="btn-close"></button>
                        </div>
                        <div class="modal-body">
                            @if(!empty($customParameters['analysis']['description']))
                                <p class="text-muted">{{ $customParameters['analysis']['description'] }}</p>
                            @elseif(empty($customParameters['analysis']['options']) && empty($customParameters['analysis']['arguments']))
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    This is a remote RAI command. Common parameters have been pre-populated based on the command type.
                                </div>
                            @endif

                            @if(!empty($customParameters['analysis']['arguments']))
                                <h6 class="mt-3 mb-2">Arguments:</h6>
                                @foreach($customParameters['analysis']['arguments'] as $argument)
                                    <div class="mb-3">
                                        <label class="form-label">
                                            {{ $argument['name'] }}
                                            @if($argument['required']) <span class="text-danger">*</span> @endif
                                            <small class="text-muted">({{ $argument['description'] }})</small>
                                        </label>
                                        @php
                                            $isDate = str_contains(strtolower($argument['name']), 'date') || 
                                                     str_contains(strtolower($argument['name']), 'start') || 
                                                     str_contains(strtolower($argument['name']), 'end');
                                        @endphp
                                        @if($isDate)
                                            <input type="date" 
                                                   class="form-control" 
                                                   wire:model.live="editedParams.{{ $argument['name'] }}"
                                                   value="{{ $editedParams[$argument['name']] ?? '' }}">
                                        @else
                                            <input type="text" 
                                                   class="form-control" 
                                                   wire:model.live="editedParams.{{ $argument['name'] }}"
                                                   placeholder="{{ $argument['default'] ?? 'No default' }}">
                                        @endif
                                    </div>
                                @endforeach
                            @endif

                            @if(!empty($customParameters['analysis']['options']))
                                <h6 class="mt-3 mb-2">Options:</h6>
                                @foreach($customParameters['analysis']['options'] as $option)
                                    <div class="mb-3">
                                        <label class="form-label">
                                            --{{ $option['name'] }}
                                            @if($option['shortcut']) <small class="text-muted">(-{{ $option['shortcut'] }})</small> @endif
                                            <small class="text-muted">({{ $option['description'] }})</small>
                                        </label>
                                        @if($option['accepts_value'])
                                            @php
                                                $isDate = str_contains(strtolower($option['name']), 'date') || 
                                                         str_contains(strtolower($option['name']), 'start') || 
                                                         str_contains(strtolower($option['name']), 'end');
                                            @endphp
                                            @if($isDate)
                                                <input type="date" 
                                                       class="form-control" 
                                                       wire:model.live="editedParams.--{{ $option['name'] }}"
                                                       value="{{ $editedParams['--' . $option['name']] ?? '' }}">
                                            @else
                                                <input type="text" 
                                                       class="form-control" 
                                                       wire:model.live="editedParams.--{{ $option['name'] }}"
                                                       placeholder="{{ $option['default'] ?? 'No default' }}">
                                            @endif
                                        @else
                                            <div class="form-check">
                                                <input type="checkbox" 
                                                       class="form-check-input" 
                                                       wire:model.live="editedParams.--{{ $option['name'] }}">
                                                <label class="form-check-label">Enable</label>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button wire:click="closeParameterModal" class="btn btn-secondary">Cancel</button>
                            <button wire:click="saveParameters" class="btn btn-primary">Save Parameters</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        @endif
    </div>
</div>
