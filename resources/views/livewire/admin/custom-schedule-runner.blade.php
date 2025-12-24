<div>
    <div wire:poll.5s="checkStatus">
        <x-page-header title="Schedule Runner"/>

        <livewire:admin.flash-message fade="true"/>

        {{-- TENANT SELECTION --}}
        @if($isSuperAdmin)
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Filter UI by Tenant:</label>
                            <select class="form-select form-select-sm" wire:model.live="selectedTenantMasterId">
                                <option value="">All Tenants</option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Commands run for Tenant:</label>
                            <select class="form-select form-select-sm" wire:model.live="commandTenantMasterId">
                                <option value="">No Tenant (All)</option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            @if($commandTenantMasterId)
                                @php $commandTenant = $tenants->find($commandTenantMasterId); @endphp
                                <span class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Commands will run for: <strong>{{ $commandTenant->name ?? 'Unknown' }}</strong>
                                    @if($commandTenant && $commandTenant->rdsInstance)
                                        <br>RDS: <strong>{{ $commandTenant->rdsInstance->name }}</strong>
                                    @endif
                                </span>
                            @else
                                <span class="text-muted small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Commands will run for: <strong>All Tenants</strong>
                                </span>
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
                        Commands will run for your tenant: <strong>{{ auth()->user()->tenantMaster->name ?? 'Your Tenant' }}</strong>
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
                                @if(!$commandTenantMasterId && !$selectedTenantMasterId) disabled @endif>
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
                        <div class="alert alert-info">
                            <h5><i class="bi bi-play-circle me-2"></i>Command Running</h5>
                            <p><strong>Status:</strong> {{ $execution->status }}</p>
                            <p><strong>Progress:</strong> {{ $execution->completed_steps }} / {{ $execution->total_steps }} ({{ $execution->progress_percentage }}%)</p>
                            @if($execution->current_step)
                                <p><strong>Current Step:</strong> <code>{{ $execution->current_step }}</code></p>
                            @endif
                            @if($execution->output)
                                <div class="mt-3">
                                    <strong>Output:</strong>
                                    <pre class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow-y: auto;">{{ $execution->output }}</pre>
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
                        <div>
                            <button wire:click="selectAllCommands" class="btn btn-sm btn-outline-light me-2">
                                <i class="bi bi-check-all"></i> Select All
                            </button>
                            <button wire:click="deselectAllCommands" class="btn btn-sm btn-outline-light">
                                <i class="bi bi-x-lg"></i> Deselect All
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
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
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Parameters</th>
                                    <th style="width: 80px;" class="text-center">Retry?</th>
                                    <th style="width: 100px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $categories = collect($availableCommands)->map(function($cmd, $idx) {
                                        return array_merge($cmd, ['original_index' => $idx]);
                                    })->groupBy('category');
                                @endphp

                                @foreach($categories as $category => $commands)
                                    <tr>
                                        <td colspan="7" class="fw-bold bg-light">
                                            <i class="bi bi-folder2-open me-2"></i>{{ $category ?: 'General' }}
                                        </td>
                                    </tr>
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
                                            <td>
                                                <span class="badge bg-secondary">{{ $cmd['category'] ?? 'General' }}</span>
                                            </td>
                                            <td>{{ $cmd['description'] ?? '' }}</td>
                                            <td>
                                                @if(isset($cmd['params']) && !empty($cmd['params']))
                                                    @foreach($cmd['params'] as $key => $value)
                                                        <span class="badge bg-info me-1">
                                                            {{ $key }}@if(!is_bool($value))
                                                                ={{ $value }}
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted small">No parameters</span>
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
                                                <button class="btn btn-sm p-0 bg-transparent border-0 text-secondary"
                                                        type="button"
                                                        wire:click="openParameterModal({{ $cmdIndex }})"
                                                        title="Customize Parameters">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                                @if(count($availableCommands) === 0)
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No commands available</td>
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
                                @if($modalExecution->status === 'running')
                                    <span class="badge bg-primary">Running</span>
                                @elseif($modalExecution->status === 'completed')
                                    <span class="badge bg-success">Completed</span>
                                @else
                                    <span class="badge bg-danger">Failed</span>
                                @endif
                            </p>
                            <p><strong>Progress:</strong> {{ $modalExecution->completed_steps }} / {{ $modalExecution->total_steps }}</p>
                            @if($modalExecution->output)
                                <div class="mt-3">
                                    <strong>Output:</strong>
                                    <pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">{{ $modalExecution->output }}</pre>
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
        @if($showParameterModal && !empty($customParameters))
            <div class="modal fade show" style="display: block;" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Customize Parameters: {{ $customParameters['command'] ?? '' }}</h5>
                            <button wire:click="closeParameterModal" class="btn-close"></button>
                        </div>
                        <div class="modal-body">
                            @if(!empty($customParameters['analysis']['description']))
                                <p class="text-muted">{{ $customParameters['analysis']['description'] }}</p>
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
