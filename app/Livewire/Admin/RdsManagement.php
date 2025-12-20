<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\RdsInstance;
use App\Models\AuditLog;
use App\Services\RdsConnectionService;

class RdsManagement extends Component
{
    use WithPagination;

    // Modal state
    public bool $showModal = false;
    public ?int $editId = null;

    // Form fields
    public string $name = '';
    public string $host = '';
    public int $port = 3306;
    public string $username = '';
    public string $password = '';
    public string $rai_database = '';
    public string $providers_database = '';
    public string $app_url = '';
    public bool $is_active = true;
    public bool $is_master = false;
    public string $notes = '';

    // Test connection result
    public ?array $testResult = null;

    // Search
    public string $search = '';

    protected $paginationTheme = 'bootstrap';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => $this->editId ? 'nullable|string' : 'required|string',
            'rai_database' => 'required|string|max:255',
            'providers_database' => 'nullable|string|max:255',
            'app_url' => 'required|url|max:255',
            'is_active' => 'boolean',
            'is_master' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }

    public function openModal(?int $id = null): void
    {
        $this->resetForm();
        $this->testResult = null;

        if ($id) {
            $rds = RdsInstance::findOrFail($id);
            $this->editId = $rds->id;
            $this->name = $rds->name;
            $this->host = $rds->host;
            $this->port = $rds->port;
            $this->username = $rds->username;
            $this->password = ''; // Don't populate password for security
            $this->rai_database = $rds->rai_database;
            $this->providers_database = $rds->providers_database ?? '';
            $this->app_url = $rds->app_url;
            $this->is_active = $rds->is_active;
            $this->is_master = $rds->is_master;
            $this->notes = $rds->notes ?? '';
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
        $this->testResult = null;
    }

    public function resetForm(): void
    {
        $this->editId = null;
        $this->name = '';
        $this->host = '';
        $this->port = 3306;
        $this->username = '';
        $this->password = '';
        $this->rai_database = '';
        $this->providers_database = '';
        $this->app_url = '';
        $this->is_active = true;
        $this->is_master = false;
        $this->notes = '';
        $this->resetValidation();
    }

    public function testConnection(): void
    {
        // Create a temporary RDS instance to test
        $rds = new RdsInstance([
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'rai_database' => $this->rai_database,
            'providers_database' => $this->providers_database,
        ]);

        // Use existing password if editing and no new password provided
        if ($this->editId && empty($this->password)) {
            $existing = RdsInstance::find($this->editId);
            $rds->attributes['password'] = $existing->getAttributes()['password'];
        } else {
            $rds->password = $this->password;
        }

        $this->testResult = $rds->testConnection();
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'rai_database' => $this->rai_database,
            'providers_database' => $this->providers_database ?: null,
            'app_url' => $this->app_url,
            'is_active' => $this->is_active,
            'is_master' => $this->is_master,
            'notes' => $this->notes ?: null,
        ];

        // Only update password if provided
        if (!empty($this->password)) {
            $data['password'] = $this->password;
        }

        if ($this->editId) {
            $rds = RdsInstance::findOrFail($this->editId);
            $oldValues = $rds->only(['name', 'host', 'port', 'username', 'rai_database', 'app_url', 'is_active', 'is_master']);
            $rds->update($data);

            AuditLog::log(
                'updated',
                'RdsInstance',
                $rds->id,
                $oldValues,
                $rds->only(['name', 'host', 'port', 'username', 'rai_database', 'app_url', 'is_active', 'is_master'])
            );

            session()->flash('success', "RDS Instance '{$rds->name}' updated successfully.");
        } else {
            $rds = RdsInstance::create($data);

            AuditLog::log(
                'created',
                'RdsInstance',
                $rds->id,
                null,
                $rds->only(['name', 'host', 'port', 'username', 'rai_database', 'app_url', 'is_active', 'is_master'])
            );

            session()->flash('success', "RDS Instance '{$rds->name}' created successfully.");
        }

        // If this is set as master, unset any other masters
        if ($this->is_master) {
            RdsInstance::where('id', '!=', $rds->id)
                ->where('is_master', true)
                ->update(['is_master' => false]);
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $rds = RdsInstance::findOrFail($id);

        // Don't allow deleting master RDS
        if ($rds->is_master) {
            session()->flash('error', 'Cannot delete the master RDS instance.');
            return;
        }

        // Check for associated tenants
        if ($rds->tenants()->count() > 0) {
            session()->flash('error', "Cannot delete RDS Instance '{$rds->name}' - it has associated tenants.");
            return;
        }

        $name = $rds->name;

        AuditLog::log(
            'deleted',
            'RdsInstance',
            $rds->id,
            $rds->only(['name', 'host', 'port', 'username', 'rai_database', 'app_url']),
            null
        );

        $rds->delete();

        session()->flash('success', "RDS Instance '{$name}' deleted successfully.");
    }

    public function refreshHealth(int $id): void
    {
        $rds = RdsInstance::findOrFail($id);
        $rds->updateHealthStatus();

        session()->flash('success', "Health status updated for '{$rds->name}'.");
    }

    public function refreshAllHealth(): void
    {
        $service = app(RdsConnectionService::class);
        $service->runHealthChecks();

        session()->flash('success', 'Health checks completed for all RDS instances.');
    }

    public function render()
    {
        $instances = RdsInstance::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('host', 'like', "%{$this->search}%");
            })
            ->withCount('tenants')
            ->orderBy('is_master', 'desc')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.rds-management', [
            'instances' => $instances,
        ])->layout('layouts.rai');
    }
}

