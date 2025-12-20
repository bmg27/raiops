<div>
    <!-- Tabs -->
    <ul class="nav nav-pills mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'users' ? 'active' : '' }}"
               wire:click="$set('tab','users')"
               style="cursor:pointer;">
                Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'roles' ? 'active' : '' }}"
               wire:click="$set('tab','roles')"
               style="cursor:pointer;">
                Roles
            </a>
        </li>
        @if(auth()->check() && auth()->user()->hasRole('Super Admin'))
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'permissions' ? 'active' : '' }}"
               wire:click="$set('tab','permissions')"
               style="cursor:pointer;">
                Permissions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'menu_items' ? 'active' : '' }}"
               wire:click="$set('tab','menu_items')"
               style="cursor:pointer;">
                Menu Items
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'organize_menu' ? 'active' : '' }}"
               wire:click="$set('tab','organize_menu')"
               style="cursor:pointer;">
                Organize Menu
            </a>
        </li>
        @endif
    </ul>

    <!-- Tab Content -->
    <div class="tab-content tab-content-pills">
        @if($tab === 'users')
            <div wire:key="tab-content-users">
                <livewire:permissions.users-index :userId="$userId"/>
            </div>
        @elseif($tab === 'roles')
            <div wire:key="tab-content-roles">
                <livewire:permissions.roles-index/>
            </div>
        @elseif($tab === 'permissions' && auth()->check() && auth()->user()->hasRole('Super Admin'))
            <div wire:key="tab-content-permissions">
                <livewire:permissions.permissions-index/>
            </div>
        @elseif($tab === 'menu_items' && auth()->check() && auth()->user()->hasRole('Super Admin'))
            <div wire:key="tab-content-menu-items">
                <livewire:permissions.menu-items-index/>
            </div>
        @elseif($tab === 'organize_menu' && auth()->check() && auth()->user()->hasRole('Super Admin'))
            <div wire:key="tab-content-organize-menu">
                <livewire:permissions.menu-organizer/>
            </div>
        @else
            {{-- Fallback for non-super admins trying to access admin tabs --}}
            <div class="alert alert-warning">
                You do not have permission to access this section. Redirecting to Users tab.
            </div>
            <script>
                setTimeout(function() {
                    @this.set('tab', 'users');
                }, 1000);
            </script>
        @endif
    </div>
</div>

