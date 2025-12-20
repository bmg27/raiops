<?php

namespace App\Livewire\Permissions;

use Livewire\Component;

class ManageMaster extends Component
{
    public string $tab = 'users';
    public $userId = null;

    public function mount($userId = null)
    {
        $user = \App\Models\User::find($userId);
        if($user) $this->userId = $userId; else $this->userId = null;
        
        // In RAINBO, all users are super admins, so we can show all tabs
        // But keep the check for consistency
        $isSuperAdmin = auth()->check() && auth()->user()->hasRole('Super Admin');
        if (!$isSuperAdmin && in_array($this->tab, ['permissions', 'menu_items', 'organize_menu'])) {
            $this->tab = 'users'; // Default to users tab
        }
    }
    public function render()
    {
        return view('livewire.permissions.rump-admin')
            ->layout('layouts.rai');
    }
}

