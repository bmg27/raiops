<?php

namespace App\Livewire\Admin;

use App\Models\RdsInstance;
use App\Services\RdsConnectionService;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class ScheduleManagement extends Component
{
    public $rdsInstances = [];
    public $selectedRdsId = null;
    public $commands = [];
    public $frequencies = [
        'hourly' => 'Hourly',
        '2hours' => 'Every 2 Hours',
        '4hours' => 'Every 4 Hours',
        '6hours' => 'Every 6 Hours',
        '12hours' => 'Every 12 Hours',
        'daily' => 'Daily (2am)',
        'weekly' => 'Weekly (Sunday 2am)',
    ];
    
    public $editingCommand = null;
    public $editFrequency = null;
    public $editEnabled = null;
    
    public $filterCategory = '';
    public $filterFrequency = '';
    public $showDisabled = true;
    public $searchQuery = '';
    public $collapsedCategories = [];

    protected RdsConnectionService $rdsService;

    public function boot(RdsConnectionService $rdsService)
    {
        $this->rdsService = $rdsService;
    }

    public function mount()
    {
        $this->rdsInstances = RdsInstance::orderBy('name')->get();
        
        // Default to first RDS
        if ($this->rdsInstances->isNotEmpty()) {
            $this->selectedRdsId = $this->rdsInstances->first()->id;
            $this->loadCommands();
        }
    }

    public function updatedSelectedRdsId()
    {
        $this->loadCommands();
    }

    public function loadCommands()
    {
        if (!$this->selectedRdsId) {
            $this->commands = [];
            return;
        }

        try {
            $rds = RdsInstance::find($this->selectedRdsId);
            if (!$rds) {
                $this->commands = [];
                return;
            }

            $db = $this->rdsService->query($rds);

            $query = $db->table('scheduled_commands')
                ->leftJoin('providers', 'scheduled_commands.provider_id', '=', 'providers.id')
                ->select(
                    'scheduled_commands.id',
                    'scheduled_commands.command_name',
                    'scheduled_commands.display_name',
                    'scheduled_commands.description',
                    'scheduled_commands.category',
                    'scheduled_commands.schedule_frequency',
                    'scheduled_commands.schedule_enabled',
                    'scheduled_commands.is_active',
                    'scheduled_commands.default_enabled',
                    'scheduled_commands.sort_order',
                    'providers.name as provider_name'
                )
                ->where('scheduled_commands.is_active', true);

            // Apply filters
            if ($this->filterCategory) {
                $query->where('scheduled_commands.category', $this->filterCategory);
            }
            
            if ($this->filterFrequency) {
                $query->where('scheduled_commands.schedule_frequency', $this->filterFrequency);
            }
            
            if (!$this->showDisabled) {
                $query->where('scheduled_commands.schedule_enabled', true);
            }

            $this->commands = $query
                ->orderBy('scheduled_commands.category')
                ->orderBy('scheduled_commands.sort_order')
                ->orderBy('scheduled_commands.display_name')
                ->get();

        } catch (\Exception $e) {
            Log::error("Failed to load scheduled commands", [
                'rds_id' => $this->selectedRdsId,
                'error' => $e->getMessage(),
            ]);
            $this->commands = [];
            $this->dispatch('notify', type: 'error', message: 'Failed to load commands: ' . $e->getMessage());
        }
    }

    public function getCategories()
    {
        return collect($this->commands)->pluck('category')->unique()->filter()->sort()->values();
    }

    public function toggleCategory($category)
    {
        if (in_array($category, $this->collapsedCategories)) {
            $this->collapsedCategories = array_values(array_diff($this->collapsedCategories, [$category]));
        } else {
            $this->collapsedCategories[] = $category;
        }
    }

    public function expandAll()
    {
        $this->collapsedCategories = [];
    }

    public function collapseAll()
    {
        $this->collapsedCategories = $this->getCategories()->toArray();
    }

    public function getFilteredCommands()
    {
        $commands = collect($this->commands);
        
        if ($this->searchQuery) {
            $search = strtolower($this->searchQuery);
            $commands = $commands->filter(function ($cmd) use ($search) {
                return str_contains(strtolower($cmd->display_name), $search) ||
                       str_contains(strtolower($cmd->command_name), $search) ||
                       str_contains(strtolower($cmd->description ?? ''), $search);
            });
        }
        
        return $commands;
    }

    public function openEditModal($commandId)
    {
        $command = collect($this->commands)->firstWhere('id', $commandId);
        if ($command) {
            $this->editingCommand = $command;
            $this->editFrequency = $command->schedule_frequency;
            $this->editEnabled = (bool) $command->schedule_enabled;
        }
    }

    public function closeEditModal()
    {
        $this->editingCommand = null;
        $this->editFrequency = null;
        $this->editEnabled = null;
    }

    public function saveCommand()
    {
        if (!$this->editingCommand) {
            return;
        }

        try {
            $rds = RdsInstance::find($this->selectedRdsId);
            $db = $this->rdsService->query($rds);

            $db->table('scheduled_commands')
                ->where('id', $this->editingCommand->id)
                ->update([
                    'schedule_frequency' => $this->editFrequency,
                    'schedule_enabled' => $this->editEnabled,
                    'updated_at' => now(),
                ]);

            $this->dispatch('notify', type: 'success', message: 'Schedule updated successfully!');
            $this->closeEditModal();
            $this->loadCommands();

        } catch (\Exception $e) {
            Log::error("Failed to update scheduled command", [
                'command_id' => $this->editingCommand->id,
                'error' => $e->getMessage(),
            ]);
            $this->dispatch('notify', type: 'error', message: 'Failed to save: ' . $e->getMessage());
        }
    }

    public function quickToggle($commandId)
    {
        try {
            $rds = RdsInstance::find($this->selectedRdsId);
            $db = $this->rdsService->query($rds);

            $command = collect($this->commands)->firstWhere('id', $commandId);
            $newEnabled = !$command->schedule_enabled;

            $db->table('scheduled_commands')
                ->where('id', $commandId)
                ->update([
                    'schedule_enabled' => $newEnabled,
                    'updated_at' => now(),
                ]);

            $this->loadCommands();
            $this->dispatch('notify', type: 'success', message: $newEnabled ? 'Command enabled' : 'Command disabled');

        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Failed to toggle: ' . $e->getMessage());
        }
    }

    public function quickSetFrequency($commandId, $frequency)
    {
        try {
            $rds = RdsInstance::find($this->selectedRdsId);
            $db = $this->rdsService->query($rds);

            $db->table('scheduled_commands')
                ->where('id', $commandId)
                ->update([
                    'schedule_frequency' => $frequency,
                    'updated_at' => now(),
                ]);

            $this->loadCommands();
            $this->dispatch('notify', type: 'success', message: 'Frequency updated!');

        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Failed to update: ' . $e->getMessage());
        }
    }

    public function bulkSetFrequency($frequency)
    {
        if (!$this->filterCategory) {
            $this->dispatch('notify', type: 'error', message: 'Please select a category first');
            return;
        }

        try {
            $rds = RdsInstance::find($this->selectedRdsId);
            $db = $this->rdsService->query($rds);

            $db->table('scheduled_commands')
                ->where('category', $this->filterCategory)
                ->where('is_active', true)
                ->update([
                    'schedule_frequency' => $frequency,
                    'updated_at' => now(),
                ]);

            $this->loadCommands();
            $this->dispatch('notify', type: 'success', message: "All {$this->filterCategory} commands set to {$frequency}");

        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Failed to bulk update: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.schedule-management', [
            'categories' => $this->getCategories(),
        ])->layout('layouts.rai');
    }
}
