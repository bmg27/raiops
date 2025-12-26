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
        
        // Super admins have full access (like RAI)
        // Also allow users with user.manage permission
        $hasAccess = auth()->check() && (
            auth()->user()->isSuperAdmin() ||
            auth()->user()->can('user.manage')
        );
        
        if (!$hasAccess && in_array($this->tab, ['permissions', 'menu_items', 'organize_menu'])) {
            $this->tab = 'users'; // Default to users tab
        }
    }
    public function render()
    {
        return view('livewire.permissions.rump-admin')
            ->layout('layouts.rai');
    }
}

