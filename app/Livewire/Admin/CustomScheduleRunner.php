<?php

namespace App\Livewire\Admin;

use App\Models\CommandExecution;
use App\Models\CommandPreset;
use App\Models\ScheduledCommand;
use App\Models\TenantMaster;
use App\Services\CommandAnalyzer;
use App\Services\TenantIntegrationService;
use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

class CustomScheduleRunner extends Component
{
    public $execution;
    public $isRunning = false;
    public $canRun = true;
    public $latestExecutionId = null;

    // Tab navigation
    public $activeTab = 'executions'; // executions, commands, presets

    // Command selection
    public $availableCommands = [];
    public $selectedCommands = [];
    public $showCommandSelector = false;

    // Execution history
    public $executionHistory = [];
    public $selectedHistoryId = null;
    public $showExecutionModal = false;
    public $modalExecution = null;

    // Presets
    public $presets = [];
    public $archivedPresets = [];
    public $showPresetsListModal = false;
    public $showPresetFormModal = false;
    public $editingPresetId = null;
    public $presetName = '';
    public $presetDescription = '';
    public $isChain = false;
    public $showArchivedInList = false;
    
    // Tenant selection (super admin only)
    public $selectedTenantMasterId = null; // For filtering UI data (executions, presets)
    public $commandTenantMasterId = null; // For commands to run against (determines which tenant commands execute for)
    public $tenants = [];
    public $isSuperAdmin = false;
    
    // Parameter customization
    public $customizingCommand = null;
    public $customParameters = [];
    public $showParameterModal = false;
    public $editedParams = [];

    public function mount()
    {
        $this->isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;
        
        // Load tenants for super admin
        if ($this->isSuperAdmin) {
            $this->tenants = TenantMaster::with('rdsInstance')->orderBy('name')->get();
            
            // Load selectedTenantMasterId from cookie (for UI filtering)
            if ($cookie = Cookie::get('schedule_runner_selected_tenant_master')) {
                $cookie = $cookie ?: null;
                if ($cookie === null || TenantMaster::find($cookie)) {
                    $this->selectedTenantMasterId = $cookie;
                }
            }
            
            // Load commandTenantMasterId from cookie (for command execution)
            if ($cookie = Cookie::get('schedule_runner_command_tenant_master')) {
                $cookie = $cookie ?: null;
                if ($cookie === null || TenantMaster::find($cookie)) {
                    $this->commandTenantMasterId = $cookie;
                }
            }
        } else {
            // Regular users: use their tenant (if they have one)
            $this->selectedTenantMasterId = auth()->user()->tenant_master_id ?? null;
            $this->commandTenantMasterId = auth()->user()->tenant_master_id ?? null;
        }
        
        $this->loadAvailableCommands();
        $this->loadExecutionHistory();
        $this->loadPresets();
        $this->checkStatus();
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->showCommandSelector = false;
        $this->showPresetsListModal = false;
        $this->showPresetFormModal = false;
    }

    public function loadPresets()
    {
        $query = CommandPreset::active();
        
        if ($this->isSuperAdmin) {
            if ($this->selectedTenantMasterId) {
                $query->where('tenant_master_id', $this->selectedTenantMasterId);
            }
        } else {
            // For now, only super admins can use this
            $query->where('tenant_master_id', $this->selectedTenantMasterId);
        }
        
        $this->presets = $query->with('tenantMaster')->orderBy('name')->get();
        
        $archivedQuery = CommandPreset::archived();
        if ($this->isSuperAdmin) {
            if ($this->selectedTenantMasterId) {
                $archivedQuery->where('tenant_master_id', $this->selectedTenantMasterId);
            }
        } else {
            $archivedQuery->where('tenant_master_id', $this->selectedTenantMasterId);
        }
        
        $this->archivedPresets = $archivedQuery->with('tenantMaster')->orderBy('name')->get();
    }

    public function loadExecutionHistory()
    {
        $query = CommandExecution::where('command_name', 'like', 'raiops:run-schedule%');
        
        if ($this->isSuperAdmin) {
            if ($this->selectedTenantMasterId) {
                $query->where('tenant_master_id', $this->selectedTenantMasterId);
            }
        } else {
            if ($this->selectedTenantMasterId) {
                $query->where('tenant_master_id', $this->selectedTenantMasterId);
            }
        }
        
        $this->executionHistory = $query->with('tenantMaster', 'rdsInstance')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    }
    
    public function updatedSelectedTenantMasterId()
    {
        $this->selectedTenantMasterId = $this->selectedTenantMasterId ?: null;
        
        if ($this->isSuperAdmin) {
            Cookie::queue('schedule_runner_selected_tenant_master', $this->selectedTenantMasterId ?? '', 60 * 24 * 30);
        }
        
        $this->loadPresets();
        $this->loadExecutionHistory();
        // Don't reload commands here - that's controlled by commandTenantMasterId
    }
    
    public function updatedCommandTenantMasterId()
    {
        $this->commandTenantMasterId = $this->commandTenantMasterId ?: null;
        
        if ($this->isSuperAdmin) {
            Cookie::queue('schedule_runner_command_tenant_master', $this->commandTenantMasterId ?? '', 60 * 24 * 30);
        }
        
        // Reload commands when command tenant changes (integration filtering)
        $this->loadAvailableCommands();
    }

    public function viewExecution($id)
    {
        $this->modalExecution = CommandExecution::find($id);
        $this->selectedHistoryId = $id;
        $this->showExecutionModal = true;
    }

    public function closeExecutionModal()
    {
        $this->showExecutionModal = false;
        $this->modalExecution = null;
        $this->selectedHistoryId = null;
    }

    public function togglePresetsListModal()
    {
        $this->showPresetsListModal = !$this->showPresetsListModal;
        if ($this->showPresetsListModal) {
            $this->loadPresets();
        }
    }

    public function openPresetFormModal($presetId = null)
    {
        $this->showPresetFormModal = true;
        $this->editingPresetId = $presetId;

        if ($presetId) {
            $preset = CommandPreset::find($presetId);
            if ($preset) {
                $this->presetName = $preset->name;
                $this->presetDescription = $preset->description;
                $this->isChain = $preset->is_chain;
            }
        } else {
            $this->presetName = '';
            $this->presetDescription = '';
            $this->isChain = false;
        }
    }

    public function closePresetFormModal()
    {
        $this->showPresetFormModal = false;
        $this->editingPresetId = null;
        $this->presetName = '';
        $this->presetDescription = '';
        $this->isChain = false;
    }

    public function savePreset()
    {
        $this->validate([
            'presetName' => 'required|min:3|max:100',
            'presetDescription' => 'nullable|max:500',
        ]);

        if (empty($this->selectedCommands)) {
            $this->dispatch('notify', type: 'error', message: 'Please select at least one command!');
            return;
        }

        // Build command configuration
        $selectedCommandsData = [];
        foreach ($this->selectedCommands as $index) {
            if (isset($this->availableCommands[$index])) {
                $cmdData = $this->availableCommands[$index];
                $selectedCommandsData[] = [
                    'command' => $cmdData['command'],
                    'retry' => $cmdData['retry'] ?? true,
                ];
            }
        }

        $presetTenantMasterId = $this->selectedTenantMasterId;

        if ($this->editingPresetId) {
            $preset = CommandPreset::find($this->editingPresetId);
            if ($preset) {
                if (!$this->isSuperAdmin && $preset->tenant_master_id != $presetTenantMasterId) {
                    $this->dispatch('notify', type: 'error', message: 'You do not have permission to edit this preset.');
                    return;
                }
                
                $preset->update([
                    'name' => $this->presetName,
                    'description' => $this->presetDescription,
                    'commands' => $selectedCommandsData,
                    'is_chain' => $this->isChain,
                    'tenant_master_id' => $presetTenantMasterId,
                ]);
                $message = 'Preset updated successfully!';
            }
        } else {
            CommandPreset::create([
                'name' => $this->presetName,
                'description' => $this->presetDescription,
                'commands' => $selectedCommandsData,
                'is_chain' => $this->isChain,
                'created_by' => auth()->id(),
                'tenant_master_id' => $presetTenantMasterId,
            ]);
            $message = 'Preset saved successfully!';
        }

        $this->loadPresets();
        $this->closePresetFormModal();
        $this->dispatch('notify', type: 'success', message: $message);
    }

    public function loadPreset($presetId)
    {
        $preset = CommandPreset::find($presetId);

        if (!$preset) {
            $this->dispatch('notify', type: 'error', message: 'Preset not found!');
            return;
        }

        $this->selectedCommands = [];

        foreach ($preset->commands as $cmdData) {
            $commandName = $cmdData['command'] ?? null;
            if ($commandName) {
                $baseCmd = explode(' ', $commandName)[0];
                
                foreach ($this->availableCommands as $idx => $availCmd) {
                    if ($availCmd['command'] === $baseCmd) {
                        $this->selectedCommands[] = $idx;
                        break;
                    }
                }
            }
        }

        $this->isChain = $preset->is_chain;
        $preset->recordRun();
        $this->activeTab = 'commands';
        $this->dispatch('notify', type: 'success', message: "Preset '{$preset->name}' loaded!");
    }

    public function cancelExecution()
    {
        $runningExecution = CommandExecution::where('command_name', 'like', 'raiops:run-schedule%')
            ->where('status', 'running')
            ->latest()
            ->first();

        if (!$runningExecution) {
            $this->dispatch('notify', type: 'error', message: 'No running command to cancel!');
            return;
        }

        $pid = $runningExecution->process_id;

        if (!$pid) {
            $this->dispatch('notify', type: 'error', message: 'No process ID found!');
            return;
        }

        try {
            $sigterm = defined('SIGTERM') ? SIGTERM : 15;
            $sigkill = defined('SIGKILL') ? SIGKILL : 9;
            
            if (function_exists('posix_kill')) {
                if (posix_getpgid($pid)) {
                    posix_kill($pid, $sigterm);
                    usleep(500000);
                    if (posix_getpgid($pid)) {
                        posix_kill($pid, $sigkill);
                    }
                }
            } else {
                exec("kill -15 $pid 2>&1");
                usleep(500000);
                exec("kill -9 $pid 2>&1");
            }

            $runningExecution->update([
                'status' => 'failed',
                'error' => 'Cancelled by user: ' . auth()->user()->name,
                'completed_at' => now(),
            ]);

            $this->dispatch('notify', type: 'success', message: 'Command cancelled successfully!');
            $this->checkStatus();
        } catch (\Exception $e) {
            \Log::error("Failed to cancel execution: " . $e->getMessage());
            $this->dispatch('notify', type: 'error', message: 'Failed to cancel command: ' . $e->getMessage());
        }
    }

    public function archivePreset($presetId)
    {
        $preset = CommandPreset::find($presetId);
        if ($preset) {
            $preset->archive();
            $this->loadPresets();
            $this->dispatch('notify', type: 'success', message: 'Preset archived!');
        }
    }

    public function unarchivePreset($presetId)
    {
        $preset = CommandPreset::find($presetId);
        if ($preset) {
            $preset->unarchive();
            $this->loadPresets();
            $this->dispatch('notify', type: 'success', message: 'Preset restored!');
        }
    }

    public function deletePreset($presetId)
    {
        $preset = CommandPreset::find($presetId);
        if ($preset) {
            $preset->delete();
            $this->loadPresets();
            $this->dispatch('notify', type: 'success', message: 'Preset deleted!');
        }
    }

    public function selectAllCommands()
    {
        $this->selectedCommands = array_keys($this->availableCommands);
    }

    public function deselectAllCommands()
    {
        $this->selectedCommands = [];
    }

    public function loadAvailableCommands()
    {
        // Determine which tenant to use for filtering commands (integration-based)
        $tenantMasterId = $this->commandTenantMasterId ?? $this->selectedTenantMasterId;
        
        try {
            // Load commands filtered by tenant's integrations if tenant is selected
            if ($tenantMasterId) {
                $scheduledCommands = ScheduledCommand::getForTenantMaster($tenantMasterId);
            } else {
                // Load all active commands (no tenant filtering)
                $scheduledCommands = ScheduledCommand::active()
                    ->orderBy('sort_order')
                    ->orderBy('display_name')
                    ->get();
            }
            
            // Convert ScheduledCommand models to the format expected by UI
            $this->availableCommands = [];
            foreach ($scheduledCommands as $cmd) {
                // Get default params and resolve dynamic dates
                $params = $cmd->default_params ?? [];
                
                // Resolve dynamic dates in params
                foreach ($params as $key => $value) {
                    if (is_string($value) && preg_match('/\{date:([^}]+)\}/', $value, $matches)) {
                        $dateExpr = $matches[1];
                        
                        // Parse format like "Ymd:-3days" or just "-3days"
                        if (strpos($dateExpr, ':') !== false) {
                            [$format, $expr] = explode(':', $dateExpr, 2);
                        } else {
                            $format = 'Ymd';
                            $expr = $dateExpr;
                        }
                        
                        // Handle special cases
                        if ($expr === 'today') {
                            $date = now();
                        } elseif ($expr === 'yesterday') {
                            $date = now()->subDay();
                        } else {
                            // Parse expressions like "-3days", "+1week", etc.
                            $date = now()->modify($expr);
                        }
                        
                        $params[$key] = $date->format($format);
                    }
                }
                
                $this->availableCommands[] = [
                    'command' => $cmd->command_name,
                    'display_name' => $cmd->display_name,
                    'description' => $cmd->description ?? '',
                    'category' => $cmd->category ?? 'Other',
                    'params' => $params,
                    'requires_tenant' => $cmd->requires_tenant ?? true,
                    'retry' => true,
                ];
            }
        } catch (\Exception $e) {
            \Log::error("Failed to load commands: " . $e->getMessage(), [
                'tenant_master_id' => $tenantMasterId,
                'error' => $e->getMessage()
            ]);
            $this->availableCommands = [];
        }
    }

    public function openParameterModal($index)
    {
        $this->customizingCommand = $index;
        $command = $this->availableCommands[$index];
        $commandName = $command['command'] ?? '';
        
        // Analyze the command to get its signature
        $analysis = CommandAnalyzer::analyzeCommand($commandName);
        
        $existingParams = $command['params'] ?? [];
        $currentParamsForDisplay = [];

        foreach ($analysis['options'] as $option) {
            $key = '--' . $option['name'];
            $value = $existingParams[$key] ?? $existingParams[$option['name']] ?? $option['default'];
            if ($this->isDateParameter($option['name']) && is_string($value) && $value !== '') {
                $currentParamsForDisplay[$key] = $this->convertDateForDisplay($value, $commandName);
            } else {
                $currentParamsForDisplay[$key] = $value;
            }
        }
        foreach ($analysis['arguments'] as $argument) {
            $key = $argument['name'];
            $value = $existingParams[$key] ?? $argument['default'];
            if ($this->isDateParameter($argument['name']) && is_string($value) && $value !== '') {
                $currentParamsForDisplay[$key] = $this->convertDateForDisplay($value, $commandName);
            } else {
                $currentParamsForDisplay[$key] = $value;
            }
        }

        $this->customParameters = [
            'command' => $commandName,
            'analysis' => $analysis,
            'current_params' => $currentParamsForDisplay,
        ];

        $this->editedParams = $currentParamsForDisplay; // Initialize edited params
        $this->showParameterModal = true;
    }

    private function convertDateForDisplay($dateValue, $commandName): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            return $dateValue;
        }
        if (preg_match('/^\d{8}$/', $dateValue)) {
            return substr($dateValue, 0, 4) . '-' . substr($dateValue, 4, 2) . '-' . substr($dateValue, 6, 2);
        }
        try {
            $date = new \DateTime($dateValue);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return $dateValue;
        }
    }

    public function saveParameters()
    {
        if ($this->customizingCommand !== null) {
            $command = $this->availableCommands[$this->customizingCommand];
            $commandName = $command['command'] ?? '';
            
            $processedParams = [];
            foreach ($this->editedParams as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $cleanKey = ltrim($key, '-');
                    if ($this->isDateParameter($cleanKey) && is_string($value)) {
                        $processedParams[$key] = $this->formatDateForCommand($value, $commandName, $cleanKey);
                    } else {
                        $processedParams[$key] = $value;
                    }
                }
            }

            $groupedByCleanKey = [];
            foreach ($processedParams as $key => $value) {
                $cleanKey = ltrim($key, '-');
                if (!isset($groupedByCleanKey[$cleanKey]) || str_starts_with($key, '--')) {
                    $groupedByCleanKey[$cleanKey] = ['key' => $key, 'value' => $value];
                }
            }
            
            $normalizedParams = [];
            foreach ($groupedByCleanKey as $cleanKey => $data) {
                $normalizedParams[$data['key']] = $data['value'];
            }

            $this->availableCommands[$this->customizingCommand]['params'] = $normalizedParams;
            $this->closeParameterModal();
            $this->dispatch('notify', type: 'success', message: 'Parameters updated!');
        }
    }

    public function closeParameterModal()
    {
        $this->showParameterModal = false;
        $this->customizingCommand = null;
        $this->customParameters = [];
        $this->editedParams = [];
    }

    private function isDateParameter($name): bool
    {
        $dateKeywords = ['date', 'start', 'end', 'from', 'to', 'day', 'month', 'year'];
        $nameLower = strtolower($name);

        foreach ($dateKeywords as $keyword) {
            if (str_contains($nameLower, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function formatDateForCommand($dateValue, $commandName, $paramName): string
    {
        $isToastCommand = str_starts_with($commandName, 'toast:');
        
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateValue, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];
            if ($isToastCommand) {
                return $year . $month . $day;
            }
            return $dateValue;
        }
        if (preg_match('/^\d{8}$/', $dateValue)) {
            if ($isToastCommand) {
                return $dateValue;
            }
            return substr($dateValue, 0, 4) . '-' . substr($dateValue, 4, 2) . '-' . substr($dateValue, 6, 2);
        }
        return $dateValue;
    }

    public function checkStatus()
    {
        $this->loadExecutionHistory();

        if ($this->selectedHistoryId && $this->showExecutionModal) {
            $this->modalExecution = CommandExecution::find($this->selectedHistoryId);
        }

        $runningExecution = CommandExecution::where('command_name', 'like', 'raiops:run-schedule%')
            ->where('status', 'running')
            ->latest()
            ->first();

        if ($runningExecution) {
            $this->execution = $runningExecution;
            $this->latestExecutionId = $runningExecution->id;

            if ($runningExecution->process_id && $this->isProcessRunning($runningExecution->process_id)) {
                $this->isRunning = true;
                $this->canRun = false;
                $this->loadLogFile();
            } else {
                $runningExecution->update([
                    'status' => 'failed',
                    'error' => 'Process terminated unexpectedly. PID: ' . $runningExecution->process_id,
                    'completed_at' => now(),
                ]);
                $this->isRunning = false;
                $this->canRun = true;
            }
        } else {
            $latest = CommandExecution::where('command_name', 'like', 'raiops:run-schedule%')
                ->latest()
                ->first();

            if ($latest) {
                $this->latestExecutionId = $latest->id;
            }

            $this->isRunning = false;
            $this->canRun = true;
        }
    }

    private function loadLogFile()
    {
        if (!$this->execution) {
            return;
        }

        $logFile = storage_path("logs/schedule_cron_{$this->execution->id}.log");

        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            if ($content && $content !== $this->execution->output) {
                $this->execution->update(['output' => $content]);
                $this->execution->refresh();
            }
        }
    }

    public function startCommand()
    {
        $this->checkStatus();

        if (!$this->canRun) {
            session()->flash('error', 'Command is already running!');
            return;
        }

        if (empty($this->selectedCommands)) {
            $this->dispatch('notify', type: 'error', message: 'Please select at least one command to run!');
            return;
        }


        // Build command configuration with parameters
        $selectedCommandsData = [];
        foreach ($this->selectedCommands as $index) {
            if (isset($this->availableCommands[$index])) {
                $cmdData = $this->availableCommands[$index];
                
                // Extract base command (just the command name, without any existing parameters)
                $baseCommand = $cmdData['command'];
                $parts = preg_split('/\s+--/', $baseCommand, 2);
                $baseCommandName = trim($parts[0]);
                
                // Start with base command only (no existing parameters)
                $commandString = $baseCommandName;
                $params = $cmdData['params'] ?? [];
                
                // Remove any existing --tenant from params if commandTenantMasterId is set
                if ($this->commandTenantMasterId) {
                    unset($params['--tenant']);
                    unset($params['tenant']);
                }
                
                // Build command string with parameters from $params array
                foreach ($params as $key => $value) {
                    // Skip empty values
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    
                    // Clean key (remove '--' prefix if present)
                    $cleanKey = ltrim($key, '-');
                    
                    // Format date parameters to ensure correct format for the command
                    if ($this->isDateParameter($cleanKey) && is_string($value)) {
                        $value = $this->formatDateForCommand($value, $cmdData['command'], $cleanKey);
                    }
                    
                    // Add parameter to command string
                    if (is_bool($value)) {
                        if ($value) {
                            $commandString .= " --{$cleanKey}";
                        }
                    } else {
                        $commandString .= " --{$cleanKey}={$value}";
                    }
                }
                
                // If commandTenantMasterId is set, add --tenant parameter to commands that support it
                // Note: This should be the remote_tenant_id (RAI tenant_id), not tenant_master_id
                if ($this->commandTenantMasterId && strpos($commandString, '--tenant=') === false) {
                    $requiresTenant = $cmdData['requires_tenant'] ?? true;
                    if ($requiresTenant) {
                        $tenant = TenantMaster::find($this->commandTenantMasterId);
                        if ($tenant && $tenant->remote_tenant_id) {
                            $commandString .= " --tenant={$tenant->remote_tenant_id}";
                        }
                    }
                }
                
                // Store in format expected by raiops:run-schedule
                $selectedCommandsData[] = [
                    'command' => $commandString,
                    'retry' => $cmdData['retry'] ?? true,
                ];
            }
        }

        // Use commandTenantMasterId for execution, fallback to selectedTenantMasterId
        $executionTenantMasterId = $this->commandTenantMasterId ?? $this->selectedTenantMasterId;
        
        if (!$executionTenantMasterId) {
            $this->dispatch('notify', type: 'error', message: 'Please select a tenant for command execution!');
            return;
        }

        $tenant = TenantMaster::with('rdsInstance')->find($executionTenantMasterId);
        if (!$tenant || !$tenant->rdsInstance) {
            $this->dispatch('notify', type: 'error', message: 'Tenant has no RDS instance!');
            return;
        }

        // Create execution record
        $execution = CommandExecution::create([
            'command_name' => 'webhook:schedule',
            'user_id' => auth()->id(),
            'tenant_master_id' => $executionTenantMasterId,
            'rds_instance_id' => $tenant->rdsInstance->id,
            'triggered_by' => 'manual',
            'status' => 'pending',
            'started_at' => now(),
            'total_steps' => count($selectedCommandsData),
            'completed_steps' => 0,
        ]);

        // Build webhook URL from RDS instance app_url
        $webhookUrl = rtrim($tenant->rdsInstance->app_url, '/') . '/api/webhook/schedule';
        
        // Build callback URL for RAI to report progress back
        $callbackUrl = url('/api/webhook/schedule-callback');

        // Build webhook payload
        $webhookPayload = [
            'execution_id' => $execution->id,
            'tenant_id' => $tenant->remote_tenant_id,
            'commands' => $selectedCommandsData,
            'is_chain' => $this->isChain,
            'callback_url' => $callbackUrl,
        ];

        \Log::info("Sending schedule webhook to RAI", [
            'execution_id' => $execution->id,
            'tenant_master_id' => $executionTenantMasterId,
            'webhook_url' => $webhookUrl,
            'command_count' => count($selectedCommandsData),
        ]);

        try {
            // Send webhook with HMAC signature
            $response = $this->sendWebhook($webhookUrl, $webhookPayload);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
                // Update execution with PID if provided
                if (isset($responseData['pid'])) {
                    $execution->update([
                        'process_id' => $responseData['pid'],
                        'status' => 'running',
                    ]);
                } else {
                    $execution->update(['status' => 'running']);
                }
                
                $this->execution = $execution;
                $this->isRunning = true;
                $this->canRun = false;
                $this->activeTab = 'executions';
                $this->dispatch('notify', type: 'success', message: 'Schedule command started on RAI instance!');
            } else {
                $errorMessage = $response->json('error') ?? 'Unknown error from RAI';
                $execution->update([
                    'status' => 'failed',
                    'error' => "Webhook failed: {$errorMessage} (HTTP {$response->status()})",
                    'completed_at' => now(),
                ]);
                $this->checkStatus();
                $this->dispatch('notify', type: 'error', message: "Failed to start schedule: {$errorMessage}");
            }
        } catch (\Exception $e) {
            \Log::error("Schedule webhook failed", [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);
            
            $execution->update([
                'status' => 'failed',
                'error' => "Webhook exception: {$e->getMessage()}",
                'completed_at' => now(),
            ]);
            $this->checkStatus();
            $this->dispatch('notify', type: 'error', message: 'Failed to connect to RAI instance.');
        }
    }
    
    /**
     * Send webhook to RAI with HMAC signature
     */
    private function sendWebhook(string $url, array $payload): \Illuminate\Http\Client\Response
    {
        $secret = config('services.rai.webhook_secret');
        $timestamp = time();
        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp . '.' . $jsonPayload, $secret);
        
        return \Illuminate\Support\Facades\Http::withHeaders([
            'X-Webhook-Signature' => "{$timestamp}.{$signature}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url, $payload);
    }

    private function isProcessRunning($pid)
    {
        if (!$pid || !is_numeric($pid)) {
            return false;
        }
        $output = shell_exec("ps -p {$pid} -o pid= 2>/dev/null");
        return !empty(trim($output));
    }

    protected function getPhpBinaryPath(): string
    {
        if ($envPath = env('PHP_BINARY_PATH')) {
            if (file_exists($envPath) && is_executable($envPath)) {
                return $envPath;
            }
        }

        if (defined('PHP_BINARY') && PHP_BINARY) {
            $phpBinary = PHP_BINARY;
            if (str_contains($phpBinary, 'fpm')) {
                if (preg_match('/php-fpm(\d+\.?\d*)/', $phpBinary, $matches)) {
                    $version = $matches[1];
                    $cliPath = str_replace('php-fpm' . $version, 'php' . $version, $phpBinary);
                    if (file_exists($cliPath) && is_executable($cliPath)) {
                        return $cliPath;
                    }
                }
                $cliPath = str_replace('php-fpm', 'php', $phpBinary);
                if (file_exists($cliPath) && is_executable($cliPath)) {
                    return $cliPath;
                }
            } else {
                if (file_exists($phpBinary) && is_executable($phpBinary)) {
                    return $phpBinary;
                }
            }
        }

        $whichPhp = trim(shell_exec('which php 2>/dev/null') ?: '');
        if ($whichPhp && file_exists($whichPhp) && is_executable($whichPhp)) {
            return $whichPhp;
        }

        $commonPaths = [
            '/usr/bin/php8.3',
            '/usr/bin/php8.2',
            '/usr/bin/php8.1',
            '/usr/bin/php',
        ];
        
        foreach ($commonPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return '/usr/bin/php';
    }

    public function render()
    {
        return view('livewire.admin.custom-schedule-runner')
            ->layout('layouts.rai');
    }
}
